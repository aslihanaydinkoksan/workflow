<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TaskFormExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    protected array $data;
    protected string $formName;

    public function __construct(array $data, string $formName = 'Form Kaydı')
    {
        $this->data = $data;
        $this->formName = $formName;
    }

    public function collection()
    {
        // Gelen anahtar-değer ($key => $value) dizisini Excel'in anlayacağı satır formatına çevir
        return collect([array_values($this->data)]);
    }

    public function headings(): array
    {
        // Gelen verilerin anahtarlarını (Formdaki soruları) başlık olarak ayarla
        return array_keys($this->data);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // 1. Satırı (Başlıkları) kalın, gri arka planlı ve siyah yazılı yap
            1    => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'], // Tailwind gray-200
                ],
            ],
        ];
    }
}