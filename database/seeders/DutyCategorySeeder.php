<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DutyCategory;

class DutyCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'code' => 'fuar',
                'name' => 'Fuar & Etkinlik Katılımı',
                'icon' => '🎪',
                'color' => 'indigo',
                'description' => 'Yurt içi veya yurt dışı sektörel fuar, kongre ve stand görevlendirmeleri (Örn: K-Fair, Plast Eurasia)',
                'is_field_duty' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'musteri',
                'name' => 'Müşteri / Firma Ziyareti',
                'icon' => '🏭',
                'color' => 'emerald',
                'description' => 'Müşteri ticari görüşmeleri, saha toplantıları ve teknik satış ziyaretleri',
                'is_field_duty' => true,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'code' => 'servis',
                'name' => 'Satış Sonrası Teknik Servis & Destek',
                'icon' => '🛠️',
                'color' => 'blue',
                'description' => 'Kalıp denemeleri, termoform/şişirme hat arızası müdahalesi ve müşteri sahasında teknik destek',
                'is_field_duty' => true,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'code' => 'transfer',
                'name' => 'Fabrikalar Arası Transfer (Tesisler)',
                'icon' => '🔄',
                'color' => 'amber',
                'description' => 'Gaziantep Merkez, Çorlu ve diğer fabrika yerleşkeleri arası kurum içi operasyonel görevlendirme',
                'is_field_duty' => true,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'code' => 'denetim',
                'name' => 'Tedarikçi & Kalite Denetimi',
                'icon' => '🔍',
                'color' => 'purple',
                'description' => 'Hammadde, katkı ve ambalaj tedarikçisi yerinde kalite denetimi ve şartlandırma kontrolü',
                'is_field_duty' => true,
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'code' => 'egitim',
                'name' => 'Şirket İçi / Dışı Eğitim & Akademi',
                'icon' => '🎓',
                'color' => 'teal',
                'description' => 'Yönetici gelişim, teknik operatör eğitimleri, sertifikasyon ve akademik seminerler',
                'is_field_duty' => true,
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'code' => 'resmi',
                'name' => 'Resmi Kurum & Bakanlık Ziyareti',
                'icon' => '🏛️',
                'color' => 'slate',
                'description' => 'Sanayi Bakanlığı, TSE, gümrük, Çevre Şehircilik ve OSB bölge müdürlüğü resmi temasları',
                'is_field_duty' => true,
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'code' => 'diger',
                'name' => 'Diğer Kurumsal Dış Görev',
                'icon' => '💼',
                'color' => 'rose',
                'description' => 'Yukarıdaki kapsamlara girmeyen diğer kurumsal seyahat ve saha operasyonları',
                'is_field_duty' => true,
                'is_active' => true,
                'sort_order' => 8,
            ],
        ];

        foreach ($categories as $cat) {
            DutyCategory::updateOrCreate(
                ['code' => $cat['code']],
                $cat
            );
        }
    }
}
