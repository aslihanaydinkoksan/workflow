<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\MailDigestQueue;
use App\Models\MailEvent;
use App\Models\MailRule;
use App\Models\MailSetting;
use App\Models\MailTemplate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class MailSettingsController extends Controller
{
    /**
     * E-Posta Yönetim Paneli ana ekran verilerini döndürür.
     */
    public function index(): Response
    {
        // 1. Global E-Posta Ayarları
        $globalSettings = [
            'global_kill_switch' => (bool) MailSetting::get('global_kill_switch', false),
            'test_mode' => (bool) MailSetting::get('test_mode', true),
            'test_recipient_email' => (string) MailSetting::get('test_recipient_email', 'aslihan.aydin@koksan.com'),
        ];

        // Eğer sunucuya taşındıktan sonra seeder çalıştırılmamışsa otomatik olarak varsayılanları yükle
        if (MailEvent::count() === 0) {
            try {
                (new \Database\Seeders\MailSystemSeeder())->run();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('MailSystemSeeder otomatik çalıştırma uyarısı: ' . $e->getMessage());
            }
        }

        // 2. Olaylar, aktif şablonlar ve aktif kurallar
        $events = MailEvent::with(['activeTemplate', 'activeRule', 'templates', 'rules'])
            ->orderBy('id')
            ->get();

        // 3. Kural hedefleme için referans listeleri
        $users = User::select('id', 'name', 'email', 'department_id')
            ->orderBy('name')
            ->get();

        $roles = Role::select('id', 'name')
            ->orderBy('name')
            ->get();

        $departments = Department::select('id', 'name')
            ->orderBy('name')
            ->get();

        // 4. Günler sözlüğü
        $availableDays = [
            'Monday' => 'Pazartesi',
            'Tuesday' => 'Salı',
            'Wednesday' => 'Çarşamba',
            'Thursday' => 'Perşembe',
            'Friday' => 'Cuma',
            'Saturday' => 'Cumartesi',
            'Sunday' => 'Pazar',
        ];

        // 5. Sabah Özeti (Morning Digest) İstatistikleri ve Kuyruk Kayıtları
        $digestStats = [
            'pending_count' => MailDigestQueue::where('status', 'pending')->count(),
            'sent_today_count' => MailDigestQueue::where('status', 'sent')->whereDate('sent_at', Carbon::today())->count(),
            'recent_items' => MailDigestQueue::with('recipientUser')
                ->orderByDesc('id')
                ->limit(15)
                ->get(),
        ];

        return Inertia::render('Admin/MailSettings/Index', [
            'settings' => $globalSettings,
            'events' => $events,
            'users' => $users,
            'roles' => $roles,
            'departments' => $departments,
            'availableDays' => $availableDays,
            'digestStats' => $digestStats,
        ]);
    }

    /**
     * Global e-posta parametrelerini (Kill switch, test modu, test alıcısı) günceller.
     */
    public function updateGlobalSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'global_kill_switch' => 'required|boolean',
            'test_mode' => 'required|boolean',
            'test_recipient_email' => 'required|email|max:255',
        ]);

        MailSetting::set(
            'global_kill_switch',
            $validated['global_kill_switch'],
            'boolean',
            'Tüm dış e-posta gönderimlerini anında durduran ana şalter (Kill Switch).'
        );

        MailSetting::set(
            'test_mode',
            $validated['test_mode'],
            'boolean',
            'Test Modu: Açıkken tüm e-postalar test alıcısına yönlendirilir.'
        );

        MailSetting::set(
            'test_recipient_email',
            trim($validated['test_recipient_email']),
            'string',
            'Test modunda tüm e-postaların yönlendirileceği hedef e-posta adresi.'
        );

        return redirect()->back()->with('success', 'Genel e-posta güvenlik ayarları başarıyla güncellendi.');
    }

    /**
     * Belirli bir e-posta olayının aktiflik durumunu (is_active) günceller.
     */
    public function updateEventStatus(Request $request, int $eventId): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $event = MailEvent::findOrFail($eventId);
        $event->update([
            'is_active' => $validated['is_active'],
        ]);

        $statusText = $validated['is_active'] ? 'aktif' : 'pasif';

        return redirect()->back()->with('success', "\"{$event->name}\" olayı {$statusText} duruma getirildi.");
    }

    /**
     * Sisteme dinamik olarak yeni bir e-posta olayı tanımlar ve varsayılan kural/şablon atar.
     */
    public function storeEvent(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'event_code' => 'required|string|max:100|regex:/^[a-zA-Z0-9_\-]+$/|unique:mail_events,event_code',
            'description' => 'nullable|string|max:1000',
        ], [
            'event_code.regex' => 'Olay kodu yalnızca küçük/büyük harf, rakam ve alt çizgi içerebilir (Örn: sozlesme_vadesi_doldu).',
            'event_code.unique' => 'Bu olay kodu zaten sistemde tanımlı.',
        ]);

        $eventCode = strtolower(trim($validated['event_code']));

        $event = MailEvent::create([
            'event_code' => $eventCode,
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        // Varsayılan zaman penceresi kuralı
        $event->rules()->create([
            'allowed_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'allowed_time_start' => '08:00:00',
            'allowed_time_end' => '19:00:00',
            'is_active' => true,
        ]);

        // Varsayılan e-posta şablonu
        $event->templates()->create([
            'subject' => $event->name . ': {{ workflow_name }}',
            'body_content' => '<p>Sayın <strong>{{ user_name }}</strong>,</p>'
                . '<p>' . htmlspecialchars($event->description ?: $event->name, ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p><a href="{{ action_url }}" style="display: inline-block; background-color: #4f46e5; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: bold;">Detayları Görüntüle</a></p>',
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', "Yeni e-posta olayı \"{$event->name}\" ({$event->event_code}) başarıyla oluşturuldu.");
    }

    /**
     * Bir e-posta olayını ve ona bağlı şablon/kuralları siler.
     */
    public function destroyEvent(int $eventId): RedirectResponse
    {
        $event = MailEvent::findOrFail($eventId);
        $name = $event->name;
        $event->delete();

        return redirect()->back()->with('success', "\"{$name}\" e-posta olayı başarıyla silindi.");
    }

    /**
     * Belirli bir olaya ait kuralın (Hedef kitle ve Zaman penceresi) parametrelerini günceller.
     */
    public function updateRule(Request $request, int $ruleId): RedirectResponse
    {
        $rule = MailRule::findOrFail($ruleId);

        $validated = $request->validate([
            'is_active' => 'sometimes|boolean',
            'target_users' => 'nullable|array',
            'target_users.*' => 'integer|exists:users,id',
            'target_roles' => 'nullable|array',
            'target_roles.*' => 'string',
            'target_departments' => 'nullable|array',
            'target_departments.*' => 'integer|exists:departments,id',
            'allowed_days' => 'nullable|array',
            'allowed_days.*' => 'string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'allowed_time_start' => 'nullable|string|regex:/^\d{2}:\d{2}(:\d{2})?$/',
            'allowed_time_end' => 'nullable|string|regex:/^\d{2}:\d{2}(:\d{2})?$/',
        ]);

        $rule->update([
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $rule->is_active,
            'target_users' => $validated['target_users'] ?? null,
            'target_roles' => $validated['target_roles'] ?? null,
            'target_departments' => $validated['target_departments'] ?? null,
            'allowed_days' => $validated['allowed_days'] ?? null,
            'allowed_time_start' => $validated['allowed_time_start'] ?? null,
            'allowed_time_end' => $validated['allowed_time_end'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Kural ve hedefleme ayarları başarıyla güncellendi.');
    }

    /**
     * Belirli bir e-posta şablonunu günceller.
     */
    public function updateTemplate(Request $request, int $templateId): RedirectResponse
    {
        $template = MailTemplate::findOrFail($templateId);

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'body_content' => 'required|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $template->update([
            'subject' => $validated['subject'],
            'body_content' => $validated['body_content'],
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $template->is_active,
        ]);

        return redirect()->back()->with('success', 'E-posta şablon içeriği başarıyla kaydedildi.');
    }

    /**
     * UI üzerinden anlık test e-postası tetikler.
     */
    public function sendTestMail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_code' => 'required|string|exists:mail_events,event_code',
            'recipient_email' => 'required|email|max:255',
            'subject' => 'nullable|string|max:255',
        ]);

        $eventCode = $validated['event_code'];
        $recipient = $validated['recipient_email'];
        $subject = $validated['subject'] ?: "Panel Test E-Postası: [{$eventCode}]";
        $mailEvent = MailEvent::where('event_code', $eventCode)->first();

        $senderUser = auth()->user();
        $senderName = $senderUser ? $senderUser->name : 'Sistem Yöneticisi';
        $senderEmail = $senderUser ? $senderUser->email : 'admin@koksan.com';

        try {
            Mail::raw(
                "Bu mesaj Köksan Dinamik E-Posta Yönetim Paneli üzerinden tetiklenen bir test gönderimidir.\n\n"
                . "• Olay Kodu: {$eventCode} (" . ($mailEvent ? $mailEvent->name : 'Tanımsız') . ")\n"
                . "• Tetikleyen Yönetici: {$senderName} ({$senderEmail})\n"
                . "• Orijinal Hedef: {$recipient}\n"
                . "• Gönderim Zamanı: " . now()->format('d.m.Y H:i:s') . "\n\n"
                . "Not: Sistem MailGuardListener denetiminden geçmiş ve Test Modu kurallarına göre teslim edilmiştir.",
                function ($message) use ($recipient, $subject, $eventCode) {
                    $message->to($recipient)
                        ->subject($subject);

                    // X-Mail-Event başlığı ile MailGuardListener'a olayı açıkça bildir
                    $message->getHeaders()->addTextHeader('X-Mail-Event', $eventCode);
                }
            );

            return redirect()->back()->with(
                'success',
                "Test e-postası başarıyla kuyruğa alındı / gönderildi. (Hedef: {$recipient})"
            );
        } catch (\Throwable $e) {
            Log::error('Panel üzerinden test mail gönderim hatası: ' . $e->getMessage(), [
                'event_code' => $eventCode,
                'recipient' => $recipient,
            ]);

            return redirect()->back()->with(
                'error',
                'E-posta gönderimi sırasında bir hata oluştu: ' . $e->getMessage()
            );
        }
    }

    /**
     * Sabah Yönetici Özeti (Morning Digest) bültenini manuel tetikler.
     */
    public function sendDigestNow(): RedirectResponse
    {
        try {
            Artisan::call('mail:send-digest', ['--force' => true]);

            return redirect()->back()->with('success', '☀️ Sabah Yönetici Özeti bülteni başarıyla çalıştırıldı ve bekleyen bildirimler iletildi.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Sabah özeti gönderilirken bir hata oluştu: ' . $e->getMessage());
        }
    }
}
