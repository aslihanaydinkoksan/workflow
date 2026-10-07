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
use App\Services\WorkflowMigrationService;
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

        $workflows = Workflow::with(['formTemplate', 'creator'])
            ->withCount([
                'processInstances as total_instances_count',
                'processInstances as active_instances_count' => function ($query) {
                    $query->whereIn('status', ['running', 'waiting']);
                },
                'processInstances as completed_instances_count' => function ($query) {
                    $query->where('status', 'completed');
                },
            ])
            ->latest()
            ->get()
            ->map(function (Workflow $workflow) {
                // Her bir akışın düğüm sayısı
                $nodes = $workflow->nodes ?? [];
                $workflow->setAttribute('node_count', count($nodes));

                // Kategori listesi (array veya string)
                $category = $workflow->category;
                if (is_string($category)) {
                    $decoded = json_decode($category, true);
                    $category = is_array($decoded) ? $decoded : [$category];
                }
                $workflow->setAttribute('categories_list', is_array($category) ? array_filter($category) : []);

                // Versiyon Bilgileri
                $workflow->setAttribute('version_number', $workflow->version ?: 1);
                $workflow->setAttribute('is_latest_version', (bool) ($workflow->is_latest ?? true));
                $rootId = $workflow->parent_workflow_id ?: $workflow->id;
                $workflow->setAttribute('root_workflow_id', $rootId);

                return $workflow;
            });

        // Tüm mevcut kategorileri filtreleme için toplayalım
        $categories = collect($workflows)
            ->flatMap(fn($w) => $w->categories_list)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $canManage = $user->hasRole('Admin')
            || $user->hasRole('superadmin')
            || $user->can('manage_workflows');

        return Inertia::render('Workflow/Index', [
            'workflows' => $workflows,
            'categories' => $categories,
            'canManage' => $canManage,
            'showProcessTracking' => $showProcessTracking,
        ]);
    }

    public function create()
    {
        $forms = FormTemplate::where('is_active', true)->get();
        $users = User::select('id', 'name', 'email', 'department_id', 'title')->get();
        $departments = Department::with(['allManagers' => function($q) {
            $q->select('users.id', 'users.name', 'users.email', 'users.title');
        }])->select('id', 'name')->get();
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
        $users = User::select('id', 'name', 'email', 'department_id', 'title')->get();
        $departments = Department::with(['allManagers' => function($q) {
            $q->select('users.id', 'users.name', 'users.email', 'users.title');
        }])->select('id', 'name')->get();
        $directorates = Directorate::select('id', 'name')->get();
        $roles = Role::select('id', 'name')->get();
        $workflowCategories = WorkflowCategory::select('id', 'name')->get();

        $allVersions = $workflow->allVersions()->map(fn($w) => [
            'id'            => $w->id,
            'version'       => $w->version ?: 1,
            'is_latest'     => (bool) ($w->is_latest ?? true),
            'status'        => $w->status,
            'version_notes' => $w->version_notes,
            'created_at'    => $w->created_at->format('d.m.Y H:i'),
            'node_count'    => count($w->nodes ?? []),
        ]);
        
        return Inertia::render('Workflow/Designer', [
            'workflow'           => $workflow,
            'forms'              => $forms,
            'users'              => $users,
            'departments'        => $departments,
            'directorates'       => $directorates,
            'roles'              => $roles,
            'workflowCategories' => $workflowCategories,
            'workflowVersions'   => $allVersions,
        ]);
    }

    public function update(Request $request, Workflow $workflow)
    {
        $validated = $this->validatedWorkflowPayload($request);

        $workflow->update($validated);

        return $this->redirectAfterSave($workflow, $validated, $request);
    }

    public function destroy(Workflow $workflow)
    {
        // 1. Devam eden canlı talep kontrolü
        $activeInstancesCount = $workflow->processInstances()
            ->whereIn('status', ['running', 'waiting'])
            ->count();

        if ($activeInstancesCount > 0) {
            return redirect()->back()->with('error', "Bu akış şablonuna bağlı yürüyen {$activeInstancesCount} adet canlı talep bulunmaktadır. Süreçler tamamlanmadan şablon silinemez. Dilerseniz şablonu 'Taslak' veya 'Arşivli' durumuna alabilirsiniz.");
        }

        $name = $workflow->name;
        $workflow->delete();

        return redirect()->route('workflows.index')->with('success', "'{$name}' adlı iş akış şablonu başarıyla silindi.");
    }

    public function toggleStatus(Workflow $workflow)
    {
        $newStatus = $workflow->status === 'active' ? 'draft' : 'active';
        $workflow->update(['status' => $newStatus]);

        $statusLabel = $newStatus === 'active' ? 'Yayına alındı (Aktif)' : 'Taslağa çekildi (Pasif)';
        return redirect()->back()->with('success', "'{$workflow->name}' şablonunun durumu '{$statusLabel}' olarak güncellendi.");
    }

    public function duplicate(Workflow $workflow)
    {
        $clone = $workflow->replicate();
        $clone->name = $workflow->name . ' (Kopya)';
        $clone->status = 'draft';
        $clone->created_by = auth()->id();
        $clone->created_at = now();
        $clone->updated_at = now();
        $clone->save();

        return redirect()->route('workflows.edit', $clone->id)->with('success', "'{$workflow->name}' şablonu başarıyla kopyalandı. Tasarımcı ekranından düzenleyebilirsiniz.");
    }

    /**
     * Akışı yeni bir versiyon olarak yayınlar (v+1) ve eskisini arşivler.
     */
    public function publishVersion(Request $request, Workflow $workflow)
    {
        $validated = $this->validatedWorkflowPayload($request);
        $versionNotes = $request->input('version_notes', 'Tasarımcı üzerinden yeni versiyon yayınlandı.');

        $newWorkflow = $workflow->createNextVersion($validated, $versionNotes, (int) auth()->id());

        if ($request->wantsJson()) {
            return response()->json([
                'success'      => true,
                'message'      => "Yeni versiyon (v{$newWorkflow->version}.0) başarıyla yayınlandı.",
                'workflow'     => $newWorkflow,
                'redirect_url' => route('workflows.edit', $newWorkflow->id),
            ]);
        }

        return redirect()->route('workflows.edit', $newWorkflow->id)
            ->with('success', "Yeni versiyon (v{$newWorkflow->version}.0) başarıyla yayınlandı. Eski versiyona bağlı canlı süreçler etkilenmeden çalışmaya devam edecektir.");
    }

    /**
     * Bu akış ailesine ait tüm versiyonları döner.
     */
    public function versions(Workflow $workflow)
    {
        $versions = $workflow->allVersions()->map(function (Workflow $w) {
            return [
                'id'                     => $w->id,
                'version'                => $w->version ?: 1,
                'is_latest'              => (bool) ($w->is_latest ?? true),
                'status'                 => $w->status,
                'version_notes'          => $w->version_notes,
                'created_at'             => $w->created_at->format('d.m.Y H:i'),
                'node_count'             => count($w->nodes ?? []),
                'active_instances_count' => $w->processInstances()->whereIn('status', ['running', 'waiting'])->count(),
                'total_instances_count'  => $w->processInstances()->count(),
            ];
        });

        return response()->json([
            'workflow_id' => $workflow->id,
            'root_id'     => $workflow->parent_workflow_id ?: $workflow->id,
            'versions'    => $versions,
        ]);
    }

    /**
     * Canlı süreç göçü için düğüm eşleştirme şemasını döner.
     */
    public function migrationSchema(Workflow $workflow, Request $request, WorkflowMigrationService $migrationService)
    {
        $targetWorkflowId = $request->input('target_workflow_id');
        $targetWorkflow = $targetWorkflowId 
            ? Workflow::findOrFail($targetWorkflowId) 
            : $workflow->allVersions()->firstWhere('is_latest', true);

        if (!$targetWorkflow) {
            $targetWorkflow = $workflow;
        }

        $schema = $migrationService->getMigrationSchema($workflow, $targetWorkflow);

        return response()->json($schema);
    }

    /**
     * Canlı çalışan süreçleri eski versiyondan yeni versiyona taşır.
     */
    public function migrateInstances(Request $request, Workflow $workflow, WorkflowMigrationService $migrationService)
    {
        $request->validate([
            'target_workflow_id' => 'required|exists:workflows,id',
            'node_mapping'       => 'required|array',
            'instance_ids'       => 'nullable|array',
        ]);

        $targetWorkflow = Workflow::findOrFail($request->input('target_workflow_id'));
        $nodeMapping = (array) $request->input('node_mapping');
        $instanceIds = $request->input('instance_ids');

        $result = $migrationService->migrateInstances(
            $workflow,
            $targetWorkflow,
            $nodeMapping,
            $instanceIds,
            (int) auth()->id()
        );

        $msg = "{$result['migrated_instances_count']} adet canlı süreç kesintisiz (Zero-Downtime) olarak v{$result['target_version']}.0 versiyonuna taşındı. {$result['updated_tasks_count']} aktif görev güncellendi.";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'data'    => $result,
            ]);
        }

        return redirect()->back()->with('success', $msg);
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
