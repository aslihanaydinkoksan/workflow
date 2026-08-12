<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f9; margin: 0; padding: 0; }
        .email-container { max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .email-header { background-color: #004b87; color: #ffffff; padding: 25px 30px; text-align: center; border-bottom: 4px solid #003366; }
        .email-header h1 { margin: 0; font-size: 22px; font-weight: 600; letter-spacing: 0.5px; }
        .email-body { padding: 35px 30px; color: #444444; line-height: 1.6; font-size: 15px; }
        .action-button { display: inline-block; background-color: #0066cc; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: bold; margin-top: 25px; transition: background-color 0.3s; }
        .action-button:hover { background-color: #004b87; }
        .email-footer { background-color: #f9fafc; padding: 20px 30px; text-align: center; font-size: 12px; color: #888888; border-top: 1px solid #eeeeee; }
        .info-box { background-color: #f8f9fa; padding: 15px 20px; border-left: 4px solid #0066cc; margin: 25px 0; border-radius: 0 4px 4px 0; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>KÖKSAN Workflow</h1>
        </div>
        <div class="email-body">
            <p>Sayın <strong>{{ $task->assignedUser->name ?? 'İlgili Personel' }}</strong>,</p>
            <p><strong>{{ $task->processInstance->workflow->name ?? 'İş Akışı Talebi' }}</strong> kapsamında tarafınıza sistem üzerinden yeni bir görev atanmıştır.</p>
            
            <div class="info-box">
                <strong>Süreç Numarası:</strong> #{{ $task->process_instance_id }}<br>
                <strong>Görev Tipi:</strong> {{ ucfirst($task->type) }}
            </div>

            <p>İlgili talebi incelemek ve aksiyon almak için aşağıdaki butonu kullanabilirsiniz:</p>
            
            <center>
                <!-- Güvenli URL Üretimi: .env içindeki APP_URL'i zorunlu kılar -->
                <a href="{{ rtrim(config('app.url'), '/') }}{{ route('tasks.show', $task->id, false) }}" class="action-button">Görevi Görüntüle</a>
            </center>
        </div>
        <div class="email-footer">
            Bu e-posta KÖKSAN Süreç Portalı tarafından otomatik olarak oluşturulmuştur. Lütfen bu e-postayı yanıtlamayınız.
        </div>
    </div>
</body>
</html>