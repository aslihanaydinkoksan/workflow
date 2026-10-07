<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\FormTemplate;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DisGorevWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        echo "========================================================\n";
        echo "  KOKSAN DIS GOREV VE HARCIRAH SURECLERI KURULUMU       \n";
        echo "========================================================\n\n";

        // ---------------------------------------------------------
        // 1. ROLLER VE KULLANICILARIN TANIMLANMASI
        // ---------------------------------------------------------
        $roleAdmin = Role::firstOrCreate(['name' => 'Admin']);
        $roleMudur = Role::firstOrCreate(['name' => 'Müdür']);
        $roleAmir  = Role::firstOrCreate(['name' => 'Amir']);
        $roleDirektor = Role::firstOrCreate(['name' => 'Direktör']);
        $roleKullanici = Role::firstOrCreate(['name' => 'Kullanıcı']);

        // İlgili Departmanlar
        $deptIdari = Department::where('name', 'like', '%İDARİ İŞLER%')->first() ?? Department::find(4);
        $deptMuhasebe = Department::where('name', 'like', '%MUHASEBE%')->first() ?? Department::find(21);
        $deptGMY = Department::where('name', 'like', '%GMY%')->first() ?? Department::find(20);
        $deptSatis = Department::where('name', 'like', '%SATIŞ%')->first() ?? Department::find(61);
        $deptKalite = Department::where('name', 'like', '%MERKEZ KALİTE%')->first() ?? Department::find(11);
        $deptLevha = Department::where('name', 'like', '%LEVHA%')->first() ?? Department::find(23);

        // a) Serkan TELEK (Servis Amaçlı / Satış Sonrası Teknik Destek)
        $userSerkan = User::updateOrCreate(
            ['email' => 'serkan.telek@koksan.com'],
            [
                'name' => 'Serkan TELEK',
                'password' => Hash::make('password'),
                'department_id' => $deptLevha?->id ?? 23,
                'email_verified_at' => now(),
            ]
        );
        $userSerkan->syncRoles(['Amir', 'Kullanıcı']);

        // b) Gülnur Hanım (Müşteri & Firma Ziyareti - Satış & Teknik)
        $userGulnur = User::updateOrCreate(
            ['email' => 'gulnur.test@koksan.com'],
            [
                'name' => 'Gülnur Hanım (Teknik & Satış)',
                'password' => Hash::make('password'),
                'department_id' => $deptSatis?->id ?? 61,
                'email_verified_at' => now(),
            ]
        );
        $userGulnur->syncRoles(['Kullanıcı']);

        // c) Ahmet Aslan (Fuar & Etkinlik - Satış Ekibi)
        $userAhmet = User::updateOrCreate(
            ['email' => 'ahmet.aslan@koksan.com'],
            [
                'name' => 'Ahmet ASLAN',
                'password' => Hash::make('password'),
                'department_id' => $deptSatis?->id ?? 61,
                'email_verified_at' => now(),
            ]
        );
        $userAhmet->syncRoles(['Kullanıcı']);

        // d) İlk Amir (Birim Amiri)
        $userAmir = User::updateOrCreate(
            ['email' => 'amir.test@koksan.com'],
            [
                'name' => 'Hakan DEMİR (Birim Amiri)',
                'password' => Hash::make('password'),
                'department_id' => $deptLevha?->id ?? 23,
                'email_verified_at' => now(),
            ]
        );
        $userAmir->syncRoles(['Amir', 'Kullanıcı']);

        // e) Üst Amir (Departman Müdürü)
        $userMudur = User::updateOrCreate(
            ['email' => 'mudur.test@koksan.com'],
            [
                'name' => 'Aykut ÇELİK (Departman Müdürü)',
                'password' => Hash::make('password'),
                'department_id' => $deptLevha?->id ?? 23,
                'email_verified_at' => now(),
            ]
        );
        $userMudur->syncRoles(['Müdür', 'Amir']);

        // f) İdari İşler Yetkilisi (Araç Tahsis & Uçak / Otel Rezervasyonu)
        $userIdari = User::updateOrCreate(
            ['email' => 'idari.isler.test@koksan.com'],
            [
                'name' => 'İdari İşler & Seyahat Sorumlusu',
                'password' => Hash::make('password'),
                'department_id' => $deptIdari?->id ?? 4,
                'email_verified_at' => now(),
            ]
        );
        $userIdari->syncRoles(['Kullanıcı']);

        // g) Muhasebe & Finans Yetkilisi (Harcırah ve Masraf Mahsubu)
        $userMuhasebe = User::updateOrCreate(
            ['email' => 'muhasebe.test@koksan.com'],
            [
                'name' => 'Muhasebe & Mali İşler Sorumlusu',
                'password' => Hash::make('password'),
                'department_id' => $deptMuhasebe?->id ?? 21,
                'email_verified_at' => now(),
            ]
        );
        $userMuhasebe->syncRoles(['Kullanıcı']);

        // h) Genel Müdür Yardımcısı / GMY / CEO
        $userGMY = User::updateOrCreate(
            ['email' => 'gmy.test@koksan.com'],
            [
                'name' => 'Genel Müdür Yardımcısı (GMY)',
                'password' => Hash::make('password'),
                'department_id' => $deptGMY?->id ?? 20,
                'email_verified_at' => now(),
            ]
        );
        $userGMY->syncRoles(['Direktör', 'Müdür']);

        echo "-> [1/4] Serkan Telek, Gülnur Hn, Ahmet Aslan, Amir, Müdür, GMY, İdari İşler ve Muhasebe kullanıcıları hazırlandı.\n";

        // ---------------------------------------------------------
        // 2. AYRI FORMLARIN TANIMLANMASI (MÜDÜRÜN NOTUNA BİREBİR UYGUN)
        // ---------------------------------------------------------

        // FORM 1: DIŞ GÖREV TALEP VE İZİN FORMU (FR-IDR-008) - Personel Doldurur
        $form1Schema = [
            [
                'id' => 'h_gorev_amaci',
                'type' => 'header',
                'label' => '1. Görev Türü ve Amaç Bilgileri',
                'description' => 'Müdürünüzün el yazısı notunda belirtilen görev türünü ve firmayı seçiniz.',
                'width' => '12'
            ],
            [
                'id' => 'gorev_kategorisi',
                'type' => 'select',
                'label' => 'Dış Görev Türü / Amacı',
                'options' => 'Servis Amaçlı (Satış Sonrası Teknik Destek) - Serkan TELEK ve Ekibi, Fuar / Etkinlik Katılımı - Satış Ekibi, Müşteri / Firma Ziyareti (Satış & Teknik) - Gülnur Hn ve Ekibi, Diğer Kurumsal Dış Görev',
                'required' => true,
                'width' => '7'
            ],
            [
                'id' => 'seyahat_bolgesi',
                'type' => 'select',
                'label' => 'Seyahat Bölgesi',
                'options' => 'Yurt İçi, Yurt Dışı (Avrupa), Yurt Dışı (Diğer)',
                'required' => true,
                'width' => '5'
            ],
            [
                'id' => 'ziyaret_edilecek_firma',
                'type' => 'text',
                'label' => 'Ziyaret Edilecek Müşteri / Firma / Fuar Alanı',
                'placeholder' => 'Örn: ALMER LTD veya Interpack Fuar Merkezi',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'hedef_sehir_ulke',
                'type' => 'text',
                'label' => 'Gidilecek Şehir ve Ülke',
                'placeholder' => 'Örn: Sofya / Bulgaristan veya Düsseldorf / Almanya',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'gorevli_personeller',
                'type' => 'text',
                'label' => 'Görevli Personeller (Heyet Listesi)',
                'placeholder' => 'Örn: Serkan Telek, Gülnur Hanım, Ahmet Aslan',
                'required' => true,
                'width' => '12'
            ],
            [
                'id' => 'h_tarih_sure',
                'type' => 'header',
                'label' => '2. Tarih ve Süre Planı',
                'description' => 'Görevin başlangıç, bitiş tarihleri ve toplam süresi.',
                'width' => '12'
            ],
            [
                'id' => 'cikis_tarihi',
                'type' => 'date',
                'label' => 'Çıkış / Gidiş Tarihi',
                'required' => true,
                'width' => '4'
            ],
            [
                'id' => 'donus_tarihi',
                'type' => 'date',
                'label' => 'Dönüş Tarihi',
                'required' => true,
                'width' => '4'
            ],
            [
                'id' => 'toplam_gun',
                'type' => 'text',
                'label' => 'Toplam Süre (Gün)',
                'placeholder' => 'Tarihlere göre otomatik hesaplanır (Örn: 3 Gün)',
                'required' => false,
                'width' => '4'
            ],
            [
                'id' => 'h_talep_detay',
                'type' => 'header',
                'label' => '3. Ulaşım, Konaklama & Avans İhtiyaç Beyanı',
                'description' => 'İdari işler ve muhasebe tarafından koordine edilecek ihtiyaçlarınızı seçiniz.',
                'width' => '12'
            ],
            [
                'id' => 'ulasim_tercihi',
                'type' => 'select',
                'label' => 'Araç ve Ulaşım Şekli Tercihi',
                'options' => 'Ulaşım Talebim Yok (Kendi İmkânlarımla Ulaşım), Şirket Havuz Aracı Tahsisi, Uçak Rezervasyonu (Uçak Bileti), Kiralık Araç (Rent-a-Car), Otobüs / Hızlı Tren Bileti, Kendi Şahsi Aracı (KM Hesabı)',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'ulasim_notlari',
                'type' => 'text',
                'label' => 'Ulaşım Detay / Tercih Notu',
                'placeholder' => 'Örn: Sabah uçuşu tercih ediliyor, bagaj hakkı gereklidir',
                'required' => false,
                'depends_on' => 'ulasim_tercihi',
                'show_if_not' => 'Ulaşım Talebim Yok',
                'width' => '6'
            ],
            [
                'id' => 'konaklama_durumu',
                'type' => 'select',
                'label' => 'Konaklama Talebi',
                'options' => 'Konaklama Gerekmiyor (Günübirlik Seyahat), Otel Rezervasyonu Gerekiyor (Şirket Tarafından), Karşı Firma / Ev Sahibi Tarafından Karşılanacak',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'konaklama_notlari',
                'type' => 'text',
                'label' => 'Konaklama Detay Notu',
                'placeholder' => 'Örn: Fabrikaya / Fuar alanına yakın otel',
                'required' => false,
                'depends_on' => 'konaklama_durumu',
                'show_if' => 'Otel Rezervasyonu',
                'width' => '6'
            ],
            [
                'id' => 'harcirah_talebi',
                'type' => 'select',
                'label' => 'Harcırah / Yol Avansı Talebi Var mı?',
                'options' => 'Hayır - Avans İstemiyorum (Masraflar Dönüşte Kapatılacak), Evet - Harcırah ve Yol Avansı Talep Ediyorum',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'talep_edilen_avans_tutari',
                'type' => 'text',
                'label' => 'Talep Edilen Avans Tutarı ve Para Birimi',
                'placeholder' => 'Örn: 7.500 TL veya 600 EUR',
                'required' => true,
                'depends_on' => 'harcirah_talebi',
                'show_if' => 'Evet',
                'width' => '6'
            ],
            [
                'id' => 'gorev_amaci_detay',
                'type' => 'textarea',
                'label' => 'Dış Görevin Amacı ve Kapsamlı Açıklaması',
                'placeholder' => 'Görev kapsamında yapılacak teknik servis, müşteri ziyareti veya fuar toplantıları hakkında detaylı bilgi...',
                'required' => true,
                'width' => '12'
            ],
        ];

        $form1 = FormTemplate::updateOrCreate(
            ['name' => 'Dış Görev Talep ve İzin Formu'],
            [
                'description'   => 'Personelin dış görev öncesi doldurduğu resmi talep ve seyahat onay formu.',
                'schema'        => $form1Schema,
                'is_active'     => true,
                'document_no'   => 'FR-IDR-008',
                'publish_date'  => now(),
                'revision_no'   => '01',
                'revision_date' => now(),
                'page_no'       => 1,
            ]
        );

        // FORM 2: SEYAHAT, ARAÇ VE KONAKLAMA KOORDİNASYON FORMU (FR-IDR-009) - İdari İşler Doldurur (Madde 2 & 4)
        $form2Schema = [
            [
                'id' => 'h_idari_arac',
                'type' => 'header',
                'label' => '1. Araç & Ulaşım Koordinasyonu (Madde 2)',
                'description' => 'Tahsis edilen araç veya uçak bilet bilgileri.',
                'width' => '12'
            ],
            [
                'id' => 'tahsis_edilen_ulasim_turu',
                'type' => 'select',
                'label' => 'Tahsis Edilen Ulaşım Türü / Koordinasyon',
                'options' => 'Şirket Havuz Aracı Tahsis Edildi, Kiralık Araç (Rent-a-Car) Sağlandı, Uçak Rezervasyonu Yapıldı (Bilet Alındı), Bilet Alındı (Tren/Otobüs), Ulaşım Koordinasyonu Gerekmiyor (Personel Kendi İmkânlarıyla Gidecek)',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'tahsis_edilen_arac_veya_pnr',
                'type' => 'text',
                'label' => 'Tahsis Edilen Araç Plakası veya Uçak PNR No',
                'placeholder' => 'Örn: 27 KOK 042 (Havuz Aracı) veya THY PNR: TK78291',
                'required' => true,
                'depends_on' => 'tahsis_edilen_ulasim_turu',
                'show_if_not' => 'Gerekmiyor',
                'width' => '6'
            ],
            [
                'id' => 'ulasim_detay_saatler',
                'type' => 'textarea',
                'label' => 'Uçuş Saatleri / Araç Teslim Detayları',
                'placeholder' => 'Kalkış: 08:30 IST -> SOF, Dönüş: 18:00 SOF -> IST, Bilet PDF sisteme eklendi...',
                'required' => false,
                'width' => '12'
            ],
            [
                'id' => 'h_idari_konaklama',
                'type' => 'header',
                'label' => '2. Konaklama ve Otel Rezervasyonu (Madde 4)',
                'description' => 'Rezerve edilen otel ve voucher bilgileri.',
                'width' => '12'
            ],
            [
                'id' => 'konaklama_koordinasyon_durumu',
                'type' => 'select',
                'label' => 'Konaklama Koordinasyon Durumu',
                'options' => 'Otel Rezervasyonu Yapıldı, Konaklama Koordinasyonu Gerekmiyor (Günübirlik Seyahat), Karşı Firma / Ev Sahibi Tarafından Karşılanacak',
                'required' => true,
                'width' => '5'
            ],
            [
                'id' => 'otel_rezervasyon_bilgisi',
                'type' => 'text',
                'label' => 'Rezerve Edilen Otel Adı ve Oda Detayı',
                'placeholder' => 'Örn: Grand Hotel Sofya (2 Gece, Tek Kişilik Oda, Kahvaltı Dahil)',
                'required' => true,
                'depends_on' => 'konaklama_koordinasyon_durumu',
                'show_if' => 'Otel Rezervasyonu Yapıldı',
                'width' => '4'
            ],
            [
                'id' => 'otel_voucher_kod',
                'type' => 'text',
                'label' => 'Rezervasyon Konfirmasyon / Voucher Kodu',
                'placeholder' => 'Örn: BKG-994120',
                'required' => false,
                'depends_on' => 'konaklama_koordinasyon_durumu',
                'show_if' => 'Otel Rezervasyonu Yapıldı',
                'width' => '3'
            ],
            [
                'id' => 'idari_isler_notu',
                'type' => 'textarea',
                'label' => 'İdari İşler Seyahat Yönergesi ve Notlar',
                'placeholder' => 'Araç anahtarı ve bilet evrakları güvenlik noktasından teslim alınabilir.',
                'required' => false,
                'width' => '12'
            ],
        ];

        $form2 = FormTemplate::updateOrCreate(
            ['name' => 'Seyahat, Araç ve Konaklama Koordinasyon Formu'],
            [
                'description'   => 'İdari İşler departmanının araç tahsisi, uçak bileti ve otel rezervasyonlarını kaydettiği resmi form.',
                'schema'        => $form2Schema,
                'is_active'     => true,
                'document_no'   => 'FR-IDR-009',
                'publish_date'  => now(),
                'revision_no'   => '01',
                'revision_date' => now(),
                'page_no'       => 1,
            ]
        );

        // FORM 3: MALİ / HARCIRAH VE YOL AVANSI ÖDEME FORMU (FR-MUH-010) - Muhasebe Doldurur (Madde 3)
        $form3Schema = [
            [
                'id' => 'h_muhasebe_avans',
                'type' => 'header',
                'label' => 'Harcırah & Yol Avansı Ödeme Kaydı (Madde 3)',
                'description' => 'Muhasebe tarafından personelin hesabına çıkarılan yolluk/avans bilgisi.',
                'width' => '12'
            ],
            [
                'id' => 'avans_islem_durumu',
                'type' => 'select',
                'label' => 'Avans İşlem Kararı',
                'options' => 'Avans Ödemesi Yapıldı (Dekont Ektedir), Avans Talebi Bulunmuyor (Harcamalar Görev Dönüşü Masraf Formuyla Mahsuplaşılacak)',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'odenen_avans_bilgisi',
                'type' => 'text',
                'label' => 'Muhasebe Tarafından Ödenen Avans Tutarı',
                'placeholder' => 'Örn: 7.500 TL veya 600 EUR',
                'required' => true,
                'depends_on' => 'avans_islem_durumu',
                'show_if' => 'Ödemesi Yapıldı',
                'width' => '6'
            ],
            [
                'id' => 'odeme_yontemi',
                'type' => 'select',
                'label' => 'Ödeme Şekli / Kanalı',
                'options' => 'Banka Hesabına Havale / EFT, Şirket Veznesi / Nakit Kasa Çıkışı, Kurumsal Kredi Kartı Limiti',
                'required' => true,
                'depends_on' => 'avans_islem_durumu',
                'show_if' => 'Ödemesi Yapıldı',
                'width' => '6'
            ],
            [
                'id' => 'odeme_tarihi',
                'type' => 'date',
                'label' => 'Ödeme Çıkış Tarihi',
                'required' => true,
                'depends_on' => 'avans_islem_durumu',
                'show_if' => 'Ödemesi Yapıldı',
                'width' => '6'
            ],
            [
                'id' => 'dekont_ref_no',
                'type' => 'text',
                'label' => 'Banka Dekontu / Kasa Tediye Fiş No',
                'placeholder' => 'Örn: DKB-2026-8812',
                'required' => false,
                'depends_on' => 'avans_islem_durumu',
                'show_if' => 'Ödemesi Yapıldı',
                'width' => '6'
            ],
            [
                'id' => 'muhasebe_avans_notu',
                'type' => 'textarea',
                'label' => 'Muhasebe & Finans Açıklama Notu',
                'placeholder' => 'Avans personelin maaş hesabına yatırılmıştır. Seyahat bitiminde masraf formu ile mahsuplaşılacaktır.',
                'required' => false,
                'width' => '12'
            ],
        ];

        $form3 = FormTemplate::updateOrCreate(
            ['name' => 'Harcırah ve Yol Avansı Ödeme Formu'],
            [
                'description'   => 'Muhasebe ve Finans biriminin harcırah ve yol avansı ödemelerini işlediği resmi form.',
                'schema'        => $form3Schema,
                'is_active'     => true,
                'document_no'   => 'FR-MUH-010',
                'publish_date'  => now(),
                'revision_no'   => '01',
                'revision_date' => now(),
                'page_no'       => 1,
            ]
        );

        // FORM 4: SEYAHAT VE ZİYARET DEĞERLENDİRME RAPORU (FR-IK-016) - Görev Dönüşü Personel Doldurur (Madde 5)
        $form4Schema = [
            [
                'id' => 'h_rapor_header',
                'type' => 'header',
                'label' => 'Seyahat & Ziyaret Değerlendirme Raporu (Madde 5)',
                'description' => 'Dış görev tamamlandıktan sonra yapılan görüşmeler, teknik servis çıktıları ve aksiyonlar.',
                'width' => '12'
            ],
            [
                'id' => 'gorusulen_yetkililer',
                'type' => 'text',
                'label' => 'Görüşülen Müşteri / Firma Yetkilileri (İsim & Unvanlar)',
                'placeholder' => 'Örn: İvan Petrov (Fabrika Müdürü), Elena Dimitrova (Satın Alma Sorumlusu)',
                'required' => true,
                'width' => '12'
            ],
            [
                'id' => 'ziyaret_faaliyetleri',
                'type' => 'textarea',
                'label' => 'Gerçekleştirilen Faaliyetler / Servis ve Teknik İşlemler',
                'placeholder' => 'Servis kapsamında yapılan kalıp ve hat ayarları, termoform denemeleri veya fuar müşteri görüşmeleri...',
                'required' => true,
                'width' => '12'
            ],
            [
                'id' => 'alinan_aksiyonlar',
                'type' => 'textarea',
                'label' => 'Alınan Kararlar ve Şirket İçi Aksiyon Maddeleri',
                'placeholder' => 'Müşteriye teklif gönderilecek, numune revizyonu yapılacak...',
                'required' => true,
                'width' => '8'
            ],
            [
                'id' => 'ziyaret_memnuniyeti',
                'type' => 'select',
                'label' => 'Genel Ziyaret / Servis Değerlendirmesi',
                'options' => 'Çok Başarılı / Tam Memnuniyet, Olumlu / Süreç Devam Ediyor, Şartlı / Sorun Giderildi, Olumsuz / Takip Gerekiyor',
                'required' => true,
                'width' => '4'
            ],
            [
                'id' => 'takip_sorumlusu',
                'type' => 'text',
                'label' => 'Aksiyon Takip Sorumlusu ve Hedef Tarih',
                'placeholder' => 'Örn: Serkan Telek (15.10.2026)',
                'required' => false,
                'width' => '12'
            ],
        ];

        $form4 = FormTemplate::updateOrCreate(
            ['name' => 'Seyahat ve Ziyaret Değerlendirme Raporu'],
            [
                'description'   => 'Dış görev dönüşünde personelin dolduracağı ziyaret ve servis sonuç raporu.',
                'schema'        => $form4Schema,
                'is_active'     => true,
                'document_no'   => 'FR-IK-016',
                'publish_date'  => now(),
                'revision_no'   => '01',
                'revision_date' => now(),
                'page_no'       => 1,
            ]
        );

        // FORM 5 [AYRI SÜREÇ]: HARCIRAH VE SEYAHAT HARCAMA MASRAF FORMU (FR-MUH-021) - Madde 6
        $form5Schema = [
            [
                'id' => 'h_harcama_bilgi',
                'type' => 'header',
                'label' => '1. İlişkili Dış Görev ve Harcama Bilgileri',
                'description' => 'Fatura ve harcama dökümü (Belgeli ve Belgesiz Harcamalar).',
                'width' => '12'
            ],
            [
                'id' => 'iliskili_dis_gorev_no',
                'type' => 'text',
                'label' => 'İlişkili Dış Görev Takip No',
                'placeholder' => 'Örn: #85 veya DGB-2026-00085',
                'required' => false,
                'width' => '4'
            ],
            [
                'id' => 'personel_adi_unvan',
                'type' => 'text',
                'label' => 'Harcamayı Yapan Personel',
                'placeholder' => 'Ad Soyad / Departman',
                'required' => true,
                'width' => '4'
            ],
            [
                'id' => 'gorev_tarihleri',
                'type' => 'text',
                'label' => 'Seyahat Tarih Aralığı',
                'placeholder' => 'Örn: 28.09.2026 - 30.09.2026',
                'required' => true,
                'width' => '4'
            ],
            [
                'id' => 'h_belgeli',
                'type' => 'header',
                'label' => '2. Belgeli Harcamalar (Dinamik Fatura & Harcama Tablosu)',
                'description' => 'Fatura ve harcama belgelerinizi kalem kalem satır olarak ekleyiniz. Miktar ve birim fiyat girildiğinde toplamlar otomatik hesaplanır.',
                'width' => '12'
            ],
            [
                'id' => 'masraf_kalemleri_tablosu',
                'type' => 'table',
                'label' => 'Belgeli Harcama Kalemleri Listesi (Repeater Grid)',
                'description' => 'Satır ekleyip miktar ve birim fiyat girdiğinizde satır ve dip toplam canlı hesaplanır.',
                'width' => '12',
                'required' => true,
                'currency' => 'EUR',
                'add_button_text' => '+ Yeni Fatura / Harcama Kalemi Ekle',
                'columns' => [
                    ['id' => 'tarih', 'label' => 'Fatura Tarihi', 'type' => 'date', 'width' => '16%', 'required' => true],
                    ['id' => 'kategori', 'label' => 'Masraf Kategorisi', 'type' => 'select', 'options' => 'Ulaşım & Taksi, Otel & Konaklama, Müşteri İkram & Yemek, Yakıt & Otoyol Geçişi, Numune Kargo & Lojistik, Diğer Operasyonel Masraf', 'width' => '22%', 'required' => true],
                    ['id' => 'belge_no', 'label' => 'Fiş/Fatura No & Açıklama', 'type' => 'text', 'placeholder' => 'Örn: F-8910 Taksi fişi', 'width' => '28%', 'required' => true],
                    ['id' => 'miktar', 'label' => 'Miktar/Adet', 'type' => 'number', 'placeholder' => '1', 'width' => '10%', 'default' => 1, 'required' => true],
                    ['id' => 'birim_fiyat', 'label' => 'Birim Fiyat', 'type' => 'number', 'placeholder' => '0.00', 'width' => '12%', 'required' => true],
                    ['id' => 'toplam', 'label' => 'Tutar', 'type' => 'calculated', 'formula' => 'miktar * birim_fiyat', 'width' => '12%'],
                ],
                'footer_summary' => [
                    'label' => 'Belgeli Harcamalar Genel Toplamı',
                    'target_field' => 'belgeli_harcama_toplam',
                    'column' => 'toplam'
                ]
            ],
            [
                'id' => 'belgeli_harcama_toplam',
                'type' => 'text',
                'label' => 'Belgeli Harcamalar Toplam Tutarı (Tablodan Otomatik Toplanır)',
                'placeholder' => 'Tablodan otomatik toplanır...',
                'required' => true,
                'is_readonly' => true,
                'width' => '12'
            ],
            [
                'id' => 'h_belgesiz',
                'type' => 'header',
                'label' => '3. Belgesiz Harcamalar (Günlük Harcırah / Yolluk)',
                'description' => 'Şirket yönetmeliğine göre günlük belgesiz yolluk hakkı.',
                'width' => '12'
            ],
            [
                'id' => 'belgesiz_harcirah_gun',
                'type' => 'text',
                'label' => 'Görev Gün Sayısı',
                'placeholder' => 'Örn: 4 Gün',
                'required' => true,
                'width' => '4'
            ],
            [
                'id' => 'belgesiz_harcirah_toplam',
                'type' => 'text',
                'label' => 'Belgesiz Harcırah Toplam Tutarı',
                'placeholder' => 'Örn: 320 EUR (Günlük 80 EUR x 4 Gün)',
                'required' => true,
                'width' => '8'
            ],
            [
                'id' => 'h_mahsup',
                'type' => 'header',
                'label' => '4. Muhasebe Mahsuplaşma ve Net Tutar (Otomatik Formül)',
                'description' => 'Genel toplam masraf ve önceden alınan avansın mahsubu.',
                'width' => '12'
            ],
            [
                'id' => 'genel_toplam_masraf',
                'type' => 'text',
                'label' => 'Genel Toplam Masraf (Belgeli + Belgesiz - Otomatik Hesaplanır)',
                'placeholder' => 'Otomatik hesaplanır...',
                'required' => true,
                'is_readonly' => true,
                'width' => '4'
            ],
            [
                'id' => 'onceden_alinan_avans',
                'type' => 'text',
                'label' => 'Önceden Alınan Yolluk / Avans Tutarı',
                'placeholder' => 'Örn: 600 EUR',
                'required' => true,
                'width' => '4'
            ],
            [
                'id' => 'odenecek_iade_net_tutar',
                'type' => 'text',
                'label' => 'Personele Ödenecek / İade Edilecek Net Tutar (Otomatik Mahsup)',
                'placeholder' => 'Otomatik hesaplanır...',
                'required' => true,
                'is_readonly' => true,
                'width' => '4'
            ],
            [
                'id' => 'personel_iban',
                'type' => 'text',
                'label' => 'Personel Banka IBAN Numarası',
                'placeholder' => 'TR00 0000 0000 0000 0000 0000 00',
                'required' => false,
                'width' => '6'
            ],
            [
                'id' => 'muhasebe_kapanis_notu',
                'type' => 'textarea',
                'label' => 'Muhasebe & Amir Açıklaması',
                'placeholder' => 'Masraf belgeleri kontrol edilmiş ve mutabakat sağlanmıştır...',
                'required' => false,
                'width' => '6'
            ],
        ];

        $form5 = FormTemplate::updateOrCreate(
            ['name' => 'Harcırah ve Seyahat Harcama Masraf Formu'],
            [
                'description'   => 'Dış görev sonrası belgeli ve belgesiz (harcırah) harcamaların bildirilmesi ve mahsuplaşma formu.',
                'schema'        => $form5Schema,
                'is_active'     => true,
                'document_no'   => 'FR-MUH-021',
                'publish_date'  => now(),
                'revision_no'   => '01',
                'revision_date' => now(),
                'page_no'       => 1,
            ]
        );

        echo "-> [2/4] 5 Adet resmi form şablonu (Dış Görev Talep, İdari Seyahat, Mali Avans, Seyahat Raporu, Harcırah Masraf) ayrı ayrı hazırlandı.\n";

        // ---------------------------------------------------------
        // 3. İŞ AKIŞI 1: DIŞ GÖREV SÜRECİ (WORKFLOW ID: 9)
        // ---------------------------------------------------------
        $disGorevNodes = [
            // 1. Başlangıç
            [
                'id' => 'node_start',
                'type' => 'start',
                'position' => ['x' => 350, 'y' => 20],
                'data' => [
                    'label' => 'Dış Görev Talebi Başlatma',
                    'taskType' => 'start',
                    'description' => 'Personel (Serkan Telek, Gülnur Hn, Satışçı) görev amacını, firma ve süre bilgilerini girerek talebi başlatır.'
                ]
            ],
            // 2. İlk Amir Onayı (Madde 1a)
            [
                'id' => 'node_ilk_amir',
                'type' => 'approval',
                'position' => ['x' => 350, 'y' => 150],
                'data' => [
                    'label' => 'İlk Amir Onayı',
                    'customName' => 'İlk Amir Onayı (Birim Amiri)',
                    'taskType' => 'approval',
                    'assignType' => 'role',
                    'assignValue' => 'Amir',
                    'rejectEnabled' => true,
                    'color' => '#f59e0b',
                    'description' => 'Bağlı bulunan birim amiri görev gerekçesini, süresini ve amacını inceler (Onay/Revizyon/Ret).'
                ]
            ],
            // 3. Üst Amir Onayı (Madde 1b)
            [
                'id' => 'node_ust_amir',
                'type' => 'approval',
                'position' => ['x' => 350, 'y' => 300],
                'data' => [
                    'label' => 'Üst Amir Onayı',
                    'customName' => 'Üst Amir Onayı (Departman Müdürü)',
                    'taskType' => 'approval',
                    'assignType' => 'role',
                    'assignValue' => 'Müdür',
                    'rejectEnabled' => true,
                    'color' => '#8b5cf6',
                    'description' => 'Departman müdürü dış görevi ve bütçe uygunluğunu onaylar.'
                ]
            ],
            // 4. (Opsiyonel / Şartlı) GMY / CEO Onayı (Madde 1c)
            [
                'id' => 'node_gmy_onay',
                'type' => 'approval',
                'position' => ['x' => 350, 'y' => 450],
                'data' => [
                    'label' => 'GMY / CEO Onayı',
                    'customName' => 'Genel Müdür Yardımcısı (GMY) Onayı',
                    'taskType' => 'approval',
                    'assignType' => 'department',
                    'assignValue' => $deptGMY?->id ?? 20,
                    'rejectEnabled' => true,
                    'color' => '#ec4899',
                    'description' => 'Yurt dışı seyahatler veya üst onay gerektiren görevler için GMY/CEO onayı.'
                ]
            ],
            // 5. İdari İşler & Seyahat Koordinasyonu (Madde 2 & 4 - Araç, Uçak & Otel Rezervasyonu)
            [
                'id' => 'node_idari_isler',
                'type' => 'task',
                'position' => ['x' => 350, 'y' => 600],
                'data' => [
                    'label' => 'İdari İşler (Araç, Uçak & Otel Rezervasyonu)',
                    'customName' => 'İdari İşler (Seyahat & Konaklama Koordinasyonu)',
                    'taskType' => 'form',
                    'assignType' => 'department',
                    'assignValue' => $deptIdari?->id ?? 4,
                    'subFormId' => $form2->id,
                    'color' => '#0284c7',
                    'description' => 'İdari işler araç tahsisini yapar (plaka/rentacar), uçak biletini alır ve otel rezervasyonunu işler.'
                ]
            ],
            // 6. Mali İşler (Madde 3 - Harcırah & Avans Ödemesi)
            [
                'id' => 'node_mali_avans',
                'type' => 'task',
                'position' => ['x' => 350, 'y' => 750],
                'data' => [
                    'label' => 'Muhasebe (Harcırah & Yol Avansı Ödemesi)',
                    'customName' => 'Mali İşler (Yolluk & Avans Ödemesi & Dekont)',
                    'taskType' => 'form',
                    'assignType' => 'department',
                    'assignValue' => $deptMuhasebe?->id ?? 21,
                    'subFormId' => $form3->id,
                    'color' => '#10b981',
                    'description' => 'Muhasebe yolluk/avans ödemesini personelin banka hesabına aktarır ve dekontunu girer.'
                ]
            ],
            // 7. Seyahat Raporu Girişi (Madde 5 - Görev Dönüşü)
            [
                'id' => 'node_seyahat_raporu',
                'type' => 'task',
                'position' => ['x' => 350, 'y' => 900],
                'data' => [
                    'label' => 'Seyahat & Ziyaret Raporu Girişi',
                    'customName' => 'Seyahat Raporu (Ziyaret / Servis Değerlendirmesi)',
                    'taskType' => 'form',
                    'assignType' => 'starter',
                    'subFormId' => $form4->id,
                    'color' => '#f97316',
                    'description' => 'Görevden dönen personel görüşme notlarını, teknik servis sonuçlarını ve aksiyonları sisteme girer.'
                ]
            ],
            // 8. Bitiş Düğümü (Başarılı)
            [
                'id' => 'node_end',
                'type' => 'end',
                'position' => ['x' => 350, 'y' => 1050],
                'data' => [
                    'label' => 'Dış Görev Başarıyla Tamamlandı',
                    'customName' => 'Dış Görev Başarıyla Tamamlandı',
                    'taskType' => 'end',
                    'processStatus' => 'completed',
                    'color' => '#10b981',
                    'description' => 'Dış görev ve seyahat raporu tamamlandı. Harcırah/masraf kapama süreci başlatılabilir.'
                ]
            ],
            // 9. Ret Bitiş Düğümü
            [
                'id' => 'node_end_rejected',
                'type' => 'end',
                'position' => ['x' => 100, 'y' => 300],
                'data' => [
                    'label' => 'Dış Görev Talebi Reddedildi',
                    'customName' => 'Dış Görev Talebi Reddedildi',
                    'taskType' => 'end',
                    'processStatus' => 'rejected',
                    'color' => '#ef4444',
                    'description' => 'Talep amir veya yönetim tarafından gerekçeli olarak reddedildi.'
                ]
            ],
        ];

        $disGorevEdges = [
            ['id' => 'edge_start_to_ilk_amir', 'source' => 'node_start', 'target' => 'node_ilk_amir'],
            ['id' => 'edge_ilk_amir_approve', 'source' => 'node_ilk_amir', 'target' => 'node_ust_amir', 'sourceHandle' => 'approved', 'label' => 'approved'],
            ['id' => 'edge_ilk_amir_reject', 'source' => 'node_ilk_amir', 'target' => 'node_end_rejected', 'sourceHandle' => 'rejected', 'label' => 'rejected'],
            ['id' => 'edge_ust_amir_approve', 'source' => 'node_ust_amir', 'target' => 'node_gmy_onay', 'sourceHandle' => 'approved', 'label' => 'approved'],
            ['id' => 'edge_ust_amir_reject', 'source' => 'node_ust_amir', 'target' => 'node_end_rejected', 'sourceHandle' => 'rejected', 'label' => 'rejected'],
            ['id' => 'edge_gmy_approve', 'source' => 'node_gmy_onay', 'target' => 'node_idari_isler', 'sourceHandle' => 'approved', 'label' => 'approved'],
            ['id' => 'edge_gmy_reject', 'source' => 'node_gmy_onay', 'target' => 'node_end_rejected', 'sourceHandle' => 'rejected', 'label' => 'rejected'],
            ['id' => 'edge_idari_to_mali', 'source' => 'node_idari_isler', 'target' => 'node_mali_avans'],
            ['id' => 'edge_mali_to_rapor', 'source' => 'node_mali_avans', 'target' => 'node_seyahat_raporu'],
            ['id' => 'edge_rapor_to_end', 'source' => 'node_seyahat_raporu', 'target' => 'node_end'],
        ];

        $workflowDisGorev = Workflow::updateOrCreate(
            ['id' => 9],
            [
                'name'             => 'Dış Görev Süreci',
                'description'      => 'Servis, Fuar, Müşteri Ziyareti ve kurumsal dış görevler için onay, rezervasyon/ulaşım ve seyahat raporu akışı.',
                'category'         => ['Dış Görev'],
                'form_template_id' => $form1->id,
                'status'           => 'active',
                'nodes'            => $disGorevNodes,
                'edges'            => $disGorevEdges,
                'version'          => 1,
            ]
        );

        echo "-> [3/4] 'Dış Görev Süreci' (Workflow ID: {$workflowDisGorev->id}) kuruldu.\n";

        // ---------------------------------------------------------
        // 4. İŞ AKIŞI 2: HARCIRAH & SEYAHAT MASRAF KAPAMA SÜRECİ (WORKFLOW ID: 10 - Madde 6)
        // ---------------------------------------------------------
        $harcamaNodes = [
            [
                'id' => 'node_start',
                'type' => 'start',
                'position' => ['x' => 300, 'y' => 20],
                'data' => [
                    'label' => 'Masraf Formu Doldurma',
                    'taskType' => 'start',
                    'description' => 'Çalışan seyahat dönüşü fatura/fiş belgeli ve günlük harcırah dökümünü girer.'
                ]
            ],
            [
                'id' => 'node_amir_masraf_onay',
                'type' => 'approval',
                'position' => ['x' => 300, 'y' => 160],
                'data' => [
                    'label' => 'Birim Amiri Masraf Onayı',
                    'customName' => 'Birim Amiri Harcama Onayı',
                    'taskType' => 'approval',
                    'assignType' => 'role',
                    'assignValue' => 'Amir',
                    'rejectEnabled' => true,
                    'color' => '#f59e0b',
                    'description' => 'Birim amiri harcamaların iş amacıyla yapıldığını teyit eder.'
                ]
            ],
            [
                'id' => 'node_muhasebe_mahsup',
                'type' => 'approval',
                'position' => ['x' => 300, 'y' => 320],
                'data' => [
                    'label' => 'Muhasebe Kontrol & Mahsuplaşma',
                    'customName' => 'Mali İşler / Muhasebe Mahsuplaşma Onayı',
                    'taskType' => 'approval',
                    'assignType' => 'department',
                    'assignValue' => $deptMuhasebe?->id ?? 21,
                    'rejectEnabled' => true,
                    'color' => '#10b981',
                    'description' => 'Muhasebe fatura/fiş asıllarını kontrol eder, avans ile mahsuplaşır ve ödemeyi gerçekleştirir.'
                ]
            ],
            [
                'id' => 'node_end',
                'type' => 'end',
                'position' => ['x' => 300, 'y' => 480],
                'data' => [
                    'label' => 'Masraf Hesabı Kapatıldı',
                    'customName' => 'Masraf Hesabı Kapatıldı',
                    'taskType' => 'end',
                    'processStatus' => 'completed',
                    'color' => '#10b981',
                    'description' => 'Harcırah ve masraf formu onaylandı, mahsuplaşma tamamlandı.'
                ]
            ],
            [
                'id' => 'node_end_rejected',
                'type' => 'end',
                'position' => ['x' => 100, 'y' => 240],
                'data' => [
                    'label' => 'Masraf Formu Reddedildi',
                    'customName' => 'Masraf Formu Reddedildi',
                    'taskType' => 'end',
                    'processStatus' => 'rejected',
                    'color' => '#ef4444',
                    'description' => 'Harcamalar amir veya muhasebe tarafından reddedildi.'
                ]
            ],
        ];

        $harcamaEdges = [
            ['id' => 'edge_m_start_to_amir', 'source' => 'node_start', 'target' => 'node_amir_masraf_onay'],
            ['id' => 'edge_m_amir_approve', 'source' => 'node_amir_masraf_onay', 'target' => 'node_muhasebe_mahsup', 'sourceHandle' => 'approved', 'label' => 'approved'],
            ['id' => 'edge_m_amir_reject', 'source' => 'node_amir_masraf_onay', 'target' => 'node_end_rejected', 'sourceHandle' => 'rejected', 'label' => 'rejected'],
            ['id' => 'edge_m_muhasebe_approve', 'source' => 'node_muhasebe_mahsup', 'target' => 'node_end', 'sourceHandle' => 'approved', 'label' => 'approved'],
            ['id' => 'edge_m_muhasebe_reject', 'source' => 'node_muhasebe_mahsup', 'target' => 'node_end_rejected', 'sourceHandle' => 'rejected', 'label' => 'rejected'],
        ];

        $workflowHarcama = Workflow::updateOrCreate(
            ['id' => 10],
            [
                'name'             => 'Harcırah ve Seyahat Masraf Kapama Süreci',
                'description'      => 'Dış görev dönüşü personelin seyahat masraflarını ve harcırahlarını kapatma ve mahsuplaşma süreci.',
                'category'         => ['Masraf & Harcırah'],
                'form_template_id' => $form5->id,
                'status'           => 'active',
                'nodes'            => $harcamaNodes,
                'edges'            => $harcamaEdges,
                'version'          => 1,
            ]
        );

        echo "-> [4/4] 'Harcırah ve Seyahat Masraf Kapama Süreci' (Workflow ID: {$workflowHarcama->id}) kuruldu.\n";
        echo "\n🎉 KÖKSAN DIŞ GÖREV VE HARCIRAH SİSTEMİ BAŞARIYLA TAMAMLANDI!\n";
    }
}
