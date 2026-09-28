<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MailEvent;
use App\Models\MailRule;
use App\Models\MailSetting;
use App\Models\MailTemplate;
use Illuminate\Database\Seeder;

class MailSystemSeeder extends Seeder
{
    public function run(): void
    {
        // 1. GLOBAL AYARLAR
        MailSetting::set(
            'global_kill_switch',
            false,
            'boolean',
            'Tüm dış e-posta gönderimlerini anında durduran ana şalter (Kill Switch). Açıkken hiçbir e-posta atılmaz.'
        );

        MailSetting::set(
            'test_mode',
            true,
            'boolean',
            'Test Modu: Açıkken gerçek alıcılar yerine tüm e-postalar test alıcısına yönlendirilir.'
        );

        MailSetting::set(
            'test_recipient_email',
            'aslihan.aydin@koksan.com',
            'string',
            'Test modunda tüm e-postaların yönlendirileceği hedef e-posta adresi.'
        );

        // 2. DEFAULT OLAYLAR & ŞABLONLAR & KURALLAR

        // Olay 1: Yeni Görev Ataması (task_assigned)
        $taskAssignedEvent = MailEvent::updateOrCreate(
            ['event_code' => 'task_assigned'],
            [
                'name' => 'Yeni Görev Ataması',
                'description' => 'Süreç içerisinde bir kullanıcıya veya role yeni bir görev atandığında tetiklenir.',
                'is_active' => true,
            ]
        );

        MailTemplate::updateOrCreate(
            ['mail_event_id' => $taskAssignedEvent->id],
            [
                'subject' => 'Yeni Görev: {{ workflow_name }} - {{ node_label }}',
                'body_content' => '<p>Sayın <strong>{{ user_name }}</strong>,</p>'
                    . '<p><strong>{{ workflow_name }}</strong> sürecinde üzerinize yeni bir görev atanmıştır.</p>'
                    . '<div style="background-color: #f3f4f6; padding: 15px; border-radius: 8px; margin: 15px 0;">'
                    . '<p style="margin: 0;"><strong>Görev Adımı:</strong> {{ node_label }}</p>'
                    . '<p style="margin: 5px 0 0 0;"><strong>Talep No:</strong> #{{ instance_id }}</p>'
                    . '</div>'
                    . '<p><a href="{{ action_url }}" style="display: inline-block; background-color: #4f46e5; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: bold;">Görevi İncele ve Tamamla</a></p>',
                'is_active' => true,
            ]
        );

        MailRule::updateOrCreate(
            ['mail_event_id' => $taskAssignedEvent->id],
            [
                'target_users' => null, // Herkes
                'target_roles' => null, // Her rol
                'target_departments' => null, // Tüm departmanlar
                'allowed_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                'allowed_time_start' => '08:00:00',
                'allowed_time_end' => '19:00:00',
                'is_active' => true,
            ]
        );

        // Olay 2: Süreç Başarıyla Tamamlandı (process_completed)
        $processCompletedEvent = MailEvent::updateOrCreate(
            ['event_code' => 'process_completed'],
            [
                'name' => 'Süreç Tamamlandı',
                'description' => 'Başlatılan bir iş akışı tüm onay adımlarını geçerek başarıyla tamamlandığında talep sahibine bildirilir.',
                'is_active' => true,
            ]
        );

        MailTemplate::updateOrCreate(
            ['mail_event_id' => $processCompletedEvent->id],
            [
                'subject' => 'Talebiniz Başarıyla Tamamlandı: #{{ instance_id }} {{ workflow_name }}',
                'body_content' => '<p>Sayın <strong>{{ starter_name }}</strong>,</p>'
                    . '<p>Başlatmış olduğunuz <strong>#{{ instance_id }}</strong> numaralı <strong>{{ workflow_name }}</strong> süreci tüm onay aşamalarını geçerek başarıyla tamamlanmıştır.</p>'
                    . '<p><a href="{{ tracker_url }}" style="display: inline-block; background-color: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: bold;">Süreç Detaylarını Gör</a></p>',
                'is_active' => true,
            ]
        );

        MailRule::updateOrCreate(
            ['mail_event_id' => $processCompletedEvent->id],
            [
                'target_users' => null,
                'target_roles' => null,
                'target_departments' => null,
                'allowed_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                'allowed_time_start' => '08:00:00',
                'allowed_time_end' => '19:00:00',
                'is_active' => true,
            ]
        );

        // Olay 3: Görev / Talep Reddedildi (task_rejected)
        $taskRejectedEvent = MailEvent::updateOrCreate(
            ['event_code' => 'task_rejected'],
            [
                'name' => 'Görev / Talep Reddedildi',
                'description' => 'Bir onay adımında red kararı verildiğinde talep sahibine gerekçesiyle bildirilir.',
                'is_active' => true,
            ]
        );

        MailTemplate::updateOrCreate(
            ['mail_event_id' => $taskRejectedEvent->id],
            [
                'subject' => 'Talebiniz Reddedildi: #{{ instance_id }} {{ workflow_name }}',
                'body_content' => '<p>Sayın <strong>{{ starter_name }}</strong>,</p>'
                    . '<p><strong>{{ workflow_name }}</strong> sürecindeki <strong>{{ node_label }}</strong> adımı <strong>{{ rejected_by }}</strong> tarafından reddedilmiştir.</p>'
                    . '<blockquote style="border-left: 4px solid #ef4444; padding-left: 10px; margin: 15px 0; color: #b91c1c;"><strong>Gerekçe:</strong> {{ comment }}</blockquote>'
                    . '<p><a href="{{ tracker_url }}" style="display: inline-block; background-color: #ef4444; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: bold;">Talebi Görüntüle</a></p>',
                'is_active' => true,
            ]
        );

        MailRule::updateOrCreate(
            ['mail_event_id' => $taskRejectedEvent->id],
            [
                'target_users' => null,
                'target_roles' => null,
                'target_departments' => null,
                'allowed_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                'allowed_time_start' => '08:00:00',
                'allowed_time_end' => '19:00:00',
                'is_active' => true,
            ]
        );
    }
}
