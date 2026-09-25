<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\FormTemplate;
use App\Models\Node;
use App\Models\TreeType;
use App\Models\User;
use App\Models\Workflow;
use App\Services\HierarchyManagementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LevhaCompleteWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        echo "========================================================\n";
        echo "  LEVHA BIRIMI HIYERARSI, FORM VE IS AKISI KURULUMU     \n";
        echo "========================================================\n\n";

        $hierarchyService = app(HierarchyManagementService::class);

        // ---------------------------------------------------------
        // 1. SEMA GUNCELLEMESI (TreeType 3: Makine)
        // ---------------------------------------------------------
        $makineType = TreeType::firstOrCreate(
            ['key' => 'makine'],
            ['display_name' => 'Makine', 'is_active' => true]
        );

        $makineSchema = [
            [
                'field' => 'makine_tipi',
                'label' => 'Makine Tipi',
                'type' => 'multiselect',
                'required' => true,
                'options' => ['Levha Makinesi', 'Termoform FFS Makinesi', 'Ekstrüzyon Hattı', 'Resin Makinesi', 'Preform Makinesi', 'Kopet Makinesi', 'Streç Makinesi']
            ],
            [
                'field' => 'marka_model',
                'label' => 'Marka / Model',
                'type' => 'text',
                'required' => false
            ],
            [
                'field' => 'calisma_genisligi',
                'label' => 'Çalışma Genişliği (mm)',
                'type' => 'text',
                'required' => false
            ],
            [
                'field' => 'seri_no',
                'label' => 'Seri No',
                'type' => 'text',
                'required' => false
            ],
            [
                'field' => 'durum',
                'label' => 'Çalışma Durumu',
                'type' => 'select',
                'required' => false,
                'options' => ['Aktif / Çalışıyor', 'Bakımda', 'Arızalı']
            ]
        ];
        $makineType->update(['schema' => $makineSchema]);
        echo "-> [1/4] 'makine' TreeType semasi zenginlestirildi.\n";

        // ---------------------------------------------------------
        // 2. HIYERARSI KURULUMU (/admin/hierarchy)
        // ---------------------------------------------------------
        $rootNode = Node::where('label', 'like', '%Merkez Fabrika%')->first()
            ?? Node::first();

        $uretimNode = Node::where('label', 'like', '%Üretim Birimi%')->first()
            ?? $rootNode;

        $orgTreeType = TreeType::where('key', 'factory_hierarchy')->first() ?? TreeType::first();
        $personelTreeType = TreeType::where('key', 'personel')->first() ?? TreeType::find(4);

        // A) Levha Isletme Birimi Dugumu
        $levhaBirimNode = Node::where('label', 'Levha Üretim İşletmesi')->first();
        if (!$levhaBirimNode) {
            $levhaBirimNode = $hierarchyService->createNode([
                'tree_type_id' => $orgTreeType->id,
                'label'        => 'Levha Üretim İşletmesi',
                'node_subtype' => 'birim',
                'metadata'     => [],
                'is_active'    => true,
            ], $uretimNode ? $uretimNode->id : null);
            echo "-> [2/4] 'Levha Üretim İşletmesi' dugumu hiyerarside olusturuldu (ID: {$levhaBirimNode->id}).\n";
        } else {
            echo "-> [2/4] 'Levha Üretim İşletmesi' dugumu zaten mevcut (ID: {$levhaBirimNode->id}).\n";
        }

        // B) Betapak FFS Makinesi Dugumu (ALMER.xlsx A9 hucresindeki makine!)
        $betapakNode = Node::where('label', 'like', '%Betapak FFS%')->first();
        if (!$betapakNode) {
            $betapakNode = $hierarchyService->createNode([
                'tree_type_id' => $makineType->id,
                'label'        => 'Betapak FFS Termoform Makinesi',
                'node_subtype' => 'makine',
                'metadata'     => [
                    'makine_tipi'       => ['Termoform FFS Makinesi', 'Levha Makinesi'],
                    'marka_model'       => 'Betapak FFS 250 Thermoformer',
                    'calisma_genisligi' => '400 - 550 mm',
                    'seri_no'           => 'BETA-2026-01',
                    'durum'             => 'Aktif / Çalışıyor',
                ],
                'is_active'    => true,
            ], $levhaBirimNode->id);
            echo "   + 'Betapak FFS Termoform Makinesi' makine dugumu baglandi.\n";
        }

        // C) Ekstruzyon Hatti Dugumu
        $hat1Node = Node::where('label', 'like', '%Levha Ekstrüzyon Hattı 1%')->first();
        if (!$hat1Node) {
            $hat1Node = $hierarchyService->createNode([
                'tree_type_id' => $makineType->id,
                'label'        => 'Levha Ekstrüzyon Hattı 1',
                'node_subtype' => 'makine',
                'metadata'     => [
                    'makine_tipi'       => ['Levha Makinesi', 'Ekstrüzyon Hattı'],
                    'marka_model'       => 'Bandera High Speed Extruder',
                    'calisma_genisligi' => '1200 mm',
                    'seri_no'           => 'EXT-LEVHA-01',
                    'durum'             => 'Aktif / Çalışıyor',
                ],
                'is_active'    => true,
            ], $levhaBirimNode->id);
            echo "   + 'Levha Ekstrüzyon Hattı 1' makine dugumu baglandi.\n";
        }

        // D) Levha Isletme Yoneticisi Dugumu (Onayci)
        $mudurUser = User::where('email', 'mudur@test.com')->first();
        $levhaMudurNode = Node::where('label', 'Levha İşletme Sorumlusu')->first();
        if (!$levhaMudurNode) {
            $levhaMudurNode = $hierarchyService->createNode([
                'tree_type_id' => $personelTreeType->id,
                'label'        => 'Levha İşletme Sorumlusu',
                'node_subtype' => 'Yönetici',
                'user_id'      => $mudurUser?->id,
                'metadata'     => [
                    'personel_adi'     => $mudurUser?->name ?? 'Levha İşletme Müdürü',
                    'departman_gorevi' => 'Numune Kabul ve Termin Yetkilisi',
                ],
                'is_active'    => true,
            ], $levhaBirimNode->id);
            echo "   + 'Levha İşletme Sorumlusu' personel dugumu baglandi.\n";
        }

        // E) Levha Kalite Kontrol Uzmani Dugumu (Analiz Girisi)
        $kaliteUser = User::where('email', 'ayse@koksan.com')->first();
        $levhaKaliteNode = Node::where('label', 'Levha Kalite Uzmanı')->first();
        if (!$levhaKaliteNode) {
            $levhaKaliteNode = $hierarchyService->createNode([
                'tree_type_id' => $personelTreeType->id,
                'label'        => 'Levha Kalite Uzmanı',
                'node_subtype' => 'Kalite',
                'user_id'      => $kaliteUser?->id,
                'metadata'     => [
                    'personel_adi'     => $kaliteUser?->name ?? 'Levha Kalite Uzmanı',
                    'departman_gorevi' => 'Kalite Analiz ve Laboratuvar Testleri',
                ],
                'is_active'    => true,
            ], $levhaBirimNode->id);
            echo "   + 'Levha Kalite Uzmanı' personel dugumu baglandi.\n";
        }

        // ---------------------------------------------------------
        // 3. FORM SABLONU KURULUMU (/forms - ALMER.xlsx Birebir)
        // ---------------------------------------------------------
        $formElements = [
            // BOLUM 1: MUSTERI BILGI FORMU (CARI KART)
            [
                'id' => 'h_cari',
                'type' => 'header',
                'label' => '1. Müşteri Cari Bilgileri (Cari Kart)',
                'description' => 'Satış yetkilisi veya ilgili operasyon personeli tarafından doldurulur.',
                'width' => '12'
            ],
            [
                'id' => 'musteri_adi',
                'type' => 'text',
                'label' => 'Müşteri Cari Bilgileri',
                'placeholder' => 'Örn: ALMER LTD',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'sektor',
                'type' => 'select',
                'label' => 'Sektör',
                'options' => 'Distribütör, Gıda Ambalajı, Endüstriyel Üretim, Medikal, Otomotiv, Diğer',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'urun_grubu',
                'type' => 'select',
                'label' => 'Ürün Grubu',
                'options' => 'PET/EVOH, PET MONO, R-PET, G-PET, A-PET, Özel Kompound',
                'required' => true,
                'width' => '4'
            ],
            [
                'id' => 'ulke_bolge',
                'type' => 'text',
                'label' => 'Bölge / Ülke',
                'placeholder' => 'Örn: Bulgaristan / Avrupa',
                'required' => true,
                'width' => '4'
            ],
            [
                'id' => 'faaliyet_alani',
                'type' => 'text',
                'label' => 'Müşteri Faaliyet Alanı',
                'placeholder' => 'Örn: Dilimlenmiş peynir termoform paketleme',
                'required' => false,
                'width' => '4'
            ],
            [
                'id' => 'ekstra_notlar',
                'type' => 'textarea',
                'label' => 'Ekstra Notlar / Müşteri Özel Talepleri',
                'placeholder' => 'Müşteriye dair özel ticari veya teknik notlar...',
                'required' => false,
                'width' => '12'
            ],

            // BOLUM 2: NUMUNE VE SIPARIS DETAYLARI
            [
                'id' => 'h_siparis',
                'type' => 'header',
                'label' => '2. Teknik Sipariş & Numune Özellikleri (Sipariş Detayları)',
                'description' => 'Levha ürününün teknik ölçüleri, hammadde reçetesi ve toleransları.',
                'width' => '12'
            ],
            [
                'id' => 'genislik',
                'type' => 'text',
                'label' => 'Genişlik (Width) ve Tolerans Bilgisi',
                'placeholder' => 'Örn: 422 mm ± 1 mm',
                'required' => true,
                'width' => '4'
            ],
            [
                'id' => 'kalinlik_mikron',
                'type' => 'text',
                'label' => 'Mikron (Kalınlık) ve Tolerans Bilgisi',
                'placeholder' => 'Örn: 550+50 µm veya 750 µm',
                'required' => true,
                'width' => '4'
            ],
            [
                'id' => 'renk',
                'type' => 'select',
                'label' => 'Renk',
                'options' => 'Şeffaf, Siyah, Beyaz, Mavi, Özel Renk',
                'required' => true,
                'width' => '4'
            ],
            [
                'id' => 'laminasyon_turu',
                'type' => 'select',
                'label' => 'Ürün Grubu / Laminasyon Tipi',
                'options' => 'EVOH/SEAL, SEAL, MONO, PEEL, EVOH/PEEL, YOK / NO',
                'required' => true,
                'width' => '4'
            ],
            [
                'id' => 'masura_capi',
                'type' => 'select',
                'label' => 'Masura / Core Çapı',
                'options' => '3\'\' (77MM), 6\'\' (152MM)',
                'required' => true,
                'width' => '4'
            ],
            [
                'id' => 'rulo_ozellikleri',
                'type' => 'text',
                'label' => 'İstenilen Min-Maks Rulo Kg / Metraj / Çap',
                'placeholder' => 'Örn: Maks 450 mm Çap, 250-300 KG veya 350 metre',
                'required' => true,
                'width' => '4'
            ],
            [
                'id' => 'yuzey_islemi',
                'type' => 'select',
                'label' => 'Yüzey İşlemi (Silikon / Antiblok)',
                'options' => 'SİLİKON, ANTİ-BLOK, SİLİKON + ANTİ-BLOK, YOK',
                'required' => true,
                'width' => '3'
            ],
            [
                'id' => 'yuzey_tarafi',
                'type' => 'select',
                'label' => 'Uygulama Yüzeyi',
                'options' => 'İÇ / INNER, DIŞ / EXTERNAL, ÇİFT TARAF / DOUBLE LAYER',
                'required' => true,
                'width' => '3'
            ],
            [
                'id' => 'silikon_orani',
                'type' => 'text',
                'label' => 'Silikon / Antiblok Oranı (%)',
                'placeholder' => 'Örn: 7.5 veya 2.5',
                'required' => false,
                'width' => '3'
            ],
            [
                'id' => 'katki_durumu',
                'type' => 'select',
                'label' => 'Katkı Durumu / Additives',
                'options' => 'YOK, ANTI FOG, ANTI STATIC, UV KORUYUCU, DIŞ KATKI',
                'required' => false,
                'width' => '3'
            ],
            [
                'id' => 'uretim_recetesi',
                'type' => 'textarea',
                'label' => 'Üretim Reçetesi / Oranı (Özel Hammadde Talebi)',
                'placeholder' => 'Örn: %30 Rpet içermesi gerekmektedir. Reçetenin kalan kısmı üretim tarafından belirlenebilir. Siyah rengin tonu numune ile aynı olmalı.',
                'required' => false,
                'width' => '6'
            ],
            [
                'id' => 'kullanim_amaci',
                'type' => 'textarea',
                'label' => 'Son Ürün Kullanım Amacı ve Çalışacağı Makine',
                'placeholder' => 'Örn: 250 gr dilimlenmiş peynir paketlemesi için kullanılacak. FFS Betapak termoform makinesinde çalışacak.',
                'required' => false,
                'width' => '6'
            ],
            [
                'id' => 'paketleme_detaylari',
                'type' => 'text',
                'label' => 'Paketleme ve Palet Ölçüsü / Diziliş',
                'placeholder' => 'Örn: 80*120 CM EPAL, standart streç sarım',
                'required' => false,
                'width' => '6'
            ],
            [
                'id' => 'lojistik_bilgileri',
                'type' => 'text',
                'label' => 'Yükleme Bilgileri ve Ek Talepler',
                'placeholder' => 'Örn: Parsiyel Tır ile sevk edilecek',
                'required' => false,
                'width' => '6'
            ],

            // BOLUM 3: ISLETME INCELEME & TERMIN (ISLETME ADIMI)
            [
                'id' => 'h_isletme',
                'type' => 'header',
                'label' => '3. İşletme Kabul / Ret & Termin Bildirimi',
                'description' => 'Levha birimi onaycısı tarafından doldurulur. Kabul durumunda termin zorunludur.',
                'width' => '12'
            ],
            [
                'id' => 'isletme_karari',
                'type' => 'select',
                'label' => 'İşletme Değerlendirme Kararı',
                'options' => 'Kabul Edildi, Reddedildi, Şartlı Kabul',
                'required' => false,
                'width' => '4'
            ],
            [
                'id' => 'termin_araligi',
                'type' => 'text',
                'label' => 'Termin Aralığı / Tahmini Teslim Tarihi',
                'placeholder' => 'Örn: 05.10.2026 - 10.10.2026 (7 İş Günü)',
                'required' => false,
                'width' => '8'
            ],
            [
                'id' => 'isletme_notu',
                'type' => 'textarea',
                'label' => 'İşletme Notu / Planlama Açıklaması',
                'placeholder' => 'Üretim planı, hat müsaitliği veya ret gerekçesi...',
                'required' => false,
                'width' => '12'
            ],

            // BOLUM 4: KALITE ANALIZ SONUCLARI (CoA PDF'E AKACAK VERILER)
            [
                'id' => 'h_analiz',
                'type' => 'header',
                'label' => '4. Üretim & Kalite Kontrol Analiz Sonuçları (Sertifika)',
                'description' => 'Numune üretildikten sonra kalite/laboratuvar uzmanı tarafından doldurulur.',
                'width' => '12'
            ],
            [
                'id' => 'parti_no',
                'type' => 'text',
                'label' => 'Parti / Lot Numarası',
                'placeholder' => 'Örn: LOT-20260922-LV01',
                'required' => false,
                'width' => '4'
            ],
            [
                'id' => 'uretim_tarihi',
                'type' => 'date',
                'label' => 'Üretim Tarihi',
                'required' => false,
                'width' => '4'
            ],
            [
                'id' => 'gerceklesen_genislik',
                'type' => 'text',
                'label' => 'Gerçekleşen Genişlik (mm)',
                'placeholder' => 'Örn: 422.2',
                'required' => false,
                'width' => '4'
            ],
            [
                'id' => 'gerceklesen_mikron',
                'type' => 'text',
                'label' => 'Gerçekleşen Kalınlık (µm)',
                'placeholder' => 'Örn: 552',
                'required' => false,
                'width' => '4'
            ],
            [
                'id' => 'yogunluk',
                'type' => 'text',
                'label' => 'Yoğunluk (g/cm³)',
                'placeholder' => 'Örn: 1.34',
                'required' => false,
                'width' => '4'
            ],
            [
                'id' => 'cekme_dayanimi',
                'type' => 'text',
                'label' => 'Çekme Dayanımı (MPa)',
                'placeholder' => 'Örn: 58.4',
                'required' => false,
                'width' => '4'
            ],
            [
                'id' => 'kopma_uzamasi',
                'type' => 'text',
                'label' => 'Kopma Uzaması (%)',
                'placeholder' => 'Örn: 215',
                'required' => false,
                'width' => '4'
            ],
            [
                'id' => 'korona_dyne',
                'type' => 'text',
                'label' => 'Yüzey Gerilimi / Korona (Dyne/cm)',
                'placeholder' => 'Örn: 44',
                'required' => false,
                'width' => '4'
            ],
            [
                'id' => 'analiz_gorunum',
                'type' => 'select',
                'label' => 'Görünüm ve Renk Uygunluğu',
                'options' => 'Uygun / Homojen ve Temiz, Şartlı Uygun, Uygun Değil',
                'required' => false,
                'width' => '4'
            ],
            [
                'id' => 'kalite_aciklamasi',
                'type' => 'textarea',
                'label' => 'Laboratuvar Onay ve Kalite Değerlendirme Notu',
                'placeholder' => 'Analiz sonuçları spek değerlerine uygundur...',
                'required' => false,
                'width' => '12'
            ]
        ];

        $adminUser = User::where('email', 'admin@test.com')->first() ?? User::first();

        $formTemplate = FormTemplate::updateOrCreate(
            ['name' => 'Levha Birimi Numune Talep ve Üretim Formu'],
            [
                'description'   => 'ALMER.xlsx spesifikasyonlarına uygun Levha Numune Talep, İşletme Termin ve Kalite Analiz Formu.',
                'schema'        => $formElements,
                'is_active'     => true,
                'document_no'   => 'FR-LVH-042',
                'publish_date'  => now(),
                'revision_no'   => '01',
                'revision_date' => now(),
                'page_no'       => 1,
                'created_by'    => $adminUser?->id,
            ]
        );
        echo "-> [3/4] 'Levha Birimi Numune Talep ve Üretim Formu' olusturuldu/guncellendi (Form ID: {$formTemplate->id}).\n";

        // ---------------------------------------------------------
        // 4. IS AKISI KURULUMU (/workflows)
        // ---------------------------------------------------------
        $workflowNodes = [
            // 1. Baslangic
            [
                'id' => 'node_start',
                'type' => 'start',
                'position' => ['x' => 300, 'y' => 20],
                'data' => [
                    'label' => 'Numune Talebi Başlatma',
                    'taskType' => 'start',
                    'description' => 'Satış yetkilisi numune talep formunu doldurarak akışı başlatır.'
                ]
            ],
            // 2. Isletme Kabul / Ret & Termin Onayi
            [
                'id' => 'node_isletme_onay',
                'type' => 'approval',
                'position' => ['x' => 300, 'y' => 150],
                'data' => [
                    'label' => 'LEVHA İşletme Kabul ve Termin Onayı',
                    'customName' => 'LEVHA İşletme Kabul ve Termin Onayı',
                    'taskType' => 'approval',
                    'assignType' => 'department',
                    'assignValue' => 23, // LEVHA Departmani
                    'requireTermin' => true, // ZORUNLU TERMIN
                    'rejectEnabled' => true,
                    'color' => '#f59e0b',
                    'description' => 'İşletme numuneyi kabul ederse termin aralığı bildirmek zorundadır.'
                ]
            ],

            // 4. Numune Uretimi & Kalite Analiz Girisi
            [
                'id' => 'node_uretim_analiz',
                'type' => 'task',
                'position' => ['x' => 450, 'y' => 450],
                'data' => [
                    'label' => 'Numune Üretimi & Kalite Analiz Girişi',
                    'customName' => 'Numune Üretimi & Kalite Analiz Girişi',
                    'taskType' => 'form',
                    'assignType' => 'department',
                    'assignValue' => 23, // LEVHA
                    'subFormId' => $formTemplate->id,
                    'color' => '#10b981',
                    'description' => 'Numune üretildikten sonra gerçekleşen analiz sonuçları ve Lot no sisteme girilir.'
                ]
            ],
            // 5. Ret Durumu Bildirimi
            [
                'id' => 'node_ret_bildirim',
                'type' => 'task',
                'position' => ['x' => 150, 'y' => 450],
                'data' => [
                    'label' => 'Satışçıya Ret Bildirimi',
                    'customName' => 'Satışçıya Ret Bildirimi',
                    'taskType' => 'notify',
                    'notifyTo' => 'initiator',
                    'notifyTitle' => 'Numune Talebiniz İşletme Tarafından Reddedildi',
                    'color' => '#ef4444',
                    'description' => 'Gerekçeli ret kararı süreci başlatan satışçıya e-posta ile bildirilir.'
                ]
            ],
            // 6. Numune Sevk Edildi & Sertifika Bildirimi
            [
                'id' => 'node_sevk_bildirim',
                'type' => 'task',
                'position' => ['x' => 450, 'y' => 600],
                'data' => [
                    'label' => 'Numune Sevk & Sertifika Bildirimi',
                    'customName' => 'Numune Sevk & Sertifika Bildirimi',
                    'taskType' => 'notify',
                    'notifyTo' => 'initiator',
                    'notifyTitle' => 'Levha Numuneniz Hazırlandı ve Analiz Sertifikası Oluşturuldu',
                    'color' => '#3b82f6',
                    'description' => 'Satışçıya numunenin hazırlandığı ve analiz sertifikasının indirilebilir olduğu bildirilir.'
                ]
            ],
            // 7. Süreç Takibi (15 Gün Sonra Sipariş Takibi)
            [
                'id' => 'node_follow_up',
                'type' => 'io',
                'position' => ['x' => 450, 'y' => 750],
                'data' => [
                    'label' => 'Süreç Takibi',
                    'customName' => 'Süreç Takibi',
                    'taskType' => 'follow_up',
                    'followUpDays' => 15,
                    'followUpIntervalDays' => 5,
                    'followUpMaxReminders' => 3,
                    'followUpTitle' => 'Numune Siparişe Döndü mü?',
                    'followUpPrompt' => '15 gün önce sevk edilen Levha numunesi müşteri tarafından siparişe dönüştürüldü mü? Lütfen sipariş numarasını veya dönmeme gerekçesini sisteme işleyiniz.',
                    'assignType' => 'starter',
                    'assignValue' => '',
                    'subFormId' => '',
                    'followUpType' => 'general_follow_up',
                    'color' => '#0284c7',
                    'description' => 'Numune sevk edildikten 15 gün sonra süreci başlatan satış temsilcisine sipariş dönüşüm takibi (ve SAP sipariş no) sorgulaması başlatır.'
                ]
            ],
            // 8. Bitiş Düğümü (Başarılı)
            [
                'id' => 'node_end',
                'type' => 'end',
                'position' => ['x' => 450, 'y' => 900],
                'data' => [
                    'label' => 'Süreç Başarıyla Tamamlandı',
                    'customName' => 'Süreç Başarıyla Tamamlandı',
                    'taskType' => 'end',
                    'processStatus' => 'completed',
                    'color' => '#10b981',
                    'description' => 'Numunenin yolculuğu ve sipariş dönüşüm takibi başarıyla tamamlandı.'
                ]
            ],
            // 9. Bitiş Düğümü (Ret)
            [
                'id' => 'node_end_rejected',
                'type' => 'end',
                'position' => ['x' => 150, 'y' => 600],
                'data' => [
                    'label' => 'Süreç Sonlandı (Reddedildi)',
                    'customName' => 'Süreç Sonlandı (Reddedildi)',
                    'taskType' => 'end',
                    'processStatus' => 'rejected',
                    'color' => '#ef4444',
                    'description' => 'İşletme ret kararı sonrası süreç sonlandırıldı.'
                ]
            ]
        ];

        $workflowEdges = [
            [
                'id' => 'edge_start_to_isletme',
                'source' => 'node_start',
                'target' => 'node_isletme_onay',
            ],
            [
                'id' => 'edge_isletme_approved',
                'source' => 'node_isletme_onay',
                'target' => 'node_uretim_analiz',
                'sourceHandle' => 'approved',
            ],
            [
                'id' => 'edge_isletme_rejected',
                'source' => 'node_isletme_onay',
                'target' => 'node_ret_bildirim',
                'sourceHandle' => 'rejected',
            ],
            [
                'id' => 'edge_ret_to_end',
                'source' => 'node_ret_bildirim',
                'target' => 'node_end_rejected',
            ],
            [
                'id' => 'edge_uretim_to_sevk',
                'source' => 'node_uretim_analiz',
                'target' => 'node_sevk_bildirim',
            ],
            [
                'id' => 'edge_sevk_to_followup',
                'source' => 'node_sevk_bildirim',
                'target' => 'node_follow_up',
            ],
            [
                'id' => 'edge_followup_to_end',
                'source' => 'node_follow_up',
                'target' => 'node_end',
            ],
        ];

        $workflow = Workflow::updateOrCreate(
            ['name' => 'Levha Numune Talep ve Üretim Süreci'],
            [
                'description'         => 'Satış/Operasyon Numune Talebi, LEVHA İşletme Termin Onayı, Üretim & Analiz Sertifikası ve 15 Günlük Satışçı Takip Döngüsü.',
                'category'            => ['Numune Talebi'],
                'form_template_id'    => $formTemplate->id,
                'allowed_departments' => [], // Tum birimler / kullanicilar baslatabilir
                'nodes'               => $workflowNodes,
                'edges'               => $workflowEdges,
                'status'              => 'active',
                'follow_up_enabled'   => true,
                'is_sample_workflow'  => true,
                'created_by'          => $adminUser?->id,
            ]
        );

        echo "-> [4/4] 'Levha Numune Talep ve Üretim Süreci' is akisi olusturuldu/guncellendi (Workflow ID: {$workflow->id}).\n";
        echo "\n========================================================\n";
        echo "  TUM ENTEGRASYON BASARIYLA TAMAMLANDI!                 \n";
        echo "========================================================\n";
    }
}
