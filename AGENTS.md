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

5. **Hiyerarşi ve Makine Tanımları (`/admin/hierarchy`):**
   - `Levha Üretim İşletmesi` birimi altında `Betapak FFS 250 Thermoformer` makinesi ve `Levha Ekstrüzyon Hattı 1` düğümleri aktiftir.

---

## 3. Doğrulama ve Test Komutları
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
   - `php scratch/test_dis_gorev_e2e.php` (Tüm 10 adım uçtan uca otomatik test edilir).
   - `php artisan db:seed --class=DisGorevWorkflowSeeder`

