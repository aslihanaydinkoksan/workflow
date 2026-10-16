<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Köksan Sabah Yönetici Özeti</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            line-height: 1.5;
        }
        .container {
            max-width: 680px;
            margin: 24px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
            border: 1px solid #e2e8f0;
        }
        .header {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            padding: 32px 28px;
            color: #ffffff;
            text-align: left;
            border-bottom: 4px solid #f59e0b;
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .company-name {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #94a3b8;
        }
        .digest-badge {
            display: inline-block;
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.4);
            font-size: 11px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 9999px;
            text-transform: uppercase;
        }
        .title {
            font-size: 24px;
            font-weight: 800;
            margin: 0 0 6px 0;
            color: #ffffff;
            letter-spacing: -0.5px;
        }
        .subtitle {
            font-size: 14px;
            color: #cbd5e1;
            margin: 0;
        }
        .body-content {
            padding: 28px;
        }
        .greeting {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 8px;
        }
        .intro-text {
            font-size: 14px;
            color: #475569;
            margin-bottom: 24px;
            line-height: 1.6;
        }
        .kpi-row {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }
        .kpi-card {
            flex: 1;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
        }
        .kpi-value {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
        }
        .kpi-label {
            font-size: 11px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }
        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .items-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 24px;
        }
        .item-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            background: #ffffff;
            transition: all 0.2s;
        }
        .item-card.critical {
            border-left: 4px solid #ef4444;
            background: #fffcfc;
        }
        .item-card.warning {
            border-left: 4px solid #f59e0b;
            background: #fffdfa;
        }
        .item-card.info {
            border-left: 4px solid #3b82f6;
            background: #fbfdff;
        }
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .badge {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-slate { background: #f1f5f9; color: #475569; }

        .item-time {
            font-size: 11px;
            color: #94a3b8;
        }
        .item-subject {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
        }
        .item-body {
            font-size: 13px;
            color: #475569;
            margin-bottom: 10px;
            white-space: pre-line;
        }
        .item-action-link {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            color: #2563eb;
            text-decoration: none;
        }
        .item-action-link:hover {
            text-decoration: underline;
        }
        .cta-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            margin-top: 24px;
        }
        .cta-button {
            display: inline-block;
            background: #0f172a;
            color: #ffffff !important;
            padding: 12px 28px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 6px;
            text-decoration: none;
            margin-top: 8px;
        }
        .footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 20px 28px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
        }
        .footer-note {
            margin-bottom: 6px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <div class="header-top">
                <span class="company-name">Köksan Pet ve Plastik Ambalaj San. A.Ş.</span>
                <span class="digest-badge">☀️ Sabah 08:30 Özeti</span>
            </div>
            <h1 class="title">Günün Bekleyen İş & Onay Bülteni</h1>
            <p class="subtitle">Dün gece mesai saatleri dışında kaydedilen süreç bildirimleri</p>
        </div>

        <!-- BODY -->
        <div class="body-content">
            <div class="greeting">
                Sayın {{ $recipientName }},
            </div>
            <p class="intro-text">
                Mesai saatleri dışında gelen bildirimler yöneticilerimizin dinlenme saatlerini korumak amacıyla 
                <strong>Köksan Mail Guard</strong> tarafından sessize alınmış ve sabah mesai başlangıcınız için bu bültende derlenmiştir.
            </p>

            <!-- KPI ÖZETİ -->
            <div class="kpi-row">
                <div class="kpi-card">
                    <div class="kpi-value">{{ count($items) }}</div>
                    <div class="kpi-label">Toplam Bildirim</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-value" style="color: #dc2626;">{{ $criticalCount }}</div>
                    <div class="kpi-label">Kritik / SLA İhlali</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-value" style="color: #d97706;">{{ $warningCount }}</div>
                    <div class="kpi-label">Termin & Zaman Aşımı</div>
                </div>
            </div>

            <!-- BİLDİRİMLER LİSTESİ -->
            <div class="section-title">
                📋 Gece Biriken İş ve Karar Maddeleri
            </div>

            <div class="items-list">
                @foreach($items as $item)
                    @php
                        $code = $item->event_code;
                        $cardClass = 'info';
                        $badgeClass = 'badge-blue';
                        $badgeLabel = 'BİLDİRİM';

                        if (in_array($code, ['termin_overdue_escalation', 'boundary_timer_escalate'])) {
                            $cardClass = 'critical';
                            $badgeClass = 'badge-red';
                            $badgeLabel = ($code === 'boundary_timer_escalate') ? '⏱️ SÜRE AŞIMI ESKALASYONU' : '🚨 SLA İHLALİ';
                        } elseif ($code === 'termin_warning') {
                            $cardClass = 'warning';
                            $badgeClass = 'badge-amber';
                            $badgeLabel = '⏳ TERMİN YAKLAŞIYOR';
                        } elseif ($code === 'task_assigned') {
                            $cardClass = 'info';
                            $badgeClass = 'badge-blue';
                            $badgeLabel = '📋 YENİ GÖREV';
                        }

                        $actionUrl = $item->metadata['action_url'] ?? url('/tasks');
                    @endphp
                    <div class="item-card {{ $cardClass }}">
                        <div class="item-header">
                            <span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
                            <span class="item-time">⏰ {{ $item->queued_at ? $item->queued_at->format('d.m.Y H:i') : '' }}</span>
                        </div>
                        <div class="item-subject">{{ $item->subject }}</div>
                        <div class="item-body">{{ Str::limit($item->body, 220) }}</div>
                        <div>
                            <a href="{{ $actionUrl }}" class="item-action-link">Detayı ve Görevi Aç &rarr;</a>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- CTA -->
            <div class="cta-box">
                <div style="font-size: 14px; font-weight: 600; color: #0f172a; margin-bottom: 4px;">Tüm Bekleyen İşlerinizi İnceleyin</div>
                <div style="font-size: 12px; color: #64748b; margin-bottom: 12px;">Gelen kutunuzda işlem bekleyen tüm onay ve form görevlerine doğrudan erişebilirsiniz.</div>
                <a href="{{ url('/tasks') }}" class="cta-button">📥 Görev Kutuma Git</a>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="footer">
            <div class="footer-note">
                Bu e-posta, <strong>Köksan İş Akışı & BPM Platformu</strong> E-Posta Kural Motoru (Mail Guard) tarafından otomatik oluşturulmuştur.
            </div>
            <div>
                © {{ date('Y') }} Köksan Pet ve Plastik Ambalaj Sanayi A.Ş. | Bilgi Teknolojileri ve Süreç Yönetimi
            </div>
        </div>
    </div>
</body>
</html>
