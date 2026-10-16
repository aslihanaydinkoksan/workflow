<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Malzeme ve Satın Alma Talep Fişi - {{ $orderNo }}</title>
    <style>
        @page {
            margin: 15mm 15mm 15mm 15mm;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #1e293b;
            line-height: 1.4;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #003366;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .company-title {
            font-size: 18px;
            font-weight: bold;
            color: #003366;
            letter-spacing: 0.5px;
        }
        .company-subtitle {
            font-size: 8.5px;
            color: #64748b;
            text-transform: uppercase;
        }
        .doc-title {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            padding: 8px 0;
            background-color: #f1f5f9;
            border-radius: 4px;
            margin-bottom: 14px;
            letter-spacing: 0.5px;
            border: 1px solid #cbd5e1;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .info-table td, .info-table th {
            padding: 5px 8px;
            border: 1px solid #cbd5e1;
            font-size: 9.5px;
        }
        .info-table th {
            background-color: #f8fafc;
            color: #334155;
            text-align: left;
            font-weight: bold;
            width: 25%;
        }
        .section-header {
            background-color: #e2e8f0;
            color: #0f172a;
            font-weight: bold;
            font-size: 10.5px;
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            text-transform: uppercase;
            margin-top: 10px;
            margin-bottom: 4px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .items-table th, .items-table td {
            border: 1px solid #cbd5e1;
            padding: 6px;
            font-size: 9px;
            text-align: left;
        }
        .items-table th {
            background-color: #003366;
            color: white;
            font-weight: bold;
        }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }
        .signature-table td {
            width: 33.33%;
            border: 1px solid #cbd5e1;
            padding: 8px;
            text-align: center;
            vertical-align: top;
            height: 80px;
        }
        .sig-title {
            font-weight: bold;
            font-size: 10px;
            color: #003366;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .sig-name {
            font-weight: bold;
            color: #1e293b;
            font-size: 9.5px;
            margin-top: 25px;
        }
        .sig-date {
            font-size: 8px;
            color: #64748b;
        }
        .footer {
            margin-top: 20px;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
            text-align: right;
            font-size: 8px;
            color: #94a3b8;
        }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="width: 65%;">
                <div class="company-title">KÖKSAN</div>
                <div class="company-subtitle">PET VE PLASTİK AMBALAJ SAN. VE TİC. A.Ş.</div>
                <div style="font-size: 8px; color: #64748b; margin-top: 2px;">
                    Satın Alma & Tedarik Zinciri Direktörlüğü
                </div>
            </td>
            <td style="width: 35%; text-align: right;">
                <div style="font-weight: bold; font-size: 11px; color: #003366;">TALEP NO: {{ $orderNo }}</div>
                <div style="font-size: 8.5px; color: #64748b; margin-top: 2px;">Form Kodu: FR-SAT-004 / Rev.02</div>
                <div style="font-size: 8.5px; color: #64748b;">Talep Tarihi: {{ $issueDate }}</div>
            </td>
        </tr>
    </table>

    <div class="doc-title">
        MALZEME VE HİZMET SATIN ALMA TALEP FORMU
    </div>

    <div class="section-header">1. TALEP BİLGİLERİ VE MASRAF MERKEZİ</div>
    <table class="info-table">
        <tr>
            <th>Talep Türü:</th>
            <td><strong>{{ $data['talep_turu'] ?? 'Yedek Parça & Rulman / Rezistans' }}</strong></td>
            <th>Masraf Merkezi:</th>
            <td><strong>{{ $data['masraf_merkezi'] ?? 'CC-1230 - LEVHA' }}</strong></td>
        </tr>
        <tr>
            <th>Aciliyet Durumu:</th>
            <td><strong style="color: #dc2626;">{{ $data['aciliyet_durumu'] ?? 'Acil / Üretim Durma Riski' }}</strong></td>
            <th>İstenen Termin:</th>
            <td>{{ $data['istenilen_termin'] ?? now()->addDays(3)->format('d.m.Y') }}</td>
        </tr>
        <tr>
            <th>Talep Eden:</th>
            <td>{{ $instance->starter?->name ?? 'Operasyon Sorumlusu' }}</td>
            <th>Departman:</th>
            <td>{{ $instance->starter?->department?->name ?? 'Levha İşletmesi' }}</td>
        </tr>
        <tr>
            <th>Talep Gerekçesi:</th>
            <td colspan="3">{{ $data['talep_gerekcesi'] ?? 'Hat 1 ekstrüzyon filtresi ve kalıp rezistansının ömrü dolmuş olup ani arızayı önlemek için kritik stok tamamlama talebidir.' }}</td>
        </tr>
    </table>

    <div class="section-header">2. TALEP EDİLEN MALZEME VE HİZMET LİSTESİ</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 8%;">Sıra</th>
                <th style="width: 45%;">Malzeme / Hizmet Tanımı</th>
                <th style="width: 12%;">Miktar</th>
                <th style="width: 12%;">Birim</th>
                <th style="width: 23%; text-align: right;">Tahmini Tutar</th>
            </tr>
        </thead>
        <tbody>
            @php
                $items = is_array($data['malzeme_kalemleri'] ?? null) ? $data['malzeme_kalemleri'] : [];
                $total = $data['genel_toplam_masraf'] ?? 42500;
            @endphp
            @if(count($items) > 0)
                @foreach($items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item['malzeme_adi'] ?? $item['tanim'] ?? 'Seramik Bant Rezistans 2500W' }}</td>
                    <td>{{ $item['miktar'] ?? '4' }}</td>
                    <td>{{ $item['birim'] ?? 'Adet' }}</td>
                    <td style="text-align: right;">{{ number_format((float)($item['tutar'] ?? 10625), 2, ',', '.') }} TL</td>
                </tr>
                @endforeach
            @else
                <tr>
                    <td>1</td>
                    <td>Seramik Kalıp Bant Rezistansı 2500W / 400V</td>
                    <td>4</td>
                    <td>Adet</td>
                    <td style="text-align: right;">18.500,00 TL</td>
                </tr>
                <tr>
                    <td>2</td>
                    <td>Yüksek Basınç Eriyik Filtresi 40 Mikron</td>
                    <td>10</td>
                    <td>Adet</td>
                    <td style="text-align: right;">24.000,00 TL</td>
                </tr>
            @endif
            <tr style="background-color: #f8fafc; font-weight: bold;">
                <td colspan="4" style="text-align: right;">GENEL TOPLAM TALEP TUTARI:</td>
                <td style="text-align: right; color: #003366; font-size: 11px;">
                    {{ number_format((float)$total, 2, ',', '.') }} TL
                </td>
            </tr>
        </tbody>
    </table>

    <div class="section-header">3. SATIN ALMA VE SAP ENTEGRASYON BİLGİSİ</div>
    <table class="info-table">
        <tr>
            <th>Seçilen Tedarikçi Firma:</th>
            <td><strong>{{ $data['secilen_tedarikci'] ?? 'REZİSTEKNİK ENDÜSTRİYEL ISITMA A.Ş.' }}</strong></td>
            <th>Nihai Sipariş Tutarı:</th>
            <td><strong>{{ number_format((float)$total, 2, ',', '.') }} TL</strong></td>
        </tr>
        <tr>
            <th>SAP Satın Alma SAS No:</th>
            <td><strong style="color: #059669; font-size: 11px;">{{ $data['sap_sas_no'] ?? 'SAS-2026-44812' }}</strong></td>
            <th>Teklif Karşılaştırma Durumu:</th>
            <td>3 Teklif Alındı · En Uygun Fiyat & Stok Onaylandı</td>
        </tr>
    </table>

    <table class="signature-table">
        <tr>
            <td>
                <div class="sig-title">TALEP EDEN</div>
                <div class="sig-name">{{ $instance->starter?->name ?? 'Talep Sahibi' }}</div>
                <div class="sig-date">{{ $issueDate }}</div>
            </td>
            <td>
                <div class="sig-title">BİRİM MÜDÜRÜ ONAYI</div>
                <div class="sig-name">Aykut ÇELİK (Müdür)</div>
                <div class="sig-date">{{ $issueDate }}</div>
            </td>
            <td>
                <div class="sig-title">SATIN ALMA MÜDÜRÜ</div>
                <div class="sig-name">Satın Alma Direktörlüğü</div>
                <div class="sig-date">{{ $issueDate }}</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Köksan Kurumsal BPM & Satın Alma Portalı · Belge Doğrulama Kodu: {{ md5($orderNo) }} · Sayfa 1 / 1
    </div>

</body>
</html>
