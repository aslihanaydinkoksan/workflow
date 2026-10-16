<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReportSchedule;
use App\Models\ReportScheduleLog;
use App\Services\ReportQueryEngineService;
use App\Services\ReportExportService;
use App\Mail\ScheduledReportMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class DispatchScheduledReportsCommand extends Command
{
    protected $signature = 'reports:dispatch-scheduled {--force : Zaman pencerelerine bakılmaksızın tüm aktif raporları gönderir}';
    protected $description = 'Zamanlanmış yönetim ve operasyonel raporları üretip ilgililere e-posta ile iletir';

    public function handle(
        ReportQueryEngineService $queryEngine,
        ReportExportService $exportService
    ): int {
        $force = $this->option('force');
        $this->info("=== KÖKSAN BPM OTOMATİK RAPOR DAĞITIM MOTORU BAŞLATILDI ===");

        $schedules = ReportSchedule::where('is_active', true)
            ->with('dashboard')
            ->get();

        if ($schedules->isEmpty()) {
            $this->warn("Aktif bir zamanlanmış rapor aboneliği bulunamadı.");
            return 0;
        }

        $now = Carbon::now('Europe/Istanbul');
        $currentTime = $now->format('H:i');
        $currentDayOfWeek = $now->dayOfWeekIso; // 1 = Pazartesi ... 7 = Pazar
        $currentDayOfMonth = $now->day;

        $dispatchedCount = 0;

        foreach ($schedules as $schedule) {
            $isDue = false;

            if ($force) {
                $isDue = true;
            } else {
                $schedTime = substr($schedule->dispatch_time ?? '08:30:00', 0, 5);
                $isTimeMatch = abs(strtotime($currentTime) - strtotime($schedTime)) <= 120; // 2 dakikalık tolerans

                switch ($schedule->frequency_type) {
                    case 'daily':
                        // Her gün belirtilen saatte
                        if ($isTimeMatch && (!$schedule->last_sent_at || !$schedule->last_sent_at->isToday())) {
                            $isDue = true;
                        }
                        break;
                    case 'weekly':
                        // Belirtilen gün ve saatte
                        $targetDay = $schedule->day_of_week ?? 1;
                        if ($currentDayOfWeek === $targetDay && $isTimeMatch && (!$schedule->last_sent_at || !$schedule->last_sent_at->isCurrentWeek())) {
                            $isDue = true;
                        }
                        break;
                    case 'monthly':
                        // Ayın 1'inde veya belirtilen gününde
                        $targetDay = $schedule->day_of_month ?? 1;
                        if ($currentDayOfMonth === $targetDay && $isTimeMatch && (!$schedule->last_sent_at || !$schedule->last_sent_at->isCurrentMonth())) {
                            $isDue = true;
                        }
                        break;
                    default:
                        $isDue = false;
                        break;
                }
            }

            if (!$isDue) {
                $this->line("Atlandı [Vakti Gelmedi]: #{$schedule->id} {$schedule->title}");
                continue;
            }

            $this->info("İşleniyor: #{$schedule->id} {$schedule->title} (Alıcılar: " . implode(', ', $schedule->recipients_json) . ")");

            $dashboard = $schedule->dashboard;
            if (!$dashboard) {
                $this->error("Pano bulunamadı: ID {$schedule->dashboard_id}");
                continue;
            }

            $startTime = microtime(true);
            $widgets = $dashboard->widgets()->where('is_active', true)->get();

            foreach ($widgets as $widget) {
                $widget->chart_data = $queryEngine->executeWidgetQuery($widget, ['date_range' => 'last_30_days']);
            }

            // PDF üret
            $pdfBinary = $schedule->include_pdf
                ? $exportService->generateDashboardPdf($dashboard, $widgets->all(), 'Otomatik Rapor (Son 30 Gün)')
                : null;

            // Excel üret
            $excelBinary = $schedule->include_excel
                ? $exportService->generateDashboardExcel($dashboard, $widgets->all())
                : null;

            $recipients = $schedule->recipients_json;

            try {
                Mail::to($recipients)->send(
                    new ScheduledReportMail($schedule, $dashboard, $widgets->all(), $pdfBinary, $excelBinary)
                );

                $execTimeMs = (int) round((microtime(true) - $startTime) * 1000);

                ReportScheduleLog::create([
                    'schedule_id' => $schedule->id,
                    'sent_at' => now(),
                    'status' => 'success',
                    'recipients_count' => count($recipients),
                    'execution_time_ms' => $execTimeMs,
                ]);

                $schedule->update(['last_sent_at' => now()]);
                $this->info("✓ Başarıyla gönderildi ({$execTimeMs} ms)");
                $dispatchedCount++;
            } catch (\Exception $e) {
                ReportScheduleLog::create([
                    'schedule_id' => $schedule->id,
                    'sent_at' => now(),
                    'status' => 'failed',
                    'recipients_count' => count($recipients),
                    'error_message' => $e->getMessage(),
                ]);

                $this->error("✗ Gönderim hatası: " . $e->getMessage());
            }
        }

        $this->info("Toplam gönderilen rapor adedi: {$dispatchedCount}");
        return 0;
    }
}
