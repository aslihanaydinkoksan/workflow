<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>{{ $notification->title }}</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f8fafc; padding:24px; color:#1f2937;">
    <div style="max-width:560px; margin:0 auto; background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; padding:24px;">
        <p style="margin:0 0 8px; font-size:12px; font-weight:bold; color:#6366f1; text-transform:uppercase; letter-spacing:.05em;">
            Workflow Bildirimi
        </p>
        <h1 style="margin:0 0 16px; font-size:20px;">{{ $notification->title }}</h1>
        <p style="margin:0 0 20px; line-height:1.6; white-space:pre-line;">{{ $notification->body }}</p>

        @if(!empty($notification->data['action_url']))
            <a href="{{ $notification->data['action_url'] }}"
               style="display:inline-block; background:#4f46e5; color:#ffffff; text-decoration:none; padding:10px 16px; border-radius:8px; font-weight:bold;">
                Görüntüle
            </a>
        @endif

        <p style="margin:24px 0 0; font-size:12px; color:#9ca3af;">
            Bu e-posta Workflow uygulaması tarafından otomatik gönderilmiştir.
        </p>
    </div>
</body>
</html>
