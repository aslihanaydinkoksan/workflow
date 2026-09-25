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
    </style>
</head>
<body>

    <!-- Üst Başlık -->
    <table class="header-table">
        <tr>
            <td style="width: 65%;">
                <div class="company-title">KÖKSAN PET VE PLASTİK AMBALAJ SAN. A.Ş.</div>
                <div class="company-subtitle">İnsan Kaynakları & İdari İşler Direktörlüğü</div>
            </td>
            <td style="width: 35%; text-align: right;">
                <div style="font-size: 11px; font-weight: bold; color: #003366;">FORM NO: FR-IDR-008</div>
                <div style="font-size: 9px; color: #64748b;">Belge No: {{ $orderNo }}</div>
                <div style="font-size: 9px; color: #64748b;">Tarih: {{ $issueDate }}</div>
            </td>
        </tr>
    </table>

    <div class="doc-title">
        DIŞ GÖREVLENDİRME VE SEYAHAT İZİN BELGESİ
    </div>

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
