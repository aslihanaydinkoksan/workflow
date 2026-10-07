# KÖKSAN İŞ AKIŞI SİSTEMİ - UÇTAN UCA TEST VE DOĞRULAMA KILAVUZU
**Tarih:** 02.10.2026  
**Konu:** Levha Numune Süreci (Workflow #7) - Yeni Termin Seçimi, Zorunlu Açıklama & Revizyon Döngüsü Test Rehberi

---

## 🔑 1. TEST KULLANICI GİRİŞ BİLGİLERİ

| Rol / Görev | Ad Soyad | E-Posta | Şifre |
| :--- | :--- | :--- | :--- |
| **Satış Temsilcisi (Talep Sahibi)** | Ahmet Aslan | `ahmet_aslan@gmail.com` | `123456` |
| **Levha Üretim Müdürü (İşletme Onay)** | Aykut ÇELİK | `aykut.celik@koksan.com` | `123456` |
| **Levha Kalite & Laboratuvar Sorumlusu** | Mustafa YILMAZ | `mustafa.yilmaz@koksan.com` | `123456` |
| **Sistem Yöneticisi (Admin)** | Sistem Yöneticisi | `admin@test.com` | `password` |

---

## 💻 2. BİLGİSAYAR AÇILDIĞINDA SİSTEMİ BAŞLATMA ADIMLARI
Bilgisayarınızı yeniden başlattıktan sonra projenin çalışması için:
1. **XAMPP Kontrol Panelini** açın $\rightarrow$ **Apache** ve **MySQL** servislerini `Start` edin.
2. Terminal / PowerShell'de `c:\xampp\htdocs\workflow` dizinindeyken:
   ```powershell
   php artisan serve
   ```
   *(Varsayılan olarak http://127.0.0.1:8000 adresinde yayına başlar).*
3. Tarayıcınızdan **http://127.0.0.1:8000** adresini açın.

---

## 🚀 3. ADIM ADIM TEST SENARYOSU

### ADIM 1: Talebi Başlatma (Ahmet Aslan)
1. **http://127.0.0.1:8000/login** adresine gidin.
2. `ahmet_aslan@gmail.com` / `123456` ile giriş yapın.
3. Menüden **Taleplerim / Yeni Talep** ([/processes](http://127.0.0.1:8000/processes)) sayfasına geçin.
4. **"Levha Numune Talep ve Üretim Süreci"** kartındaki `[Talebi Başlat]` butonuna tıklayın.
5. Formu doldurun:
   - **Müşteri / Cari:** ALMER LTD
   - **Genişlik:** 422 mm
   - **Kalınlık:** 550 mikron
6. `[Talebi Başlat ve Gönder]` butonuna basın.
7. Sistem sizi doğrudan **Süreç Takipçisi (Tracker)** ekranına alacaktır. Sıranın Aykut Çelik'te olduğunu görün.

---

### ADIM 2: Yeni Termin Seçimi & Revizyon İade Testi (Aykut Çelik)
1. Sağ üstten **Çıkış Yap** deyin (veya yeni bir Gizli Sekme açın).
2. `aykut.celik@koksan.com` / `123456` ile giriş yapın.
3. **İş Listem** ([/tasks](http://127.0.0.1:8000/tasks)) sayfasında bekleyen **"LEVHA İşletme Kabul ve Termin Onayı"** görevine girin (`[İşlemi Yap →]`).
4. **Yeni Çoklu Termin Seçicisi Testi:**
   - Sarı renkli *"İşletme Kabul ve Termin Bildirimi"* kutusuna bakın.
   - Takvimden başlangıç ve bitiş tarihi seçin veya hızlı **`[+14 Gün]`** butonuna tıklayın; yanındaki gün sayacının anında `⏱️ 14 Takvim Günü` olarak hesaplandığını görün.
   - İsterseniz **`[+ Yeni Parti / Tarih Aralığı Ekle]`** butonuna basarak 2. parti termin aralığı ve parti notu ekleyin.
5. **Zorunlu Açıklama & Revizyon Testi:**
   - Alttaki **`[🔄 Revize İste (Geri Gönder)]`** butonuna basın.
   - Açılan kutuda hiçbir şey yazmadan göndermeyi deneyin; sistemin gerekçe girmeyi mecburi kıldığını görün.
   - Şimdi geçerli bir revizyon gerekçesi yazın:  
     *“Lütfen mikron kalınlığını 550+50 toleransıyla güncelleyiniz.”* ve onaylayın.

> 💡 **Önemli Not ("Şartlı Kabul" vs "Revizyon İstendi"):**
> - **"Şartlı Kabul"**: Bir **ONAY** türüdür. İşletme talebi üretime kabul eder (süreç ileriye, üretime akar) fakat belirli tolerans/şartlar koyar.
> - **"Revizyon İstendi"**: Bir **DÜZELTME / GERİ İADE** işlemidir. Açılır kutuda ne seçili olursa olsun, alttaki **`[🔄 Revize İste (Geri Gönder)]`** butonuna bastığınız anda sistem kararı otomatik olarak **"Revizyon İstendi"** yapar ve süreci Ahmet Aslan'a iade eder. Dolayısıyla revizyon için kutudan "Şartlı Kabul" seçmenize gerek yoktur.

---

### ADIM 3: Revizyon Görevini Düzenleme ve Tekrar Gönderme (Ahmet Aslan)
1. Tekrar `ahmet_aslan@gmail.com` hesabına dönün.
2. **Süreç Takipçisi (Tracker)** ekranına bakın:
   - Üstte artık "Onay" değil, turuncu renkli **"SIRA SİZDE & REVİZYON TALEBİ"** uyarı panosu yer alır.
   - Aykut Bey'in yazdığı gerekçe kart içerisinde alıntı olarak yer alır.
   - Yol haritasında (stepper) 1. düğümde `🔄 Revizyon Bekleniyor` rozeti görünür.
3. Turuncu renkli **`[✏️ Talebi Düzenle ve Yeniden Gönder →]`** butonuna basın.
4. **Revizyon Görev Formu Açıldığında:**
   - Formun en üstünde Aykut Bey'in yazdığı revizyon notunu içeren uyarı kartını göreceksiniz.
   - 22 alanlık form salt-okunur değil; önceki girdiğiniz tüm verilerle **düzenlenebilir** modda gelir.
   - Kalınlık alanını `550+50 µm` olarak güncelleyin.
   - En alttaki turuncu renkli **`[🔄 Revize Edildi, Tekrar Onaya Gönder]`** butonuna basın.

---

### ADIM 4: Düzeltilen Formun Kabul Edilmesi (Aykut Çelik)
1. `aykut.celik@koksan.com` hesabına geçin.
2. **İş Listem**'e gelen güncellenmiş görevi açın.
3. Ahmet'in düzelttiği `550+50 µm` değerini görün.
4. İşletme Kararını **"Kabul Edildi"** yapın, termin aralığınızı seçin ve **`[✅ Onayla ve İlerlet]`** butonuna basın.

---

### ADIM 5: Kalite Analiz Girişi ve CoA Sertifikası (Mustafa Yılmaz)
1. `mustafa.yilmaz@koksan.com` / `123456` ile giriş yapın.
2. **İş Listem** ekranındaki **"Numune Üretim & Kalite Analiz Girişi"** görevini açın.
3. Gerçekleşen laboratuvar değerlerini girin (Parti No: `P-2026-01`, Gerçekleşen Mikron: `552`, Yoğunluk: `1.34`, vb.) ve kaydedin.
4. Süreç başarıyla **"Tamamlandı"** statüsüne geçer.
5. Süreç Takipçisi ekranındaki **`[📄 Analiz Sertifikası (CoA PDF) İndir]`** butonuna tıklayarak kurumsal antetli karşılaştırmalı sertifikanızı indirin.

---

### ADIM 6: Gerekçeli Ret Testi (Opsiyonel)
- Başka yeni bir talep başlatıp Aykut Çelik ekranında **`[⛔ Reddet]`** butonuna bastığınızda, açıklama girmeden reddetmeye izin verilmediğini; açıklama girildiğinde sürecin **"Reddedildi"** olarak sonlandığını ve takipçide kırmızı uyarı panosuyla işlendiğini test edebilirsiniz.

---

### ADIM 7: Çoklu Onay & Konsensüs Testi (Multi-Approver)
Büyük işletmelerde birden fazla müdür/şef onayını test etmek için:
1. **Akış Tasarımcısı:** `/workflows/7/edit` sayfasına girip `LEVHA İşletme Kabul` düğümünü açın.
2. Atanan kadroyu `specific_users` (Aykut ÇELİK + Hakan DEMİR) ve karar politikasını **`[🤝 Tümünün Onayı Şart (Konsensüs)]`** olarak ayarlayın.
3. Ahmet Aslan ile yeni bir talep başlatın.
4. Takipçide (Tracker) **`🤝 Konsensüs: Aykut ÇELİK, Hakan DEMİR`** yazısını ve her iki yetkilinin beklediğini görün.
5. Aykut Çelik onayladığında; sürecin henüz ilerlemediğini, Aykut'un onayının geçmişe düştüğünü ve Hakan Demir'in onayının beklendiğini (`waiting`) görün.
6. Hakan Demir de onayladığında sürecin otomatik olarak Mustafa Yılmaz'ın laboratuvar analiz aşamasına geçtiğini gözlemleyin.

---

## 🛠️ OTOMATİK KONSOL TEST KOMUTLARI
Terminalden tek tıkla arka plan testlerini koşturmak isterseniz:
- **Çoklu Onay & Konsensüs Doğrulama Suite'i:**
  ```powershell
  php scratch/test_multi_approver_scenarios.php
  ```
- **Tüm Uçtan Uca Sistem Senaryoları (16 Adım):**
  ```powershell
  php scratch/run_full_system_scenarios.php
  ```
- **Revizyon & Termin Döngüsü Özel Testi:**
  ```powershell
  php scratch/test_revision_and_termin.php
  ```
