<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProcessFieldAudit;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    /**
     * Kurumsal Denetim İzi & Güvenlik Kokpiti (Audit Log Dashboard)
     */
    public function index(Request $request)
    {
        $query = ProcessFieldAudit::with([
            'user:id,name,email',
            'processInstance:id,status,workflow_id,created_at',
            'processInstance.workflow:id,name',
            'task:id,node_id,type,status,process_instance_id',
        ])->latest('id');

        // Filtre: Olay Tipi
        if ($request->filled('event_type') && $request->event_type !== 'all') {
            $query->where('event_type', $request->event_type);
        }

        // Filtre: Kullanıcı
        if ($request->filled('user_id') && $request->user_id !== 'all') {
            $query->where('user_id', $request->user_id);
        }

        // Filtre: Süreç ID
        if ($request->filled('process_instance_id')) {
            $query->where('process_instance_id', $request->process_instance_id);
        }

        // Filtre: Metin Arama
        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('field_label', 'like', "%{$s}%")
                    ->orWhere('field_key', 'like', "%{$s}%")
                    ->orWhere('comment', 'like', "%{$s}%")
                    ->orWhere('old_value', 'like', "%{$s}%")
                    ->orWhere('new_value', 'like', "%{$s}%")
                    ->orWhereHas('processInstance.workflow', function ($w) use ($s) {
                        $w->where('name', 'like', "%{$s}%");
                    })
                    ->orWhereHas('user', function ($u) use ($s) {
                        $u->where('name', 'like', "%{$s}%");
                    });
            });
        }

        // Filtre: Tarih Aralığı
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Sayfalama
        $audits = $query->paginate(20)->withQueryString();

        // Üst İstatistikler (Global veya filtrelenmiş)
        $stats = [
            'total' => ProcessFieldAudit::count(),
            'sod' => ProcessFieldAudit::where('event_type', 'sod_escalation')->count(),
            'doa' => ProcessFieldAudit::where('event_type', 'doa_bypass')->count(),
            'admin_ops' => ProcessFieldAudit::whereIn('event_type', ['admin_jump', 'admin_reassign', 'workflow_migration'])->count(),
            'timers' => ProcessFieldAudit::whereIn('event_type', ['boundary_timer_escalate', 'boundary_timer_auto_approve'])->count(),
            'revisions' => ProcessFieldAudit::where('event_type', 'revision')->count(),
        ];

        // Olay tipleri sözlüğü
        $eventDefinitions = [
            'sod_escalation' => [
                'label' => '4-Göz İlkesi (SoD Eskalasyon)',
                'badge' => 'bg-rose-100 text-rose-800 border-rose-300',
                'icon' => '🛡️',
                'desc' => 'Süreci başlatanın kendi onayına gelmesi önlendi ve üst amirine devredildi.',
            ],
            'doa_bypass' => [
                'label' => 'Finansal Yetki Aşımı (DoA Eskalasyon)',
                'badge' => 'bg-amber-100 text-amber-800 border-amber-300',
                'icon' => '💰',
                'desc' => 'Talep tutarı amirin onay limitini aştığı için bir üst makama yönlendirildi.',
            ],
            'admin_jump' => [
                'label' => 'Yönetici Adım Atlama (Admin Jump)',
                'badge' => 'bg-purple-100 text-purple-800 border-purple-300',
                'icon' => '⚡',
                'desc' => 'Yönetici kokpitinden süreç zorla başka bir adıma fırlatıldı.',
            ],
            'admin_reassign' => [
                'label' => 'Yönetici Görev Devri (Admin Reassign)',
                'badge' => 'bg-indigo-100 text-indigo-800 border-indigo-300',
                'icon' => '🔄',
                'desc' => 'Yönetici kokpitinden bekleyen görev zorla başka personele devredildi.',
            ],
            'boundary_timer_escalate' => [
                'label' => 'SLA Süre Aşımı Eskalasyonu',
                'badge' => 'bg-orange-100 text-orange-800 border-orange-300',
                'icon' => '⏰',
                'desc' => 'Görev SLA süresi dolduğu için amirine otomatik eskalasyon görevi açıldı.',
            ],
            'boundary_timer_auto_approve' => [
                'label' => 'Otomatik Onaylama (SLA Timer)',
                'badge' => 'bg-teal-100 text-teal-800 border-teal-300',
                'icon' => '⏱️',
                'desc' => 'Süre aşımında süreç otomatik onaylanarak sonraki adıma geçirildi.',
            ],
            'revision' => [
                'label' => 'Revizyon & Düzeltme İsteği',
                'badge' => 'bg-blue-100 text-blue-800 border-blue-300',
                'icon' => '📝',
                'desc' => 'Onay makamı gerekçe belirterek talebi sahibine düzeltmeye gönderdi.',
            ],
            'form_submit' => [
                'label' => 'Form Veri Güncelleme / Kayıt',
                'badge' => 'bg-slate-100 text-slate-800 border-slate-300',
                'icon' => '📋',
                'desc' => 'Kullanıcı görev esnasında form alanını güncelledi.',
            ],
            'parallel_fork' => [
                'label' => 'Paralel Çatallanma (Fork)',
                'badge' => 'bg-sky-100 text-sky-800 border-sky-300',
                'icon' => '🔀',
                'desc' => 'Süreç eş zamanlı çoklu kollara ayrıldı.',
            ],
            'parallel_join_sync' => [
                'label' => 'Paralel Birleşme (Join Sync)',
                'badge' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                'icon' => '✅',
                'desc' => 'Tüm paralel kollar tamamlandı ve süreç birleşerek devam etti.',
            ],
            'workflow_migration' => [
                'label' => 'Süreç Versiyon Göçü (Migration)',
                'badge' => 'bg-violet-100 text-violet-800 border-violet-300',
                'icon' => '🚀',
                'desc' => 'Çalışan süreç yeni akış versiyonu şemasına migrate edildi.',
            ],
        ];

        // Denetim kaydı oluşturan kullanıcılar
        $auditUserIds = ProcessFieldAudit::distinct()->whereNotNull('user_id')->pluck('user_id');
        $users = User::whereIn('id', $auditUserIds)->select('id', 'name', 'email')->orderBy('name')->get();

        return Inertia::render('Admin/AuditLogs/Index', [
            'audits' => $audits,
            'stats' => $stats,
            'eventDefinitions' => $eventDefinitions,
            'users' => $users,
            'filters' => [
                'event_type' => $request->event_type ?? 'all',
                'user_id' => $request->user_id ?? 'all',
                'process_instance_id' => $request->process_instance_id ?? '',
                'search' => $request->search ?? '',
                'date_from' => $request->date_from ?? '',
                'date_to' => $request->date_to ?? '',
            ],
        ]);
    }
}
