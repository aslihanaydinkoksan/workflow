<?php

namespace Database\Seeders;

use App\Models\FormTemplate;
use Illuminate\Database\Seeder;

class MicrobiologyFormSeeder extends Seeder
{
    public function run(): void
    {
        $schema = [
            [
                'name' => 'referans_no',
                'label' => 'Referans No (ML No)',
                'type' => 'text',
                'placeholder' => 'Örn: ML 202610015',
                'required' => true,
                'width' => '50%',
            ],
            [
                'name' => 'rapor_tarihi',
                'label' => 'Rapor Düzenleme Tarihi',
                'type' => 'date',
                'required' => true,
                'width' => '50%',
            ],
            [
                'name' => 'numune_alinis_sebebi',
                'label' => 'Numune Alınış Sebebi',
                'type' => 'text',
                'placeholder' => 'Örn: Periyodik Hijyen & Mikrobiyolojik Swap Kontrolü',
                'required' => true,
                'width' => '100%',
            ],
            [
                'name' => 'analiz_tablosu',
                'label' => 'Mikrobiyoloji Swap ve Analiz Noktaları Tablosu',
                'type' => 'table_grid',
                'width' => '100%',
                'columns' => [
                    ['key' => 'analiz_tarihi', 'label' => 'Analiz Tarihi', 'type' => 'date'],
                    ['key' => 'numune_cinsi', 'label' => 'Numune Cinsi', 'type' => 'text'],
                    ['key' => 'alim_noktasi', 'label' => 'Numune Alım Noktası', 'type' => 'text'],
                    ['key' => 'koliform', 'label' => 'Koliform (36°C)', 'type' => 'text'],
                    ['key' => 'e_coli', 'label' => 'E-coli (36°C)', 'type' => 'text'],
                    ['key' => 'aqhc', 'label' => 'AQHC (22°C)', 'type' => 'text'],
                    ['key' => 'kuf', 'label' => 'Küf (22°C)', 'type' => 'text'],
                    ['key' => 'maya', 'label' => 'Maya (22°C)', 'type' => 'text'],
                    ['key' => 'not', 'label' => 'Açıklama / Not', 'type' => 'text'],
                ],
            ],
            [
                'name' => 'analiz_yapan',
                'label' => 'Analizi Yapan (Laborant / Sorumlu)',
                'type' => 'text',
                'placeholder' => 'Örn: Aysun Yücel / Mik. Lab. Sorumlusu',
                'required' => true,
                'width' => '50%',
            ],
            [
                'name' => 'onaylayan',
                'label' => 'Onaylayan (Kalite Şefi / Müdürü)',
                'type' => 'text',
                'placeholder' => 'Örn: İskender Şişmen / Kalite Kontrol Şefi',
                'required' => true,
                'width' => '50%',
            ],
        ];

        FormTemplate::updateOrCreate(
            ['document_no' => 'FR-MKK-11'],
            [
                'name' => 'Swap ile Mikrobiyoloji Analiz Formu',
                'document_no' => 'FR-MKK-11',
                'publish_date' => '2014-12-11 00:00:00',
                'revision_no' => '0',
                'revision_date' => '2014-12-11 00:00:00',
                'page_no' => 1,
                'description' => 'Köksan Pet ve Plastik resmi mikrobiyolojik swap ve hijyen analiz raporu şablonu (FR-MKK-11)',
                'schema' => $schema,
                'is_active' => true,
            ]
        );
    }
}
