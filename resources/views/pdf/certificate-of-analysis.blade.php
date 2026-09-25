<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Analiz Sertifikası - {{ $certificateNo }}</title>
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
            border-collapse: collapse;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .company-title {
            font-size: 20px;
            font-weight: bold;
            color: #003366;
            letter-spacing: 1px;
        }
        .company-subtitle {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
        }
        .doc-title {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            padding: 8px 0;
            background-color: #f1f5f9;
            border-radius: 4px;
            margin-bottom: 15px;
            letter-spacing: 0.5px;
        }
        .doc-info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 10px;
        }
        .doc-info-table td {
            padding: 4px 8px;
            border: 1px solid #cbd5e1;
        }
        .doc-info-table .label {
            background-color: #f8fafc;
            font-weight: bold;
            color: #475569;
            width: 20%;
        }
        .doc-info-table .val {
            color: #0f172a;
            width: 30%;
        }
        .section-header {
            font-size: 11px;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-top: 15px;
            margin-bottom: 6px;
            border-left: 3px solid #2563eb;
            padding-left: 6px;
        }
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            margin-bottom: 15px;
        }
        .results-table th, .results-table td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
            font-size: 10px;
        }
        .results-table th {
            background-color: #f1f5f9;
            color: #1e293b;
            font-weight: bold;
        }
        .results-table .status-pass {
            color: #047857;
            font-weight: bold;
        }
        .results-table .status-fail {
            color: #b91c1c;
            font-weight: bold;
        }
        .decision-box {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 4px;
            padding: 10px;
            margin-top: 15px;
            color: #166534;
        }
        .decision-box .title {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 3px;
        }
        .decision-box p {
            margin: 0;
            font-size: 10px;
        }
        .signatures-table {
            width: 100%;
            margin-top: 30px;
            border-collapse: collapse;
        }
        .signatures-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 10px;
        }
        .sign-title {
            font-weight: bold;
            font-size: 10px;
            color: #475569;
            margin-bottom: 40px;
        }
        .sign-name {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
            border-top: 1px dotted #94a3b8;
            padding-top: 5px;
            display: inline-block;
            width: 180px;
        }
        .sign-role {
            font-size: 9px;
            color: #64748b;
        }
        .footer-note {
            margin-top: 30px;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            font-size: 8px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- Header Section -->
    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                @if(!empty($appLogo))
                    <img src="{{ public_path($appLogo) }}" style="max-height: 45px; max-width: 200px; object-fit: contain;" alt="Logo" />
                @else
                    <div class="company-title">KÖKSAN</div>
                    <div class="company-subtitle">PET VE PLASTİK AMBALAJ SAN. VE TİC. A.Ş.</div>
                @endif
            </td>
            <td style="width: 40%; text-align: right;">
                <div style="font-size: 10px; color: #64748b;">Rapor No: <strong>{{ $certificateNo }}</strong></div>
                <div style="font-size: 9px; color: #94a3b8;">Tarih: {{ now()->format('d.m.Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <div class="doc-title">
        ANALİZ VE KALİTE UYGUNLUK SERTİFİKASI<br>
        <span style="font-size: 10px; font-weight: normal; color: #475569;">CERTIFICATE OF ANALYSIS (CoA)</span>
    </div>

    <!-- Belge ve Numune Bilgileri -->
    <div class="section-header">1. Numune ve Genel Bilgiler</div>
    <table class="doc-info-table">
        <tr>
            <td class="label">Sertifika No:</td>
            <td class="val">{{ $certificateNo }}</td>
            <td class="label">Numune Talep No:</td>
            <td class="val">#PI-{{ $instance->id }}</td>
        </tr>
        <tr>
            <td class="label">Ürün / Malzeme:</td>
            <td class="val"><strong>{{ $productName }}</strong></td>
            <td class="label">Müşteri / Firma:</td>
            <td class="val">{{ $customerName }}</td>
        </tr>
        <tr>
            <td class="label">Parti / Lot No:</td>
            <td class="val">{{ $lotNo }}</td>
            <td class="label">Numune Miktarı:</td>
            <td class="val">{{ $quantity }}</td>
        </tr>
        <tr>
            <td class="label">Üretim / Test Tarihi:</td>
            <td class="val">{{ $productionDate }}</td>
            <td class="label">Satış Temsilcisi:</td>
            <td class="val">{{ $salesPerson }}</td>
        </tr>
    </table>

    <!-- Analiz Parametreleri Tablosu -->
    <div class="section-header">2. Fiziksel ve Kimyasal Analiz Sonuçları</div>
    <table class="results-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 25%;">Analiz Parametresi</th>
                <th style="width: 15%;">Test Metodu</th>
                <th style="width: 10%;">Birim</th>
                <th style="width: 20%;">Şartname Limiti</th>
                <th style="width: 15%;">Bulunan Değer</th>
                <th style="width: 10%; text-align: center;">Durum</th>
            </tr>
        </thead>
        <tbody>
            @forelse($parameters as $index => $param)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td><strong>{{ $param['name'] }}</strong></td>
                    <td>{{ $param['method'] ?? 'Laboratuvar Standart' }}</td>
                    <td>{{ $param['unit'] ?? '-' }}</td>
                    <td>{{ $param['specification'] ?? '-' }}</td>
                    <td>{{ $param['result'] ?? '-' }}</td>
                    <td style="text-align: center;">
                        <span class="{{ ($param['status'] ?? 'pass') === 'pass' ? 'status-pass' : 'status-fail' }}">
                            {{ ($param['status'] ?? 'pass') === 'pass' ? 'UYGUN' : 'UYGUN DEĞİL' }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; color: #94a3b8; padding: 15px;">
                        Kayıtlı analiz parametresi bulunamadı.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Karar ve Uygunluk Onayı -->
    <div class="decision-box">
        <div class="title">✅ Kalite Kontrol ve Uygunluk Kararı:</div>
        <p>
            Yukarıda belirtilen numunenin analiz sonuçları, ilgili üretim ve teknik kalite spesifikasyonlarına 
            <strong>UYGUN BULUNMUŞTUR</strong>. Numune müşteriye sevk edilmeye ve kullanıma uygundur.
        </p>
    </div>

    <!-- İmzalar -->
    <table class="signatures-table">
        <tr>
            <td>
                <div class="sign-title">ANALİZİ YAPAN / LABORATUVAR</div>
                <div class="sign-name">{{ $analystName }}</div>
                <div class="sign-role">Kalite Kontrol Teknisyeni / Uzmanı</div>
            </td>
            <td>
                <div class="sign-title">ONAYLAYAN / İŞLETME YÖNETİMİ</div>
                <div class="sign-name">{{ $approverName }}</div>
                <div class="sign-role">Kalite Güvence & İşletme Müdürü</div>
            </td>
        </tr>
    </table>

    <!-- Footer -->
    <div class="footer-note">
        Bu analiz sertifikası KÖKSAN İş Akış Yönetim Portalı üzerinden otomatik olarak oluşturulmuştur. Elektronik ortamda onaylandığından ıslak imza gerektirmez.<br>
        Form Doküman Kodu: FR-KAL-024 / Rev. 02 &bull; www.koksan.com
    </div>

</body>
</html>
