<?php

namespace App\Services;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;

class PermissionRegistryService
{
    /**
     * Önceden tanımlı modül meta sözlüğü.
     * İleride yeni bir modül eklendiğinde buraya eklenmese bile
     * otomatik keşif motoru (auto-discovery) onu dinamik olarak üretir!
     */
    protected array $predefinedModules = [
        'reports' => [
            'id' => 'reports',
            'title' => 'İş Zekası & Power BI Raporlama',
            'description' => 'Canlı grafik panoları, widget sihirbazı, zamanlanmış e-posta dağıtımı ve Excel/PDF dökümleri',
            'icon' => '📊',
            'badge_color' => 'indigo',
            'order' => 1,
        ],
        'termin' => [
            'id' => 'termin',
            'title' => 'Termin, SLA & OTIF Yönetimi',
            'description' => 'Fabrika termin takipleri, kök nedenli erteleme/revizyon ve SLA eskalasyon kontrolleri',
            'icon' => '⏱️',
            'badge_color' => 'amber',
            'order' => 2,
        ],
        'samples' => [
            'id' => 'samples',
            'title' => 'Numune Yaşam Döngüsü & SAP S/4HANA',
            'description' => 'Numune takip boru hattı (pipeline), SAP sipariş dönüşümü ve Kalite Analiz Sertifikası (CoA PDF)',
            'icon' => '🧪',
            'badge_color' => 'emerald',
            'order' => 3,
        ],
        'travel' => [
            'id' => 'travel',
            'title' => 'Dış Görev, Seyahat & Harcırah Masraf',
            'description' => 'Seyahat talep/onay akışı, idari rezervasyonlar, harcırah avansı, masraf mahsup ve İzin Belgesi PDF',
            'icon' => '✈️',
            'badge_color' => 'blue',
            'order' => 4,
        ],
        'duty_radar' => [
            'id' => 'duty_radar',
            'title' => 'Canlı Saha & Personel Radarı (Kim Nerede?)',
            'description' => 'Fabrika dışındaki personelin (Fuar, Müşteri, Servis vb.) anlık lokasyon, görev ve vekalet takibi',
            'icon' => '📍',
            'badge_color' => 'indigo',
            'order' => 5,
        ],
        'governance' => [
            'id' => 'governance',
            'title' => 'İç Denetim, SoD, DoA & Süreç Kokpiti',
            'description' => 'Form değişiklik denetim izi (Audit Diff), Yönetici Süreç Kokpiti (Zorla müdahale/atlama) ve E-Posta Guard',
            'icon' => '🛡️',
            'badge_color' => 'rose',
            'order' => 6,
        ],
        'designer' => [
            'id' => 'designer',
            'title' => 'BPMN Akış Tasarımı & Versiyonlama',
            'description' => 'Görsel iş akışı şemaları, düğüm konfigürasyonu, versiyonlama ve canlı süreç migrasyonu',
            'icon' => '🔄',
            'badge_color' => 'purple',
            'order' => 7,
        ],
        'processes' => [
            'id' => 'processes',
            'title' => 'Süreç & Görev Operasyonları',
            'description' => 'Süreç başlatma, görev tamamlama, onay/ret verme ve görev havuzu takibi',
            'icon' => '📋',
            'badge_color' => 'cyan',
            'order' => 7,
        ],
        'system' => [
            'id' => 'system',
            'title' => 'Sistem & Organizasyon Yönetimi',
            'description' => 'Kullanıcı, departman, direktörlük ve rol matrisi tanımlamaları',
            'icon' => '⚙️',
            'badge_color' => 'slate',
            'order' => 8,
        ],
        // --- İLERİYE DÖNÜK KURUMSAL GENİŞLEME MODÜLLERİ (ROADMAP PRESETS) ---
        'procurement' => [
            'id' => 'procurement',
            'title' => 'Satın Alma, Teklif & Tedarikçi Siparişi',
            'description' => 'Satın alma talepleri, tedarikçi teklif onayları ve SAP PO senkronizasyonu',
            'icon' => '🛒',
            'badge_color' => 'emerald',
            'order' => 9,
        ],
        'warehouse' => [
            'id' => 'warehouse',
            'title' => 'Depo, Lojistik & Barkodlu WMS',
            'description' => 'Hammadde/mamul depo hareketleri, transfer fişleri ve barkodlu palet kabulü',
            'icon' => '📦',
            'badge_color' => 'teal',
            'order' => 10,
        ],
        'maintenance' => [
            'id' => 'maintenance',
            'title' => 'Bakım Onarım, Arıza & TPM Yönetimi',
            'description' => 'Makine arıza bildirimleri, planlı periyodik bakımlar ve yedek parça talepleri',
            'icon' => '🔧',
            'badge_color' => 'amber',
            'order' => 11,
        ],
        'quality' => [
            'id' => 'quality',
            'title' => 'Kalite Güvence, DÖF & Müşteri Şikayeti (CAPA)',
            'description' => 'Düzeltici/Önleyici Faaliyetler (DÖF), müşteri şikayetleri ve laboratuvar testleri',
            'icon' => '🎯',
            'badge_color' => 'rose',
            'order' => 12,
        ],
        'hr' => [
            'id' => 'hr',
            'title' => 'İnsan Kaynakları & Personel Özlük',
            'description' => 'İzin talepleri, avans, zimmet teslim ve personel işe alım/çıkış süreçleri',
            'icon' => '👥',
            'badge_color' => 'blue',
            'order' => 13,
        ],
    ];

    /**
     * Önceden tanımlı yetki Türkçe etiket ve açıklamaları.
     */
    protected array $predefinedPermissions = [
        // Raporlama
        'reports.view' => ['label' => 'Rapor Panolarını Görüntüleme', 'desc' => 'Power BI raporlama kokpitine ve mevcut panolara erişebilir.'],
        'reports.create_dashboard' => ['label' => 'Yeni Pano Oluşturma & Düzenleme', 'desc' => 'Yeni rapor panoları oluşturabilir, düzenleyebilir ve silebilir.'],
        'reports.manage_widgets' => ['label' => 'Power BI Grafik / Widget Sihirbazı', 'desc' => 'Canlı veritabanı sorguları ve dinamik formlardan yeni grafikler ekleyebilir.'],
        'reports.export_excel' => ['label' => 'Çok Sekmeli Excel (.xlsx) İndirme', 'desc' => 'Panoları çok sekmeli kurumsal Excel çalışma kitabı olarak dışa aktarabilir.'],
        'reports.export_pdf' => ['label' => 'Resmi PDF Rapor Dökümü Alma', 'desc' => 'Köksan antetli resmi PDF raporları üretebilir ve indirebilir.'],
        'reports.manage_schedules' => ['label' => 'Otomatik & Anlık E-Posta Dağıtımı', 'desc' => 'Zamanlanmış veya anlık e-posta rapor dağıtım planları tanımlayabilir.'],

        // Termin & SLA
        'termin.view_sla' => ['label' => 'Canlı Termin & SLA Takibi', 'desc' => 'Tüm süreçlerin termin sayaçlarını ve gecikme trafik ışıklarını görebilir.'],
        'termin.reschedule' => ['label' => 'Termini Erteleme & Revize Etme', 'desc' => 'Resmi kök neden kategorisi ve gerekçe notuyla termini revize edebilir.'],
        'termin.override' => ['label' => 'Üst Yönetim SLA Müdahalesi', 'desc' => 'Kritik gecikmelerde sürece doğrudan yeni termin atayabilir.'],

        // Numune & SAP
        'samples.view_pipeline' => ['label' => 'Numune Takip Kokpitini Görme', 'desc' => 'Tüm departmanların numune takip havuzunu ve durumlarını inceleyebilir.'],
        'samples.convert_order' => ['label' => 'SAP Siparişine Dönüştürme', 'desc' => 'Numuneyi SAP satış siparişine dönüştürüp SAP S/4HANA ile senkronize edebilir.'],
        'samples.download_coa' => ['label' => 'Kalite Analiz Sertifikası (CoA PDF)', 'desc' => 'Laboratuvar tolerans analiz sertifikasını tek tıkla resmi PDF olarak indirebilir.'],

        // Dış Görev & Harcırah
        'travel.request' => ['label' => 'Dış Görev Talebi Başlatma (FR-IDR-008)', 'desc' => 'Kurumsal seyahat ve dış görev talep formunu doldurabilir.'],
        'travel.approve_manager' => ['label' => 'İlk / Üst Amir Seyahat Onayı', 'desc' => 'Kendisine bağlı çalışanların dış görev taleplerini onaylayabilir.'],
        'travel.approve_gmy' => ['label' => 'GMY / Üst Yönetim Bütçe Onayı', 'desc' => 'Bütçe ve yönetim onayına tabi seyahatleri onaylayabilir.'],
        'travel.admin_services' => ['label' => 'İdari İşler Rezervasyon Yönetimi', 'desc' => 'Araç tahsisi, uçak bileti (PNR) ve otel rezervasyonlarını sisteme işleyebilir.'],
        'travel.finance_advance' => ['label' => 'Muhasebe Harcırah & Avans Ödemesi', 'desc' => 'Yol avansı ve harcırah ödemesini yapıp dekont yükleyebilir.'],
        'travel.expense_settlement' => ['label' => 'Harcırah Masraf Kapama Onayı (FR-MUH-021)', 'desc' => 'Seyahat dönüşü fatura/fiş masraf mahsubunu inceleyip onaylayabilir.'],
        'travel.download_order' => ['label' => 'Resmi Seyahat İzin Belgesi PDF', 'desc' => 'Barkodlu ve QR kodlu resmi görevlendirme belgesini indirebilir.'],

        // Canlı Saha & Personel Radarı (Kim Nerede?)
        'duty_radar.view' => ['label' => 'Canlı Saha & Personel Radarını Görüntüleme', 'desc' => 'Canlı personel radar kokpitine (/duty-radar), navbar menüsüne ve ana sayfa bilgi panosuna erişebilir.'],
        'duty_radar.manage_categories' => ['label' => 'Görev & Saha Kategorilerini Yönetme (CRUD)', 'desc' => 'Dinamik fuar, müşteri ziyareti, teknik servis vb. saha kategorilerini yönetebilir (/admin/duty-categories).'],

        // Denetim & Kokpit
        'audit.view_diff' => ['label' => 'Form Değişiklik Denetim İzi (Git Diff)', 'desc' => 'Süreçteki tüm veri revizyonlarını alan bazında eski/yeni karşılaştırmasıyla görebilir.'],
        'governance.admin_cockpit' => ['label' => 'Süreç Yönetici Kokpiti (Admin Cockpit)', 'desc' => 'Süreci zorla tamamlama, adımları ileri/geri atlatma ve görevi başkasına atama.'],
        'mail.manage_guard' => ['label' => 'E-Posta Yönetimi & Kill Switch', 'desc' => 'E-posta kural motorunu yönetebilir ve global kill-switch korumasını açıp kapatabilir.'],

        // BPMN Tasarım
        'designer.view' => ['label' => 'BPMN Akış Şemalarını İnceleme', 'desc' => 'Süreç diyagramlarını görsel tuval üzerinde inceleyebilir.'],
        'designer.edit' => ['label' => 'İş Akışı Tasarımı & Düzenleme', 'desc' => 'Yeni akış düğümleri ekleyebilir ve onay kurallarını tasarlayabilir.'],
        'designer.publish_version' => ['label' => 'Versiyon Yayınlama & Süreç Göçü', 'desc' => 'Yeni akış versiyonu yayınlayabilir ve eski süreçleri yeni versiyona geçirebilir.'],

        // Temel Süreç & Sistem
        'start_processes' => ['label' => 'Yeni Süreç Başlatabilme', 'desc' => 'Katalogdan izin verilen form ve iş akışlarını başlatabilir.'],
        'processes.approve' => ['label' => 'Görev Onay / Ret / Revize', 'desc' => 'Kendisine atanan görevleri onaylayabilir, reddedebilir veya revizyona gönderebilir.'],
        'processes.assign' => ['label' => 'Görevi Başkasına Devretme', 'desc' => 'Kendi görevini bir başka yetkiliye devredebilir.'],
        'processes.cancel' => ['label' => 'Devam Eden Süreci İptal Etme', 'desc' => 'Başlatılmış süreçleri gerekçe girerek iptal edebilir.'],
        'processes.view_own' => ['label' => 'Sadece Kendi Süreçlerini Görme', 'desc' => 'Yalnızca kendisinin başlattığı veya görev aldığı süreçleri takip edebilir.'],
        'processes.view_department' => ['label' => 'Departman Süreçlerini Görme', 'desc' => 'Kendi departmanında başlatılan tüm süreçleri görebilir.'],
        'processes.view_directorate' => ['label' => 'Direktörlük Süreçlerini Görme', 'desc' => 'Kendi direktörlüğüne bağlı tüm departmanların süreçlerini görebilir.'],
        'processes.view_all' => ['label' => 'Tüm Şirket Süreçlerini Görme', 'desc' => 'Firma genelindeki tüm süreçleri izleyebilir.'],
        'tasks.admin_edit' => ['label' => 'Yönetici Görev Düzenleme & Devretme', 'desc' => 'Bekleyen görevlerin atanan yetkilisini, vadesini veya durumunu yönetici olarak düzenleyebilir.'],
        'tasks.admin_delete' => ['label' => 'Görev & Test Süreci Silme (Soft Delete)', 'desc' => 'Test veya hatalı görev ve süreçleri güvenli şekilde soft delete ile arşivleyip temizleyebilir.'],
        'tasks.admin_bulk_delete' => ['label' => 'Toplu Görev & Süreç Temizleme', 'desc' => 'İş listesindeki birden fazla test görevini tek tıkla toplu olarak soft delete ile temizleyebilir.'],
        'workflows.create' => ['label' => 'Yeni İş Akışı & Süreç Oluşturma', 'desc' => 'Süreç kataloğunda yeni iş akışı şablonu oluşturabilir ve BPMN tasarımcısını başlatabilir.'],
        'workflows.edit' => ['label' => 'İş Akışı Şablonunu Düzenleme', 'desc' => 'Süreç kataloğunda akış başlığı, açıklama, kategori ve aktif/pasif durumunu düzenleyebilir.'],
        'workflows.delete' => ['label' => 'İş Akışı Şablonunu Silme (Soft Delete)', 'desc' => 'Test veya eski iş akışı şablonlarını güvenli şekilde soft delete ile arşivleyip gizleyebilir.'],
        'manage_workflows' => ['label' => 'İş Akışı Şablonlarını Yönetme', 'desc' => 'Sistemdeki iş akışı modellerini yönetir.'],

        'create_forms' => ['label' => 'Yeni Form Şablonu Oluşturma', 'desc' => 'Form tasarımcısıyla yeni şablonlar tasarlar.'],
        'templates.edit' => ['label' => 'Şablonları Düzenleme', 'desc' => 'Mevcut form şablonlarını günceller.'],
        'templates.delete' => ['label' => 'Şablonları Silme', 'desc' => 'Kullanılmayan şablonları kaldırır.'],
        'templates.publish' => ['label' => 'Şablonları Yayına Alma', 'desc' => 'Şablonu kullanıcıların başlatmasına açar.'],
        'view_admin_panel' => ['label' => 'Yönetim Paneline Giriş', 'desc' => 'Admin menüsüne erişim sağlar.'],
        'manage_users' => ['label' => 'Kullanıcıları Yönetme', 'desc' => 'Personel hesaplarını ekler, düzenler ve yetkilendirir.'],
        'manage_roles' => ['label' => 'Rol ve Yetkileri Yönetme', 'desc' => 'Rolleri ve izin matrislerini yönetir.'],
        'manage_departments' => ['label' => 'Departmanları Yönetme', 'desc' => 'Şirket departman hiyerarşisini yönetir.'],
        'manage_directorates' => ['label' => 'Direktörlükleri Yönetme', 'desc' => 'Üst organizasyon birimlerini yönetir.'],
        'manage_settings' => ['label' => 'Sistem Ayarlarını Yönetme', 'desc' => 'Genel portal ayarlarını düzenler.'],
    ];

    /**
     * Tüm modül tanımlarını (Önceden tanımlı + DB Setting üzerinden dinamik eklenenler) getirir.
     */
    public function getModuleDefinitions(): array
    {
        $modules = $this->predefinedModules;

        try {
            $customModulesJson = \App\Models\Setting::get('custom_rbac_modules');
            if ($customModulesJson) {
                $custom = json_decode($customModulesJson, true);
                if (is_array($custom)) {
                    foreach ($custom as $k => $c) {
                        $modules[$k] = array_merge($modules[$k] ?? [], $c);
                    }
                }
            }
        } catch (\Throwable $e) {
            // DB hazır değilse veya hata durumunda fallback
        }

        return $modules;
    }

    /**
     * Tüm yetkileri modüllerine göre dinamik olarak gruplayarak döner.
     * Gelecekte eklenen YENİ yetkiler de otomatik keşif mekanizması ile
     * kendi modülü altında anında listelenir!
     */
    public function getModuleGroupedPermissions(): array
    {
        $allPermissions = Permission::orderBy('name')->get();
        $modules = $this->getModuleDefinitions();

        // Her modüle boş permissions dizisi başlat
        foreach ($modules as $k => $m) {
            $modules[$k]['permissions'] = [];
        }

        // Veritabanındaki tüm yetkileri tek tek incele
        foreach ($allPermissions as $permission) {
            $name = $permission->name;
            $moduleKey = $this->detectModuleKey($name);

            // Eğer bu modül daha önce tanımlanmamış YEPYENİ bir modülse, dinamik oluştur (Auto-Discovery)
            if (!isset($modules[$moduleKey])) {
                $modules[$moduleKey] = [
                    'id' => $moduleKey,
                    'title' => Str::title(str_replace('_', ' ', $moduleKey)) . ' Modülü (Dinamik)',
                    'description' => "Gelecekte sisteme entegre edilen {$moduleKey} operasyonel modülü yetkileri",
                    'icon' => '🧩',
                    'badge_color' => 'teal',
                    'order' => 99,
                    'permissions' => [],
                ];
            }

            // Yetki etiketi ve açıklamasını bul ya da otomatik üret
            $meta = $this->predefinedPermissions[$name] ?? [
                'label' => Str::title(str_replace(['.', '_'], ' ', $name)),
                'desc' => "{$name} işlem yetkisi",
            ];

            $modules[$moduleKey]['permissions'][] = [
                'id' => $permission->id,
                'name' => $permission->name,
                'label' => $meta['label'],
                'description' => $meta['desc'],
                'module_key' => $moduleKey,
            ];
        }

        // Boş modülleri filtrele ve sıraya göre diz
        $activeModules = array_filter($modules, fn($m) => count($m['permissions']) > 0);
        usort($activeModules, fn($a, $b) => ($a['order'] ?? 99) <=> ($b['order'] ?? 99));

        return array_values($activeModules);
    }

    /**
     * Bir rolün hangi modüllerde aktif yetkileri olduğunu hesaplar.
     */
    public function getRoleModuleBadges(Role $role): array
    {
        $rolePermissions = $role->permissions->pluck('name')->toArray();
        $allModuleDefs = $this->getModuleDefinitions();
        $modules = [];

        foreach ($rolePermissions as $permName) {
            $modKey = $this->detectModuleKey($permName);
            if (!isset($modules[$modKey])) {
                $meta = $allModuleDefs[$modKey] ?? [
                    'title' => Str::title(str_replace('_', ' ', $modKey)),
                    'icon' => '🧩',
                    'badge_color' => 'slate',
                ];
                $modules[$modKey] = [
                    'key' => $modKey,
                    'title' => $meta['title'],
                    'icon' => $meta['icon'],
                    'badge_color' => $meta['badge_color'] ?? 'slate',
                    'count' => 0,
                ];
            }
            $modules[$modKey]['count']++;
        }

        return array_values($modules);
    }

    /**
     * Yetki isminden modül anahtarını otomatik tespit eder.
     */
    protected function detectModuleKey(string $permissionName): string
    {
        if (str_contains($permissionName, '.')) {
            $prefix = explode('.', $permissionName)[0];
            return match ($prefix) {
                'reports' => 'reports',
                'termin' => 'termin',
                'samples' => 'samples',
                'travel' => 'travel',
                'duty_radar', 'duty-radar' => 'duty_radar',
                'audit', 'governance', 'mail' => 'governance',
                'designer', 'templates' => 'designer',
                'processes', 'tasks', 'workflows' => 'processes',
                'procurement' => 'procurement',
                'warehouse' => 'warehouse',
                'maintenance' => 'maintenance',
                'quality' => 'quality',
                'hr' => 'hr',
                default => $prefix, // Gelecekte örn: wms.scan -> wms
            };
        }

        // Eski veya alt çizgili formatlar
        if (str_starts_with($permissionName, 'duty_radar')) {
            return 'duty_radar';
        }

        if (str_starts_with($permissionName, 'manage_') || $permissionName === 'view_admin_panel') {
            if ($permissionName === 'manage_workflows' || $permissionName === 'create_forms') {
                return 'designer';
            }
            return 'system';
        }

        if (str_starts_with($permissionName, 'start_') || str_starts_with($permissionName, 'process') || str_starts_with($permissionName, 'task')) {
            return 'processes';
        }


        return 'system';
    }

    /**
     * Yeni bir dinamik yetki ve modül kaydeder (Ekrandan veya Gelecekteki Modüllerden)
     */
    public function registerPermission(
        string $name,
        string $label,
        string $moduleKey,
        ?string $description = null,
        ?string $moduleTitle = null,
        ?string $moduleIcon = null
    ): Permission {
        $perm = Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]);

        // Özel Modül Meta Tanımı Varsa Setting Tablosuna Kalıcı Kaydet
        if ($moduleTitle || $moduleIcon) {
            try {
                $customJson = \App\Models\Setting::get('custom_rbac_modules');
                $custom = $customJson ? json_decode($customJson, true) : [];
                $custom[$moduleKey] = [
                    'id' => $moduleKey,
                    'title' => $moduleTitle ?: (Str::title(str_replace('_', ' ', $moduleKey)) . ' Modülü'),
                    'icon' => $moduleIcon ?: '🧩',
                    'description' => "Dinamik tanımlanan {$moduleKey} operasyonel modülü",
                    'badge_color' => 'teal',
                    'order' => 90,
                ];
                \App\Models\Setting::set('custom_rbac_modules', json_encode($custom), 'Dinamik RBAC Modül Tanımları');
            } catch (\Throwable $e) {
                // Ignore DB error
            }
        }

        // Önceden tanımlı meta sözlüğüne geçici de olsa ekle
        $this->predefinedPermissions[$name] = [
            'label' => $label,
            'desc' => $description ?? "{$label} işlem yetkisi",
        ];

        return $perm;
    }
}

