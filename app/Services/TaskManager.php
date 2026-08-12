<?php

namespace App\Services;

use App\Models\ProcessInstance;
use App\Models\Task;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use App\Mail\TaskAssignedMail;

class TaskManager
{
    /**
     * Bir düğüm için gerekli görev(ler)i oluşturur.
     *
     * @return Task[]
     */
    // Dependency Injection ile HierarchyResolverService'i dahil ediyoruz
    public function __construct(
        private readonly HierarchyResolverService $hierarchyResolver,
        private readonly NotificationService $notificationService, // Mevcutsa DI üzerinden almak daha temizdir
        private readonly DelegationService $delegationService
    ) {}
    public function createTasks(ProcessInstance $instance, string $nodeId, array $nodeData): array
    {
        $assignType = $nodeData['assignType'] ?? null;
        $taskType = $nodeData['taskType'] ?? 'approval';

        if ($assignType === 'role') {
            return $this->createTasksForRole($instance, $nodeId, $nodeData);
        }

        if (in_array($assignType, ['user', 'hierarchy', 'department', 'directorate', 'starter'], true)) {
            return [$this->createTask($instance, $nodeId, $nodeData)];
        }

        $allowedRoles = collect($instance->workflow->allowed_roles ?? [])
            ->filter(fn($roleId) => $roleId !== null && $roleId !== '')
            ->values()
            ->all();

        if (! empty($allowedRoles) && in_array($taskType, ['approval', 'form', 'review'], true)) {
            $tasks = [];
            foreach ($allowedRoles as $roleId) {
                $tasks = array_merge($tasks, $this->createTasksForRole($instance, $nodeId, array_merge($nodeData, [
                    'assignType' => 'role',
                    'assignValue' => $roleId,
                ])));
            }

            return $tasks;
        }

        return [$this->createTask($instance, $nodeId, $nodeData)];
    }

    public function createTask(ProcessInstance $instance, string $nodeId, array $nodeData): Task
    {
        $taskType = $nodeData['taskType'] ?? 'approval';

        // 1. Hedef Kullanıcıyı / Rolü Çözümle
        $resolution = $this->resolveAssignee($nodeData, $instance);

        $assignedTo = $resolution['assignedTo'];
        $assignedRoleId = $resolution['assignedRoleId'];
        $assignedRole = $resolution['assignedRole'];

        // 2. Fallback (Yedek) Atama Stratejileri
        $assignedTo = $this->applyFallbackAssignments($assignedTo, $assignedRoleId, $taskType, $instance->started_by);

        // 3. Vekalet (Delegation) Kontrolü
        if ($assignedTo) {
            $assignedTo = $this->delegationService->resolveAssignee((int) $assignedTo);
        }

        // 4. Görevi Oluştur
        $task = Task::create([
            'process_instance_id' => $instance->id,
            'node_id'             => $nodeId,
            'assigned_to'         => $assignedTo,
            'assigned_role'       => $assignedRole,
            'assigned_role_id'    => $assignedRoleId,
            'type'                => $taskType,
            'status'              => 'pending',
            'due_date'            => $this->resolveDueDate($nodeData),
        ]);

        // 5. Görev E-postası Gönderimi (Eğer atanmış bir kullanıcı ID'si varsa)
        if ($task->assigned_to) {
            // Eğer süreç kural motoru tarafından anında iptal/red edildiyse yeni görev maili atma!
            if (!in_array($instance->status, ['rejected', 'cancelled'])) {
                $user = User::find($task->assigned_to);
                if ($user && !empty($user->email)) {
                    Mail::to($user->email)->queue(new TaskAssignedMail($task));
                }
            }
        }

        return $task;
    }
    /**
     * Düğüm verisine göre ilgili kullanıcı ID'sini veya Rol bilgilerini çıkarır.
     */
    private function resolveAssignee(array $nodeData, ProcessInstance $instance): array
    {
        $result = [
            'assignedTo'     => null,
            'assignedRoleId' => null,
            'assignedRole'   => null,
        ];

        // Preset rol verilerini al
        if (!empty($nodeData['assignedRoleId'])) {
            $result['assignedRoleId'] = (int) $nodeData['assignedRoleId'];
            $result['assignedRole']   = $nodeData['assignedRoleName'] ?? null;
        }

        $assignType = $this->normalizeAssignType($nodeData);
        $assignValue = $nodeData['assignValue'] ?? null;

        // Dinamik hiyerarşi ve yapısal departman/direktörlük atamalarını servise delege et
        if (in_array($assignType, ['hierarchy', 'department', 'directorate', 'tree_relation'], true)) {
            $result['assignedTo'] = $this->hierarchyResolver->resolveTargetUser($nodeData, $instance->started_by);
        } elseif ($assignType === 'user') {
            $result['assignedTo'] = (int) $assignValue;
        } elseif ($assignType === 'starter') {
            $result['assignedTo'] = $instance->started_by;
        } elseif ($assignType === 'role') {
            $roleData = $this->resolveRoleAssignment($assignValue);
            $result['assignedRoleId'] = $roleData['id'] ?? $result['assignedRoleId'];
            $result['assignedRole']   = $roleData['name'] ?? $result['assignedRole'];
        }

        return $result;
    }
    /**
     * Eski ve yeni assignType yapılarını standartlaştırır.
     */
    private function normalizeAssignType(array $nodeData): ?string
    {
        $assignType = $nodeData['assignType'] ?? null;

        // Geriye Dönük Uyumluluk
        if (!$assignType && !empty($nodeData['role'])) {
            return in_array($nodeData['role'], ['manager_1', 'manager_2']) ? 'hierarchy' : 'role';
        }

        return $assignType;
    }
    /**
     * Rol atama çözümleyicisi
     */
    private function resolveRoleAssignment(mixed $assignValue): array
    {
        $role = is_numeric($assignValue)
            ? Role::find((int) $assignValue)
            : Role::where('name', $assignValue)->first();

        return [
            'id'   => $role?->id,
            'name' => $role ? $role->name : (is_string($assignValue) ? $assignValue : null),
        ];
    }
    /**
     * Hiçbir atama yapılamadıysa veya özel durumlarda son çare (fallback) olarak kimin atanacağına karar verir.
     */
    private function applyFallbackAssignments(?int $assignedTo, ?int $assignedRoleId, string $taskType, ?int $starterId): ?int
    {
        if ($assignedTo || $assignedRoleId) {
            return $assignedTo;
        }

        // Form görevleri varsayılan olarak başlatan kişiye atanır
        if ($taskType === 'form') {
            return $starterId;
        }

        // Onay/inceleme görevlerinde kimse bulunamadıysa hiyerarşik 1. amir denenir
        if (in_array($taskType, ['approval', 'review'], true)) {
            $starter = User::find($starterId);
            if ($starter?->manager_id) {
                return $starter->manager_id;
            }
        }

        // Hala atanamadıysa süreç tıkanmasın diye başlatan kişiye (starter) düşürülür
        return $starterId;
    }
    private function resolveDueDate(array $nodeData): ?Carbon
    {
        if (empty($nodeData['scheduleEnabled'])) {
            return null;
        }

        $days = max(0, (int) ($nodeData['scheduleDays'] ?? 0));
        $hours = max(0, (int) ($nodeData['scheduleHours'] ?? 0));
        $minutes = max(0, (int) ($nodeData['scheduleMinutes'] ?? 0));

        if ($days === 0 && $hours === 0 && $minutes === 0) {
            $value = (int) ($nodeData['scheduleValue'] ?? 0);
            if ($value < 1) {
                return null;
            }

            $unit = $nodeData['scheduleUnit'] ?? 'hours';

            return match ($unit) {
                'minutes' => now()->addMinutes($value),
                'days' => now()->addDays($value),
                default => now()->addHours($value),
            };
        }

        $due = now();

        if ($days > 0) {
            $due = $due->addDays($days);
        }

        if ($hours > 0) {
            $due = $due->addHours($hours);
        }

        if ($minutes > 0) {
            $due = $due->addMinutes($minutes);
        }

        return $due;
    }

    // private function resolveDelegation(int $userId): int
    // {
    //     $delegation = \App\Models\Delegation::where('delegator_id', $userId)
    //         ->where('status', 'active')
    //         ->where('start_date', '<=', now()->toDateString())
    //         ->where('end_date', '>=', now()->toDateString())
    //         ->first();

    //     // Eğer aktif vekalet varsa vekile ata, yoksa asıl kişiye
    //     return $delegation ? $delegation->delegatee_id : $userId;
    // }

    /**
     * Rol atamasında o role sahip her aktif kullanıcı için ayrı görev oluşturur.
     *
     * @return Task[]
     */
    public function createTasksForRole(ProcessInstance $instance, string $nodeId, array $nodeData): array
    {
        $assignValue = $nodeData['assignValue'] ?? null;
        $role = null;

        if (is_numeric($assignValue)) {
            $role = Role::find((int) $assignValue);
        } elseif ($assignValue) {
            $role = Role::where('name', $assignValue)->first();
        }

        if (! $role) {
            return [$this->createTask($instance, $nodeId, $nodeData)];
        }

        $users = User::query()
            ->whereHas('roles', fn($query) => $query->where('roles.id', $role->id))
            ->when(
                Schema::hasColumn('users', 'is_active'),
                fn($query) => $query->where(function ($inner) {
                    $inner->where('is_active', true)->orWhereNull('is_active');
                })
            )
            ->get();

        if ($users->isEmpty()) {
            return [$this->createTask($instance, $nodeId, array_merge($nodeData, [
                'assignType' => 'role',
                'assignValue' => (string) $role->id,
            ]))];
        }

        $tasks = [];
        foreach ($users as $user) {
            $tasks[] = $this->createTask($instance, $nodeId, array_merge($nodeData, [
                'assignType' => 'user',
                'assignValue' => (string) $user->id,
                'assignedRoleId' => $role->id,
                'assignedRoleName' => $role->name,
            ]));
        }

        return $tasks;
    }

    public function nodeHasPendingTasks(ProcessInstance $instance, string $nodeId): bool
    {
        return Task::query()
            ->where('process_instance_id', $instance->id)
            ->where('node_id', $nodeId)
            ->where('status', 'pending')
            ->exists();
    }

    public function instanceHasPendingTasks(ProcessInstance $instance): bool
    {
        return Task::query()
            ->where('process_instance_id', $instance->id)
            ->where('status', 'pending')
            ->exists();
    }

    public function cancelPendingTasksForNode(ProcessInstance $instance, string $nodeId, ?int $exceptTaskId = null): void
    {
        Task::query()
            ->where('process_instance_id', $instance->id)
            ->where('node_id', $nodeId)
            ->where('status', 'pending')
            ->when($exceptTaskId, fn($query) => $query->where('id', '!=', $exceptTaskId))
            ->update([
                'status' => 'cancelled',
                'completed_at' => now(),
            ]);
    }

    public function completeTask(Task $task, string $comment = null): void
    {
        $task->update([
            'status' => 'completed',
            'comment' => $comment,
            'completed_at' => now(),
        ]);
    }

    public function rejectTask(Task $task, string $comment = null): void
    {
        $task->update([
            'status' => 'rejected',
            'comment' => $comment,
            'completed_at' => now(),
        ]);
    }
}
