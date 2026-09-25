<?php

namespace App\Services;

use App\Mail\FollowUpMail;
use App\Models\FollowUp;
use App\Models\ProcessInstance;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class FollowUpService
{
    private SapIntegrationService $sapService;

    public function __construct(SapIntegrationService $sapService)
    {
        $this->sapService = $sapService;
    }

    /**
     * Tamamlanan veya ara adımdaki bir süreç için dinamik/düğüm ayarlarına göre Follow-Up planlar.
     */
    public function scheduleFollowUp(ProcessInstance $instance, array $options = []): ?FollowUp
    {
        // Daha önce bu süreç için aktif bir takip oluşturulmuşsa tekrar oluşturma
        $existing = FollowUp::where('process_instance_id', $instance->id)->first();
        if ($existing) {
            return $existing;
        }

        // Düğümden gelen özel gün sayısı varsa onu kullan, yoksa Ayarlar ekranındaki dinamik değeri al
        $defaultDays = (int) Setting::get('sample_follow_up_days', 30);
        $followUpDays = !empty($options['followUpDays']) ? (int) $options['followUpDays'] : $defaultDays;
        if ($followUpDays < 1) {
            $followUpDays = 30;
        }

        $scheduledAt = Carbon::now()->addDays($followUpDays);

        $title = $options['followUpTitle'] ?? $options['title'] ?? 'Süreç & Sipariş Takibi';
        $prompt = $options['followUpPrompt'] ?? $options['prompt'] ?? 'Talep süreci tamamlanmıştır. Lütfen güncel durumu sisteme işleyiniz.';
        $subFormId = !empty($options['subFormId']) ? (int) $options['subFormId'] : null;
        $assignedTo = !empty($options['assigned_to']) ? (int) $options['assigned_to'] : $instance->started_by;
        $type = $options['followUpType'] ?? $options['type'] ?? 'general_follow_up';

        $followUp = FollowUp::create([
            'process_instance_id' => $instance->id,
            'follow_up_type'      => $type,
            'title'               => $title,
            'prompt'              => $prompt,
            'sub_form_id'         => $subFormId,
            'status'              => 'pending',
            'scheduled_at'        => $scheduledAt,
            'assigned_to'         => $assignedTo,
            'reminder_count'      => 0,
            'metadata'            => [
                'initial_days' => $followUpDays,
                'created_via'  => 'workflow_designer_node_or_hook',
                'options'      => $options,
            ]
        ]);

        Log::info("Zamanlanmış Takip Planlandı: Takip ID {$followUp->id}, Süreç #{$instance->id}, Başlık: {$title}, Tarih: {$scheduledAt->toDateTimeString()}");

        return $followUp;
    }

    /**
     * Zamanı gelmiş bekleyen tüm numune takiplerini işler, e-posta ve bildirim gönderir.
     */
    public function processScheduledFollowUps(): int
    {
        $maxReminders = (int) Setting::get('sample_follow_up_max_reminders', 3);
        $intervalDays = (int) Setting::get('sample_follow_up_reminder_interval_days', 7);

        if ($maxReminders < 1) $maxReminders = 3;
        if ($intervalDays < 1) $intervalDays = 7;

        // Vadesi dolmuş ve hala cevap verilmemiş takipler
        $dueFollowUps = FollowUp::with(['processInstance.starter', 'assignedUser', 'subForm'])
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', Carbon::now())
            ->get();

        $processedCount = 0;

        foreach ($dueFollowUps as $followUp) {
            $shouldSend = false;

            // 1. İlk gönderim (Daha önce hiç hatırlatılmadıysa)
            if ($followUp->reminder_count === 0 && is_null($followUp->last_reminded_at)) {
                $shouldSend = true;
            } 
            // 2. Hatırlatma gönderimi (Maksimum limite ulaşılmadıysa ve belirlenen aralık geçtiyse)
            elseif ($followUp->reminder_count < $maxReminders && $followUp->last_reminded_at) {
                $daysSinceLast = Carbon::now()->diffInDays($followUp->last_reminded_at);
                if ($daysSinceLast >= $intervalDays) {
                    $shouldSend = true;
                }
            }

            if ($shouldSend) {
                $this->sendNotificationAndEmail($followUp);

                $followUp->increment('reminder_count');
                $followUp->update([
                    'last_reminded_at' => Carbon::now()
                ]);

                $processedCount++;
            }
        }

        return $processedCount;
    }

    /**
     * Takip bildirimini kullanıcıya e-posta ve sistem içi bildirim olarak iletir.
     */
    public function sendNotificationAndEmail(FollowUp $followUp): void
    {
        $targetUser = $followUp->assignedUser ?? $followUp->processInstance?->starter;
        if (!$targetUser) {
            return;
        }

        $instance = $followUp->processInstance;
        $attemptText = $followUp->reminder_count > 0 ? " ({$followUp->reminder_count}. Hatırlatma)" : "";

        $title = $followUp->title ?: "Süreç Takibi: Durum Sorgulama";
        $body = $followUp->prompt ?: "#{$instance->id} numaralı talep süreci tamamlanmıştır. Lütfen güncel durumu sisteme işleyiniz.";

        // 1. Sistem İçi Çan Bildirimi
        UserNotification::create([
            'user_id' => $targetUser->id,
            'type'    => 'sample_follow_up',
            'title'   => "{$title}{$attemptText}",
            'body'    => $body,
            'data'    => [
                'url'          => route('follow-ups.show', $followUp->id),
                'action_url'   => route('follow-ups.show', $followUp->id),
                'follow_up_id' => $followUp->id,
                'instance_id'  => $instance->id,
            ],
        ]);

        // 2. E-Posta Gönderimi
        if (!empty($targetUser->email)) {
            try {
                Mail::to($targetUser->email)->send(new FollowUpMail($followUp));
            } catch (\Exception $e) {
                Log::error("FollowUp mail gönderilemedi: " . $e->getMessage());
            }
        }
    }

    /**
     * Satış temsilcisinin cevabını işler, gerekirse SAP senkronizasyonunu tetikler.
     */
    public function recordResponse(FollowUp $followUp, array $data, User $user): array
    {
        $status = $data['response_status'] ?? 'pending'; // 'converted' | 'not_converted' | 'rescheduled'

        if ($status === 'converted') {
            $followUp->update([
                'status'                => 'converted',
                'response_status'       => 'converted',
                'order_number'          => $data['order_number'] ?? null,
                'customer_feedback'     => $data['customer_feedback'] ?? null,
                'responded_at'          => Carbon::now(),
                'responded_by'          => $user->id,
            ]);

            // SAP S/4HANA Entegrasyonunu Tetikle
            $sapResult = $this->sapService->syncSampleOrder($followUp);

            return [
                'success'    => true,
                'message'    => 'Numune başarıyla siparişe dönüştürüldü ve SAP entegrasyonu tetiklendi.',
                'sap_result' => $sapResult,
            ];
        } elseif ($status === 'not_converted') {
            $followUp->update([
                'status'                => 'not_converted',
                'response_status'       => 'not_converted',
                'non_conversion_reason' => $data['non_conversion_reason'] ?? 'Belirtilmedi',
                'customer_feedback'     => $data['customer_feedback'] ?? null,
                'responded_at'          => Carbon::now(),
                'responded_by'          => $user->id,
            ]);

            return [
                'success' => true,
                'message' => 'Numunenin siparişe dönmeme gerekçesi başarıyla kaydedildi.',
            ];
        } elseif ($status === 'rescheduled') {
            $newDate = !empty($data['new_date']) ? Carbon::parse($data['new_date']) : Carbon::now()->addDays(15);

            $followUp->update([
                'scheduled_at'      => $newDate,
                'response_status'   => 'in_negotiation',
                'customer_feedback' => $data['customer_feedback'] ?? 'Görüşmeler devam ediyor.',
                'last_reminded_at'  => null, // Sıfırla ki yeni tarihte tekrar uyarsın
            ]);

            return [
                'success' => true,
                'message' => 'Numune takip tarihi başarıyla güncellendi.',
            ];
        }

        return ['success' => false, 'message' => 'Geçersiz takip durumu.'];
    }
}
