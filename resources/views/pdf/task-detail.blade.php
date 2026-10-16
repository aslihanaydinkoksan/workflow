<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>{{ $templateName }}</title>
    <style>
        @page {
            margin: {{ ($paperSize ?? 'a4') === 'a5' ? '8mm 8mm 8mm 8mm' : '12mm 14mm 12mm 14mm' }};
            size: {{ ($paperSize ?? 'a4') === 'a5' ? 'a5 portrait' : 'a4 portrait' }};
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: {{ ($paperSize ?? 'a4') === 'a5' ? '9px' : '10.5px' }};
            color: #1e293b;
            line-height: 1.35;
        }

        /* Köksan Resmi 3 Kutulu Üst Başlık Tablosu */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
            margin-bottom: {{ ($paperSize ?? 'a4') === 'a5' ? '10px' : '14px' }};
        }
        .header-table td {
            border: 1px solid #000;
            vertical-align: middle;
        }
        .header-logo-cell {
            width: {{ ($paperSize ?? 'a4') === 'a5' ? '22%' : '24%' }};
            text-align: center;
            padding: 4px;
            background-color: #fff;
        }
        .header-logo-cell img {
            max-width: {{ ($paperSize ?? 'a4') === 'a5' ? '120px' : '150px' }};
            max-height: {{ ($paperSize ?? 'a4') === 'a5' ? '40px' : '52px' }};
        }
        .header-title-cell {
            width: {{ ($paperSize ?? 'a4') === 'a5' ? '50%' : '48%' }};
            text-align: center;
            padding: {{ ($paperSize ?? 'a4') === 'a5' ? '4px' : '6px' }};
        }
        .header-title-cell h1 {
            font-size: {{ ($paperSize ?? 'a4') === 'a5' ? '11px' : '14px' }};
            font-weight: bold;
            margin: 0;
            color: #000;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header-title-cell .sub-desc {
            font-size: {{ ($paperSize ?? 'a4') === 'a5' ? '7.5px' : '8.5px' }};
            color: #64748b;
            margin-top: 2px;
        }
        .header-meta-cell {
            width: {{ ($paperSize ?? 'a4') === 'a5' ? '28%' : '28%' }};
            padding: 0;
        }
        .meta-inner-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-inner-table td {
            border: none;
            border-bottom: 1px solid #000;
            padding: {{ ($paperSize ?? 'a4') === 'a5' ? '2px 4px' : '3px 6px' }};
            font-size: {{ ($paperSize ?? 'a4') === 'a5' ? '7.5px' : '8.5px' }};
        }
        .meta-inner-table tr:last-child td {
            border-bottom: none;
        }
        .meta-label {
            font-weight: bold;
            width: 50%;
            border-right: 1px solid #000 !important;
            background-color: #f8fafc;
        }

        /* Form Tablosu */
        .form-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .form-table th, .form-table td {
            border: 1px solid #94a3b8;
            padding: {{ ($paperSize ?? 'a4') === 'a5' ? '4px 6px' : '6px 8px' }};
            text-align: left;
            vertical-align: top;
        }
        .form-table th {
            background-color: #f1f5f9;
            font-weight: bold;
            width: 38%;
            color: #334155;
            font-size: {{ ($paperSize ?? 'a4') === 'a5' ? '8.5px' : '9.5px' }};
        }
        .form-table td {
            width: 62%;
            color: #0f172a;
            font-size: {{ ($paperSize ?? 'a4') === 'a5' ? '8.5px' : '9.5px' }};
        }
        .section-header-row td {
            background-color: #e2e8f0;
            font-weight: bold;
            font-size: {{ ($paperSize ?? 'a4') === 'a5' ? '9px' : '10.5px' }};
            color: #0f172a;
            text-transform: uppercase;
            padding: {{ ($paperSize ?? 'a4') === 'a5' ? '5px 8px' : '7px 10px' }};
            border-left: 3px solid #3b82f6;
        }

        /* Karar Rozetleri */
        .decision-badge-yes {
            display: inline-block;
            background-color: #ecfdf5;
            border: 1.5px solid #10b981;
            color: #065f46;
            font-weight: bold;
            padding: 3px 8px;
            border-radius: 4px;
        }
        .decision-badge-no {
            display: inline-block;
            background-color: #fff1f2;
            border: 1.5px solid #f43f5e;
            color: #9f1239;
            font-weight: bold;
            padding: 3px 8px;
            border-radius: 4px;
        }
        .decision-note {
            margin-top: 4px;
            font-size: 8px;
            color: #475569;
            font-style: italic;
        }

        /* Alt Bilgi */
        .footer-note {
            margin-top: 12px;
            text-align: right;
            font-size: {{ ($paperSize ?? 'a4') === 'a5' ? '7px' : '8px' }};
            color: #64748b;
        }
    </style>
</head>
<body>

    <!-- 1. KÖKSAN 3 KUTULU DOKÜMAN BAŞLIĞI -->
    <table class="header-table">
        <tr>
            <!-- Sol Kutu: Logo -->
            <td class="header-logo-cell">
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" alt="KÖKSAN">
                @else
                    <div style="font-weight:bold; font-size:14px; color:#1e3a8a;">KÖKSAN</div>
                @endif
            </td>

            <!-- Orta Kutu: Başlık -->
            <td class="header-title-cell">
                <h1>{{ $templateName }}</h1>
                @if(!empty($description))
                    <div class="sub-desc">{{ $description }}</div>
                @endif
            </td>

            <!-- Sağ Kutu: Doküman Kontrol Bilgileri -->
            <td class="header-meta-cell">
                <table class="meta-inner-table">
                    <tr>
                        <td class="meta-label">Doküman No</td>
                        <td>{{ $documentNo ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Yayın Tarihi</td>
                        <td>{{ $publishDate ? \Carbon\Carbon::parse($publishDate)->format('d.m.Y') : '-' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Rev. No / Tarih</td>
                        <td>{{ $revisionNo ?? '0' }} / {{ $revisionDate ? \Carbon\Carbon::parse($revisionDate)->format('d.m.Y') : '-' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Sayfa No</td>
                        <td>{{ $pageNo ?? 1 }} / 1 ({{ strtoupper($paperSize ?? 'A4') }})</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- 2. FORM ALANLARI VE VERİLER -->
    <table class="form-table">
        <tbody>
            @foreach($elements as $element)
                @if($element['type'] === 'header')
                    <tr class="section-header-row">
                        <td colspan="2">
                            {{ $element['label'] }}
                            @if(!empty($element['description']))
                                <div style="font-size:7.5px; font-weight:normal; color:#475569; text-transform:none; margin-top:2px;">
                                    {{ $element['description'] }}
                                </div>
                            @endif
                        </td>
                    </tr>
                @elseif($element['type'] === 'yes_no')
                    <tr>
                        <th>⚖️ {{ $element['label'] }}</th>
                        <td>
                            @php
                                $val = $formData[$element['id']] ?? '';
                                $rawVal = $rawFormData[$element['id']] ?? '';
                                $note = $rawFormData[$element['id'] . '_note'] ?? '';
                                $isYes = in_array(mb_strtolower((string)$rawVal, 'UTF-8'), ['evet', 'kabul', 'onay', 'onaylandı', 'true', mb_strtolower($element['yes_label'] ?? 'evet', 'UTF-8')]);
                                $isNo = in_array(mb_strtolower((string)$rawVal, 'UTF-8'), ['hayır', 'ret', 'reddedildi', 'iptal', 'false', mb_strtolower($element['no_label'] ?? 'hayır', 'UTF-8')]);
                            @endphp

                            @if($isYes)
                                <div class="decision-badge-yes">
                                    ✅ {{ $element['yes_label'] ?? 'EVET / ONAYLANDI' }}
                                </div>
                            @elseif($isNo)
                                <div class="decision-badge-no">
                                    ❌ {{ $element['no_label'] ?? 'HAYIR / REDDEDİLDİ' }}
                                </div>
                            @else
                                <span style="color:#94a3b8; font-style:italic;">- (Karar Belirtilmedi)</span>
                            @endif

                            @if(!empty($note))
                                <div class="decision-note">
                                    <strong>Gerekçe / Açıklama:</strong> {{ $note }}
                                </div>
                            @endif
                        </td>
                    </tr>
                @else
                    <tr>
                        <th>{{ $element['label'] }}</th>
                        <td>
                            @php
                                $val = $formData[$element['id']] ?? '';
                            @endphp
                            {{ empty($val) ? '-' : $val }}
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <div class="footer-note">
        KÖKSAN PET VE PLASTİK AMBALAJ SAN. TİC. A.Ş. · Sistem Döküm Tarihi: {{ now()->format('d.m.Y H:i') }}
    </div>

</body>
</html>