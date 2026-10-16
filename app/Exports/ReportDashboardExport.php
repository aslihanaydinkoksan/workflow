<?php

namespace App\Exports;

use App\Models\ReportDashboard;
use App\Exports\Sheets\ReportExecutiveSummarySheet;
use App\Exports\Sheets\ReportWidgetDetailSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Illuminate\Support\Str;

class ReportDashboardExport implements WithMultipleSheets
{
    protected ReportDashboard $dashboard;
    protected array $widgetsWithChartData;
    protected string $dateRangeLabel;

    public function __construct(ReportDashboard $dashboard, array $widgetsWithChartData, string $dateRangeLabel = 'Tüm Zamanlar')
    {
        $this->dashboard = $dashboard;
        $this->widgetsWithChartData = $widgetsWithChartData;
        $this->dateRangeLabel = $dateRangeLabel;
    }

    /**
     * Çok sekmeli (Multi-Sheet) Excel dosyasının tüm sayfalarını üretir
     */
    public function sheets(): array
    {
        $sheets = [];

        // 1. Sekme: Üst Düzey Yönetici Özeti (Executive Summary)
        $sheets[] = new ReportExecutiveSummarySheet(
            $this->dashboard->title,
            $this->widgetsWithChartData,
            $this->dateRangeLabel
        );

        // 2..N Sekmeler: Her Bir Grafik & Metrik İçin Ayrıntılı Detay Sayfası
        $usedTitles = ['Yonetici Ozeti' => true];
        $sheetIndex = 1;

        foreach ($this->widgetsWithChartData as $widget) {
            $title = is_object($widget) ? $widget->title : ($widget['title'] ?? 'Grafik');
            $ds = is_object($widget) ? $widget->data_source : ($widget['data_source'] ?? '-');
            $ct = is_object($widget) ? $widget->chart_type : ($widget['chart_type'] ?? 'bar');
            $chartData = is_object($widget) ? $widget->chart_data : ($widget['chart_data'] ?? []);

            // Excel sayfa adı kuralları: Max 31 karakter, *, ?, :, /, \, [, ] karakterleri yasaktır
            $cleanTitle = preg_replace('/[\*\?\:\/\\\\\[\]]/', '', $title);
            $cleanTitle = Str::limit(trim($cleanTitle), 25, '');
            if (empty($cleanTitle)) {
                $cleanTitle = 'Grafik ' . $sheetIndex;
            }

            // Sayfa adlarının benzersiz olmasını garanti et
            $uniqueTitle = $cleanTitle;
            $counter = 1;
            while (isset($usedTitles[$uniqueTitle])) {
                $uniqueTitle = Str::limit($cleanTitle, 22, '') . ' (' . (++$counter) . ')';
            }
            $usedTitles[$uniqueTitle] = true;
            $sheetIndex++;

            $sheets[] = new ReportWidgetDetailSheet($title, $ds, $ct, $chartData, $uniqueTitle);
        }

        return $sheets;
    }
}
