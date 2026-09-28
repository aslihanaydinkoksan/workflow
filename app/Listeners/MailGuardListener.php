<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\MailEvent;
use App\Models\MailRule;
use App\Models\MailSetting;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class MailGuardListener
{
    /**
     * Handle the MessageSending event.
     * Return false to cancel email dispatch completely.
     */
    public function handle(MessageSending $event): ?bool
    {
        $message = $event->message;

        // Message nesnesi Symfony Email örneği değilse müdahale etmeden devam et
        if (! $message instanceof Email) {
            return null;
        }

        // 1. ADIM: GLOBAL KILL SWITCH KONTROLÜ
        $killSwitchActive = (bool) MailSetting::get('global_kill_switch', false);
        if ($killSwitchActive) {
            $toAddresses = array_map(fn (Address $a) => $a->getAddress(), $message->getTo() ?? []);
            Log::warning('[MAIL GUARD] E-posta gönderimi KESİNTİYE UĞRADI: Global Kill Switch (mail_settings.global_kill_switch) aktif!', [
                'subject' => $message->getSubject(),
                'to' => $toAddresses,
            ]);

            return false; // Laravel mail gönderimini sessizce ve güvenle iptal eder
        }

        // 2. ADIM: OLAY KODU EŞLEŞTİRME (EVENT MAPPING)
        $eventCode = $this->resolveEventCode($event, $message);

        // 3. ADIM: OLAY VE KURAL DEĞERLENDİRMESİ (RULE EVALUATION)
        if ($eventCode !== null) {
            $mailEvent = MailEvent::where('event_code', $eventCode)->with('activeRule')->first();

            if ($mailEvent) {
                // Olay aktif mi?
                if (! $mailEvent->is_active) {
                    Log::info("[MAIL GUARD] E-posta iptal edildi: '{$eventCode}' olayı sistemde pasif duruma getirilmiş.", [
                        'event_code' => $eventCode,
                        'subject' => $message->getSubject(),
                    ]);

                    return false;
                }

                // Olayın kuralı (Zaman ve Gün kısıtlaması) geçerli mi?
                if ($mailEvent->activeRule && ! $this->evaluateRule($mailEvent->activeRule, $eventCode)) {
                    return false;
                }
            }
        }

        // 4. ADIM: TEST MODU VE ALICI YÖNLENDİRME (TEST MODE OVERRIDE)
        $this->applyTestMode($message);

        return true;
    }

    /**
     * Olay kodunu (event_code) çok katmanlı ve güvenilir stratejiyle tespit eder.
     */
    protected function resolveEventCode(MessageSending $event, Email $message): ?string
    {
        // Katman 1: E-Posta Başlığında 'X-Mail-Event' başlığı var mı? (En yetkili kaynak)
        $header = $message->getHeaders()->get('X-Mail-Event');
        if ($header) {
            $val = trim($header->getBodyAsString());
            if ($val !== '') {
                return $val;
            }
        }

        // Katman 2: $event->data içinde doğrudan tanımlanmış bir event kodu var mı?
        if (! empty($event->data['mail_event_code'])) {
            return (string) $event->data['mail_event_code'];
        }
        if (! empty($event->data['event_code'])) {
            return (string) $event->data['event_code'];
        }

        // Katman 3: Bildirim nesnesi (UserNotification) var mı?
        if (isset($event->data['notification']) && is_object($event->data['notification'])) {
            $notificationType = $event->data['notification']->type ?? null;
            if ($notificationType) {
                return match ($notificationType) {
                    'task_assigned' => 'task_assigned',
                    'task_rejected' => 'task_rejected',
                    'task_due_soon', 'task_overdue' => 'task_assigned',
                    'delegation_assigned' => 'delegation_assigned',
                    'workflow_completed' => 'process_completed',
                    default => (string) $notificationType,
                };
            }
        }

        // Katman 4: Konu (Subject) üzerinden sezgisel eşleştirme
        $subject = (string) $message->getSubject();
        if (str_contains($subject, 'Yeni Görev')) {
            return 'task_assigned';
        }
        if (str_contains($subject, 'Tamamlandı')) {
            return 'process_completed';
        }
        if (str_contains($subject, 'Reddedildi') || str_contains($subject, 'red işlemi')) {
            return 'task_rejected';
        }
        if (str_contains($subject, 'Numune Takip') || str_contains($subject, 'Sipariş Dönüşümü')) {
            return 'sample_follow_up';
        }

        return null;
    }

    /**
     * Gün ve Saat kısıtlamalarını kural motoru üzerinden denetler.
     */
    protected function evaluateRule(MailRule $rule, string $eventCode): bool
    {
        if (! $rule->is_active) {
            return true;
        }

        $timezone = env('APP_TIMEZONE', 'Europe/Istanbul');
        $now = Carbon::now($timezone);

        // 1. Gün Kısıtlaması (allowed_days: ['Monday', 'Tuesday', ...])
        if (! empty($rule->allowed_days) && is_array($rule->allowed_days)) {
            $currentDay = $now->format('l'); // Monday, Tuesday...
            $allowedNormalized = array_map('strtolower', $rule->allowed_days);

            if (! in_array(strtolower($currentDay), $allowedNormalized, true)) {
                Log::warning("[MAIL GUARD] E-posta iptal edildi: '{$currentDay}' günü '{$eventCode}' olayı için izin verilen günler arasında değil.", [
                    'event_code' => $eventCode,
                    'allowed_days' => $rule->allowed_days,
                    'current_day' => $currentDay,
                ]);

                return false;
            }
        }

        // 2. Saat Penceresi Kısıtlaması (allowed_time_start - allowed_time_end)
        if ($rule->allowed_time_start && $rule->allowed_time_end) {
            $currentTime = $now->format('H:i:s');
            $start = Carbon::parse($rule->allowed_time_start)->format('H:i:s');
            $end = Carbon::parse($rule->allowed_time_end)->format('H:i:s');

            $inWindow = ($start <= $end)
                ? ($currentTime >= $start && $currentTime <= $end)
                : ($currentTime >= $start || $currentTime <= $end);

            if (! $inWindow) {
                Log::warning("[MAIL GUARD] E-posta iptal edildi: Saat {$currentTime}, '{$eventCode}' için belirlenen [{$start} - {$end}] zaman penceresi dışında.", [
                    'event_code' => $eventCode,
                    'time_window' => "{$start} - {$end}",
                    'current_time' => $currentTime,
                ]);

                return false;
            }
        }

        return true;
    }

    /**
     * Test Modu aktifse alıcıları test adresine yönlendirir ve konuya etiket ekler.
     */
    protected function applyTestMode(Email $message): void
    {
        $testMode = (bool) MailSetting::get('test_mode', false);
        if (! $testMode) {
            return;
        }

        $testRecipient = (string) MailSetting::get('test_recipient_email', 'aslihan.aydin@koksan.com');
        if (trim($testRecipient) === '') {
            $testRecipient = 'aslihan.aydin@koksan.com';
        }

        // Orijinal alıcı adreslerini (To, Cc, Bcc) topla
        $to = array_map(fn (Address $a) => $a->getAddress(), $message->getTo() ?? []);
        $cc = array_map(fn (Address $a) => $a->getAddress(), $message->getCc() ?? []);
        $bcc = array_map(fn (Address $a) => $a->getAddress(), $message->getBcc() ?? []);

        $allOriginal = array_unique(array_filter(array_merge($to, $cc, $bcc)));
        $originalSummary = implode(', ', $allOriginal);

        // Alıcı listesini tek test adresine indirge
        $message->to(new Address($testRecipient, 'Workflow Test Sandbox'));
        $message->getHeaders()->remove('Cc');
        $message->getHeaders()->remove('Bcc');

        // Başlığa test etiketini ve asıl hedefleri ekle
        $currentSubject = $message->getSubject() ?? '(Başlıksız)';
        if (! str_starts_with($currentSubject, '[TEST')) {
            $message->subject("[TEST - To: {$originalSummary}] {$currentSubject}");
        }

        Log::info("[MAIL GUARD] Test Modu: E-posta gerçek alıcılar yerine {$testRecipient} adresine yönlendirildi.", [
            'original_recipients' => $allOriginal,
            'redirected_to' => $testRecipient,
            'final_subject' => $message->getSubject(),
        ]);
    }
}
