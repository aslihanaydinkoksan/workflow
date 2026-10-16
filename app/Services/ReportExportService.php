<?php

namespace App\Services;

use App\Models\ReportDashboard;
use App\Exports\ReportDashboardExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class ReportExportService
{
    /**
     * Panonun resmi antetli PDF çıktısını üretir (Binary string döner)
     */
    public function generateDashboardPdf(ReportDashboard $dashboard, array $widgetsWithChartData, string $dateRangeLabel = 'Tüm Zamanlar'): string
    {
        $pdf = Pdf::loadView('pdf.dashboard-report', [
            'dashboard' => $dashboard,
            'widgets' => $widgetsWithChartData,
            'dateRangeLabel' => $dateRangeLabel,
        ])->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    /**
     * Panodaki tüm widget verilerini çok sekmeli kurumsal Excel formatına dönüştürür (Binary string döner)
     */
    public function generateDashboardExcel(ReportDashboard $dashboard, array $widgetsWithChartData, string $dateRangeLabel = 'Tüm Zamanlar'): string
    {
        $export = new ReportDashboardExport($dashboard, $widgetsWithChartData, $dateRangeLabel);
        return Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
    }
}

