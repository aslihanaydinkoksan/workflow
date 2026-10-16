<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Dış Görevlendirme ve Seyahat İzin Belgesi - {{ $orderNo }}</title>
    <style>
        @page {
            margin: 15mm 15mm 15mm 15mm;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10.5px;
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
            padding: 7px 0;
            background-color: #f1f5f9;
            border-radius: 4px;
            margin-bottom: 14px;
            letter-spacing: 0.5px;
            border: 1px solid #e2e8f0;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .info-table td, .info-table th {
            padding: 5px 8px;
            border: 1px solid #cbd5e1;
            font-size: 10px;
        }
        .info-table th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: bold;
            width: 25%;
        }
        .section-header {
            font-size: 11px;
            font-weight: bold;
            color: #003366;
            background-color: #e2e8f0;
            padding: 5px 8px;
            margin-top: 10px;
            margin-bottom: 6px;
            border-left: 4px solid #003366;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }
        .signatures-table td {
            width: 25%;
            border: 1px solid #cbd5e1;
            padding: 8px;
            text-align: center;
            vertical-align: top;
        }
        .sig-title {
            font-size: 9.5px;
            font-weight: bold;
            color: #475569;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .sig-name {
            font-size: 10px;
            font-weight: bold;
            color: #0f172a;
        }
        .sig-date {
            font-size: 8.5px;
            color: #64748b;
            margin-top: 2px;
        }
        .sig-badge {
            display: inline-block;
            margin-top: 15px;
            padding: 2px 6px;
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
            border-radius: 3px;
            font-size: 8.5px;
            font-weight: bold;
        }
        .footer-note {
            margin-top: 20px;
            padding: 8px;
            background-color: #f8fafc;
            border-left: 3px solid #64748b;
            font-size: 8.5px;
            color: #64748b;
            line-height: 1.3;
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
            font-size: 13px;
            font-weight: bold;
            color: #003366;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1.3;
        }
        .header-doc-subtitle {
            font-size: 8.5px;
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
                <div class="header-doc-title">DIŞ GÖREVLENDİRME VE SEYAHAT İZİN BELGESİ</div>
                <div class="header-doc-subtitle">OFFICIAL BUSINESS TRAVEL ORDER & PERMIT</div>
            </td>

            <!-- SAĞ: DOKÜMAN KONTROL VE KALİTE KÜNYESİ -->
            <td class="header-control-cell">
                <table class="control-table">
                    <tr>
                        <td class="control-lbl">Doküman No</td>
                        <td class="control-val">{{ $documentNo ?? 'FR-IDR-008' }}</td>
                    </tr>
                    <tr>
                        <td class="control-lbl">Yayın Tarihi</td>
                        <td class="control-val">{{ $publishDate ?? '25.09.2026' }}</td>
                    </tr>
                    <tr>
                        <td class="control-lbl">Revizyon No / Tarih</td>
                        <td class="control-val">{{ $revisionInfo ?? '01 / 25.09.2026' }}</td>
                    </tr>
                    <tr>
                        <td class="control-lbl">Sayfa No</td>
                        <td class="control-val">{{ $pageNo ?? '1 / 1' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- 1. Görev ve Personel Bilgileri -->
    <div class="section-header">1. GÖREV VE PERSONEL BİLGİLERİ</div>
    <table class="info-table">
        <tr>
            <th>Talep No / Akış ID</th>
            <td><strong>#{{ $instance->id }}</strong></td>
            <th>Görev Türü / Amacı</th>
            <td><strong>{{ $data['gorev_kategorisi'] ?? 'Kurumsal Dış Görev' }}</strong></td>
        </tr>
        <tr>
            <th>Görevlendirilen Personel(ler)</th>
            <td colspan="3"><strong>{{ $data['gorevli_personeller'] ?? ($instance->starter?->name ?? 'Personel') }}</strong></td>
        </tr>
        <tr>
            <th>Ziyaret Edilecek Kurum / Firma</th>
            <td>{{ $data['ziyaret_edilecek_firma'] ?? '-' }}</td>
            <th>Gidilecek Şehir / Ülke</th>
            <td>{{ $data['hedef_sehir_ulke'] ?? '-' }} ({{ $data['seyahat_bolgesi'] ?? 'Yurt İçi' }})</td>
        </tr>
        <tr>
            <th>Çıkış / Gidiş Tarihi</th>
            <td>{{ $data['cikis_tarihi'] ?? '-' }}</td>
            <th>Dönüş Tarihi / Süre</th>
            <td>{{ $data['donus_tarihi'] ?? '-' }} ({{ $data['toplam_gun'] ?? '-' }})</td>
        </tr>
    </table>

    <!-- 2. Seyahat, Ulaşım ve Konaklama Koordinasyonu -->
    <div class="section-header">2. SEYAHAT, ULAŞIM VE KONAKLAMA BİLGİLERİ (İDARİ İŞLER)</div>
    <table class="info-table">
        <tr>
            <th>Ulaşım Şekli / Tercihi</th>
            <td>{{ $data['ulasim_tercihi'] ?? 'Şirket Aracı / Ulaşım' }}</td>
            <th>Tahsis Edilen Araç / Bilet PNR</th>
            <td><strong>{{ $data['tahsis_edilen_arac_veya_pnr'] ?? 'Plaka / PNR Bildirilecek' }}</strong></td>
        </tr>
        <tr>
            <th>Konaklama Durumu</th>
            <td>{{ $data['konaklama_durumu'] ?? '-' }}</td>
            <th>Rezerve Edilen Otel / Tesis</th>
            <td>{{ $data['otel_rezervasyon_bilgisi'] ?? '-' }}</td>
        </tr>
        <tr>
            <th>Ulaşım & Konaklama Notu</th>
            <td colspan="3">{{ $data['idari_isler_notu'] ?? ($data['ulasim_notlari'] ?? 'Normal seyahat prosedürü uygulanacaktır.') }}</td>
        </tr>
    </table>

    <!-- 3. Mali Bilgiler / Yolluk & Avans -->
    <div class="section-header">3. MALİ İŞLER / HARCIRAH VE AVANS DURUMU</div>
    <table class="info-table">
        <tr>
            <th>Harcırah / Avans Talebi</th>
            <td>{{ $data['harcirah_talebi'] ?? 'Talep Edilmedi' }}</td>
            <th>Ödenen / Tahsis Edilen Avans</th>
            <td><strong>{{ $data['odenen_avans_bilgisi'] ?? ($data['talep_edilen_avans_tutari'] ?? '-') }}</strong></td>
        </tr>
        <tr>
            <th>Görev Açıklaması / Kapsamı</th>
            <td colspan="3">{{ $data['gorev_amaci_detay'] ?? '-' }}</td>
        </tr>
    </table>

    <!-- Onay Makamları ve İmzalar -->
    <div class="section-header">4. ONAY VE GÖREVLENDİRME MAKAMLARI</div>
    <table class="signatures-table">
        <tr>
            <td>
                <div class="sig-title">İlk Amir (Şef / Amir)</div>
                <div class="sig-name">{{ $ilkAmirName }}</div>
                <div class="sig-date">{{ $ilkAmirDate }}</div>
                <div class="sig-badge">✓ SİSTEMDEN ONAYLANDI</div>
            </td>
            <td>
                <div class="sig-title">Üst Amir (Müdür / Direktör)</div>
                <div class="sig-name">{{ $ustAmirName }}</div>
                <div class="sig-date">{{ $ustAmirDate }}</div>
                <div class="sig-badge">✓ SİSTEMDEN ONAYLANDI</div>
            </td>
            <td>
                <div class="sig-title">GMY / Üst Yönetim</div>
                <div class="sig-name">{{ $gmyDate ? 'Genel Müdür Yrd.' : 'Gereksiz (Muaf)' }}</div>
                <div class="sig-date">{{ $gmyDate ?? '-' }}</div>
                <div class="sig-badge" style="{{ $gmyDate ? '' : 'background-color:#f1f5f9;color:#64748b;border-color:#cbd5e1;' }}">
                    {{ $gmyDate ? '✓ ONAYLANDI' : 'UYGULANMADI' }}
                </div>
            </td>
            <td>
                <div class="sig-title">İdari İşler & Seyahat</div>
                <div class="sig-name">İdari İşler Sorumlusu</div>
                <div class="sig-date">{{ $issueDate }}</div>
                <div class="sig-badge">✓ KAYIT ALTINA ALINDI</div>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        <strong>YASAL UYARI VE BİLGİLENDİRME:</strong><br>
        Yukarıda açık kimliği ve görev kapsamı belirtilen şirketimiz personeli / heyeti, belirtilen tarihler arasında Köksan Pet ve Plastik Ambalaj Sanayi A.Ş. adına resmi görevli olarak seyahat etmektedir. Bu belge gerektiğinde ilgili resmi makamlara (Emniyet, Jandarma, Havalimanı Güvenlik ve Konaklama Tesisleri) ibraz edilmek üzere Köksan İş Akış ve Süreç Yönetim Platformu tarafından dijital olarak üretilmiş ve onaylanmıştır.
    </div>

</body>
</html>
