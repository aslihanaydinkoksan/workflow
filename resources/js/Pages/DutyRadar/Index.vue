<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    duties: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
    categoryCounts: {
        type: Object,
        default: () => ({}),
    },
    stats: {
        type: Object,
        default: () => ({
            total_on_duty: 0,
            in_factory: 0,
            active_delegations: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            date_filter: 'today',
            start_date: null,
            end_date: null,
            category: 'all',
            search: '',
        }),
    },
});

const searchInput = ref(props.filters.search || '');
const dateFilter = ref(props.filters.date_filter || 'today');
const customStart = ref(props.filters.start_date || '');
const customEnd = ref(props.filters.end_date || '');
const selectedCategory = ref(props.filters.category || 'all');

const filteredDuties = computed(() => {
    let list = props.duties || [];
    if (selectedCategory.value !== 'all') {
        list = list.filter(d => d.category?.code === selectedCategory.value);
    }
    if (searchInput.value.trim()) {
        const s = searchInput.value.trim().toLowerCase();
        list = list.filter(d => 
            (d.person?.name && d.person.name.toLowerCase().includes(s)) ||
            (d.destination_company && d.destination_company.toLowerCase().includes(s)) ||
            (d.location && d.location.toLowerCase().includes(s)) ||
            (d.purpose && d.purpose.toLowerCase().includes(s)) ||
            (d.person?.department && d.person.department.toLowerCase().includes(s))
        );
    }
    return list;
});

const applyFilter = (newParams = {}) => {
    const params = {
        date_filter: dateFilter.value,
        start_date: dateFilter.value === 'custom' ? customStart.value : undefined,
        end_date: dateFilter.value === 'custom' ? customEnd.value : undefined,
        category: selectedCategory.value,
        search: searchInput.value.trim() ? searchInput.value.trim() : undefined,
        ...newParams,
    };

    router.get(route('duty-radar.index'), params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const setDateFilter = (filter) => {
    dateFilter.value = filter;
    applyFilter({ date_filter: filter });
};

const setCategoryFilter = (catCode) => {
    selectedCategory.value = catCode;
    applyFilter({ category: catCode });
};

const handleSearch = () => {
    applyFilter({ search: searchInput.value.trim() || undefined });
};

const resetFilters = () => {
    searchInput.value = '';
    dateFilter.value = 'today';
    customStart.value = '';
    customEnd.value = '';
    selectedCategory.value = 'all';
    router.get(route('duty-radar.index'));
};

const getCategoryColorClasses = (color) => {
    const map = {
        indigo: 'bg-indigo-50 text-indigo-700 border-indigo-200 ring-indigo-500/20',
        emerald: 'bg-emerald-50 text-emerald-700 border-emerald-200 ring-emerald-500/20',
        blue: 'bg-blue-50 text-blue-700 border-blue-200 ring-blue-500/20',
        amber: 'bg-amber-50 text-amber-800 border-amber-200 ring-amber-500/20',
        purple: 'bg-purple-50 text-purple-700 border-purple-200 ring-purple-500/20',
        teal: 'bg-teal-50 text-teal-700 border-teal-200 ring-teal-500/20',
        slate: 'bg-slate-100 text-slate-700 border-slate-300 ring-slate-500/20',
        rose: 'bg-rose-50 text-rose-700 border-rose-200 ring-rose-500/20',
    };
    return map[color] || map.indigo;
};

const getInitials = (name) => {
    if (!name) return '??';
    const parts = name.trim().split(' ');
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
};
</script>

<template>
    <Head title="📍 Canlı Saha & Personel Radarı (Kim Nerede?)" />

    <AuthenticatedLayout>
        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Header & Executive Hero Banner -->
                <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white rounded-2xl p-6 sm:p-8 shadow-xl border border-slate-800 relative overflow-hidden">
                    <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
                    <div class="absolute -left-10 -top-10 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

                    <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                        <div>
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 text-xs font-semibold uppercase tracking-wider mb-3">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                Canlı Operasyonel Saha Görünümü
                            </div>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white flex items-center gap-3">
                                <span>📍 Kim Nerede?</span>
                                <span class="text-xs sm:text-sm font-normal text-slate-300 bg-white/10 px-3 py-1 rounded-lg border border-white/10">
                                    Personel & Saha Radarı
                                </span>
                            </h1>
                            <p class="mt-2 text-sm text-slate-300 max-w-2xl leading-relaxed">
                                Köksan genelinde fuarda, müşteri ziyaretinde, sahada veya dış görevde olan tüm personelin anlık lokasyon, görev ve vekalet haritası.
                            </p>
                        </div>

                        <!-- Admin Management Action Button -->
                        <div class="flex items-center gap-3 self-start md:self-center">
                            <Link
                                v-if="$page.props.auth?.permissions?.includes('view_admin_panel')"
                                :href="route('admin.duty-categories.index')"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-semibold border border-white/20 transition-all shadow-sm hover:shadow"
                            >
                                <span>⚙️</span>
                                <span>Görev Türlerini Yönet</span>
                            </Link>

                            <Link
                                :href="route('processes.create', 9)"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white text-xs font-bold transition-all shadow-lg shadow-emerald-500/20"
                            >
                                <span>✈️</span>
                                <span>Yeni Dış Görev Talebi</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Executive Stats Row -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-8 pt-6 border-t border-white/10">
                        <div class="bg-white/5 backdrop-blur-xs rounded-xl p-4 border border-white/10 flex items-center gap-4">
                            <div class="w-12 h-12 rounded-xl bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center text-2xl">
                                🎪
                            </div>
                            <div>
                                <div class="text-xs font-medium text-slate-300 uppercase tracking-wider">Sahada / Dış Görevde</div>
                                <div class="text-2xl font-black text-white flex items-center gap-2">
                                    {{ stats.total_on_duty }}
                                    <span class="text-xs font-normal text-emerald-400 bg-emerald-500/20 px-2 py-0.5 rounded-full">Kişi</span>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white/5 backdrop-blur-xs rounded-xl p-4 border border-white/10 flex items-center gap-4">
                            <div class="w-12 h-12 rounded-xl bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center text-2xl">
                                🏭
                            </div>
                            <div>
                                <div class="text-xs font-medium text-slate-300 uppercase tracking-wider">Fabrikada / Merkezde</div>
                                <div class="text-2xl font-black text-white flex items-center gap-2">
                                    {{ stats.in_factory }}
                                    <span class="text-xs font-normal text-indigo-300 bg-indigo-500/20 px-2 py-0.5 rounded-full">Kişi</span>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white/5 backdrop-blur-xs rounded-xl p-4 border border-white/10 flex items-center gap-4">
                            <div class="w-12 h-12 rounded-xl bg-amber-500/20 border border-amber-400/30 flex items-center justify-center text-2xl">
                                💼
                            </div>
                            <div>
                                <div class="text-xs font-medium text-slate-300 uppercase tracking-wider">Aktif Vekaletler</div>
                                <div class="text-2xl font-black text-white flex items-center gap-2">
                                    {{ stats.active_delegations }}
                                    <span class="text-xs font-normal text-amber-300 bg-amber-500/20 px-2 py-0.5 rounded-full">Devir</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters & Category Navigation -->
                <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 space-y-4">
                    <!-- Top Bar: Date Preset Tabs & Search -->
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <!-- Date Filter Buttons -->
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                @click="setDateFilter('today')"
                                type="button"
                                class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                                :class="dateFilter === 'today'
                                    ? 'bg-slate-900 text-white shadow-sm ring-2 ring-slate-900/10'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            >
                                <span>⚡</span>
                                <span>Bugün Sahada Olanlar</span>
                            </button>

                            <button
                                @click="setDateFilter('this_week')"
                                type="button"
                                class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                                :class="dateFilter === 'this_week'
                                    ? 'bg-slate-900 text-white shadow-sm ring-2 ring-slate-900/10'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            >
                                <span>📅</span>
                                <span>Bu Hafta</span>
                            </button>

                            <button
                                @click="setDateFilter('this_month')"
                                type="button"
                                class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                                :class="dateFilter === 'this_month'
                                    ? 'bg-slate-900 text-white shadow-sm ring-2 ring-slate-900/10'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            >
                                <span>📆</span>
                                <span>Bu Ay</span>
                            </button>

                            <button
                                @click="setDateFilter('custom')"
                                type="button"
                                class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                                :class="dateFilter === 'custom'
                                    ? 'bg-slate-900 text-white shadow-sm ring-2 ring-slate-900/10'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            >
                                <span>🔍</span>
                                <span>Özel Tarih Aralığı</span>
                            </button>
                        </div>

                        <!-- Search Bar -->
                        <div class="relative w-full lg:w-80">
                            <input
                                v-model="searchInput"
                                @keyup.enter="handleSearch"
                                type="text"
                                placeholder="Personel, firma, fuar, şehir ara..."
                                class="w-full pl-9 pr-20 py-2 text-xs rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 bg-slate-50 focus:bg-white transition"
                            />
                            <span class="absolute left-3 top-2.5 text-slate-400 text-sm">🔍</span>
                            <button
                                v-if="searchInput"
                                @click="handleSearch"
                                type="button"
                                class="absolute right-1.5 top-1.5 px-2.5 py-1 text-[11px] font-bold bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition"
                            >
                                Ara
                            </button>
                        </div>
                    </div>

                    <!-- Custom Date Range Pickers (If selected) -->
                    <div v-if="dateFilter === 'custom'" class="flex flex-wrap items-center gap-3 pt-3 border-t border-slate-100 bg-slate-50 p-3 rounded-xl">
                        <div class="flex items-center gap-2 text-xs text-slate-700 font-semibold">
                            <span>Başlangıç:</span>
                            <input
                                v-model="customStart"
                                type="date"
                                class="px-2.5 py-1 text-xs rounded-lg border-slate-300 focus:ring-indigo-500"
                            />
                        </div>
                        <div class="flex items-center gap-2 text-xs text-slate-700 font-semibold">
                            <span>Bitiş:</span>
                            <input
                                v-model="customEnd"
                                type="date"
                                class="px-2.5 py-1 text-xs rounded-lg border-slate-300 focus:ring-indigo-500"
                            />
                        </div>
                        <button
                            @click="applyFilter()"
                            type="button"
                            class="px-3 py-1 bg-indigo-600 text-white text-xs font-bold rounded-lg hover:bg-indigo-700 transition"
                        >
                            Tarihi Uygula
                        </button>
                    </div>

                    <!-- Category Pills Navigation (Loaded Dynamically From DB) -->
                    <div class="flex items-center gap-2 overflow-x-auto pb-1 pt-1 no-scrollbar border-t border-slate-100">
                        <button
                            @click="setCategoryFilter('all')"
                            type="button"
                            class="px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition flex items-center gap-1.5 border"
                            :class="selectedCategory === 'all'
                                ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                        >
                            <span>🌐</span>
                            <span>Tüm Kategoriler</span>
                            <span
                                class="px-1.5 py-0.2 text-[10px] rounded-full font-bold ml-1"
                                :class="selectedCategory === 'all' ? 'bg-indigo-800 text-white' : 'bg-slate-100 text-slate-600'"
                            >
                                {{ duties.length }}
                            </span>
                        </button>

                        <button
                            v-for="cat in categories"
                            :key="cat.id"
                            @click="setCategoryFilter(cat.code)"
                            type="button"
                            class="px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition flex items-center gap-1.5 border"
                            :class="selectedCategory === cat.code
                                ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                        >
                            <span>{{ cat.icon }}</span>
                            <span>{{ cat.name }}</span>
                            <span
                                class="px-1.5 py-0.2 text-[10px] rounded-full font-bold ml-1"
                                :class="selectedCategory === cat.code ? 'bg-indigo-800 text-white' : 'bg-slate-100 text-slate-600'"
                            >
                                {{ categoryCounts[cat.code] || 0 }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Active Filter Tags / Reset Button -->
                <div v-if="selectedCategory !== 'all' || searchInput || dateFilter !== 'today'" class="flex items-center justify-between text-xs text-slate-600 px-1">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-slate-800">Uygulanan Filtreler:</span>
                        <span v-if="dateFilter !== 'today'" class="px-2.5 py-0.5 rounded-md bg-slate-200 text-slate-700 font-medium">
                            Tarih: {{ dateFilter === 'this_week' ? 'Bu Hafta' : (dateFilter === 'this_month' ? 'Bu Ay' : 'Özel Tarih') }}
                        </span>
                        <span v-if="selectedCategory !== 'all'" class="px-2.5 py-0.5 rounded-md bg-indigo-100 text-indigo-800 font-medium">
                            Kategori: {{ categories.find(c => c.code === selectedCategory)?.name || selectedCategory }}
                        </span>
                        <span v-if="searchInput" class="px-2.5 py-0.5 rounded-md bg-amber-100 text-amber-800 font-medium">
                            Arama: "{{ searchInput }}"
                        </span>
                    </div>
                    <button
                        @click="resetFilters"
                        type="button"
                        class="text-indigo-600 hover:text-indigo-800 font-bold hover:underline"
                    >
                        ✕ Filtreleri Sıfırla
                    </button>
                </div>

                <!-- Duty Personnel Cards Grid -->
                <div v-if="filteredDuties.length > 0" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    <div
                        v-for="duty in filteredDuties"
                        :key="duty.id"
                        class="bg-white rounded-2xl border transition-all duration-200 hover:shadow-lg flex flex-col justify-between overflow-hidden group"
                        :class="duty.is_ongoing_today
                            ? 'border-emerald-300 ring-2 ring-emerald-500/10 shadow-sm'
                            : 'border-slate-200 hover:border-slate-300 shadow-xs'"
                    >
                        <!-- Card Top: Category Badge & Status Indicator -->
                        <div class="p-5 pb-4 space-y-4">
                            <div class="flex items-center justify-between gap-2">
                                <!-- Dynamic Category Badge -->
                                <span
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border ring-2"
                                    :class="getCategoryColorClasses(duty.category.color)"
                                >
                                    <span>{{ duty.category.icon }}</span>
                                    <span>{{ duty.category.name }}</span>
                                </span>

                                <!-- Live Status Badge -->
                                <span
                                    v-if="duty.is_ongoing_today"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    {{ duty.status_badge }}
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600 border border-slate-200"
                                >
                                    {{ duty.status_badge }}
                                </span>
                            </div>

                            <!-- Person Identity Box -->
                            <div class="flex items-start gap-3.5 pt-1">
                                <div
                                    class="w-12 h-12 rounded-xl flex items-center justify-center font-black text-sm shrink-0 border shadow-xs"
                                    :class="duty.is_ongoing_today
                                        ? 'bg-gradient-to-br from-emerald-500 to-teal-600 text-white border-emerald-400'
                                        : 'bg-gradient-to-br from-slate-700 to-slate-900 text-white border-slate-600'"
                                >
                                    {{ getInitials(duty.person.name) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-base font-bold text-slate-900 truncate group-hover:text-indigo-600 transition-colors">
                                        {{ duty.person.name }}
                                    </h3>
                                    <p class="text-xs text-slate-500 font-medium truncate">
                                        {{ duty.person.title }}
                                    </p>
                                    <p class="text-[11px] text-indigo-600 font-semibold mt-0.5">
                                        🏢 {{ duty.person.department }}
                                    </p>
                                </div>
                            </div>

                            <!-- Destination & Location Box -->
                            <div class="bg-slate-50/80 rounded-xl p-3.5 border border-slate-100 space-y-2">
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="text-slate-400 font-bold shrink-0">Hedef / Firma:</span>
                                    <span class="font-bold text-slate-900 truncate">
                                        {{ duty.destination_company }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="text-slate-400 font-bold shrink-0">Lokasyon:</span>
                                    <span class="font-semibold text-slate-700 truncate">
                                        📍 {{ duty.location }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="text-slate-400 font-bold shrink-0">Tarih Aralığı:</span>
                                    <span class="font-bold text-indigo-700">
                                        📅 {{ duty.start_date }} - {{ duty.end_date }}
                                    </span>
                                </div>
                            </div>

                            <!-- Purpose / Notes -->
                            <div v-if="duty.purpose && duty.purpose !== '-'" class="text-xs text-slate-600 line-clamp-2 italic bg-white p-2 rounded-lg border border-slate-100">
                                "{{ duty.purpose }}"
                            </div>

                            <!-- Travel & Accommodation Pills -->
                            <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                <span v-if="duty.transport" class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                    🚗 {{ duty.transport }}
                                </span>
                                <span v-if="duty.accommodation" class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-purple-50 text-purple-700 border border-purple-200">
                                    🏨 {{ duty.accommodation }}
                                </span>
                            </div>

                            <!-- Active Delegation Notice (Patron Görünümü İçin Kritik Bilgi) -->
                            <div
                                v-if="duty.delegation"
                                class="bg-amber-50/90 border border-amber-200 rounded-xl p-3 flex items-start gap-2.5 text-xs text-amber-900"
                            >
                                <span class="text-base shrink-0">💼</span>
                                <div>
                                    <div class="font-bold">Fabrikada Yerine Bakan Vekil:</div>
                                    <div class="text-[11px] font-medium text-amber-800">
                                        <strong>{{ duty.delegation.delegate_name }}</strong> işleri devraldı.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Footer Actions -->
                        <div class="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between gap-2">
                            <a
                                :href="duty.travel_order_url"
                                target="_blank"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white hover:bg-slate-100 text-slate-700 text-xs font-bold border border-slate-200 shadow-2xs transition"
                                title="Resmi Seyahat & Görev Emri İzin Belgesi PDF"
                            >
                                <span>📄</span>
                                <span>İzin PDF</span>
                            </a>

                            <a
                                :href="duty.tracker_url"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition"
                            >
                                <span>🧭 Süreç Takibi</span>
                                <span>→</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-else
                    class="bg-white rounded-2xl border border-slate-200 p-12 text-center shadow-xs space-y-4"
                >
                    <div class="w-20 h-20 rounded-full bg-slate-100 mx-auto flex items-center justify-center text-4xl shadow-inner">
                        🗺️
                    </div>
                    <div class="max-w-md mx-auto">
                        <h3 class="text-lg font-bold text-slate-900">
                            Seçilen Kriterde Sahada Personel Bulunamadı
                        </h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Şu anda bu tarih aralığında ve kategoride kayıtlı bir dış görev, fuar veya müşteri ziyareti bulunmuyor.
                        </p>
                    </div>
                    <div class="pt-2 flex justify-center gap-3">
                        <button
                            @click="resetFilters"
                            type="button"
                            class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition"
                        >
                            Filtreleri Temizle
                        </button>
                        <Link
                            :href="route('processes.create', 9)"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-xs"
                        >
                            + Yeni Dış Görev Başlat
                        </Link>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
