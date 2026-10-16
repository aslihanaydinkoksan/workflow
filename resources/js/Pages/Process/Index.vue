<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    groupedWorkflows: {
        type: Object,
        default: () => ({})
    },
    authPermissions: {
        type: Object,
        default: () => ({
            canCreate: false,
            canEdit: false,
            canDelete: false,
            canManage: false
        })
    },
    formTemplates: {
        type: Array,
        default: () => []
    },
    allCategories: {
        type: Array,
        default: () => []
    },
    isShowingTrashed: {
        type: Boolean,
        default: false
    }
});

const searchQuery = ref('');
const selectedCategory = ref('all');
const selectedStatus = ref('all'); // 'all', 'active', 'draft'

// Yetki tespiti
const isManager = computed(() => {
    return props.authPermissions?.canCreate ||
           props.authPermissions?.canEdit ||
           props.authPermissions?.canDelete ||
           props.authPermissions?.canManage;
});

const categories = computed(() => {
    return Object.keys(props.groupedWorkflows || {});
});

const totalWorkflowsCount = computed(() => {
    let count = 0;
    for (const cat in props.groupedWorkflows) {
        count += (props.groupedWorkflows[cat] || []).length;
    }
    return count;
});

const filteredGroupedWorkflows = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    const result = {};

    for (const category in props.groupedWorkflows) {
        if (selectedCategory.value !== 'all' && selectedCategory.value !== category) {
            continue;
        }

        const workflows = (props.groupedWorkflows[category] || []).filter(wf => {
            // Durum filtresi (Aktif / Taslak)
            if (selectedStatus.value === 'active' && wf.status !== 'active') return false;
            if (selectedStatus.value === 'draft' && wf.status !== 'draft') return false;

            if (!q) return true;
            const nameMatch = (wf.name || '').toLowerCase().includes(q);
            const descMatch = (wf.description || '').toLowerCase().includes(q);
            const catMatch = (category || '').toLowerCase().includes(q);
            const docMatch = (wf.form_template?.document_no || '').toLowerCase().includes(q);
            return nameMatch || descMatch || catMatch || docMatch;
        });

        if (workflows.length > 0) {
            result[category] = workflows;
        }
    }

    return result;
});

// --- MODAL & CRUD İŞLEMLERİ ---

// 1. Yeni Süreç Oluşturma Modalı
const showCreateModal = ref(false);
const createForm = useForm({
    name: '',
    description: '',
    category: '',
    new_category: '',
    form_template_id: '',
    status: 'active',
    open_designer: true,
});

const openCreateModal = () => {
    createForm.reset();
    createForm.status = 'active';
    createForm.open_designer = true;
    showCreateModal.value = true;
};

const submitCreateWorkflow = () => {
    const finalCategory = createForm.new_category ? createForm.new_category.trim() : createForm.category;
    createForm.transform(data => ({
        ...data,
        category: finalCategory
    })).post(route('processes.workflows.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        }
    });
};

// 2. Süreç Düzenleme Modalı
const showEditModal = ref(false);
const editingWorkflow = ref(null);
const editForm = useForm({
    name: '',
    description: '',
    category: '',
    new_category: '',
    form_template_id: '',
    status: 'active',
});

const openEditModal = (wf) => {
    editingWorkflow.value = wf;
    const cat = Array.isArray(wf.category) ? (wf.category[0] || '') : (wf.category || '');
    editForm.name = wf.name || '';
    editForm.description = wf.description || '';
    editForm.category = cat;
    editForm.new_category = '';
    editForm.form_template_id = wf.form_template_id || '';
    editForm.status = wf.status || 'active';
    showEditModal.value = true;
};

const submitEditWorkflow = () => {
    if (!editingWorkflow.value) return;
    const finalCategory = editForm.new_category ? editForm.new_category.trim() : editForm.category;
    editForm.transform(data => ({
        ...data,
        category: finalCategory
    })).patch(route('processes.workflows.update', editingWorkflow.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editingWorkflow.value = null;
        }
    });
};

// 3. Durum Değiştirme (Aktif / Pasif Toggle)
const isTogglingStatus = ref({});
const toggleWorkflowStatus = (wf) => {
    isTogglingStatus.value[wf.id] = true;
    router.post(route('processes.workflows.toggle-status', wf.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            delete isTogglingStatus.value[wf.id];
        }
    });
};

// 4. Soft Delete Modalı
const showDeleteModal = ref(false);
const deletingWorkflow = ref(null);
const deleteForm = useForm({});

const openDeleteModal = (wf) => {
    deletingWorkflow.value = wf;
    showDeleteModal.value = true;
};

const confirmSoftDelete = () => {
    if (!deletingWorkflow.value) return;
    deleteForm.delete(route('processes.workflows.destroy', deletingWorkflow.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            deletingWorkflow.value = null;
        }
    });
};

// 5. Geri Yükle (Restore)
const restoreWorkflow = (wf) => {
    router.post(route('processes.workflows.restore', wf.id), {}, {
        preserveScroll: true,
    });
};

// 6. Çöp Kutusu / Arşiv Görünümü Aç/Kapa
const toggleTrashView = () => {
    router.get(route('processes.index'), {
        show_trashed: props.isShowingTrashed ? 0 : 1
    }, {
        preserveState: true,
        preserveScroll: true
    });
};
</script>

<template>
    <Head title="Süreç Kataloğu - Yeni Talep Başlat" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="font-extrabold text-2xl text-gray-900 leading-tight flex items-center gap-2">
                        <span>🚀 Süreç Kataloğu</span>
                        <span v-if="isShowingTrashed" class="text-xs px-2.5 py-1 bg-rose-100 text-rose-800 rounded-lg font-bold border border-rose-200">
                            🗑️ Çöp Kutusu / Arşiv
                        </span>
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ isShowingTrashed 
                            ? 'Soft-delete ile güvenle silinmiş iş akışı modellerini görüntülemektesiniz. İstenildiğinde tek tıkla geri yüklenebilir.' 
                            : 'Departmanınıza ve rolünüze açık tüm kurumsal formları tek tıkla arayın ve başlatın.' 
                        }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <!-- Toplam Süreç Rozeti -->
                    <div class="flex items-center gap-2 text-xs font-semibold text-gray-600 bg-white px-3 py-2 rounded-xl border border-gray-200 shadow-sm">
                        <span>Toplam Tanımlı Süreç:</span>
                        <span class="text-indigo-600 font-extrabold text-sm">{{ totalWorkflowsCount }}</span>
                    </div>

                    <!-- Çöp Kutusu / Arşiv Butonu (Yetkili Silme İzni Olanlara) -->
                    <button
                        v-if="authPermissions?.canDelete"
                        @click="toggleTrashView"
                        type="button"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold transition border shadow-sm"
                        :class="isShowingTrashed 
                            ? 'bg-rose-50 border-rose-300 text-rose-700 hover:bg-rose-100' 
                            : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'"
                    >
                        <span>{{ isShowingTrashed ? '🔙 Aktif Kataloğa Dön' : '🗑️ Arşiv & Çöp Kutusu' }}</span>
                    </button>

                    <!-- Yeni Süreç Oluştur Butonu (Yetkili İzni Olanlara) -->
                    <button
                        v-if="authPermissions?.canCreate && !isShowingTrashed"
                        @click="openCreateModal"
                        type="button"
                        class="inline-flex items-center gap-2 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white font-bold py-2 px-4 rounded-xl text-xs shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5"
                    >
                        <span class="text-base leading-none">+</span>
                        <span>Yeni Süreç / Akış Oluştur</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- FLASH BİLDİRİMLERİ -->
                <div v-if="$page.props.flash?.success" class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold flex items-center justify-between shadow-sm animate-fade-in">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">✓</span>
                        <span>{{ $page.props.flash.success }}</span>
                    </div>
                </div>

                <div v-if="$page.props.flash?.error" class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-semibold flex items-center justify-between shadow-sm animate-fade-in">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">⚠️</span>
                        <span>{{ $page.props.flash.error }}</span>
                    </div>
                </div>

                <!-- ÇÖP KUTUSU MODU BANNER -->
                <div v-if="isShowingTrashed" class="p-4 rounded-2xl bg-gradient-to-r from-rose-50 to-amber-50 border border-rose-200 text-rose-900 text-xs shadow-sm flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">🛡️</span>
                        <div>
                            <div class="font-extrabold text-sm text-rose-900">Soft-Delete Arşiv Görünümü Aktif</div>
                            <div class="text-gray-600 mt-0.5">Aşağıda listelenen akışlar standart personele kapalıdır. Veritabanında güvenle saklanmaktadır. Dilediğiniz süreci "Geri Yükle" butonuyla anında tekrar yayına alabilirsiniz.</div>
                        </div>
                    </div>
                    <button
                        @click="toggleTrashView"
                        type="button"
                        class="px-3 py-1.5 bg-white border border-rose-300 text-rose-700 hover:bg-rose-100 rounded-lg font-bold text-xs shadow-sm transition"
                    >
                        Kataloğa Dön
                    </button>
                </div>

                <!-- CANLI ARAMA VE KATEGORİ / DURUM FİLTRELEME ÇUBUĞU -->
                <div class="bg-white rounded-2xl border border-gray-200/80 p-5 shadow-sm space-y-4">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 text-lg">
                            🔍
                        </div>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Süreç veya form ara... (Örn: numune, levha, dış görev, harcırah, bakım, arıza, satınalma, FR-...)"
                            class="w-full pl-11 pr-4 py-3 rounded-xl border-gray-200 bg-gray-50/50 focus:bg-white focus:border-indigo-500 focus:ring-indigo-500 text-sm font-medium transition shadow-inner"
                        />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            type="button"
                            class="absolute inset-y-0 right-0 pr-4 flex items-center text-xs text-gray-400 hover:text-gray-600"
                        >
                            ✕ Temizle
                        </button>
                    </div>

                    <!-- Kategori ve Durum Filtreleme Satırı -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-3 border-t border-gray-100">
                        <!-- Kategori Butonları -->
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-bold text-gray-500 uppercase mr-1">Kategori:</span>
                            <button
                                type="button"
                                @click="selectedCategory = 'all'"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
                                :class="selectedCategory === 'all' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-gray-100 hover:bg-gray-200 text-gray-700'"
                            >
                                Tümü ({{ totalWorkflowsCount }})
                            </button>
                            <button
                                v-for="cat in categories"
                                :key="cat"
                                type="button"
                                @click="selectedCategory = cat"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
                                :class="selectedCategory === cat ? 'bg-indigo-600 text-white shadow-sm' : 'bg-gray-100 hover:bg-gray-200 text-gray-700'"
                            >
                                {{ cat }} ({{ (groupedWorkflows[cat] || []).length }})
                            </button>
                        </div>

                        <!-- Yönetici Durum Filtresi (Aktif / Taslak) -->
                        <div v-if="isManager && !isShowingTrashed" class="flex items-center gap-1.5 self-end sm:self-auto bg-gray-50 p-1 rounded-xl border border-gray-200">
                            <span class="text-[11px] font-bold text-gray-400 px-2 uppercase">Durum:</span>
                            <button
                                type="button"
                                @click="selectedStatus = 'all'"
                                class="px-2.5 py-1 rounded-lg text-xs font-bold transition"
                                :class="selectedStatus === 'all' ? 'bg-white text-gray-900 shadow-sm border border-gray-200' : 'text-gray-500 hover:text-gray-800'"
                            >
                                Tümü
                            </button>
                            <button
                                type="button"
                                @click="selectedStatus = 'active'"
                                class="px-2.5 py-1 rounded-lg text-xs font-bold transition flex items-center gap-1"
                                :class="selectedStatus === 'active' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-800'"
                            >
                                <span>✓</span> Aktif
                            </button>
                            <button
                                type="button"
                                @click="selectedStatus = 'draft'"
                                class="px-2.5 py-1 rounded-lg text-xs font-bold transition flex items-center gap-1"
                                :class="selectedStatus === 'draft' ? 'bg-amber-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-800'"
                            >
                                <span>⏸️</span> Taslak
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SONUÇ BULUNAMADI UYARISI -->
                <div v-if="Object.keys(filteredGroupedWorkflows).length === 0" class="p-12 text-center bg-white rounded-2xl border border-gray-200 shadow-sm">
                    <div class="text-4xl mb-3">🔍</div>
                    <h3 class="font-bold text-lg text-gray-800">Aramanıza Uygun Süreç Bulunamadı</h3>
                    <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">
                        "{{ searchQuery }}" terimi veya seçili filtrelere uygun bir form bulunamadı. Lütfen filtreleri sıfırlayınız.
                    </p>
                    <button
                        @click="searchQuery = ''; selectedCategory = 'all'; selectedStatus = 'all'"
                        class="mt-4 px-4 py-2 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-bold hover:bg-indigo-100 transition"
                    >
                        Tüm Süreçleri Göster
                    </button>
                </div>

                <!-- KATEGORİ VE FORMLAR LİSTESİ -->
                <div v-for="(workflows, category) in filteredGroupedWorkflows" :key="category" class="space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-200 pb-2">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                            {{ category }}
                        </h3>
                        <span class="text-xs font-semibold text-gray-400">{{ workflows.length }} Süreç</span>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div 
                            v-for="workflow in workflows" 
                            :key="workflow.id" 
                            class="bg-white rounded-2xl border transition-all duration-200 shadow-sm hover:shadow-md flex flex-col justify-between overflow-hidden group"
                            :class="[
                                workflow.deleted_at ? 'border-rose-300 bg-rose-50/20' : 
                                (workflow.status === 'draft' ? 'border-amber-200 bg-amber-50/10' : 'border-gray-200 hover:border-indigo-400')
                            ]"
                        >
                            <div class="p-6">
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="flex items-center gap-3">
                                        <div 
                                            class="h-11 w-11 rounded-xl flex items-center justify-center font-black text-lg group-hover:scale-105 transition-transform shrink-0 border"
                                            :class="[
                                                workflow.deleted_at ? 'bg-rose-100 text-rose-700 border-rose-200' :
                                                (workflow.status === 'draft' ? 'bg-amber-100 text-amber-800 border-amber-200' : 'bg-gradient-to-br from-indigo-50 to-indigo-100 border-indigo-200 text-indigo-700')
                                            ]"
                                        >
                                            {{ workflow.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <h4 class="text-base font-bold text-gray-900 leading-snug group-hover:text-indigo-600 transition-colors">
                                                {{ workflow.name }}
                                            </h4>
                                            <div class="flex items-center gap-2 mt-0.5">
                                                <span class="text-[11px] font-semibold text-gray-400">
                                                    {{ workflow.form_template?.document_no || 'Dinamik Form' }}
                                                </span>
                                                <span v-if="workflow.version" class="text-[10px] font-bold text-gray-500 bg-gray-100 px-1.5 py-0.2 rounded border border-gray-200">
                                                    v{{ workflow.version }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- DURUM ROZETİ (Yöneticiler için Tıklanabilir Toggle) -->
                                    <div class="shrink-0">
                                        <template v-if="workflow.deleted_at">
                                            <span class="text-[10px] font-extrabold text-rose-700 bg-rose-100 px-2 py-1 rounded-md border border-rose-200">
                                                🗑️ Silinmiş
                                            </span>
                                        </template>
                                        <template v-else-if="authPermissions?.canEdit">
                                            <button
                                                type="button"
                                                @click="toggleWorkflowStatus(workflow)"
                                                :disabled="isTogglingStatus[workflow.id]"
                                                class="text-[10px] font-extrabold px-2 py-1 rounded-md border transition flex items-center gap-1 shadow-2xs hover:scale-105 cursor-pointer"
                                                :class="workflow.status === 'active' 
                                                    ? 'text-emerald-700 bg-emerald-50 border-emerald-300 hover:bg-emerald-100' 
                                                    : 'text-amber-800 bg-amber-50 border-amber-300 hover:bg-amber-100'"
                                                :title="workflow.status === 'active' ? 'Pasife almak için tıklayın' : 'Yayına almak için tıklayın'"
                                            >
                                                <span v-if="workflow.status === 'active'">✓ Aktif</span>
                                                <span v-else>⏸️ Taslak</span>
                                                <span v-if="isTogglingStatus[workflow.id]" class="animate-spin text-xs">⟳</span>
                                            </button>
                                        </template>
                                        <template v-else>
                                            <span 
                                                class="text-[10px] font-extrabold px-2 py-1 rounded-md border"
                                                :class="workflow.status === 'active' ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : 'text-gray-600 bg-gray-100 border-gray-200'"
                                            >
                                                {{ workflow.status === 'active' ? '✓ Aktif' : '⏸️ Pasif' }}
                                            </span>
                                        </template>
                                    </div>
                                </div>

                                <p class="text-xs text-gray-600 line-clamp-2 leading-relaxed">
                                    {{ workflow.description || 'Bu süreç için açıklama tanımlanmamıştır.' }}
                                </p>

                                <!-- Yürüyen Talep Göstergesi (Yöneticiler için) -->
                                <div v-if="isManager && (workflow.active_instances_count > 0)" class="mt-3 flex items-center gap-1.5 text-[11px] font-bold text-amber-700 bg-amber-50 px-2.5 py-1 rounded-lg border border-amber-200">
                                    <span>⚡</span>
                                    <span>{{ workflow.active_instances_count }} canlı talep yürütülüyor</span>
                                </div>
                            </div>
                            
                            <!-- YÖNETİCİ HIZLI CRUD EYLEM ÇUBUĞU -->
                            <div 
                                v-if="isManager" 
                                class="px-4 py-2 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-xs"
                            >
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Yönetim</span>
                                
                                <div class="flex items-center gap-1.5">
                                    <!-- Çöp Kutusunda Geri Yükle Butonu -->
                                    <template v-if="workflow.deleted_at">
                                        <button
                                            v-if="authPermissions?.canDelete"
                                            @click="restoreWorkflow(workflow)"
                                            type="button"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-[11px] transition shadow-2xs cursor-pointer"
                                        >
                                            <span>♻️</span>
                                            <span>Geri Yükle</span>
                                        </button>
                                    </template>
                                    
                                    <!-- Normal Kataloğ Eylemleri -->
                                    <template v-else>
                                        <!-- Hızlı Düzenle -->
                                        <button
                                            v-if="authPermissions?.canEdit"
                                            @click="openEditModal(workflow)"
                                            type="button"
                                            class="inline-flex items-center gap-1 px-2 py-1 bg-white hover:bg-indigo-50 text-indigo-700 rounded-lg font-bold text-[11px] border border-gray-200 hover:border-indigo-300 transition shadow-2xs cursor-pointer"
                                            title="Süreç bilgilerini düzenle"
                                        >
                                            <span>✏️</span>
                                            <span>Düzenle</span>
                                        </button>

                                        <!-- BPMN Akış Tasarımcısı -->
                                        <Link
                                            v-if="authPermissions?.canEdit || authPermissions?.canManage"
                                            :href="route('workflows.edit', workflow.id)"
                                            class="inline-flex items-center gap-1 px-2 py-1 bg-white hover:bg-purple-50 text-purple-700 rounded-lg font-bold text-[11px] border border-gray-200 hover:border-purple-300 transition shadow-2xs cursor-pointer"
                                            title="BPMN Akış Tasarımcısını Aç"
                                        >
                                            <span>🎨</span>
                                            <span>Tasarımcı</span>
                                        </Link>

                                        <!-- Soft Delete Silme Butonu -->
                                        <button
                                            v-if="authPermissions?.canDelete"
                                            @click="openDeleteModal(workflow)"
                                            type="button"
                                            class="inline-flex items-center gap-1 px-2 py-1 bg-white hover:bg-rose-50 text-rose-600 rounded-lg font-bold text-[11px] border border-gray-200 hover:border-rose-300 transition shadow-2xs cursor-pointer"
                                            title="Test/Eski Süreci Güvenle Sil (Soft Delete)"
                                        >
                                            <span>🗑️</span>
                                            <span>Sil</span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <!-- ALT KISIM: TALEBİ BAŞLAT BUTONU -->
                            <div class="p-4 bg-gray-50/80 border-t border-gray-100 flex justify-between items-center">
                                <div class="text-[11px] text-gray-500 font-medium">
                                    <span v-if="workflow.valid_until">
                                        Son: {{ workflow.valid_until }}
                                    </span>
                                </div>

                                <template v-if="workflow.status === 'active' && !workflow.deleted_at">
                                    <Link 
                                        :href="route('processes.create', workflow.id)" 
                                        class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-xl text-xs shadow-sm hover:shadow transition transform hover:-translate-y-0.5"
                                    >
                                        <span>Talebi Başlat</span>
                                        <span>&rarr;</span>
                                    </Link>
                                </template>
                                <template v-else>
                                    <span class="inline-flex items-center gap-1 text-gray-400 font-bold py-1.5 px-3 rounded-lg text-xs bg-gray-100 border border-gray-200">
                                        <span>⏸️</span>
                                        <span>Pasif (Başlatılamaz)</span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL 1: YENİ SÜREÇ / AKIŞ OLUŞTURMA MODALI -->
        <!-- ========================================================================= -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-lg w-full border border-gray-200 shadow-2xl overflow-hidden animate-scale-in">
                <div class="bg-gradient-to-r from-indigo-700 via-indigo-600 to-indigo-800 p-6 text-white flex justify-between items-center">
                    <div>
                        <h3 class="font-extrabold text-lg flex items-center gap-2">
                            <span>🚀 Yeni İş Akışı & Süreç Oluştur</span>
                        </h3>
                        <p class="text-xs text-indigo-100 mt-1">Kataloğa eklenecek yeni iş akışının temel bilgilerini tanımlayın.</p>
                    </div>
                    <button @click="showCreateModal = false" class="text-white/80 hover:text-white text-xl font-bold cursor-pointer">✕</button>
                </div>

                <form @submit.prevent="submitCreateWorkflow" class="p-6 space-y-4 text-xs font-medium">
                    <!-- Süreç Adı -->
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Süreç Adı <span class="text-rose-500">*</span></label>
                        <input
                            v-model="createForm.name"
                            type="text"
                            required
                            placeholder="Örn: Levha Numune Talep ve Kalite Süreci"
                            class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-xs py-2.5"
                        />
                        <div v-if="createForm.errors.name" class="text-rose-500 text-[11px] mt-1">{{ createForm.errors.name }}</div>
                    </div>

                    <!-- Kategori Seçimi veya Yeni Kategori -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Mevcut Kategori</label>
                            <select
                                v-model="createForm.category"
                                class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-xs py-2.5"
                            >
                                <option value="">-- Kategori Seçin --</option>
                                <option v-for="cat in allCategories" :key="cat" :value="cat">{{ cat }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Veya Yeni Kategori Yazın</label>
                            <input
                                v-model="createForm.new_category"
                                type="text"
                                placeholder="Örn: Kalite Güvence"
                                class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-xs py-2.5"
                            />
                        </div>
                    </div>

                    <!-- Bağlı Form Şablonu -->
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Bağlı Form Şablonu (Opsiyonel)</label>
                        <select
                            v-model="createForm.form_template_id"
                            class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-xs py-2.5"
                        >
                            <option value="">-- Form Şablonu Yok / Sonra Bağlanacak --</option>
                            <option v-for="ft in formTemplates" :key="ft.id" :value="ft.id">
                                {{ ft.name }} ({{ ft.document_no || 'Form #' + ft.id }})
                            </option>
                        </select>
                        <p class="text-[11px] text-gray-400 mt-1">Süreç başlatıldığında doldurulacak dinamik form şablonudur.</p>
                    </div>

                    <!-- Açıklama -->
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Açıklama</label>
                        <textarea
                            v-model="createForm.description"
                            rows="2"
                            placeholder="Sürecin amacı ve kapsamı..."
                            class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-xs py-2"
                        ></textarea>
                    </div>

                    <!-- Başlangıç Durumu -->
                    <div class="grid grid-cols-2 gap-3 pt-1">
                        <label 
                            class="border rounded-xl p-3 flex items-center gap-2 cursor-pointer transition"
                            :class="createForm.status === 'active' ? 'bg-emerald-50 border-emerald-400 text-emerald-900' : 'bg-gray-50 border-gray-200 text-gray-600'"
                        >
                            <input type="radio" v-model="createForm.status" value="active" class="text-emerald-600 focus:ring-emerald-500" />
                            <span class="font-bold">✓ Hemen Yayına Al</span>
                        </label>
                        <label 
                            class="border rounded-xl p-3 flex items-center gap-2 cursor-pointer transition"
                            :class="createForm.status === 'draft' ? 'bg-amber-50 border-amber-400 text-amber-900' : 'bg-gray-50 border-gray-200 text-gray-600'"
                        >
                            <input type="radio" v-model="createForm.status" value="draft" class="text-amber-600 focus:ring-amber-500" />
                            <span class="font-bold">⏸️ Taslak Olarak Kaydet</span>
                        </label>
                    </div>

                    <!-- Checkbox: Tasarımcıyı Aç -->
                    <div class="pt-2 flex items-center gap-2">
                        <input
                            id="open_designer_cb"
                            type="checkbox"
                            v-model="createForm.open_designer"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <label for="open_designer_cb" class="text-xs text-gray-700 font-semibold cursor-pointer">
                            Oluşturulduktan sonra görsel BPMN Akış Tasarımcısını otomatik aç
                        </label>
                    </div>

                    <!-- Butonlar -->
                    <div class="pt-4 border-t border-gray-100 flex justify-end gap-2">
                        <button
                            type="button"
                            @click="showCreateModal = false"
                            class="px-4 py-2.5 rounded-xl border border-gray-200 text-gray-700 hover:bg-gray-50 font-bold transition cursor-pointer"
                        >
                            İptal
                        </button>
                        <button
                            type="submit"
                            :disabled="createForm.processing"
                            class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold transition shadow-sm cursor-pointer"
                        >
                            {{ createForm.processing ? 'Oluşturuluyor...' : '🚀 Süreci Oluştur' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL 2: SÜREÇ DÜZENLEME MODALI -->
        <!-- ========================================================================= -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-lg w-full border border-gray-200 shadow-2xl overflow-hidden animate-scale-in">
                <div class="bg-gradient-to-r from-slate-800 to-indigo-900 p-6 text-white flex justify-between items-center">
                    <div>
                        <h3 class="font-extrabold text-lg flex items-center gap-2">
                            <span>✏️ İş Akışını Düzenle</span>
                        </h3>
                        <p class="text-xs text-indigo-200 mt-1">{{ editingWorkflow?.name }}</p>
                    </div>
                    <button @click="showEditModal = false" class="text-white/80 hover:text-white text-xl font-bold cursor-pointer">✕</button>
                </div>

                <form @submit.prevent="submitEditWorkflow" class="p-6 space-y-4 text-xs font-medium">
                    <!-- Süreç Adı -->
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Süreç Adı <span class="text-rose-500">*</span></label>
                        <input
                            v-model="editForm.name"
                            type="text"
                            required
                            class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-xs py-2.5"
                        />
                        <div v-if="editForm.errors.name" class="text-rose-500 text-[11px] mt-1">{{ editForm.errors.name }}</div>
                    </div>

                    <!-- Kategori -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Mevcut Kategori</label>
                            <select
                                v-model="editForm.category"
                                class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-xs py-2.5"
                            >
                                <option value="">-- Kategori Yok / Diğer --</option>
                                <option v-for="cat in allCategories" :key="cat" :value="cat">{{ cat }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Veya Yeni Kategori</label>
                            <input
                                v-model="editForm.new_category"
                                type="text"
                                placeholder="Yeni kategori ismi..."
                                class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-xs py-2.5"
                            />
                        </div>
                    </div>

                    <!-- Bağlı Form Şablonu -->
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Bağlı Form Şablonu</label>
                        <select
                            v-model="editForm.form_template_id"
                            class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-xs py-2.5"
                        >
                            <option value="">-- Form Şablonu Yok --</option>
                            <option v-for="ft in formTemplates" :key="ft.id" :value="ft.id">
                                {{ ft.name }} ({{ ft.document_no || 'Form #' + ft.id }})
                            </option>
                        </select>
                    </div>

                    <!-- Açıklama -->
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Açıklama</label>
                        <textarea
                            v-model="editForm.description"
                            rows="2"
                            class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-xs py-2"
                        ></textarea>
                    </div>

                    <!-- Durum (Aktif / Taslak) -->
                    <div class="grid grid-cols-2 gap-3 pt-1">
                        <label 
                            class="border rounded-xl p-3 flex items-center gap-2 cursor-pointer transition"
                            :class="editForm.status === 'active' ? 'bg-emerald-50 border-emerald-400 text-emerald-900' : 'bg-gray-50 border-gray-200 text-gray-600'"
                        >
                            <input type="radio" v-model="editForm.status" value="active" class="text-emerald-600 focus:ring-emerald-500" />
                            <span class="font-bold">✓ Aktif (Yayında)</span>
                        </label>
                        <label 
                            class="border rounded-xl p-3 flex items-center gap-2 cursor-pointer transition"
                            :class="editForm.status === 'draft' ? 'bg-amber-50 border-amber-400 text-amber-900' : 'bg-gray-50 border-gray-200 text-gray-600'"
                        >
                            <input type="radio" v-model="editForm.status" value="draft" class="text-amber-600 focus:ring-amber-500" />
                            <span class="font-bold">⏸️ Taslak (Pasif)</span>
                        </label>
                    </div>

                    <!-- Butonlar -->
                    <div class="pt-4 border-t border-gray-100 flex justify-end gap-2">
                        <button
                            type="button"
                            @click="showEditModal = false"
                            class="px-4 py-2.5 rounded-xl border border-gray-200 text-gray-700 hover:bg-gray-50 font-bold transition cursor-pointer"
                        >
                            İptal
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold transition shadow-sm cursor-pointer"
                        >
                            {{ editForm.processing ? 'Kaydediliyor...' : 'Değişiklikleri Kaydet' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL 3: SOFT DELETE ONAY MODALI -->
        <!-- ========================================================================= -->
        <div v-if="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-md w-full border border-gray-200 shadow-2xl overflow-hidden animate-scale-in">
                <div class="bg-rose-600 p-6 text-white flex justify-between items-center">
                    <div>
                        <h3 class="font-extrabold text-lg flex items-center gap-2">
                            <span>🗑️ İş Akışını Güvenle Sil</span>
                        </h3>
                        <p class="text-xs text-rose-100 mt-1">Soft Delete Güvenlik Koruması</p>
                    </div>
                    <button @click="showDeleteModal = false" class="text-white/80 hover:text-white text-xl font-bold cursor-pointer">✕</button>
                </div>

                <div class="p-6 space-y-4 text-xs">
                    <div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-rose-900 space-y-2">
                        <div class="font-bold text-sm">
                            "{{ deletingWorkflow?.name }}"
                        </div>
                        <p class="text-xs leading-relaxed text-rose-800">
                            Bu iş akışı şablonu süreç kataloğundan ve standart kullanıcılardan tamamen gizlenecektir.
                        </p>
                        <p class="text-[11px] text-gray-600 border-t border-rose-200/60 pt-2">
                            <strong>🛡️ Soft Delete Güvencesi:</strong> Veritabanından fiziksel olarak silinmez. Tamamlanmış geçmiş süreçler, onay logları ve form verileri %100 korunur. İstenildiğinde yönetici tarafından çöp kutusundan tek tıkla geri yüklenebilir.
                        </p>
                    </div>

                    <!-- Canlı Talep Uyarısı (Varsa) -->
                    <div v-if="deletingWorkflow?.active_instances_count > 0" class="p-3 bg-amber-50 border border-amber-300 rounded-xl text-amber-900 font-semibold">
                        ⚠️ Dikkat: Bu akışa ait yürüyen <strong>{{ deletingWorkflow.active_instances_count }} canlı talep</strong> bulunmaktadır. Canlı talepler tamamlanmadan veya iptal edilmeden silme işlemi gerçekleştirilemez. Dilerseniz şablonu 'Taslak' durumuna alabilirsiniz.
                    </div>

                    <!-- Butonlar -->
                    <div class="pt-4 border-t border-gray-100 flex justify-end gap-2">
                        <button
                            type="button"
                            @click="showDeleteModal = false"
                            class="px-4 py-2.5 rounded-xl border border-gray-200 text-gray-700 hover:bg-gray-50 font-bold transition cursor-pointer"
                        >
                            Vazgeç
                        </button>
                        
                        <button
                            v-if="deletingWorkflow?.active_instances_count > 0"
                            type="button"
                            @click="toggleWorkflowStatus(deletingWorkflow); showDeleteModal = false;"
                            class="px-4 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold transition shadow-sm cursor-pointer"
                        >
                            ⏸️ Taslağa Al
                        </button>

                        <button
                            v-else
                            type="button"
                            @click="confirmSoftDelete"
                            :disabled="deleteForm.processing"
                            class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold transition shadow-sm cursor-pointer"
                        >
                            {{ deleteForm.processing ? 'Siliniyor...' : '🗑️ Güvenle Sil (Soft Delete)' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
