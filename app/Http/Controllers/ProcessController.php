<?php

namespace App\Http\Controllers;

use App\Models\Workflow;
use App\Models\ProcessInstance;
use App\Services\ProcessEngine;
use App\Services\ProcessCancellationService;
use App\Services\ProcessHistoryService;
use App\Services\TaskVisibility;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\TerminRevision;
use App\Services\TerminSlaService;

class ProcessController extends Controller
{
    public function index()
    {
        /** @var User $user */
        $user = Auth::user();
        $userDeptId = (string) $user->department_id;
        $userRoleIds = $user->roles->pluck('id')->map(fn($id) => (string) $id)->toArray();
        $userId = (string) $user->id;

        // Başlatılabilecek akışlar
        $workflows = Workflow::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()->toDateString());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()->toDateString());
            })
            ->with('formTemplate')
            ->get();

        // Departman, Rol ve Kullanıcı Yetkisi Filtresi
        $filteredWorkflows = $workflows->filter(function ($workflow) use ($user) {
            return $this->userCanStartWorkflow($user, $workflow);
        });

        // Kategorilere göre grupla
        $grouped = $filteredWorkflows->groupBy(function ($item) {
            if (is_array($item->category)) {
                return !empty($item->category[0]) ? (string) $item->category[0] : 'Diğer Formlar';
            }
            return $item->category ? (string) $item->category : 'Diğer Formlar';
        });

        return Inertia::render('Process/Index', [
            'groupedWorkflows' => $grouped
        ]);
    }

    private function userCanStartWorkflow(User $user, Workflow $workflow): bool
    {
        if ($workflow->status !== 'active') return false;

        if ($workflow->valid_from && $workflow->valid_from > now()->toDateString()) return false;
        if ($workflow->valid_until && $workflow->valid_until < now()->toDateString()) return false;

        $userDeptId = (string) $user->department_id;
        $userRoleIds = $user->roles->pluck('id')->map(fn($id) => (string) $id)->toArray();
        $userId = (string) $user->id;

        $allowedDepts = $workflow->allowed_departments ?? [];
        $allowedRoles = $workflow->allowed_roles ?? [];
        $allowedUsers = $workflow->allowed_users ?? [];

        // Hiç kısıt yoksa herkes başlatabilir
        if (empty($allowedDepts) && empty($allowedRoles) && empty($allowedUsers)) {
            return true;
        }

        // Departman, rol veya kullanıcı listesinden herhangi biri eşleşirse yeterli (VE değil VEYA)
        if (! empty($allowedDepts)) {
            if (
                in_array('Tümü', $allowedDepts, true)
                || in_array($userDeptId, $allowedDepts, true)
                || in_array((int) $userDeptId, $allowedDepts, true)
            ) {
                return true;
            }
        }

        if (! empty($allowedRoles) && count(array_intersect($userRoleIds, $allowedRoles)) > 0) {
            return true;
        }

        if (
            ! empty($allowedUsers)
            && (in_array($userId, $allowedUsers, true) || in_array((int) $userId, $allowedUsers, true))
        ) {
            return true;
        }

        return false;
    }

    public function create(Workflow $workflow, \App\Services\FormRenderingService $renderingService)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$this->userCanStartWorkflow($user, $workflow)) {
            abort(403, 'Bu süreci başlatma yetkiniz bulunmamaktadır.');
        }

        $workflow->load('formTemplate');

        // Form şemasını al ve kullanıcının hiyerarşi/sicil bilgileriyle eşleştir
        $schema = $workflow->formTemplate->schema ?? [];
        $prefilledData = $renderingService->getPrefilledData($schema, $user->id);

        // Klonlama / Benzer Talep Başlatma: Geçmiş bir talebin verilerini aktar
        if (request()->has('clone_from')) {
            $cloneInstance = ProcessInstance::find(request('clone_from'));
            if ($cloneInstance && is_array($cloneInstance->data)) {
                $cloneData = $cloneInstance->data;
                unset($cloneData['_cancellation'], $cloneData['_force_rejected'], $cloneData['_last_updated_at'], $cloneData['_last_updated_by']);
                $prefilledData = array_merge($prefilledData, $cloneData);
            }
        }

        return Inertia::render('Process/Start', [
            'workflow'       => $workflow,
            'prefilled_data' => $prefilledData
        ]);
    }

    public function edit(ProcessInstance $instance)
    {
        $user = Auth::user();

        if ($instance->started_by !== $user->id && !$user->hasRole('Admin') && !$user->hasRole('superadmin')) {
            abort(403, 'Bu talebi düzenleme yetkiniz bulunmamaktadır.');
        }

        // Onay zincirinde herhangi bir görev tamamlanmışsa doğrudan düzenleme yapılamaz!
        $completedTasksCount = $instance->tasks()->where('status', 'completed')->count();
        if ($completedTasksCount > 0 || in_array($instance->status, ['completed', 'cancelled'])) {
            return redirect()->route('processes.tracker', $instance->id)
                ->with('error', 'Bu talep onay zincirinde ilerlediği veya tamamlandığı için form verileri doğrudan değiştirilemez.');
        }

        $instance->load('workflow.formTemplate');

        return Inertia::render('Process/Start', [
            'workflow'       => $instance->workflow,
            'prefilled_data' => $instance->data ?? [],
            'is_editing'     => true,
            'instance_id'    => $instance->id,
        ]);
    }

    public function update(Request $request, ProcessInstance $instance)
    {
        $user = Auth::user();

        if ($instance->started_by !== $user->id && !$user->hasRole('Admin') && !$user->hasRole('superadmin')) {
            abort(403, 'Bu talebi düzenleme yetkiniz bulunmamaktadır.');
        }

        $completedTasksCount = $instance->tasks()->where('status', 'completed')->count();
        if ($completedTasksCount > 0 || in_array($instance->status, ['completed', 'cancelled'])) {
            return redirect()->route('processes.tracker', $instance->id)
                ->with('error', 'Bu talep onay zincirinde ilerlediği veya tamamlandığı için form verileri doğrudan değiştirilemez.');
        }

        $validatedData = $request->input('answers', []);
        $oldData = (array) ($instance->data ?? []);
        $mergedData = array_merge($oldData, $validatedData);
        $mergedData['_last_updated_at'] = now()->toIso8601String();
        $mergedData['_last_updated_by'] = $user->id;

        $instance->update(['data' => $mergedData]);

        // Denetim İzi (Audit Diff): Talep sahibi düzenlemesini kaydet
        app(\App\Services\ProcessAuditService::class)->recordDiff(
            $instance,
            $oldData,
            $validatedData,
            $user->id,
            'form_update',
            null,
            'Talep Sahibi Ön Düzenlemesi'
        );

        return redirect()->route('processes.tracker', $instance->id)
            ->with('success', 'Talep form verileri başarıyla güncellendi.');
    }

    public function store(Request $request, Workflow $workflow, ProcessEngine $engine)
    {
        if (!$this->userCanStartWorkflow(Auth::user(), $workflow)) {
            abort(403, 'Bu süreci başlatma yetkiniz bulunmamaktadır.');
        }

        $validatedData = $request->input('answers', []);

        $instance = $engine->startProcess($workflow, Auth::id(), $validatedData);

        $pendingTask = TaskVisibility::queryForUser(Auth::user())
            ->where('process_instance_id', $instance->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($pendingTask) {
            return redirect()->route('tasks.show', $pendingTask)
                ->with('success', 'Süreç başlatıldı. İlk görev size atandığı için doğrudan görev ekranına yönlendirildiniz.');
        }

        return redirect()->route('processes.tracker', $instance->id)
            ->with('success', 'Süreç başarıyla başlatıldı ve ilgili onay/işlem birimine iletildi. (Talep No: #' . $instance->id . ')');
    }

    public function history(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // 1. Kullanıcının Kendi Başlattığı Talepler
        $instances = ProcessInstance::where('started_by', $user->id)
            ->with(['workflow.formTemplate', 'starter', 'tasks' => function ($q) {
                $q->where('status', 'pending')->with('assignedUser');
            }])
            ->withCount(['tasks as completed_tasks_count' => function ($q) {
                $q->where('status', 'completed');
            }])
            ->latest()
            ->get()
            ->map(function ($instance) use ($user) {
                $isPending = in_array($instance->status, ['running', 'waiting']);
                $instance->setAttribute('can_cancel', $this->userCanCancelProcess($user, $instance));
                $instance->setAttribute('can_edit', $isPending && $instance->completed_tasks_count === 0);
                return $instance;
            });

        // 2. Bölüm Süreçleri (Yetkili kullanıcılar veya amirler için)
        $canViewDepartment = $user->hasRole('Admin') || $user->hasRole('superadmin') || $user->can('processes.view_department');
        $departmentInstances = [];

        if ($canViewDepartment) {
            $depQuery = ProcessInstance::query();

            if ($user->department_id) {
                $depQuery->whereHas('starter', function ($q) use ($user) {
                    $q->where('department_id', $user->department_id);
                });
            }

            $departmentInstances = $depQuery
                ->with(['workflow.formTemplate', 'starter', 'tasks' => function ($q) {
                    $q->where('status', 'pending')->with('assignedUser');
                }])
                ->withCount(['tasks as completed_tasks_count' => function ($q) {
                    $q->where('status', 'completed');
                }])
                ->latest()
                ->get()
                ->map(function ($instance) use ($user) {
                    $isPending = in_array($instance->status, ['running', 'waiting']);
                    $instance->setAttribute('can_cancel', $this->userCanCancelProcess($user, $instance));
                    $instance->setAttribute('can_edit', $isPending && $instance->completed_tasks_count === 0);
                    return $instance;
                });
        }

        $initialScope = $request->query('scope', 'my');

        return Inertia::render('Process/History', [
            'instances' => $instances,
            'departmentInstances' => $departmentInstances,
            'canViewDepartment' => $canViewDepartment,
            'departmentName' => $user->department ? $user->department->name : 'Bölümünüz',
            'initialScope' => $initialScope,
        ]);
    }

    public function tracker(ProcessInstance $instance, ProcessHistoryService $historyService)
    {
        /** @var User $user */
        $user = Auth::user();

        $canView = $instance->started_by === $user->id
            || $user->hasRole('Admin')
            || $user->hasRole('superadmin')
            || $user->can('view_admin_panel')
            || ($user->can('processes.view_department') && $instance->starter?->department_id === $user->department_id)
            || $instance->tasks()->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('completed_by', $user->id);
            })->exists();

        if (!$canView) {
            abort(403, 'Bu süreci görüntüleme yetkiniz bulunmamaktadır.');
        }

        $instance->load('workflow.formTemplate', 'starter');
        $instance->load(['tasks' => function ($q) {
            $q->where('status', 'pending')->with('assignedUser');
        }]);

        $myPendingTask = TaskVisibility::queryForUser($user)
            ->where('process_instance_id', $instance->id)
            ->where('status', 'pending')
            ->first();

        $followUp = \App\Models\FollowUp::where('process_instance_id', $instance->id)->latest()->first();
        $expenseWorkflow = Workflow::where('name', 'like', '%Harcırah%')->orWhere('name', 'like', '%Masraf%')->first();

        $slaService = app(TerminSlaService::class);
        $terminSla = $slaService->calculateSla($instance);
        $terminRevisions = $instance->terminRevisions()->with('requestedBy')->get();
        $terminCategories = TerminRevision::CATEGORIES;

        $isAdmin = $user->hasRole('Admin')
            || $user->hasRole('superadmin')
            || $user->can('view_admin_panel');

        $canReschedule = $isAdmin
            || $instance->tasks()->where('assigned_to', $user->id)->exists()
            || $user->department_id === 23;

        $fieldAudits = app(\App\Services\ProcessAuditService::class)->getAuditsForInstance($instance);

        $allUsers = $isAdmin
            ? User::select('id', 'name', 'email', 'department_id')
                ->with('department:id,name')
                ->where(function ($q) {
                    $q->where('is_active', true)->orWhereNull('is_active');
                })
                ->orderBy('name')
                ->get()
            : [];

        $workflowNodes = ($isAdmin && $instance->workflow)
            ? collect($instance->workflow->nodes ?? [])->map(function ($n) {
                return [
                    'id' => $n['id'],
                    'label' => $n['data']['customName'] ?? $n['data']['label'] ?? $n['id'],
                    'taskType' => $n['data']['taskType'] ?? $n['type'] ?? 'task',
                ];
            })->values()->all()
            : [];

        return Inertia::render('Process/Tracker', [
            'instance' => $instance,
            'myPendingTask' => $myPendingTask,
            'followUp' => $followUp,
            'expenseWorkflowId' => $expenseWorkflow?->id,
            'processHistory' => $historyService->buildForInstance($instance),
            'terminSla' => $terminSla,
            'terminRevisions' => $terminRevisions,
            'terminCategories' => $terminCategories,
            'canRescheduleTermin' => $canReschedule,
            'canCancelProcess' => $this->userCanCancelProcess($user, $instance)
                && ! in_array($instance->status, ['completed', 'cancelled'], true),
            'fieldAudits' => $fieldAudits,
            'isAdmin' => $isAdmin,
            'allUsers' => $allUsers,
            'workflowNodes' => $workflowNodes,
        ]);
    }

    public function rescheduleTermin(Request $request, ProcessInstance $instance, TerminSlaService $slaService)
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'new_termin' => 'required|string|min:4|max:255',
            'reason_category' => 'required|string|max:50',
            'reason_note' => 'required|string|min:5|max:2000',
        ]);

        $slaService->rescheduleTermin(
            $instance,
            $user,
            $validated['new_termin'],
            $validated['reason_category'],
            $validated['reason_note']
        );

        return redirect()->back()->with('success', 'Termin taahhüdü başarıyla revize edildi ve tüm birimlere resmi bildirim iletildi.');
    }

    public function cancel(Request $request, ProcessInstance $instance, ProcessCancellationService $cancellationService)
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $this->userCanCancelProcess($user, $instance)) {
            abort(403, 'Süreç iptal etme yetkiniz bulunmamaktadır.');
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $cancellationService->cancel($instance, (int) $user->id, $validated['reason'] ?? null);

        return redirect()
            ->back()
            ->with('success', 'Süreç başarıyla geri çekildi / iptal edildi. Bekleyen tüm görevler kapatıldı.');
    }

    private function userCanCancelProcess(User $user, ?ProcessInstance $instance = null): bool
    {
        if ($user->can('processes.cancel')) {
            return true;
        }

        if ($instance && $instance->started_by === $user->id && in_array($instance->status, ['running', 'waiting'])) {
            return true;
        }

        return false;
    }

    public function department(Request $request)
    {
        return redirect()->route('processes.history', ['scope' => 'department']);
    }

    public function exportCertificate(ProcessInstance $instance, \App\Services\CertificateGenerator $generator)
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('Admin') || $user->hasRole('superadmin');

        // Kullanıcı süreci başlatan kişi, admin veya süreçteki bir göreve atanmış kişi ise indirebilir
        $isRelated = $isAdmin
            || $instance->started_by === $user->id
            || $instance->tasks()->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('completed_by', $user->id);
            })->exists();

        if (!$isRelated) {
            abort(403, 'Bu analiz sertifikasını görüntüleme yetkiniz bulunmamaktadır.');
        }

        $pdf = $generator->generate($instance);
        $fileName = 'Analiz-Sertifikasi-PI-' . $instance->id . '.pdf';

        return $pdf->download($fileName);
    }

    public function exportTravelOrder(ProcessInstance $instance, \App\Services\TravelOrderGenerator $generator)
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('Admin') || $user->hasRole('superadmin');

        $isRelated = $isAdmin
            || $instance->started_by === $user->id
            || $instance->tasks()->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('completed_by', $user->id);
            })->exists();

        if (!$isRelated) {
            abort(403, 'Bu dış görev belgesini görüntüleme yetkiniz bulunmamaktadır.');
        }

        $pdf = $generator->generate($instance);
        $fileName = 'Dis-Gorev-Belgesi-PI-' . $instance->id . '.pdf';

        return $pdf->download($fileName);
    }

    public function adminReassignTask(Request $request, ProcessInstance $instance)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole('Admin') && !$user->hasRole('superadmin') && !$user->can('view_admin_panel')) {
            abort(403, 'Yönetici müdahale yetkiniz bulunmamaktadır.');
        }

        $validated = $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'new_user_id' => 'required|exists:users,id',
            'reason' => 'required|string|min:3|max:1000',
        ]);

        $task = $instance->tasks()->where('id', $validated['task_id'])->firstOrFail();
        if ($task->status !== 'pending') {
            return back()->with('error', 'Sadece bekleyen görevler yeniden atanabilir.');
        }

        $newUser = User::findOrFail($validated['new_user_id']);
        $oldAssigneeName = $task->assignedUser?->name ?? $task->assigned_role ?? 'İlgili Birim';

        $task->update([
            'assigned_to' => $newUser->id,
            'assigned_role' => null,
        ]);

        app(\App\Services\ProcessAuditService::class)->recordAdminIntervention(
            $instance,
            'admin_reassign',
            $user->id,
            "Görev Yeniden Atandı: {$task->title}",
            $oldAssigneeName,
            $newUser->name,
            $validated['reason'],
            $task->id
        );

        \App\Models\UserNotification::create([
            'user_id' => $newUser->id,
            'type'    => 'task_assigned',
            'title'   => 'Yönetici Tarafından Görev Yeniden Atandı',
            'body'    => "Yönetici ({$user->name}) tarafından size '{$task->title}' görevi devredilmiştir. Gerekçe: {$validated['reason']}",
            'data'    => [
                'url' => route('tasks.show', $task->id),
                'action_url' => route('tasks.show', $task->id),
                'task_id' => $task->id,
                'process_instance_id' => $instance->id,
            ],
        ]);

        return back()->with('success', "Görev başarıyla {$newUser->name} personeline yeniden atandı.");
    }

    public function adminJumpNode(Request $request, ProcessInstance $instance, \App\Services\TaskManager $taskManager)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole('Admin') && !$user->hasRole('superadmin') && !$user->can('view_admin_panel')) {
            abort(403, 'Yönetici müdahale yetkiniz bulunmamaktadır.');
        }

        $validated = $request->validate([
            'target_node_id' => 'required|string',
            'reason' => 'required|string|min:3|max:1000',
        ]);

        $nodes = collect($instance->workflow->nodes ?? []);
        $targetNode = $nodes->firstWhere('id', $validated['target_node_id']);
        if (!$targetNode) {
            return back()->with('error', 'Seçilen hedef adım iş akışı tasarımında bulunamadı.');
        }

        $oldNode = $nodes->firstWhere('id', $instance->current_node_id);
        $oldNodeLabel = $oldNode['data']['customName'] ?? $oldNode['data']['label'] ?? $instance->current_node_id ?? 'Mevcut Adım';
        $targetNodeLabel = $targetNode['data']['customName'] ?? $targetNode['data']['label'] ?? $targetNode['id'];

        // Mevcut bekleyen görevleri iptal et
        $instance->tasks()->where('status', 'pending')->update([
            'status' => 'cancelled',
            'comment' => "[Yönetici Müdahalesi] Adım Değişimi: {$validated['reason']}",
        ]);

        $instance->update([
            'current_node_id' => $targetNode['id'],
            'status' => 'running',
        ]);

        // Hedef düğüm için görevleri üret
        $taskManager->createTasks($instance, $targetNode['id'], $targetNode['data'] ?? []);

        app(\App\Services\ProcessAuditService::class)->recordAdminIntervention(
            $instance,
            'admin_jump',
            $user->id,
            "Süreç Adımı Taşındı",
            $oldNodeLabel,
            $targetNodeLabel,
            $validated['reason']
        );

        return back()->with('success', "Süreç başarıyla '{$targetNodeLabel}' adımına taşındı ve yeni görevler atandı.");
    }

    public function adminForceProgress(Request $request, ProcessInstance $instance, ProcessEngine $engine)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole('Admin') && !$user->hasRole('superadmin') && !$user->can('view_admin_panel')) {
            abort(403, 'Yönetici müdahale yetkiniz bulunmamaktadır.');
        }

        $validated = $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'reason' => 'required|string|min:3|max:1000',
        ]);

        $task = $instance->tasks()->where('id', $validated['task_id'])->firstOrFail();
        if ($task->status !== 'pending') {
            return back()->with('error', 'Sadece bekleyen görevler zorla ilerletilebilir.');
        }

        $task->update([
            'status' => 'completed',
            'completed_by' => $user->id,
            'completed_at' => now(),
            'comment' => "[Yönetici Müdahalesi / Force Complete] {$validated['reason']}",
        ]);

        app(\App\Services\ProcessAuditService::class)->recordAdminIntervention(
            $instance,
            'admin_force',
            $user->id,
            "Yönetici Tarafından Tamamlandı: {$task->title}",
            'Bekliyor',
            'Zorla Onaylandı / İlerletildi',
            $validated['reason'],
            $task->id
        );

        $engine->onTaskCompleted($instance, 'approve', $validated['reason']);

        return back()->with('success', 'Görev yönetici yetkisiyle onaylandı ve süreç bir sonraki adıma ilerletildi.');
    }
}
