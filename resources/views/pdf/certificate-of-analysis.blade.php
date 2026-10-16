<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>{{ $documentTitle }} - {{ $certificateNo }}</title>
    <style>
        @page {
            margin: 12mm 12mm 14mm 12mm;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #0f172a;
            line-height: 1.35;
        }

        /* 1. KÖKSAN RESMİ 3'LÜ ANTET ÇERÇEVESİ (FR-MKK-11 STANDARDI) */
        .header-frame {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #1e293b;
            margin-bottom: 8px;
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

        /* 2. REFERANS VE NUMUNE BİLGİLERİ IZGARASI */
        .meta-frame {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #94a3b8;
            margin-bottom: 10px;
            font-size: 9.5px;
        }
        .meta-frame td {
            padding: 4px 6px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
        }
        .meta-lbl {
            background-color: #f8fafc;
            font-weight: bold;
            color: #334155;
            width: 22%;
            font-size: 9px;
            text-transform: uppercase;
        }
        .meta-val {
            color: #0f172a;
            width: 28%;
        }

        /* 3. BÖLÜM BAŞLIKLARI */
        .section-bar {
            background-color: #003366;
            color: #ffffff;
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 4px 8px;
            margin-top: 8px;
            margin-bottom: 4px;
            letter-spacing: 0.4px;
            border-radius: 2px;
        }

        /* 4. ANALİZ BULGULARI TABLOSU */
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .results-table th, .results-table td {
            border: 1px solid #94a3b8;
            padding: 4px 6px;
            font-size: 9px;
            text-align: left;
        }
        .results-table th {
            background-color: #f1f5f9;
            color: #0f172a;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8.5px;
        }
        .results-table tr:nth-child(even) {
            background-color: #fbfcfd;
        }
        .status-pass {
            color: #047857;
            font-weight: bold;
        }
        .status-fail {
            color: #b91c1c;
            font-weight: bold;
        }

        /* 5. MİKROBİYOLOJİ KRİTER AÇIKLAMA KUTUSU (FR-MKK-11) */
        .criteria-box {
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            padding: 5px 8px;
            margin-bottom: 8px;
            font-size: 8px;
            color: #475569;
            line-height: 1.35;
        }
        .criteria-title {
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        /* 6. KALİTE UYGUNLUK KARARI */
        .decision-frame {
            border: 1.5px solid #10b981;
            background-color: #f0fdf4;
            padding: 6px 10px;
            margin-bottom: 12px;
            border-radius: 3px;
        }
        .decision-header {
            font-size: 9.5px;
            font-weight: bold;
            color: #065f46;
            margin-bottom: 2px;
        }
        .decision-text {
            font-size: 9px;
            color: #166534;
            line-height: 1.35;
        }

        /* 7. RESMİ İMZA VE ONAY ÇERÇEVESİ (FR-MKK-11 STANDARDI) */
        .signatures-frame {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #94a3b8;
            margin-top: 6px;
            margin-bottom: 10px;
        }
        .signatures-frame td {
            width: 50%;
            border: 1px solid #94a3b8;
            padding: 6px 10px;
            text-align: center;
            vertical-align: top;
            background-color: #ffffff;
        }
        .sign-header {
            font-size: 9px;
            font-weight: bold;
            color: #003366;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 3px;
            margin-bottom: 20px;
            letter-spacing: 0.5px;
        }
        .sign-name {
            font-size: 10.5px;
            font-weight: bold;
            color: #0f172a;
        }
        .sign-title {
            font-size: 8.5px;
            color: #475569;
            margin-top: 1px;
        }
        .sign-meta {
            font-size: 7.5px;
            color: #047857;
            margin-top: 3px;
            font-weight: bold;
        }

        /* 8. FOOTER */
        .footer-note {
            border-top: 1px solid #cbd5e1;
            padding-top: 5px;
            font-size: 7.5px;
            color: #64748b;
            text-align: center;
            line-height: 1.3;
        }
    </style>
</head>
<body>

    <!-- 1. KÖKSAN RESMİ 3'LÜ KALİTE ANTETİ (FR-MKK-11 / FR-LVH-042) -->
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
                <div class="header-doc-title">{{ $documentTitle }}</div>
                @if(!empty($documentSubtitle))
                    <div class="header-doc-subtitle">{{ $documentSubtitle }}</div>
                @endif
            </td>

            <!-- SAĞ: DOKÜMAN KONTROL VE KALİTE KÜNYESİ -->
            <td class="header-control-cell">
                <table class="control-table">
                    <tr>
                        <td class="control-lbl">Doküman No</td>
                        <td class="control-val">{{ $documentNo }}</td>
                    </tr>
                    <tr>
                        <td class="control-lbl">Yayın Tarihi</td>
                        <td class="control-val">{{ $publishDate }}</td>
                    </tr>
                    <tr>
                        <td class="control-lbl">Revizyon No / Tarih</td>
                        <td class="control-val">{{ $revisionInfo }}</td>
                    </tr>
                    <tr>
                        <td class="control-lbl">Sayfa No</td>
                        <td class="control-val">{{ $pageNo }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- 2. REFERANS VE NUMUNE BİLGİLERİ TABLOSU -->
    <table class="meta-frame">
        <tr>
            <td class="meta-lbl">REFERANS NO:</td>
            <td class="meta-val"><strong>{{ $certificateNo }}</strong></td>
            <td class="meta-lbl">DÜZENLEME TARİHİ:</td>
            <td class="meta-val"><strong>{{ $productionDate }}</strong></td>
        </tr>
        <tr>
            <td class="meta-lbl">NUMUNE ALINIŞ SEBEBİ:</td>
            <td class="meta-val" colspan="3"><strong>{{ $sampleReason }}</strong></td>
        </tr>
        <tr>
            <td class="meta-lbl">MÜŞTERİ / FİRMA:</td>
            <td class="meta-val"><strong>{{ $customerName }}</strong></td>
            <td class="meta-lbl">SÜREÇ / TALEP NO:</td>
            <td class="meta-val">#PI-{{ $instance->id }}</td>
        </tr>
        <tr>
            <td class="meta-lbl">ÜRÜN / MALZEME:</td>
            <td class="meta-val"><strong>{{ $productName }}</strong></td>
            <td class="meta-lbl">PARTİ / LOT NO:</td>
            <td class="meta-val"><strong>{{ $lotNo }}</strong></td>
        </tr>
        <tr>
            <td class="meta-lbl">NUMUNE MİKTARI:</td>
            <td class="meta-val">{{ $quantity }}</td>
            <td class="meta-lbl">SATIŞ / BAŞLATAN:</td>
            <td class="meta-val">{{ $salesPerson }}</td>
        </tr>
    </table>

    <!-- 3. ANALİZ BULGULARI VE SONUÇLARI -->
    <div class="section-bar">Laboratuvar Analiz ve Kalite Test Bulguları</div>
    <table class="results-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">No</th>
                <th style="width: 27%;">Analiz Parametresi / Test Cinsi</th>
                <th style="width: 17%;">Test Metodu / Standart</th>
                <th style="width: 9%; text-align: center;">Birim</th>
                <th style="width: 19%;">Şartname / Spesifikasyon Limiti</th>
                <th style="width: 13%;">Ölçülen Değer</th>
                <th style="width: 10%; text-align: center;">Durum</th>
            </tr>
        </thead>
        <tbody>
            @forelse($parameters as $index => $param)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td><strong>{{ $param['name'] }}</strong></td>
                    <td>{{ $param['method'] ?? 'TS / ISO / ASTM' }}</td>
                    <td style="text-align: center;">{{ $param['unit'] ?? '-' }}</td>
                    <td>{{ $param['specification'] ?? '-' }}</td>
                    <td><strong>{{ $param['result'] ?? '-' }}</strong></td>
                    <td style="text-align: center;">
                        <span class="{{ ($param['status'] ?? 'pass') === 'pass' ? 'status-pass' : 'status-fail' }}">
                            {{ ($param['status'] ?? 'pass') === 'pass' ? 'UYGUN' : 'UYGUN DEĞİL' }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; color: #64748b; padding: 12px;">
                        Kayıtlı analiz sonucu bulunamadı.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- MİKROBİYOLOJİ ÇOKLU NOKTA TABLOSU (VARSA) -->
    @if(!empty($microbiologyTable) && count($microbiologyTable) > 0)
        <div class="section-bar" style="margin-top: 6px;">Mikrobiyolojik Numune Alım Noktaları ve Sayım Değerleri</div>
        <table class="results-table">
            <thead>
                <tr>
                    <th style="width: 14%;">Analiz Tarihi</th>
                    <th style="width: 18%;">Numune Cinsi</th>
                    <th style="width: 20%;">Numune Alım Noktası</th>
                    <th style="width: 10%; text-align: center;">Koliform</th>
                    <th style="width: 10%; text-align: center;">E-coli</th>
                    <th style="width: 10%; text-align: center;">AQHC</th>
                    <th style="width: 9%; text-align: center;">Küf</th>
                    <th style="width: 9%; text-align: center;">Maya</th>
                </tr>
            </thead>
            <tbody>
                @foreach($microbiologyTable as $row)
                    <tr>
                        <td>{{ $row['analiz_tarihi'] ?? ($row['tarih'] ?? $productionDate) }}</td>
                        <td>{{ $row['numune_cinsi'] ?? ($productName ?? '-') }}</td>
                        <td>{{ $row['alim_noktasi'] ?? ($row['numune_alim_noktasi'] ?? 'Üretim Hattı') }}</td>
                        <td style="text-align: center;">{{ $row['koliform'] ?? '0' }}</td>
                        <td style="text-align: center;">{{ $row['e_coli'] ?? '0' }}</td>
                        <td style="text-align: center;">{{ $row['aqhc'] ?? '0' }}</td>
                        <td style="text-align: center;">{{ $row['kuf'] ?? '0' }}</td>
                        <td style="text-align: center;">{{ $row['maya'] ?? '0' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- MİKROBİYOLOJİ KRİTERLERİ BİLGİLENDİRME (FR-MKK-11 STANDARDINDA VARSA) -->
    @if($isMicrobiology)
        <div class="criteria-box">
            <div class="criteria-title">Analiz Limitleri, Metodoloji ve Kabul Kriterleri (FR-MKK-11 Standardı):</div>
            <div>&bull; <strong>n:</strong> Analiz edilecek örnek sayısı &bull; <strong>c:</strong> Marjinal sonuçlar için maksimum izin verilen numune sayısı (m ile M arası).</div>
            <div>&bull; <strong>m:</strong> İyi üretim uygulamaları kapsamında kabul edilebilir sınır. &bull; <strong>M:</strong> Kabul edilemez marjinal üst limit.</div>
            <div>&bull; Sonuç &le; m ise parti uygundur. &gt; m ve &le; M ise ikinci örnekleme yapılır. &gt; M ise malzeme kesin olarak reddedilir.</div>
        </div>
    @endif

    <!-- 4. KALİTE KONTROL VE UYGUNLUK KARARI -->
    <div class="decision-frame">
        <div class="decision-header">✅ KALİTE GÜVENCE VE UYGUNLUK KARARI:</div>
        <div class="decision-text">
            Yukarıda parti numarası ve teknik spesifikasyonları belirtilen numunenin laboratuvar analiz ve test sonuçları, 
            KÖKSAN kalite kontrol standartlarına ve müşteri şartnamesine <strong>TAM UYGUN BULUNMUŞTUR</strong>. 
            İlgili partinin sevkiyatında ve müşteri deneme prosesinde kullanılmasında teknik ve mikrobiyolojik açıdan bir sakınca yoktur.
        </div>
    </div>

    <!-- 5. RESMİ İMZA VE ONAY ÇERÇEVESİ (FR-MKK-11 ÇİFT KUTU) -->
    <table class="signatures-frame">
        <tr>
            <td>
                <div class="sign-header">ANALİZİ YAPAN</div>
                <div class="sign-name">{{ $analystName }}</div>
                <div class="sign-title">{{ $analystTitle }}</div>
                <div class="sign-meta">✔ Sistem Üzerinden Elektronik Onaylı &bull; {{ $productionDate }}</div>
            </td>
            <td>
                <div class="sign-header">ONAYLAYAN</div>
                <div class="sign-name">{{ $approverName }}</div>
                <div class="sign-title">{{ $approverTitle }}</div>
                <div class="sign-meta">✔ Sistem Üzerinden Elektronik Onaylı &bull; {{ $productionDate }}</div>
            </td>
        </tr>
    </table>

    <!-- 6. KURUMSAL FOOTER BİLGİSİ -->
    <div class="footer-note">
        KÖKSAN PET VE PLASTİK AMBALAJ SANAYİ VE TİCARET A.Ş. &bull; 4. Organize Sanayi Bölgesi Gaziantep / TÜRKİYE<br>
        Form Doküman Kodu: <strong>{{ $documentNo }}</strong> &bull; Kalite Yönetim Sistemi Resmi Form Çıktısı &bull; www.koksan.com
    </div>

</body>
</html>
