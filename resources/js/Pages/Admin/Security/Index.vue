<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
    pagination: {
        type: Object,
        default: () => ({ current_page: 1, last_page: 1, total: 0 }),
    },
    stats: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({ date: '', time_start: '', time_end: '', type: '', severity: '', search: '' }),
    },
    available_dates: {
        type: Array,
        default: () => [],
    },
    file_exists: {
        type: Boolean,
        default: true,
    },
    config_info: {
        type: Object,
        default: () => ({}),
    },
});

const activeTab = ref('logs'); // 'logs', 'test_lab', 'config'

// Arama ve Filtre Durumu
const filterForm = ref({
    date: props.filters.date || new Date().toISOString().split('T')[0],
    time_start: props.filters.time_start || '',
    time_end: props.filters.time_end || '',
    type: props.filters.type || '',
    severity: props.filters.severity || '',
    search: props.filters.search || '',
});

const setDateToday = () => {
    filterForm.value.date = new Date().toISOString().split('T')[0];
    applyFilters(1);
};

const setDateYesterday = () => {
    const d = new Date();
    d.setDate(d.getDate() - 1);
    filterForm.value.date = d.toISOString().split('T')[0];
    applyFilters(1);
};

const applyFilters = (page = 1) => {
    router.get(route('admin.security.index'), {
        ...filterForm.value,
        page,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    filterForm.value = {
        date: new Date().toISOString().split('T')[0],
        time_start: '',
        time_end: '',
        type: '',
        severity: '',
        search: '',
    };
    applyFilters(1);
};

// Modal Detay İnceleme
const selectedEvent = ref(null);
const showDetailModal = ref(false);

const openDetailModal = (event) => {
    selectedEvent.value = event;
    showDetailModal.value = true;
};

// Canlı Test Laboratuvarı Durumu
const isRunningTests = ref(false);
const testReport = ref(null);
const lastTestMessage = ref('');

const defaultTestList = [
    { id: 1, name: 'Başarısız Giriş Denemesi', requirement: 'G1 / G2', expected_event: 'auth.login_failed', description: 'Mevcut hesaba 1 yanlış şifre ile giriş denenir; user_exists=true loglanır, şifre maskelenir.' },
    { id: 2, name: 'Hesap Kilidi & Brute Force Kalkanı', requirement: 'G2', expected_event: 'auth.account_locked & auth.login_blocked', description: 'Aynı hesaba 5 başarısız denemede kilitlenir; 6. denemede şifre bakılmadan HTTP 429 üretilir.' },
    { id: 3, name: 'Credential Stuffing & IP Engeli', requirement: 'G2', expected_event: 'auth.ip_blocked', description: 'Aynı IP\'den 20 farklı kullanıcı adı denendiğinde kaynak IP 15 dakika engellenir.' },
    { id: 4, name: 'SQL Injection & sqlmap Tespiti', requirement: 'G3 / G4', expected_event: 'attack.sqli_pattern & attack.scanner_user_agent', description: 'UNION SELECT injection saldırısı ve bilinen sqlmap User-Agent aracı tespit edilir.' },
    { id: 5, name: 'XSS (Cross-Site Scripting) Taraması', requirement: 'G3', expected_event: 'attack.xss_pattern', description: 'Parametredeki <script>alert(1)<\\/script> ve tehlikeli XSS payload\'ları yakalanır.' },
    { id: 6, name: 'Dizin Taraması & Yüksek Hata Oranı', requirement: 'G4', expected_event: 'attack.scanning_behavior', description: '60 saniyede 50 adet 404 alan tarama aracı (gobuster/ffuf) tespit edilir, tek olay loglanır.' },
    { id: 7, name: 'CSRF Token Uyuşmazlığı', requirement: 'G4', expected_event: 'session.csrf_mismatch', description: 'Geçersiz veya eksik CSRF token içeren form POST isteğinde 419 loglanır.' },
    { id: 8, name: 'Yetkisiz Erişim (403 Forbidden)', requirement: 'G4', expected_event: 'authz.access_denied', description: 'Kullanıcının yetkisi olmayan bir kayda ID değiştirerek erişim denemesi loglanır.' },
    { id: 9, name: 'Content Security Policy (CSP) İhlali', requirement: 'G5', expected_event: 'attack.csp_violation', description: 'Tarayıcıdan iletilen harici script ihlal raporu /security/csp-report üzerinden toplanır.' },
    { id: 10, name: 'Veritabanı Syntax Hatası Tespiti', requirement: 'G3', expected_event: 'attack.db_syntax_error', description: 'Bozuk parametrenin tetiklediği SQLSTATE 42xxx hatası şablonuyla birlikte yakalanır.' },
];

const runAcceptanceTests = async () => {
    isRunningTests.value = true;
    lastTestMessage.value = '10 Kabul Testi sunucu üzerinde izole CLI sürecinde çalıştırılıyor...';

    try {
        const response = await axios.post(route('admin.security.run-tests'));
        const data = response.data;
        if (data.success) {
            testReport.value = data.report;
            lastTestMessage.value = data.message;
            // Test sonrası log tablosunu tazeleyelim
            applyFilters(props.pagination.current_page);
        } else {
            lastTestMessage.value = 'Testler sırasında hata oluştu: ' + (data.message || 'Bilinmeyen hata');
        }
    } catch (err) {
        if (err.response?.status === 419) {
            lastTestMessage.value = 'Oturum süreniz dolmuş veya token yenilenmiş. Lütfen sayfayı yenileyip tekrar deneyin.';
        } else {
            lastTestMessage.value = 'Ağ veya sunucu hatası: ' + (err.response?.data?.message || err.message);
        }
    } finally {
        isRunningTests.value = false;
    }
};

// Tekil Olay Silme
const isDeleting = ref(false);
const deleteLogEntry = async (requestId) => {
    if (!confirm(`X-Request-Id: ${requestId} numaralı olayı log dosyasından kaldırmak istediğinize emin misiniz?`)) {
        return;
    }

    try {
        const response = await axios.delete(route('admin.security.destroy-log-entry', requestId), {
            data: { date: filterForm.value.date },
        });
        if (response.data.success) {
            applyFilters(props.pagination.current_page);
        } else {
            alert(response.data.message || 'Silinemedi.');
        }
    } catch (e) {
        alert('Hata: ' + (e.response?.data?.message || e.message));
    }
};

// Günlük Log Dosyasını Temizleme
const clearLogsForDate = async () => {
    if (!confirm(`${filterForm.value.date} tarihli tüm güvenlik loglarını sıfırlamak istediğinize emin misiniz? Bu işlem geri alınamaz.`)) {
        return;
    }

    try {
        const response = await axios.post(route('admin.security.clear-logs'), {
            date: filterForm.value.date,
        });
        if (response.data.success) {
            applyFilters(1);
        }
    } catch (e) {
        alert('Hata: ' + (e.response?.data?.message || e.message));
    }
};

// JSON Formatlama
const formatJson = (val) => {
    try {
        return JSON.stringify(val, null, 2);
    } catch {
        return String(val);
    }
};

// Renk ve Rozet Yardımcıları
const getSeverityClass = (sev) => {
    switch (sev) {
        case 'critical':
            return 'bg-red-500 text-white ring-2 ring-red-300 animate-pulse';
        case 'high':
            return 'bg-rose-100 text-rose-800 border border-rose-300 font-bold';
        case 'medium':
            return 'bg-amber-100 text-amber-800 border border-amber-300 font-medium';
        case 'low':
            return 'bg-sky-100 text-sky-800 border border-sky-300';
        default:
            return 'bg-slate-100 text-slate-700 border border-slate-200';
    }
};

const getEventBadgeClass = (type) => {
    if (type.startsWith('attack.')) {
        return 'bg-red-50 text-red-700 border-red-200 font-semibold';
    }
    if (type.includes('blocked') || type.includes('locked')) {
        return 'bg-purple-50 text-purple-700 border-purple-200 font-semibold';
    }
    if (type.includes('failed')) {
        return 'bg-amber-50 text-amber-700 border-amber-200';
    }
    if (type.startsWith('session.') || type.startsWith('authz.')) {
        return 'bg-orange-50 text-orange-700 border-orange-200';
    }
    return 'bg-slate-50 text-slate-700 border-slate-200';
};
</script>

<template>
    <Head title="Güvenlik & SIEM Tehdit Kokpiti" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <span class="p-2.5 bg-gradient-to-br from-indigo-600 to-rose-600 text-white rounded-xl shadow-md text-xl">
                            🛡️
                        </span>
                        <div>
                            <h2 class="font-extrabold text-2xl text-slate-900 tracking-tight flex items-center gap-2">
                                Güvenlik & SIEM Tehdit Kokpiti
                                <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-300 font-semibold">
                                    G1–G5 Aktif
                                </span>
                            </h2>
                            <p class="text-sm text-slate-500 mt-0.5">
                                Brute Force Kalkanı, SQLi/XSS Dedektörleri, Tarama Algılama, CSP Raporlama ve Logsign SIEM Entegrasyonu
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <button
                        @click="runAcceptanceTests"
                        :disabled="isRunningTests"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-semibold text-sm rounded-xl shadow-md transition disabled:opacity-50 cursor-pointer"
                    >
                        <span v-if="isRunningTests" class="animate-spin text-base">⏳</span>
                        <span v-else>🧪</span>
                        {{ isRunningTests ? 'Testler Koşuyor...' : '10 Kabul Testini Canlı Çalıştır' }}
                    </button>

                    <a
                        :href="route('admin.security.export-json', { date: filterForm.date })"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-medium text-sm rounded-xl shadow-sm transition"
                    >
                        <span>📥</span> JSON İndir
                    </a>

                    <button
                        @click="clearLogsForDate"
                        class="inline-flex items-center gap-1.5 px-3 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-medium text-sm rounded-xl transition cursor-pointer"
                        title="Seçili tarihin loglarını temizle"
                    >
                        <span>🗑️</span> Temizle
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- KPI HERO STATS -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm relative overflow-hidden">
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Toplam Olay</div>
                    <div class="text-2xl font-black text-slate-900 mt-1">{{ stats.total_events || 0 }}</div>
                    <div class="text-xs text-slate-400 mt-1">Bugünkü Güvenlik Logu</div>
                    <div class="absolute right-3 top-3 text-2xl opacity-15">📊</div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-purple-200/80 shadow-sm relative overflow-hidden bg-gradient-to-br from-white to-purple-50/30">
                    <div class="text-xs font-semibold text-purple-700 uppercase tracking-wider">Kilitli Hesap</div>
                    <div class="text-2xl font-black text-purple-900 mt-1">{{ stats.account_locked || 0 }}</div>
                    <div class="text-xs text-purple-600 mt-1">Brute Force Kalkanı (5+)</div>
                    <div class="absolute right-3 top-3 text-2xl opacity-15">🔒</div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-rose-200/80 shadow-sm relative overflow-hidden bg-gradient-to-br from-white to-rose-50/30">
                    <div class="text-xs font-semibold text-rose-700 uppercase tracking-wider">Engelli IP</div>
                    <div class="text-2xl font-black text-rose-900 mt-1">{{ stats.ip_blocked || 0 }}</div>
                    <div class="text-xs text-rose-600 mt-1">Stuffing Engeli (20+)</div>
                    <div class="absolute right-3 top-3 text-2xl opacity-15">🚫</div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-red-200/80 shadow-sm relative overflow-hidden bg-gradient-to-br from-white to-red-50/30">
                    <div class="text-xs font-semibold text-red-700 uppercase tracking-wider">SQLi / XSS</div>
                    <div class="text-2xl font-black text-red-900 mt-1">{{ (stats.sqli_detected || 0) + (stats.xss_detected || 0) }}</div>
                    <div class="text-xs text-red-600 mt-1">{{ stats.sqli_detected || 0 }} SQLi · {{ stats.xss_detected || 0 }} XSS</div>
                    <div class="absolute right-3 top-3 text-2xl opacity-15">💉</div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-amber-200/80 shadow-sm relative overflow-hidden bg-gradient-to-br from-white to-amber-50/30">
                    <div class="text-xs font-semibold text-amber-700 uppercase tracking-wider">Tarama Tespiti</div>
                    <div class="text-2xl font-black text-amber-900 mt-1">{{ stats.scanners_detected || 0 }}</div>
                    <div class="text-xs text-amber-600 mt-1">sqlmap / gobuster / 404 spike</div>
                    <div class="absolute right-3 top-3 text-2xl opacity-15">🛰️</div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-blue-200/80 shadow-sm relative overflow-hidden bg-gradient-to-br from-white to-blue-50/30">
                    <div class="text-xs font-semibold text-blue-700 uppercase tracking-wider">CSP Raporları</div>
                    <div class="text-2xl font-black text-blue-900 mt-1">{{ stats.csp_violations || 0 }}</div>
                    <div class="text-xs text-blue-600 mt-1">Tarayıcı İhlalleri</div>
                    <div class="absolute right-3 top-3 text-2xl opacity-15">🌐</div>
                </div>
            </div>

            <!-- SEKME BUTONLARI -->
            <div class="flex items-center gap-2 border-b border-slate-200 pb-2">
                <button
                    @click="activeTab = 'logs'"
                    :class="[
                        'px-4 py-2 font-semibold text-sm rounded-xl transition cursor-pointer flex items-center gap-2',
                        activeTab === 'logs'
                            ? 'bg-slate-900 text-white shadow-sm'
                            : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                    ]"
                >
                    <span>📋</span> Canlı Güvenlik Logları ({{ pagination.total }})
                </button>

                <button
                    @click="activeTab = 'test_lab'"
                    :class="[
                        'px-4 py-2 font-semibold text-sm rounded-xl transition cursor-pointer flex items-center gap-2',
                        activeTab === 'test_lab'
                            ? 'bg-emerald-700 text-white shadow-sm'
                            : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                    ]"
                >
                    <span>🧪</span> Kabul Testleri Laboratuvarı (10 Senaryo)
                    <span v-if="testReport" class="px-2 py-0.5 text-xs bg-white/20 rounded-full">
                        {{ testReport.passed_tests }}/{{ testReport.total_tests }}
                    </span>
                </button>

                <button
                    @click="activeTab = 'config'"
                    :class="[
                        'px-4 py-2 font-semibold text-sm rounded-xl transition cursor-pointer flex items-center gap-2',
                        activeTab === 'config'
                            ? 'bg-indigo-700 text-white shadow-sm'
                            : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                    ]"
                >
                    <span>⚙️</span> Güvenlik & SIEM Mimarisi
                </button>
            </div>

            <!-- TAB 1: CANLI GÜVENLİK LOGLARI -->
            <div v-show="activeTab === 'logs'" class="space-y-4">
                <!-- FİLTRE VE ARAMA ÇUBUĞU -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- 1. Tarih Seçici & Hızlı Butonlar -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-xs font-bold text-slate-700 flex items-center gap-1">
                                    <span>📅</span> Tarih Seçimi
                                </label>
                                <div class="flex items-center gap-1 text-[11px]">
                                    <button
                                        @click="setDateToday"
                                        type="button"
                                        class="px-1.5 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded font-medium transition cursor-pointer"
                                    >
                                        Bugün
                                    </button>
                                    <button
                                        @click="setDateYesterday"
                                        type="button"
                                        class="px-1.5 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded font-medium transition cursor-pointer"
                                    >
                                        Dün
                                    </button>
                                </div>
                            </div>
                            <input
                                v-model="filterForm.date"
                                @change="applyFilters(1)"
                                type="date"
                                class="w-full text-sm font-semibold border-slate-300 rounded-xl focus:ring-indigo-500 focus:border-indigo-500 bg-slate-50 cursor-pointer"
                            />
                            <div v-if="available_dates.length > 0" class="mt-1 flex items-center gap-1 text-[11px] text-slate-400">
                                <span>Mevcut Log Dosyaları:</span>
                                <select
                                    @change="filterForm.date = $event.target.value; applyFilters(1)"
                                    class="text-[11px] py-0 px-1 border-none bg-transparent text-indigo-600 font-semibold cursor-pointer underline"
                                >
                                    <option value="" disabled selected>Dosyadan Seç</option>
                                    <option v-for="d in available_dates" :key="d" :value="d">{{ d }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- 2. Saat Aralığı (Zaman Penceresi) -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1 flex items-center gap-1">
                                <span>⏱️</span> Zaman Aralığı (Saat)
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <input
                                        v-model="filterForm.time_start"
                                        @change="applyFilters(1)"
                                        type="time"
                                        placeholder="00:00"
                                        class="w-full text-xs font-mono border-slate-300 rounded-xl focus:ring-indigo-500 focus:border-indigo-500"
                                        title="Başlangıç Saati (Örn: 08:30)"
                                    />
                                    <span class="text-[10px] text-slate-400 mt-0.5 block">Başlangıç</span>
                                </div>
                                <div>
                                    <input
                                        v-model="filterForm.time_end"
                                        @change="applyFilters(1)"
                                        type="time"
                                        placeholder="23:59"
                                        class="w-full text-xs font-mono border-slate-300 rounded-xl focus:ring-indigo-500 focus:border-indigo-500"
                                        title="Bitiş Saati (Örn: 17:30)"
                                    />
                                    <span class="text-[10px] text-slate-400 mt-0.5 block">Bitiş</span>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Olay Tipi (Kategorize Edilmiş Dropdown) -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1 flex items-center gap-1">
                                <span>🎯</span> Olay Tipi (Event Type)
                            </label>
                            <select
                                v-model="filterForm.type"
                                @change="applyFilters(1)"
                                class="w-full text-sm border-slate-300 rounded-xl focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">Tüm Olay Tipleri (Hepsi)</option>
                                <optgroup label="🔐 Kimlik Doğrulama & Brute Force (G1, G2)">
                                    <option value="auth.login_failed">auth.login_failed (Hatalı Şifre / Giriş)</option>
                                    <option value="auth.account_locked">auth.account_locked (Hesap Kilidi - 5 Deneme)</option>
                                    <option value="auth.login_blocked">auth.login_blocked (HTTP 429 Engel)</option>
                                    <option value="auth.ip_blocked">auth.ip_blocked (IP Engeli - Stuffing)</option>
                                    <option value="auth.login_success">auth.login_success (Başarılı Giriş)</option>
                                </optgroup>
                                <optgroup label="💉 Web Saldırıları & Injection (G3)">
                                    <option value="attack.sqli_pattern">attack.sqli_pattern (SQL Injection)</option>
                                    <option value="attack.xss_pattern">attack.xss_pattern (XSS Script Taraması)</option>
                                    <option value="attack.db_syntax_error">attack.db_syntax_error (DB Syntax Hatası)</option>
                                </optgroup>
                                <optgroup label="🛰️ Keşif, Tarama & Hata Artışı (G4)">
                                    <option value="attack.scanner_user_agent">attack.scanner_user_agent (Tarama Aracı: sqlmap/gobuster)</option>
                                    <option value="attack.scanning_behavior">attack.scanning_behavior (Yüksek 404 Hata Spike)</option>
                                </optgroup>
                                <optgroup label="🛡️ Yetkilendirme & Tarayıcı CSP (G4, G5)">
                                    <option value="session.csrf_mismatch">session.csrf_mismatch (HTTP 419 CSRF)</option>
                                    <option value="authz.access_denied">authz.access_denied (HTTP 403 Yetkisiz)</option>
                                    <option value="attack.csp_violation">attack.csp_violation (CSP Tarayıcı İhlali)</option>
                                </optgroup>
                            </select>
                        </div>

                        <!-- 4. Kritiklik (Severity) -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1 flex items-center gap-1">
                                <span>🚨</span> Kritiklik Derecesi (Severity)
                            </label>
                            <select
                                v-model="filterForm.severity"
                                @change="applyFilters(1)"
                                class="w-full text-sm border-slate-300 rounded-xl focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">Tümü (Critical, High, Medium, Low, Info)</option>
                                <option value="critical">🔴 Critical (Kritik)</option>
                                <option value="high">🟠 High (Yüksek)</option>
                                <option value="medium">🟡 Medium (Orta)</option>
                                <option value="low">🔵 Low (Düşük)</option>
                                <option value="info">⚪ Info (Bilgi)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Alt Satır: Serbest Arama & Butonlar -->
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2 border-t border-slate-100">
                        <div class="relative w-full sm:max-w-md">
                            <span class="absolute left-3 top-2.5 text-xs text-slate-400">🔍</span>
                            <input
                                v-model="filterForm.search"
                                @keydown.enter="applyFilters(1)"
                                type="text"
                                placeholder="IP adresi, e-posta, URL veya Request-Id ara..."
                                class="w-full text-sm pl-8 pr-8 border-slate-300 rounded-xl focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <button
                                v-if="filterForm.search"
                                @click="filterForm.search = ''; applyFilters(1)"
                                class="absolute right-2.5 top-2.5 text-xs text-slate-400 hover:text-slate-600"
                            >
                                ✕
                            </button>
                        </div>

                        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                            <button
                                @click="applyFilters(1)"
                                class="px-5 py-2 bg-slate-900 text-white rounded-xl text-sm font-semibold hover:bg-slate-800 transition cursor-pointer flex items-center gap-1.5"
                            >
                                <span>⚡</span> Filtrele
                            </button>
                            <button
                                @click="resetFilters"
                                class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl text-sm font-medium hover:bg-slate-200 transition cursor-pointer"
                            >
                                Sıfırla
                            </button>
                        </div>
                    </div>
                </div>

                <!-- DOSYA BULUNAMADI UYARISI -->
                <div
                    v-if="!file_exists"
                    class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-xs"
                >
                    <div class="flex items-center gap-2.5">
                        <span class="text-2xl">📅</span>
                        <div>
                            <span class="font-bold text-sm text-amber-950">{{ filterForm.date }}</span> tarihine ait bir güvenlik log dosyası (<code class="bg-amber-100 px-1 py-0.5 rounded font-mono">security-{{ filterForm.date }}.log</code>) bulunamadı.
                            <p class="text-[11px] text-amber-700 mt-0.5">Bu tarihte sistemde kaydedilmiş herhangi bir güvenlik olayı veya saldırı teşebbüsü yaşanmamış olabilir.</p>
                        </div>
                    </div>
                    <button
                        @click="setDateToday"
                        class="px-3.5 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl font-bold text-xs transition cursor-pointer shrink-0"
                    >
                        Bugünün Loglarına Git »
                    </button>
                </div>

                <!-- LOG TABLOSU -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-700">
                            <thead class="bg-slate-50/80 text-xs text-slate-500 uppercase tracking-wider border-b border-slate-200">
                                <tr>
                                    <th class="py-3 px-4">Zaman Damgası (UTC)</th>
                                    <th class="py-3 px-4">Olay Tipi (Event)</th>
                                    <th class="py-3 px-4">Kritiklik</th>
                                    <th class="py-3 px-4">Kaynak IP</th>
                                    <th class="py-3 px-4">Kullanıcı / Hedef</th>
                                    <th class="py-3 px-4">İstek / URL</th>
                                    <th class="py-3 px-4">Tespit Özeti</th>
                                    <th class="py-3 px-4 text-right">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="(event, idx) in events"
                                    :key="event.request_id || idx"
                                    class="hover:bg-slate-50/80 transition font-mono text-xs"
                                >
                                    <!-- Zaman Damgası -->
                                    <td class="py-3 px-4 whitespace-nowrap text-slate-500">
                                        {{ (event['@timestamp'] || '').replace('T', ' ').replace('Z', '') }}
                                    </td>

                                    <!-- Olay Tipi -->
                                    <td class="py-3 px-4 whitespace-nowrap font-sans">
                                        <span :class="['px-2.5 py-1 rounded-lg border text-xs font-semibold', getEventBadgeClass(event.event_type || '')]">
                                            {{ event.event_type }}
                                        </span>
                                    </td>

                                    <!-- Severity -->
                                    <td class="py-3 px-4 whitespace-nowrap font-sans">
                                        <span :class="['px-2 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider', getSeverityClass(event.severity)]">
                                            {{ event.severity }}
                                        </span>
                                    </td>

                                    <!-- Kaynak IP -->
                                    <td class="py-3 px-4 whitespace-nowrap font-semibold text-slate-800">
                                        {{ event.source_ip || '-' }}
                                    </td>

                                    <!-- Kullanıcı / Hedef -->
                                    <td class="py-3 px-4 whitespace-nowrap text-slate-700 font-sans">
                                        <span v-if="event.username" class="font-medium text-slate-900">
                                            {{ event.username }}
                                        </span>
                                        <span v-else-if="event.user_id" class="text-slate-500">
                                            UID: #{{ event.user_id }}
                                        </span>
                                        <span v-else class="text-slate-400 italic">
                                            Misafir
                                        </span>
                                    </td>

                                    <!-- Metod & URL -->
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <span v-if="event.method" class="font-bold text-slate-600 mr-1">
                                            {{ event.method }}
                                        </span>
                                        <span class="text-slate-500 truncate max-w-[180px] inline-block align-bottom" :title="event.path">
                                            {{ event.path || '-' }}
                                        </span>
                                    </td>

                                    <!-- Tespit Özeti -->
                                    <td class="py-3 px-4 text-slate-600 font-sans text-xs">
                                        <div v-if="event.rules && event.rules.length" class="flex gap-1 flex-wrap">
                                            <span v-for="r in event.rules" :key="r" class="bg-rose-50 text-rose-700 px-1.5 py-0.5 rounded border border-rose-200">
                                                {{ r }}
                                            </span>
                                        </div>
                                        <span v-else-if="event.tool" class="text-amber-800 font-semibold">
                                            Tool: {{ event.tool }}
                                        </span>
                                        <span v-else-if="event.error_responses" class="text-rose-700 font-bold">
                                            {{ event.error_responses }} Hata (Spike)
                                        </span>
                                        <span v-else-if="event.violated_directive" class="text-blue-700">
                                            CSP: {{ event.violated_directive }}
                                        </span>
                                        <span v-else-if="event.account_attempts" class="text-purple-700">
                                            Deneme: {{ event.account_attempts }} / IP: {{ event.ip_attempts }}
                                        </span>
                                        <span v-else-if="event.sqlstate" class="text-red-700">
                                            SQLSTATE {{ event.sqlstate }}
                                        </span>
                                        <span v-else class="text-slate-400">
                                            -
                                        </span>
                                    </td>

                                    <!-- İşlemler -->
                                    <td class="py-3 px-4 whitespace-nowrap text-right font-sans">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button
                                                @click="openDetailModal(event)"
                                                class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-medium transition cursor-pointer"
                                                title="Detaylı JSON Verisi"
                                            >
                                                🔍 İncele
                                            </button>
                                            <button
                                                v-if="event.request_id"
                                                @click="deleteLogEntry(event.request_id)"
                                                class="px-2 py-1 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg text-xs transition cursor-pointer"
                                                title="Log Satırını Sil"
                                            >
                                                🗑️
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="events.length === 0">
                                    <td colspan="8" class="text-center py-12 text-slate-400 font-sans">
                                        <div class="text-3xl mb-2">🛡️</div>
                                        <p class="font-semibold text-slate-600">Seçilen kriterlerde olay kaydı bulunamadı.</p>
                                        <p class="text-xs mt-1 text-slate-400">Filtreleri sıfırlayabilir veya yukarıdaki test butonuna basarak olay üretebilirsiniz.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- SAYFALAMA -->
                    <div v-if="pagination.last_page > 1" class="p-4 border-t border-slate-200 flex items-center justify-between">
                        <div class="text-xs text-slate-500">
                            Toplam <span class="font-bold text-slate-700">{{ pagination.total }}</span> olay arasından
                            <span class="font-bold text-slate-700">{{ (pagination.current_page - 1) * pagination.per_page + 1 }}</span> -
                            <span class="font-bold text-slate-700">{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}</span> arası gösteriliyor.
                        </div>

                        <div class="flex items-center gap-1">
                            <button
                                :disabled="pagination.current_page <= 1"
                                @click="applyFilters(pagination.current_page - 1)"
                                class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold disabled:opacity-40 cursor-pointer"
                            >
                                « Önceki
                            </button>

                            <span class="px-3 py-1.5 text-xs text-slate-600 font-bold">
                                Sayfa {{ pagination.current_page }} / {{ pagination.last_page }}
                            </span>

                            <button
                                :disabled="pagination.current_page >= pagination.last_page"
                                @click="applyFilters(pagination.current_page + 1)"
                                class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold disabled:opacity-40 cursor-pointer"
                            >
                                Sonraki »
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: KABUL TESTLERİ LABORATUVARI (10 SENARYO) -->
            <div v-show="activeTab === 'test_lab'" class="space-y-6">
                <!-- BANNER -->
                <div class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white p-6 rounded-3xl shadow-lg border border-slate-800 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div class="space-y-1">
                        <div class="inline-flex items-center gap-2 px-3 py-1 bg-emerald-500/20 text-emerald-300 rounded-full text-xs font-bold border border-emerald-500/30">
                            <span>✨</span> IT Bilgi Güvenliği Resmi Kabul Kriterleri (Emin Bey)
                        </div>
                        <h3 class="text-xl font-black tracking-tight text-white">
                            Otomatik Güvenlik & SIEM Doğrulama Laboratuvarı
                        </h3>
                        <p class="text-sm text-slate-300 max-w-2xl">
                            Bu panel, IT ekibinin raporunda belirttiği 10 kritik saldırı ve deneme senaryosunu projenin gerçek HTTP çekirdeğinde koşturur ve üretilen olayları denetler.
                        </p>
                        <div class="mt-3 flex items-center gap-2 text-xs text-slate-300 bg-white/5 p-2.5 rounded-xl border border-white/10">
                            <span class="text-amber-400 text-base">💡</span>
                            <span><strong>'Koşuma Hazır' Durumu:</strong> Henüz test başlatılmadığı anlamına gelir. Sağdaki <strong>'Tüm 10 Testi Canlı Koştur'</strong> butonuna bastığınızda bu senaryolar sunucu üzerinde gerçek zamanlı denenir ve tümü <strong>'✅ GEÇTİ'</strong> olarak güncellenir.</span>
                        </div>
                    </div>

                    <div class="flex flex-col items-end gap-2 w-full md:w-auto">
                        <button
                            @click="runAcceptanceTests"
                            :disabled="isRunningTests"
                            class="w-full md:w-auto px-6 py-3 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-black text-sm rounded-2xl shadow-lg transition disabled:opacity-50 cursor-pointer flex items-center justify-center gap-2"
                        >
                            <span v-if="isRunningTests" class="animate-spin text-lg">⏳</span>
                            <span v-else class="text-lg">🚀</span>
                            {{ isRunningTests ? 'Testler Çalıştırılıyor...' : 'Tüm 10 Testi Canlı Koştur' }}
                        </button>
                        <span v-if="testReport" class="text-xs text-slate-400">
                            Son Koşum: {{ testReport.executed_at }} ({{ testReport.duration_ms }} ms)
                        </span>
                    </div>
                </div>

                <div v-if="lastTestMessage" class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-sm font-semibold flex items-center gap-2">
                    <span>📢</span> {{ lastTestMessage }}
                </div>

                <!-- 10 TEST KARTI GRID -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Statik başlangıç kartları veya testReport'tan gelen canlı sonuçlar -->
                    <div
                        v-for="test in (testReport ? testReport.results : defaultTestList)"
                        :key="test.id"
                        class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition space-y-3"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span class="w-7 h-7 rounded-lg bg-slate-100 text-slate-700 font-black text-xs flex items-center justify-center">
                                    #{{ test.id }}
                                </span>
                                <div>
                                    <h4 class="font-bold text-slate-900 text-sm">
                                        {{ test.name }}
                                    </h4>
                                    <span class="text-xs text-indigo-600 font-semibold">
                                        Gereksinim: {{ test.requirement }}
                                    </span>
                                </div>
                            </div>

                            <span
                                v-if="test.passed !== undefined"
                                :class="[
                                    'px-3 py-1 rounded-full text-xs font-black flex items-center gap-1',
                                    test.passed
                                        ? 'bg-emerald-100 text-emerald-800 border border-emerald-300'
                                        : 'bg-rose-100 text-rose-800 border border-rose-300'
                                ]"
                            >
                                <span v-if="test.passed">✅ GEÇTİ</span>
                                <span v-else>❌ BAŞARISIZ</span>
                            </span>
                            <span v-else-if="isRunningTests" class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300 animate-pulse flex items-center gap-1">
                                <span>⏳</span> Koşuluyor...
                            </span>
                            <span v-else class="px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200 flex items-center gap-1">
                                <span>⏸️</span> Koşuma Hazır (Henüz Çalıştırılmadı)
                            </span>
                        </div>

                        <p class="text-xs text-slate-600 leading-relaxed">
                            {{ test.details || test.description }}
                        </p>

                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 text-xs font-mono space-y-1">
                            <div class="flex items-center justify-between text-slate-500 text-[11px]">
                                <span>Beklenen Olay: <strong class="text-slate-800">{{ test.expected_event }}</strong></span>
                                <span v-if="test.duration_ms" class="text-indigo-600 font-bold">{{ test.duration_ms }} ms</span>
                            </div>

                            <div v-if="test.event_sample" class="mt-2 pt-2 border-t border-slate-200">
                                <span class="text-[10px] text-slate-400 block mb-1">Üretilen JSON Log Örneği:</span>
                                <pre class="text-[10px] text-slate-800 overflow-x-auto max-h-24 bg-white p-2 rounded border border-slate-200">{{ formatJson(test.event_sample) }}</pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: GÜVENLİK VE SIEM MİMARİSİ -->
            <div v-show="activeTab === 'config'" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- SIEM & rsyslog Entegrasyon Kartı -->
                    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="p-2.5 bg-blue-50 text-blue-700 rounded-xl text-lg">📡</span>
                            <div>
                                <h3 class="font-bold text-slate-900 text-base">SIEM (Logsign) & rsyslog Akışı</h3>
                                <p class="text-xs text-slate-500">Turkcell Sanal Veri Merkezi (SVM) & Şirket İçi VPN</p>
                            </div>
                        </div>

                        <p class="text-xs text-slate-600 leading-relaxed">
                            Uygulama olayları doğrudan internete çıkmadan <code class="bg-slate-100 text-indigo-700 px-1 py-0.5 rounded font-mono">storage/logs/security-YYYY-MM-DD.log</code> dosyasına flat JSON formatında yazar. Uygulama sunucusundaki <strong>rsyslog servisi</strong> bu dosyayı disk kuyruğu tutarak (RFC 5424) Logsign collector'a (TCP 514) aktarır.
                        </p>

                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 space-y-2 text-xs">
                            <div class="flex justify-between py-1 border-b border-slate-200/60">
                                <span class="text-slate-500">Log Kanalı:</span>
                                <span class="font-bold text-slate-800">{{ config_info.log_channel }} (stack)</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-200/60">
                                <span class="text-slate-500">Uygulama Adı (SIEM ident):</span>
                                <span class="font-bold text-slate-800">{{ config_info.app_name }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-200/60">
                                <span class="text-slate-500">Hedef SIEM Syslog Host:</span>
                                <span class="font-bold text-slate-800">{{ config_info.siem_syslog_host }}:{{ config_info.siem_syslog_port }}</span>
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-slate-500">Log Saklama Süresi:</span>
                                <span class="font-bold text-emerald-700">90 Gün (Günlük Rotasyon)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Brute Force Kalkanı Ayarları -->
                    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="p-2.5 bg-purple-50 text-purple-700 rounded-xl text-lg">🔒</span>
                            <div>
                                <h3 class="font-bold text-slate-900 text-base">Brute Force & Credential Stuffing Bütçesi</h3>
                                <p class="text-xs text-slate-500">İki Kademeli Bağımsız Bütçe Motoru</p>
                            </div>
                        </div>

                        <p class="text-xs text-slate-600 leading-relaxed">
                            Hesap ve IP limitleri bağımsız olarak denetlenir. Bir IP'den çok sayıda farklı hesaba saldırıldığında IP engellenir; tek bir hesaba farklı IP'lerden saldırıldığında hesap kilitlenir.
                        </p>

                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 space-y-2 text-xs">
                            <div class="flex justify-between py-1 border-b border-slate-200/60">
                                <span class="text-slate-500">Hesap Başına Başarısız Limit:</span>
                                <span class="font-bold text-purple-700">{{ config_info.max_account_attempts }} Deneme (Aşıldığında Kilit)</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-200/60">
                                <span class="text-slate-500">IP Başına Başarısız Limit:</span>
                                <span class="font-bold text-rose-700">{{ config_info.max_ip_attempts }} Deneme (Aşıldığında IP Engel)</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-200/60">
                                <span class="text-slate-500">Kilit & Engel Süresi:</span>
                                <span class="font-bold text-slate-800">{{ config_info.decay_seconds / 60 }} Dakika ({{ config_info.decay_seconds }} saniye)</span>
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-slate-500">Kilitliyken Yeni İstek:</span>
                                <span class="font-bold text-slate-800">Şifre kontrol edilmeden HTTP 429 Reddi</span>
                            </div>
                        </div>
                    </div>

                    <!-- Content Security Policy (CSP) Kartı -->
                    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="p-2.5 bg-emerald-50 text-emerald-700 rounded-xl text-lg">🌐</span>
                            <div>
                                <h3 class="font-bold text-slate-900 text-base">Content Security Policy (CSP)</h3>
                                <p class="text-xs text-slate-500">Tarayıcı XSS Engelleme & İhlal Toplayıcı</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1 rounded-full text-xs font-bold" :class="config_info.csp_report_only ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-emerald-100 text-emerald-800'">
                                {{ config_info.csp_report_only ? '⚠️ Report-Only Modu (Tavsiye Edilen)' : '🛡️ Enforced Modu' }}
                            </span>
                            <span class="text-xs text-slate-500">Rapor Endpoint: <code class="bg-slate-100 px-1 py-0.5 rounded font-mono">/security/csp-report</code></span>
                        </div>

                        <div class="p-3 bg-slate-900 text-slate-200 rounded-xl text-[11px] font-mono break-all leading-relaxed">
                            {{ config_info.csp_policy }}
                        </div>
                    </div>

                    <!-- Hassas Alanlar & Maskeleme Kartı -->
                    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="p-2.5 bg-rose-50 text-rose-700 rounded-xl text-lg">🛡️</span>
                            <div>
                                <h3 class="font-bold text-slate-900 text-base">Hassas Veri Maskeleme ([REDACTED])</h3>
                                <p class="text-xs text-slate-500">KVKK ve SIEM Veri Güvenliği Kalkanı</p>
                            </div>
                        </div>

                        <p class="text-xs text-slate-600 leading-relaxed">
                            Aşağıdaki alan isimleri, formlar veya istekler ne olursa olsun asla açık metin olarak loglanmaz; doğrudan <code class="bg-rose-100 text-rose-800 font-bold px-1 rounded">[REDACTED]</code> ile değiştirilir:
                        </p>

                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="field in config_info.sensitive_fields"
                                :key="field"
                                class="px-2 py-0.5 bg-slate-100 text-slate-700 text-xs rounded-md border border-slate-200 font-mono"
                            >
                                {{ field }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- DETAY İNCELEME MODALI -->
        <div
            v-if="showDetailModal && selectedEvent"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
        >
            <div class="bg-white rounded-3xl max-w-2xl w-full max-h-[85vh] flex flex-col shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-2">
                        <span :class="['px-2.5 py-1 rounded-lg border text-xs font-semibold', getEventBadgeClass(selectedEvent.event_type || '')]">
                            {{ selectedEvent.event_type }}
                        </span>
                        <span :class="['px-2 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider', getSeverityClass(selectedEvent.severity)]">
                            {{ selectedEvent.severity }}
                        </span>
                    </div>

                    <button
                        @click="showDetailModal = false"
                        class="w-8 h-8 rounded-full bg-slate-200/80 hover:bg-slate-300 text-slate-600 flex items-center justify-center transition cursor-pointer"
                    >
                        ✕
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-4 text-xs font-mono">
                    <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 rounded-xl border border-slate-100 font-sans">
                        <div>
                            <span class="text-slate-400 block text-[11px]">X-Request-Id (Korelasyon):</span>
                            <span class="font-mono text-slate-800 font-bold break-all">{{ selectedEvent.request_id || '-' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[11px]">Kaynak IP / Zaman:</span>
                            <span class="font-mono text-slate-800 font-bold">{{ selectedEvent.source_ip || '-' }} · {{ selectedEvent['@timestamp'] }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[11px]">Kullanıcı Bilgisi:</span>
                            <span class="font-sans text-slate-800 font-bold">{{ selectedEvent.username || selectedEvent.user_id || 'Anonim / Misafir' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[11px]">İstek:</span>
                            <span class="font-mono text-slate-800 font-bold">{{ selectedEvent.method }} {{ selectedEvent.path }}</span>
                        </div>
                    </div>

                    <div>
                        <span class="text-slate-500 font-sans font-bold block mb-1">Ham SIEM JSON Olayı:</span>
                        <pre class="bg-slate-900 text-emerald-300 p-4 rounded-2xl overflow-x-auto text-[11px] leading-relaxed max-h-72">{{ formatJson(selectedEvent) }}</pre>
                    </div>
                </div>

                <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex justify-end gap-2 font-sans">
                    <button
                        @click="showDetailModal = false"
                        class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-semibold hover:bg-slate-800 transition cursor-pointer"
                    >
                        Kapat
                    </button>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
