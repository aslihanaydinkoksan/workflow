<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ReportExecutiveSummarySheet implements FromArray, WithHeadings, WithStyles, WithTitle, ShouldAutoSize, WithCustomStartCell, WithEvents
{
    protected string $dashboardTitle;
    protected array $widgetsData;
    protected string $dateRangeLabel;

    public function __construct(string $dashboardTitle, array $widgetsData, string $dateRangeLabel = 'Tüm Zamanlar')
    {
        $this->dashboardTitle = $dashboardTitle;
        $this->widgetsData = $widgetsData;
        $this->dateRangeLabel = $dateRangeLabel;
    }

    public function title(): string
    {
        return 'Yonetici Ozeti';
    }

    public function startCell(): string
    {
        return 'A5';
    }

    public function headings(): array
    {
        return [
            'No',
            'Grafik / Analiz Modülü',
            'Veri Kaynağı',
            'Toplam Değer / Kayıt',
            'En Yüksek Kategori (Öne Çıkan)',
            'İlgili Detay Sekmesi'
        ];
    }

    public function array(): array
    {
        $rows = [];
        $index = 1;

        foreach ($this->widgetsData as $w) {
            $title = is_object($w) ? $w->title : ($w['title'] ?? 'Grafik');
            $ds = is_object($w) ? $w->data_source : ($w['data_source'] ?? '-');
            $chartData = is_object($w) ? $w->chart_data : ($w['chart_data'] ?? []);

            $total = $chartData['total'] ?? ($chartData['value'] ?? 0);
            $topCategory = '-';

            if (!empty($chartData['table_rows']) && is_array($chartData['table_rows'])) {
                $firstRow = $chartData['table_rows'][0];
                $topCategory = ($firstRow['label'] ?? '-') . ' (' . ($firstRow['value'] ?? 0) . ')';
            }

            $cleanSheetName = \Illuminate\Support\Str::limit(preg_replace('/[\*\?\:\/\\\\\[\]]/', '', $title), 28, '');

            $rows[] = [
                $index++,
                $title,
                $this->formatDataSourceName($ds),
                is_numeric($total) ? number_format((float)$total, 0, ',', '.') : $total,
                $topCategory,
                "👉 '{$cleanSheetName}' sekmesi"
            ];
        }

        return $rows;
    }

    protected function formatDataSourceName(string $ds): string
    {
        return match($ds) {
            'processes' => '🚀 Süreç Performansı',
            'tasks' => '⏱️ Görev & Onaylar',
            'termin_sla' => '🎯 Termin & SLA',
            'follow_ups' => '💼 Numune Dönüşümü',
            default => '📋 Dinamik Form (' . $ds . ')',
        };
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Üst Kurumsal Başlık Bloğu
                $sheet->setCellValue('A1', 'KÖKSAN PET VE PLASTİK AMBALAJ SAN. TİC. A.Ş.');
                $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true)->getColor()->setRGB('1E293B');

                $sheet->setCellValue('A2', 'KURUMSAL İŞ ZEKASI & BPM SÜREÇ PERFORMANS RAPORU: ' . mb_strtoupper($this->dashboardTitle, 'UTF-8'));
                $sheet->getStyle('A2')->getFont()->setSize(11)->setBold(true)->getColor()->setRGB('475569');

                $sheet->setCellValue('A3', 'Rapor Tarihi: ' . now()->format('d.m.Y H:i') . ' | Veri Aralığı: ' . $this->dateRangeLabel . ' | Üretim: Canlı Sistem');
                $sheet->getStyle('A3')->getFont()->setSize(9)->setItalic(true)->getColor()->setRGB('64748B');

                // Satır yükseklikleri
                $sheet->getRowDimension(1)->setRowHeight(22);
                $sheet->getRowDimension(2)->setRowHeight(18);
                $sheet->getRowDimension(5)->setRowHeight(24);
            }
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $rowCount = count($this->widgetsData);
        $lastRow = 5 + $rowCount;

        return [
            5 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E293B'], // Dark Navy
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            // Tüm tablo kenarlıkları
            "A5:F{$lastRow}" => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CBD5E1'],
                    ],
                ],
            ],
        ];
    }
}
