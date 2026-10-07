<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { VueFlow } from '@vue-flow/core';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';

import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';
import '@vue-flow/controls/dist/style.css';

import StartNode from '@/Components/WorkflowNodes/StartNode.vue';
import EndNode from '@/Components/WorkflowNodes/EndNode.vue';
import TaskNode from '@/Components/WorkflowNodes/TaskNode.vue';
import DecisionNode from '@/Components/WorkflowNodes/DecisionNode.vue';
import IONode from '@/Components/WorkflowNodes/IONode.vue';
import StorageNode from '@/Components/WorkflowNodes/StorageNode.vue';
import DocumentNode from '@/Components/WorkflowNodes/DocumentNode.vue';
import WorkflowMigrationModal from '@/Components/WorkflowMigrationModal.vue';

const props = defineProps({
    workflows: {
        type: Array,
        required: true,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    showProcessTracking: {
        type: Boolean,
        default: false,
    },
    canManage: {
        type: Boolean,
        default: false,
    },
});

// Durum Filtresi (Sekmeler)
const statusFilter = ref('all'); // 'all', 'active', 'draft', 'archived'

// Arama & Kategori Filtresi
const searchQuery = ref('');
const selectedCategory = ref('all');

// Metrikler
const totalWorkflowsCount = computed(() => props.workflows.length);
const activeWorkflowsCount = computed(() => props.workflows.filter(w => w.status === 'active').length);
const draftWorkflowsCount = computed(() => props.workflows.filter(w => w.status === 'draft').length);
const totalLiveInstancesCount = computed(() => 
    props.workflows.reduce((acc, w) => acc + (w.active_instances_count || 0), 0)
);

// Filtrelenmiş Akış Listesi
const filteredWorkflows = computed(() => {
    return props.workflows.filter((w) => {
        // 1. Durum Sekmesi Filtresi
        if (statusFilter.value === 'active' && w.status !== 'active') return false;
        if (statusFilter.value === 'draft' && w.status !== 'draft') return false;
        if (statusFilter.value === 'archived' && w.status !== 'archived') return false;

        // 2. Kategori Filtresi
        if (selectedCategory.value !== 'all') {
            const cats = w.categories_list || [];
            if (!cats.includes(selectedCategory.value)) return false;
        }

        // 3. Metin Arama Filtresi
        if (searchQuery.value.trim()) {
            const query = searchQuery.value.toLowerCase();
            const matchName = (w.name || '').toLowerCase().includes(query);
            const matchDesc = (w.description || '').toLowerCase().includes(query);
            const matchForm = (w.form_template?.name || '').toLowerCase().includes(query);
            const matchId = String(w.id).includes(query);
            const matchCats = (w.categories_list || []).some(c => c.toLowerCase().includes(query));
            if (!matchName && !matchDesc && !matchForm && !matchId && !matchCats) return false;
        }

        return true;
    });
});

// Akış Önizleme Modalı
const isPreviewOpen = ref(false);
const selectedWorkflow = ref(null);
const previewNodes = ref([]);
const previewEdges = ref([]);

const openPreview = async (workflow) => {
    selectedWorkflow.value = workflow;

    previewNodes.value = (workflow.nodes || []).map(n => ({
        ...n,
        draggable: false,
        selectable: false
    }));

    previewEdges.value = (workflow.edges || []).map(e => ({
        ...e,
        animated: true,
        style: { stroke: '#0d9488', strokeWidth: 2 }
    }));

    isPreviewOpen.value = true;
};

// CRUD & Yönetim Aksiyonları
const isDeleteModalOpen = ref(false);
const workflowToDelete = ref(null);
const isDeleting = ref(false);
const deleteBlocked = computed(() => (workflowToDelete.value?.active_instances_count || 0) > 0);

const toggleWorkflowStatus = (workflow) => {
    router.post(route('workflows.toggle-status', workflow.id), {}, {
        preserveScroll: true,
    });
};

const duplicateWorkflow = (workflow) => {
    if (confirm(`'${workflow.name}' şablonunu kopyalayarak yeni bir taslak akış oluşturmak istiyor musunuz?`)) {
        router.post(route('workflows.duplicate', workflow.id));
    }
};

const openDeleteModal = (workflow) => {
    workflowToDelete.value = workflow;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = () => {
    isDeleteModalOpen.value = false;
    workflowToDelete.value = null;
    isDeleting.value = false;
};

const confirmDeleteWorkflow = () => {
    if (!workflowToDelete.value || deleteBlocked.value) return;
    isDeleting.value = true;
    router.delete(route('workflows.destroy', workflowToDelete.value.id), {
        preserveScroll: true,
        onFinish: () => {
            closeDeleteModal();
        },
    });
};

// Canlı Süreç Versiyon Göçü (Zero-Downtime Migration)
const isMigrationModalOpen = ref(false);
const workflowToMigrate = ref(null);

const openMigrationModal = (workflow) => {
    workflowToMigrate.value = workflow;
    isMigrationModalOpen.value = true;
};

const handleWorkflowMigrated = () => {
    router.reload({ preserveScroll: true });
};
</script>

<template>
    <Head title="İş Akışı Şablonları & Süreç Kataloğu" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-teal-50 text-teal-700 border border-teal-200">
                            🏢 KÖKSAN BPM SÜREÇ KATALOĞU
                        </span>
                    </div>
                    <h2 class="font-bold text-2xl text-gray-900 leading-tight tracking-tight mt-1">
                        İş Akışı Şablonları & Süreç Tasarımları
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        İşletmelerin (Levha, Preform, Kapak vb.) kurumsal prosedürleri, onay hiyerarşileri ve dinamik form akışları.
                    </p>
                </div>
                <div class="flex items-center gap-2.5">
                    <Link
                        :href="route('processes.history')"
                        class="inline-flex items-center justify-center px-3.5 py-2 border border-gray-300 text-sm font-medium rounded-lg shadow-sm text-gray-700 bg-white hover:bg-gray-50 transition-colors"
                        title="Canlıda yürüyen tüm talepleri incele"
                    >
                        <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                        Canlı Süreç Talepleri (Tracker)
                    </Link>
                    <Link
                        :href="route('workflows.create')"
                        class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 transition-colors"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        Yeni Akış Çiz
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8 bg-gray-50/50 min-h-screen">
            <div class="max-w-[86rem] mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- 1. KPI İstatistik Kartları -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Toplam Şablon -->
                    <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                        <div>
                            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Toplam Akış Şablonu</div>
                            <div class="text-2xl font-black text-gray-900 mt-1">{{ totalWorkflowsCount }}</div>
                            <div class="text-[11px] text-gray-400 mt-0.5">Kalıcı fabrika prosedürleri</div>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center text-xl font-bold">
                            📐
                        </div>
                    </div>

                    <!-- Yayında / Aktif -->
                    <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                        <div>
                            <div class="text-xs font-semibold text-emerald-600 uppercase tracking-wider">Yayında (Aktif)</div>
                            <div class="text-2xl font-black text-emerald-700 mt-1">{{ activeWorkflowsCount }}</div>
                            <div class="text-[11px] text-emerald-600/80 mt-0.5">Kullanıma açık şablonlar</div>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl font-bold">
                            🚀
                        </div>
                    </div>

                    <!-- Taslaklar -->
                    <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                        <div>
                            <div class="text-xs font-semibold text-amber-600 uppercase tracking-wider">Taslak Şablonlar</div>
                            <div class="text-2xl font-black text-amber-700 mt-1">{{ draftWorkflowsCount }}</div>
                            <div class="text-[11px] text-amber-600/80 mt-0.5">Geliştirme aşamasında</div>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl font-bold">
                            📝
                        </div>
                    </div>

                    <!-- Canlıda Yürüyen Talepler -->
                    <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                        <div>
                            <div class="text-xs font-semibold text-sky-600 uppercase tracking-wider flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></span>
                                Yürüyen Canlı Talep
                            </div>
                            <div class="text-2xl font-black text-sky-700 mt-1">{{ totalLiveInstancesCount }}</div>
                            <div class="text-[11px] text-sky-600/80 mt-0.5">Şu an onay bekleyen işler</div>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center text-xl font-bold">
                            ⚡
                        </div>
                    </div>
                </div>

                <!-- 2. Filtreleme & Arama Çubuğu -->
                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <!-- Sekmeler (Status Tabs) -->
                    <div class="flex items-center gap-1 bg-gray-100/80 p-1 rounded-lg">
                        <button
                            type="button"
                            @click="statusFilter = 'all'"
                            :class="[
                                'px-3.5 py-1.5 rounded-md text-xs font-semibold transition-all',
                                statusFilter === 'all' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'
                            ]"
                        >
                            Tüm Şablonlar
                            <span class="ml-1.5 px-1.5 py-0.2 rounded-full text-[10px] bg-gray-200 text-gray-700 font-mono">{{ totalWorkflowsCount }}</span>
                        </button>
                        <button
                            type="button"
                            @click="statusFilter = 'active'"
                            :class="[
                                'px-3.5 py-1.5 rounded-md text-xs font-semibold transition-all',
                                statusFilter === 'active' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-600 hover:text-emerald-700'
                            ]"
                        >
                            Yayındakiler (Aktif)
                            <span class="ml-1.5 px-1.5 py-0.2 rounded-full text-[10px]" :class="statusFilter === 'active' ? 'bg-emerald-700 text-white' : 'bg-emerald-100 text-emerald-800'">
                                {{ activeWorkflowsCount }}
                            </span>
                        </button>
                        <button
                            type="button"
                            @click="statusFilter = 'draft'"
                            :class="[
                                'px-3.5 py-1.5 rounded-md text-xs font-semibold transition-all',
                                statusFilter === 'draft' ? 'bg-amber-600 text-white shadow-sm' : 'text-gray-600 hover:text-amber-700'
                            ]"
                        >
                            Taslaklar
                            <span class="ml-1.5 px-1.5 py-0.2 rounded-full text-[10px]" :class="statusFilter === 'draft' ? 'bg-amber-700 text-white' : 'bg-amber-100 text-amber-800'">
                                {{ draftWorkflowsCount }}
                            </span>
                        </button>
                    </div>

                    <!-- Arama & Kategori -->
                    <div class="flex flex-col sm:flex-row items-center gap-2.5 flex-1 max-w-xl">
                        <!-- Kategori Dropdown -->
                        <div v-if="categories.length > 0" class="w-full sm:w-48">
                            <select
                                v-model="selectedCategory"
                                class="w-full bg-gray-50 border border-gray-200 text-gray-800 text-xs rounded-lg focus:ring-teal-500 focus:border-teal-500 py-2 px-2.5"
                            >
                                <option value="all">📁 Tüm Kategoriler</option>
                                <option v-for="cat in categories" :key="cat" :value="cat">
                                    {{ cat }}
                                </option>
                            </select>
                        </div>

                        <!-- Arama Input -->
                        <div class="relative w-full">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Akış adı, bağlı form veya ID ile ara..."
                                class="w-full bg-gray-50 border border-gray-200 text-gray-800 text-xs rounded-lg focus:ring-teal-500 focus:border-teal-500 pl-8 pr-3 py-2"
                            />
                            <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                    </div>
                </div>

                <!-- 3. Süreç Kataloğu Tablosu -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div v-if="workflows.length === 0" class="p-16 flex flex-col items-center justify-center text-center">
                        <div class="w-20 h-20 mb-4 bg-teal-50 rounded-full flex items-center justify-center text-3xl">
                            📐
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-1">Henüz Bir İş Akışı Şablonu Bulunmuyor</h3>
                        <p class="text-sm text-gray-500 mb-6 max-w-md">Sistemde kayıtlı akış reçetesi yok. Levha, Preform veya Kapak birimleri için ilk akışınızı hemen çizebilirsiniz.</p>
                        <Link :href="route('workflows.create')" class="inline-flex items-center px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm font-semibold transition-colors">
                            Hemen Yeni Akış Çiz &rarr;
                        </Link>
                    </div>

                    <div v-else-if="filteredWorkflows.length === 0" class="p-12 text-center text-gray-500">
                        <div class="text-3xl mb-2">🔍</div>
                        <p class="text-sm font-medium">Arama kriterlerinize uygun iş akışı şablonu bulunamadı.</p>
                        <button @click="searchQuery = ''; selectedCategory = 'all'; statusFilter = 'all'" class="text-xs text-teal-600 hover:underline mt-2 font-semibold">
                            Filtreleri Temizle
                        </button>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50/80">
                                <tr>
                                    <th scope="col" class="px-5 py-3.5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider w-16">ID</th>
                                    <th scope="col" class="px-5 py-3.5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">İş Akışı Şablonu (Ad & Tanım)</th>
                                    <th scope="col" class="px-5 py-3.5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Kategori / Birim</th>
                                    <th scope="col" class="px-5 py-3.5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Bağlı Giriş Formu</th>
                                    <th scope="col" class="px-4 py-3.5 text-center text-xs font-bold text-gray-600 uppercase tracking-wider w-24">Adım</th>
                                    <th scope="col" class="px-5 py-3.5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider w-28">Durum</th>
                                    <th scope="col" class="px-5 py-3.5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider w-36">Canlı Kullanım</th>
                                    <th scope="col" class="px-5 py-3.5 text-right text-xs font-bold text-gray-600 uppercase tracking-wider w-80">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-for="w in filteredWorkflows" :key="w.id" class="hover:bg-teal-50/30 transition-colors group">
                                    <!-- ID -->
                                    <td class="px-5 py-4 whitespace-nowrap text-xs font-mono font-semibold text-gray-400">
                                        #{{ w.id }}
                                    </td>

                                    <!-- Akış Adı & Detay -->
                                    <td class="px-5 py-4">
                                        <div class="flex items-start gap-3">
                                            <div class="w-9 h-9 rounded-lg bg-teal-50 text-teal-700 font-bold flex items-center justify-center shrink-0 border border-teal-100 text-sm">
                                                {{ w.name.charAt(0).toUpperCase() }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm font-bold text-gray-900 truncate" :title="w.name">
                                                        {{ w.name }}
                                                    </span>
                                                    <span 
                                                        class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold font-mono tracking-tight"
                                                        :class="w.is_latest_version ? 'bg-purple-50 text-purple-700 border border-purple-200' : 'bg-amber-50 text-amber-700 border border-amber-200'"
                                                        :title="w.is_latest_version ? 'En Güncel Yayınlanmış Versiyon' : 'Eski Versiyon (Canlı Süreç Göçü Yapılabilir)'"
                                                    >
                                                        v{{ w.version_number }}.0
                                                        <span v-if="!w.is_latest_version" class="ml-1 text-[9px] font-normal text-amber-600">(Eski)</span>
                                                        <span v-else class="ml-1 text-[9px] font-normal text-purple-600">● Son</span>
                                                    </span>
                                                </div>
                                                <p v-if="w.description" class="text-xs text-gray-500 line-clamp-1 mt-0.5" :title="w.description">
                                                    {{ w.description }}
                                                </p>
                                                <div class="flex items-center gap-3 text-[11px] text-gray-400 mt-1">
                                                    <span v-if="w.creator" class="flex items-center gap-1">
                                                        <span>👤</span> {{ w.creator.name }}
                                                    </span>
                                                    <span>📅 {{ new Date(w.created_at).toLocaleDateString('tr-TR') }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Kategori & Birim -->
                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap gap-1.5">
                                            <template v-if="w.categories_list && w.categories_list.length > 0">
                                                <span 
                                                    v-for="cat in w.categories_list" 
                                                    :key="cat"
                                                    class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200"
                                                >
                                                    {{ cat }}
                                                </span>
                                            </template>
                                            <span v-else class="text-xs text-gray-400 italic">
                                                Kategorisiz
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Bağlı Giriş Formu -->
                                    <td class="px-5 py-4 max-w-xs">
                                        <div v-if="w.form_template" class="flex items-center gap-1.5 text-xs text-gray-800 font-medium bg-gray-50 px-2.5 py-1.5 rounded-lg border border-gray-200/80">
                                            <span class="text-teal-600">📋</span>
                                            <span class="truncate" :title="w.form_template.name">{{ w.form_template.name }}</span>
                                        </div>
                                        <div v-else class="text-xs text-gray-400 italic">
                                            Form Bağlanmamış
                                        </div>
                                    </td>

                                    <!-- Düğüm / Adım Sayısı -->
                                    <td class="px-4 py-4 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                                            {{ w.node_count || 0 }} Adım
                                        </span>
                                    </td>

                                    <!-- Şablon Durumu -->
                                    <td class="px-5 py-4 whitespace-nowrap">
                                        <span 
                                            v-if="w.status === 'active'" 
                                            class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200"
                                        >
                                            <span class="w-1.5 h-1.5 mr-1.5 bg-emerald-500 rounded-full"></span>
                                            Yayında (Aktif)
                                        </span>
                                        <span 
                                            v-else-if="w.status === 'draft'" 
                                            class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200"
                                        >
                                            <span class="w-1.5 h-1.5 mr-1.5 bg-amber-500 rounded-full"></span>
                                            Taslak
                                        </span>
                                        <span 
                                            v-else 
                                            class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-gray-100 text-gray-600"
                                        >
                                            {{ w.status }}
                                        </span>
                                    </td>

                                    <!-- Canlı Kullanım (Talepler) -->
                                    <td class="px-5 py-4 whitespace-nowrap">
                                        <div class="text-xs font-semibold text-gray-900">
                                            {{ w.total_instances_count || 0 }} Talep
                                        </div>
                                        <div v-if="w.active_instances_count > 0" class="text-[11px] font-bold text-sky-600 flex items-center gap-1 mt-0.5">
                                            <span class="w-1.5 h-1.5 bg-sky-500 rounded-full animate-pulse"></span>
                                            {{ w.active_instances_count }} canlı yürüyen
                                        </div>
                                        <div v-else class="text-[11px] text-gray-400 mt-0.5">
                                            {{ w.completed_instances_count || 0 }} tamamlandı
                                        </div>
                                    </td>

                                    <!-- İşlemler -->
                                    <td class="px-5 py-4 whitespace-nowrap text-right">
                                        <div class="inline-flex items-center justify-end gap-1.5">
                                            <!-- Yeni Talep Başlat Butonu -->
                                            <Link
                                                v-if="w.status === 'active'"
                                                :href="route('processes.create', w.id)"
                                                class="inline-flex items-center px-2.5 py-1.5 bg-teal-50 hover:bg-teal-100 text-teal-700 text-xs font-bold rounded-lg border border-teal-200 transition-colors shadow-2xs"
                                                title="Bu akış şablonuyla yeni bir form ve süreç başlat"
                                            >
                                                <svg class="w-3.5 h-3.5 mr-1 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                Talep Başlat
                                            </Link>

                                            <!-- Şemayı Gör (Önizleme) -->
                                            <button
                                                type="button"
                                                @click="openPreview(w)"
                                                class="inline-flex items-center px-2 py-1.5 bg-gray-50 hover:bg-gray-100 text-gray-700 text-xs font-semibold rounded-lg border border-gray-200 transition-colors"
                                                title="Akış şemasını ve düğüm bağlantılarını görselleştir"
                                            >
                                                <svg class="w-3.5 h-3.5 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                Şema
                                            </button>

                                            <!-- Tasarımı Düzenle -->
                                            <Link
                                                v-if="canManage"
                                                :href="route('workflows.edit', w.id)"
                                                class="inline-flex items-center px-2 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-lg border border-indigo-200 transition-colors"
                                                title="Akış tasarımcısını aç (Düğüm ekle, kural tanımla)"
                                            >
                                                <svg class="w-3.5 h-3.5 mr-1 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                Düzenle
                                            </Link>

                                            <!-- Yetkili CRUD Aksiyonları -->
                                            <template v-if="canManage">
                                                <!-- Canlı Süreç Göçü (Zero-Downtime Migration) -->
                                                <button
                                                    type="button"
                                                    @click="openMigrationModal(w)"
                                                    class="inline-flex items-center px-2 py-1.5 bg-purple-50 hover:bg-purple-100 text-purple-700 text-xs font-bold rounded-lg border border-purple-200 transition-colors shadow-2xs"
                                                    title="Canlı yürüyen süreçleri yeni versiyona aktar / düğüm eşleştir"
                                                >
                                                    <span class="mr-1">🔄</span>
                                                    Göç
                                                </button>

                                                <!-- Durum Değiştir (Aktif <-> Taslak) -->
                                                <button
                                                    type="button"
                                                    @click="toggleWorkflowStatus(w)"
                                                    :class="[
                                                        'inline-flex items-center px-2 py-1.5 text-xs font-bold rounded-lg border transition-colors',
                                                        w.status === 'active' 
                                                            ? 'bg-amber-50 hover:bg-amber-100 text-amber-800 border-amber-200' 
                                                            : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border-emerald-200'
                                                    ]"
                                                    :title="w.status === 'active' ? 'Taslağa Al (Yeni talep başlatmayı geçici olarak durdurur)' : 'Yayına Al (Kullanıcıların bu akışla talep başlatmasını sağlar)'"
                                                >
                                                    <span class="mr-1">{{ w.status === 'active' ? '⏸️' : '▶️' }}</span>
                                                    {{ w.status === 'active' ? 'Taslak' : 'Yayınla' }}
                                                </button>

                                                <!-- Klonla (Duplicate) -->
                                                <button
                                                    type="button"
                                                    @click="duplicateWorkflow(w)"
                                                    class="inline-flex items-center px-2 py-1.5 bg-sky-50 hover:bg-sky-100 text-sky-700 text-xs font-bold rounded-lg border border-sky-200 transition-colors"
                                                    title="Bu akışı kopyala (Yeni bir taslak olarak çoğaltır)"
                                                >
                                                    <svg class="w-3.5 h-3.5 mr-1 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                                    Klonla
                                                </button>

                                                <!-- Sil -->
                                                <button
                                                    type="button"
                                                    @click="openDeleteModal(w)"
                                                    class="inline-flex items-center p-1.5 bg-red-50 hover:bg-red-100 text-red-600 hover:text-red-800 text-xs font-bold rounded-lg border border-red-200 transition-colors"
                                                    title="Akış şablonunu sil"
                                                >
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- Akış Önizleme Modalı -->
        <div v-if="isPreviewOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/80 backdrop-blur-sm transition-opacity">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl h-[85vh] flex flex-col overflow-hidden border border-gray-100">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50/90">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-teal-100 text-teal-800 flex items-center justify-center font-bold text-lg">
                            📐
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-gray-900">{{ selectedWorkflow?.name }}</h3>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-teal-50 text-teal-700 border border-teal-200 font-mono">
                                    #{{ selectedWorkflow?.id }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">{{ selectedWorkflow?.node_count || selectedWorkflow?.nodes?.length || 0 }} Düğüm Bağlantısı · Görsel Mimari Önizleme</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link
                            :href="route('workflows.edit', selectedWorkflow?.id)"
                            class="px-3.5 py-1.5 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-bold transition-colors flex items-center gap-1.5 shadow-sm"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            Tasarımcıda Düzenle
                        </Link>
                        <button @click="isPreviewOpen = false" class="text-gray-400 hover:text-red-500 bg-white hover:bg-red-50 rounded-lg p-2 transition-colors border border-gray-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                </div>
                
                <!-- VueFlow Canvas -->
                <div class="flex-1 relative bg-[#0f172a]">
                    <VueFlow :nodes="previewNodes" :edges="previewEdges" :nodes-draggable="false" :nodes-connectable="false" :zoom-on-scroll="true" :pan-on-drag="true" :default-viewport="{ zoom: 0.85 }">
                        <Background pattern-color="#334155" gap="20" />
                        <Controls class="bg-gray-800 rounded-md shadow border border-gray-700 fill-gray-300" />
                        
                        <!-- Template renderers for custom nodes -->
                        <template #node-start="props">
                            <StartNode :id="props.id" :data="props.data" />
                        </template>
                        <template #node-end="props">
                            <EndNode :id="props.id" :data="props.data" />
                        </template>
                        <template #node-task="props">
                            <TaskNode :id="props.id" :data="props.data" />
                        </template>
                        <template #node-io="props">
                            <IONode :id="props.id" :data="props.data" />
                        </template>
                        <template #node-decision="props">
                            <DecisionNode :id="props.id" :data="props.data" />
                        </template>
                        <template #node-storage="props">
                            <StorageNode :id="props.id" :data="props.data" />
                        </template>
                        <template #node-document="props">
                            <DocumentNode :id="props.id" :data="props.data" />
                        </template>
                    </VueFlow>
                </div>
            </div>
        </div>

        <!-- Akış Silme Onay & Güvenlik Denetim Modalı -->
        <div v-if="isDeleteModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/80 backdrop-blur-sm transition-opacity">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-gray-100">
                <!-- Güvenlik Engeli: Canlı Talep Var -->
                <div v-if="deleteBlocked" class="p-6">
                    <div class="w-14 h-14 mx-auto mb-4 bg-red-100 text-red-600 rounded-2xl flex items-center justify-center text-2xl font-bold shadow-md shadow-red-200">
                        🛡️
                    </div>
                    <div class="text-center">
                        <h3 class="text-lg font-bold text-gray-900">Silme İşlemi Engellendi</h3>
                        <p class="text-xs font-semibold text-red-600 mt-1 uppercase tracking-wide">Yürüyen Canlı Talepler Bulunuyor</p>
                        
                        <div class="mt-4 p-4 rounded-xl bg-red-50/70 border border-red-200 text-left text-xs text-gray-700 space-y-2">
                            <p>
                                <strong>"{{ workflowToDelete?.name }}"</strong> şablonu üzerinde onay veya işlem aşamasında olan 
                                <span class="font-extrabold text-red-700 font-mono">{{ workflowToDelete?.active_instances_count }} adet canlı talep</span> mevcuttur.
                            </p>
                            <p class="text-gray-500">
                                Kurumsal süreç ve denetim (audit) güvenliği gereğince, bu talepler sonuçlandırılmadan veya iptal edilmeden akış şablonu veritabanından silinemez.
                            </p>
                        </div>

                        <div class="mt-4 p-3 rounded-xl bg-amber-50 border border-amber-200 text-left text-xs text-amber-800 flex items-center gap-2">
                            <span>💡</span>
                            <span>Yeni taleplerin gelmesini durdurmak için şablonu <strong>Taslağa Al</strong> yapabilirsiniz.</span>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="closeDeleteModal"
                            class="px-4 py-2 text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors"
                        >
                            Kapat
                        </button>
                        <button
                            v-if="workflowToDelete?.status === 'active'"
                            type="button"
                            @click="toggleWorkflowStatus(workflowToDelete); closeDeleteModal();"
                            class="px-4 py-2 text-xs font-bold text-amber-800 bg-amber-100 hover:bg-amber-200 rounded-xl transition-colors"
                        >
                            Taslağa Al ve Kapat
                        </button>
                    </div>
                </div>

                <!-- Güvenli Silme Onayı -->
                <div v-else class="p-6">
                    <div class="w-14 h-14 mx-auto mb-4 bg-red-50 text-red-600 rounded-2xl flex items-center justify-center text-2xl font-bold">
                        🗑️
                    </div>
                    <div class="text-center">
                        <h3 class="text-lg font-bold text-gray-900">Akış Şablonunu Sil</h3>
                        <p class="text-xs text-gray-500 mt-1">Bu işlem kalıcıdır ve şablonun tüm adımlarını siler.</p>

                        <div class="mt-4 p-4 rounded-xl bg-gray-50 border border-gray-200 text-left text-xs text-gray-700 space-y-1.5">
                            <div><strong class="text-gray-500">Akış Adı:</strong> <span class="font-bold text-gray-900">{{ workflowToDelete?.name }}</span></div>
                            <div><strong class="text-gray-500">Şablon ID:</strong> <span class="font-mono font-semibold text-indigo-700">#{{ workflowToDelete?.id }}</span></div>
                            <div v-if="workflowToDelete?.total_instances_count > 0" class="text-amber-700 pt-1 border-t border-gray-200">
                                ℹ️ Bu şablon üzerinden geçmişte {{ workflowToDelete.total_instances_count }} adet talep tamamlanmıştır.
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="closeDeleteModal"
                            :disabled="isDeleting"
                            class="px-4 py-2 text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors disabled:opacity-50"
                        >
                            Vazgeç
                        </button>
                        <button
                            type="button"
                            @click="confirmDeleteWorkflow"
                            :disabled="isDeleting"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-red-600 hover:bg-red-700 rounded-xl shadow-md shadow-red-600/20 transition-all disabled:opacity-50"
                        >
                            <svg v-if="isDeleting" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span>{{ isDeleting ? 'Siliniyor...' : 'Evet, Kalıcı Olarak Sil' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Canlı Süreç Versiyon Göçü Modalı (Zero-Downtime Migration) -->
        <WorkflowMigrationModal
            :show="isMigrationModalOpen"
            :workflow="workflowToMigrate"
            @close="isMigrationModalOpen = false"
            @migrated="handleWorkflowMigrated"
        />
    </AuthenticatedLayout>
</template>

<style>
.vue-flow__wrapper {
  width: 100%;
  height: 100%;
}
</style>
