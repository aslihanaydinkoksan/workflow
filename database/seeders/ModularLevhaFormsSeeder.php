<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FormTemplate;
use App\Models\FormCategory;
use App\Models\User;

class ModularLevhaFormsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@test.com')->first();
        $adminId = $admin ? $admin->id : 1;

        // 1. Kategori
        $category = FormCategory::firstOrCreate(
            ['name' => 'Levha Üretim & Numune'],
            ['name' => 'Levha Üretim & Numune']
        );

        // --- FORM 1: FR-LVH-01 (Satış Talep Formu) ---
        $schema1 = [
            [
                'id' => 'h_cari',
                'type' => 'header',
                'label' => '1. Müşteri Cari Bilgileri (Cari Kart)',
                'description' => 'Müşteri ünvanı, faaliyet alanı ve bölge tanımları'
            ],
            [
                'id' => 'musteri_adi',
                'name' => 'musteri_adi',
                'label' => 'Müşteri Cari Bilgileri / Ünvanı',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Örn: ALMER LTD',
                'help' => 'ALMER.xlsx Cari Kart Sayfası'
            ],
            [
                'id' => 'sektor',
                'name' => 'sektor',
                'label' => 'Sektör',
                'type' => 'select',
                'required' => true,
                'options' => ['GIDA AMBALAJI', 'MEDİKAL', 'DİSTRİBÜTÖR', 'ENDÜSTRİYEL', 'OTOMOTİV', 'DİĞER'],
                'placeholder' => 'Sektör Seçiniz'
            ],
            [
                'id' => 'urun_grubu',
                'name' => 'urun_grubu',
                'label' => 'Ürün Grubu',
                'type' => 'select',
                'required' => true,
                'options' => ['PET/EVOH/PE', 'PET/EVOH/SEAL', 'PET/PE', 'MONO PET', 'R-PET', 'GAG', 'BARİYER LEVHA', 'DİĞER'],
                'placeholder' => 'Ürün Grubu Seçiniz'
            ],
            [
                'id' => 'ulke_bolge',
                'name' => 'ulke_bolge',
                'label' => 'Bölge / Ülke',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Örn: BULGARİSTAN'
            ],
            [
                'id' => 'faaliyet_alani',
                'name' => 'faaliyet_alani',
                'label' => 'Müşteri Faaliyet Alanı',
                'type' => 'text',
                'placeholder' => 'Örn: Termoform Ambalaj Üretimi'
            ],
            [
                'id' => 'ekstra_notlar',
                'name' => 'ekstra_notlar',
                'label' => 'Ekstra Notlar / Müşteri Özel Talepleri',
                'type' => 'textarea',
                'placeholder' => 'Varsa ek müşteri talepleri ve özel gereksinimler...'
            ],
            [
                'id' => 'h_siparis',
                'type' => 'header',
                'label' => '2. Teknik Sipariş & Numune Özellikleri (Sipariş Detayları)',
                'description' => 'Levha boyut, mikron ve tolerans gereksinimleri'
            ],
            [
                'id' => 'genislik',
                'name' => 'genislik',
                'label' => 'Genişlik (Width) ve Tolerans Bilgisi',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Örn: 422 mm ± 1 mm',
                'help' => 'ALMER.xlsx Genişlik Spesifikasyonu'
            ],
            [
                'id' => 'kalinlik_mikron',
                'name' => 'kalinlik_mikron',
                'label' => 'Mikron (Kalınlık) ve Tolerans Bilgisi',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Örn: 550+50 µm',
                'help' => 'Nominal ve artı tolerans mikronu'
            ],
            [
                'id' => 'renk',
                'name' => 'renk',
                'label' => 'Renk',
                'type' => 'select',
                'required' => true,
                'options' => ['ŞEFFAF (TRANSPARENT)', 'BEYAZ', 'SİYAH', 'KAHVERENGİ', 'ÖZEL RENK'],
                'placeholder' => 'Renk Seçiniz'
            ],
            [
                'id' => 'laminasyon_turu',
                'name' => 'laminasyon_turu',
                'label' => 'Ürün Grubu / Laminasyon Tipi',
                'type' => 'select',
                'required' => true,
                'options' => ['STANDART MONO', 'EVOH BARİYER', 'SEAL (ISIL YAPIŞMALI)', 'PE LAMİNASYONLU'],
                'placeholder' => 'Laminasyon Tipi Seçiniz'
            ],
            [
                'id' => 'masura_capi',
                'name' => 'masura_capi',
                'label' => 'Masura / Core Çapı',
                'type' => 'select',
                'required' => true,
                'options' => ["3'' (76-77 mm)", "6'' (152 mm)"],
                'placeholder' => 'Masura Çapı Seçiniz'
            ],
            [
                'id' => 'rulo_ozellikleri',
                'name' => 'rulo_ozellikleri',
                'label' => 'İstenilen Min-Maks Rulo Kg / Metraj / Çap',
                'type' => 'text',
                'placeholder' => 'Örn: 250-400 kg / Dış çap maks 800 mm / Dışa sarım'
            ],
            [
                'id' => 'yuzey_islemi',
                'name' => 'yuzey_islemi',
                'label' => 'Yüzey İşlemi (Silikon / Antiblok)',
                'type' => 'select',
                'required' => true,
                'options' => ['SİLİKONLU (TEK YÜZEY)', 'SİLİKONLU (ÇİFT YÜZEY)', 'KORONALI', 'KORONA + SİLİKON', 'İŞLEMSİZ'],
                'placeholder' => 'Yüzey İşlemi Seçiniz'
            ],
            [
                'id' => 'yuzey_tarafi',
                'name' => 'yuzey_tarafi',
                'label' => 'Uygulama Yüzeyi',
                'type' => 'select',
                'options' => ['İÇ YÜZEY', 'DIŞ YÜZEY', 'ÇİFT TARAFLI']
            ],
            [
                'id' => 'silikon_orani',
                'name' => 'silikon_orani',
                'label' => 'Silikon / Antiblok Oranı (%)',
                'type' => 'text',
                'placeholder' => 'Örn: %7.5',
                'default' => '7.5'
            ],
            [
                'id' => 'katki_durumu',
                'name' => 'katki_durumu',
                'label' => 'Katkı Durumu / Additives',
                'type' => 'select',
                'options' => ['STANDART REÇETE', 'ANTİSTATİK', 'UV BARİYER', 'ANTİBLOK']
            ],
            [
                'id' => 'uretim_recetesi',
                'name' => 'uretim_recetesi',
                'label' => 'Üretim Reçetesi / Oranı (Özel Hammadde Talebi)',
                'type' => 'textarea',
                'placeholder' => 'Özel hammadde karışım oranı varsa belirtiniz...'
            ],
            [
                'id' => 'h_makine',
                'type' => 'header',
                'label' => '3. Müşteri Makine Parkuru & Kullanım Şartları',
                'description' => 'Numunenin çalışacağı makine ve son ürün bilgileri'
            ],
            [
                'id' => 'kullanim_amaci',
                'name' => 'kullanim_amaci',
                'label' => 'Son Ürün Kullanım Amacı ve Çalışacağı Makine',
                'type' => 'textarea',
                'required' => true,
                'placeholder' => 'Örn: Betapak FFS 250 termoform makinesinde kaşar/peynir vakumlu termoform ambalajı'
            ],
            [
                'id' => 'numune_miktari_kg',
                'name' => 'numune_miktari_kg',
                'label' => 'Talep Edilen Numune Miktarı (Kg / Rulo)',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Örn: 300 kg (1 Bobin)'
            ],
            [
                'id' => 'hedef_termin_tarihi',
                'name' => 'hedef_termin_tarihi',
                'label' => 'Müşterinin İstediği Hedef Teslim Tarihi',
                'type' => 'date',
                'placeholder' => 'Hedef teslim günü'
            ]
        ];

        $form1 = FormTemplate::updateOrCreate(
            ['document_no' => 'FR-LVH-01'],
            [
                'name' => 'FR-LVH-01: Levha Numune Talep ve Satış Formu',
                'description' => 'Satış temsilcisi tarafından müşteri cari kartı ve teknik levha toleranslarının doldurulduğu başlangıç formu.',
                'category_id' => $category->id,
                'schema' => $schema1,
                'is_active' => true,
                'publish_date' => '2026-10-01',
                'revision_no' => '02',
                'revision_date' => '2026-10-16',
                'paper_size' => 'a4',
                'created_by' => $adminId,
            ]
        );
        $this->command->info("✓ Form 1 Hazır: ID={$form1->id} - {$form1->name} ({$form1->document_no})");

        // --- FORM 2: FR-LVH-02 (İşletme Kabul & Termin Formu) ---
        $schema2 = [
            [
                'id' => 'h_isletme_onay',
                'type' => 'header',
                'label' => 'İşletme Kabul, Termin ve Hat Planlama Değerlendirmesi',
                'description' => 'Levha Üretim İşletmesi tarafından doldurulan termin ve kabul formu'
            ],
            [
                'id' => 'isletme_karari',
                'name' => 'isletme_karari',
                'label' => 'İşletme Değerlendirme Kararı',
                'type' => 'select',
                'required' => true,
                'options' => ['Kabul (Üretime Al)', 'Şartlı Kabul (Not Eklendi)', 'Revizyon İste (Satışçıya İade)', 'Ret (Talebi İptal Et)'],
                'placeholder' => 'Karar Seçiniz'
            ],
            [
                'id' => 'planlanan_hat',
                'name' => 'planlanan_hat',
                'label' => 'Planlanan Ekstrüzyon Hattı / Makine',
                'type' => 'select',
                'required' => true,
                'options' => ['Levha Ekstrüzyon Hattı 1', 'Levha Ekstrüzyon Hattı 2', 'Betapak FFS 250 Thermoformer (Test Hattı)'],
                'placeholder' => 'Üretim Hattı Seçiniz'
            ],
            [
                'id' => 'termin_araligi',
                'name' => 'termin_araligi',
                'label' => 'Bağlayıcı Termin Aralığı (Taahhüt Edilen Tarih)',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Örn: 20.10.2026 - 27.10.2026',
                'help' => 'Kabul durumunda doldurulması zorunlu termin aralığıdır'
            ],
            [
                'id' => 'hammadde_durumu',
                'name' => 'hammadde_durumu',
                'label' => 'Hammadde ve Katkı Stok Durumu',
                'type' => 'select',
                'required' => true,
                'options' => ['Hammadde ve Katkılar Stokta Mevcut', 'Tedarik Edilecek (Termine Dahil)', 'Özel Reçete Hazırlığı Yapılacak'],
                'placeholder' => 'Hammadde Durumu Seçiniz'
            ],
            [
                'id' => 'planlanan_bobin_adedi',
                'name' => 'planlanan_bobin_adedi',
                'label' => 'Planlanan Deneme Bobin Sayısı',
                'type' => 'number',
                'placeholder' => 'Örn: 1'
            ],
            [
                'id' => 'isletme_notu',
                'name' => 'isletme_notu',
                'label' => 'İşletme Notu / Planlama & Revizyon Açıklaması',
                'type' => 'textarea',
                'placeholder' => 'Operasyonel şartlar, hammadde planlaması veya revizyon gerekçesi...'
            ]
        ];

        $form2 = FormTemplate::updateOrCreate(
            ['document_no' => 'FR-LVH-02'],
            [
                'name' => 'FR-LVH-02: Levha İşletme Kabul ve Termin Bildirim Formu',
                'description' => 'Levha İşletme Yetkilisi (Aykut Çelik vb.) tarafından doldurulan kabul, termin aralığı ve makine hattı formu.',
                'category_id' => $category->id,
                'schema' => $schema2,
                'is_active' => true,
                'publish_date' => '2026-10-01',
                'revision_no' => '02',
                'revision_date' => '2026-10-16',
                'paper_size' => 'a4',
                'created_by' => $adminId,
            ]
        );
        $this->command->info("✓ Form 2 Hazır: ID={$form2->id} - {$form2->name} ({$form2->document_no})");

        // --- FORM 3: FR-LVH-03 (Kalite Analiz Formu - CoA) ---
        $schema3 = [
            [
                'id' => 'h_kalite_coa',
                'type' => 'header',
                'label' => 'Üretim & Kalite Kontrol Analiz Sonuçları (Resmi Sertifika)',
                'description' => 'Laboratuvar tolerans ölçümleri ve resmi Analiz Sertifikası (CoA) verileri'
            ],
            [
                'id' => 'parti_no',
                'name' => 'parti_no',
                'label' => 'Parti / Lot Numarası',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Örn: LOT-2026-99441',
                'help' => 'Resmi Analiz Sertifikasına (CoA) basılacak parti numarası'
            ],
            [
                'id' => 'uretim_tarihi',
                'name' => 'uretim_tarihi',
                'label' => 'Üretim Tarihi',
                'type' => 'date',
                'required' => true
            ],
            [
                'id' => 'gerceklesen_genislik',
                'name' => 'gerceklesen_genislik',
                'label' => 'Gerçekleşen Genişlik (mm)',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Örn: 422.2 mm'
            ],
            [
                'id' => 'gerceklesen_mikron',
                'name' => 'gerceklesen_mikron',
                'label' => 'Gerçekleşen Kalınlık / Mikron (µm)',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Örn: 598 µm'
            ],
            [
                'id' => 'yogunluk',
                'name' => 'yogunluk',
                'label' => 'Yoğunluk (g/cm³)',
                'type' => 'text',
                'placeholder' => 'Örn: 1.34 g/cm³',
                'default' => '1.34'
            ],
            [
                'id' => 'cekme_dayanimi',
                'name' => 'cekme_dayanimi',
                'label' => 'Çekme Dayanımı (MPa)',
                'type' => 'text',
                'placeholder' => 'Örn: 55 MPa'
            ],
            [
                'id' => 'kopma_uzamasi',
                'name' => 'kopma_uzamasi',
                'label' => 'Kopma Uzaması (%)',
                'type' => 'text',
                'placeholder' => 'Örn: %250'
            ],
            [
                'id' => 'korona_dyne',
                'name' => 'korona_dyne',
                'label' => 'Yüzey Gerilimi / Korona (Dyne/cm)',
                'type' => 'text',
                'placeholder' => 'Örn: 42 Dyne/cm'
            ],
            [
                'id' => 'analiz_gorunum',
                'name' => 'analiz_gorunum',
                'label' => 'Görünüm ve Renk Uygunluğu',
                'type' => 'select',
                'required' => true,
                'options' => ['UYGUN (ŞEFFAF VE KUSURSUZ)', 'ŞARTLI UYGUN (HAFİF DALGA)', 'UYGUN DEĞİL'],
                'placeholder' => 'Görünüm Uygunluğunu Seçiniz'
            ],
            [
                'id' => 'kalite_karari',
                'name' => 'kalite_karari',
                'label' => 'Kalite Laboratuvar Kararı',
                'type' => 'select',
                'required' => true,
                'options' => ['ONAYLANDI (Resmi CoA Sertifikası Basılsın)', 'ŞARTLI ONAY', 'REDDEDİLDİ (Hatalı Üretim)'],
                'placeholder' => 'Kalite Kararını Seçiniz'
            ],
            [
                'id' => 'kalite_aciklamasi',
                'name' => 'kalite_aciklamasi',
                'label' => 'Laboratuvar Onay ve Kalite Değerlendirme Notu',
                'type' => 'textarea',
                'placeholder' => 'Laboratuvar onay ve tolerans değerlendirme açıklaması...'
            ]
        ];

        $form3 = FormTemplate::updateOrCreate(
            ['document_no' => 'FR-LVH-03'],
            [
                'name' => 'FR-LVH-03: Levha Kalite ve Laboratuvar Analiz Formu (CoA)',
                'description' => 'Kalite Güvence / Laboratuvar teknikeri tarafından doldurulan ve resmi Analiz Sertifikası (CoA PDF) basan form.',
                'category_id' => $category->id,
                'schema' => $schema3,
                'is_active' => true,
                'publish_date' => '2026-10-01',
                'revision_no' => '02',
                'revision_date' => '2026-10-16',
                'paper_size' => 'a4',
                'created_by' => $adminId,
            ]
        );
        $this->command->info("✓ Form 3 Hazır: ID={$form3->id} - {$form3->name} ({$form3->document_no})");

        // --- FORM 4: FR-LVH-04 (Sevkiyat ve Müşteri Takip Formu) ---
        $schema4 = [
            [
                'id' => 'h_sevkiyat',
                'type' => 'header',
                'label' => '1. Sevkiyat ve Kargo Bilgileri',
                'description' => 'Numunenin müşteriye sevk edilme detayları'
            ],
            [
                'id' => 'sevk_tarihi',
                'name' => 'sevk_tarihi',
                'label' => 'Sevk Tarihi',
                'type' => 'date',
                'required' => true
            ],
            [
                'id' => 'kargo_firmasi',
                'name' => 'kargo_firmasi',
                'label' => 'Kargo / Lojistik Firması',
                'type' => 'select',
                'required' => true,
                'options' => ['YURTİÇİ KARGO', 'DHL EXPRESS', 'MNG KARGO', 'FABRİKADAN ARAÇLA TESLİM', 'DİĞER LOJİSTİK'],
                'placeholder' => 'Kargo Firması Seçiniz'
            ],
            [
                'id' => 'takip_no',
                'name' => 'takip_no',
                'label' => 'Kargo Takip No / İrsaliye Numarası',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Örn: TR-982481023 veya İrsaliye No'
            ],
            [
                'id' => 'sevk_edilen_miktar_kg',
                'name' => 'sevk_edilen_miktar_kg',
                'label' => 'Sevk Edilen Net Miktar (Kg / Rulo)',
                'type' => 'text',
                'placeholder' => 'Örn: 310 kg (1 Bobin)'
            ],
            [
                'id' => 'coa_belgesi_eklendi',
                'name' => 'coa_belgesi_eklendi',
                'label' => 'Analiz Sertifikası (CoA) Pakete Eklendi mi?',
                'type' => 'select',
                'options' => ['EVET (Resmi CoA Sertifikası Pakete Eklendi)', 'HAYIR']
            ],
            [
                'id' => 'h_takip_sonuc',
                'type' => 'header',
                'label' => '2. Müşteri Makine Deneme Sonucu & SAP Sipariş Kapama (15 Gün Sonra)',
                'description' => 'Müşteri deneme performansı ve ticari sipariş dönüşümü'
            ],
            [
                'id' => 'musteri_deneme_sonucu',
                'name' => 'musteri_deneme_sonucu',
                'label' => 'Müşteri Makine Deneme Sonucu',
                'type' => 'select',
                'options' => [
                    '🟢 SİPARİŞE DÖNÜŞTÜ (BAŞARILI)',
                    '🟡 DENEME DEVAM EDİYOR / İKİNCİ NUMUNE İSTENDİ',
                    '🔴 TEKNİK UYUMSUZLUK (MAKİNEYE UYMADI)',
                    '⚪ FİYAT / TİCARİ NEDENLE İPTAL'
                ],
                'placeholder' => 'Müşteri Deneme Sonucunu Seçiniz'
            ],
            [
                'id' => 'sap_siparis_no',
                'name' => 'sap_siparis_no',
                'label' => 'SAP Satış Siparişi Numarası',
                'type' => 'text',
                'placeholder' => 'Örn: SAP-2026-99441'
            ],
            [
                'id' => 'yillik_potansiyel_ton',
                'name' => 'yillik_potansiyel_ton',
                'label' => 'Tahmini Yıllık Sipariş Tonajı (Ton/Yıl)',
                'type' => 'text',
                'placeholder' => 'Örn: 120 Ton / Yıl'
            ],
            [
                'id' => 'musteri_geri_bildirim_notu',
                'name' => 'musteri_geri_bildirim_notu',
                'label' => 'Müşteri Teknik ve Ticari Geri Bildirimi',
                'type' => 'textarea',
                'placeholder' => 'Müşterinin termoform kalıbında şekillendirme ve sızdırmazlık performansı...'
            ]
        ];

        $form4 = FormTemplate::updateOrCreate(
            ['document_no' => 'FR-LVH-04'],
            [
                'name' => 'FR-LVH-04: Numune Sevkiyat ve Müşteri Takip Formu',
                'description' => 'Lojistik ve Satışçı tarafından kargo takibi, müşteri deneme sonucu ve SAP sipariş dönüşümü için doldurulan form.',
                'category_id' => $category->id,
                'schema' => $schema4,
                'is_active' => true,
                'publish_date' => '2026-10-01',
                'revision_no' => '01',
                'revision_date' => '2026-10-16',
                'paper_size' => 'a4',
                'created_by' => $adminId,
            ]
        );
        $this->command->info("✓ Form 4 Hazır: ID={$form4->id} - {$form4->name} ({$form4->document_no})");
    }
}
