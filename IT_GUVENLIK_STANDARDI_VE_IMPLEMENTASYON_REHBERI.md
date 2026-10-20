# KÖKSAN BİLGİ GÜVENLİĞİ VE SIEM STANDARDI UYGULAMA REHBERİ
**Kurumsal Web Uygulamaları Güvenlik Altyapısı, Merkezi SIEM Loglama ve 10 Kabul Testi Entegrasyon Kılavuzu**

> **Bu Belgenin Amacı:**  
> Köksan bünyesindeki tüm Laravel/Vue tabanlı web projelerinde (Workflow, KYS, Portal vb.) IT Bilgi Güvenliği Denetimi (G1 - G5 Kuralları) gereksinimlerini karşılamak; ortak `/admin/security` kokpit arayüzü, standart flat JSON (NDJSON) SIEM loglama altyapısı ve 10/10 kabul testi laboratuvarını birebir aynı standartta kurmaktır.  
> Bu belgeyi projenizdeki **Antigravity AI Agent**'ına vererek sırasıyla tüm adımları projenize entegre ettirebilirsiniz.

---

## 📋 İÇİNDEKİLER VE MİLESTONE PLANI

- [Milestone 0: Genel Mimari ve Gereksinimler (G1 - G5)](#milestone-0-genel-mimari-ve-gereksinimler-g1---g5)
- [Milestone 1: Ortam Değişkenleri ve Konfigürasyon (.env & config)](#milestone-1-ortam-değişkenleri-ve-konfigürasyon-env--config)
- [Milestone 2: Güvenlik Çekirdeği (Enums, Contracts, Sanitizer & Logger)](#milestone-2-güvenlik-çekirdeği-enums-contracts-sanitizer--logger)
- [Milestone 3: Tehdit Tespiti ve WAF Motoru (SQLi, XSS, Scanner)](#milestone-3-tehdit-tespiti-ve-waf-motoru-sqli-xss-scanner)
- [Milestone 4: Güvenlik Kalkanları ve Ara Katmanlar (Middleware & CSP)](#milestone-4-güvenlik-kalkanları-ve-ara-katmanlar-middleware--csp)
- [Milestone 5: Olay Dinleyicileri (Auth Subscribers & DB Error Listener)](#milestone-5-olay-dinleyicileri-auth-subscribers--db-error-listener)
- [Milestone 6: 10 Kabul Testi ve Canlı Penetrasyon Laboratuvarı](#milestone-6-10-kabul-testi-ve-canlı-penetrasyon-laboratuvarı)
- [Milestone 7: Admin Güvenlik Kokpiti (Controller, Routes & Vue UI)](#milestone-7-admin-güvenlik-kokpiti-controller-routes--vue-ui)
- [Milestone 8: Öğrenilen Kritik Dersler ve Gotchas (Tuzaklar ve Çözümleri)](#milestone-8-öğrenilen-kritik-dersler-ve-gotchas-tuzaklar-ve-çözümleri)
- [Milestone 9: Doğrulama ve Canlı Test Kontrol Listesi](#milestone-9-doğrulama-ve-canlı-test-kontrol-listesi)

---

## MILESTONE 0: GENEL MİMARİ VE GEREKSİNİMLER (G1 - G5)

IT Bilgi Güvenliği ekibinin talep ettiği 10 temel denetim maddesi ve log karşılıkları:

| Madde | Gereksinim Adı | Beklenen Olay Tipi (`event_type`) | Açıklama / Kural |
| :---: | :--- | :--- | :--- |
| **G1** | Başarısız Giriş | `auth.login_failed` | Hatalı şifre denemesi loglanır, şifre maskelenir (`[REDACTED]`), kullanıcı varsa `user_exists: true`. |
| **G2** | Hesap Kilitleme | `auth.account_locked` | Aynı hesaba 15 dk içinde 5 hatalı denemede hesap kilitlenir. |
| **G2** | Giriş Engelleme | `auth.login_blocked` | Kilitli hesaba 6. denemede şifre kontrol edilmeden `HTTP 429` dönülür. |
| **G2** | IP Engelleme | `auth.ip_blocked` | Aynı IP'den 15 dk içinde 20 başarısız denemede IP bloklanır (Credential Stuffing kalkanı). |
| **G3** | SQL Injection | `attack.sqli_pattern` / `attack.scanner_user_agent` | Gelen istek parametrelerinde SQLi veya `sqlmap` aracı tespiti. |
| **G3** | XSS Taraması | `attack.xss_pattern` | `<script>` veya inline JS event payload tespiti. |
| **G3** | Dizin Taraması | `attack.scanning_behavior` / `attack.scanner_user_agent` | `gobuster`/`nikto` veya hızlı 404 dizin tarama tespiti. |
| **G1** | CSRF Uyuşmazlığı | `session.csrf_mismatch` | Geçersiz CSRF token ile gelen istek `HTTP 419` ile durdurulur ve loglanır. |
| **G4** | Yetkisiz Erişim | `authz.access_denied` | `HTTP 403 Forbidden` durumunda yetkisiz kaynak erişimi loglanır. |
| **G5** | CSP İhlali | `attack.csp_violation` | Tarayıcının `/security/csp-report` uç noktasına attığı ihlal raporu `HTTP 204` ile loglanır. |
| **G3** | DB Syntax Hatası | `attack.db_syntax_error` | Hatalı SQL enjeksiyonunun tetiklediği `42xxx` syntax hataları parametreler gizlenerek loglanır. |

### Log Depolama Standardı
- **Format:** Tek satır JSON (NDJSON / JSON Lines).
- **Konum:** `storage/logs/security-YYYY-MM-DD.log` (Günlük rotasyon, 90 gün saklama).
- **Veritabanı Yükü:** Sıfır SQL tablosu (Veritabanını şişirmemek ve kilitlenmeleri önlemek için flat JSON kullanılır).
- **SIEM Uyumluluğu:** Splunk, ElasticSearch, Logsign, Wazuh ve Microsoft Sentinel ile doğrudan uyumludur; istendiğinde UDP Syslog RFC-5424 ile merkezi SIEM sunucusuna anlık fırlatılır.

---

## MILESTONE 1: ORTAM DEĞİŞKENLERİ VE KONFİGÜRASYON (.env & config)

### 1. `.env` Dosyasına Eklenecek Parametreler
Her proje kendi adını ve çalışma ortamını tanımlar:

```dotenv
# =====================================================================
# KÖKSAN IT BİLGİ GÜVENLİĞİ VE SIEM STANDARDI
# =====================================================================
SECURITY_APP_NAME=koksan-workflow           # Projenize göre: koksan-kys, koksan-portal vb.
SECURITY_LOG_CHANNEL=security
SECURITY_LOGIN_USERNAME_FIELD=email
SECURITY_LOGIN_MAX_ACCOUNT_ATTEMPTS=5
SECURITY_LOGIN_MAX_IP_ATTEMPTS=20
SECURITY_LOGIN_DECAY_SECONDS=900             # 15 Dakika (15 * 60)
SECURITY_INSPECTION_ENABLED=true
SECURITY_INSPECTION_MODE=monitor             # 'monitor' (önerilen) veya 'block' (403)
SECURITY_CSP_ENABLED=true
SECURITY_CSP_REPORT_ONLY=true                # Canlıyı kırmamak için raporlama modunda çalışır

# İsteğe Bağlı: Doğrudan Merkezi SIEM Sunucusuna UDP Syslog Akışı
# SIEM_SYSLOG_HOST=10.0.0.50
# SIEM_SYSLOG_PORT=514
```

### 2. `config/logging.php` Güncellemesi
`'channels'` dizisi içine `'security'`, `'security_file'` ve `'security_syslog'` kanalları eklenir:

```php
'channels' => [
    // ... mevcut stack, single kanalları ...

    'security' => [
        'driver' => 'stack',
        'channels' => array_values(array_filter([
            'security_file',
            env('SIEM_SYSLOG_HOST') ? 'security_syslog' : null,
        ])),
        'ignore_exceptions' => true,
    ],

    'security_file' => [
        'driver' => 'daily',
        'path' => storage_path('logs/security.log'),
        'level' => 'debug',
        'days' => 90,
        'formatter' => \App\Security\Logging\SecurityEventJsonFormatter::class,
    ],

    'security_syslog' => [
        'driver' => 'monolog',
        'level' => 'debug',
        'handler' => \Monolog\Handler\SyslogUdpHandler::class,
        'handler_with' => [
            'host' => env('SIEM_SYSLOG_HOST'),
            'port' => (int) env('SIEM_SYSLOG_PORT', 514),
            'facility' => LOG_AUTH,
            'ident' => env('SECURITY_APP_NAME', 'koksan-app'),
            'rfc' => \Monolog\Handler\SyslogUdpHandler::RFC5424,
        ],
        'formatter' => \App\Security\Logging\SecurityEventJsonFormatter::class,
        'formatter_with' => ['appendNewline' => false],
    ],
],
```

### 3. `config/security-monitoring.php` Dosyasının Oluşturulması
Hassas alanların (şifre, kredi kartı, TC no vb.) loglara yazılmasını engelleyen ve eşik değerleri tutan ana ayar dosyası:

```php
<?php

return [
    'log_channel' => env('SECURITY_LOG_CHANNEL', 'security'),

    'sensitive_fields' => [
        'password', 'password_confirmation', 'current_password', 'new_password',
        '_token', 'token', 'access_token', 'refresh_token', 'api_key', 'secret',
        'otp', 'two_factor_code', 'recovery_code',
        'card_number', 'cvv', 'iban', 'tckn', 'tc_kimlik_no', 'tc_no', 'identity_no',
    ],

    'logging' => [
        'application' => env('SECURITY_APP_NAME', 'koksan-app'),
        'max_value_length' => 200,
        'pseudonymize_usernames' => (bool) env('SECURITY_PSEUDONYMIZE_USERNAMES', false),
    ],

    'login_throttle' => [
        'username_field' => env('SECURITY_LOGIN_USERNAME_FIELD', 'email'),
        'max_account_attempts' => (int) env('SECURITY_LOGIN_MAX_ACCOUNT_ATTEMPTS', 5),
        'max_ip_attempts' => (int) env('SECURITY_LOGIN_MAX_IP_ATTEMPTS', 20),
        'decay_seconds' => (int) env('SECURITY_LOGIN_DECAY_SECONDS', 900),
    ],

    'inspection' => [
        'enabled' => (bool) env('SECURITY_INSPECTION_ENABLED', true),
        'mode' => env('SECURITY_INSPECTION_MODE', 'monitor'),
        'exempt_fields' => ['body_html', 'description', 'notes'],
    ],

    'csp' => [
        'enabled' => (bool) env('SECURITY_CSP_ENABLED', true),
        'report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', true),
        'report_uri' => '/security/csp-report',
        'policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; style-src 'self' 'unsafe-inline' https:; font-src 'self' data: https:; img-src 'self' data: blob: https:; connect-src 'self' https:; frame-ancestors 'self';",
    ],
];
```

---

## MILESTONE 2: GÜVENLİK ÇEKİRDEĞİ (Enums, Contracts, Sanitizer & Logger)

Uygulamanızda `app/Security/` klasör hiyerarşisi oluşturulur:

```
app/Security/
├── Auth/
│   ├── LoginFailureState.php
│   └── RateLimiterLoginAttemptGuard.php
├── Config/
│   ├── CspSettings.php
│   ├── InspectionSettings.php
│   ├── LoggingSettings.php
│   └── LoginThrottleSettings.php
├── Contracts/
│   ├── LoginAttemptGuard.php
│   ├── PayloadDetector.php
│   ├── RequestContextProvider.php
│   └── SecurityEventLogger.php
├── Detection/
│   ├── AbstractSignatureDetector.php
│   ├── DetectionResult.php
│   ├── InputNormalizer.php
│   ├── InspectionFinding.php
│   ├── PayloadInspector.php
│   ├── ScannerUserAgentDetector.php
│   ├── SqlInjectionDetector.php
│   └── XssDetector.php
├── Enums/
│   ├── SecurityEventType.php
│   └── Severity.php
├── Http/
│   ├── Controllers/
│   │   └── CspReportController.php
│   └── Middleware/
│       ├── AssignRequestId.php
│       ├── ContentSecurityPolicy.php
│       ├── InspectRequestPayload.php
│       ├── MonitorSecurityResponses.php
│       └── ThrottleLoginAttempts.php
├── Listeners/
│   └── AuthenticationEventSubscriber.php
└── Logging/
    ├── LaravelRequestContextProvider.php
    ├── PsrSecurityEventLogger.php
    ├── SecurityEventJsonFormatter.php
    └── ValueSanitizer.php
```

### Önemli Bileşen Detayları:

1. **`ValueSanitizer.php` (Maskeleme Kalkanı):**  
   Loglanmak istenen dizideki `password`, `_token`, `card_number` vb. alanları otomatik tespit eder ve loglara asla açık metin yazmaz; yerine `[REDACTED]` basar.
2. **`LaravelRequestContextProvider.php` (Korelasyon ve İstek Bağlamı):**  
   Her log satırına şu alanları otomatik enjekte eder:
   - `@timestamp`: ISO-8601 UTC mikrosaniye hassasiyetli zaman (`2026-10-20T12:00:00.123Z`).
   - `request_id`: `X-Request-Id` başlığıyla istemciye de dönen benzersiz UUID.
   - `source_ip`: WAF/Proxy arkasındaki gerçek istemci IP'si.
   - `method`, `host`, `path`: İstek yapılan HTTP metodu ve fiziksel URL.
   - `route`: Tanımlı ise rota adı (Route Name).
   - `user_id`: Kullanıcı oturum açmışsa veritabanındaki ID numarası, misafir/anonim ise `null`.
3. **`RateLimiterLoginAttemptGuard.php` (Eşik Sayacı):**  
   Laravel'in `RateLimiter` önbelleğini kullanarak hesap bazlı (`security:login:account:{hash}`) ve IP bazlı (`security:login:ip:{ip}`) kilitleri yönetir.  
   > ⚠️ **Kritik Kural:** Eşik tespiti katı `===` değil, mutlaka `>=` (`$accountAttempts >= $this->settings->maxAccountAttempts`) olmalıdır!

---

## MILESTONE 3: TEHDİT TESPİTİ VE WAF MOTORU (SQLi, XSS, Scanner)

Uygulama katmanında WAF (Web Application Firewall) imza denetimi sağlayan servisler:

1. **`SqlInjectionDetector.php`:**
   - Regex desenleri: `union select`, `or 1=1`, `sleep(\d+)`, `waitfor delay`, `exec(xp_)`, `--`, `/*...*/`.
2. **`XssDetector.php`:**
   - Regex desenleri: `<script`, `javascript:`, `onerror=`, `onload=`, `eval\(`, `document.cookie`.
3. **`ScannerUserAgentDetector.php`:**
   - Otomatik saldırı araçları imzaları: `sqlmap`, `nikto`, `gobuster`, `dirbuster`, `nmap`, `wpscan`, `acunetix`, `nessus`.
4. **`InputNormalizer.php`:**
   - URL decode, hex decode ve HTML entity çözümleriyle gizlenmiş (obfuscated) saldırı kodlarını temizleyip algılar.

---

## MILESTONE 4: GÜVENLİK KALKANLARI VE ARA KATMANLAR (Middleware & CSP)

### 1. `AssignRequestId.php`
En dış katmanda çalışır. İstekte `X-Request-Id` varsa onu alır, yoksa yeni bir UUID v4 üretip hem `$request->attributes` hem de `$response->headers` içine ekler.

### 2. `ThrottleLoginAttempts.php`
`POST /login` rotasına takılır:
- İstek geldiğinde kullanıcının veya IP'nin bütçesi dolmuş mu (`secondsUntilAllowed > 0`) kontrol eder.
- Dolmuşsa şifreyi bile doğrulamadan anında `auth.login_blocked` loglar ve `HTTP 429 Too Many Requests` döner.

### 3. `InspectRequestPayload.php`
Gelen isteklerin query string ve form body verilerini WAF imza motorundan geçirir. Bir bulgu varsa `attack.sqli_pattern` veya `attack.xss_pattern` logu üretir.

### 4. `ContentSecurityPolicy.php`
Yanıt başlıklarına `Content-Security-Policy` veya `Content-Security-Policy-Report-Only` başlığını basar; tarayıcı ihlal raporlarını `/security/csp-report` adresine yönlendirir.

### 5. `MonitorSecurityResponses.php`
Uygulamanın ürettiği yanıt kodlarını denetler:
- `419`: `session.csrf_mismatch`
- `403`: `authz.access_denied`
- `429`: `rate_limit_exceeded`

### 6. Laravel 11 `bootstrap/app.php` Middleware Kaydı:
```php
->withMiddleware(function (Middleware $middleware): void {
    // 1. En dışta istek korelasyon ID'si (X-Request-Id)
    $middleware->prepend(\App\Security\Http\Middleware\AssignRequestId::class);

    // 2. Güvenlik yanıt izleme, WAF denetimi ve CSP başlığı
    $middleware->append([
        \App\Security\Http\Middleware\MonitorSecurityResponses::class,
        \App\Security\Http\Middleware\InspectRequestPayload::class,
        \App\Security\Http\Middleware\ContentSecurityPolicy::class,
    ]);

    // 3. Giriş brute-force kalkanı takma adı
    $middleware->alias([
        'security.login' => \App\Security\Http\Middleware\ThrottleLoginAttempts::class,
    ]);

    // 4. CSRF Korumasından muaf tutulacak rotalar (CSP Rapor Uç Noktası)
    $middleware->validateCsrfTokens(except: [
        'security/csp-report',
    ]);
})
```

---

## MILESTONE 5: OLAY DİNLEYİCİLERİ (Auth Subscribers & DB Error Listener)

### 1. `AuthenticationEventSubscriber.php`
Laravel'in çekirdek auth event'lerine abone olur:
- `Illuminate\Auth\Events\Failed`: Hatalı şifre denemesinde `auth.login_failed` basar. Eşik aşılmışsa `auth.account_locked` ve `auth.ip_blocked` tetikler.
- `Illuminate\Auth\Events\Login`: Başarılı girişte `RateLimiter` sayacını sıfırlar ve `auth.login_success` basar.
- `Illuminate\Auth\Events\Logout`: Çıkış yapıldığında `auth.logout` basar.

### 2. `DatabaseQueryErrorListener.php`
`Illuminate\Database\Events\QueryExecuted` veya veritabanı hatalarında SQLSTATE `42xxx` (syntax hatası) oluştuğunda `attack.db_syntax_error` loglar. SQL içindeki parametreleri maskeler.

---

## MILESTONE 6: 10 KABUL TESTİ VE CANLI PENETRASYON LABORATUVARI

### 1. `app/Services/SecurityTestRunnerService.php`
Uygulama üzerinde 10 adet mikro penetrasyon testi koşturan test motorudur.  
**Önemli Fonksiyonlar:**
- `makePostRequest()`: Alt istekleri (sub-request) oluştururken session cookie'sini `app('encrypter')->encrypt($session->getId(), false)` ile şifreler, `HTTP_X_CSRF_TOKEN` başlığı basar.
- `runAllTests()`: Tarayıcıdan giriş yapmış admin kullanıcısının oturumunun düşmemesi için `RedirectIfAuthenticated::class` ve `'guest'` alias'ını geçici olarak devreden çıkarır, test bitince geri yükler.
- Her test ürettiği benzersiz hedef e-posta ve IP ile log dosyasından teyit alır.

### 2. `app/Console/Commands/RunSecurityAcceptanceTestsCommand.php`
Terminalden çalıştırma komutu:
```bash
php artisan security:test-acceptance
# veya JSON formatında:
php artisan security:test-acceptance --json
```

---

## MILESTONE 7: ADMIN GÜVENLİK KOKPİTİ (Controller, Routes & Vue UI)

### 1. Controller: `app/Http/Controllers/Admin/SecurityMonitoringController.php`
- `index()`: Seçilen tarihteki (`storage/logs/security-YYYY-MM-DD.log`) logları okur, istatistikleri hesaplar, arama/filtreleme/sayfalama uygular ve Inertia sayfasına döner.
- `runAcceptanceTests()`: Testleri arka planda izole CLI sürecinde veya servis üzerinden çalıştırıp anında JSON raporu döner.
- `exportJson()`: Seçili günün tüm loglarını NDJSON formatında indirir.
- `clearLogs()`: Log dosyasını sıfırlar.

### 2. Rotalar (`routes/web.php`):
```php
// 1. Kamuya Açık CSP İhlal Raporu (Session & CSRF yükünden arındırılmış)
Route::post('security/csp-report', \App\Security\Http\Controllers\CspReportController::class)
    ->withoutMiddleware([
        \App\Http\Middleware\HandleInertiaRequests::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    ])
    ->name('security.csp-report');

// 2. Admin Güvenlik Kokpiti (Admin yetkisi gerektirir)
Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:view_admin_panel'])->group(function () {
    Route::prefix('security')->name('security.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\SecurityMonitoringController::class, 'index'])->name('index');
        Route::post('/run-tests', [\App\Http\Controllers\Admin\SecurityMonitoringController::class, 'runAcceptanceTests'])->name('run-tests');
        Route::post('/clear-logs', [\App\Http\Controllers\Admin\SecurityMonitoringController::class, 'clearLogs'])->name('clear-logs');
        Route::delete('/log-entry/{requestId}', [\App\Http\Controllers\Admin\SecurityMonitoringController::class, 'destroyLogEntry'])->name('destroy-log-entry');
        Route::get('/export-json', [\App\Http\Controllers\Admin\SecurityMonitoringController::class, 'exportJson'])->name('export-json');
    });
});
```

### 3. Arayüz: `resources/js/Pages/Admin/Security/Index.vue`
> 💎 **UI'ın Pikseli Pikseline (%100 Birebir) Aynı Olması İçin:**  
> `Index.vue` bileşeni Workflow projesine özel **hiçbir veritabanı veya özel model bağımlılığı içermez**. Rotaları Ziggy (`route('admin.security.xxx')`) üzerinden dinamik çağırır ve standart `AuthenticatedLayout` kullanır.  
> Bu nedenle diğer projelerde AI'ın arayüzü sıfırdan tahminle baştan yazması yerine, bu dosyanın **doğrudan kaynak projeden kopyalanması** %100 aynı UI görünümünü (3 sekme, filtreler, modal JSON görüntüleyici, canlı test lab kartları) garanti eder.
>
> **Hızlı Kopyalama Komutu:**
> ```bash
> # Linux / Sunucu Ortamında:
> cp /var/www/kys_koksan/workflow_project/resources/js/Pages/Admin/Security/Index.vue resources/js/Pages/Admin/Security/Index.vue
>
> # Windows (PowerShell) Ortamında:
> Copy-Item "c:\xampp\htdocs\workflow\resources\js\Pages\Admin\Security\Index.vue" -Destination "resources\js\Pages\Admin\Security\Index.vue" -Force
> ```

Arayüzün sunduğu 3 ana sekme:
- **Sekme 1 (Canlı Log Akışı):** Canlı istatistik sayaçları (Toplam, Başarısız Giriş, Kilitli Hesap, Engellenen IP, SQLi, XSS, Scanner, CSP, DB Hatası), Tarih/Saat/Ciddiyet filtreleri, Arama çubuğu, Renkli Severity rozetleri, Modal ile formatlanmış JSON inceleme, Dışa Aktarma (Export NDJSON) butonu.
- **Sekme 2 (IT Güvenlik Kabul Test Laboratuvarı):** Canlı **"Tüm 10 Testi Canlı Koştur"** butonu, interaktif ilerleme durumu, süre sayaçları (ms), Gereksinim & Beklenen Olay rozetleri, Başarılı/Başarısız teşhis detayları, üretilen örnek JSON log kartı.
- **Sekme 3 (Sistem Yapılandırması):** Aktif `.env` parametreleri, Rate Limit eşikleri, CSP kuralları, WAF modu ve maskelenen hassas alanlar tablosu.

---

## MILESTONE 8: ÖĞRENİLEN KRİTİK DERSLER VE GOTCHAS (Tuzaklar ve Çözümleri)

Geliştirme ve canlı sunucu testlerinde bizzat tecrübe edilen ve diğer projelerde **zaman kaybettirmemesi için mutlaka bilinmesi gereken püf noktaları**:

1. **`guest` Middleware ve Oturum Düşme Riski:**  
   Admin panelinden giriş yapmış bir kullanıcı "10 Testi Koştur" dediğinde, alt istekler `/login` rotasına gittiğinde Laravel'in `guest` ara katmanı admini `/dashboard`'a yönlendirir (HTTP 302). Bu yüzden test motorunda hem `RedirectIfAuthenticated::class` hem de route alias olan `'guest'` mutlaka pass-through edilmelidir!
2. **Alt İsteklerde Şifrelenmemiş Session Cookie:**  
   `makePostRequest` fonksiyonunda `$session->getId()` ham olarak verilirse, Laravel'in `EncryptCookies` ara katmanı çerezi çözemez ve yeni boş session üretir; bu da HTTP 419 CSRF hatasına yol açar. Session ID mutlaka `app('encrypter')->encrypt($session->getId(), false)` ile şifrelenmeli ve `HTTP_X_CSRF_TOKEN` başlığı eklenmelidir.
3. **CSP Rapor Uç Noktasında HTTP 500 Hatası:**  
   Tarayıcının arka planda gönderdiği `POST /security/csp-report` isteği JSON payload taşır ve session taşımaz. Eğer bu rota Inertia, StartSession veya CSRF middleware grubu içine dahil olursa HTTP 500 / 419 verir. Bu rota mutlaka `routes/web.php`'de `withoutMiddleware([...])` ile session/Inertia yükünden arındırılmalı ve HTTP 204 dönmelidir.
4. **Throttle Karşılaştırma Eşiği (`>=` vs `===`):**  
   `RateLimiterLoginAttemptGuard` içinde hesap kilit durumunu incelerken `$accountAttempts === 5` yazılırsa, araya giren paralel bir istekte sayaç 6 olduğunda kilit olayı tetiklenmeyebilir. Mutlaka `$accountAttempts >= 5` kullanılmalıdır.
5. **Oturum Açıkken vs Kapalıyken `user_id` Durumu:**  
   `auth.login_failed` olayında `user_id`'nin `null` gelmesi bir hata değil, uluslararası SIEM standardıdır. Çünkü şifre yanlış girilmiştir ve istek sahibi henüz sisteme kabul edilmemiş bir misafirdir. Kullanıcının veritabanında var olduğu `user_exists: true` ile belirtilir. Oturum açıldıktan sonraki tüm işlemlerde (403, SQLi, XSS) `user_id` kullanıcının gerçek ID'si ile dolar.

---

## MILESTONE 9: DOĞRULAMA VE CANLI TEST KONTROL LİSTESİ

Kurulumu tamamladıktan sonra sırasıyla şu adımları uygulayın:

### 1. Adım: Önbellekleri Temizleyin
```bash
php artisan optimize:clear
```

### 2. Adım: Terminalden Kabul Testlerini Koşturun
```bash
php artisan security:test-acceptance
```
Beklenen Konsol Çıktısı:
```
====================================================================
🛡️ IT BİLGİ GÜVENLİĞİ 10 KABUL TESTİ VE SIEM DOĞRULAMA SUITE'İ
====================================================================
Tarih: 20.10.2026 15:46:19
Süre : 8879.4 ms
Sonuç: 10 / 10 (% 100 Başarı)

  [✅ GEÇTİ] #1 Başarısız Giriş Denemesi (719.9 ms)
  [✅ GEÇTİ] #2 Hesap Kilidi & Brute Force Kalkanı (1455.6 ms)
  [✅ GEÇTİ] #3 Credential Stuffing & IP Engeli (5614.5 ms)
  [✅ GEÇTİ] #4 SQL Injection & sqlmap Tespiti (1030 ms)
  [✅ GEÇTİ] #5 XSS (Cross-Site Scripting) Taraması (145.8 ms)
  [✅ GEÇTİ] #6 Dizin Taraması & Yüksek Hata Oranı (775.9 ms)
  [✅ GEÇTİ] #7 CSRF Token Uyuşmazlığı (36.5 ms)
  [✅ GEÇTİ] #8 Yetkisiz Erişim (403 Forbidden) (21 ms)
  [✅ GEÇTİ] #9 Content Security Policy (CSP) İhlali (49.5 ms)
  [✅ GEÇTİ] #10 Veritabanı Syntax Hatası Tespiti (23.5 ms)
====================================================================
```

### 3. Adım: Tarayıcıdan Canlı Kokpiti Test Edin
1. Tarayıcıdan projenizin admin paneline giriş yapın.
2. `https://your-domain.com/{app_prefix}/admin/security` adresine gidin.
3. **"🧪 IT Güvenlik Kabul Test Laboratuvarı"** sekmesine tıklayın.
4. **"Tüm 10 Testi Canlı Koştur"** butonuna basın.
5. 10 testin tamamının yeşil `✅ GEÇTİ` olduğunu ve `10 / 10 (%100)` başarı oranını teyit edin.
6. **"📊 Canlı Güvenlik Logları"** sekmesine dönüp üretilen yeni olayların gerçek zamanlı olarak tabloya düştüğünü gözlemleyin.

---

### 💬 İş Arkadaşlarımızın Kendi Antigravity Agent'larına Vereceği Prompt

UI'ın ve tüm güvenlik altyapısının %100 birebir aynı olması için arkadaşlarınız kendi projelerinde şu promptu verebilirler:

```text
Lütfen @IT_GUVENLIK_STANDARDI_VE_IMPLEMENTASYON_REHBERI.md belgesindeki adımları ve kurumsal güvenlik mimarisini projemize sırasıyla uygula.

Özellikle dikkat edilecekler:
1. `app/Security/` klasörünü ve `resources/js/Pages/Admin/Security/Index.vue` dosyasını kaynak projedeki (Workflow) orijinal kodlarıyla birebir aynı şekilde oluştur (Böylece UI ve çalışma mantığı %100 aynı standartta olsun).
2. `config/logging.php`, `config/security-monitoring.php` ve `bootstrap/app.php` ara katmanlarını rehberdeki gibi yapılandır.
3. `routes/web.php` içine `/admin/security` ve `/security/csp-report` rotalarını ekle.
4. `php artisan security:test-acceptance` komutunun 10/10 (%100) başarıyla geçtiğini doğrula.
```

---

### 🎉 Sonuç
Bu standart uygulandığında; Köksan bünyesindeki tüm web projeleri kurumsal siber güvenlik denetimlerine %100 hazır, SIEM entegrasyonu tamamlanmış, aynı yönetim paneline (`/admin/security`) ve aynı yüksek dayanıklılık standartlarına sahip olacaktır.
