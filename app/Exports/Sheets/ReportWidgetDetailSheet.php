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

class ReportWidgetDetailSheet implements FromArray, WithHeadings, WithStyles, WithTitle, ShouldAutoSize, WithCustomStartCell, WithEvents
{
    protected string $title;
    protected string $dataSource;
    protected string $chartType;
    protected array $chartData;
    protected string $cleanSheetTitle;

    public function __construct(string $title, string $dataSource, string $chartType, array $chartData, string $cleanSheetTitle)
    {
        $this->title = $title;
        $this->dataSource = $dataSource;
        $this->chartType = $chartType;
        $this->chartData = $chartData;
        $this->cleanSheetTitle = $cleanSheetTitle;
    }

    public function title(): string
    {
        return $this->cleanSheetTitle;
    }

    public function startCell(): string
    {
        return 'A5';
    }

    public function headings(): array
    {
        return [
            'Sıra',
            'Kategori / Boyut',
            'Hesaplanan Değer',
            'Pay (%)',
            'Görsel Dağılım'
        ];
    }

    public function array(): array
    {
        $rows = [];
        $tableRows = $this->chartData['table_rows'] ?? [];
        $total = $this->chartData['total'] ?? 0;
        if ($total == 0) $total = 1;

        $index = 1;
        $sumValues = 0;

        if (is_array($tableRows) && count($tableRows) > 0) {
            foreach ($tableRows as $r) {
                $val = is_numeric($r['value'] ?? 0) ? (float)$r['value'] : 0;
                $sumValues += $val;
                $share = round(($val / $total) * 100, 1);
                
                // Mini görsel bar göstergesi
                $barLength = min(15, (int) round($share / 7));
                $visualBar = str_repeat('█', max(1, $barLength));

                $rows[] = [
                    $index++,
                    (string)($r['label'] ?? 'Diğer'),
                    $val,
                    $share . '%',
                    $visualBar
                ];
            }

            // En alta TOPLAM satırı ekle
            $rows[] = [
                'TOPLAM',
                '-',
                $sumValues,
                '100.0%',
                '███████████████'
            ];
        } elseif (isset($this->chartData['value'])) {
            $rows[] = [
                1,
                'Toplam Ölçülen Değer',
                $this->chartData['value'],
                '100.0%',
                '███████████████'
            ];
            $rows[] = [
                'TOPLAM',
                '-',
                $this->chartData['value'],
                '100.0%',
                '███████████████'
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Üst Başlık Bloğu
                $sheet->setCellValue('A1', mb_strtoupper($this->title, 'UTF-8'));
                $sheet->getStyle('A1')->getFont()->setSize(13)->setBold(true)->getColor()->setRGB('0F766E'); // Teal-700

                $sheet->setCellValue('A2', 'Veri Kaynağı: ' . $this->dataSource . ' | Grafik Tipi: ' . mb_strtoupper($this->chartType) . ' | Raporlama: ' . now()->format('d.m.Y H:i'));
                $sheet->getStyle('A2')->getFont()->setSize(9)->setItalic(true)->getColor()->setRGB('64748B');

                $sheet->getRowDimension(1)->setRowHeight(20);
                $sheet->getRowDimension(5)->setRowHeight(24);
            }
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $tableRows = $this->chartData['table_rows'] ?? [];
        $dataCount = is_array($tableRows) ? count($tableRows) : 1;
        $totalRowIndex = 5 + $dataCount + 1; // Başlık (5) + data satırları + TOPLAM satırı

        return [
            // Tablo Başlığı (Row 5)
            5 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0F766E'], // Deep Teal / Emerald
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            // En Alttaki TOPLAM Satırı
            $totalRowIndex => [
                'font' => ['bold' => true, 'color' => ['rgb' => '0F172A'], 'size' => 10],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F1F5F9'], // Slate-100
                ],
                'borders' => [
                    'top' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '94A3B8'],
                    ],
                    'bottom' => [
                        'borderStyle' => Border::BORDER_DOUBLE, // Standart kurumsal muhasebe çift çizgisi
                        'color' => ['rgb' => '0F172A'],
                    ],
                ],
            ],
            // Tüm Tablo Kenarlıkları
            "A5:E{$totalRowIndex}" => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'E2E8F0'],
                    ],
                ],
            ],
        ];
    }
}
