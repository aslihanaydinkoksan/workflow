<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Makine Arıza ve Bakım Onarım İş Emri - {{ $orderNo }}</title>
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
        .highlight-box {
            background-color: #fef2f2;
            border: 1px solid #f87171;
            border-left: 4px solid #ef4444;
            padding: 8px 12px;
            margin-bottom: 14px;
            border-radius: 4px;
            font-size: 9.5px;
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
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8.5px;
            font-weight: bold;
            color: white;
            background-color: #ef4444;
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
                    Merkez: 4. OSB 83414 Nolu Cad. No:10 Şehitkamil / GAZİANTEP
                </div>
            </td>
            <td style="width: 35%; text-align: right;">
                <div style="font-weight: bold; font-size: 11px; color: #003366;">İŞ EMRİ NO: {{ $orderNo }}</div>
                <div style="font-size: 8.5px; color: #64748b; margin-top: 2px;">Form Kodu: FR-BAK-001 / Rev.03</div>
                <div style="font-size: 8.5px; color: #64748b;">Düzenleme Tarihi: {{ $issueDate }}</div>
            </td>
        </tr>
    </table>

    <div class="doc-title">
        MAKİNE ARIZA, BAKIM ONARIM VE TPM İŞ EMRİ FORMU
    </div>

    <div class="section-header">1. ARIZA BİLDİRİMİ VE MAKİNE BİLGİSİ</div>
    <table class="info-table">
        <tr>
            <th>İşletme / Bölüm:</th>
            <td>{{ $data['tesis_bolum'] ?? 'Levha Üretim İşletmesi' }}</td>
            <th>Makine / Hat Tanımı:</th>
            <td><strong>{{ $data['makine_hatti'] ?? 'Betapak FFS 250 Thermoformer' }}</strong></td>
        </tr>
        <tr>
            <th>Arıza Tipi:</th>
            <td>{{ $data['ariza_tipi'] ?? 'Mekanik Arıza & Rulman Aşınması' }}</td>
            <th>Öncelik Derecesi:</th>
            <td>
                <span class="badge" style="background-color: {{ ($data['oncelik_derecesi'] ?? '') === 'Kritik / Hat Durdu (Acil)' ? '#ef4444' : '#f59e0b' }};">
                    {{ $data['oncelik_derecesi'] ?? 'Kritik / Hat Durdu (Acil)' }}
                </span>
            </td>
        </tr>
        <tr>
            <th>Arıza Başlangıç Zamanı:</th>
            <td>{{ $data['ariza_baslangic_zamani'] ?? now()->subHours(2)->format('d.m.Y H:i') }}</td>
            <th>Bildiren Personel:</th>
            <td>{{ $data['bildiren_operator'] ?? ($instance->starter?->name ?? 'Operatör') }}</td>
        </tr>
        <tr>
            <th>Arıza Tanımı & Belirtileri:</th>
            <td colspan="3">{{ $data['ariza_aciklamasi'] ?? 'Makine ana tahrik ünitesinde aşırı sürtünme ve ses tespit edildi. Hat güvenlik duruşuna geçti.' }}</td>
        </tr>
    </table>

    <div class="section-header">2. BAKIM ONARIM MÜDAHALESİ VE DURUŞ SÜRESİ KAYDI</div>
    <table class="info-table">
        <tr>
            <th>Müdahale Başlangıç / Bitiş:</th>
            <td>{{ $data['bakim_mudahale_baslangic'] ?? now()->subMinutes(90)->format('H:i') }} - {{ $data['bakim_mudahale_bitis'] ?? now()->format('H:i') }}</td>
            <th>Net Duruş Süresi (Downtime):</th>
            <td><strong style="color: #dc2626; font-size: 11px;">{{ $data['net_durus_suresi_dk'] ?? '90' }} Dakika (1.5 Saat)</strong></td>
        </tr>
        <tr>
            <th>Arıza Kök Nedeni:</th>
            <td colspan="3"><strong>{{ $data['ariza_kok_nedeni'] ?? 'Aşınma / Yıpranma & Periyodik Yağlama Eksikliği' }}</strong></td>
        </tr>
        <tr>
            <th>Yapılan İşlem & Müdahale Özeti:</th>
            <td colspan="3">{{ $data['yapilan_mudahale'] ?? 'Tahrik milindeki deforme olmuş konik rulman söküldü, yuva temizlendi, yeni rulman preslendi ve yüksek sıcaklık gresi uygulandı.' }}</td>
        </tr>
        <tr>
            <th>Değiştirilen Parçalar:</th>
            <td colspan="3">{{ $data['degisen_yedek_parcalar'] ?? 'SKF 6208-2RS Rulman (1 Adet), NBR 40x62x7 Yağ Keçesi (1 Adet)' }}</td>
        </tr>
    </table>

    <div class="section-header">3. TEST, KALİTE VE ÇALIŞIR DEVREYE ALMA KABULÜ</div>
    <table class="info-table">
        <tr>
            <th>Test ve Devreye Alma Durumu:</th>
            <td colspan="3">
                <strong style="color: #059669;">
                    ✓ {{ $data['makine_test_durumu'] ?? 'Test Edildi - Normal Hızda ve Yüksüz/Yüklü Çalışıyor' }}
                </strong>
            </td>
        </tr>
        <tr>
            <th>Vardiya Şefi Kabul Notu:</th>
            <td colspan="3">{{ $data['test_kabul_notu'] ?? 'Hat test ürünleri basılarak kontrol edildi, toleranslar uygun, üretim hattı tam kapasite devreye alındı.' }}</td>
        </tr>
    </table>

    <table class="signature-table">
        <tr>
            <td>
                <div class="sig-title">BİLDİREN OPERATÖR</div>
                <div class="sig-name">{{ $data['bildiren_operator'] ?? ($instance->starter?->name ?? 'Operatör') }}</div>
                <div class="sig-date">{{ $issueDate }}</div>
            </td>
            <td>
                <div class="sig-title">BAKIM ONARIM TEKNİSYENİ</div>
                <div class="sig-name">{{ $data['mudahale_eden_teknisyen'] ?? 'Bakım Ekibi / Serkan TELEK' }}</div>
                <div class="sig-date">{{ $issueDate }}</div>
            </td>
            <td>
                <div class="sig-title">ÜRETİM / VARDİYA ŞEFİ KABULÜ</div>
                <div class="sig-name">{{ $sefiName ?? 'Hakan DEMİR (Vardiya Şefi)' }}</div>
                <div class="sig-date">{{ $issueDate }}</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Köksan Kurumsal BPM & TPM Sistemi · Belge Doğrulama Kodu: {{ md5($orderNo) }} · Sayfa 1 / 1
    </div>

</body>
</html>
