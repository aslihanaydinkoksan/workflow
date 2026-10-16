<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProcessInstance;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BoundaryTimerService
{
    public function __construct(
        protected TaskManager $taskManager,
        protected ProcessAuditService $auditService
    ) {}

    /**
     * Bekleyen görevler üzerindeki BPMN 2.0 Boundary Timer (Zaman Sınır Olayları) kurallarını denetler.
     *
     * @return array{scanned: int, escalated: int, auto_approved: int, reminded: int}
     */
    public function checkTaskTimers(bool $dryRun = false): array
    {
        $stats = [
            'scanned'       => 0,
            'escalated'     => 0,
            'auto_approved' => 0,
            'reminded'      => 0,
        ];

        $pendingTasks = Task::where('status', 'pending')
            ->with(['processInstance.workflow', 'assignedUser'])
            ->get();

        $engine = app(ProcessEngine::class);

        foreach ($pendingTasks as $task) {
            $instance = $task->processInstance;
            if (!$instance || !$instance->workflow) {
                continue;
            }

            $workflowNodes = collect($instance->workflow->nodes ?? []);
            $node = $workflowNodes->firstWhere('id', $task->node_id);
            if (!$node) {
                continue;
            }

            $nodeData = $node['data'] ?? [];
            $timerConfig = $nodeData['boundaryTimer'] ?? $nodeData['boundary_timer'] ?? null;

            // Timer aktif mi?
            $isEnabled = !empty($timerConfig['enabled']) || !empty($timerConfig['active']);
            if (!$isEnabled) {
                // Alternatif: Node doğrudan 'slaHours' veya 'timeoutHours' tanımlamış olabilir
                if (!empty($nodeData['slaHours']) && is_numeric($nodeData['slaHours'])) {
                    $timerConfig = [
                        'enabled'        => true,
                        'duration_hours' => (int) $nodeData['slaHours'],
                        'action'         => $nodeData['slaAction'] ?? 'notify_reminder',
                    ];
                } else {
                    continue;
                }
            }

            $stats['scanned']++;
            $durationHours = (int) ($timerConfig['duration_hours'] ?? $timerConfig['duration'] ?? 24);
            $action = $timerConfig['action'] ?? 'auto_escalate'; // auto_escalate, auto_approve, notify_reminder

            // Görevin oluşturulduğu andan itibaren geçen saat
            $createdAt = Carbon::parse($task->created_at);
            $elapsedHours = (int) $createdAt->diffInHours(Carbon::now());

            if ($elapsedHours < $durationHours) {
                continue;
            }

            $assignedUser = $task->assignedUser;
            $nodeName = $nodeData['customName'] ?? $nodeData['label'] ?? $task->node_id;

            if ($dryRun) {
                Log::info("[DRY-RUN] Boundary timer triggered for Task #{$task->id} ({$action}, {$elapsedHours}h >= {$durationHours}h)");
                continue;
            }

            // Aksiyonu Uygula
            if ($action === 'auto_escalate') {
                $stats['escalated']++;
                $this->executeAutoEscalation($task, $instance, $assignedUser, $nodeName, $durationHours);
            } elseif ($action === 'auto_approve') {
                $stats['auto_approved']++;
                $this->executeAutoApprove($task, $instance, $nodeName, $durationHours, $engine);
            } elseif ($action === 'notify_reminder') {
                $stats['reminded']++;
                $this->executeNotifyReminder($task, $instance, $assignedUser, $nodeName, $durationHours);
            }
        }

        return $stats;
    }

    /**
     * Süre aşımında görevi otomatik olarak kullanıcının amirine devreder (Escalate).
     */
    protected function executeAutoEscalation(Task $task, ProcessInstance $instance, ?User $assignedUser, string $nodeName, int $hours): void
    {
        $managerId = $assignedUser?->manager_id;
        $manager = $managerId ? User::find($managerId) : null;

        if (!$manager) {
            // Eğer amir bulunamazsa admin veya direktöre yönlendir
            $manager = User::whereHas('roles', fn($q) => $q->where('name', 'Admin'))->first();
        }

        if (!$manager || $manager->id === $task->assigned_to) {
            return;
        }

        $oldUserName = $assignedUser ? $assignedUser->name : 'Bilinmeyen Personel';
        $newUserName = $manager->name;

        // Görevi devret
        $task->update([
            'assigned_to' => $manager->id,
        ]);

        // Denetim İzi Kaydı
        $this->auditService->recordAdminIntervention(
            $instance,
            'boundary_timer_escalate',
            null,
            "BPMN 2.0 Boundary Timer Süre Aşımı Eskalasyonu",
            $oldUserName . " (Süre Aşımı: {$hours} Saat)",
            $newUserName . " (Üst Amir)",
            "'{$nodeName}' adımında tanımlı {$hours} saatlik SLA süresi aşıldığı için görev BPMN 2.0 Boundary Timer kuralı gereğince otomatik olarak üst amire eskale edilmiştir.",
            $task->id
        );

        // Bildirim
        UserNotification::create([
            'user_id' => $manager->id,
            'type'    => 'task_assigned',
            'title'   => "⚠️ Süre Aşımı Eskalasyonu: #{$instance->id} - {$nodeName}",
            'body'    => "'{$oldUserName}' personeline atanan görev {$hours} saat içinde tamamlanmadığı için size eskale edilmiştir.",
            'data'    => [
                'task_id'             => $task->id,
                'process_instance_id' => $instance->id,
                'action_url'          => route('tasks.show', $task->id),
            ],
        ]);

        // Kurumsal E-Posta Gönderimi (MailGuard Korumalı)
        if ($manager->email) {
            try {
                \Illuminate\Support\Facades\Mail::raw(
                    "'{$oldUserName}' personeline atanan görev {$hours} saat içinde tamamlanmadığı için BPMN 2.0 Boundary Timer kuralı gereğince size devredilmiştir.\n" .
                    "Süreç: #{$instance->id} - {$nodeName}\n" .
                    "İşlem Linki: " . route('tasks.show', $task->id),
                    function ($msg) use ($manager, $instance, $nodeName) {
                        $msg->to($manager->email)
                            ->subject("⚠️ Süre Aşımı Eskalasyonu: #{$instance->id} - {$nodeName}")
                            ->getHeaders()->addTextHeader('X-Mail-Event', 'boundary_timer_escalate');
                    }
                );
            } catch (\Throwable $e) {
                Log::warning("Boundary timer escalation mail failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Süre aşımında görevi sistem adına otomatik olarak onaylar ve süreci ilerletir.
     */
    protected function executeAutoApprove(Task $task, ProcessInstance $instance, string $nodeName, int $hours, ProcessEngine $engine): void
    {
        $comment = "BPMN 2.0 Boundary Timer: {$hours} saatlik onay süresi aşıldığı için sistem tarafından otomatik onaylandı.";

        // Görevi tamamla
        $this->taskManager->completeTask($task, $comment, 1);

        // Denetim İzi
        $this->auditService->recordAdminIntervention(
            $instance,
            'boundary_timer_auto_approve',
            null,
            "BPMN 2.0 Boundary Timer Otomatik Onay (Auto-Approve)",
            "Bekleyen: {$nodeName}",
            "Sistem Tarafından Onaylandı (Timeout: {$hours} Saat)",
            $comment,
            $task->id
        );

        // Süreci ilerlet
        $instance->update([
            'current_node_id' => $task->node_id,
            'status'          => 'running',
        ]);
        $engine->onTaskCompleted($instance, 'approved', $comment, $task->node_id);
    }

    /**
     * Süre aşımında acil SLA hatırlatması gönderir.
     */
    protected function executeNotifyReminder(Task $task, ProcessInstance $instance, ?User $assignedUser, string $nodeName, int $hours): void
    {
        $alreadySentToday = UserNotification::where('type', 'boundary_timer_warning')
            ->where('task_id', $task->id)
            ->whereDate('created_at', Carbon::today())
            ->exists();

        if ($alreadySentToday) {
            return;
        }

        if ($assignedUser) {
            UserNotification::create([
                'user_id' => $assignedUser->id,
                'type'    => 'boundary_timer_warning',
                'title'   => "🚨 Acil SLA Hatırlatması: #{$instance->id} - {$nodeName}",
                'body'    => "Görevinizin atanmasının üzerinden {$hours} saat geçti. Lütfen işlemi gecikmeksizin tamamlayınız.",
                'data'    => [
                    'task_id'             => $task->id,
                    'process_instance_id' => $instance->id,
                    'action_url'          => route('tasks.show', $task->id),
                ],
            ]);
        }

        // Amire de bilgi ver
        if ($assignedUser?->manager_id) {
            UserNotification::create([
                'user_id' => $assignedUser->manager_id,
                'type'    => 'boundary_timer_warning',
                'title'   => "⚠️ Geciken Personel Görevi: #{$instance->id} ({$assignedUser->name})",
                'body'    => "Ekibinizdeki '{$assignedUser->name}' personelinin '{$nodeName}' adımı {$hours} saattir beklemededir.",
                'data'    => [
                    'task_id'             => $task->id,
                    'process_instance_id' => $instance->id,
                    'action_url'          => route('processes.tracker', $instance->id),
                ],
            ]);
        }
    }
}
