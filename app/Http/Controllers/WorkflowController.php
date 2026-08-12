<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Workflow;
use App\Models\FormTemplate;
use App\Models\ProcessInstance;
use App\Models\Task;
use App\Models\User;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\WorkflowCategory;
use App\Services\WorkflowGraphNormalizer;
use App\Services\WorkflowRepairService;
use App\Services\ProcessEngine;
use Spatie\Permission\Models\Role;

class WorkflowController extends Controller
{
    public function __construct(
        private WorkflowGraphNormalizer $graphNormalizer,
        private WorkflowRepairService $repairService,
    ) {
    }

    public function index()
    {
        $user = auth()->user();
        $showProcessTracking = $user->hasRole('Admin')
            || $user->hasRole('superadmin')
            || $user->can('view_admin_panel');

        $workflows = Workflow::with([
            'formTemplate',
            'latestProcessInstance' => function ($query) {
                $query->with(['tasks' => function ($taskQuery) {
                    $taskQuery->where('status', 'pending')->with('assignedUser');
                }]);
            },
        ])
            ->latest()
            ->get()
            ->map(function (Workflow $workflow) use ($showProcessTracking) {
                $instance = $workflow->latestProcessInstance;

                $runtimeStatus = null;
                if ($instance) {
                    $runtimeStatus = match ($instance->status) {
                        'completed' => 'completed',
                        'cancelled' => 'cancelled',
                        default => 'in_progress',
                    };
                }

                $workflow->setAttribute('runtime_status', $runtimeStatus);
                $workflow->setAttribute('latest_instance_id', $instance?->id);
                $workflow->setAttribute('duration', $this->buildDurationSummary($instance));

                if ($showProcessTracking) {
                    $workflow->setAttribute('process_tracking', $this->buildProcessTracking($workflow, $instance));
                }

                return $workflow;
            });

        return Inertia::render('Workflow/Index', [
            'workflows' => $workflows,
            'showProcessTracking' => $showProcessTracking,
        ]);
    }

    public function create()
    {
        $forms = FormTemplate::where('is_active', true)->get();
        $users = User::select('id', 'name', 'email')->get();
        $departments = Department::select('id', 'name')->get();
        $directorates = Directorate::select('id', 'name')->get();
        $roles = Role::select('id', 'name')->get();
        $workflowCategories = WorkflowCategory::select('id', 'name')->get();
        
        return Inertia::render('Workflow/Designer', [
            'forms' => $forms,
            'users' => $users,
            'departments' => $departments,
            'directorates' => $directorates,
            'roles' => $roles,
            'workflowCategories' => $workflowCategories
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatedWorkflowPayload($request);

        $workflow = Workflow::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'allowed_departments' => $validated['allowed_departments'] ?? [],
            'allowed_roles' => $validated['allowed_roles'] ?? [],
            'allowed_users' => $validated['allowed_users'] ?? [],
            'valid_from' => $validated['valid_from'] ?? null,
            'valid_until' => $validated['valid_until'] ?? null,
            'form_template_id' => $validated['form_template_id'] ?? null,
            'nodes' => $validated['nodes'] ?? [],
            'edges' => $validated['edges'] ?? [],
            'status' => $validated['status'] ?? 'draft',
            'created_by' => auth()->id(),
        ]);

        return $this->redirectAfterSave($workflow, $validated, $request);
    }

    public function edit(Workflow $workflow)
    {
        $forms = FormTemplate::where('is_active', true)->get();
        $users = User::select('id', 'name', 'email')->get();
        $departments = Department::select('id', 'name')->get();
        $directorates = Directorate::select('id', 'name')->get();
        $roles = Role::select('id', 'name')->get();
        $workflowCategories = WorkflowCategory::select('id', 'name')->get();
        
        return Inertia::render('Workflow/Designer', [
            'workflow' => $workflow,
            'forms' => $forms,
            'users' => $users,
            'departments' => $departments,
            'directorates' => $directorates,
            'roles' => $roles,
            'workflowCategories' => $workflowCategories
        ]);
    }

    public function update(Request $request, Workflow $workflow)
    {
        $validated = $this->validatedWorkflowPayload($request);

        $workflow->update($validated);

        return $this->redirectAfterSave($workflow, $validated, $request);
    }

    private function redirectAfterSave(Workflow $workflow, array $validated, Request $request)
    {
        $message = 'Akış kaydedildi ve yayına alındı.';

        if (
            ($validated['status'] ?? '') === 'active'
            && $request->boolean('auto_start_process', true)
            && $this->repairService->hasRunnableGraph($validated['nodes'], $validated['edges'])
        ) {
            $instance = app(ProcessEngine::class)->startProcess($workflow->fresh(), (int) auth()->id(), []);
            $pendingCount = $instance->tasks()->where('status', 'pending')->count();

            $message = "Süreç otomatik başlatıldı (No: {$instance->id}). "
                . "{$pendingCount} kişiye ilk adım görevi atandı — ilgili kullanıcılar Görevlerim menüsünden görebilir. "
                . 'Sonraki adımlar, önceki onay tamamlanınca otomatik düşer.';
        }

        return redirect()->route('workflows.index')->with('success', $message);
    }

    private function validatedWorkflowPayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|array',
            'allowed_departments' => 'nullable|array',
            'allowed_roles' => 'nullable|array',
            'allowed_users' => 'nullable|array',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'form_template_id' => 'nullable|exists:form_templates,id',
            'nodes' => 'nullable|array',
            'edges' => 'nullable|array',
            'status' => 'nullable|in:draft,active,archived',
            'auto_start_process' => 'nullable|boolean',
        ]);

        $normalized = $this->graphNormalizer->normalize(
            $validated['nodes'] ?? [],
            $validated['edges'] ?? []
        );

        $validated['nodes'] = $normalized['nodes'];
        $validated['edges'] = $normalized['edges'];

        // Geçerli akış şeması varsa otomatik yayına al (taslak/aktif ayrımı kullanıcıyı yanıltmasın)
        if ($this->repairService->hasRunnableGraph($validated['nodes'], $validated['edges'])) {
            $validated['status'] = 'active';
        } elseif (empty($validated['status'])) {
            $validated['status'] = 'draft';
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildDurationSummary(?ProcessInstance $instance): ?array
    {
        if (! $instance?->created_at) {
            return null;
        }

        $start = $instance->created_at;
        $isCompleted = $instance->status === 'completed';
        $isCancelled = $instance->status === 'cancelled';
        $end = ($isCompleted || $isCancelled) ? ($instance->updated_at ?? now()) : now();

        $totalHours = max(0, (int) $start->diffInHours($end));
        $days = (int) $start->diffInDays($end);

        if ($isCancelled) {
            $label = $days > 0
                ? "{$days} günde iptal edildi"
                : ($totalHours > 0 ? "{$totalHours} saatte iptal edildi" : 'Bugün iptal edildi');
            $progress = 100;
        } elseif ($isCompleted) {
            $label = $days > 0
                ? "{$days} günde tamamlandı"
                : ($totalHours > 0 ? "{$totalHours} saatte tamamlandı" : 'Bugün tamamlandı');
            $progress = 100;
        } elseif ($days === 0) {
            $label = $totalHours < 1 ? 'Bugün başladı' : "{$totalHours} saattir devam ediyor";
            $progress = min(40, max(10, ($totalHours / 24) * 40));
        } elseif ($days === 1) {
            $label = '1 gündür devam ediyor';
            $progress = min(60, 35 + (($totalHours % 24) / 24) * 25);
        } else {
            $label = "{$days} gündür devam ediyor";
            $progress = min(95, max(20, ($days / 14) * 95));
        }

        return [
            'label' => $label,
            'days' => $days,
            'hours' => $totalHours,
            'progress' => (int) round($progress),
            'is_completed' => $isCompleted,
            'is_cancelled' => $isCancelled,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildProcessTracking(Workflow $workflow, ?ProcessInstance $instance): ?array
    {
        if (! $instance) {
            return null;
        }

        $nodes = collect($workflow->nodes ?? []);

        if ($instance->status === 'completed') {
            $endNode = $nodes->first(function ($node) {
                $taskType = $node['data']['taskType'] ?? null;
                $type = $node['type'] ?? null;

                return $taskType === 'end' || in_array($type, ['output', 'end'], true);
            });

            return [
                'current_node_label' => $endNode['data']['label'] ?? $endNode['label'] ?? 'Bitiş',
                'assignees' => [],
                'pending_task_count' => 0,
                'is_completed' => true,
            ];
        }

        if ($instance->status === 'cancelled') {
            return [
                'current_node_label' => 'İptal Edildi',
                'assignees' => [],
                'pending_task_count' => 0,
                'is_completed' => true,
                'is_cancelled' => true,
            ];
        }

        $pendingTasks = $instance->relationLoaded('tasks')
            ? $instance->tasks->where('status', 'pending')->values()
            : Task::query()
                ->where('process_instance_id', $instance->id)
                ->where('status', 'pending')
                ->with('assignedUser')
                ->get();

        $activeNodeId = $pendingTasks->first()?->node_id ?? $instance->current_node_id;
        $node = $nodes->firstWhere('id', $activeNodeId);
        $nodeLabel = $node['data']['label'] ?? $node['data']['customName'] ?? $node['label'] ?? 'Bilinmeyen Adım';

        $assignees = $pendingTasks
            ->map(function (Task $task) {
                if ($task->assigned_to && $task->assignedUser) {
                    return [
                        'name' => $task->assignedUser->name,
                        'type' => 'user',
                    ];
                }

                if ($task->assigned_role) {
                    return [
                        'name' => $task->assigned_role,
                        'type' => 'role',
                    ];
                }

                return [
                    'name' => 'Atanmamış',
                    'type' => 'unknown',
                ];
            })
            ->unique(fn (array $assignee) => $assignee['name'].'|'.$assignee['type'])
            ->values()
            ->all();

        return [
            'current_node_label' => $nodeLabel,
            'current_node_id' => $activeNodeId,
            'assignees' => $assignees,
            'pending_task_count' => $pendingTasks->count(),
            'is_completed' => false,
        ];
    }
}
