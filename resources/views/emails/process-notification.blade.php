<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f9;
            margin: 0;
            padding: 0;
        }

        .email-container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .email-header {
            background-color: #004b87;
            color: #ffffff;
            padding: 25px 30px;
            text-align: center;
            border-bottom: 4px solid #003366;
        }

        .email-header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .email-body {
            padding: 35px 30px;
            color: #444444;
            line-height: 1.6;
            font-size: 15px;
        }

        .action-button {
            display: inline-block;
            background-color: #0066cc;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 6px;
            font-weight: bold;
            margin-top: 25px;
            transition: background-color 0.3s;
        }

        .email-footer {
            background-color: #f9fafc;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #888888;
            border-top: 1px solid #eeeeee;
        }

        .alert-box {
            background-color: #fff8f8;
            padding: 15px 20px;
            border-left: 4px solid #cc0000;
            margin: 25px 0;
            border-radius: 0 4px 4px 0;
            color: #880000;
        }
    </style>
</head>

<body>
    <div class="email-container">
        <div class="email-header">
            <h1>Süreç Bilgilendirmesi</h1>
        </div>
        <div class="email-body">
            <p>Sayın İlgili,</p>
            <p>Başlatmış olduğunuz <strong>{{ $instance->workflow->name ?? 'İş Akışı Talebi' }}</strong> (Talep No:
                #{{ $instance->id }}) ile ilgili sistem tarafından otomatik bir bilgilendirme oluşturulmuştur.</p>

            <div class="alert-box">
                @php
                    $desc =
                        $mailData['description'] ??
                        'Sürecinizle ilgili sistem kuralları gereği yeni bir karar alınmıştır.';
                    // "Sistem Karar Notu:" metnini bularak HTML <strong> etiketi ile değiştiriyoruz
                    $desc = str_replace('Sistem Karar Notu:', '<strong>Sistem Karar Notu:</strong>', e($desc));
                @endphp
                {!! nl2br($desc) !!}
            </div>

            <p>Sürecinizin güncel durumunu takip etmek için aşağıdaki bağlantıyı kullanabilirsiniz:</p>

            <center>
                <!-- Güvenli URL Üretimi -->
                <a href="{{ rtrim(config('app.url'), '/') }}/processes/tracker/{{ $instance->id }}"
                    class="action-button">Süreci Görüntüle</a>
            </center>
        </div>
        <div class="email-footer">
            Bu e-posta KÖKSAN Workflow tarafından otomatik olarak oluşturulmuştur. Lütfen bu e-postayı yanıtlamayınız.
        </div>
    </div>
</body>

</html>
