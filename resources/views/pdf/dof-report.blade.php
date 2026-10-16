<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Düzeltici ve Önleyici Faaliyet (DÖF / 8D) Raporu - {{ $orderNo }}</title>
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
        .why-box {
            background-color: #f8fafc;
            border-left: 3px solid #6366f1;
            padding: 6px 10px;
            margin-bottom: 4px;
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
        /* 1. KÖKSAN RESMİ 3'LÜ ANTET ÇERÇEVESİ */
        .header-frame {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #1e293b;
            margin-bottom: 12px;
        }
        .header-frame td {
            vertical-align: middle;
            border: 1.5px solid #1e293b;
        }
        .header-logo-cell {
            width: 25%;
            padding: 6px 8px;
            text-align: center;
            background-color: #ffffff;
        }
        .header-logo-cell img {
            max-height: 48px;
            max-width: 175px;
            display: inline-block;
        }
        .header-logo-fallback {
            font-size: 16px;
            font-weight: bold;
            color: #003366;
            letter-spacing: 1px;
        }
        .header-logo-sub {
            font-size: 7.5px;
            color: #64748b;
        }
        .header-title-cell {
            width: 47%;
            padding: 8px 10px;
            text-align: center;
            background-color: #f8fafc;
        }
        .header-doc-title {
            font-size: 12.5px;
            font-weight: bold;
            color: #003366;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1.3;
        }
        .header-doc-subtitle {
            font-size: 8px;
            color: #475569;
            font-weight: normal;
            margin-top: 3px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .header-control-cell {
            width: 28%;
            padding: 0;
            background-color: #ffffff;
        }
        .control-table {
            width: 100%;
            border-collapse: collapse;
        }
        .control-table td {
            padding: 3px 6px;
            font-size: 8.5px;
            border: none;
            border-bottom: 1px solid #cbd5e1;
        }
        .control-table tr:last-child td {
            border-bottom: none;
        }
        .control-lbl {
            font-weight: bold;
            color: #334155;
            background-color: #f1f5f9;
            width: 52%;
            border-right: 1px solid #cbd5e1 !important;
        }
        .control-val {
            color: #0f172a;
            font-weight: bold;
            text-align: center;
            width: 48%;
        }
    </style>
</head>
<body>

    <!-- Üst Başlık (Köksan Kalite Standart Anteti) -->
    <table class="header-frame">
        <tr>
            <!-- SOL: KÖKSAN KURUMSAL AMBLEM & LOGO -->
            <td class="header-logo-cell">
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" alt="KÖKSAN" />
                @else
                    <div class="header-logo-fallback">KÖKSAN</div>
                    <div class="header-logo-sub">PET VE PLASTİK AMBALAJ SAN. VE TİC. A.Ş.</div>
                @endif
            </td>

            <!-- ORTA: DOKÜMAN / RAPOR BAŞLIĞI -->
            <td class="header-title-cell">
                <div class="header-doc-title">DÜZELTİCİ VE ÖNLEYİCİ FAALİYET (DÖF / 8D) RAPORU</div>
                <div class="header-doc-subtitle">CORRECTIVE & PREVENTIVE ACTION REPORT</div>
            </td>

            <!-- SAĞ: DOKÜMAN KONTROL VE KALİTE KÜNYESİ -->
            <td class="header-control-cell">
                <table class="control-table">
                    <tr>
                        <td class="control-lbl">Doküman No</td>
                        <td class="control-val">{{ $documentNo ?? 'FR-KAL-015' }}</td>
                    </tr>
                    <tr>
                        <td class="control-lbl">Yayın Tarihi</td>
                        <td class="control-val">{{ $publishDate ?? '13.10.2026' }}</td>
                    </tr>
                    <tr>
                        <td class="control-lbl">Revizyon No / Tarih</td>
                        <td class="control-val">{{ $revisionInfo ?? '04 / 13.10.2026' }}</td>
                    </tr>
                    <tr>
                        <td class="control-lbl">Sayfa No</td>
                        <td class="control-val">{{ $pageNo ?? '1 / 1' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="section-header">1. UYGUNSUZLUK TANIMI VE KAYNAĞI</div>
    <table class="info-table">
        <tr>
            <th>DÖF Kaynağı:</th>
            <td><strong>{{ $data['dof_kaynagi'] ?? 'Müşteri Şikayeti / Proses Sapması' }}</strong></td>
            <th>Ürün Grubu:</th>
            <td>{{ $data['urun_grubu'] ?? 'PET Levha (EVOH Çok Katmanlı)' }}</td>
        </tr>
        <tr>
            <th>Parti / Lot No:</th>
            <td>{{ $data['parti_lot_no'] ?? 'L-2026-9944' }}</td>
            <th>Etkilenen Miktar:</th>
            <td>{{ $data['etkilenen_miktar_kg'] ?? '1.250' }} KG</td>
        </tr>
        <tr>
            <th>Uygunsuzluk Tanımı:</th>
            <td colspan="3">{{ $data['uygunsuzluk_tanimi'] ?? 'Müşteride termoform denemesinde film kalınlık dalgalanması ve kenar yapışma hatası gözlenmiştir.' }}</td>
        </tr>
        <tr>
            <th>Acil Aksiyon & Karantina:</th>
            <td colspan="3"><strong style="color: #b91c1c;">{{ $data['acil_aksiyon_karari'] ?? 'Karantinaya Alındı (Bloke)' }}</strong> - Karantina No: {{ $data['karantina_etiket_no'] ?? 'BLOK-2026-081' }}</td>
        </tr>
    </table>

    <div class="section-header">2. KÖK NEDEN ANALİZİ (5-WHY / BALIK KILÇIĞI)</div>
    <table class="info-table">
        <tr>
            <td colspan="4" style="background-color: #faf5ff;">
                <div class="why-box"><strong>1. Neden?</strong> Termoform esnasında film neden homojen gerilmedi? -> Kalınlık profili tolerans dışına çıktı (+70 µm).</div>
                <div class="why-box"><strong>2. Neden?</strong> Kalınlık profili neden dalgalandı? -> Ekstrüder kalıp dudak ısıtıcı rezistansında lokal sıcaklık düşüşü oldu.</div>
                <div class="why-box"><strong>3. Neden?</strong> Isıtıcı neden sıcaklık kaybetti? -> Termokupl soketinde temassızlık ve kirlenme meydana geldi.</div>
                <div class="why-box"><strong>4. Neden?</strong> Temassızlık zamanında fark edilmedi? -> Vardiya öncesi profil sensörü kalibrasyon kontrolü atlandı.</div>
                <div class="why-box"><strong>5. Kök Neden:</strong> <strong>Kalıp ısıtma zonları için periyodik kalibrasyon kontrol formu ve PID sıcaklık alarm toleransı daraltılmamıştı.</strong></div>
            </td>
        </tr>
    </table>

    <div class="section-header">3. KALICI AKSİYON PLANI VE ETKİNLİK DOĞRULAMA</div>
    <table class="info-table">
        <tr>
            <th>Kalıcı Aksiyon:</th>
            <td colspan="3">{{ $data['kalici_aksiyon_tanimi'] ?? 'Tüm hat ekstrüzyon kalıp zonlarına ±2°C limitli PID sesli alarm tanımlandı ve vardiya devir teslim kontrol listesine eklendi.' }}</td>
        </tr>
        <tr>
            <th>Aksiyon Sorumlusu:</th>
            <td>{{ $data['aksiyon_sorumlusu'] ?? 'Levha İşletme Şefi / Elektrik Bakım' }}</td>
            <th>Hedef Termin:</th>
            <td>{{ $data['hedef_termin_tarihi'] ?? now()->addDays(14)->format('d.m.Y') }}</td>
        </tr>
        <tr>
            <th>Etkinlik Doğrulama:</th>
            <td colspan="3"><strong style="color: #059669;">✓ {{ $data['etkinlik_dogrulama_sonucu'] ?? 'Faaliyet Başarılı - Sonraki 5 partide kalınlık sapması sıfırlandı. DÖF Kapatıldı.' }}</strong></td>
        </tr>
    </table>

    <table class="signature-table">
        <tr>
            <td>
                <div class="sig-title">DÖF BAŞLATAN (KALİTE)</div>
                <div class="sig-name">{{ $instance->starter?->name ?? 'Kalite Güvence Uzmanı' }}</div>
                <div class="sig-date">{{ $issueDate }}</div>
            </td>
            <td>
                <div class="sig-title">AKSİYON SORUMLUSU (İŞLETME)</div>
                <div class="sig-name">{{ $data['aksiyon_sorumlusu'] ?? 'Birim Amiri' }}</div>
                <div class="sig-date">{{ $issueDate }}</div>
            </td>
            <td>
                <div class="sig-title">KALİTE GÜVENCE MÜDÜRÜ</div>
                <div class="sig-name">Aykut ÇELİK (Kalite Müdürü)</div>
                <div class="sig-date">{{ $issueDate }}</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Köksan Kurumsal BPM & Kalite Sistemi · Belge Doğrulama Kodu: {{ md5($orderNo) }} · Sayfa 1 / 1
    </div>

</body>
</html>
