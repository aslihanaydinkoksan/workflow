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
            background-color: #1e3a8a;
            color: #ffffff;
            padding: 25px 30px;
            text-align: center;
            border-bottom: 4px solid #1d4ed8;
        }
        .email-header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
        }
        .email-body {
            padding: 30px;
            color: #334155;
            line-height: 1.6;
            font-size: 14px;
        }
        .info-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px;
            margin: 20px 0;
        }
        .info-card table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-card td {
            padding: 6px 0;
            font-size: 13px;
        }
        .info-card .label {
            color: #64748b;
            font-weight: 600;
            width: 35%;
        }
        .info-card .val {
            color: #0f172a;
            font-weight: 500;
        }
        .action-button {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 6px;
            font-weight: bold;
            margin-top: 20px;
            font-size: 14px;
        }
        .email-footer {
            background-color: #f8fafc;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            background-color: #dbeafe;
            color: #1e40af;
            font-size: 11px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>🧪 Numune Takip & Sipariş Sorgulama</h1>
        </div>
        <div class="email-body">
            <p>Sayın <strong>{{ $followUp->assignedUser?->name ?? 'Satış Yetkilisi' }}</strong>,</p>

            <p>
                Daha önce talebini başlattığınız ve üretimi başarıyla tamamlanan numunenin takibi için sistemimiz bu bildirimi oluşturmuştur:
            </p>

            @php
                $instance = $followUp->processInstance;
                $formData = (array) ($instance?->data ?? []);
                $customer = $formData['musteri_adi'] ?? $formData['customer_name'] ?? 'Belirtilmedi';
                $product = $formData['urun_adi'] ?? $formData['material_name'] ?? $formData['numune_adi'] ?? 'Numune Ürünü';
                $quantity = $formData['miktar'] ?? $formData['quantity'] ?? '-';
            @endphp

            <div class="info-card">
                <table>
                    <tr>
                        <td class="label">Talep No:</td>
                        <td class="val"><span class="badge">#{{ $instance->id }}</span></td>
                    </tr>
                    <tr>
                        <td class="label">Müşteri:</td>
                        <td class="val"><strong>{{ $customer }}</strong></td>
                    </tr>
                    <tr>
                        <td class="label">Numune Ürünü:</td>
                        <td class="val">{{ $product }}</td>
                    </tr>
                    <tr>
                        <td class="label">Miktar:</td>
                        <td class="val">{{ $quantity }}</td>
                    </tr>
                    <tr>
                        <td class="label">Tamamlanma Tarihi:</td>
                        <td class="val">{{ $instance->updated_at ? $instance->updated_at->format('d.m.Y') : '-' }}</td>
                    </tr>
                </table>
            </div>

            <p style="font-weight: 600; color: #1e293b;">
                👉 Bu numune siparişe dönüştü mü?
            </p>
            <p style="font-size: 13px; color: #475569;">
                Lütfen aşağıdaki butona tıklayarak numunenin sipariş durumunu (sipariş numarası veya siparişe dönmeme gerekçesi) sisteme işleyiniz.
            </p>

            <center>
                <a href="{{ rtrim(config('app.url'), '/') }}/follow-ups/{{ $followUp->id }}" class="action-button">
                    Durumu Bildir / Cevapla
                </a>
            </center>

            @if($followUp->reminder_count > 0)
                <p style="font-size: 11px; color: #ea580c; text-align: center; margin-top: 15px;">
                    * Bu bildirim {{ $followUp->reminder_count }}. kez hatırlatma amaçlı iletilmektedir.
                </p>
            @endif
        </div>
        <div class="email-footer">
            Bu e-posta Köksan İş Akış Yönetim Sistemi tarafından otomatik oluşturulmuştur.
        </div>
    </div>
</body>
</html>
