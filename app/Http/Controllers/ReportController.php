<?php

namespace App\Http\Controllers;

use App\Models\ReportDashboard;
use App\Models\ReportWidget;
use App\Models\ReportSchedule;
use App\Models\ReportScheduleLog;
use App\Services\ReportQueryEngineService;
use App\Services\ReportExportService;
use App\Mail\ScheduledReportMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReportController extends Controller
{
    public function __construct(
        protected ReportQueryEngineService $queryEngine,
        protected ReportExportService $exportService
    ) {}

    /**
     * Raporlama ve BI Ana Yönetim Kokpiti
     */
    public function index(Request $request): Response
    {
        $dashboards = ReportDashboard::with('creator')
            ->orderBy('is_default', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        $activeDashboardId = $request->input('dashboard_id');
        $activeDashboard = $dashboards->firstWhere('id', $activeDashboardId)
            ?? $dashboards->firstWhere('is_default', true)
            ?? $dashboards->first();

        $dateRange = $request->input('date_range', 'all_time');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $departmentId = $request->input('department_id');
        $workflowId = $request->input('workflow_id');

        $activeWidgets = [];
        $schedules = [];

        $runtimeFilters = [
            'date_range' => $dateRange,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'department_id' => $departmentId,
            'workflow_id' => $workflowId,
        ];

        if ($activeDashboard) {
            $widgets = $activeDashboard->widgets()
                ->where('is_active', true)
                ->get();

            foreach ($widgets as $widget) {
                $chartData = $this->queryEngine->executeWidgetQuery($widget, $runtimeFilters);
                $widget->chart_data = $chartData;
                $activeWidgets[] = $widget;
            }

            $schedules = ReportSchedule::where('dashboard_id', $activeDashboard->id)
                ->with('logs')
                ->latest()
                ->get();
        }

        $allWidgets = ReportWidget::where('is_active', true)->latest()->get();
        $dataSourcesMeta = $this->queryEngine->getAvailableDataSources();

        $departments = \App\Models\Department::select('id', 'name')->orderBy('name')->get();
        $workflows = \App\Models\Workflow::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Reports/Index', [
            'dashboards' => $dashboards,
            'activeDashboard' => $activeDashboard,
            'activeWidgets' => $activeWidgets,
            'allWidgets' => $allWidgets,
            'dataSourcesMeta' => $dataSourcesMeta,
            'schedules' => $schedules,
            'departments' => $departments,
            'workflows' => $workflows,
            'filters' => [
                'date_range' => $dateRange,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'department_id' => $departmentId ? (int)$departmentId : null,
                'workflow_id' => $workflowId ? (int)$workflowId : null,
                'dashboard_id' => $activeDashboard?->id,
            ],
        ]);
    }


    /**
     * Yeni bir Rapor Grafiği / Widget'ı Kaydeder
     */
    public function storeWidget(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'data_source' => 'required|string',
            'chart_type' => 'required|string|in:bar,line,area,donut,pie,kpi_card,table',
            'dimension_field' => 'required|string',
            'metric_field' => 'required|string',
            'aggregation_type' => 'required|string|in:COUNT,SUM,AVG,MIN,MAX',
            'color_palette' => 'nullable|string|in:indigo,emerald,blue,amber,rose',
            'dashboard_id' => 'nullable|integer|exists:report_dashboards,id',
            'width_cols' => 'nullable|integer|in:4,6,12',
        ]);

        $widget = ReportWidget::create([
            'title' => $validated['title'],
            'data_source' => $validated['data_source'],
            'chart_type' => $validated['chart_type'],
            'dimension_field' => $validated['dimension_field'],
            'metric_field' => $validated['metric_field'],
            'aggregation_type' => $validated['aggregation_type'],
            'color_palette' => $validated['color_palette'] ?? 'indigo',
            'created_by' => auth()->id(),
            'is_active' => true,
        ]);

        if (!empty($validated['dashboard_id'])) {
            $dashboard = ReportDashboard::findOrFail($validated['dashboard_id']);
            $maxOrder = $dashboard->widgets()->max('order_index') ?? 0;
            $dashboard->widgets()->attach($widget->id, [
                'order_index' => $maxOrder + 1,
                'width_cols' => $validated['width_cols'] ?? 6,
            ]);
        }

        return back()->with('success', 'Rapor widget grafiği başarıyla oluşturuldu.');
    }

    /**
     * Widget'ı Panodan Kaldırır veya Tamamen Siler
     */
    public function destroyWidget(Request $request, ReportWidget $widget)
    {
        $dashboardId = $request->input('dashboard_id');

        if ($dashboardId) {
            $dashboard = ReportDashboard::findOrFail($dashboardId);
            $dashboard->widgets()->detach($widget->id);
            return back()->with('success', 'Grafik panodan kaldırıldı.');
        }

        $widget->delete();
        return back()->with('success', 'Rapor widget grafiği silindi.');
    }

    /**
     * Widget'ı Ana Sayfaya Sabitle / Sabitlemeyi Kaldır (Pin to Home Dashboard)
     */
    public function toggleWidgetPin(ReportWidget $widget)
    {
        $widget->is_pinned_to_home = !$widget->is_pinned_to_home;
        $widget->save();

        $msg = $widget->is_pinned_to_home
            ? "'{$widget->title}' grafiği Ana Sayfa kokpitine sabitlendi."
            : "'{$widget->title}' grafiğinin Ana Sayfa sabitlemesi kaldırıldı.";

        return back()->with('success', $msg);
    }

    /**
     * Yeni bir Rapor Panosu Oluşturur
     */
    public function storeDashboard(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_default' => 'nullable|boolean',
        ]);

        if (!empty($validated['is_default'])) {
            ReportDashboard::where('is_default', true)->update(['is_default' => false]);
        }

        $dashboard = ReportDashboard::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
            'is_public' => true,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('reports.index', ['dashboard_id' => $dashboard->id])
            ->with('success', 'Yeni rapor panosu başarıyla oluşturuldu.');
    }

    /**
     * Rapor Panosunu Günceller (Edit / Update)
     */
    public function updateDashboard(Request $request, int $dashboardId)
    {
        $dashboard = ReportDashboard::findOrFail($dashboardId);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_default' => 'nullable|boolean',
        ]);

        if (!empty($validated['is_default'])) {
            ReportDashboard::where('id', '!=', $dashboard->id)->update(['is_default' => false]);
        }

        $dashboard->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
        ]);

        return redirect()->route('reports.index', ['dashboard_id' => $dashboard->id])
            ->with('success', 'Rapor panosu başarıyla güncellendi.');
    }

    /**
     * Rapor Panosunu Siler (Delete)
     */
    public function destroyDashboard(int $dashboardId)
    {
        $dashboard = ReportDashboard::findOrFail($dashboardId);

        $totalDashboards = ReportDashboard::count();
        if ($totalDashboards <= 1) {
            return back()->with('error', 'Sistemdeki son kalan rapor panosu silinemez.');
        }

        $wasDefault = $dashboard->is_default;

        // Panoya ait widget ve planları sil
        $dashboard->widgets()->delete();
        $dashboard->schedules()->delete();
        $dashboard->delete();

        // Kalan panolar arasından varsayılanı veya ilkini seç
        $nextDashboard = ReportDashboard::where('is_default', true)->first()
            ?? ReportDashboard::first();

        if ($wasDefault && $nextDashboard) {
            $nextDashboard->update(['is_default' => true]);
        }

        return redirect()->route('reports.index', ['dashboard_id' => $nextDashboard?->id])
            ->with('success', 'Rapor panosu ve bağlı tüm grafikleri başarıyla silindi.');
    }

    /**
     * Modal içinde canlı grafik önizleme API'si
     */
    public function queryPreview(Request $request)
    {
        $data = $this->queryEngine->executeWidgetQuery($request->all(), [
            'date_range' => $request->input('date_range', 'all_time'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ]);

        return response()->json($data);
    }

    /**
     * Panoyu Canlı PDF Olarak İndirir
     */
    public function exportPdf(Request $request, int $dashboardId)
    {
        $dashboard = ReportDashboard::findOrFail($dashboardId);
        $dateRange = $request->input('date_range', 'all_time');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $departmentId = $request->input('department_id');
        $workflowId = $request->input('workflow_id');

        $runtimeFilters = [
            'date_range' => $dateRange,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'department_id' => $departmentId,
            'workflow_id' => $workflowId,
        ];

        $widgets = $dashboard->widgets()->where('is_active', true)->get();
        foreach ($widgets as $widget) {
            $widget->chart_data = $this->queryEngine->executeWidgetQuery($widget, $runtimeFilters);
        }

        if (!empty($startDate) && !empty($endDate)) {
            $dateRangeLabel = \Carbon\Carbon::parse($startDate)->format('d.m.Y') . ' - ' . \Carbon\Carbon::parse($endDate)->format('d.m.Y');
        } else {
            $dateLabels = [
                'today' => 'Bugün',
                'this_week' => 'Bu Hafta',
                'this_month' => 'Bu Ay',
                'this_year' => 'Bu Yıl',
                'last_30_days' => 'Son 30 Gün',
                'all_time' => 'Tüm Zamanlar',
            ];
            $dateRangeLabel = $dateLabels[$dateRange] ?? 'Tüm Zamanlar';
        }

        if ($departmentId && $dept = \App\Models\Department::find($departmentId)) {
            $dateRangeLabel .= " | Departman: {$dept->name}";
        }
        if ($workflowId && $wf = \App\Models\Workflow::find($workflowId)) {
            $dateRangeLabel .= " | Süreç: {$wf->name}";
        }

        $pdfBinary = $this->exportService->generateDashboardPdf($dashboard, $widgets->all(), $dateRangeLabel);

        $safeTitle = \Illuminate\Support\Str::slug($dashboard->title, '_');
        if (empty($safeTitle)) {
            $safeTitle = 'koksan_rapor';
        }
        $filename = "Koksan_{$safeTitle}_Raporu.pdf";

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Panoyu Çok Sekmeli Kurumsal Excel (.xlsx) Olarak İndirir
     */
    public function exportExcel(Request $request, int $dashboardId)
    {
        $dashboard = ReportDashboard::findOrFail($dashboardId);
        $dateRange = $request->input('date_range', 'all_time');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $departmentId = $request->input('department_id');
        $workflowId = $request->input('workflow_id');

        $runtimeFilters = [
            'date_range' => $dateRange,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'department_id' => $departmentId,
            'workflow_id' => $workflowId,
        ];

        $widgets = $dashboard->widgets()->where('is_active', true)->get();
        foreach ($widgets as $widget) {
            $widget->chart_data = $this->queryEngine->executeWidgetQuery($widget, $runtimeFilters);
        }

        if (!empty($startDate) && !empty($endDate)) {
            $dateRangeLabel = \Carbon\Carbon::parse($startDate)->format('d.m.Y') . ' - ' . \Carbon\Carbon::parse($endDate)->format('d.m.Y');
        } else {
            $dateLabels = [
                'today' => 'Bugün',
                'this_week' => 'Bu Hafta',
                'this_month' => 'Bu Ay',
                'this_year' => 'Bu Yıl',
                'last_30_days' => 'Son 30 Gün',
                'all_time' => 'Tüm Zamanlar',
            ];
            $dateRangeLabel = $dateLabels[$dateRange] ?? 'Tüm Zamanlar';
        }

        if ($departmentId && $dept = \App\Models\Department::find($departmentId)) {
            $dateRangeLabel .= " | Departman: {$dept->name}";
        }
        if ($workflowId && $wf = \App\Models\Workflow::find($workflowId)) {
            $dateRangeLabel .= " | Süreç: {$wf->name}";
        }

        $excelBinary = $this->exportService->generateDashboardExcel($dashboard, $widgets->all(), $dateRangeLabel);


        $safeTitle = \Illuminate\Support\Str::slug($dashboard->title, '_');
        if (empty($safeTitle)) {
            $safeTitle = 'koksan_rapor';
        }
        $filename = "Koksan_{$safeTitle}_Raporu.xlsx";

        return response($excelBinary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }


    /**
     * Yeni E-Posta Aboneliği & Zamanlanmış Rapor Kaydeder (Anlık veya Periyodik)
     */
    public function storeSchedule(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'dashboard_id' => 'required|integer|exists:report_dashboards,id',
            'frequency_type' => 'required|string|in:daily,weekly,monthly,cron,instant',
            'dispatch_time' => 'nullable|string',
            'day_of_week' => 'nullable|integer|min:1|max:7',
            'recipients' => 'required|string', // Virgülle ayrılmış e-postalar
            'include_pdf' => 'nullable|boolean',
            'include_excel' => 'nullable|boolean',
        ]);

        $recipientList = array_filter(array_map('trim', explode(',', $validated['recipients'])));

        $schedule = ReportSchedule::create([
            'title' => $validated['title'],
            'dashboard_id' => $validated['dashboard_id'],
            'frequency_type' => $validated['frequency_type'],
            'dispatch_time' => $validated['dispatch_time'] ?? now()->format('H:i'),
            'day_of_week' => $validated['day_of_week'] ?? 1,
            'recipients_json' => array_values($recipientList),
            'include_pdf' => $validated['include_pdf'] ?? true,
            'include_excel' => $validated['include_excel'] ?? true,
            'include_html_body' => true,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        // EĞER "ANLIK" SEÇİLDİYSE HEMEN ŞİMDİ GÖNDER!
        if ($validated['frequency_type'] === 'instant') {
            return $this->sendScheduleNow($schedule);
        }

        return back()->with('success', 'Otomatik e-posta raporlama aboneliği planlandı.');
    }

    /**
     * Zamanlanmış Raporu Anında Test Olarak Gönderir (Instant Dispatch)
     */
    public function sendScheduleNow(ReportSchedule $schedule)
    {
        $dashboard = $schedule->dashboard;
        if (!$dashboard) {
            return back()->with('error', 'Rapor panosu bulunamadı.');
        }

        $startTime = microtime(true);
        $widgets = $dashboard->widgets()->where('is_active', true)->get();
        foreach ($widgets as $widget) {
            $widget->chart_data = $this->queryEngine->executeWidgetQuery($widget, ['date_range' => 'last_30_days']);
        }

        $pdfBinary = $schedule->include_pdf
            ? $this->exportService->generateDashboardPdf($dashboard, $widgets->all(), 'Son 30 Gün (Test)')
            : null;

        $excelBinary = $schedule->include_excel
            ? $this->exportService->generateDashboardExcel($dashboard, $widgets->all(), 'Son 30 Gün (Test)')
            : null;

        $recipients = $schedule->recipients_json;

        try {
            Mail::to($recipients)->send(
                new ScheduledReportMail($schedule, $dashboard, $widgets->all(), $pdfBinary, $excelBinary)
            );

            $execTimeMs = (int) round((microtime(true) - $startTime) * 1000);

            ReportScheduleLog::create([
                'schedule_id' => $schedule->id,
                'sent_at' => now(),
                'status' => 'success',
                'recipients_count' => count($recipients),
                'execution_time_ms' => $execTimeMs,
            ]);

            $schedule->update(['last_sent_at' => now()]);

            $killSwitchActive = (bool) \App\Models\MailSetting::get('global_kill_switch', false);
            if ($killSwitchActive) {
                return back()->with('warning', 'Rapor PDF ve Excel ekleriyle hazırlandı ve denetim günlüğü kaydedildi. Ancak sistemde Global Kill Switch (Güvenlik Kalkanı) aktif olduğu için fiziksel e-posta kutularına çıkış engellendi. Gerçek e-posta teslimatı için "Sistem Yönetimi > E-Posta Yönetimi" ekranından Kill Switch\'i kapatabilirsiniz.');
            }

            return back()->with('success', 'Rapor e-postası alıcılara (' . implode(', ', $recipients) . ') başarıyla gönderildi!');
        } catch (\Exception $e) {
            ReportScheduleLog::create([
                'schedule_id' => $schedule->id,
                'sent_at' => now(),
                'status' => 'failed',
                'recipients_count' => count($recipients),
                'error_message' => $e->getMessage(),
            ]);

            return back()->with('error', 'E-posta gönderiminde hata: ' . $e->getMessage());
        }
    }
}
