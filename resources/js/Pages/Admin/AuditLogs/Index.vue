<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    audits: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        default: () => ({ total: 0, sod: 0, doa: 0, admin_ops: 0, timers: 0, revisions: 0 }),
    },
    eventDefinitions: {
        type: Object,
        default: () => ({}),
    },
    users: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const filterForm = ref({
    event_type: props.filters.event_type || 'all',
    user_id: props.filters.user_id || 'all',
    process_instance_id: props.filters.process_instance_id || '',
    search: props.filters.search || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
});

const isFilterLoading = ref(false);

const applyFilters = () => {
    isFilterLoading.value = true;
    router.get(route('admin.audit-logs.index'), filterForm.value, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            isFilterLoading.value = false;
        },
    });
};

const resetFilters = () => {
    filterForm.value = {
        event_type: 'all',
        user_id: 'all',
        process_instance_id: '',
        search: '',
        date_from: '',
        date_to: '',
    };
    applyFilters();
};

const selectedAudit = ref(null);
const showDiffModal = ref(false);

const openDiffModal = (audit) => {
    selectedAudit.value = audit;
    showDiffModal.value = true;
};

const closeDiffModal = () => {
    showDiffModal.value = false;
    selectedAudit.value = null;
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('tr-TR', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return dateStr;
    }
};

const getEventMeta = (type) => {
    return props.eventDefinitions[type] || {
        label: type,
        badge: 'bg-gray-100 text-gray-800 border-gray-300',
        icon: '📌',
        desc: 'Sistem denetim kaydı',
    };
};

const isJsonString = (str) => {
    if (!str || typeof str !== 'string') return false;
    try {
        const parsed = JSON.parse(str);
        return typeof parsed === 'object' && parsed !== null;
    } catch {
        return false;
    }
};

const formatValueDisplay = (val) => {
    if (val === null || val === undefined || val === '') return '(Boş)';
    if (typeof val === 'string' && isJsonString(val)) {
        try {
            return JSON.stringify(JSON.parse(val), null, 2);
        } catch {
            return val;
        }
    }
    return String(val);
};
</script>

<template>
    <Head title="Kurumsal Denetim İzi & Güvenlik Kokpiti" />

    <AuthenticatedLayout>
        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- HERO HEADER -->
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 md:p-8 text-white shadow-xl border border-indigo-900/50">
                    <div class="absolute -right-10 -bottom-10 opacity-10 text-indigo-400 select-none pointer-events-none">
                        <svg class="w-80 h-80" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 2.18l7 3.12v4.7c0 4.67-3.13 9.04-7 10.15-3.87-1.11-7-5.48-7-10.15V6.3l7-3.12zM11 7v6l5 3 .75-1.23-4.25-2.52V7H11z"/>
                        </svg>
                    </div>

                    <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                    SOX & Fortune-500 Uyumlu İç Denetim
                                </span>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                    Canlı Alan Bazlı Git-Diff Günlüğü
                                </span>
                            </div>
                            <h1 class="text-2xl md:text-3xl font-extrabold text-white tracking-tight flex items-center gap-3">
                                <span>🛡️</span> Kurumsal Denetim İzi & Güvenlik Kokpiti
                            </h1>
                            <p class="mt-2 text-sm text-indigo-200 max-w-2xl leading-relaxed">
                                Süreçler üzerinde gerçekleşen tüm 4-Göz İlkesi (SoD) eskalasyonları, Finansal Yetki (DoA) aşım bypassları, yönetici müdahaleleri ve form değişiklikleri tam denetim iziyle kayıt altındadır.
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            <button @click="resetFilters"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800/80 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-lg border border-slate-700 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                Yenile
                            </button>
                        </div>
                    </div>
                </div>

                <!-- STATS CARDS -->
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <div class="bg-white rounded-xl p-4 shadow-sm border border-slate-200/80 hover:border-slate-300 transition">
                        <div class="flex items-center justify-between text-xs text-slate-500 font-medium">
                            <span>Toplam Kayıt</span>
                            <span class="text-slate-400">📋</span>
                        </div>
                        <div class="mt-2 text-2xl font-black text-slate-900">{{ stats.total }}</div>
                        <div class="mt-1 text-[11px] text-slate-400">Tüm sistem olayları</div>
                    </div>

                    <div class="bg-white rounded-xl p-4 shadow-sm border border-rose-200/80 hover:border-rose-300 transition bg-gradient-to-br from-white to-rose-50/30">
                        <div class="flex items-center justify-between text-xs text-rose-700 font-medium">
                            <span>4-Göz (SoD)</span>
                            <span class="text-rose-500">🛡️</span>
                        </div>
                        <div class="mt-2 text-2xl font-black text-rose-700">{{ stats.sod }}</div>
                        <div class="mt-1 text-[11px] text-rose-600/80">Kendi onayını devretme</div>
                    </div>

                    <div class="bg-white rounded-xl p-4 shadow-sm border border-amber-200/80 hover:border-amber-300 transition bg-gradient-to-br from-white to-amber-50/30">
                        <div class="flex items-center justify-between text-xs text-amber-700 font-medium">
                            <span>Yetki (DoA) Aşımı</span>
                            <span class="text-amber-500">💰</span>
                        </div>
                        <div class="mt-2 text-2xl font-black text-amber-700">{{ stats.doa }}</div>
                        <div class="mt-1 text-[11px] text-amber-600/80">Tutar limit eskalasyonu</div>
                    </div>

                    <div class="bg-white rounded-xl p-4 shadow-sm border border-purple-200/80 hover:border-purple-300 transition bg-gradient-to-br from-white to-purple-50/30">
                        <div class="flex items-center justify-between text-xs text-purple-700 font-medium">
                            <span>Yönetici Müdahalesi</span>
                            <span class="text-purple-500">⚡</span>
                        </div>
                        <div class="mt-2 text-2xl font-black text-purple-700">{{ stats.admin_ops }}</div>
                        <div class="mt-1 text-[11px] text-purple-600/80">Jump & Reassign</div>
                    </div>

                    <div class="bg-white rounded-xl p-4 shadow-sm border border-orange-200/80 hover:border-orange-300 transition bg-gradient-to-br from-white to-orange-50/30">
                        <div class="flex items-center justify-between text-xs text-orange-700 font-medium">
                            <span>SLA & Zamanlayıcı</span>
                            <span class="text-orange-500">⏰</span>
                        </div>
                        <div class="mt-2 text-2xl font-black text-orange-700">{{ stats.timers }}</div>
                        <div class="mt-1 text-[11px] text-orange-600/80">Otomatik eskalasyon</div>
                    </div>

                    <div class="bg-white rounded-xl p-4 shadow-sm border border-blue-200/80 hover:border-blue-300 transition bg-gradient-to-br from-white to-blue-50/30">
                        <div class="flex items-center justify-between text-xs text-blue-700 font-medium">
                            <span>Revizyon İsteği</span>
                            <span class="text-blue-500">📝</span>
                        </div>
                        <div class="mt-2 text-2xl font-black text-blue-700">{{ stats.revisions }}</div>
                        <div class="mt-1 text-[11px] text-blue-600/80">Düzeltme döngüleri</div>
                    </div>
                </div>

                <!-- FILTERS TOOLBAR -->
                <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-200/80 space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                        <div class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-2">
                            <span>🔍</span> Arama ve Denetim Filtreleri
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="applyFilters"
                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                                <span v-if="isFilterLoading">Uygulanıyor...</span>
                                <span v-else>Filtreleri Uygula</span>
                            </button>
                            <button @click="resetFilters"
                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-medium rounded-lg transition">
                                Sıfırla
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3">
                        <!-- Olay Tipi -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Olay Türü</label>
                            <select v-model="filterForm.event_type" @change="applyFilters"
                                class="w-full text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 py-1.5">
                                <option value="all">Tüm Olay Tipleri</option>
                                <option v-for="(meta, key) in eventDefinitions" :key="key" :value="key">
                                    {{ meta.icon }} {{ meta.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Personel -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">İşlemi Yapan Personel</label>
                            <select v-model="filterForm.user_id" @change="applyFilters"
                                class="w-full text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 py-1.5">
                                <option value="all">Tüm Personeller</option>
                                <option v-for="u in users" :key="u.id" :value="u.id">
                                    {{ u.name }} ({{ u.email }})
                                </option>
                            </select>
                        </div>

                        <!-- Süreç ID -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Süreç ID</label>
                            <input v-model="filterForm.process_instance_id" type="number" placeholder="Örn: 104"
                                @keyup.enter="applyFilters"
                                class="w-full text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 py-1.5" />
                        </div>

                        <!-- Metin Arama -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Metin Arama (Alan, Değer, Not)</label>
                            <input v-model="filterForm.search" type="text" placeholder="Kelime ara..."
                                @keyup.enter="applyFilters"
                                class="w-full text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 py-1.5" />
                        </div>

                        <!-- Tarih Aralığı -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Tarih Aralığı</label>
                            <div class="grid grid-cols-2 gap-1">
                                <input v-model="filterForm.date_from" type="date"
                                    class="w-full text-[11px] rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 py-1 px-1.5" />
                                <input v-model="filterForm.date_to" type="date"
                                    class="w-full text-[11px] rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 py-1 px-1.5" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TABLE SECTION -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200/80 overflow-hidden">
                    <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-slate-800">Denetim Günlüğü Kayıtları</span>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 font-semibold">
                                {{ audits.total }} kayıt bulundu
                            </span>
                        </div>
                        <div class="text-xs text-slate-400">
                            Sayfa {{ audits.current_page }} / {{ audits.last_page }}
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-600 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                                <tr>
                                    <th class="py-3 px-4">Tarih / Saat</th>
                                    <th class="py-3 px-4">Olay Türü</th>
                                    <th class="py-3 px-4">Süreç & Talep</th>
                                    <th class="py-3 px-4">İşlemi Yapan</th>
                                    <th class="py-3 px-4">Alan / İşlem</th>
                                    <th class="py-3 px-4">Değişiklik / Not</th>
                                    <th class="py-3 px-4 text-right">İşlem</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-if="!audits.data || audits.data.length === 0">
                                    <td colspan="7" class="py-12 text-center text-slate-400">
                                        <div class="flex flex-col items-center justify-center">
                                            <span class="text-3xl mb-2">🔍</span>
                                            <p class="font-semibold text-slate-600">Eşleşen denetim kaydı bulunamadı</p>
                                            <p class="text-xs text-slate-400 mt-1">Arama filtrelerinizi genişletmeyi deneyin.</p>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-for="audit in audits.data" :key="audit.id" class="hover:bg-slate-50/80 transition group">
                                    <!-- Tarih -->
                                    <td class="py-3 px-4 whitespace-nowrap text-slate-600 font-medium">
                                        {{ formatDate(audit.created_at) }}
                                    </td>

                                    <!-- Olay Türü -->
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border"
                                            :class="getEventMeta(audit.event_type).badge">
                                            <span>{{ getEventMeta(audit.event_type).icon }}</span>
                                            <span>{{ getEventMeta(audit.event_type).label }}</span>
                                        </span>
                                    </td>

                                    <!-- Süreç -->
                                    <td class="py-3 px-4 max-w-xs truncate">
                                        <div v-if="audit.process_instance">
                                            <Link :href="route('processes.tracker', audit.process_instance_id)"
                                                class="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline">
                                                #{{ audit.process_instance_id }} - {{ audit.process_instance.workflow?.name || 'Süreç' }}
                                            </Link>
                                            <div class="text-[10px] text-slate-400 truncate">
                                                Durum: {{ audit.process_instance.status }}
                                            </div>
                                        </div>
                                        <div v-else class="text-slate-400 font-mono text-[11px]">
                                            #{{ audit.process_instance_id || '-' }}
                                        </div>
                                    </td>

                                    <!-- İşlemi Yapan -->
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div v-if="audit.user" class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center font-bold text-[10px]">
                                                {{ audit.user.name.charAt(0) }}
                                            </div>
                                            <div>
                                                <div class="font-semibold text-slate-800">{{ audit.user.name }}</div>
                                                <div class="text-[10px] text-slate-400">{{ audit.user.email }}</div>
                                            </div>
                                        </div>
                                        <div v-else class="text-slate-400 italic">
                                            ⚙️ Sistem / Otomasyon
                                        </div>
                                    </td>

                                    <!-- Alan / İşlem -->
                                    <td class="py-3 px-4 max-w-xs">
                                        <div class="font-semibold text-slate-800 truncate">
                                            {{ audit.field_label || audit.field_key || '-' }}
                                        </div>
                                        <div v-if="audit.field_key && audit.field_label" class="text-[10px] text-slate-400 font-mono">
                                            {{ audit.field_key }}
                                        </div>
                                    </td>

                                    <!-- Değişiklik / Not -->
                                    <td class="py-3 px-4 max-w-sm">
                                        <div v-if="audit.old_value !== null || audit.new_value !== null" class="space-y-1 font-mono text-[11px]">
                                            <div v-if="audit.old_value !== null" class="text-rose-600 line-through truncate max-w-xs">
                                                - {{ audit.old_value }}
                                            </div>
                                            <div v-if="audit.new_value !== null" class="text-emerald-700 font-bold truncate max-w-xs">
                                                + {{ audit.new_value }}
                                            </div>
                                        </div>
                                        <div v-else-if="audit.comment" class="text-slate-600 truncate text-[11px]">
                                            {{ audit.comment }}
                                        </div>
                                        <div v-else class="text-slate-400">-</div>
                                    </td>

                                    <!-- İşlem Butonu -->
                                    <td class="py-3 px-4 text-right whitespace-nowrap">
                                        <button @click="openDiffModal(audit)"
                                            class="px-2.5 py-1 text-[11px] font-semibold text-indigo-600 hover:text-white hover:bg-indigo-600 border border-indigo-200 rounded-md transition shadow-2xs">
                                            İncele 🔍
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINATION -->
                    <div v-if="audits.links && audits.links.length > 3" class="p-4 border-t border-slate-100 flex items-center justify-between">
                        <div class="text-xs text-slate-500">
                            Toplam {{ audits.total }} kayıttan {{ audits.from || 0 }} - {{ audits.to || 0 }} arası gösteriliyor
                        </div>
                        <div class="flex items-center gap-1">
                            <template v-for="(link, i) in audits.links" :key="i">
                                <Link v-if="link.url" :href="link.url"
                                    class="px-3 py-1 text-xs rounded-md border transition"
                                    :class="link.active ? 'bg-indigo-600 text-white border-indigo-600 font-bold' : 'bg-white text-slate-700 hover:bg-slate-50 border-slate-200'"
                                    v-html="link.label" />
                                <span v-else class="px-2 py-1 text-xs text-slate-300" v-html="link.label" />
                            </template>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- DIFF & DETAILS MODAL -->
        <div v-if="showDiffModal && selectedAudit"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-slate-200 overflow-hidden transform transition-all">
                
                <!-- Modal Header -->
                <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">{{ getEventMeta(selectedAudit.event_type).icon }}</span>
                        <div>
                            <h3 class="text-base font-bold text-white">Denetim İzi & Değişiklik Detayı</h3>
                            <p class="text-xs text-slate-400">Kayıt ID: #{{ selectedAudit.id }} · {{ formatDate(selectedAudit.created_at) }}</p>
                        </div>
                    </div>
                    <button @click="closeDiffModal" class="text-slate-400 hover:text-white text-xl font-bold">&times;</button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
                    <!-- Meta Grid -->
                    <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs">
                        <div>
                            <span class="text-slate-400 font-medium">Olay Türü:</span>
                            <div class="mt-0.5">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold border"
                                    :class="getEventMeta(selectedAudit.event_type).badge">
                                    {{ getEventMeta(selectedAudit.event_type).label }}
                                </span>
                            </div>
                        </div>
                        <div>
                            <span class="text-slate-400 font-medium">İşlem Yapan:</span>
                            <div class="mt-0.5 font-bold text-slate-800">
                                {{ selectedAudit.user?.name || 'Sistem / Otomasyon' }}
                            </div>
                        </div>
                        <div>
                            <span class="text-slate-400 font-medium">İlgili Süreç:</span>
                            <div class="mt-0.5 font-semibold text-indigo-600">
                                #{{ selectedAudit.process_instance_id }} - {{ selectedAudit.process_instance?.workflow?.name || '-' }}
                            </div>
                        </div>
                        <div>
                            <span class="text-slate-400 font-medium">İlgili Görev:</span>
                            <div class="mt-0.5 font-semibold text-slate-700">
                                {{ selectedAudit.task?.title || '(Süreç Düzeyi)' }}
                            </div>
                        </div>
                    </div>

                    <!-- Açıklama / Kural Bilgisi -->
                    <div class="p-3 bg-indigo-50/60 rounded-xl border border-indigo-100 text-xs text-indigo-900">
                        <span class="font-bold">Kural & Amaç:</span> {{ getEventMeta(selectedAudit.event_type).desc }}
                    </div>

                    <!-- Yorum / Not -->
                    <div v-if="selectedAudit.comment" class="space-y-1">
                        <label class="block text-xs font-bold text-slate-700">İşlem Gerekçesi / Sistem Notu:</label>
                        <div class="p-3 bg-amber-50 rounded-lg border border-amber-200 text-xs text-amber-900 leading-relaxed font-medium">
                            {{ selectedAudit.comment }}
                        </div>
                    </div>

                    <!-- Git Diff Box -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-xs font-bold text-slate-700">
                            <span>Alan Bazlı Değer Değişimi (Diff):</span>
                            <span class="text-slate-400 font-mono">{{ selectedAudit.field_label || selectedAudit.field_key }}</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <!-- Eski Değer -->
                            <div class="border border-rose-200 rounded-xl bg-rose-50/50 p-3">
                                <div class="flex items-center gap-1.5 text-xs font-bold text-rose-800 mb-1.5">
                                    <span>➖</span> Eski Değer (Before)
                                </div>
                                <pre class="text-[11px] font-mono text-rose-900 whitespace-pre-wrap break-all bg-white p-2.5 rounded-lg border border-rose-200 max-h-48 overflow-y-auto">{{ formatValueDisplay(selectedAudit.old_value) }}</pre>
                            </div>

                            <!-- Yeni Değer -->
                            <div class="border border-emerald-200 rounded-xl bg-emerald-50/50 p-3">
                                <div class="flex items-center gap-1.5 text-xs font-bold text-emerald-800 mb-1.5">
                                    <span>➕</span> Yeni Değer (After)
                                </div>
                                <pre class="text-[11px] font-mono text-emerald-950 whitespace-pre-wrap break-all bg-white p-2.5 rounded-lg border border-emerald-200 max-h-48 overflow-y-auto font-semibold">{{ formatValueDisplay(selectedAudit.new_value) }}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-3 bg-slate-100 border-t border-slate-200 flex items-center justify-between">
                    <Link v-if="selectedAudit.process_instance_id"
                        :href="route('processes.tracker', selectedAudit.process_instance_id)"
                        class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 hover:underline flex items-center gap-1">
                        <span>🎯</span> Süreç Takip Ekranına Git (#{{ selectedAudit.process_instance_id }})
                    </Link>
                    <div v-else></div>

                    <button @click="closeDiffModal"
                        class="px-4 py-1.5 bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold rounded-lg transition">
                        Kapat
                    </button>
                </div>

            </div>
        </div>

    </AuthenticatedLayout>
</template>
