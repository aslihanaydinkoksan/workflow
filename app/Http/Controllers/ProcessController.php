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
            return $item->category ?: 'Diğer Formlar';
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

        return Inertia::render('Process/Start', [
            'workflow'       => $workflow,
            'prefilled_data' => $prefilledData // Frontend'e otomatik veri bağlama dizisi olarak gönderiliyor
        ]);
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
                ->with('success', 'Süreç başlatıldı. Size atanan görevi tamamlayabilirsiniz.');
        }

        return redirect()->route('processes.tracker', $instance->id)
            ->with('success', 'Süreç başarıyla başlatıldı. Akış No: ' . $instance->id);
    }

    public function history()
    {
        $instances = ProcessInstance::where('started_by', Auth::id())
            ->with(['workflow', 'tasks' => function ($q) {
                $q->where('status', 'pending')->with('assignedUser');
            }])
            ->latest()
            ->get();

        return Inertia::render('Process/History', [
            'instances' => $instances
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
            || ($user->can('processes.view_department') && $instance->starter->department_id === $user->department_id);

        if (!$canView) {
            abort(403);
        }

        $instance->load('workflow.formTemplate', 'starter');
        $instance->load(['tasks' => function ($q) {
            $q->where('status', 'pending')->with('assignedUser');
        }]);

        return Inertia::render('Process/Tracker', [
            'instance' => $instance,
            'processHistory' => $historyService->buildForInstance($instance),
            'canCancelProcess' => $this->userCanCancelProcess($user)
                && ! in_array($instance->status, ['completed', 'cancelled'], true),
        ]);
    }

    public function cancel(Request $request, ProcessInstance $instance, ProcessCancellationService $cancellationService)
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $this->userCanCancelProcess($user)) {
            abort(403, 'Süreç iptal etme yetkiniz bulunmamaktadır.');
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $cancellationService->cancel($instance, (int) $user->id, $validated['reason'] ?? null);

        return redirect()
            ->route('processes.tracker', $instance->id)
            ->with('success', 'Süreç başarıyla iptal edildi. Bekleyen tüm görevler kapatıldı.');
    }

    private function userCanCancelProcess(User $user): bool
    {
        return $user->can('processes.cancel');
    }

    public function department()
    {
        $user = Auth::user();

        // Bu kullanıcının bölümündeki kişilerin başlattığı süreçler
        $instances = ProcessInstance::whereHas('starter', function ($q) use ($user) {
            $q->where('department_id', $user->department_id);
        })
            ->with(['workflow', 'starter', 'tasks' => function ($q) {
                $q->where('status', 'pending')->with('assignedUser');
            }])
            ->latest()
            ->get();

        return Inertia::render('Process/Department', [
            'instances' => $instances,
            'departmentName' => $user->department ? $user->department->name : 'Bölümünüz'
        ]);
    }
}
