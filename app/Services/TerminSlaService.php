<?php

namespace App\Services;

use App\Models\ProcessInstance;
use App\Models\TerminRevision;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TerminSlaService
{
    /**
     * Süreç için güncel SLA durumunu, kalan süreyi ve metrikleri hesaplar.
     */
    public function calculateSla(ProcessInstance $instance): ?array
    {
        $data = (array) ($instance->data ?? []);

        // 1. Termin metnini veya aralıklarını tespit et
        $terminStr = $data['termin_araligi'] ?? $data['termin_tarihi'] ?? $data['termin'] ?? null;
        $terminRanges = $data['termin_ranges'] ?? null;

        if (empty($terminStr) && empty($terminRanges)) {
            return null;
        }

        // 2. Hedef nihai termin tarihini (Deadline) tespit et
        $targetDate = $this->extractTargetDate($terminStr, $terminRanges);
        if (!$targetDate) {
            return null;
        }

        $now = Carbon::now();
        $isCompleted = $instance->status === 'completed';
        $isRejected = in_array($instance->status, ['rejected', 'cancelled'], true);

        $revisionsCount = $instance->terminRevisions()->count();
        $latestRevision = $instance->latestTerminRevision;

        // Tamamlanmış Süreç Analizi (OTIF: On-Time In-Full)
        if ($isCompleted) {
            $completedAt = $instance->updated_at ?? $now;
            $wasOnTime = $completedAt->lte($targetDate->copy()->endOfDay());
            $delayDays = $wasOnTime ? 0 : (int) $targetDate->copy()->endOfDay()->diffInDays($completedAt);

            return [
                'has_termin' => true,
                'termin_text' => (string) $terminStr,
                'target_date' => $targetDate->format('Y-m-d'),
                'target_date_formatted' => $targetDate->format('d.m.Y'),
                'is_completed' => true,
                'was_on_time' => $wasOnTime,
                'otif_percentage' => $wasOnTime ? 100 : 0,
                'delay_days' => $delayDays,
                'sla_status' => $wasOnTime ? 'completed_on_time' : 'completed_delayed',
                'traffic_light' => $wasOnTime ? 'green' : 'amber',
                'badge_title' => $wasOnTime ? 'Zamanında Teslim Edildi (OTIF %100)' : "Gecikmeli Teslim Edildi (+{$delayDays} Gün)",
                'badge_color' => $wasOnTime 
                    ? 'bg-emerald-50 text-emerald-700 border-emerald-300' 
                    : 'bg-amber-50 text-amber-700 border-amber-300',
                'revisions_count' => $revisionsCount,
                'latest_revision' => $latestRevision,
            ];
        }

        if ($isRejected) {
            return [
                'has_termin' => true,
                'termin_text' => (string) $terminStr,
                'target_date' => $targetDate->format('Y-m-d'),
                'target_date_formatted' => $targetDate->format('d.m.Y'),
                'is_completed' => true,
                'sla_status' => 'cancelled',
                'traffic_light' => 'gray',
                'badge_title' => 'Süreç İptal / Ret',
                'badge_color' => 'bg-gray-100 text-gray-700 border-gray-300',
                'revisions_count' => $revisionsCount,
                'latest_revision' => $latestRevision,
            ];
        }

        // Aktif Süreç Analizi (Canlı SLA Sayacı)
        $endOfDayTarget = $targetDate->copy()->endOfDay();
        $isPast = $now->gt($endOfDayTarget);

        // Kalan / Geciken gün hesabı
        $daysDiff = (int) $now->diffInDays($endOfDayTarget, false);
        $hoursDiff = (int) $now->diffInHours($endOfDayTarget, false);

        if ($isPast) {
            $overdueDays = abs($daysDiff) === 0 ? 1 : abs($daysDiff);
            if ($overdueDays >= 3) {
                $slaStatus = 'critical';
                $trafficLight = 'critical-red';
                $badgeTitle = "🚨 KRİTİK SLA İHLALİ (-{$overdueDays} Gün Gecikmede)";
                $badgeColor = 'bg-rose-900 text-white border-rose-950 animate-pulse shadow-rose-900/30';
            } else {
                $slaStatus = 'overdue';
                $trafficLight = 'red';
                $badgeTitle = "⛔ TERMİN AŞILDI (-{$overdueDays} Gün Gecikmede)";
                $badgeColor = 'bg-red-50 text-red-700 border-red-300';
            }
        } elseif ($hoursDiff <= 48) {
            $slaStatus = 'warning';
            $trafficLight = 'amber';
            $remainingHoursText = $hoursDiff > 0 ? "Son {$hoursDiff} Saat!" : "Bugün Son Gün!";
            $badgeTitle = "⚠️ DİKKAT: TERMİN YAKLAŞIYOR ({$remainingHoursText})";
            $badgeColor = 'bg-amber-50 text-amber-800 border-amber-300 animate-pulse';
        } else {
            $slaStatus = 'on_time';
            $trafficLight = 'green';
            $badgeTitle = "🟢 ZAMANINDA İLERLİYOR (Kalan: {$daysDiff} Gün)";
            $badgeColor = 'bg-emerald-50 text-emerald-800 border-emerald-300';
        }

        return [
            'has_termin' => true,
            'termin_text' => (string) $terminStr,
            'target_date' => $targetDate->format('Y-m-d'),
            'target_date_formatted' => $targetDate->format('d.m.Y'),
            'is_completed' => false,
            'days_remaining' => $daysDiff,
            'hours_remaining' => $hoursDiff,
            'is_overdue' => $isPast,
            'sla_status' => $slaStatus,
            'traffic_light' => $trafficLight,
            'badge_title' => $badgeTitle,
            'badge_color' => $badgeColor,
            'revisions_count' => $revisionsCount,
            'latest_revision' => $latestRevision,
        ];
    }

    /**
     * Termin Revizyonu / Tarih Erteleme İşlemini Yürütür.
     */
    public function rescheduleTermin(
        ProcessInstance $instance,
        User $user,
        string $newTermin,
        string $reasonCategory,
        string $reasonNote
    ): TerminRevision {
        $data = (array) ($instance->data ?? []);
        $prevTermin = $data['termin_araligi'] ?? $data['termin_tarihi'] ?? $data['termin'] ?? null;

        $newTargetDate = $this->extractTargetDate($newTermin, null);

        // 1. Revizyon Kaydını Veritabanına Yaz
        $revision = TerminRevision::create([
            'process_instance_id' => $instance->id,
            'requested_by' => $user->id,
            'previous_termin' => $prevTermin,
            'new_termin' => $newTermin,
            'new_target_date' => $newTargetDate?->format('Y-m-d'),
            'reason_category' => $reasonCategory,
            'reason_note' => $reasonNote,
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        // 2. Süreç İçindeki Form Verisini Güncelle
        $data['termin_araligi'] = $newTermin;
        $data['_last_termin_revision_id'] = $revision->id;
        $instance->update(['data' => $data]);

        // 3. Bildirim & E-Posta Gönderimi
        $this->notifyPartiesOfReschedule($instance, $revision, $user);

        return $revision;
    }

    /**
     * Çeşitli formatlardaki termin metinlerinden nihai bitiş tarihini çıkarır.
     */
    public function extractTargetDate(?string $terminStr, ?array $terminRanges = null): ?Carbon
    {
        // Öncelik 1: termin_ranges dizisi varsa en büyük tarihi bul
        if (!empty($terminRanges) && is_array($terminRanges)) {
            $dates = [];
            foreach ($terminRanges as $range) {
                if (!empty($range['end'])) {
                    $dates[] = Carbon::parse($range['end']);
                } elseif (!empty($range['start'])) {
                    $dates[] = Carbon::parse($range['start']);
                }
            }
            if (!empty($dates)) {
                return collect($dates)->max();
            }
        }

        if (empty($terminStr)) {
            return null;
        }

        // Öncelik 2: Regex ile dd.mm.yyyy veya yyyy-mm-dd formatlı tarihleri topla
        // Örn: "20.10.2026 - 27.10.2026" veya "1. Parti: 2026-10-20 - 2026-10-27"
        $matches = [];
        preg_match_all('/\b(\d{1,2}\.\d{1,2}\.\d{4}|\d{4}-\d{2}-\d{2})\b/', $terminStr, $matches);

        if (!empty($matches[0])) {
            $parsedDates = [];
            foreach ($matches[0] as $match) {
                try {
                    if (str_contains($match, '.')) {
                        $parsedDates[] = Carbon::createFromFormat('d.m.Y', $match);
                    } else {
                        $parsedDates[] = Carbon::parse($match);
                    }
                } catch (\Throwable) {
                    // ignore
                }
            }
            if (!empty($parsedDates)) {
                return collect($parsedDates)->max();
            }
        }

        // Öncelik 3: Tekil Carbon parse denemesi
        try {
            return Carbon::parse($terminStr);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Termin ertelemesini talep sahibine (satışçı) ve görevlilere bildirir.
     */
    private function notifyPartiesOfReschedule(ProcessInstance $instance, TerminRevision $revision, User $rescheduledBy): void
    {
        $workflowName = $instance->workflow?->name ?? 'İş Akışı';
        $categoryLabel = $revision->category_label;
        $starter = $instance->starter;

        $title = "📅 Termin Güncellendi: #{$instance->id}";
        $body = "{$rescheduledBy->name}, #{$instance->id} numaralı {$workflowName} sürecinin termin tarihini güncelledi.\n\n"
              . "Önceki Termin: " . ($revision->previous_termin ?: 'Belirtilmedi') . "\n"
              . "Yeni Termin: {$revision->new_termin}\n"
              . "Gerekçe: [{$categoryLabel}] {$revision->reason_note}";

        // 1. Satışçıya (Talebi Başlatana) Bildir
        if ($starter && $starter->id !== $rescheduledBy->id) {
            UserNotification::create([
                'user_id' => $starter->id,
                'type' => 'termin_rescheduled',
                'title' => $title,
                'body' => $body,
                'task_id' => null,
                'read_at' => null,
            ]);

            if (!empty($starter->email)) {
                try {
                    Mail::raw($body, function ($msg) use ($starter, $instance, $title) {
                        $msg->to($starter->email)->subject("{$title} - Köksan Süreç Portalı");
                    });
                } catch (\Throwable $e) {
                    Log::error("Termin revizyon mail hatası: " . $e->getMessage());
                }
            }
        }

        // 2. Süreçte şu anda bekleyen görevi olanlara bildir (Örn: Mustafa Yılmaz)
        foreach ($instance->tasks()->where('status', 'pending')->get() as $task) {
            if ($task->assigned_to && $task->assigned_to !== $rescheduledBy->id && $task->assigned_to !== $starter?->id) {
                UserNotification::create([
                    'user_id' => $task->assigned_to,
                    'type' => 'termin_rescheduled',
                    'title' => $title,
                    'body' => $body,
                    'task_id' => $task->id,
                    'read_at' => null,
                ]);
            }
        }
    }
}
