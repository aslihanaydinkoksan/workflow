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









