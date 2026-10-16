<?php

namespace App\Console\Commands;

use App\Models\ProcessInstance;
use App\Models\UserNotification;
use App\Services\TerminSlaService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckTerminSlaCommand extends Command
{
    protected $signature = 'termin:check-sla {--dry-run : Sadece tarama yap, bildirim gönderme}';

    protected $description = 'Aktif süreçlerin termin taahhütlerini (SLA) denetler; yaklaşan ve geciken süreçler için eskalasyon alarmlarını tetikler';

    public function handle(TerminSlaService $slaService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->info("🔍 KÖKSAN TERMIN & SLA ESKALASYON DENETLEYİCİSİ ÇALIŞTIRILIYOR..." . ($dryRun ? ' (DRY-RUN)' : ''));

        $activeInstances = ProcessInstance::whereIn('status', ['running', 'waiting'])
            ->with(['workflow', 'starter', 'tasks' => function ($q) {
                $q->where('status', 'pending')->with('assignedUser');
            }])
            ->get();

        $scannedCount = 0;
        $warningCount = 0;
        $overdueCount = 0;

        foreach ($activeInstances as $instance) {
            $sla = $slaService->calculateSla($instance);
            if (!$sla || empty($sla['has_termin'])) {
                continue;
            }

            $scannedCount++;
            $status = $sla['sla_status'];
            $workflowName = $instance->workflow?->name ?? 'İş Akışı';
            $starter = $instance->starter;
            $terminText = $sla['termin_text'];
            $targetDate = $sla['target_date_formatted'];

            if ($status === 'warning') {
                $warningCount++;
                $this->warn("⚠️  [YAKLAŞIYOR] #{$instance->id} - {$workflowName} | Termin: {$terminText} | Kalan: {$sla['hours_remaining']} saat");

                if (!$dryRun) {
                    $this->triggerWarningNotification($instance, $sla);
                }
            } elseif (in_array($status, ['overdue', 'critical'], true)) {
                $overdueCount++;
                $days = abs($sla['days_remaining']);
                $this->error("🚨 [SLA İHLALİ] #{$instance->id} - {$workflowName} | Termin: {$terminText} | Gecikme: {$days} gün");

                if (!$dryRun) {
                    $this->triggerOverdueEscalation($instance, $sla);
                }
            }
        }

        $this->info("\n⏱️  BPMN 2.0 BOUNDARY TIMER (ZAMAN SINIR OLAYLARI) DENETLENİYOR...");
        $timerService = app(\App\Services\BoundaryTimerService::class);
        $timerStats = $timerService->checkTaskTimers($dryRun);

        $this->info("\n📊 DENETİM ÖZETİ:");
        $this->info("   Toplam Taranan Terminli Süreç: {$scannedCount}");
        $this->info("   Yaklaşan (Son 48 Saat)        : {$warningCount}");
        $this->info("   Geciken (SLA İhlali)          : {$overdueCount}");
        $this->info("   Boundary Timer Taranan Görev  : {$timerStats['scanned']}");
        $this->info("   Boundary Timer Eskale Edilen  : {$timerStats['escalated']}");
        $this->info("   Boundary Timer Otomatik Onay  : {$timerStats['auto_approved']}");
        $this->info("   Boundary Timer Hatırlatılan   : {$timerStats['reminded']}");

        return self::SUCCESS;
    }

    private function triggerWarningNotification(ProcessInstance $instance, array $sla): void
    {
        $todayKey = 'termin_warning_' . Carbon::today()->format('Y-m-d');
        
        // Bugün zaten uyarı gönderildiyse tekrar gönderme
        $alreadySent = UserNotification::where('type', 'termin_warning')
            ->where('title', 'like', "%#{$instance->id}%")
            ->whereDate('created_at', Carbon::today())
            ->exists();

        if ($alreadySent) {
            return;
        }

        $title = "⏳ Termin Yaklaşıyor: #{$instance->id}";
        $body = "Dikkat: #{$instance->id} numaralı {$instance->workflow?->name} sürecinin terminine son {$sla['hours_remaining']} saat kaldı! Hedef Tarih: {$sla['target_date_formatted']}. Lütfen işlemlerinizi tamamlayınız.";

        // Bekleyen görev sahiplerine bildir
        foreach ($instance->tasks as $task) {
            if ($task->assigned_to) {
                UserNotification::create([
                    'user_id' => $task->assigned_to,
                    'type' => 'termin_warning',
                    'title' => $title,
                    'body' => $body,
                    'task_id' => $task->id,
                    'read_at' => null,
                ]);

                // Kurumsal Mail Guard Güvenlik Kalkanı Üzerinden E-Posta Gönderimi
                if ($task->assignedUser && $task->assignedUser->email) {
                    try {
                        Mail::raw($body, function ($msg) use ($task, $title) {
                            $msg->to($task->assignedUser->email)
                                ->subject($title)
                                ->getHeaders()->addTextHeader('X-Mail-Event', 'termin_warning');
                        });
                    } catch (\Throwable $e) {
                        Log::warning("SLA warning mail dispatch failed: " . $e->getMessage());
                    }
                }
            }
        }
    }

    private function triggerOverdueEscalation(ProcessInstance $instance, array $sla): void
    {
        // Bugün zaten gecikme eskalasyonu gönderildiyse mükerrer gönderme
        $alreadySent = UserNotification::where('type', 'termin_overdue_escalation')
            ->where('title', 'like', "%#{$instance->id}%")
            ->whereDate('created_at', Carbon::today())
            ->exists();

        if ($alreadySent) {
            return;
        }

        $days = abs($sla['days_remaining']);
        $title = "🚨 SLA İHLALİ: #{$instance->id} Termin Süresi Aşıldı";
        $body = "DİKKAT: #{$instance->id} numaralı {$instance->workflow?->name} sürecinin taahhüt edilen termin tarihi ({$sla['target_date_formatted']}) {$days} GÜN AŞILMIŞTIR.\n"
              . "Müşteri: " . ($instance->data['musteri_adi'] ?? 'Belirtilmedi') . "\n"
              . "Lütfen gecikme gerekçesini sisteme giriniz veya acil aksiyon alınız.";

        $firstTaskId = $instance->tasks->first()?->id;

        // 1. Satışçıya Bildir & Mail Gönder
        if ($instance->starter) {
            UserNotification::create([
                'user_id' => $instance->starter->id,
                'type' => 'termin_overdue_escalation',
                'title' => $title,
                'body' => $body,
                'task_id' => $firstTaskId,
                'read_at' => null,
            ]);

            if ($instance->starter->email) {
                try {
                    Mail::raw($body, function ($msg) use ($instance, $title) {
                        $msg->to($instance->starter->email)
                            ->subject($title)
                            ->getHeaders()->addTextHeader('X-Mail-Event', 'termin_overdue_escalation');
                    });
                } catch (\Throwable $e) {
                    Log::warning("SLA overdue starter mail dispatch failed: " . $e->getMessage());
                }
            }
        }

        // 2. Bekleyen Görev Sahiplerine Bildir & Mail Gönder
        foreach ($instance->tasks as $task) {
            if ($task->assigned_to && $task->assigned_to !== $instance->started_by) {
                UserNotification::create([
                    'user_id' => $task->assigned_to,
                    'type' => 'termin_overdue_escalation',
                    'title' => $title,
                    'body' => $body,
                    'task_id' => $task->id,
                    'read_at' => null,
                ]);

                if ($task->assignedUser && $task->assignedUser->email) {
                    try {
                        Mail::raw($body, function ($msg) use ($task, $title) {
                            $msg->to($task->assignedUser->email)
                                ->subject($title)
                                ->getHeaders()->addTextHeader('X-Mail-Event', 'termin_overdue_escalation');
                        });
                    } catch (\Throwable $e) {
                        Log::warning("SLA overdue assignee mail dispatch failed: " . $e->getMessage());
                    }
                }
            }
        }
    }
}
