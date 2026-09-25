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
        $deptServis = Department::where('name', 'like', '%MERKEZ KALİTE%')->first() ?? Department::find(11);

        // c) İdari İşler Yetkilisi (Araç Tahsis & Uçak / Otel Rezervasyonu)
        $userIdari = User::updateOrCreate(
            ['email' => 'idari.isler.test@koksan.com'],
            [
                'name' => 'İdari İşler & Seyahat Sorumlusu',
                'password' => Hash::make('password'),
                'department_id' => $deptIdari?->id,
                'email_verified_at' => now(),
            ]
        );
        $userIdari->syncRoles(['Kullanıcı']);

        // d) Muhasebe & Finans Yetkilisi (Harcırah ve Masraf Mahsubu)
        $userMuhasebe = User::updateOrCreate(
            ['email' => 'muhasebe.test@koksan.com'],
            [
                'name' => 'Muhasebe & Mali İşler Sorumlusu',
                'password' => Hash::make('password'),
                'department_id' => $deptMuhasebe?->id,
                'email_verified_at' => now(),
            ]
        );
        $userMuhasebe->syncRoles(['Kullanıcı']);

        // e) Genel Müdür Yardımcısı / GMY / CEO
        $userGMY = User::updateOrCreate(
            ['email' => 'gmy.test@koksan.com'],
            [
                'name' => 'Genel Müdür Yardımcısı (GMY)',
                'password' => Hash::make('password'),
                'department_id' => $deptGMY?->id,
                'email_verified_at' => now(),
            ]
        );
        $userGMY->syncRoles(['Direktör', 'Müdür']);

        echo "-> [1/4] İdari İşler, Muhasebe ve GMY kurumsal kullanıcıları hazırlandı.\n";

        // ---------------------------------------------------------
        // 2. FORM ŞABLONU 1: DIŞ GÖREV TALEP FORMU (FR-IDR-008)
        // ---------------------------------------------------------
        $disGorevSchema = [
            // BÖLÜM 1: GÖREV AMACI & KATEGORİ
            [
                'id' => 'h_gorev_amaci',
                'type' => 'header',
                'label' => '1. Görev Türü ve Amaç Bilgileri',
                'description' => 'Müdürünüzün belirttiği görev kategorisini ve ziyaret edilecek firmayı seçiniz.',
                'width' => '12'
            ],
            [
                'id' => 'gorev_kategorisi',
                'type' => 'select',
                'label' => 'Dış Görev Türü / Amacı',
                'options' => 'Servis Amaçlı (Satış Sonrası Teknik Destek), Fuar / Etkinlik Katılımı, Müşteri / Firma Ziyareti (Satış & Teknik), Diğer Kurumsal Dış Görev',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'seyahat_bolgesi',
                'type' => 'select',
                'label' => 'Seyahat Bölgesi',
                'options' => 'Yurt İçi, Yurt Dışı (Avrupa), Yurt Dışı (Diğer)',
                'required' => true,
                'width' => '3'
            ],
            [
                'id' => 'ziyaret_edilecek_firma',
                'type' => 'text',
                'label' => 'Ziyaret Edilecek Müşteri / Firma / Kurum',
                'placeholder' => 'Örn: Hedef Müşteri / Firma Adı veya Fuar / Kongre Merkezi',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'hedef_sehir_ulke',
                'type' => 'text',
                'label' => 'Gidilecek Şehir / Ülke',
                'placeholder' => 'Örn: İstanbul veya Sofya / Bulgaristan',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'gorevli_personeller',
                'type' => 'text',
                'label' => 'Görevlendirilen Personeller (Heyet)',
                'placeholder' => 'Örn: Göreve katılacak personellerin isimleri ve unvanları',
                'required' => true,
                'width' => '12'
            ],

            // BÖLÜM 2: SÜRE VE ZAMAN PLANI
            [
                'id' => 'h_zaman',
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
                'placeholder' => 'Örn: 3 İş Günü',
                'required' => true,
                'width' => '4'
            ],

            // BÖLÜM 3: ARAÇ VE ULAŞIM REZERVASYONU
            [
                'id' => 'h_ulasim',
                'type' => 'header',
                'label' => '3. Ulaşım ve Rezervasyon Talepleri (Seyahat)',
                'description' => 'İdari işler tarafından koordine edilecek araç ve bilet tercihleri.',
                'width' => '12'
            ],
            [
                'id' => 'ulasim_tercihi',
                'type' => 'select',
                'label' => 'Araç ve Ulaşım Şekli',
                'options' => 'Şirket Havuz Aracı Tahsisi, Kiralık Araç (Rent-a-Car), Uçak Rezervasyonu (Uçak Bileti), Otobüs / Hızlı Tren Bileti, Kendi Şahsi Aracı (KM Hesabı)',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'ulasim_notlari',
                'type' => 'textarea',
                'label' => 'Ulaşım Detay Notu / Tercih Edilen Saatler',
                'placeholder' => 'Uçuş saati tercihleri, bagaj bilgisi veya araç teslim noktası...',
                'required' => false,
                'width' => '6'
            ],

            // BÖLÜM 4: KONAKLAMA
            [
                'id' => 'h_konaklama',
                'type' => 'header',
                'label' => '4. Konaklama İhtiyacı',
                'description' => 'Otel ve konaklama rezervasyon durumu.',
                'width' => '12'
            ],
            [
                'id' => 'konaklama_durumu',
                'type' => 'select',
                'label' => 'Konaklama Talebi',
                'options' => 'Otel Rezervasyonu Gerekiyor (Şirket Tarafından), Konaklama Gerekmiyor (Günübirlik Seyahat), Karşı Firma / Ev Sahibi Tarafından Karşılanacak',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'konaklama_notlari',
                'type' => 'text',
                'label' => 'Konaklama Detayları / Tercih Edilen Bölge',
                'placeholder' => 'Örn: Fabrikaya yakın otel, 2 adet tek kişilik oda',
                'required' => false,
                'width' => '6'
            ],

            // BÖLÜM 5: MALİ / HARCIRAH VE AVANS
            [
                'id' => 'h_mali',
                'type' => 'header',
                'label' => '5. Mali / Harcırah ve Yol Avansı Talebi',
                'description' => 'Görev için talep edilen ön avans ve yolluk tutarı.',
                'width' => '12'
            ],
            [
                'id' => 'harcirah_talebi',
                'type' => 'select',
                'label' => 'Harcırah / Avans Talebi Var mı?',
                'options' => 'Evet - Harcırah ve Yol Avansı Talep Ediyorum, Hayır - Avans İstemiyorum (Masraflar Dönüşte Kapatılacak)',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'talep_edilen_avans_tutari',
                'type' => 'text',
                'label' => 'Talep Edilen Avans Tutarı ve Para Birimi',
                'placeholder' => 'Örn: 7.500 TL veya 600 EUR',
                'required' => false,
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

            // İŞLETME / İDARİ İŞLER DOLDURULACAK ALANLAR (Operasyon Aşaması)
            [
                'id' => 'h_operasyon_sonuc',
                'type' => 'header',
                'label' => '6. İdari İşler & Mali İşler Operasyon Kaydı',
                'description' => 'Onay sonrası tahsis edilen araç plakası, bilet PNR ve ödenen avans bilgisi.',
                'width' => '12'
            ],
            [
                'id' => 'tahsis_edilen_arac_veya_pnr',
                'type' => 'text',
                'label' => 'Tahsis Edilen Araç Plakası / Uçak PNR No',
                'placeholder' => 'Örn: 27 KOK 042 (Havuz Aracı) veya THY PNR: TK78291',
                'required' => false,
                'width' => '6'
            ],
            [
                'id' => 'otel_rezervasyon_bilgisi',
                'type' => 'text',
                'label' => 'Rezerve Edilen Otel ve Konaklama Bilgisi',
                'placeholder' => 'Örn: Grand Hotel Sofya (2 Gece, Oda+Kahvaltı)',
                'required' => false,
                'width' => '6'
            ],
            [
                'id' => 'odenen_avans_bilgisi',
                'type' => 'text',
                'label' => 'Muhasebe Tarafından Ödenen Avans Tutarı',
                'placeholder' => 'Örn: 7.500 TL (Banka Hesabına Çıkarıldı)',
                'required' => false,
                'width' => '6'
            ],
            [
                'id' => 'idari_isler_notu',
                'type' => 'textarea',
                'label' => 'İdari İşler & Seyahat Koordinasyon Notu',
                'placeholder' => 'Araç anahtarı güvenlikten teslim alınacaktır...',
                'required' => false,
                'width' => '6'
            ],
        ];

        $formDisGorev = FormTemplate::updateOrCreate(
            ['name' => 'Dış Görev Talep ve Seyahat Formu'],
            [
                'description'   => 'Servis, Fuar, Müşteri Ziyareti ve genel şirket dış görevleri için onay ve seyahat talep formu.',
                'schema'        => $disGorevSchema,
                'is_active'     => true,
                'document_no'   => 'FR-IDR-008',
                'publish_date'  => now(),
                'revision_no'   => '01',
                'revision_date' => now(),
                'page_no'       => 1,
            ]
        );

        // ---------------------------------------------------------
        // 3. FORM ŞABLONU 2: SEYAHAT RAPORU FORMU (FR-IK-016)
        // ---------------------------------------------------------
        $seyahatRaporuSchema = [
            [
                'id' => 'h_rapor',
                'type' => 'header',
                'label' => 'Seyahat & Ziyaret Değerlendirme Raporu',
                'description' => 'Dış görev tamamlandıktan sonra yapılan görüşmeler ve aksiyonlar.',
                'width' => '12'
            ],
            [
                'id' => 'gorusulen_yetkililer',
                'type' => 'text',
                'label' => 'Görüşülen Müşteri / Firma Yetkilileri (İsim & Unvanlar)',
                'placeholder' => 'Örn: İvan Petrov (Fabrika Müdürü), Elena Dimitrova (Satın Alma)',
                'required' => true,
                'width' => '12'
            ],
            [
                'id' => 'ziyaret_faaliyetleri',
                'type' => 'textarea',
                'label' => 'Gerçekleştirilen Faaliyetler / Servis ve Teknik İşlemler',
                'placeholder' => 'Servis kapsamında yapılan kalıp ve hat ayarları, termoform denemeleri veya fuar görüşmeleri...',
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
        ];

        $formSeyahatRaporu = FormTemplate::updateOrCreate(
            ['name' => 'Seyahat ve Ziyaret Değerlendirme Raporu'],
            [
                'description'   => 'Dış görev dönüşünde personelin dolduracağı ziyaret ve servis sonuç raporu.',
                'schema'        => $seyahatRaporuSchema,
                'is_active'     => true,
                'document_no'   => 'FR-IK-016',
                'publish_date'  => now(),
                'revision_no'   => '01',
                'revision_date' => now(),
                'page_no'       => 1,
            ]
        );

        // ---------------------------------------------------------
        // 4. FORM ŞABLONU 3: HARCIRAH & SEYAHAT MASRAF FORMU (FR-MUH-021)
        // ---------------------------------------------------------
        $harcamaSchema = [
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
                'placeholder' => 'Örn: #85 veya Dış Görev Referansı',
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

            // BÖLÜM 2: BELGELİ HARCAMALAR
            [
                'id' => 'h_belgeli',
                'type' => 'header',
                'label' => '2. Belgeli Harcamalar (Fatura / Fiş)',
                'description' => 'Fatura ve fiş dökümü (Otoyol, Yakıt, Temsil, Yemek, Taksi vb.).',
                'width' => '12'
            ],
            [
                'id' => 'belgeli_harcama_kalemleri',
                'type' => 'textarea',
                'label' => 'Fatura / Fiş Dökümü (Tarih - Firma - Fatura No - Harcama Türü - Tutar)',
                'placeholder' => "1. 28.09.2026 - Opet - FT#1283 - Yakıt: 1.850 TL\n2. 29.09.2026 - Restoran - Fiş#994 - Müşteri Yemeği: 1.450 TL\n3. 30.09.2026 - HGS/Köprü: 420 TL",
                'required' => true,
                'width' => '8'
            ],
            [
                'id' => 'toplam_belgeli_harcama',
                'type' => 'text',
                'label' => 'Toplam Belgeli Harcama Tutarı (TL)',
                'placeholder' => 'Örn: 3.720 TL',
                'required' => true,
                'width' => '4'
            ],

            // BÖLÜM 3: BELGESİZ HARCAMALAR (HARCIRAH / YOLLUK)
            [
                'id' => 'h_belgesiz',
                'type' => 'header',
                'label' => '3. Belgesiz Harcamalar (Günlük Harcırah / Yolluk)',
                'description' => 'Şirket yolluk yönetmeliğine göre günlük standart harcırah hesabı.',
                'width' => '12'
            ],
            [
                'id' => 'harcirah_gun_sayisi',
                'type' => 'text',
                'label' => 'Harcırah Gün Sayısı',
                'placeholder' => 'Örn: 3 Gün',
                'required' => true,
                'width' => '4'
            ],
            [
                'id' => 'gunluk_harcirah_tutari',
                'type' => 'text',
                'label' => 'Günlük Standart Harcırah Tutarı',
                'placeholder' => 'Örn: 800 TL / Gün',
                'required' => true,
                'width' => '4'
            ],
            [
                'id' => 'toplam_belgesiz_harcirah',
                'type' => 'text',
                'label' => 'Toplam Harcırah Tutarı (TL)',
                'placeholder' => 'Örn: 2.400 TL',
                'required' => true,
                'width' => '4'
            ],

            // BÖLÜM 4: MAHSUPLAŞMA VE NET ÖDEME
            [
                'id' => 'h_mahsup',
                'type' => 'header',
                'label' => '4. Muhasebe Mahsuplaşma ve Net Tutar',
                'description' => 'Genel toplam masraf ve önceden alınan avansın mahsubu.',
                'width' => '12'
            ],
            [
                'id' => 'genel_toplam_masraf',
                'type' => 'text',
                'label' => 'Genel Toplam Masraf (Belgeli + Belgesiz)',
                'placeholder' => 'Örn: 6.120 TL',
                'required' => true,
                'width' => '4'
            ],
            [
                'id' => 'onceden_alinan_avans',
                'type' => 'text',
                'label' => 'Önceden Alınan Yolluk / Avans Tutarı',
                'placeholder' => 'Örn: 5.000 TL',
                'required' => true,
                'width' => '4'
            ],
            [
                'id' => 'odenecek_iade_net_tutar',
                'type' => 'text',
                'label' => 'Personele Ödenecek / İade Edilecek Net Tutar',
                'placeholder' => 'Örn: +1.120 TL (Personele Ödenecek)',
                'required' => true,
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

        $formHarcama = FormTemplate::updateOrCreate(
            ['name' => 'Harcırah ve Seyahat Harcama Masraf Formu'],
            [
                'description'   => 'Dış görev sonrası belgeli ve belgesiz (harcırah) harcamaların bildirilmesi ve mahsuplaşma formu.',
                'schema'        => $harcamaSchema,
                'is_active'     => true,
                'document_no'   => 'FR-MUH-021',
                'publish_date'  => now(),
                'revision_no'   => '01',
                'revision_date' => now(),
                'page_no'       => 1,
            ]
        );

        echo "-> [2/4] 3 Adet resmi form şablonu (Dış Görev, Seyahat Raporu, Harcırah Masraf) oluşturuldu.\n";

        // ---------------------------------------------------------
        // 5. İŞ AKIŞI 1: DIŞ GÖREV SÜRECİ (WORKFLOW)
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
                    'description' => 'Personel görev amacını (Servis, Fuar, Müşteri Ziyareti), firma ve süre bilgilerini girerek talebi başlatır.'
                ]
            ],
            // 2. İlk Amir Onayı
            [
                'id' => 'node_ilk_amir',
                'type' => 'approval',
                'position' => ['x' => 350, 'y' => 150],
                'data' => [
                    'label' => 'İlk Amir Onayı',
                    'customName' => 'İlk Amir Onayı',
                    'taskType' => 'approval',
                    'assignType' => 'role',
                    'assignValue' => 'Amir',
                    'rejectEnabled' => true,
                    'color' => '#f59e0b',
                    'description' => 'Bağlı bulunan birim amiri görev gerekçesini, süresini ve amacını inceler.'
                ]
            ],
            // 3. Üst Amir Onayı (Müdür / Direktör)
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
            // 4. (Opsiyonel / Şartlı) GMY / CEO Onayı
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
            // 5. İdari İşler & Seyahat Rezervasyon (Araç / Uçak / Otel)
            [
                'id' => 'node_idari_isler',
                'type' => 'task',
                'position' => ['x' => 350, 'y' => 600],
                'data' => [
                    'label' => 'İdari İşler & Seyahat Koordinasyonu',
                    'customName' => 'İdari İşler (Araç, Uçak & Otel Rezervasyonu)',
                    'taskType' => 'form',
                    'assignType' => 'department',
                    'assignValue' => $deptIdari?->id ?? 4,
                    'subFormId' => $formDisGorev->id,
                    'color' => '#0284c7',
                    'description' => 'İdari işler araç tahsisini yapar (plaka bilgisi), uçak biletini alır ve otel rezervasyonunu işler.'
                ]
            ],
            // 6. Mali İşler (Harcırah & Avans Ödemesi)
            [
                'id' => 'node_mali_avans',
                'type' => 'task',
                'position' => ['x' => 350, 'y' => 750],
                'data' => [
                    'label' => 'Mali İşler (Harcırah & Avans Ödemesi)',
                    'customName' => 'Muhasebe (Yolluk & Avans Ödemesi)',
                    'taskType' => 'form',
                    'assignType' => 'department',
                    'assignValue' => $deptMuhasebe?->id ?? 21,
                    'subFormId' => $formDisGorev->id,
                    'color' => '#10b981',
                    'description' => 'Muhasebe yolluk/avans ödemesini personelin banka hesabına aktarır ve onaylar.'
                ]
            ],
            // 7. Seyahat Raporu Girişi (Görev Dönüşü - Madde 5)
            [
                'id' => 'node_seyahat_raporu',
                'type' => 'task',
                'position' => ['x' => 350, 'y' => 900],
                'data' => [
                    'label' => 'Seyahat & Ziyaret Raporu Girişi',
                    'customName' => 'Seyahat Raporu (Ziyaret / Servis Değerlendirmesi)',
                    'taskType' => 'form',
                    'assignType' => 'starter',
                    'subFormId' => $formSeyahatRaporu->id,
                    'color' => '#f97316',
                    'description' => 'Görevden dönen personel görüşme notlarını, teknik servis sonuçlarını ve aksiyonları sisteme girer.'
                ]
            ],
            // 8. Bitiş Düğümü (Başarılı)
            [
                'id' => 'node_end',
                'type' => 'output',
                'position' => ['x' => 350, 'y' => 1050],
                'data' => [
                    'label' => 'Dış Görev Başarıyla Tamamlandı',
                    'customName' => 'Dış Görev Başarıyla Tamamlandı',
                    'taskType' => 'end',
                    'processStatus' => 'completed',
                    'description' => 'Dış görev ve seyahat raporu tamamlandı. Harcırah/masraf kapama süreci başlatılabilir.'
                ]
            ],
            // 9. Ret Bitiş Düğümü
            [
                'id' => 'node_end_rejected',
                'type' => 'output',
                'position' => ['x' => 100, 'y' => 450],
                'data' => [
                    'label' => 'Dış Görev Talebi Reddedildi',
                    'customName' => 'Dış Görev Talebi Reddedildi',
                    'taskType' => 'end',
                    'processStatus' => 'rejected',
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
            ['name' => 'Dış Görev Süreci'],
            [
                'description'      => 'Servis, Fuar, Müşteri Ziyareti ve kurumsal dış görevler için onay, rezervasyon/ulaşım ve seyahat raporu akışı.',
                'category'         => ['Dış Görev'],
                'form_template_id' => $formDisGorev->id,
                'status'           => 'active',
                'nodes'            => $disGorevNodes,
                'edges'            => $disGorevEdges,
                'version'          => 1,
            ]
        );

        echo "-> [3/4] 'Dış Görev Süreci' iş akışı (Workflow ID: {$workflowDisGorev->id}) kuruldu.\n";

        // ---------------------------------------------------------
        // 6. İŞ AKIŞI 2: HARCIRAH & SEYAHAT MASRAF KAPAMA SÜRECİ (WORKFLOW - Madde 6)
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
                'type' => 'output',
                'position' => ['x' => 300, 'y' => 480],
                'data' => [
                    'label' => 'Masraf Hesabı Kapatıldı',
                    'customName' => 'Masraf Hesabı Kapatıldı',
                    'taskType' => 'end',
                    'processStatus' => 'completed',
                    'description' => 'Harcırah ve masraf formu onaylandı, mahsuplaşma tamamlandı.'
                ]
            ],
            [
                'id' => 'node_end_rejected',
                'type' => 'output',
                'position' => ['x' => 100, 'y' => 240],
                'data' => [
                    'label' => 'Masraf Formu Reddedildi',
                    'customName' => 'Masraf Formu Reddedildi',
                    'taskType' => 'end',
                    'processStatus' => 'rejected',
                    'description' => 'Harcamalar amir veya muhasebe tarafından reddedildi.'
                ]
            ],
        ];

        $harcamaEdges = [
            ['id' => 'edge_start_to_amir', 'source' => 'node_start', 'target' => 'node_amir_masraf_onay'],
            ['id' => 'edge_amir_approve', 'source' => 'node_amir_masraf_onay', 'target' => 'node_muhasebe_mahsup', 'sourceHandle' => 'approved', 'label' => 'approved'],
            ['id' => 'edge_amir_reject', 'source' => 'node_amir_masraf_onay', 'target' => 'node_end_rejected', 'sourceHandle' => 'rejected', 'label' => 'rejected'],
            ['id' => 'edge_muhasebe_approve', 'source' => 'node_muhasebe_mahsup', 'target' => 'node_end', 'sourceHandle' => 'approved', 'label' => 'approved'],
            ['id' => 'edge_muhasebe_reject', 'source' => 'node_muhasebe_mahsup', 'target' => 'node_end_rejected', 'sourceHandle' => 'rejected', 'label' => 'rejected'],
        ];

        $workflowHarcama = Workflow::updateOrCreate(
            ['name' => 'Harcırah ve Seyahat Masraf Kapama Süreci'],
            [
                'description'      => 'Dış görev sonrası belgeli (fatura/fiş) ve belgesiz (günlük harcırah) masrafların onaylanması ve avans mahsuplaşması akışı.',
                'category'         => ['Masraf Yönetimi'],
                'form_template_id' => $formHarcama->id,
                'status'           => 'active',
                'nodes'            => $harcamaNodes,
                'edges'            => $harcamaEdges,
                'version'          => 1,
            ]
        );

        echo "-> [4/4] 'Harcırah ve Seyahat Masraf Kapama Süreci' iş akışı (Workflow ID: {$workflowHarcama->id}) kuruldu.\n\n";

        echo "========================================================\n";
        echo "  TÜM DIS GOREV VE HARCIRAH SISTEMI BASARIYLA KURULDU!  \n";
        echo "========================================================\n";
    }
}
