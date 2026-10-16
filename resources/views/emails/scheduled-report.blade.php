<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $schedule->title }}</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 24px; color: #1e293b;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 650px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
        <!-- Üst Banner -->
        <tr>
            <td style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 28px 24px; text-align: left;">
                <div style="color: #38bdf8; font-size: 11px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 6px;">
                    KÖKSAN BPM · KURUMSAL RAPORLAMA VE İŞ ZEKASI
                </div>
                <h1 style="color: #ffffff; font-size: 20px; font-weight: bold; margin: 0 0 6px 0;">
                    {{ $schedule->title }}
                </h1>
                <p style="color: #94a3b8; font-size: 13px; margin: 0;">
                    Zamanlanmış Otomatik Yönetim Raporu · {{ now()->format('d.m.Y H:i') }}
                </p>
            </td>
        </tr>

        <!-- Bilgilendirme -->
        <tr>
            <td style="padding: 24px;">
                <p style="font-size: 14px; line-height: 1.5; color: #334155; margin-top: 0;">
                    Sayın Yetkili,
                </p>
                <p style="font-size: 13px; line-height: 1.5; color: #475569;">
                    Aşağıda <strong>{{ $dashboard->title }}</strong> panosuna ait güncel operasyonel veriler ve performans grafikleri özetlenmiştir. Raporun tam kapsamlı <strong>PDF</strong> dökümü ve veri tablosu <strong>Excel (.xlsx)</strong> formatında bu e-postanın ekinde sunulmuştur.
                </p>

                <!-- Widget Özetleri -->
                @foreach($widgets as $widget)
                    <div style="margin-top: 20px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                        <div style="background-color: #f8fafc; padding: 10px 14px; border-bottom: 1px solid #e2e8f0; font-weight: bold; font-size: 13px; color: #0f172a;">
                            📊 {{ $widget->title }}
                        </div>
                        <div style="padding: 12px 14px;">
                            @if($widget->chart_type === 'kpi_card')
                                <div style="font-size: 28px; font-weight: bold; color: #0f172a; text-align: center; padding: 10px 0;">
                                    {{ number_format($widget->chart_data['value'] ?? 0, 0, ',', '.') }}
                                </div>
                            @else
                                <table width="100%" cellpadding="6" cellspacing="0" style="font-size: 12px; border-collapse: collapse;">
                                    <tr style="border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 11px; text-transform: uppercase;">
                                        <th align="left">Kategori</th>
                                        <th align="right">Değer</th>
                                        <th align="right">Pay</th>
                                    </tr>
                                    @php
                                        $rows = array_slice($widget->chart_data['table_rows'] ?? [], 0, 5);
                                        $tot = $widget->chart_data['total'] ?? 1;
                                        if ($tot == 0) $tot = 1;
                                    @endphp
                                    @foreach($rows as $r)
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td style="color: #1e293b; font-weight: 500;">{{ $r['label'] }}</td>
                                            <td align="right" style="color: #0f172a; font-weight: bold;">{{ number_format($r['value'], 0, ',', '.') }}</td>
                                            <td align="right" style="color: #64748b;">%{{ number_format(($r['value'] / $tot) * 100, 1) }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif
                        </div>
                    </div>
                @endforeach

                <!-- Ek Bilgisi -->
                <div style="margin-top: 24px; padding: 14px; background-color: #f0fdf4; border-radius: 8px; border: 1px solid #bbf7d0; font-size: 12px; color: #166534;">
                    📎 <strong>Ekli Dosyalar:</strong> Bu e-postaya resmi <strong>{{ $dashboard->title }}.pdf</strong> ve veri tablolarını içeren <strong>{{ $dashboard->title }}.xlsx</strong> eklenmiştir.
                </div>
            </td>
        </tr>

        <!-- Alt Bilgi -->
        <tr>
            <td style="background-color: #f8fafc; padding: 16px 24px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 11px; color: #94a3b8;">
                Bu e-posta Köksan İş Akışı ve Süreç Yönetim Sistemi tarafından otomatik oluşturulmuştur. Cevaplamayınız.
            </td>
        </tr>
    </table>
</body>
</html>
