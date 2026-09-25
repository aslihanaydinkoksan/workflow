# LEVHA NUMUNE TALEP VE ÜRETİM SÜRECİ - KULLANICI BİLGİLENDİRME VE YÖNLENDİRME E-POSTA TASLAĞI

Bu belge, Köksan bünyesinde devreye alınan **Levha Numune Talep ve Üretim Süreci**'nin (FR-LVH-042) ilgili birimlere (Aykut Bey, Satış, Levha Üretim İşletmesi ve Kalite) duyurulması ve adım adım kullanımının tarif edilmesi amacıyla hazırlanmış resmi e-posta taslağıdır.

---

**Kime:** Aykut [Soyadı] <aykut.xxx@koksan.com>  
**Bilgi (CC):** Levha İşletme Ekibi, Satış Departmanı, Kalite Güvence Departmanı  
**Konu:** KÖKSAN Portal / Dijital Levha Numune Talep ve Üretim Süreci Canlıya Alınmıştır (FR-LVH-042 & Otomatik CoA)

---

### Merhaba Aykut Bey ve Değerli Çalışma Arkadaşlarımız,

Daha önce e-posta ve toplantılarımızda iletmiş olduğunuz talepler doğrultusunda, **Levha Numune Talep, Termin, Üretim ve Kalite Analiz Süreçleri**, Köksan Merkezi Yönetim Sistemi (KYS) ve Workflow platformumuz üzerinde **uçtan uca dijitalleştirilerek canlı ortama alınmıştır.**

Yeni sistem sayesinde kağıt/Excel üzerinden yürütülen form karmaşası son bulmuş; talebin açılmasından işletme terminine, kalite analiz sertifikasının (CoA) antetli olarak otomatik üretilmesinden 15 gün sonraki SAP sipariş dönüşüm takibine kadar tüm adımlar entegre ve şeffaf bir yapıya kavuşturulmuştur.

Aşağıda sürecin işleyişine ve kullanımına dair adım adım rehberi bulabilirsiniz:

---

### 🌐 Sisteme Erişim
Sisteme şirket içi portalimiz üzerinden tek tıkla oturum açarak erişebilirsiniz:
* **Giriş Adresi:** [https://kys.koksan.com/merkezi_yonetim_sistemi/dashboard](https://kys.koksan.com/merkezi_yonetim_sistemi/dashboard)  
*(Köksan Portal ana sayfasından **"Workflow"** uygulamasını başlattığınızda sistem sizi otomatik olarak yetkilendirecektir.)*
* **Doğrudan Akış Kataloğu:** [https://kys.koksan.com/workflow_koksan/processes](https://kys.koksan.com/workflow_koksan/processes)

---

### 📌 Adım Adım Süreç Kullanım Rehberi

#### 1. Adım: Satış Temsilcisi - Numune Talebinin Açılması (FR-LVH-042)
1. **Süreç Kataloğu** ([/processes](https://kys.koksan.com/workflow_koksan/processes)) sayfasına gidin.
2. **"Numune Talebi"** kategorisi altındaki **"Levha Numune Talep ve Üretim Süreci"** kartındaki **"Formu Doldur ve Başlat"** butonuna tıklayın.
3. Açılan resmi formda:
   * **Müşteri Cari Bilgileri:** Müşteri adı (Örn: *ALMER LTD*), ülke/bölge, sektör ve ürün grubu,
   * **Teknik Spesifikasyonlar:** Genişlik (toleransıyla), kalınlık (mikron), silikon/yüzey işlemi, lamine katman türü ve kullanım amacını (Örn: *Betapak FFS termoform peynir ambalajı*) doldurup **"Talebi Gönder"** butonuna basın.

> 📷 **[EKRAN GÖRÜNTÜSÜ 1: Süreç Kataloğu ve Levha Numune Başlatma Formu]**  
> *(Buraya formun doldurulduğu ekran görüntüsünü ekleyebilirsiniz)*

---

#### 2. Adım: Levha İşletmesi - Kabul & Zorunlu Termin Onayı
Talep açıldığı anda Levha İşletme Sorumlusu'nun e-postasına ve iş listesine görev düşer:
* **İş Listem:** [https://kys.koksan.com/workflow_koksan/tasks](https://kys.koksan.com/workflow_koksan/tasks)
* **Kritik Kural:** İşletme talebi kabul ettiğinde, sistem **termin aralığı girilmesini zorunlu tutar** (Termin girilmeden onay verilemez). Böylece satış ekibinin müşteriye vereceği termin süresi resmi olarak kayıt altına alınır.
* Reddedilmesi durumunda gerekçeli ret açıklaması ile süreç anında satışçıya bildirilir.

> 📷 **[EKRAN GÖRÜNTÜSÜ 2: Levha İşletmesi Termin Onayı Ekranı]**  
> *(Buraya işletmenin termin aralığı seçip onayladığı görev ekranını ekleyebilirsiniz)*

---

#### 3. Adım: Kalite Laboratuvarı - Numune Analiz Sonuçlarının Girilmesi
Numune üretildikten sonra kalite/laboratuvar birimine otomatik görev atanır:
1. Laboratuvar personeli ilgili görevde üretilen numuneye ait; **Parti No, Gerçekleşen Mikron, Gerçekleşen Genişlik, Çekme Dayanımı, Kopma Uzaması, Korona (Dyne) ve Görünüm** sonuçlarını forma işler ve onaylar.

> 📷 **[EKRAN GÖRÜNTÜSÜ 3: Kalite Analiz Giriş Formu]**  
> *(Laboratuvar ölçüm sonuçlarının girildiği ekran görüntüsü)*

---

#### 4. Adım: Satışçıya Bildirim ve Resmi Analiz Sertifikası (CoA PDF)
Kalite girişi tamamlandığı anda süreç otomatik olarak tamamlanır ve satış temsilcisine numunenin sevk edildiği bildirilir:
* Satış temsilcisi **Süreç Takip Ekranı** ([/processes/{id}/tracker](https://kys.koksan.com/workflow_koksan/processes/tracker)) üzerinden tek tıkla **"📄 Analiz Sertifikasını İndir (CoA)"** butonuna basarak; müşterinin istediği toleranslar ile laboratuvarda ölçülen değerleri kıyaslayan resmi, antetli Köksan Analiz Sertifikasını indirebilir ve doğrudan müşteriye iletebilir.

> 📷 **[EKRAN GÖRÜNTÜSÜ 4: Antetli Analiz Sertifikası (CoA PDF) Örneği]**  
> *(Oluşturulan karşılaştırmalı Köksan CoA PDF belgesinin ilk sayfası)*

---

#### 5. Adım: 15 Gün Sonra Otomatik Hatırlatma & SAP Sipariş Takibi
Numune sevk edildikten tam **15 gün sonra**:
* Sistem, satış temsilcisinin görev listesine otomatik olarak **"Numunenin Yolculuğu: Siparişe Döndü mü?"** takibini düşürür.
* **Geri Bildirim Ekranı:** [https://kys.koksan.com/workflow_koksan/follow-ups](https://kys.koksan.com/workflow_koksan/follow-ups)
* Satışçı tek formda:
  * **Siparişe Döndü:** Açılan kutuya ilgili **SAP Satış Sipariş Numarasını** işler.
  * **Siparişe Dönmedi:** Müşterinin fiyat, teknik veya termin konusundaki ret gerekçesini sisteme girer.
* Böylece numunelerin gerçek satışa dönüşüm oranı yönetimsel olarak raporlanabilir hale gelmiştir.

> 📷 **[EKRAN GÖRÜNTÜSÜ 5: 15 Günlük Takip ve SAP Sipariş Giriş Ekranı]**  
> *(Follow-up ekranı veya Dashboard'daki takip bildirim kutusu)*

---

Sistemi bugünden itibaren canlı olarak kullanabilir, soru veya ilave geliştirme önerileriniz olması durumunda bizimle iletişime geçebilirsiniz.

İyi çalışmalar dileriz.

**Saygılarımızla,**  
**Aslıhan AYDIN**  
OPEX / Süreç Geliştirme  
Köksan Pet ve Plastik Ambalaj San. A.Ş.
