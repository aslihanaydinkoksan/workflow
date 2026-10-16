<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\MorningDigestMail;
use App\Models\MailDigestQueue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMorningDigestCommand extends Command
{
    protected $signature = 'mail:send-digest 
                            {--user-id= : Belirli bir kullanıcıya ait bildirimleri gönder}
                            {--force : Sabah saat kontrolünü (08:30) atla ve kuyruktaki tüm özetleri hemen gönder}
                            {--dry-run : E-posta göndermeden bekleyen kayıtları simüle et ve listele}';

    protected $description = 'Mesai saatleri dışında beklemeye alınan onay, bildirim ve SLA eskalasyonlarını sabah 08:30 özet bülteni (Digest) olarak yöneticilere iletir';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $userId = $this->option('user-id');

        $this->info("☀️ KÖKSAN SABAH YÖNETİCİ ÖZETİ (MORNING DIGEST) MOTORU BAŞLATILIYOR..." . ($dryRun ? ' (DRY-RUN)' : ''));

        $now = Carbon::now(config('app.timezone', 'Europe/Istanbul'));
        $currentTime = $now->format('H:i');

        // Otomatik cron çalışmasında sabah mesai saatini kontrol et (varsayılan: 08:00 - 10:00 arası)
        if (! $force && ! $dryRun) {
            if ($currentTime < '08:00' || $currentTime > '10:30') {
                $this->warn("⚠️  Şu anki saat ({$currentTime}) sabah özet penceresi [08:00 - 10:30] dışında. Gönderim atlandı. (Zorlamak için --force kullanın)");
                return self::SUCCESS;
            }
        }

        // Kuyruktaki bekleyen kayıtları sorgula
        $query = MailDigestQueue::where('status', 'pending')->with('recipientUser');

        if ($userId) {
            $query->where('recipient_user_id', $userId);
        }

        $pendingItems = $query->orderBy('queued_at', 'asc')->get();

        if ($pendingItems->isEmpty()) {
            $this->info("✅ Kuyrukta bekleyen mesai dışı bildirim bulunmamaktadır. Gönderilecek özet yok.");
            return self::SUCCESS;
        }

        // Alıcı bazında grupla
        $groupedByEmail = $pendingItems->groupBy('recipient_email');
        $totalRecipients = $groupedByEmail->count();
        $totalItems = $pendingItems->count();

        $this->info("📊 Toplam {$totalItems} bekleyen bildirim, {$totalRecipients} farklı alıcı için derlendi.");

        $sentCount = 0;
        $failedCount = 0;

        foreach ($groupedByEmail as $email => $items) {
            $user = $items->first()?->recipientUser ?? User::where('email', $email)->first();
            $userName = $user ? $user->name : $email;
            $itemCount = $items->count();

            $this->line("👉 Alıcı: <comment>{$userName} ({$email})</comment> | {$itemCount} bildirim...");

            if ($dryRun) {
                foreach ($items as $item) {
                    $this->line("   - [{$item->event_code}] {$item->subject}");
                }
                continue;
            }

            try {
                // Sabah Özeti E-Postasını Gönder
                Mail::to($email)->send(new MorningDigestMail($user, $email, $items));

                // Kuyruk durumunu güncelle
                MailDigestQueue::whereIn('id', $items->pluck('id'))->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                $sentCount++;
                $this->info("   ✅ Başarıyla iletildi ve kuyruk 'sent' olarak güncellendi.");

                Log::info("[MORNING DIGEST] Sabah özeti başarıyla iletildi.", [
                    'recipient' => $email,
                    'item_count' => $itemCount,
                ]);
            } catch (\Throwable $e) {
                $failedCount++;
                $this->error("   ❌ Gönderim başarısız: " . $e->getMessage());

                MailDigestQueue::whereIn('id', $items->pluck('id'))->update([
                    'status' => 'failed',
                ]);

                Log::error("[MORNING DIGEST] Sabah özeti gönderimi hatası: " . $e->getMessage(), [
                    'recipient' => $email,
                ]);
            }
        }

        $this->info("\n🎉 İŞLEM TAMAMLANDI:");
        $this->info("   Başarılı Gönderilen Bülten: {$sentCount}");
        if ($failedCount > 0) {
            $this->warn("   Başarısız: {$failedCount}");
        }

        return self::SUCCESS;
    }
}
