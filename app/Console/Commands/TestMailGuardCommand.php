<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MailEvent;
use App\Models\MailSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class TestMailGuardCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test-guard 
                            {--event=task_assigned : Test edilecek olay kodu (Örn: task_assigned, process_completed)}
                            {--to=gercek.personel@koksan.com : Test edilecek hedef asıl alıcı}
                            {--subject= : Özel e-posta konusu}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'MailGuardListener ve Mail Kural Motorunu konsoldan test eder';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->info('======================================================');
        $this->info('  🛡️ MAIL GUARD & KURAL MOTORU DENETİM TESTİ');
        $this->info('======================================================');

        // 1. Ayarları Oku
        $killSwitch = (bool) MailSetting::get('global_kill_switch', false);
        $testMode = (bool) MailSetting::get('test_mode', false);
        $testRecipient = (string) MailSetting::get('test_recipient_email', 'aslihan.aydin@koksan.com');

        $this->table(
            ['Parametre', 'Değer', 'Açıklama'],
            [
                ['Global Kill Switch', $killSwitch ? '🔴 AKTİF (Tüm mailler bloklanır)' : '🟢 KAPALI (Mailler geçebilir)', 'mail_settings.global_kill_switch'],
                ['Test Modu (Sandbox)', $testMode ? '🟡 AKTİF (Alıcı yönlendirilir)' : '⚪ KAPALI (Gerçek alıcıya gider)', 'mail_settings.test_mode'],
                ['Test Alıcı Adresi', $testRecipient, 'mail_settings.test_recipient_email'],
            ]
        );

        // 2. Olay ve Kural Bilgisi
        $eventCode = (string) $this->option('event');
        $originalTo = (string) $this->option('to');
        $subject = (string) ($this->option('subject') ?: "Yeni Görev: Levha Numune Talebi Onayı #994");

        $mailEvent = MailEvent::where('event_code', $eventCode)->with('activeRule')->first();

        $this->newLine();
        $this->info("🎯 Hedef Olay: [{$eventCode}]");
        if ($mailEvent) {
            $timezone = env('APP_TIMEZONE', 'Europe/Istanbul');
            $now = Carbon::now($timezone);
            $this->line("   - Olay Adı: {$mailEvent->name}");
            $this->line("   - Olay Durumu: " . ($mailEvent->is_active ? '🟢 AKTİF' : '🔴 PASİF (Bloklanacak)'));

            $rule = $mailEvent->activeRule;
            if ($rule && $rule->is_active) {
                $days = $rule->allowed_days ? implode(', ', $rule->allowed_days) : 'Tüm Günler';
                $time = ($rule->allowed_time_start && $rule->allowed_time_end)
                    ? "{$rule->allowed_time_start} - {$rule->allowed_time_end}"
                    : 'Tüm Saatler';

                $this->line("   - İzin Verilen Günler: {$days}");
                $this->line("   - İzin Verilen Saatler: {$time}");
                $this->line("   - Şu Anki Zaman: {$now->format('l H:i:s')} (Timezone: {$now->timezoneName})");
            } else {
                $this->line("   - Kural: Aktif kural tanımlı değil (Varsayılan olarak izin verilir)");
            }
        } else {
            $this->warn("   - Uyarı: [{$eventCode}] veritabanında bulunamadı (Generic Mail olarak işlenecek)");
        }

        $this->newLine();
        $this->info("🚀 E-Posta Tetikleniyor: Hedef = [{$originalTo}] | Konu = \"{$subject}\"...");

        // 3. E-Postayı Gönder ve MailGuardListener'ın Müdahalesini İzle
        try {
            // Test için log veya mevcut transport üzerinden deneme
            $dispatched = Mail::raw(
                "Bu bir MailGuardListener otomatik güvenlik ve kural denetimi test mesajıdır.\nZaman: " . now()->toIso8601String(),
                function ($message) use ($originalTo, $subject, $eventCode) {
                    $message->to($originalTo)
                        ->subject($subject);

                    // X-Mail-Event başlığı ekleyerek olayı açıkça bildir
                    $message->getHeaders()->addTextHeader('X-Mail-Event', $eventCode);
                }
            );

            $this->newLine();
            $this->info('✅ E-Posta akışı tamamlandı (Mail::raw çağrısı sona erdi).');
            $this->comment('👉 Lütfen Laravel loglarını inceleyin (storage/logs/laravel.log).');
            $this->line('   Eğer Kill Switch açık veya kural dışıysa [MAIL GUARD] bloklama logu görülecektir.');
            $this->line('   Eğer Test Modu açıksa alıcının "' . $testRecipient . '" adresine yönlendirildiği loglanacaktır.');
            $this->newLine();

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ E-posta gönderimi sırasında hata oluştu: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
