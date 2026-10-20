# KÖKSAN İŞ AKIŞI & SÜREÇ YÖNETİM SİSTEMİ (WORKFLOW) - HAFIZA VE REHBER

Bu belge, yapay zeka asistanı (Antigravity) için projenin genel mimarisini, iş kurallarını ve geliştirilen özel süreçleri kalıcı hafızada tutmak amacıyla hazırlanmıştır. Bilgisayar veya oturum kapansa bile yeni sohbetlerde bu dosya otomatik olarak okunur.

---

## 1. Proje Özeti ve Teknoloji Yığını
- **Firma / Proje:** Köksan Pet ve Plastik Ambalaj Sanayi A.Ş. - Kurumsal İş Akışı & BPM Platformu
- **Backend:** Laravel 11 / PHP 8.2+
- **Frontend:** Vue 3 (Composition API) + Inertia.js + TailwindCSS / Custom CSS
- **Veritabanı:** MySQL / MariaDB (XAMPP ortamı `c:\xampp\htdocs\workflow`)
- **PDF & Raporlama:** DOMPDF (`CertificateGenerator.php`, `TaskController.php`)
- **Giriş Bilgileri (Test):** `admin@test.com` / `password`

---

## 2. Özel İş Akışı: "Levha Numune Talep ve Üretim Süreci" (Workflow #7)
Aykut Bey'in ilettiği e-posta ve ekindeki **ALMER.xlsx** resmi form spesifikasyonlarına göre uçtan uca dijitalleştirilen süreçtir.

### Temel Bileşenler:
1. **Dinamik Form Şablonu (FR-LVH-042 - Form Template ID: 4):**
   - 22 alanlık resmi form.
   - Müşteri Cari Bilgileri (Cari Kart): `musteri_adi` (Örn: ALMER LTD), `sektor`, `urun_grubu` (PET/EVOH vb.), `ulke_bolge` (Bulgaristan vb.), `faaliyet_alani`.
   - Teknik Sipariş Detayları: `genislik` (422 mm ± 1 mm), `kalinlik_mikron` (550+50 µm), `renk` (Şeffaf), `laminasyon_turu` (EVOH/SEAL), `masura_capi` (3'' 77mm), `rulo_ozellikleri`, `yuzey_islemi` (SİLİKON), `silikon_orani` (7.5), `kullanim_amaci` (Betapak FFS termoform makinesinde peynir ambalajı).
   - İşletme Termin Alanları: `isletme_karari`, `termin_araligi`, `isletme_notu`.
   - Kalite Analiz Sonuçları (CoA): `parti_no`, `uretim_tarihi`, `gerceklesen_genislik`, `gerceklesen_mikron`, `yogunluk`, `cekme_dayanimi`, `kopma_uzamasi`, `korona_dyne`, `analiz_gorunum`, `kalite_aciklamasi`.

2. **İş Akışı Tasarımı (Workflow ID: 7):**
   - **Kategori:** Sadece `["Numune Talebi"]` (Katalogda `/processes` altında mükerrer görünmemesi için tek kategori olarak tanımlıdır).
   - `node_start` $\rightarrow$ Satışçı talebi başlatır.
   - `node_isletme_onay` $\rightarrow$ LEVHA İşletme Kabul ve Termin Onayı. **Kritik Kural:** `requireTermin: true`. İşletme kabul ederse termin aralığı girmeden onaya izin verilmez!
   - `node_uretim_analiz` $\rightarrow$ Numune üretimi tamamlandıktan sonra kalite/laboratuvar sonuçlarının işlendiği form görevi.
   - `node_sevk_bildirim` $\rightarrow$ Satışçıya numunenin sevk edildiği ve Analiz Sertifikasının hazır olduğu bildirimi.
   - `node_follow_up` $\rightarrow$ 15 gün sonraki otomatik numune yolculuğu ve sipariş takibi.
   - `node_end` / `node_end_rejected` $\rightarrow$ Başarılı tamamlanma veya gerekçeli ret ile sonlanma.

3. **Otomatik Analiz Sertifikası (CoA PDF) - `app/Services/CertificateGenerator.php`:**
   - Süreç takip ekranında (`/processes/{id}/tracker`) veya görev detayında tek tıkla indirilir.
   - Müşterinin istediği toleranslar ile laboratuvarın ölçtüğü gerçekleşen değerleri karşılaştırmalı olarak antetli resmi Köksan formatında basar.

4. **15 Günlük Süreç Takibi (Follow-Up Motoru) & SAP Entegrasyonu:**
   - **Model & Servis:** `App\Models\FollowUp`, `App\Services\FollowUpService`, `App\Services\SapIntegrationService`
   - **Ekranlar:** `/follow-ups` (Tüm takipler, arama, filtreleme, dönüşüm oranı istatistikleri) ve `/tasks` (vadesi gelen takipler).
   - **Yanıt:** Satışçı `converted` (Siparişe Döndü) veya `not_converted` seçer; SAP Sipariş Numarasını (`SAP-2026-99441`) ve müşteri geri bildirimini işler.

5. **Hiyerarşi ve Makine Tanımları (`/admin/hierarchy`) & Hedefli Amir Atama:**
   - `Levha Üretim İşletmesi` birimi altında `Betapak FFS 250 Thermoformer` makinesi ve `Levha Ekstrüzyon Hattı 1` düğümleri aktiftir.
   - **Kademeli ve Kişi Bazlı Hedefli Atama (`assignType: department`):** İşletmelerde birden fazla müdür, müdür yardımcısı veya vardiya şefi olabileceği için; onay ve form görevlerinde departman seçildikten sonra hangi kademenin (`manager`: Departman Müdürü, `assistant_manager`: Müdür Yrd., `chief`: İşletme Şefi) veya isimle seçilen doğrudan hangi personelin (`specific_user`: `assignedUserId`) atanacağı belirlenir. Görev ve e-posta bildirimi tüm departmana değil, yalnızca seçilen tek bir yetkiliye iletilir (`HierarchyResolverService.php`, `Designer.vue`, `BaseNode.vue`).

---

## 3. Doğrulama ve Test Komutları
- **Büyük Uçtan Uca Test Suite'i (Tüm Butonlar ve Senaryolar):** `php scratch/run_full_system_scenarios.php` (16 test adımı: Mutlu Yol, Revizyon/Geri Gönder, Gerekçeli Ret, Undo/Geri Al, İptal Et, Dış Görev, Kalite CoA PDF, Seyahat İzin PDF, Follow-Up).
- **Uçtan Uca Entegrasyon Testi (Levha):** `php scratch/test_levha_e2e.php` (8 test aşaması, veritabanını kirletmeden transaction rollback ile tam doğrulama yapar).
- **Seeder:** `php artisan db:seed --class=LevhaCompleteWorkflowSeeder`

---

## 4. Özel İş Akışı: "Dış Görev Süreci" ve "Harcırah Masraf Süreci" (Workflow #9 & #10)
Yönetim el yazısı görev notuna göre uçtan uca dijitalleştirilen entegre dış görev ve harcırah/masraf kapama süreçleridir.

### Temel Bileşenler:
1. **Dinamik Form Şablonları:**
   - **FR-IDR-008 (Form Template ID: 5) - Dış Görev Talep ve Seyahat Formu:**
     - Görev Türleri: `Servis Amaçlı (Satış Sonrası Teknik Destek)`, `Fuar / Etkinlik Katılımı`, `Müşteri / Firma Ziyareti (Satış & Teknik)`, `Diğer Kurumsal Dış Görev`.
     - Amaç, Gidilecek Firma, Lokasyon, Başlangıç/Bitiş Tarihi, Ulaşım Tercihi (Şirket Aracı / Kiralık Araç / Uçak), Konaklama İhtiyacı, Talep Edilen Harcırah/Avans, Katılacak Personel Listesi.
   - **FR-IK-016 (Form Template ID: 6) - Seyahat ve Ziyaret Değerlendirme Raporu:**
     - Gerçekleşen Ziyaret Özeti, Görüşülen Yetkililer, Servis/Fuar/Ziyaret Çıktıları, Aksiyon Maddeleri, Takip Sorumlusu.
   - **FR-MUH-021 (Form Template ID: 7) - Harcırah ve Seyahat Harcama Masraf Formu:**
     - Alınan Avans, Belgeli Masraflar (Fatura/Fiş), Belgesiz Harcamalar (Harcırah/Yolluk), Masraf Kalemleri Tablosu, İade / Ek Talep Tutarı, Fatura/Fiş Evrak Yükleme.

2. **Dış Görev Süreci Tasarımı (Workflow ID: 9):**
   - `node_talep` $\rightarrow$ Görevli çalışan (örn: Serkan Telek, Gülnur Hn) talebi doldurur.
   - `node_ilk_amir` $\rightarrow$ 1. Onay Makamı (İlk Amir Onayı).
   - `node_ust_amir` $\rightarrow$ 2. Onay Makamı (Birim Müdürü / Üst Amir Onayı).
   - `node_gmy_onay` $\rightarrow$ 3. Onay Makamı (Opsiyonel GMY / CEO Onayı - Bütçe ve üst düzey onay).
   - `node_idari_isler` $\rightarrow$ İdari İşler Görevi (Araç Tahsisi, Uçak Bileti & PNR, Otel Rezervasyonu).
   - `node_mali_avans` $\rightarrow$ Muhasebe & Finans Görevi (Harcırah ve Yol Avansı Ödemesi & Dekont).
   - `node_seyahat_raporu` $\rightarrow$ Seyahat Dönüşü Değerlendirme Raporunun doldurulması.
   - `node_tamamlandi` $\rightarrow$ Sürecin başarıyla tamamlanması.

3. **Harcırah ve Seyahat Masraf Kapama Süreci [Ayrı Süreç] (Workflow ID: 10):**
   - El yazısı nottaki Madde 6: Harcırah ve Masraf Kapama Formu ayrı bir süreç olarak tasarlanmıştır.
   - Seyahat takip ekranından tek tıkla (`[💳 Harcırah & Masraf Kapama Formunu Başlat]`) doğrudan başlatılabilir.
   - `node_masraf_beyan` $\rightarrow$ `node_amir_masraf_onay` $\rightarrow$ `node_muhasebe_mahsup` $\rightarrow$ `node_masraf_tamamlandi`.

4. **Resmi Dış Görevlendirme ve Seyahat İzin Belgesi (PDF) - `app/Services/TravelOrderGenerator.php`:**
   - Köksan antetli, kurumsal barkodlu, QR kodlu, görev emri ve polis/havaalanı/güvenlik kontrol noktaları için resmi izin metnini içeren PDF dökümü.
   - `/processes/{id}/travel-order` rotası ve Tracker ekranından tek tıkla indirilir.

5. **Kullanıcılar ve Roller:**
   - `idari.isler@koksan.com` (İdari İşler Sorumlusu - Araç & Otel Rezervasyonu)
   - `muhasebe@koksan.com` (Muhasebe & Mali İşler Sorumlusu - Avans & Mahsup)
   - `gmy@koksan.com` (Genel Müdür Yardımcısı / CEO - Üst Yönetim Onayı)
   - Rol Bazlı Onay Makamları: `Amir` (İlk Amir), `Müdür` (Departman Müdürü)

6. **Doğrulama Komutları:**
   - `php scratch/test_dis_gorev_e2e.php` (Tüm 11 adım uçtan uca backend testi).
   - `& "C:\node\node-v24.18.0-win-x64\node.exe" scratch/verify_dis_gorev_browser.mjs` (Tüm 10 ekran uçtan uca tarayıcı ve UI testi, 0 JS hatası).
   - `php scratch/print_dis_gorev_sql_proof.php` (Canlı SQL veritabanı denetim izi çıktısı).
   - `php artisan db:seed --class=DisGorevWorkflowSeeder`

---

## 5. Özel Sistem Modülü: "Dinamik E-Posta Yönetim Paneli & Mail Rule Engine"
Test aşamalarında dış kullanıcılara yanlışlıkla e-posta gitmesini önleyen, global kill-switch, test modu sandbox yönlendirmesi ve mesai/zaman pencereli kural motorudur.

### Temel Bileşenler:
1. **Veritabanı Katmanı (`mail_settings`, `mail_events`, `mail_templates`, `mail_rules`):**
   - Modeller: `MailSetting`, `MailEvent`, `MailTemplate`, `MailRule`.
   - Seeder: `MailSystemSeeder` (`php artisan db:seed --class=MailSystemSeeder`).
2. **Merkezi Güvenlik Kalkanı (`App\Listeners\MailGuardListener`):**
   - Laravel `MessageSending` olayını dinler.
   - Global Kill Switch açıkken e-posta çıkışını `return false` ile anında durdurur.
   - Olay tespiti: `X-Mail-Event` başlığı, bildirim tipi veya konu eşleştirmesi ile 4 katmanlı tespit.
   - Kural denetimi: `allowed_days` ve `allowed_time_start - allowed_time_end` mesai saatleri penceresi (Europe/Istanbul).
   - Test Modu Override: Gerçek alıcıları (`To`, `Cc`, `Bcc`) temizleyip `test_recipient_email` adresine yönlendirir; konuya `[TEST - To: {orijinal_alicilar}]` ekler.
3. **Backend & Arayüz:**
   - Controller: `App\Http\Controllers\Admin\MailSettingsController` (`/admin/mail-settings`).
   - Arayüz: `resources/js/Pages/Admin/MailSettings/Index.vue`.
   - Menü: `AuthenticatedLayout.vue` içinde `⚙️ Sistem Yönetimi -> 📧 E-Posta Yönetimi`.
4. **Test & Doğrulama Komutu:**
   - `php artisan mail:test-guard --event=task_assigned --to=serkan.telek@koksan.com`

---

## 6. Özel İş Akışı Özellikleri: "Revizyon Döngüsü, Zorunlu Açıklama & Çoklu Termin Seçicisi"
- **Zorunlu Gerekçe:** Ret ve Revizyon işlemlerinde gerekçe girilmesi hem frontend (Show.vue) hem de backend (TaskController.php) tarafında mecburidir.
- **Çoklu Termin Aralığı (Show.vue):** İşletme kabul aşamasındaki termin alanı; takvim seçicili başlangıç/bitiş, hızlı presetler (+7, +14, +21, +30 gün), çoklu parti ekleme ve takvim günü hesabı ile zenginleştirildi.
- **Satışçı Revizyon Deneyimi (Ahmet Aslan):** İşletme revize istediğinde talep sahibine 'onay' olarak değil, 'revizyon/düzeltme' olarak atanır (`is_revision: true`). Form önceki girilen verilerle düzenlenebilir açılır ve amirin revizyon notu kart olarak gösterilir.
- **Test Kılavuzu:** Proje kök dizininde `TEST_KILAVUZU.md` dosyasında tüm kullanıcı şifreleri ve adım adım test yönergeleri kalıcı olarak kayıtlıdır.

---

## 7. Kanıt Temelli Doğrulama Kuralı (Proof-Based Verification)
Asistan (Antigravity), kullanıcıya "tamamlandı", "sorunsuz çalışıyor" veya "her şey kusursuz" demeden önce aşağıdaki 3 kanıtı ZORUNLU olarak sunmalıdır:
1. **Görsel Kanıt:** Tarayıcıdan çekilmiş nihai ekran görüntüsü (Browser subagent veya screenshot).
2. **Network/Konsol Kanıtı:** Sayfada hiçbir kırmızı JavaScript hatasının (0 uncaught errors) olmaması.
3. **Veritabanı Kanıtı:** İlgili tablodaki (`tasks`, `processes`, `form_submissions` vb.) kaydın değiştiğini gösteren canlı SQL çıktısı.
Bu 3 şart sağlanmadan sözlü güvence verilmesi KESİNLİKLE YASAKTIR.

---

## 8. Özel UX Modülü: "Akıllı Görev Odaklayıcı & Smart Spotlight"
Fabrika sahasındaki teknolojiye mesafeli personeller (operatörler, laborantlar, vardiya şefleri) için tasarlanmış evrensel görsel rehberlik motorudur.
- **Bölümleme & Tamamlanma Tespiti (`FormRenderer.vue`):** Form elemanlarını başlıklara (`header`) göre dinamik gruplar. Önceki adımlardan dolu gelen bölümleri tespit edip `🔒 Önceki Adım Verisi (Salt Okunur)` olarak kilitler.
- **Canlı Yeşil Nefes Alan Işık (Breathing Emerald Glow):** Kullanıcının doldurması gereken hedef bölüm (`✨ SİZİN DOLDURMANIZ GEREKEN BÖLÜM`) `ring-4 ring-emerald-500/20` ve yeşil gradyan ile vurgulanır.
- **Otomatik Odaklama ve Kaydırma (`scrollToActiveFocus`):** Görev açıldığında sayfa doğrudan aktif çalışma alanına (`active-task-focus`) yumuşakça kayar (`smooth scroll`).
- **Üst Adım Rehberi Panosu (`Show.vue`):** Ekranın en üstünde modern cam efektli koyu lacivert/zümrüt gradyanlı bir yönlendirme panosu yer alır. Kullanıcının kim olduğunu, rolünü, ne yapması gerektiğini açıklar ve tek tıkla (`🎯 Hedef Alana Odaklan`) doğrudan ilgili bölüme uçurur.
- **Onay Görevlerinde Termin Odaklama:** Onay görevlerinde (Aykut Çelik vb.) termin ve kabul kutusu otomatik olarak yeşil spotlight ile parlar.

---

## 9. Özel Onay Motoru: "Çoklu Yetkili & Konsensüs Modülü (Multi-Approver & Approval Policies)"
Büyük ölçekli kurumsal yapılarda bir onay veya inceleme adımının birden fazla personele, vardiya ekibine veya departman kadrosuna hedeflenmesini sağlayan hibrit onay mimarisidir.
- **Hedef Çözümleyici (`HierarchyResolverService::resolveTargetUsers`):**
  - **Kadro Bazlı Çoklu Atama:**
    - `all_managers`: Departmandaki tüm müdür ve müdür yardımcıları (Çoklu).
    - `all_chiefs`: Departmandaki tüm işletme şefleri / amirleri (Çoklu).
  - **Doğrudan Çoklu Kişi Seçimi:**
    - `specific_users`: Departmandan seçilen birden fazla yetkili (`assignedUserIds`).
    - `user` tipi atamalarda birden fazla kullanıcının eş zamanlı atanması (`assignedUserIds`).
- **Görev Üretimi (`TaskManager::createTasks`):**
  - Çözümlenen her yetkili için bağımsız bir `Task` kaydı, bildirim ve e-posta tetiklenir.
- **Onay Politikası ve Karar Motoru (`TaskController.php`):**
  - **⚡ İlk Onaylayan Yeterli (`approvalPolicy: 'any'` - Havuz / Vardiya Modeli):** Atanan yetkililerden herhangi biri onayladığında, diğer kişilerin bekleyen görevleri otomatik olarak `cancelled` yapılır ve süreç anında bir sonraki adıma geçer.
  - **🤝 Tümünün Onayı Şart (`approvalPolicy: 'all'` - Konsensüs Modeli):** Atanan her bir yetkilinin onay vermesi zorunludur. İlk onaylar süreci `waiting` durumunda tutar ("Onayınız kaydedildi, diğer yetkililerin işlemi bekleniyor"). Tüm görevler tamamlandığında süreç otomatik ilerler.
  - **Gerekçeli Ret & Revizyon:** Hangi yetkili ret veya revize verirse versin, süreç ilgili hata/revizyon yoluna yönlendirilir ve kalan görevler iptal edilir.
- **Tasarımcı & Süreç Takip Deneyimi (`Designer.vue`, `BaseNode.vue`, `Tracker.vue`):**
  - Akış Tasarımcısında radyo kartlı `approvalPolicy` seçicisi ve çoklu kullanıcı listeleri.
  - BPMN tuvalinde `🏢 LEVHA · 2 Yetkili · 🤝 Konsensüs` rozetleri.
  - Süreç takip ekranında `🤝 Konsensüs: Aykut ÇELİK, Hakan DEMİR` ve gerçek zamanlı onay yol haritası.

---

## 10. Kurumsal Termin, SLA & Eskalasyon Yönetim Motoru (Fortune 500 Standardı: SAP, Siemens, BASF)
Global endüstri devlerinin (SAP S/4HANA, Siemens Opcenter, BASF Verbund) taahhüt (commit date) ve SLA yönetim mimarisine uygun olarak geliştirilen proaktif termin ve gecikme yönetim sistemidir.

### Temel Bileşenler:
1. **Veritabanı Katmanı & Revizyon Takibi (`termin_revisions`):**
   - Model: `App\Models\TerminRevision`
   - Alanlar: `process_instance_id`, `requested_by`, `previous_termin`, `new_termin`, `new_target_date`, `reason_category`, `reason_note`, `status`, `approved_by`, `approved_at`.
   - **Kök Neden Kategorileri (Fortune-500 Standart Kök Neden Matrisi):**
     - `raw_material`: Hammadde / Katkı Tedarik Gecikmesi
     - `machine_breakdown`: Ekstrüzyon / Makine Hattı Arızası & Bakım
     - `mold_change`: Kalıp / Bıçak Değişimi & Ayar Süresi
     - `quality_retest`: Kalite / Laboratuvar Tekrar Testi & Şartlandırma
     - `customer_change`: Müşteri / Satış Kaynaklı Spesifikasyon Değişikliği
     - `other`: Diğer Operasyonel Gecikme

2. **Merkezi SLA & OTIF Hesaplama Motoru (`App\Services\TerminSlaService`):**
   - **Trafik Işıkları & Durum Matrisi:**
     - 🟢 `on_time`: Kalan süre > 48 saat (Hedefte, normal akış)
     - 🟡 `warning`: Kalan süre <= 48 saat (Termin yaklaşıyor, sarı alarm)
     - 🔴 `overdue`: Termin aşıldı 1-3 gün (Gecikmede, operasyonel eskalasyon)
     - 🚨 `critical`: Termin aşıldı > 3 gün (Kritik gecikme, üst yönetim kırmızı eskalasyonu)
     - 🏆 `completed_on_time` & ⚠️ `completed_delayed`: OTIF (On-Time In-Full) analitiği.
   - **Dinamik Tarih Ayrıştırıcı (`extractTargetDate`):** `20.10.2026 - 27.10.2026`, `27.10.2026`, `2026-10-27` gibi tekil veya aralık metinlerini otomatik algılayıp nihai taahhüt gününü hesaplar.
   - **Resmi Erteleme / Revizyon Metodu (`rescheduleTermin`):** Süreç terminini günceller, revizyon logunu kaydeder, talep sahibi satışçıya ve bekleyen personele anında sistem içi ve e-posta bildirimi iletir.

3. **Otomatik Eskalasyon Motoru (Cron / Scheduler):**
   - Konsol Komutu: `php artisan termin:check-sla` (`App\Console\Commands\CheckTerminSlaCommand`)
   - 48 saatten az kalan süreçler için amir ve operatörlere `termin_warning` uyarısı gönderir.
   - Süresi dolan veya aşan süreçlerde üst yönetime `termin_overdue_escalation` kırmızı alarmı fırlatır.

4. **Kullanıcı Deneyimi (UI/UX - Tracker & Tasks):**
   - **SLA Hero Monitor Card (`Tracker.vue`):** Süreç takip ekranının en tepesinde canlı nefes alan trafik ışığı rozeti, taahhüt edilen termin, kalan/geciken gün sayacı ve tek tıkla `[🔄 Termini Ertele / Revize Et]` modal butonu.
   - **Termin & SLA Geçmişi Sekmesi (`Tracker.vue`):** Süreçteki tüm termin revizyonlarını numarası, yetkilisi, kök neden kategorisi, gerekçe notu ve üstü çizili eski/yeni tarihleriyle tam denetim (audit trail) olarak listeler.
   - **Inbox Görev Rozeti (`Tasks/Index.vue`):** Operatör ve şeflerin gelen kutusunda hangi numunenin ne zamana yetişmesi gerektiğini doğrudan satırda `⏱️ Termin: 06.10.2026 - 13.10.2026` etiketiyle gösterir.

---

## 11. Evrensel Numune Yaşam Döngüsü & Satış Dönüşüm Motoru (Fortune-500 Closed-Loop Sample Pipeline)
Köksan'ın tüm ürün gruplarında (Preform, Kapak, Levha, Reçine/Cips, Stretch vb.) ve 66+ departmanında geçerli olan, numuneyi bir maliyet değil bir satış yatırımı olarak takip eden kapalı devre dönüşüm sistemidir.

### Temel Prensipler ve Mimari:
1. **Evrensellik ve Departman Bağımsızlığı:**
   - Levha birimi bir pilot çalışma olarak tasarlanmış olup; tüm formlar, termin motoru, onay akışları ve takip mekanizmaları **PREFORM (Şişe Kalıpları/Boyunlar), KAPAK (İçecek/Yağ Kapakları), RESİN (PET Granül/Cips)** ve diğer tüm fabrikalara %100 uyumludur.
   - Departmanlar ve hiyerarşi, `/admin/departments` üzerinden doğrudan yönetilebildiği gibi, Köksan Merkezi Yönetim Sistemi (`https://kys.koksan.com/merkezi_yonetim_sistemi/admin/departments`) ile anında senkronize edilebilir (`MysApiService.php`, `SyncDepartmentsAction.php`).

2. **Numune Yaşam Döngüsü (Sample Lifecycle Stages):**
   - **Faz 1: Talep & Spesifikasyon:** Ürün grubu (Preform gramajı/PCO, Kapak tipi, Levha mikron/silikon), müşteri cari kartı ve makine parametreleri.
   - **Faz 2: Termin SLA & Üretim & Analiz Sertifikası (CoA):** İlgili işletme bağlayıcı termin verir, üretim yapılır, laboratuvar toleransları ölçer ve resmi CoA PDF üretilerek numune sevk edilir.
   - **Faz 3: Müşteri Deneme Kuluçkası (15-30 Gün):** Müşterinin kendi enjeksiyon/şişirme/dolum/termoform makinelerinde deneme yapması için planlanan kuluçka sayacı başlar.
   - **Faz 4: Otomatik Takip & Bildirim Motoru (`workflow:process-follow-ups`):** Süre dolduğunda satış temsilcisine otomatik bildirim ve e-posta tetiklenir (`FollowUpService.php`).
   - **Faz 5: Karar & SAP S/4HANA Entegrasyonu:**
     - 🟢 **Siparişe Dönüştü (Converted):** SAP Satış Siparişi Numarası (`SAP-2026-XXXXX`), yıllık hacim/tonaj potansiyeli girilir, SAP S/4HANA ile API üzerinden senkronize olunur (`SapIntegrationService.php`).
     - 🟡 **Görüşmeler / Deneme Devam Ediyor (Retrial / In Negotiation):** Müşteri yeni parti numune veya revizyon ister, takip tarihi ileriye ertelenir.
     - 🔴 **Siparişe Dönüşmedi (Lost Reason Analysis):** Kayıp nedeni Fortune-500 standardında kategorize edilir (Fiyat Yüksek, Teknik Uyumsuzluk, Rakip Tercihi, Termin Gecikmesi, İptal).

3. **Yönetim Panelleri & Analitik:**
   - `/follow-ups`: Tüm numune takiplerinin filtreleme (Tümü, Bekleyenler, Siparişe Dönenler, Dönmeyenler), departman/iş akışı seçicisi ve dönüşüm oranı analitiği (% Conversion Rate).
   - `/follow-ups/{id}`: Numune teknik detayları, müşteri bilgisi, Analiz Sertifikası PDF bağlantısı ve tek tıkla SAP sipariş senkronizasyonu.

---

## 12. Fortune-500 Enterprise BPM Faz 1: Dinamik Tablo/Grid, Formül Motoru & Canlı Denetim İzi (Audit Diff)
Siemens Opcenter ve SAP Signavio standartlarında veri tablosu ve alan bazlı değişiklik takip motorudur.
1. **Dinamik Grid & Tekrarlayan Satırlar (Repeater Grid & Live Formulas):**
   - `FormRenderer.vue`: `table_grid` tipindeki alanlar için dinamik satır ekleme/silme, kolon bazlı tip kontrolü ve canlı `calculateTotals()` formül değerlendirmesi.
2. **Alan Bazlı Git-Stili Değişiklik Günlüğü (Field-Level Audit Diff Tracker):**
   - Model & Servis: `App\Models\ProcessFieldAudit`, `App\Services\ProcessAuditService`.
   - Süreç takip ekranında (`Tracker.vue`) ve görev detayında (`Show.vue`) her form güncellemesini, eski/yeni değerleri üstü çizili kırmızı / yeşil Git-stili kartlar olarak gösterir.
3. **Yönetici Müdahale Kokpiti (Process Admin Cockpit):**
   - Admin ve yetkili süpervizörler için `/processes/{id}/admin-reassign`, `/admin-jump`, `/admin-force` uç noktaları ve `Tracker.vue` üzerinde doğrudan canlı işlem modal kokpiti.

---

## 13. Fortune-500 Enterprise BPM Faz 2: Görevler Ayrılığı (SoD), Finansal Yetki Matrisi (DoA) & Kapsamlı Vekalet
Kurumsal iç denetim, SOX ve Fortune-500 uyumluluk standartlarına uygun kurumsal karar motorudur.
1. **Görevler Ayrılığı & 4-Göz İlkesi (Separation of Duties - SoD / 4-Eyes Principle):**
   - Bir onay görevi sürecin başlatıcısına denk geldiğinde (`(int)$assignedTo === (int)$instance->started_by`), `TaskManager::createTask()` görevi talep sahibine DEĞİL, doğrudan talep sahibinin bağlı olduğu üst amirine (`$starter->manager_id`) otomatik eskale eder.
   - Durum `process_field_audits` tablosuna `sod_escalation` olarak tescil edilir ve Tracker'da `🛡️ 4-Göz İlkesi (SoD Eskalasyon)` rozetiyle gösterilir.
   - İkinci savunma hattı olarak `TaskController::update()` talep sahibinin kendi onay görevini onaylamasını HTTP seviyesinde engeller.
2. **Finansal Yetki Matrisi & Tutar Eşikleri (Delegation of Authority - DoA Matrix):**
   - Servis: `App\Services\DoaMatrixService`.
   - Standart Eşikler: İlk Amir $\le 1.000$ EUR, Departman Müdürü $\le 5.000$ EUR, GMY $\le 25.000$ EUR, CEO $> 25.000$ EUR.
   - Süreç verisindeki parasal tutarları (`talep_edilen_avans`, `genel_toplam_masraf`, vb.) Türk Lirası ve döviz formatlarını ayrıştırarak değerlendirir.
   - `ProcessEngine::advance()` esnasında eğer talep tutarı onay eşiğinin altındaysa (Örn: $750$ EUR avans için GMY onayı), adım otomatik olarak bypass edilir (`doa_bypass`), `process_field_audits` tablosuna işlenir ve Tracker'da `⚡ DoA Finansal Yetki Matrisi` rozetiyle gösterilir.
3. **Kapsamlı Süreli ve Kategori/Akış Bazlı Vekalet Motoru (Scoped Delegation Engine):**
   - `delegations` tablosu `scope_type` ('all', 'category', 'workflow'), `scope_value` ve `reason` alanlarıyla genişletildi.
   - `DelegationService::resolveAssignee()` öncelik sırasına göre:
     1. İlgili akışa özel vekalet (`workflow`),
     2. İlgili kategoriye özel vekalet (`category`),
     3. Genel vekalet (`all` / genel) eşleşmesini arar; uyuşmayan süreçlerde asıl kullanıcıyı korur.
   - Arayüz (`Tasks/Index.vue`): `✈️ Vekalet Yönetimi` paneli üzerinden kapsam butonları, dinamik kategori/akış seçicisi, vekalet gerekçesi ve rozetli kartlar sunar.

---

## 14. Fortune-500 Enterprise BPM Faz 3: BPMN 2.0 Paralel Ağ Geçitleri (Parallel Fork/Join Gateway) & Zaman Sınır Olayları / Hat Kesiciler (Boundary Timers & SLA Escalations)
Global endüstriyel süreç otomasyon devlerinin (Camunda BPMN 2.0, Siemens Teamcenter, SAP Signavio) paralel yürütme ve zaman sınır olayları mimarisidir.

### Temel Bileşenler:
1. **BPMN 2.0 Paralel Dallanma (AND-Fork Gateway):**
   - Süreç bir `parallel_fork` düğümüne ulaştığında, giden tüm kenarlar eş zamanlı olarak çözümlenir (`ProcessEngine::advance()`).
   - Her bir paralel operasyonel kol için bağımsız görevler (Örn: İdari İşler Araç Tahsisi & Muhasebe Harcırah Avansı) aynı anda `pending` statüsünde oluşturulur.
   - Durum `process_field_audits` tablosuna `parallel_fork` olarak işlenir.
2. **BPMN 2.0 Paralel Birleşme & Senkronizasyon Bariyeri (AND-Join Gateway / Barrier):**
   - Paralel kollardan biri tamamlandığında, `ProcessEngine::advance()` birleşme düğümüne gelen tüm öncül kolları (`predecessors`) denetler.
   - Eğer henüz tamamlanmamış (pending) paralel kollar varsa süreç birleşme bariyerinde tutulur, durum `waiting` yapılır ve `parallel_join_wait` denetim izi üretilir.
   - Tüm paralel kollar tamamlandığında (`pendingCount === 0`), `parallel_join_sync` denetim iziyle senkronizasyon tamamlanır ve süreç birleştirilerek sonraki aşamaya geçirilir.
3. **BPMN 2.0 Zaman Sınır Olayları & Hat Kesiciler (Boundary Timers & SLA Escalations):**
   - Servis: `App\Services\BoundaryTimerService` (`checkTaskTimers()`). Konsol komutu: `php artisan termin:check-sla`.
   - Görev düğümlerine bağlı `boundaryTimer` ayarları (`enabled`, `duration_hours`, `action`):
     - `auto_escalate`: Belirlenen SLA süresi dolduğunda görev otomatik olarak kullanıcının üst amirine eskale edilir (`boundary_timer_escalate` denetim izi).
     - `auto_approve`: Zaman aşımında görev sistem adına otomatik onaylanır ve süreç ilerletilir (`boundary_timer_auto_approve` denetim izi).
     - `notify_reminder`: Personele ve amirine acil kırmızı durum hatırlatması gönderilir.
4. **Tasarımcı & Takipçi Deneyimi (UI/UX):**
   - `Designer.vue`: Temel palette `🔀 Paralel Dallanma (AND-Fork)` ve `🔗 Paralel Birleşme (AND-Join)` düğümleri. Düğüm ayarları gelişmiş sekmesinde BPMN 2.0 Boundary Timer (Hat Kesici) paneli.
   - `DecisionNode.vue` & `BaseNode.vue`: BPMN 2.0 AND-Gateway diamond `+` simgesi, paralel kollar handle'ı ve `⏱️ Timer` rozeti.
   - `Tracker.vue`: Canlı Git-stili denetim izi kartları (`🔀 Paralel Dallanma`, `⏳ Paralel Birleşme Beklemesi`, `🔗 Paralel Kollar Senkronize Edildi`, `⏱️ Boundary Timer Eskalasyonu`).

---

## 15. Fortune-500 Enterprise BPM Faz 4: Kapsamlı Süreç Versiyonlama & Canlı Geçiş (Zero-Downtime Process Versioning & Schema Migration)
Siemens Opcenter, Camunda BPM ve SAP Signavio standartlarında sıfır kesintili (zero-downtime) süreç versiyonlama, immutable şema arşivleme ve canlı süreç göç (instance schema migration) motorudur.

### Temel Bileşenler:
1. **Veritabanı Katmanı & Versiyon Hiyerarşisi (`workflows` tablosu):**
   - Alanlar: `parent_workflow_id` (ana akışa bağlanan foreign key), `version` (tamsayı sürüm numarası: 1, 2, 3...), `is_latest` (boolean, en son aktif sürüm bayrağı), `version_notes` (versiyon değişiklik notları / changelog).
   - Model İlişkileri (`Workflow.php`): `parentWorkflow()`, `childVersions()`, `rootWorkflow()`, `allVersions()`.
   - `createNextVersion()`: Mevcut akıştan otomatik `version = parent.version + 1` üreterek eski versiyonları `is_latest = false` yapar, canlı süreçleri kesintiye uğratmadan yeni versiyonu yayına alır.

2. **Sıfır Kesinti İlkesi (Zero-Downtime Rule):**
   - Fabrika zemininde veya satışta aktif yürüyen hiçbir talep, tasarımcıdan yeni bir versiyon yayınlandığında bozulmaz veya şeması kaybolmaz.
   - Her `ProcessInstance` kendi oluşturulduğu `workflow_id`'ye bağlı kalır ve o versiyonun düğüm grafiğiyle tamamlanır.
   - Yeni başlatılan tüm süreçler otomatik olarak en son versiyona (`is_latest = true`) yönlendirilir.

3. **Canlı Süreç Göç Motoru (`App\Services\WorkflowMigrationService`):**
   - `getMigrationSchema()`: Kaynak ve hedef akış şemalarını analiz eder, kaynak düğümlerde bekleyen canlı süreç sayılarını hesaplar ve akıllı düğüm eşleştirmesi (`suggested_mapping`) önerir.
   - `migrateInstances()`: DB Transaction güvencesinde; seçilen canlı süreçlerin `workflow_id`'sini hedef versiyona geçirir, `current_node_id`'yi günceller, bekleyen görevlerin `node_id`'sini hedef şemaya taşır.
   - Tam Denetim İzi (Audit Diff): Göç işlemi `process_field_audits` tablosuna `workflow_migration` olarak eski versiyon/düğüm ve yeni versiyon/düğüm farklarıyla kaydedilir.

4. **Kullanıcı Deneyimi (UI/UX - Index, Designer & Tracker):**
   - `Workflow/Index.vue`: Süreç tablosunda `v1.0 (Eski)` ve `v2.0 ● Son` renkli versiyon rozetleri, "Göç" butonu ve entegre `WorkflowMigrationModal.vue`.
   - `WorkflowMigrationModal.vue`: Kaynak vs Hedef karşılaştırma paneli, canlı süreç sayacı, interaktif açılır düğüm eşleştirme matrisi ve tek tıkla canlı göç işlemi.
   - `Workflow/Designer.vue`: Üst barda sürüm rozeti (`Sürüm: v2.0 Son Sürüm`), versiyon ailesi hızlı geçiş seçicisi, "Mevcut Sürümü Kaydet" ve "✨ Yeni Versiyon Yayınla (v+1)" butonu ile changelog modalı.
   - `Tracker.vue`: Canlı Git-stili denetim izi kartı: `🔄 Canlı Süreç Versiyon Göçü (Schema Migration)` ile eski ve yeni versiyon/düğüm diff gösterimi.

5. **Doğrulama ve Kanıt Suite'i:**
   - E2E Test Suite: `php scratch/test_faz4_e2e.php` (9 adım %100 PASS).
   - Tarayıcı & UI Testi: `& "C:\node\node-v24.18.0-win-x64\node.exe" scratch/verify_faz4_browser.mjs` (5 ekran görüntüsü, 0 konsol hatası).
   - SQL Kanıtı: `php scratch/print_faz4_sql_proof.php` (Canlı veritabanı denetim izi).

---

## 15. Kurumsal İş Zekası (BI), Self-Service Raporlama & Otomatik E-Posta Dağıtım Motoru (Power BI & SAP SAC Standardı)
Büyük ölçekli küresel endüstri devlerinin (SAP SAC, Siemens Opcenter Intelligence, Microsoft Power BI Report Server) self-servis analitik ve zamanlanmış rapor dağıtım mimarisine uygun olarak geliştirilen modüldür.

### Temel Bileşenler:
1. **Veritabanı Katmanı (`report_widgets`, `report_dashboards`, `report_dashboard_widgets`, `report_schedules`, `report_schedule_logs`):**
   - Modeller: `ReportWidget`, `ReportDashboard`, `ReportSchedule`, `ReportScheduleLog`.
   - Seeder: `EnterpriseReportingSeeder` (`php artisan db:seed --class=EnterpriseReportingSeeder`).

2. **Semantik Veri ve Dinamik EAV/JSON Sorgu Motoru (`App\Services\ReportQueryEngineService`):**
   - Kataloglanmış Veri Kaynakları:
     - `processes`: Süreçler & Performans (İş Akışı, Durum, Departman, Başlatılma Ayı, Süre)
     - `tasks`: Görev & Kararlar (Departman, Atanan Yetkili, Karar Türü - Onay/Ret/Revize, Bekleme Süresi)
     - `termin_sla`: Termin ve SLA Analitiği (Hammadde, Makine Arızası, Kalıp Değişimi kök nedenleri)
     - `follow_ups`: Numune Satış Dönüşümü (Converted, Lost, Tonaj potansiyeli)
     - `form_fr_lvh_042`: Dinamik JSON Form Alanları (`data.urun_grubu`, `data.musteri_adi`, `data.kalinlik_mikron`)
   - `getAvailableDataSources()`: Power BI stili dinamik meta sözlüğü döner.
   - `executeWidgetQuery()`: Filtrelere ve zaman pencerelerine göre gruplama ve agregasyonları ApexCharts formatında üretir.

3. **Frontend BI Görselleştirme Kokpiti (`Reports/Index.vue`, `ReportChartRenderer.vue`):**
   - ApexCharts entegrasyonu (Bar, Line, Area, Donut, Pie, KPI Kartı, Tablo Görünümü).
   - Tek tıkla `📊 Grafik / 📋 Tablo` mod geçişi.
   - Power BI Stili No-Code Widget Sihirbazı (Canlı anlık grafik önizlemeli modal).
   - Çoklu Pano / Dashboard desteği ve global dinamik tarih aralığı filtresi (`Son 30 Gün`, `Bu Ay`, vb.).

4. **Otomatik Dağıtım, Çok Sekmeli Excel, PDF & E-Posta Motoru:**
   - Dışa Aktarma Servisi: `App\Services\ReportExportService` (Barryvdh DomPDF + Maatwebsite Excel).
   - **Çok Sekmeli Kurumsal Excel Mimarisi (`ReportDashboardExport`, `WithMultipleSheets`):**
     - `ReportExecutiveSummarySheet`: İlk sekme (Yönetici Özeti, şirket künyesi, panodaki tüm grafiklerin özet matrisi).
     - `ReportWidgetDetailSheet`: Her bir grafik için bağımsız ayrı sekme (Kategoriler, seriler, metrik değerleri, görsel çubuk göstergeleri, çift çizgili TOPLAM satırı).
   - E-Posta Şablonu: `App\Mail\ScheduledReportMail` (Antetli HTML gövde + PDF eki + Excel .xlsx eki).
   - Konsol Komutu: `php artisan reports:dispatch-scheduled` (`DispatchScheduledReportsCommand.php`).
   - Ekrandan yönetilebilir zamanlama modalı ve anında "⚡ Şimdi Test Gönder" butonu (Kill Switch koruma bildirimli).

5. **Pano Yönetimi (Tam CRUD) & Global Dilimleyiciler (Slicers):**
   - **Pano CRUD:** Yeni pano oluşturma, düzenleme modalı (`PUT /reports/dashboards/{id}`) ve silme modalı (`DELETE /reports/dashboards/{id}`).
   - **Global Departman & İş Akışı Filtresi:** Takvim seçicisinin yanında tüm panodaki grafikleri tek tıkla Departman (`department_id`) ve Süreç (`workflow_id`) bazında dinamik dilimler. Aktif filtreler hero banner'da `🏢 LEVHA`, `🚀 Levha Numune...` rozetleri olarak belirir ve PDF/Excel indirmelerine otomatik yansıtılır.

6. **Doğrulama ve Kanıt Komutları:**
   - Test Scripti: `php scratch/test_report_engine.php` (Tüm sorgular %100 PASS).
   - İhracat Testi: `php scratch/test_exports.php` (PDF + Excel doğrulaması).
   - Global Filtre Testi (SQL + Export): `php scratch/verify_reports_sql.php`.
   - Tarayıcı & UI Testi: `& "C:\node\node-v24.18.0-win-x64\node.exe" scratch/verify_reports_filters_browser.mjs` (0 JS konsol hatası).

---

## 16. Yaşayan ve İleriye Dönük Kurumsal RBAC Matrisi & Dinamik Modül Sicili (Dynamic Permission Registry)
Sistemin yaşayan ve sürekli gelişen bir platform olması gereğince; projeye gelecekte eklenecek yeni modüllerin (Satın Alma, Depo/WMS, Bakım Onarım, Kalite Güvence, İK, IoT vb.) kod değiştirilmeksizin doğrudan tanınmasını, görselleştirilmesini ve roller üzerinden yönetilmesini sağlayan Fortune-500 standardı dinamik yetkilendirme motorudur.

### Temel Bileşenler:
1. **Dinamik Modül ve İzin Sicil Servisi (`App\Services\PermissionRegistryService`):**
   - **Otomatik Keşif (Auto-Discovery Engine):** Veritabanındaki yetkilerin ön ekini (`modul.eylem`) tarar. Tanımlanmamış yepyeni bir ön ek gördüğünde dahi (Örn: `warehouse.scan`, `maintenance.repair`), sistemi çökertmeden anında dinamik bir modül kartı oluşturur.
   - **Geleceğe Dönük Kurumsal Yol Haritası (Roadmap Presets):** Satın Alma (`procurement` 🛒), Depo/WMS (`warehouse` 📦), Bakım Onarım (`maintenance` 🔧), Kalite Güvence/CAPA (`quality` 🎯), İnsan Kaynakları (`hr` 👥) şimdiden hazır meta sözlüklerine sahiptir.
   - **Kalıcı Özel Modül Kaydı (`Setting` Tablosu):** Ekrandan veya API üzerinden tanımlanan yeni modüller `custom_rbac_modules` anahtarıyla kalıcı olarak kaydedilir.
   - **Metotlar:** `getModuleDefinitions()`, `getModuleGroupedPermissions()`, `getRoleModuleBadges()`, `registerPermission()`.

2. **Gelişmiş Rol Yönetim Kokpiti (`/admin/roles` - `Index.vue`):**
   - **Hızlı Modül Dilimleyici Şeridi:** Üst kısımda `Tümü (11)`, `İş Zekası (6)`, `Termin (5)`, `Numune (4)`, `Dış Görev (5)`, vb. tıklanabilir butonlar. Tıklandığında sadece o modüle yetkisi olan rolleri anında filtreler.
   - **Modüler Kapsam Rozetleri:** Her rolün satırında hangi modülde kaç yetkisi olduğu renkli rozetlerle listelenir (`📊 Power BI 6`, `⏱️ SLA 3`, `🧪 Numune 3`, `✈️ Dış 7`, `📦 Depo 1`, `🛒 Satın 1`).
   - **Yetki Detay Matrisi Modalı (`👁️ Yetkiler`):** Rolün tüm modüllerdeki durumunu açılır modalda yeşil onay ikonları (`✅`) ve kilitli (`🔒`) yetkilerle gösterir.
   - **Sıfır Kod Dinamik Yetki & Modül Tanımlama Modalı (`✨ Yeni Yetki / Modül Ekle`):** Yöneticilerin tek tıkla yeni bir teknik kod (`modul.eylem`), modül sistem adı, modül başlığı ve emoji ikonu seçerek anında sisteme yeni yetki tanımlamasını sağlar (`POST /admin/permissions`).

3. **Modüler Rol Tanımlama & Düzenleme Ekranı (`/admin/roles/{id}/edit` - `Form.vue`):**
   - **Dinamik İki Kolonlu Modül Kartları:** Her modül bağımsız renkli bir kart olarak render edilir.
   - **Akıllı Başlık & Canlı Sayaç:** Kart başlığında `3 / 6 Yetki`, `✅ Tam Yetkili`, `⚡ Kısmi Yetki`, `🔒 Kapalı` durumları ve `Tümünü Seç / Kaldır` butonu.
   - **Canlı Yetki Arama Kutusu:** 47+ yetki arasında hem modül adıyla hem yetki başlığıyla anında filtreleme.
   - **Sticky Alt Çubuk:** Toplam seçili yetki sayısını ve hızlı kaydet butonunu her an görünür tutar.

4. **Doğrulama ve Kanıt Suite'i:**
   - E2E Backend & Extensibility Testi: `php scratch/verify_future_module_extensibility.php` (Tüm modüller ve rol rozetleri %100 PASS).
   - Tarayıcı & UI Testi: `& "C:\node\node-v24.18.0-win-x64\node.exe" scratch/verify_roles_system.mjs` (5 ekran görüntüsü, 0 uncaught JS hatası).

---

## 15. Kurumsal Entegrasyon ve Kenetleme Motoru (Pillar 1: Kopuk Bağları Birbirine Kenetleme)
Projenin önceden birbirinden bağımsız çalışan güçlü modüllerini (Ekipman Ağacı, Denetim İzi, Vekalet, Power BI Raporları, Mail Guard) uçtan uca birbirine kenetleyen kurumsal mimaridir.

### 5 Temel Kenetlenme Bileşeni:
1. **Organizasyon Şeması & Ekipman Ağacı $\leftrightarrow$ Dinamik Form Entegrasyonu (Item 1):**
   - **API & Servis:** `/api/hierarchy/nodes?subtype=makine` (`HierarchyTestController::apiNodes`).
   - **Dinamik Form Bileşeni (`FormRenderer.vue`):** Form şablonunda `datasource: 'hierarchy_nodes'` veya `hierarchy_subtype: 'makine'` tanımlı alanlar fabrika ekipman ağacından canlı olarak doldurulur.
   - **Görsel Rozet:** Alanın hemen altında `🏭 Fabrika Ekipman & Makine Ağacından Dinamik Gelir` rozeti gösterilir.
   - **Form Şablonları:** FR-LVH-042 (#4) ve FR-LVH-02 (#10) formlarına `planlanan_hat` alanı entegre edildi.

2. **Alan Bazlı Denetim İzi (Audit Diff) $\leftrightarrow$ Merkezi Güvenlik & Denetim Kokpiti (Item 2):**
   - **Uç Nokta & Controller:** `/admin/audit-logs` (`AuditLogController.php`).
   - **Arayüz (`Admin/AuditLogs/Index.vue`):** 6 KPI kartı (Toplam Değişiklik, Alan Bazlı Diff, SoD Eskalasyonları, DoA İstisnaları, Kritik Değişiklikler, Son 24 Saat).
   - **Git-Diff Karşılaştırma:** Denetim kayıtları için alan bazlı eski ve yeni değerleri üstü çizili kırmızı / yeşil Git-stili modalda (`İncele 🔍`) sunar.
   - **Menü:** `AuthenticatedLayout.vue` içinde `⚙️ Sistem Yönetimi -> 🛡️ Denetim İzi & Değişiklik Günlüğü`.

3. **Vekalet Sistemi (Delegations) $\leftrightarrow$ Üst Profil & Hızlı İzin Modu (Item 3):**
   - **Küresel Veri Paylaşımı (`HandleInertiaRequests.php`):** `active_delegation_given`, `active_delegations_received`, `all_colleagues`.
   - **Turuncu Aktif Vekalet Barı (`AuthenticatedLayout.vue`):** İzinli yöneticinin ekranında üstte `🏖️ İzin & Vekalet Modu Aktif: {Vekil}` bildirim çubuğu ve anında sonlandırma butonu (`❌ Vekaleti Sonlandır`).
   - **Petrol Yeşili Vekil Barı:** Vekaleten işlem yapan kullanıcı için bilgilendirme şeridi.
   - **Hızlı İzin Modalı:** Kullanıcı profil açılır menüsünden tek tıkla açılan `🏖️ İzin & Vekalet Modu` formu.

4. **Power BI Raporlama Panoları $\leftrightarrow$ Ana Sayfa Kokpiti (Item 4):**
   - **Veritabanı Katmanı:** `report_widgets.is_pinned_to_home` (Boolean).
   - **Sabitleme API:** `POST /reports/widgets/{id}/toggle-pin` (`ReportController::toggleWidgetPin`).
   - **Pin Butonları:** `ReportChartRenderer.vue` üzerinde pin iğnesi butonu (`📌`).
   - **Canlı Ana Sayfa (`Dashboard.vue`):** Ana sayfanın en üstünde sabitlenen Power BI metrikleri canlı ApexCharts grafikleri olarak otomatik derlenir ve gösterilir (`DashboardController.php`).

5. **E-Posta Kural Motoru (Mail Guard) $\leftrightarrow$ SLA & Görev Hatırlatma Zamanlayıcıları (Morning Digest - Item 5):**
   - **Kayıtlı Olaylar (`mail_events` & `mail_rules`):** `termin_warning`, `termin_overdue_escalation`, `boundary_timer_escalate`, `morning_digest`.
   - **Tetikleyiciler:** `CheckTerminSlaCommand.php` ve `BoundaryTimerService.php` tüm uyarı ve eskalasyon e-postalarını `X-Mail-Event` başlığı ile gönderir.
   - **Mesai Dışı Güvenlik Kalkanı (`MailGuardListener`):** Kill-switch, Sandbox Test Yönlendirmesi ve mesai saati/gün kurallarını denetler. Mesai dışı saatlerde (18:30 - 08:30) veya hafta sonu tetiklenen e-postalar yöneticileri gece rahatsız etmemek için `mail_digest_queues` tablosuna `pending` olarak kuyruğa alınır.
   - **Sabah Yönetici Özeti (Morning Digest) Motoru:**
     - **Mailable & Şablon:** `App\Mail\MorningDigestMail`, `resources/views/emails/morning_digest.blade.php` (Köksan antetli kurumsal tasarım, KPI sayaçları, gece biriken eskalasyonlar tablosu ve doğrudan işlem linkleri).
     - **Zamanlayıcı & Konsol Komutu:** `php artisan mail:send-digest` (`routes/console.php` üzerinden her hafta içi sabah saat **08:30**'da otomatik çalışır, `--force` ve `--dry-run` bayraklarını destekler).
     - **Yönetim Paneli:** `/admin/mail-settings` ekranında Morning Digest canlı KPI paneli, açılır kuyruk denetim tablosu ve tek tıkla `[⚡ Sabah Özetini Şimdi Gönder]` anlık tetikleme butonu.

### Doğrulama ve Kanıt Suite'i:
- **E2E Puppeteer Tarayıcı Testi:** `& "C:\node\node-v24.18.0-win-x64\node.exe" scratch/verify_pillar1_e2e.mjs` (7 görsel kanıt, 0 uncaught JS hatası).
- **Canlı SQL Denetim İzi:** `php scratch/print_pillar1_sql_proof.php` (Tüm 5 maddenin veritabanı kayıtları %100 doğrulanmıştır).
- **SLA Eskalasyon Konsol Testi:** `php artisan termin:check-sla` (0 hata, 8 süreç tarandı, SLA uyarıları tetiklendi).

---

## 16. Kurumsal Yönetici & Karar Destek Motoru: 2. Sütun (Pillar 2 - Executive BPM Suite)
Üst yönetim ve departman müdürlerinin onay süreçlerini hızlandıran, darboğazları tespit eden ve bütçe aşımını engelleyen Fortune-500 karar destek bileşenleridir.

### Temel Bileşenler ve Mimari:
1. **Süreç Darboğaz & Bekleme Isı Haritası (Lead Time & Bottleneck Analytics - Item A):**
   - **Servis:** `App\Services\LeadTimeService`
   - Sürecin başlatılmasından tamamlanmasına kadar geçen toplam döngü süresini (Lead Time), adım başına ortalama süreyi ve her görevin harcadığı net dakikayı/saati/günü hesaplar.
   - Süreç içerisinde en çok bekleme yapan adımı otomatik olarak `🚨 Kritik Süreç Darboğazı (Bottleneck)` olarak etiketler, süreç içi payını (% percentage) ve gecikme ağırlığını görselleştirir.
   - **Arayüz:** `/processes/{id}/tracker` ekranında `[⏳ Süre & Darboğaz Analizi]` sekmesi, renk kodlu adımlar (Zümrüt: Hedefte, Kehribar: Normal, Gül Kurusu: Darboğaz) ve zaman çizelgesi.

2. **Toplu Onay & Hızlı Karar Merkezi (Bulk Actions / One-Click Multi-Approve - Item B):**
   - **Backend & Rota:** `POST /tasks/bulk-action` (`TaskController::bulkAction`)
   - SAP MyInbox standartlarında; yetkilinin gelen kutusunda birden fazla onay görevini checkbox ile seçebilmesi, tek tıkla toplu onaylama (`approve`) veya toplu reddetme (`reject`) kararı verebilmesi.
   - Her görev için 4-Eyes / SoD eskalasyon denetimi, otomatik workflow ilerletme (`advanceWorkflow`), konsensüs/any politikası işletimi ve denetim izi loglaması.
   - **Arayüz:** `/tasks` gelen kutusunda kayan toplu aksiyon barı (`fixed bottom floating action bar`), seçilen görev sayacı ve toplu karar gerekçesi modalı.

3. **Bölünmüş Ekranda Belge & Fatura Önizleyici (Split-Screen Live Document Viewer - Item C):**
   - **Backend:** `TaskController::extractDocumentsForTask()` (Seyahat İzin PDF, CoA Analiz Sertifikası, form faturası/evrakları ve görev PDF dökümünü otomatik haritalar).
   - **Arayüz:** `/tasks/{id}` görev detayında `[📄 Yan Yana Belge Önizle]` butonu. Formun sağ tarafında yapışkan (sticky) belge penceresi açılır; kullanıcı formu doldururken veya onaylarken sayfadan ayrılmadan faturayı, izin belgesini veya teknik şartnameyi yan yana inceler.

4. **Kurumsal Bütçe & Masraf Merkezi Limiti Uyarısı (Budget & Cost Center Control - Item D):**
   - **Servis:** `App\Services\BudgetControlService`
   - Talep edilen tutarı (`DoaMatrixService`), departmanın dönem bütçesini ve masraf merkezini (örn: `CC-1230 - LEVHA`) analiz eder.
   - Bütçe tüketim çubuğu, önceki harcama oranı, yeni harcama ile oluşacak oran ve kullanılabilir kalan bütçe anlık hesaplanır.
   - Eşik Seviyeleri: 🟢 %0-%75 Bütçe Dahilinde (On-Track), 🟡 %75-%95 Bütçe Sınırına Yaklaşıyor (Warning), 🔴 >%95 Bütçe Aşımı Riski (Critical).
   - **Arayüz:** Görev detayı (`Tasks/Show.vue`) ve Süreç Takipçisi (`Process/Tracker.vue`) üzerinde canlı bütçe monitör kartı.

### Doğrulama ve Kanıt Suite'i:
- **E2E Puppeteer Tarayıcı Testi:** `& "C:\node\node-v24.18.0-win-x64\node.exe" scratch/verify_pillar2_e2e.mjs` (6 görsel kanıt, 0 uncaught JS hatası).
- **Canlı SQL Denetim İzi:** `php scratch/print_pillar2_sql_proof.php` (Toplu onaylanan görevler, ilerletilen adımlar, lead time analitiği ve bütçe hesaplamaları canlı SQL ile %100 kanıtlanmıştır).

---

## 17. Kurumsal Saha & Operasyon Süreçleri Genişletme: 3. Sütun (Pillar 3 - Factory & Operational BPM Suite)
Fabrika zeminindeki üretim, bakım, kalite güvence ve malzeme tedarik operasyonlarını uçtan uca dijitalleştiren stratejik endüstriyel süreç paketidir.

### Temel Bileşenler ve İş Akışları:
1. **TPM & Makine Arıza / Bakım Onarım Süreci (Workflow ID: 30 | Form ID: 15 | Belge: FR-BAK-001):**
   - **Kategoriler:** `["Bakım & TPM", "Üretim"]`
   - **Akış Düğümleri:** `node_start` (Operatör Bildirimi) $\rightarrow$ `node_vardiya_sefi` (Vardiya Şefi Acil Onay) $\rightarrow$ `node_bakim_mudahale` (Bakım Ekibi Müdahalesi, Duruş Kaydı & Kök Neden) $\rightarrow$ `node_test_kabul` (Üretim Testi & Kabul Onayı) $\rightarrow$ `node_end` (Kapanış).
   - **Form Özellikleri:** Fabrika Ekipman Ağacından canlı makine seçimi (`planlanan_hat`), arıza türü, duruş başlangıç/bitiş süresi, kullanılan yedek parçalar ve kök neden sınıflandırması.
   - **Resmi PDF Çıktısı:** `App\Services\MaintenanceOrderGenerator` (`/processes/{id}/maintenance-order`). Köksan antetli, duruş süresi analizli ve imza bloklu resmi Makine Bakım ve Onarım İş Emri PDF'i.
   - **Bölünmüş Ekran Entegrasyonu:** Görev detayında (`/tasks/{id}`) split-screen belge önizleyicide canlı bakım iş emri render edilir.

2. **Düzeltici ve Önleyici Faaliyet (DÖF / 8D) Süreci (Workflow ID: 31 | Form ID: 16 | Belge: FR-KAL-015):**
   - **Kategoriler:** `["Kalite Güvence", "Süreç İyileştirme"]`
   - **Akış Düğümleri:** `node_start` (Uygunsuzluk Tanımı) $\rightarrow$ `node_kok_neden_aksiyon` (Sorumlu Bölüm: 5-Why Analizi & Kalıcı Aksiyon) $\rightarrow$ `node_amir_onay` (Birim Müdürü Aksiyon Onayı) $\rightarrow$ `node_etkinlik_takip` (Kalite Güvence: Etkinlik Doğrulama) $\rightarrow$ `node_end` (Kapanış).
   - **Form Özellikleri:** Uygunsuzluk kaynağı (İç Denetim, Müşteri Şikayeti, Hurda Artışı, BRCGS/ISO Denetimi), acil karantina önlemi, 5-Neden (5-Why) derinlemesine kök neden analizi, terminli aksiyon planı ve etkinlik doğrulama kriterleri.
   - **Resmi PDF Çıktısı:** `App\Services\DofReportGenerator` (`/processes/{id}/dof-report`). ISO 9001 ve BRCGS standartlarına uygun resmi DÖF & 8D Kalite İyileştirme Raporu PDF'i.

3. **Acil Satın Alma & Malzeme Talep Süreci (Workflow ID: 32 | Form ID: 17 | Belge: FR-SAT-004):**
   - **Kategoriler:** `["Satın Alma & Tedarik", "Operasyon"]`
   - **Akış Düğümleri:** `node_start` (Talep Sahibi) $\rightarrow$ `node_amir_onay` (Birim Müdürü & Bütçe Onayı) $\rightarrow$ `node_teklif_toplama` (Satın Alma: Tedarikçi & Teklif Değerlendirme) $\rightarrow$ `node_yonetim_onay` (DoA Üst Yönetim Onayı) $\rightarrow$ `node_siparis_sap` (SAP SAS Sipariş Açılışı) $\rightarrow$ `node_end` (Tamamlandı).
   - **Form Özellikleri:** Masraf merkezi kodu (`CC-1230 - LEVHA`), aciliyet derecesi, malzeme kalemleri, tedarikçi unvanı, döviz türü, SAP SAS sipariş numarası.
   - **Bütçe & DoA Entegrasyonu:** Talep tutarı anlık olarak `BudgetControlService` ile masraf merkezinin dönem bütçesini tüketir; %65+ bütçe tüketim çubuğu ve DoA finansal yetki matrisi otomatik işletilir.
   - **Resmi PDF Çıktısı:** `App\Services\PurchaseOrderGenerator` (`/processes/{id}/purchase-order`). Antetli, kalem dökümlü ve SAP onay kodlu resmi Malzeme ve Hizmet Satın Alma Sipariş Fişi PDF'i.

### Doğrulama ve Kanıt Suite'i:
- **E2E Puppeteer Tarayıcı Testi:** `& "C:\node\node-v24.18.0-win-x64\node.exe" scratch/verify_pillar3_e2e.mjs` (5 görsel kanıt, 0 uncaught JS hatası).
- **Canlı SQL Denetim İzi:** `php scratch/print_pillar3_sql_proof.php` (Yeni iş akışları, form şablonları, canlı süreçler, görevler, bütçe etki değerlendirmesi ve TPM duruş süresi analitiği canlı SQL ile %100 kanıtlanmıştır).
- **PDF Üretim Doğrulaması:** `php scratch/test_pdf_generation.php` (Her 3 PDF çıktısı hatasız üretilmiştir).

---

## 17. Kurumsal Canlı Saha & Personel Radarı ("Kim Nerede?") ve Dinamik Görev Türleri Yönetimi (Duty Categories CRUD)
Üst yönetimin ve büyük patronun sahadaki gücünü anlık olarak takip etmesini sağlayan, *"Patron bir adamı arayıp nerede diye bana sormamalı, bu sistemden o adamın fuarda olduğunu görmeli"* vizyonuna uygun olarak geliştirilen canlı personel varlık ve görev radarıdır.

### Temel Bileşenler:
1. **Dinamik Görev Türleri & Saha Kategori Yönetimi (`duty_categories`):**
   - **Model & Tablo:** `App\Models\DutyCategory` (`name`, `code`, `icon`, `color`, `description`, `is_field_duty`, `is_active`, `sort_order`).
   - **Admin CRUD:** `/admin/duty-categories` rotası ve `App\Http\Controllers\Admin\DutyCategoryController`. Yeni görev türleri, emojili ikon seçici, kurumsal renk temaları (indigo, emerald, blue, amber, purple, teal, slate, rose), sıralama ve aktif/pasif anahtarıyla dinamik olarak yönetilir.
   - **Otomatik ve İdempotent Üretim Seeder'ı:** `DutyCategorySeeder.php` 8 zengin kategori (Fuar, Müşteri, Servis, Transfer, Denetim, Eğitim, Resmi, Diğer) ile doğrudan `DatabaseSeeder.php` içerisine entegre edilmiştir. Sunucuya atıldığında `php artisan db:seed` ile sıfırdan hiçbir el müdahalesine gerek kalmadan anında kurulur.
2. **Canlı Saha ve Personel Radarı Kokpiti (`/duty-radar`):**
   - **Controller:** `App\Http\Controllers\DutyRadarController`
   - **Arayüz:** `resources/js/Pages/DutyRadar/Index.vue`
   - **Anlık Üst Yönetim KPI Sayıcıları:**
     - 🟢 Sahada / Dış Görevde: Tarih aralığında aktif görevde olan personel sayısı.
     - 🏭 Fabrikada / Merkezde: Merkez fabrikada operasyonu sürdüren mevcut çalışan sayısı.
     - 💼 Aktif Vekaletler: Sahadaki personelin onay ve yetkilerini devrettiği aktif vekalet sayısı.
   - **Tarih Filtreleri & Presetleri:**
     - ⚡ Bugün Sahada Olanlar (Canlı durum, "Kalan X Gün", "Bugün Son Gün", "Bugün Başladı").
     - 📅 Bu Hafta, 📆 Bu Ay, 🔍 Özel Tarih Aralığı (Takvim seçicili).
   - **Sıfır Gecikmeli Dinamik Kategori & Metin Arama:**
     - Veritabanındaki tüm aktif kategorileri dinamik sekme olarak listeler.
     - İsim, gidilecek firma, fuar adı, lokasyon ve departman bazında anlık canlı arama filtresi.
   - **Akıllı Kartlar & Vekalet Uyarıları:**
     - Personel avatarı, departmanı, unvanı, görev amacı, ulaşım tercihi ve konaklama rozetleri.
     - Sahada olan personelin yerine fabrikada kimin vekalet ettiğini gösteren kehribar uyarı bloğu: `💼 Fabrikada Yerine Bakan Vekil: [Vekil Adı] (işleri devraldı)`.
     - Tek tıkla `[📄 İzin PDF]` (Resmi Dış Görevlendirme ve Seyahat İzin Belgesi) ve `[🧭 Süreç Takibi]`.
3. **Navigasyon ve Ana Sayfa Entegrasyonu:**
   - `AuthenticatedLayout.vue`: Masaüstü ve mobilde üst menüye `📍 Kim Nerede?` bağlantısı eklendi (`v-if="hasPermission('duty_radar.view')"`).
   - `Dashboard.vue`: Üst kısma canlı zümrüt ışıklı `📍 Canlı Saha & Personel Radarı (Kim Nerede?)` yönetim bannerı entegre edildi (`v-if="canViewDutyRadar"`).
   - Admin Menüsü: `⚙️ Sistem Yönetimi -> Süreç Mimarisi -> 📍 Görev & Saha Türleri` (`v-if="hasPermission('duty_radar.manage_categories') || hasPermission('view_admin_panel')"`).

---

## 18. Canlı Saha & Personel Radarı Rol ve Yetki Yönetimi (RBAC & Permissions via /admin/roles)
Saha radarı ve ana sayfa bannerının kimler tarafından görüntülenebileceğini ve yönetilebileceğini `/admin/roles` ekranı üzerinden tam yetki matrisiyle dinamik olarak denetleyen kurumsal RBAC mimarisidir.

### Temel Bileşenler:
1. **Dinamik Yetki Tanımları (`App\Services\PermissionRegistryService` & `EnterprisePermissionsSeeder`):**
   - **Modül:** `duty_radar` (İkon: `📍`, Başlık: `Canlı Saha & Personel Radarı (Kim Nerede?)`, Açıklama: `Fabrika dışındaki personelin (Fuar, Müşteri, Servis vb.) anlık lokasyon, görev ve vekalet takibi`).
   - **İzin 1 (`duty_radar.view`):** *Canlı Saha & Personel Radarını Görüntüleme* (Radar kokpiti `/duty-radar`, navbar menü linki ve ana sayfa dashboard bannerı).
   - **İzin 2 (`duty_radar.manage_categories`):** *Görev & Saha Kategorilerini Yönetme (CRUD)* (`/admin/duty-categories` yönetimi).
2. **Çok Katmanlı Güvenlik Kalkanı:**
   - **Rota Katmanı (`routes/web.php`):** `/duty-radar` rotasına `->middleware('can:duty_radar.view')`, `/admin/duty-categories` rotasına `->middleware('can:duty_radar.manage_categories')`.
   - **Controller Katmanı (`DutyRadarController::index`):** Yetkisiz doğrudan erişimlerde anında `403 Forbidden` (`Canlı Saha & Personel Radarını görüntüleme yetkiniz bulunmamaktadır.`).
   - **Arayüz Katmanı (`AuthenticatedLayout.vue` & `Dashboard.vue`):** Yetkisi olmayan roller için menü linki ve dashboard bannerı DOM üzerinde hiç çizilmez (Zero Exposure).
3. **Yönetim Deneyimi (`/admin/roles` & `/admin/roles/{id}/edit`):**
   - Admin istediği herhangi bir role (Örn: `Müdür`, `Direktör`, `Kullanıcı`) tek bir tıkla `📍 Canlı Saha & Personel Radarı` kartından izni açıp kapatabilir.
4. **Doğrulama ve Kanıt Suite'i:**
   - E2E Tarayıcı Testi: `& "C:\node\node-v24.18.0-win-x64\node.exe" scratch/verify_rbac_duty_radar_browser.mjs` (5 görsel kanıt 06-10, 0 uncaught JS hatası).
   - SQL Kanıtı: `php scratch/print_duty_radar_rbac_sql_proof.php` (Canlı veritabanı denetim izi).

---

## 19. Kurumsal Görev Yönetimi, RBAC Yetkilendirme & Güvenli Soft Delete Mimarisi (/admin/roles, /tasks, /dashboard)
Özellikle geliştirme ve canlı ortam kurulumlarında biriken test süreç ve görev verilerinin temizlenmesi, atanmış personellerin revize edilmesi ve sahadaki personelin onay bütünlüğünü korumak amacıyla geliştirilen kurumsal silme/düzenleme mimarisidir.

### Temel Mimari ve Kurallar:
1. **Zorunlu Soft Delete Güvencesi (Asla Hard Delete Yok):**
   - Hem `tasks` hem de `process_instances` tablolarına `deleted_at` sütunu ve Eloquent `SoftDeletes` trait'i entegre edilmiştir (`database/migrations/2026_10_16_130000_add_soft_deletes_to_tasks_and_processes.php`).
   - Admin için bile hiçbir kayıt veritabanından kalıcı olarak yok edilmez (`forceDelete` yasaktır). `deleted_at` zaman damgası işlenerek arşivlenir; standart sorgularda gizlenir, gerektiğinde kurtarılabilir ve denetim izi (audit trail) korunur.
2. **Standart Kullanıcı Koruma Kalkanı (Yetkisiz Silme Engeli):**
   - Fabrikadaki operatör, laborant veya normal kullanıcıların kendilerine atanan görevleri veya talepleri keyfi olarak silmesi kesinlikle engellenmiştir.
   - `/tasks` ve `/dashboard` ekranlarında standart kullanıcılara silme/düzenleme butonları hiç gösterilmez. Doğrudan API çağrısı yapılsa dahi backend seviyesinde anında `403 Forbidden` fırlatılır.
3. **Dinamik Rol & İzin Yönetimi (`/admin/roles` & `PermissionRegistryService`):**
   - Yetkiler `/admin/roles/{id}/edit` ekranında `Süreç & Görev Operasyonları` kartı altında yönetilir:
     - `tasks.admin_edit`: Görevi Düzenle / Yeniden Ata / Vade Güncelle.
     - `tasks.admin_delete`: Tekil Görev veya Bağlı Test Sürecini Güvenle Arşivle (Soft Delete).
     - `tasks.admin_bulk_delete`: Çoklu Seçim ile Toplu Görev ve Süreç Temizliği (Bulk Soft Delete).
4. **Akıllı Silme Kapsamı ve Süreç Bütünlüğü (Process Scope):**
   - Yalnızca tek bir görevi silmek süreci "askıda" bırakabileceği için modal üzerinde 2 seçenek sunulur:
     - 🚀 **Bağlı Test Sürecini ve Tüm Görevlerini Arşivle (Önerilen):** Süreci ve tüm bağlı görevlerini tek tıkla arşivleyerek arkada yetim süreç kalmasını önler.
     - 📋 **Yalnızca Bu Tekil Görevi Arşivle:** Süreci açık tutar, sadece ilgili personelin görevini soft delete yapar.
5. **Yönetici Müdahale Denetim İzi (Audit Diff):**
   - Görev üzerindeki atama veya vade değişiklikleri `process_field_audits` tablosuna `admin_task_edit` olayıyla Git-stili eski/yeni değer olarak kaydedilir.
6. **Doğrulama ve Kanıt Suite'i:**
   - Backend & Yetki Testi: `php scratch/test_admin_soft_delete_e2e.php` (6 adım, %100 Başarı).
   - Tarayıcı & UI Testi: `& "C:\node\node-v24.18.0-win-x64\node.exe" scratch/verify_admin_tasks_soft_delete_browser.mjs` (6 görsel kanıt, 0 uncaught JS hatası).
   - SQL Denetim İzi: `php scratch/print_admin_tasks_soft_delete_sql_proof.php` (`deleted_at IS NOT NULL` canlı kanıtı).

---

## 20. Kurumsal Güvenlik İzleme, SIEM (Logsign) & Tehdit Savunma Motoru (G1-G5 Standartları)
IT Bilgi Güvenliği ekibinin resmi raporuna ve kabul kriterlerine göre devreye alınan kurumsal güvenlik izleme, saldırı tespit, brute force kalkanı ve SIEM (Logsign) entegrasyon modülüdür.

### 1. Temel Mimarisi ve Karşılanan Gereksinimler:
- **G1: Yapılandırılmış Güvenlik Olay Günlüğü (JSON & RFC 5424):**
  - Tüm güvenlik olayları (`auth.*`, `attack.*`, `authz.*`, `session.*`, `input.*`) uygulama loglarından ayrı olarak `storage/logs/security-YYYY-MM-DD.log` dosyasına flat JSON formatında günlük yazılır (90 gün saklama süresi).
  - Her olayda `@timestamp` (UTC ISO 8601 milisaniyeli), `event_type`, `event_category`, `severity`, `application`, `environment`, `request_id`, `source_ip`, `user_agent`, `method`, `path`, `route`, `user_id` alanları standarttır.
  - Hassas veriler (`password`, `token`, `card_number`, `cvv`, `iban`, `tckn`, `tc_kimlik_no`, vb.) `ValueSanitizer` ile `[REDACTED]` olarak maskelenir; log injection'a karşı CR/LF karakterleri temizlenir ve metinler 200 karakterle sınırlandırılır.
  - `AssignRequestId` global middleware'i her isteğe `X-Request-Id` UUID başlığı ekler ve SIEM ile korelasyon sağlar.
- **G2: Bağımsız İki Kademeli Brute Force & Credential Stuffing Koruması:**
  - `RateLimiterLoginAttemptGuard`: Hesap ve IP bütçesini birbirinden bağımsız iki ayrı sayaçta takip eder:
    - **Hesap Başına:** 15 dakikada 5 başarısız denemede hesap kilitlenir (`auth.account_locked`). 6. ve sonraki denemelerde şifre kontrol edilmeden doğrudan `HTTP 429` ile reddedilir (`auth.login_blocked`).
    - **IP Başına:** 15 dakikada 20 başarısız denemede IP engellenir (`auth.ip_blocked`). Tek IP'den farklı hesaplara saldıran Credential Stuffing anında durdurulur.
    - Başarılı giriş yalnızca ilgili hesap sayacını sıfırlar; saldırganın IP sayacını sıfırlamasına izin verilmez.
- **G3: SQL Injection, XSS Tespiti & Güvenli Kodlama:**
  - `SqlInjectionDetector`: OWASP CRS tautology (`' or 1=1`), numeric tautology, `UNION SELECT`, stacked query (`DROP`, `DELETE`), time-based (`SLEEP`, `benchmark`), inline comment hileleri (`UNION/**/SELECT`), schema enumeration (`information_schema`) imzalarını yakalar (`attack.sqli_pattern`).
  - `XssDetector`: `<script>`, event handler'lar (`onerror=`, `onload=`), tehlikeli tag'ler (`iframe`, `svg`), `javascript:` URI'lar, DOM sink'ler (`document.cookie`, `eval`, `atob`) imzalarını yakalar (`attack.xss_pattern`).
  - Veritabanı syntax (`SQLSTATE 42xxx`, `21xxx`) hataları `attack.db_syntax_error` olarak loglanır; parametreli şablon yazılır, binding değerleri gizlenir.
- **G4: Tarama Tespiti, Hata Oranı & Scanner User-Agent:**
  - `ScannerUserAgentDetector`: 33 adet bilinen saldırı/fuzzing aracının (sqlmap, Nikto, Nuclei, Acunetix, gobuster, ffuf, hydra vb.) User-Agent'ını tespit eder (`attack.scanner_user_agent`, 10 dakikada 1 kez loglanarak log şişmesi önlenir).
  - Hata Oranı Kalkanı: 60 saniyede tek bir IP'den 50 adet hata yanıtı (400, 401, 403, 404, 405, 419, 422, 429) geldiğinde `attack.scanning_behavior` tetiklenir (tekil 404'ler logu kirletmez).
  - Laravel'in normalde loglamadığı `403` (`authz.access_denied`), `419` (`session.csrf_mismatch`), `422` (`input.validation_failed`) olayları `MonitorSecurityResponses` ile yakalanır.
- **G5: Content Security Policy (CSP) & İhlal Raporlama:**
  - HTML yanıtlarına `Content-Security-Policy-Report-Only` (veya `enforced`) header'ı basılır. Politika Inertia.js, Vite ve kurumsal fontlarla %100 uyumludur.
  - Tarayıcı ihlal raporları `POST /security/csp-report` endpoint'ine gönderilir (CSRF ve session'dan muaf, IP başına 60 istek/dk sınırlı). Tarayıcı eklentisi (extension) çöpleri elenerek `attack.csp_violation` loglanır.

### 2. Yapılandırma ve Servis Sağlayıcıları:
- Modül Dizinleri: `app/Security/` (`Auth`, `Config`, `Contracts`, `Detection`, `Enums`, `Http`, `Listeners`, `Logging`), `app/Providers/SecurityMonitoringServiceProvider.php`, `config/security-monitoring.php`.
- Kayıt: `bootstrap/providers.php` içinde `SecurityMonitoringServiceProvider`.
- Middleware Hattı (`bootstrap/app.php`):
  - `$middleware->prepend(AssignRequestId::class)`
  - `$middleware->append([MonitorSecurityResponses::class, InspectRequestPayload::class, ContentSecurityPolicy::class])`
  - `$middleware->alias(['security.login' => ThrottleLoginAttempts::class])`
  - Subpath Desteği: `public/workflow_koksan/build` junction'ı ile `/workflow_koksan/` altında kusursuz asset teslimi.

### 3. Doğrulama ve Kabul Testi Komutları:
- **10/10 SIEM Kabul Testi Suite'i:** `php scratch/test_security_acceptance.php` (Tüm 10 kabul kriteri %100 başarıyla geçmektedir).
- **Standalone İmza Doğrulama Testi:** `php scratch/run_signature_tests.php` (28 SQLi, 19 XSS ve 33 Türkçe/İngilizce normal metin %100 doğrulandı, 0 false-positive).
- **Tarayıcı & UI Entegrasyon Testi:** `& "C:\node\node-v24.18.0-win-x64\node.exe" scratch/verify_security_browser.mjs` (0 kırmızı JavaScript hatası, CSP altında çalışan canlı form, görsel kanıtlar: `01_login_page_with_csp.png`, `02_login_failed_feedback.png`).
- **Geliştirici & IT Canlı Güvenlik Kokpiti Testi:** `& "C:\node\node-v24.18.0-win-x64\node.exe" scratch/verify_security_cockpit_browser.mjs` (10/10 test laboratuvarı koşumu, canlı log listeleme, silme ve filtreleme, 0 konsol hatası).

### 4. Geliştirici & IT Güvenlik Tehdit Kokpiti (`/admin/security`):
- **Canlı Güvenlik Log Akışı (Tab 1):** Günlük flat JSON dosyasındaki olayları dinamik olarak tabloya döker, serbest takvim seçicisi (`date`), hızlı kısayollar (`Bugün`, `Dün`) ve saat/dakika aralığı (`time_start` - `time_end` HH:mm Europe/Istanbul) ile filtreler. Tek tıkla JSON detayı inceleme modalı (`openDetailModal`), tekil log satırını silme (`destroyLogEntry`), tüm günlük dosyayı temizleme (`clearLogsForDate`) ve ham Logsign JSON indirme (`exportJson`) yeteneklerine sahiptir. Dosya bulunamadığında kullanıcı dostu sarı durum uyarısı gösterilir.
- **Canlı 10 Kabul Testi Laboratuvarı (Tab 2):** IT ekibinin istediği 10 kritik saldırı senaryosunu hem yerel ortamda hem sunucuda tek bir butona basarak (`Tüm 10 Testi Canlı Koştur`) çalıştırır. Aktif admin oturumunu ve CSRF token'larını sahte login denemelerinden korumak için testler arka planda izole bir CLI sürecinde (`php artisan security:test-acceptance --json`) koşturulur (0 oturum düşmesi, %100 oturum güvenliği). Test sonuçlarını yeşil kartlar (`✅ GEÇTİ`), harcanan milisaniye ve üretilen ham SIEM JSON örneğiyle görselleştirir.
- **Güvenlik & SIEM Mimarisi (Tab 3):** Logsign collector bağlantısı, rsyslog akışı, brute force limitleri, CSP politikası ve KVKK `[REDACTED]` maskeleme kurallarını IT ekibine görsel olarak sunar.
- **Arayüz & Rota:** `/admin/security` (`SecurityMonitoringController.php`, `resources/js/Pages/Admin/Security/Index.vue`).
- **Menü Erişimi:** `AuthenticatedLayout.vue` -> `⚙️ Sistem Yönetimi` -> `🚨 Güvenlik & SIEM Kokpiti (G1-G5)`.














