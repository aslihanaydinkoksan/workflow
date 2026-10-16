<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\FormTemplate;
use App\Models\ProcessInstance;
use App\Models\Task;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Pillar3OperationalWorkflowsSeeder extends Seeder
{
    public function run(): void
    {
        echo "====================================================================\n";
        echo "  KÖKSAN 3. SÜTUN: OPERASYONEL SÜREÇLER SEEDER'I (TPM, DÖF, SATIN ALMA)\n";
        echo "====================================================================\n\n";

        $adminUser = User::where('email', 'admin@test.com')->first() ?? User::first();
        $levhaDept = Department::where('name', 'like', '%LEVHA%')->first() ?? Department::first();
        $aykut = User::where('email', 'aykut.celik@koksan.com')->first() ?? $adminUser;
        $hakan = User::where('email', 'hakan.demir@koksan.com')->first() ?? $adminUser;
        $serkan = User::where('email', 'serkan.telek@koksan.com')->first() ?? $adminUser;
        $mustafa = User::where('email', 'mustafa.yilmaz@koksan.com')->first() ?? $adminUser;

        // =========================================================================
        // 1. SÜREÇ: TPM & MAKİNE ARIZA / BAKIM ONARIM SÜRECİ
        // =========================================================================
        echo "👉 [1/3] TPM & Makine Arıza / Bakım Onarım Süreci kuruluyor...\n";

        $tpmFormSchema = [
            [
                'id' => 'h_ariza',
                'type' => 'header',
                'label' => '1. Makine & Arıza Bildirim Bilgileri',
                'description' => 'Operatör veya vardiya sorumlusu tarafından arıza anında doldurulur.',
                'width' => '12'
            ],
            [
                'id' => 'tesis_bolum',
                'type' => 'select',
                'label' => 'İşletme / Bölüm',
                'options' => 'Levha Üretim İşletmesi, Preform İşletmesi, Kapak İşletmesi, Geri Dönüşüm Tesisi',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'makine_hatti',
                'type' => 'select',
                'label' => 'Makine / Üretim Hattı',
                'options' => 'Betapak FFS 250 Thermoformer, Levha Ekstrüzyon Hattı 1, Husky HyPET 400 Enjeksiyon, Netstal Synergies 240, Sacmi CCM Kapak Presi',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'ariza_tipi',
                'type' => 'select',
                'label' => 'Arıza Sınıfı',
                'options' => 'Mekanik Arıza & Rulman/Dişli, Elektrik & Sürücü Arızası, Otomasyon & PLC/Sensör, Hidrolik & Pnömatik Valf, Kalıp & Isıtıcı Rezistans',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'oncelik_derecesi',
                'type' => 'select',
                'label' => 'Öncelik Seviyesi',
                'options' => 'Kritik / Hat Durdu (Acil), Yüksek Öncelikli, Normal, Periyodik / Planlı',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'ariza_baslangic_zamani',
                'type' => 'datetime',
                'label' => 'Arıza Başlangıç Saati',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'bildiren_operator',
                'type' => 'text',
                'label' => 'Bildiren Operatör / Personel',
                'placeholder' => 'Örn: Ahmet YILMAZ (Hat Operatörü)',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'ariza_aciklamasi',
                'type' => 'textarea',
                'label' => 'Arıza Tanımı ve İlk Belirtiler',
                'placeholder' => 'Makine ana tahrik ünitesinde aşırı sürtünme ve ses tespit edildi. Hat güvenlik duruşuna geçti...',
                'required' => true,
                'width' => '12'
            ],

            [
                'id' => 'h_mudahale',
                'type' => 'header',
                'label' => '2. Bakım Onarım Müdahalesi & Duruş Kaydı (TPM)',
                'description' => 'Bakım Onarım teknisyeni veya mühendisi tarafından müdahale sonrasında doldurulur.',
                'width' => '12'
            ],
            [
                'id' => 'bakim_mudahale_baslangic',
                'type' => 'datetime',
                'label' => 'Müdahale Başlangıç Saati',
                'required' => false,
                'width' => '4'
            ],
            [
                'id' => 'bakim_mudahale_bitis',
                'type' => 'datetime',
                'label' => 'Müdahale Bitiş Saati',
                'required' => false,
                'width' => '4'
            ],
            [
                'id' => 'net_durus_suresi_dk',
                'type' => 'number',
                'label' => 'Net Duruş Süresi (Dakika)',
                'placeholder' => 'Örn: 90',
                'required' => false,
                'width' => '4'
            ],
            [
                'id' => 'ariza_kok_nedeni',
                'type' => 'select',
                'label' => 'Arıza Kök Nedeni (Root Cause)',
                'options' => 'Aşınma / Yıpranma & Yağlama Eksikliği, Operatör / Ayar Hatası, Elektrik Dalgalanması & Gerilim Düşüşü, Malzeme Sıkışması & Kalıp Kirliliği, Malzeme / Parça Ömrü Sonu',
                'required' => false,
                'width' => '6'
            ],
            [
                'id' => 'mudahale_eden_teknisyen',
                'type' => 'text',
                'label' => 'Müdahale Eden Bakım Personeli',
                'placeholder' => 'Örn: Serkan TELEK (Mekanik Bakım Şefi)',
                'required' => false,
                'width' => '6'
            ],
            [
                'id' => 'yapilan_mudahale',
                'type' => 'textarea',
                'label' => 'Yapılan İşlem & Uygulanan Çözüm',
                'placeholder' => 'Tahrik milindeki deforme olmuş konik rulman söküldü, yuva temizlendi, yeni rulman preslendi ve yüksek sıcaklık gresi uygulandı...',
                'required' => false,
                'width' => '12'
            ],
            [
                'id' => 'degisen_yedek_parcalar',
                'type' => 'text',
                'label' => 'Değiştirilen Yedek Parça Kodları / Malzemeler',
                'placeholder' => 'Örn: SKF 6208-2RS Rulman (1 Adet), NBR 40x62x7 Keçe (1 Adet)',
                'required' => false,
                'width' => '12'
            ],

            [
                'id' => 'h_test',
                'type' => 'header',
                'label' => '3. Test, Kalite & Devreye Alma Kabulü',
                'description' => 'Üretim / Vardiya Şefi tarafından makine test edildikten sonra doldurulur.',
                'width' => '12'
            ],
            [
                'id' => 'makine_test_durumu',
                'type' => 'select',
                'label' => 'Makine Test Sonucu',
                'options' => 'Test Edildi - Normal Hızda ve Yüksüz/Yüklü Çalışıyor, Şartlı Devreye Alma (Gözlem Altında), Test Başarısız (Ek Müdahale Gerekli)',
                'required' => false,
                'width' => '6'
            ],
            [
                'id' => 'test_kabul_notu',
                'type' => 'textarea',
                'label' => 'Vardiya Şefi Devreye Alma Notu',
                'placeholder' => 'Hat test ürünleri basılarak kontrol edildi, toleranslar uygun, üretim hattı tam kapasite devreye alındı...',
                'required' => false,
                'width' => '6'
            ]
        ];

        $tpmForm = FormTemplate::updateOrCreate(
            ['name' => 'Makine Arıza ve Bakım Onarım Bildirim Formu'],
            [
                'description'   => 'Köksan fabrikaları için TPM arıza bildirimi, bakım müdahalesi, duruş süresi ve test kabul formu.',
                'schema'        => $tpmFormSchema,
                'is_active'     => true,
                'document_no'   => 'FR-BAK-001',
                'publish_date'  => now(),
                'revision_no'   => '03',
                'revision_date' => now(),
                'page_no'       => 1,
                'created_by'    => $adminUser?->id,
            ]
        );

        $tpmNodes = [
            [
                'id' => 'node_start',
                'type' => 'start',
                'position' => ['x' => 300, 'y' => 20],
                'data' => [
                    'label' => 'Arıza Bildirimi',
                    'taskType' => 'start',
                    'description' => 'Operatör veya hat sorumlusu arızayı sisteme girer.'
                ]
            ],
            [
                'id' => 'node_sefi_onay',
                'type' => 'decision',
                'position' => ['x' => 300, 'y' => 150],
                'data' => [
                    'label' => 'Vardiya Şefi Acil Onayı',
                    'customName' => 'Vardiya Şefi Acil Onayı',
                    'taskType' => 'approval',
                    'assignType' => 'department',
                    'assignValue' => $levhaDept->id,
                    'deptManagerType' => 'assistant_manager',
                    'approvalPolicy' => 'any',
                    'color' => '#f59e0b',
                    'description' => 'Vardiya şefi arızayı doğrular ve bakım ekibini göreve sevk eder.'
                ]
            ],
            [
                'id' => 'node_bakim_mudahale',
                'type' => 'task',
                'position' => ['x' => 300, 'y' => 280],
                'data' => [
                    'label' => 'Bakım Müdahalesi & Duruş Kaydı',
                    'customName' => 'Bakım Müdahalesi & Duruş Kaydı',
                    'taskType' => 'form',
                    'assignType' => 'department',
                    'assignValue' => $levhaDept->id,
                    'deptManagerType' => 'chief',
                    'subFormId' => $tpmForm->id,
                    'color' => '#10b981',
                    'description' => 'Bakım teknisyeni onarımı yapar, duruş süresini ve yedek parça bilgisini girer.'
                ]
            ],
            [
                'id' => 'node_isletme_kabul',
                'type' => 'decision',
                'position' => ['x' => 300, 'y' => 410],
                'data' => [
                    'label' => 'Üretim Devreye Alma Kabulü',
                    'customName' => 'Üretim Devreye Alma Kabulü',
                    'taskType' => 'approval',
                    'assignType' => 'department',
                    'assignValue' => $levhaDept->id,
                    'deptManagerType' => 'manager',
                    'color' => '#3b82f6',
                    'description' => 'Hat şefi test ürünlerini inceler ve hattın tam devreye alındığını onaylar.'
                ]
            ],
            [
                'id' => 'node_end',
                'type' => 'end',
                'position' => ['x' => 300, 'y' => 540],
                'data' => ['label' => 'Bakım Tamamlandı & Hat Devrede']
            ],
            [
                'id' => 'node_end_rejected',
                'type' => 'end',
                'position' => ['x' => 550, 'y' => 280],
                'data' => ['label' => 'Arıza İptal Edildi']
            ]
        ];

        $tpmEdges = [
            ['id' => 'e1', 'source' => 'node_start', 'target' => 'node_sefi_onay'],
            ['id' => 'e2', 'source' => 'node_sefi_onay', 'target' => 'node_bakim_mudahale', 'sourceHandle' => 'approved', 'label' => 'Onaylandı'],
            ['id' => 'e3', 'source' => 'node_sefi_onay', 'target' => 'node_end_rejected', 'sourceHandle' => 'rejected', 'label' => 'Reddedildi'],
            ['id' => 'e4', 'source' => 'node_bakim_mudahale', 'target' => 'node_isletme_kabul'],
            ['id' => 'e5', 'source' => 'node_isletme_kabul', 'target' => 'node_end', 'sourceHandle' => 'approved', 'label' => 'Onaylandı'],
            ['id' => 'e6', 'source' => 'node_isletme_kabul', 'target' => 'node_end_rejected', 'sourceHandle' => 'rejected', 'label' => 'Reddedildi'],
        ];

        $tpmWorkflow = Workflow::updateOrCreate(
            ['name' => 'TPM & Makine Arıza / Bakım Onarım Süreci'],
            [
                'description' => 'Köksan fabrikalarında makine arıza bildirimi, bakım onarım müdahalesi, duruş süresi takibi ve devreye alma süreci.',
                'category' => ['Bakım & TPM', 'Üretim'],
                'form_template_id' => $tpmForm->id,
                'status' => 'active',
                'nodes' => $tpmNodes,
                'edges' => $tpmEdges,
                'version' => 1,
            ]
        );

        echo "   + TPM İş Akışı Kaydedildi (ID: {$tpmWorkflow->id})\n";


        // =========================================================================
        // 2. SÜREÇ: DÜZELTİCİ VE ÖNLEYİCİ FAALİYET (DÖF / 8D) SÜRECİ
        // =========================================================================
        echo "👉 [2/3] Düzeltici ve Önleyici Faaliyet (DÖF) Süreci kuruluyor...\n";

        $dofFormSchema = [
            [
                'id' => 'h_dof_bilgi',
                'type' => 'header',
                'label' => '1. Uygunsuzluk Tanımı ve Kaynağı',
                'description' => 'Kalite Güvence veya ilgili denetçi tarafından açılır.',
                'width' => '12'
            ],
            [
                'id' => 'dof_kaynagi',
                'type' => 'select',
                'label' => 'DÖF Açılış Kaynağı',
                'options' => 'Müşteri Şikayeti / Bildirimi, İç Tetkik / Denetim Bulgusu, Proses / Üretim Sapması, Tedarikçi Kalite Hatası, Laboratuvar Analiz Sapması',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'urun_grubu',
                'type' => 'select',
                'label' => 'İlgili Ürün Grubu',
                'options' => 'PET Levha (EVOH Çok Katmanlı), PET Preform, Plastik Yağ / İçecek Kapağı, PET Granül / Cips',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'parti_lot_no',
                'type' => 'text',
                'label' => 'Parti / Lot Numarası',
                'placeholder' => 'Örn: L-2026-9944',
                'required' => true,
                'width' => '4'
            ],
            [
                'id' => 'etkilenen_miktar_kg',
                'type' => 'number',
                'label' => 'Etkilenen Miktar (KG / Adet)',
                'placeholder' => 'Örn: 1250',
                'required' => true,
                'width' => '4'
            ],
            [
                'id' => 'acil_aksiyon_karari',
                'type' => 'select',
                'label' => 'Acil Karantina / Bloke Kararı',
                'options' => 'Karantinaya Alındı (Bloke), Yeniden İşleme (Rework), Hurdaya Ayırma, Şartlı Kabul / Risk Analizli Sevk',
                'required' => true,
                'width' => '4'
            ],
            [
                'id' => 'karantina_etiket_no',
                'type' => 'text',
                'label' => 'Karantina Bloke Etiket No',
                'placeholder' => 'Örn: BLOK-2026-081',
                'required' => false,
                'width' => '6'
            ],
            [
                'id' => 'uygunsuzluk_tanimi',
                'type' => 'textarea',
                'label' => 'Uygunsuzluk Detayı ve Etkisi',
                'placeholder' => 'Müşteride termoform denemesinde film kalınlık dalgalanması ve kenar yapışma hatası gözlenmiştir...',
                'required' => true,
                'width' => '12'
            ],

            [
                'id' => 'h_dof_kok_neden',
                'type' => 'header',
                'label' => '2. Kök Neden Analizi (5-Why Metodolojisi / Balık Kılçığı)',
                'description' => 'Sorumlu operasyon birimi tarafından 5-Why kök neden analizi doldurulur.',
                'width' => '12'
            ],
            [
                'id' => 'kok_neden_5_why',
                'type' => 'textarea',
                'label' => '5-Why (5 Neden) Analizi ve Temel Kök Neden',
                'placeholder' => "1. Neden? Film homojen gerilmedi (+70 µm sapma).\n2. Neden? Kalıp dudak ısıtıcısında lokal sıcaklık düştü.\n3. Neden? Termokupl soketinde temassızlık oldu.\n4. Neden? Vardiya öncesi profil sensörü kalibrasyonu atlandı.\n5. Kök Neden: Isıtma zonları için sesli PID tolerans alarmı tanımlanmamıştı.",
                'required' => false,
                'width' => '12'
            ],

            [
                'id' => 'h_dof_aksiyon',
                'type' => 'header',
                'label' => '3. Kalıcı Düzeltici Aksiyon Planı & Etkinlik Doğrulama',
                'description' => 'Kalıcı aksiyonlar, sorumlular ve Kalite Güvence kapatma onayı.',
                'width' => '12'
            ],
            [
                'id' => 'kalici_aksiyon_tanimi',
                'type' => 'textarea',
                'label' => 'Kalıcı Düzeltici / Önleyici Aksiyon',
                'placeholder' => 'Tüm hat ekstrüzyon kalıp zonlarına ±2°C limitli PID sesli alarm tanımlandı ve vardiya devir teslim kontrol listesine eklendi...',
                'required' => false,
                'width' => '12'
            ],
            [
                'id' => 'aksiyon_sorumlusu',
                'type' => 'text',
                'label' => 'Aksiyon Sorumlusu',
                'placeholder' => 'Örn: Hakan DEMİR (İşletme Şefi)',
                'required' => false,
                'width' => '6'
            ],
            [
                'id' => 'hedef_termin_tarihi',
                'type' => 'date',
                'label' => 'Aksiyon Hedef Tamamlanma Tarihi',
                'required' => false,
                'width' => '6'
            ],
            [
                'id' => 'etkinlik_dogrulama_sonucu',
                'type' => 'select',
                'label' => 'Kalite Güvence Etkinlik Doğrulama Sonucu',
                'options' => 'Faaliyet Başarılı - Sonraki 5 Partide Hata Tekrarlanmadı (DÖF Kapatıldı), Kısmen Başarılı (Ek Aksiyon Gerekli), Faaliyet Yetersiz',
                'required' => false,
                'width' => '12'
            ]
        ];

        $dofForm = FormTemplate::updateOrCreate(
            ['name' => 'Düzeltici ve Önleyici Faaliyet (DÖF / 8D) Formu'],
            [
                'description'   => 'ISO 9001 ve BRCGS standartlarına uygun DÖF uygunsuzluk, 5-Why kök neden analizi ve etkinlik doğrulama formu.',
                'schema'        => $dofFormSchema,
                'is_active'     => true,
                'document_no'   => 'FR-KAL-015',
                'publish_date'  => now(),
                'revision_no'   => '04',
                'revision_date' => now(),
                'page_no'       => 1,
                'created_by'    => $adminUser?->id,
            ]
        );

        $dofNodes = [
            [
                'id' => 'node_start',
                'type' => 'start',
                'position' => ['x' => 300, 'y' => 20],
                'data' => [
                    'label' => 'DÖF Açılışı & Uygunsuzluk Bildirimi',
                    'taskType' => 'start',
                    'description' => 'Kalite ekibi veya tetkikçi uygunsuzluğu sisteme girer.'
                ]
            ],
            [
                'id' => 'node_kok_neden',
                'type' => 'task',
                'position' => ['x' => 300, 'y' => 150],
                'data' => [
                    'label' => 'Kök Neden (5-Why) & Kalıcı Aksiyon Planı',
                    'customName' => 'Kök Neden (5-Why) & Kalıcı Aksiyon Planı',
                    'taskType' => 'form',
                    'assignType' => 'department',
                    'assignValue' => $levhaDept->id,
                    'deptManagerType' => 'assistant_manager',
                    'subFormId' => $dofForm->id,
                    'color' => '#8b5cf6',
                    'description' => 'İlgili birim 5-Why analizi yaparak kalıcı aksiyon planını hazırlar.'
                ]
            ],
            [
                'id' => 'node_amir_onay',
                'type' => 'decision',
                'position' => ['x' => 300, 'y' => 280],
                'data' => [
                    'label' => 'Birim Müdürü Aksiyon Onayı',
                    'customName' => 'Birim Müdürü Aksiyon Onayı',
                    'taskType' => 'approval',
                    'assignType' => 'department',
                    'assignValue' => $levhaDept->id,
                    'deptManagerType' => 'manager',
                    'color' => '#f59e0b',
                    'description' => 'Birim müdürü aksiyon planını ve termin tarihini onaylar.'
                ]
            ],
            [
                'id' => 'node_kalite_kapanis',
                'type' => 'decision',
                'position' => ['x' => 300, 'y' => 410],
                'data' => [
                    'label' => 'Kalite Güvence Etkinlik Doğrulama & Kapatma',
                    'customName' => 'Kalite Güvence Etkinlik Doğrulama & Kapatma',
                    'taskType' => 'approval',
                    'assignType' => 'user',
                    'assignedUserId' => $aykut->id,
                    'color' => '#10b981',
                    'description' => 'Kalite müdürü aksiyonun sahada çalıştığını teyit ederek DÖF dosyasını resmi olarak kapatır.'
                ]
            ],
            [
                'id' => 'node_end',
                'type' => 'end',
                'position' => ['x' => 300, 'y' => 540],
                'data' => ['label' => 'DÖF Başarıyla Kapatıldı']
            ],
            [
                'id' => 'node_end_rejected',
                'type' => 'end',
                'position' => ['x' => 550, 'y' => 280],
                'data' => ['label' => 'DÖF Aksiyonu Reddedildi']
            ]
        ];

        $dofEdges = [
            ['id' => 'e1', 'source' => 'node_start', 'target' => 'node_kok_neden'],
            ['id' => 'e2', 'source' => 'node_kok_neden', 'target' => 'node_amir_onay'],
            ['id' => 'e3', 'source' => 'node_amir_onay', 'target' => 'node_kalite_kapanis', 'sourceHandle' => 'approved', 'label' => 'Onaylandı'],
            ['id' => 'e4', 'source' => 'node_amir_onay', 'target' => 'node_end_rejected', 'sourceHandle' => 'rejected', 'label' => 'Reddedildi'],
            ['id' => 'e5', 'source' => 'node_kalite_kapanis', 'target' => 'node_end', 'sourceHandle' => 'approved', 'label' => 'Onaylandı'],
            ['id' => 'e6', 'source' => 'node_kalite_kapanis', 'target' => 'node_end_rejected', 'sourceHandle' => 'rejected', 'label' => 'Reddedildi'],
        ];

        $dofWorkflow = Workflow::updateOrCreate(
            ['name' => 'Düzeltici ve Önleyici Faaliyet (DÖF) Süreci'],
            [
                'description' => 'Kalite uygunsuzlukları, müşteri şikayetleri ve denetim bulguları için 5-Why kök neden analizi, kalıcı aksiyon ve etkinlik doğrulama süreci.',
                'category' => ['Kalite Güvence', 'Süreç İyileştirme'],
                'form_template_id' => $dofForm->id,
                'status' => 'active',
                'nodes' => $dofNodes,
                'edges' => $dofEdges,
                'version' => 1,
            ]
        );

        echo "   + DÖF İş Akışı Kaydedildi (ID: {$dofWorkflow->id})\n";


        // =========================================================================
        // 3. SÜREÇ: ACİL SATIN ALMA & MALZEME TALEP SÜRECİ
        // =========================================================================
        echo "👉 [3/3] Acil Satın Alma & Malzeme Talep Süreci kuruluyor...\n";

        $satFormSchema = [
            [
                'id' => 'h_sat_talep',
                'type' => 'header',
                'label' => '1. Satın Alma Talep Bilgileri',
                'description' => 'Talep eden birim sorumlusu tarafından doldurulur.',
                'width' => '12'
            ],
            [
                'id' => 'talep_turu',
                'type' => 'select',
                'label' => 'Talep Edilen Malzeme Türü',
                'options' => 'Yedek Parça & Rulman / Rezistans, Üretim Sarf Malzemesi & Yağ/Filtre, Hammadde / Katkı & Masterbatch, Teknik Servis & Taşeron Hizmeti, İş Güvenliği & Tesis Donanımı',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'masraf_merkezi',
                'type' => 'select',
                'label' => 'İlgili Masraf Merkezi',
                'options' => 'CC-1230 - LEVHA, CC-1010 - GENEL YÖNETİM, CC-1100 - PREFORM, CC-1150 - KAPAK, CC-1050 - BAKIM ONARIM',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'aciliyet_durumu',
                'type' => 'select',
                'label' => 'Aciliyet Seviyesi',
                'options' => 'Acil / Üretim Durma Riski, Yüksek Öncelikli, Normal Planlı Stok Tamamlama',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'istenilen_termin',
                'type' => 'date',
                'label' => 'İstenilen Teslim Tarihi',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'talep_gerekcesi',
                'type' => 'textarea',
                'label' => 'Talep Gerekçesi & Kullanım Yeri',
                'placeholder' => 'Hat 1 ekstrüzyon filtresi ve kalıp rezistansının ömrü dolmuş olup ani arızayı önlemek için kritik stok tamamlama talebidir...',
                'required' => true,
                'width' => '12'
            ],

            [
                'id' => 'h_sat_kalemler',
                'type' => 'header',
                'label' => '2. Talep Edilen Malzeme / Hizmet Kalemleri & Bütçe Tutarı',
                'description' => 'Tahmini tutar girilerek DoA yetki matrisi ve bütçe kontrolü hesaplanır.',
                'width' => '12'
            ],
            [
                'id' => 'genel_toplam_masraf',
                'type' => 'number',
                'label' => 'Tahmini Toplam Tutar (TL)',
                'placeholder' => 'Örn: 42500',
                'required' => true,
                'width' => '6'
            ],
            [
                'id' => 'doviz_cinsi',
                'type' => 'select',
                'label' => 'Para Birimi',
                'options' => 'TRY, EUR, USD',
                'required' => true,
                'width' => '6'
            ],

            [
                'id' => 'h_sat_satin_alma',
                'type' => 'header',
                'label' => '3. Satın Alma Teklif Değerlendirmesi & SAP Entegrasyonu',
                'description' => 'Satın Alma Sorumlusu tarafından teklifler toplandıktan sonra doldurulur.',
                'width' => '12'
            ],
            [
                'id' => 'secilen_tedarikci',
                'type' => 'text',
                'label' => 'Seçilen Tedarikçi Firma',
                'placeholder' => 'Örn: REZİSTEKNİK ENDÜSTRİYEL ISITMA A.Ş.',
                'required' => false,
                'width' => '6'
            ],
            [
                'id' => 'sap_sas_no',
                'type' => 'text',
                'label' => 'SAP Satın Alma Sipariş No (SAS)',
                'placeholder' => 'Örn: SAS-2026-44812',
                'required' => false,
                'width' => '6'
            ],
            [
                'id' => 'teklif_toplanan_firmalar',
                'type' => 'textarea',
                'label' => 'Alınan Teklifler ve Değerlendirme Notu',
                'placeholder' => '3 firmadan teklif alındı: Firma A (45.000 TL), Firma B (42.500 TL), Firma C (49.000 TL). En uygun termin ve fiyat avantajı sağlayan Firma B seçildi...',
                'required' => false,
                'width' => '12'
            ]
        ];

        $satForm = FormTemplate::updateOrCreate(
            ['name' => 'Malzeme ve Hizmet Satın Alma Talep Formu'],
            [
                'description'   => 'Fabrika üretimi, teknik sarf ve yedek parça alımları için onay ve SAP SAS takip formu.',
                'schema'        => $satFormSchema,
                'is_active'     => true,
                'document_no'   => 'FR-SAT-004',
                'publish_date'  => now(),
                'revision_no'   => '02',
                'revision_date' => now(),
                'page_no'       => 1,
                'created_by'    => $adminUser?->id,
            ]
        );

        $satNodes = [
            [
                'id' => 'node_start',
                'type' => 'start',
                'position' => ['x' => 300, 'y' => 20],
                'data' => [
                    'label' => 'Satın Alma Talebi Başlatma',
                    'taskType' => 'start',
                    'description' => 'Talep sahibi malzeme ihtiyaç formunu doldurarak akışı başlatır.'
                ]
            ],
            [
                'id' => 'node_amir_onay',
                'type' => 'decision',
                'position' => ['x' => 300, 'y' => 150],
                'data' => [
                    'label' => 'Birim Müdürü & Bütçe Onayı',
                    'customName' => 'Birim Müdürü & Bütçe Onayı',
                    'taskType' => 'approval',
                    'assignType' => 'department',
                    'assignValue' => $levhaDept->id,
                    'deptManagerType' => 'manager',
                    'color' => '#f59e0b',
                    'description' => 'Birim müdürü bütçe uygunluğunu ve alım gerekçesini onaylar.'
                ]
            ],
            [
                'id' => 'node_satin_alma',
                'type' => 'task',
                'position' => ['x' => 300, 'y' => 280],
                'data' => [
                    'label' => 'Satın Alma: Teklif Toplama & Tedarikçi Seçimi',
                    'customName' => 'Satın Alma: Teklif Toplama & Tedarikçi Seçimi',
                    'taskType' => 'form',
                    'assignType' => 'user',
                    'assignedUserId' => $serkan->id,
                    'subFormId' => $satForm->id,
                    'color' => '#3b82f6',
                    'description' => 'Satın alma ekibi teklifleri toplar, en uygun tedarikçiyi belirler ve SAP SAS kaydını girer.'
                ]
            ],
            [
                'id' => 'node_finans_onay',
                'type' => 'decision',
                'position' => ['x' => 300, 'y' => 410],
                'data' => [
                    'label' => 'Muhasebe & Finans Onayı',
                    'customName' => 'Muhasebe & Finans Onayı',
                    'taskType' => 'approval',
                    'assignType' => 'department',
                    'assignValue' => $levhaDept->id,
                    'deptManagerType' => 'manager',
                    'color' => '#10b981',
                    'description' => 'Finans yöneticisi sipariş bütçesini ve ödeme planını onaylar.'
                ]
            ],
            [
                'id' => 'node_end',
                'type' => 'end',
                'position' => ['x' => 300, 'y' => 540],
                'data' => ['label' => 'Satın Alma Siparişi Açıldı']
            ],
            [
                'id' => 'node_end_rejected',
                'type' => 'end',
                'position' => ['x' => 550, 'y' => 280],
                'data' => ['label' => 'Talep Reddedildi']
            ]
        ];

        $satEdges = [
            ['id' => 'e1', 'source' => 'node_start', 'target' => 'node_amir_onay'],
            ['id' => 'e2', 'source' => 'node_amir_onay', 'target' => 'node_satin_alma', 'sourceHandle' => 'approved', 'label' => 'Onaylandı'],
            ['id' => 'e3', 'source' => 'node_amir_onay', 'target' => 'node_end_rejected', 'sourceHandle' => 'rejected', 'label' => 'Reddedildi'],
            ['id' => 'e4', 'source' => 'node_satin_alma', 'target' => 'node_finans_onay'],
            ['id' => 'e5', 'source' => 'node_finans_onay', 'target' => 'node_end', 'sourceHandle' => 'approved', 'label' => 'Onaylandı'],
            ['id' => 'e6', 'source' => 'node_finans_onay', 'target' => 'node_end_rejected', 'sourceHandle' => 'rejected', 'label' => 'Reddedildi'],
        ];

        $satWorkflow = Workflow::updateOrCreate(
            ['name' => 'Acil Satın Alma & Malzeme Talep Süreci'],
            [
                'description' => 'Fabrika üretimi, teknik sarf ve yedek parça alımları için bütçe kontrollü onay, teklif değerlendirme ve SAP SAS takip süreci.',
                'category' => ['Satın Alma & Tedarik', 'Operasyon'],
                'form_template_id' => $satForm->id,
                'status' => 'active',
                'nodes' => $satNodes,
                'edges' => $satEdges,
                'version' => 1,
            ]
        );

        echo "   + Satın Alma İş Akışı Kaydedildi (ID: {$satWorkflow->id})\n";


        // =========================================================================
        // 4. CANLI NUMUNE SÜREÇLERİ VE GÖREVLERİN ÜRETİLMESİ (DEMO & TEST VERİSİ)
        // =========================================================================
        echo "👉 [4/4] Örnek canlı süreçler ve görevler üretiliyor...\n";

        // A) TPM Süreç Örneği
        $tpmInstance = ProcessInstance::where('workflow_id', $tpmWorkflow->id)->first();
        $tpmData = [
            'tesis_bolum' => 'Levha Üretim İşletmesi',
            'makine_hatti' => 'Betapak FFS 250 Thermoformer',
            'ariza_tipi' => 'Mekanik Arıza & Rulman/Dişli',
            'oncelik_derecesi' => 'Kritik / Hat Durdu (Acil)',
            'ariza_baslangic_zamani' => now()->subHours(3)->format('Y-m-d H:i'),
            'bildiren_operator' => 'Ahmet YILMAZ (Hat Operatörü)',
            'ariza_aciklamasi' => 'Makine ana tahrik ünitesinde aşırı sürtünme ve ses tespit edildi. Hat acil güvenlik duruşuna geçti.',
            'bakim_mudahale_baslangic' => now()->subHours(2)->format('Y-m-d H:i'),
            'bakim_mudahale_bitis' => now()->subMinutes(30)->format('Y-m-d H:i'),
            'net_durus_suresi_dk' => 90,
            'ariza_kok_nedeni' => 'Aşınma / Yıpranma & Yağlama Eksikliği',
            'mudahale_eden_teknisyen' => 'Serkan TELEK (Mekanik Bakım Şefi)',
            'yapilan_mudahale' => 'Tahrik milindeki deforme olmuş konik rulman söküldü, yuva temizlendi, yeni rulman preslendi ve yüksek sıcaklık gresi uygulandı.',
            'degisen_yedek_parcalar' => 'SKF 6208-2RS Rulman (1 Adet), NBR 40x62x7 Keçe (1 Adet)',
            'makine_test_durumu' => 'Test Edildi - Normal Hızda ve Yüksüz/Yüklü Çalışıyor',
            'test_kabul_notu' => 'Hat test ürünleri basılarak kontrol edildi, toleranslar uygun, üretim hattı tam kapasite devreye alındı.',
        ];
        if (!$tpmInstance) {
            $tpmInstance = ProcessInstance::create([
                'workflow_id' => $tpmWorkflow->id,
                'started_by' => $aykut->id,
                'status' => 'waiting',
                'current_node_id' => 'node_bakim_mudahale',
                'data' => $tpmData,
            ]);
        } else {
            $tpmInstance->update([
                'status' => 'waiting',
                'current_node_id' => 'node_bakim_mudahale',
                'data' => $tpmData,
            ]);
        }

        // TPM Görevleri: Tamamlanan Şef Onayı ve Bekleyen Bakım Görevi
        Task::updateOrCreate(
            ['process_instance_id' => $tpmInstance->id, 'node_id' => 'node_sefi_onay'],
            [
                'type' => 'approval',
                'status' => 'completed',
                'assigned_to' => $hakan->id,
                'completed_by' => $hakan->id,
                'comment' => 'Arıza sahada incelendi, duruş kritik, bakım ekibi acil yönlendirildi.',
                'completed_at' => now()->subHours(2),
            ]
        );

        Task::updateOrCreate(
            ['process_instance_id' => $tpmInstance->id, 'node_id' => 'node_bakim_mudahale'],
            [
                'type' => 'form',
                'status' => 'pending',
                'assigned_to' => $serkan->id,
                'created_at' => now()->subHours(2),
            ]
        );

        echo "   + TPM Süreci ve Görevleri Bağlandı (Process #{$tpmInstance->id})\n";


        // B) DÖF Süreç Örneği
        $dofInstance = ProcessInstance::where('workflow_id', $dofWorkflow->id)->first();
        $dofData = [
            'dof_kaynagi' => 'Müşteri Şikayeti / Bildirimi',
            'urun_grubu' => 'PET Levha (EVOH Çok Katmanlı)',
            'parti_lot_no' => 'L-2026-9944',
            'etkilenen_miktar_kg' => 1250,
            'acil_aksiyon_karari' => 'Karantinaya Alındı (Bloke)',
            'karantina_etiket_no' => 'BLOK-2026-081',
            'uygunsuzluk_tanimi' => 'Müşteride termoform denemesinde film kalınlık dalgalanması ve kenar yapışma hatası gözlenmiştir.',
            'kok_neden_5_why' => "1. Neden? Film homojen gerilmedi (+70 µm sapma).\n2. Neden? Kalıp dudak ısıtıcısında lokal sıcaklık düştü.\n3. Neden? Termokupl soketinde temassızlık oldu.\n4. Neden? Vardiya öncesi profil sensörü kalibrasyonu atlandı.\n5. Kök Neden: Isıtma zonları için sesli PID tolerans alarmı tanımlanmamıştı.",
            'kalici_aksiyon_tanimi' => 'Tüm hat ekstrüzyon kalıp zonlarına ±2°C limitli PID sesli alarm tanımlandı ve vardiya devir teslim kontrol listesine eklendi.',
            'aksiyon_sorumlusu' => 'Hakan DEMİR (İşletme Şefi)',
            'hedef_termin_tarihi' => now()->addDays(14)->format('Y-m-d'),
            'etkinlik_dogrulama_sonucu' => 'Faaliyet Başarılı - Sonraki 5 Partide Hata Tekrarlanmadı (DÖF Kapatıldı)',
        ];
        if (!$dofInstance) {
            $dofInstance = ProcessInstance::create([
                'workflow_id' => $dofWorkflow->id,
                'started_by' => $mustafa->id,
                'status' => 'waiting',
                'current_node_id' => 'node_amir_onay',
                'data' => $dofData,
            ]);
        } else {
            $dofInstance->update([
                'status' => 'waiting',
                'current_node_id' => 'node_amir_onay',
                'data' => $dofData,
            ]);
        }

        Task::updateOrCreate(
            ['process_instance_id' => $dofInstance->id, 'node_id' => 'node_kok_neden'],
            [
                'type' => 'form',
                'status' => 'completed',
                'assigned_to' => $hakan->id,
                'completed_by' => $hakan->id,
                'completed_at' => now()->subDay(),
            ]
        );

        Task::updateOrCreate(
            ['process_instance_id' => $dofInstance->id, 'node_id' => 'node_amir_onay'],
            [
                'type' => 'approval',
                'status' => 'pending',
                'assigned_to' => $aykut->id,
                'created_at' => now()->subDay(),
            ]
        );

        echo "   + DÖF Süreci ve Görevleri Bağlandı (Process #{$dofInstance->id})\n";


        // C) Acil Satın Alma Süreç Örneği
        $satInstance = ProcessInstance::where('workflow_id', $satWorkflow->id)->first();
        $satData = [
            'talep_turu' => 'Yedek Parça & Rulman / Rezistans',
            'masraf_merkezi' => 'CC-1230 - LEVHA',
            'aciliyet_durumu' => 'Acil / Üretim Durma Riski',
            'istenilen_termin' => now()->addDays(3)->format('Y-m-d'),
            'talep_gerekcesi' => 'Hat 1 ekstrüzyon filtresi ve kalıp rezistansının ömrü dolmuş olup ani arızayı önlemek için kritik stok tamamlama talebidir.',
            'genel_toplam_masraf' => 42500,
            'doviz_cinsi' => 'TRY',
            'secilen_tedarikci' => 'REZİSTEKNİK ENDÜSTRİYEL ISITMA A.Ş.',
            'sap_sas_no' => 'SAS-2026-44812',
            'teklif_toplanan_firmalar' => '3 firmadan teklif alındı: Firma A (45.000 TL), Firma B (42.500 TL), Firma C (49.000 TL). En uygun termin ve fiyat avantajı sağlayan Firma B seçildi.',
        ];
        if (!$satInstance) {
            $satInstance = ProcessInstance::create([
                'workflow_id' => $satWorkflow->id,
                'started_by' => $serkan->id,
                'status' => 'waiting',
                'current_node_id' => 'node_amir_onay',
                'data' => $satData,
            ]);
        } else {
            $satInstance->update([
                'status' => 'waiting',
                'current_node_id' => 'node_amir_onay',
                'data' => $satData,
            ]);
        }

        Task::updateOrCreate(
            ['process_instance_id' => $satInstance->id, 'node_id' => 'node_amir_onay'],
            [
                'type' => 'approval',
                'status' => 'pending',
                'assigned_to' => $aykut->id,
                'created_at' => now()->subHours(5),
            ]
        );

        echo "   + Satın Alma Süreci ve Görevleri Bağlandı (Process #{$satInstance->id})\n";

        echo "\n====================================================================\n";
        echo "✨ 3. SÜTUN TÜM OPERASYONEL SÜREÇLER BAŞARIYLA KURULDU!\n";
        echo "====================================================================\n";
    }
}
