<!DOCTYPE html>
<html lang="tr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $dashboard->title }} - Yönetim Raporu</title>
    <style>
        @page {
            margin: 20mm 15mm 20mm 15mm;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.4;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .company-title {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
        }
        .report-subtitle {
            font-size: 11px;
            color: #64748b;
        }
        .meta-text {
            text-align: right;
            font-size: 10px;
            color: #64748b;
        }
        .dashboard-hero {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 20px;
        }
        .dashboard-hero h1 {
            margin: 0 0 4px 0;
            font-size: 15px;
            color: #0f172a;
        }
        .dashboard-hero p {
            margin: 0;
            color: #475569;
            font-size: 11px;
        }
        .widget-block {
            margin-bottom: 24px;
            page-break-inside: avoid;
        }
        .widget-header {
            background-color: #0f172a;
            color: #ffffff;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: bold;
            border-top-left-radius: 4px;
            border-top-right-radius: 4px;
        }
        .widget-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #cbd5e1;
        }
        .widget-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: bold;
            font-size: 10px;
            text-align: left;
            padding: 6px 8px;
            border-bottom: 1px solid #cbd5e1;
            text-transform: uppercase;
        }
        .widget-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
        }
        .widget-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .text-right {
            text-align: right;
        }
        .footer {
            position: fixed;
            bottom: -10mm;
            left: 0;
            right: 0;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }
        .kpi-value {
            font-size: 24px;
            font-weight: bold;
            color: #0f172a;
            padding: 12px;
            text-align: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <!-- Kurumsal Üst Başlık -->
    <table class="header-table">
        <tr>
            <td style="width: 70%;">
                <div class="company-title">KÖKSAN PET VE PLASTİK AMBALAJ SANAYİ A.Ş.</div>
                <div class="report-subtitle">Kurumsal İş Akışı & BPM Raporlama ve Analitik Sistemi</div>
            </td>
            <td class="meta-text" style="width: 30%;">
                <div><strong>Rapor Tarihi:</strong> {{ now()->format('d.m.Y H:i') }}</div>
                <div><strong>Filtre Aralığı:</strong> {{ $dateRangeLabel }}</div>
                <div><strong>Hazırlayan:</strong> {{ auth()->user()?->name ?? 'Sistem Otomasyonu' }}</div>
            </td>
        </tr>
    </table>

    <!-- Pano Bilgi Alanı -->
    <div class="dashboard-hero">
        <h1>{{ $dashboard->title }}</h1>
        <p>{{ $dashboard->description ?? 'Bu rapor Köksan BPM sistemindeki canlı operasyonel verilerden otomatik olarak derlenmiştir.' }}</p>
    </div>

    <!-- Widget'lar Listesi -->
    @foreach($widgets as $widget)
        <div class="widget-block">
            <div class="widget-header">
                {{ $widget->title }} ({{ strtoupper($widget->chart_type) }} · {{ $widget->aggregation_type }})
            </div>

            @if($widget->chart_type === 'kpi_card')
                <div class="kpi-value">
                    {{ number_format($widget->chart_data['value'] ?? 0, 0, ',', '.') }}
                    <div style="font-size: 11px; font-weight: normal; color: #64748b; margin-top: 4px;">
                        Toplam Hacim Değeri
                    </div>
                </div>
            @else
                <table class="widget-table">
                    <thead>
                        <tr>
                            <th style="width: 60%;">Kategori / Boyut</th>
                            <th class="text-right" style="width: 25%;">Hesaplanan Değer</th>
                            <th class="text-right" style="width: 15%;">Pay (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $rows = $widget->chart_data['table_rows'] ?? [];
                            $total = $widget->chart_data['total'] ?? 1;
                            if ($total == 0) $total = 1;
                        @endphp
                        @forelse($rows as $row)
                            <tr>
                                <td><strong>{{ $row['label'] }}</strong></td>
                                <td class="text-right">{{ number_format($row['value'], 1, ',', '.') }}</td>
                                <td class="text-right">{{ number_format(($row['value'] / $total) * 100, 1) }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" style="text-align: center; color: #94a3b8; padding: 12px;">
                                    Bu dönemde veri kaydı bulunamadı.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($rows) > 0)
                        <tfoot>
                            <tr style="background-color: #f1f5f9; font-weight: bold;">
                                <td>TOPLAM</td>
                                <td class="text-right">{{ number_format($widget->chart_data['total'] ?? 0, 1, ',', '.') }}</td>
                                <td class="text-right">%100,0</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @endif
        </div>
    @endforeach

    <!-- Alt Bilgi -->
    <div class="footer">
        Köksan Kurumsal BPM & İş Zekası Platformu · Gizli ve Şirket İçi Kullanıma Özeldir · Sayfa çıktısı otomatik üretilmiştir.
    </div>
</body>
</html>
