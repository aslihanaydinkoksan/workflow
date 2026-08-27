<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HierarchyNode from '@/Components/HierarchyNode.vue';
import axios from 'axios';

const props = defineProps({
    nodes: Array,
    subtypes: Array,
    treeType: Object,
    treeTypes: Array,
    users: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    directorates: { type: Array, default: () => [] }
});

// --- Ağaç Tipi Değiştirme (Üst Filtre) ---
const changeTreeType = (e) => {
    router.get(route('admin.hierarchy.test'), { type_id: e.target.value }, { preserveState: true });
};

// --- Sürükle Bırak (Drag & Drop) ---
const draggedNodeId = ref(null);
const handleDragStart = (id) => { draggedNodeId.value = id; };
const handleDragOver = (e) => { e.dataTransfer.dropEffect = 'move'; };
const handleDrop = async (e, newParentId) => {
    if (!draggedNodeId.value || draggedNodeId.value === newParentId) return;
    if (!confirm("Düğümü buraya taşımak istiyor musunuz?")) return;

    try {
        await axios.patch(route('admin.hierarchy.nodes.move', draggedNodeId.value), { new_parent_id: newParentId });
        router.reload({ only: ['nodes'] });
    } catch (err) {
        alert("Taşıma başarısız oldu.");
    } finally {
        draggedNodeId.value = null;
    }
};

// --- Modal ve Form Yönetimi ---
const isModalOpen = ref(false);
const isProcessing = ref(false);
const modalAction = ref('create');
const activeNodeId = ref(null);

const form = ref({
    tree_type_id: '',
    parent_id: null,
    label: '',
    node_subtype: '',
    metadata: {},
    user_id: null
});

// Seçilen TreeType'a ait JSON şemasını otomatik yakalayan Computed yapısı
const selectedSchema = computed(() => {
    if (!form.value.tree_type_id) return [];
    const targetType = props.treeTypes.find(t => t.id === form.value.tree_type_id);
    return targetType?.schema || [];
});

// GÖREV 2: Hangi tiplerin seçili olduğunu bul (Dinamik UI için - GÜVENLİ KONTROL)
const isPersonel = computed(() => {
    const t = props.treeTypes.find(x => x.id === form.value.tree_type_id);
    return t && (t.display_name === 'Personel' || t.name === 'Personel');
});

const isDepartment = computed(() => {
    const t = props.treeTypes.find(x => x.id === form.value.tree_type_id);
    // Soru işaretleri (?.), eğer name veya display_name null ise çökmesini engeller
    return t && (t.display_name?.includes('Departman') || t.name?.includes('Departman'));
});

const isDirectorate = computed(() => {
    const t = props.treeTypes.find(x => x.id === form.value.tree_type_id);
    return t && (t.display_name === 'Direktörlük' || t.name === 'Direktörlük');
});

// Dropdown seçimi yapıldığında bu değişkende tutulacak ve Watcher ile dağıtılacak
const selectedEntityId = ref('');

// ID seçildiğinde Label ve Metadatayı otomatik güncelleme
watch(selectedEntityId, (newId) => {
    if (!newId) return;
    
    if (isPersonel.value) {
        form.value.user_id = newId;
        const user = props.users.find(u => u.id === newId);
        if (user) form.value.label = user.name;
        
    } else if (isDepartment.value) {
        if (!form.value.metadata) form.value.metadata = {};
        form.value.metadata.department_id = newId;
        const dept = props.departments.find(d => d.id === newId);
        if (dept) form.value.label = dept.name;
        
    } else if (isDirectorate.value) {
        if (!form.value.metadata) form.value.metadata = {};
        form.value.metadata.directorate_id = newId;
        const dir = props.directorates.find(d => d.id === newId);
        if (dir) form.value.label = dir.name;
    }
});

// Form Tipi Değiştiğinde Alanları Resetleme
watch(() => form.value.tree_type_id, (newTypeId, oldTypeId) => {
    // TreeType değiştiğinde mevcut Entity referansını temizle
    if (oldTypeId !== undefined) {
        selectedEntityId.value = '';
        form.value.user_id = null;
        form.value.label = '';
    }

    if (modalAction.value === 'create') {
        const newMeta = {};
        selectedSchema.value.forEach(field => {
            if (field.type === 'multiselect') newMeta[field.field] = [];
            else if (field.type === 'boolean') newMeta[field.field] = false;
            else newMeta[field.field] = '';
        });
        
        // Departman/Direktörlük seçilmişse metadatanın içinde kalması için
        if (isDepartment.value && selectedEntityId.value) newMeta.department_id = selectedEntityId.value;
        if (isDirectorate.value && selectedEntityId.value) newMeta.directorate_id = selectedEntityId.value;

        form.value.metadata = newMeta;
    }
}, { immediate: true });

const openModal = (action, parentId = null, nodeToEdit = null) => {
    modalAction.value = action;
    
    if (action === 'create') {
        form.value.tree_type_id = props.treeType?.id || (props.treeTypes.length > 0 ? props.treeTypes[0].id : '');
        form.value.parent_id = parentId;
        form.value.label = '';
        form.value.node_subtype = '';
        form.value.user_id = null;
        selectedEntityId.value = '';
    } else {
        activeNodeId.value = nodeToEdit.id;
        form.value.tree_type_id = nodeToEdit.tree_type_id;
        form.value.parent_id = nodeToEdit.parent_id;
        form.value.label = nodeToEdit.label;
        form.value.node_subtype = nodeToEdit.node_subtype || '';
        form.value.user_id = nodeToEdit.user_id || null;
        form.value.metadata = nodeToEdit.metadata ? JSON.parse(JSON.stringify(nodeToEdit.metadata)) : {};

        // Edit modunda ilgili dropdown ID'sini doldur
        if (isPersonel.value) selectedEntityId.value = nodeToEdit.user_id || '';
        else if (isDepartment.value) selectedEntityId.value = nodeToEdit.metadata?.department_id || '';
        else if (isDirectorate.value) selectedEntityId.value = nodeToEdit.metadata?.directorate_id || '';
        else selectedEntityId.value = '';
    }
    isModalOpen.value = true;
};

const submitModal = async () => {
    isProcessing.value = true;
    try {
        const url = modalAction.value === 'create' 
            ? route('admin.hierarchy.nodes.store') 
            : route('admin.hierarchy.nodes.update', activeNodeId.value);
        const method = modalAction.value === 'create' ? 'post' : 'put';
        
        await axios[method](url, form.value);
        
        isModalOpen.value = false;
        router.reload({ only: ['nodes', 'subtypes'] });
    } catch (error) {
        alert(error.response?.data?.message || 'Eksik veya hatalı bilgi girdiniz. Lütfen alanları kontrol edin.');
    } finally {
        isProcessing.value = false;
    }
};

const deleteNode = async (id) => {
    if (!confirm("Bu düğümü ve tüm alt düğümlerini silmek istediğinize emin misiniz?")) return;
    try {
        await axios.delete(route('admin.hierarchy.nodes.destroy', id));
        router.reload({ only: ['nodes'] });
    } catch (err) {
        alert("Silme başarısız.");
    }
};

const expandedRootDetails = ref(new Set());
const toggleRootDetails = (id) => {
    const newSet = new Set(expandedRootDetails.value);
    if (newSet.has(id)) newSet.delete(id);
    else newSet.add(id);
    expandedRootDetails.value = newSet;
};
const formatValue = (value) => {
    if (Array.isArray(value)) return value.join(', ');
    if (typeof value === 'boolean') return value ? 'Evet' : 'Hayır';
    return value || '-';
};
// IDE (TypeScript) hatalarını önlemek için metin formatlama fonksiyonu
const formatLabel = (str) => {
    if (!str) return '';
    return String(str).replace(/_/g, ' ');
};
</script>

<template>
    <Head title="Organizasyon Şeması" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Organizasyon Şeması Yönetimi</h2>
                <div class="flex gap-3 items-center">
                    <select @change="changeTreeType" class="rounded-lg border-gray-300 text-sm font-semibold shadow-sm focus:ring-indigo-500">
                        <option v-for="t in treeTypes" :key="t.id" :value="t.id" :selected="treeType?.id === t.id">
                            {{ t.display_name }}
                        </option>
                    </select>
                    <button @click="openModal('create')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">
                        + Kök Düğüm Ekle
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
                    
                    <div v-if="nodes.length === 0" class="text-center py-10 text-gray-500">
                        Henüz gösterilecek hiyerarşi verisi bulunmuyor.
                    </div>
                    
                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div v-for="rootItem in nodes" :key="rootItem.node.id" 
                             class="bg-gray-50 rounded-xl p-6 border-t-4 border-indigo-500 shadow-sm transition-all"
                             @dragover.prevent="handleDragOver" @drop="handleDrop($event, rootItem.node.id)">
                            
                            <!-- Kök Düğüm -->
                            <div class="flex items-center justify-between group cursor-move" draggable="true" @dragstart="handleDragStart(rootItem.node.id)">
                                <div>
                                    <h3 class="font-bold text-lg text-gray-900">{{ rootItem.node.metadata?.name || rootItem.node.label }}</h3>
                                    <span class="text-xs text-gray-500 uppercase font-bold">{{ rootItem.node.node_subtype }}</span>
                                </div>
                                <div class="opacity-0 group-hover:opacity-100 flex gap-2 transition-opacity">
                                    <button @click="toggleRootDetails(rootItem.node.id)" class="w-8 h-8 flex items-center justify-center rounded-md bg-purple-100 text-purple-700 hover:bg-purple-200 font-bold" title="Detayları Gör">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </button>
                                    <button @click="openModal('create', rootItem.node.id)" class="w-8 h-8 flex items-center justify-center rounded-md bg-green-100 text-green-700 hover:bg-green-200 font-bold" title="Alt Düğüm Ekle">+</button>
                                    <button @click="openModal('edit', null, rootItem.node)" class="w-8 h-8 flex items-center justify-center rounded-md bg-blue-100 text-blue-700 hover:bg-blue-200 font-bold" title="Düzenle">✎</button>
                                    <button @click="deleteNode(rootItem.node.id)" class="w-8 h-8 flex items-center justify-center rounded-md bg-red-100 text-red-700 hover:bg-red-200 font-bold" title="Sil">-</button>
                                </div>
                            </div>

                            <!-- Kök Düğüm Metadata Gösterimi -->
                            <div v-show="expandedRootDetails.has(rootItem.node.id)" class="mt-5 p-4 bg-white rounded-lg border border-gray-200 shadow-inner">
                                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Kök Düğüm Detayları</h4>
                                <div v-if="rootItem.node.metadata && Object.keys(rootItem.node.metadata).length > 0" class="grid grid-cols-1 lg:grid-cols-2 gap-3 text-sm">
                                    <div v-for="(value, key) in rootItem.node.metadata" :key="key" class="bg-gray-50 px-3 py-2 rounded-md border border-gray-100">
                                        <span class="font-bold text-gray-400 block mb-0.5 capitalize text-xs">{{ formatLabel(key) }}</span>                                        <span class="text-gray-900 font-medium break-words">{{ formatValue(value) }}</span>
                                    </div>
                                </div>
                                <div v-else class="text-xs text-gray-400 italic py-2 rounded-lg text-center">
                                    Ek bilgi bulunmuyor.
                                </div>
                            </div>
                            
                            <!-- Alt Düğümler (Rekürsif Bileşen) -->
                            <div v-if="rootItem.children && rootItem.children.length" class="mt-4">
                                <HierarchyNode 
                                    v-for="child in rootItem.children" 
                                    :key="child.node.id" 
                                    :item="child" 
                                    :treeTypeId="rootItem.node.tree_type_id" 
                                    @add="openModal('create', $event)" 
                                    @edit="openModal('edit', null, $event)" 
                                    @remove="deleteNode"
                                    @dragstart="handleDragStart" 
                                    @dragover="handleDragOver" 
                                    @drop="handleDrop" 
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- İşlem Modalı (Form) -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
                
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50 shrink-0">
                    <h3 class="text-lg font-bold text-gray-900">{{ modalAction === 'create' ? 'Düğüm Ekle' : 'Düğümü Düzenle' }}</h3>
                    <button @click="isModalOpen = false" class="text-gray-400 hover:text-gray-600 text-xl font-bold transition">&times;</button>
                </div>
                
                <form @submit.prevent="submitModal" class="p-6 space-y-5 overflow-y-auto custom-scrollbar flex-1">
                    
                    <!-- Düğüm Tipi Seçimi -->
                    <div class="bg-indigo-50 p-4 rounded-lg border border-indigo-100">
                        <label class="block text-sm font-bold text-indigo-900 mb-1">Düğüm Tipi (Kalıp) <span class="text-red-500">*</span></label>
                        <select v-model="form.tree_type_id" required :disabled="modalAction === 'edit'" class="w-full rounded-md border-indigo-200 text-sm focus:border-indigo-500 focus:ring-indigo-500 bg-white">
                            <option v-for="t in treeTypes" :key="t.id" :value="t.id">{{ t.display_name }}</option>
                        </select>
                        <p class="text-[10px] text-indigo-500 mt-1">Düğümün barındıracağı dinamik özellikleri belirler.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <!-- GÖREV 2: Dinamik Varlık Seçimleri (UI) -->
                            
                            <!-- Personel Seçimi -->
                            <template v-if="isPersonel">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sistem Kullanıcısı Seçin <span class="text-red-500">*</span></label>
                                <select v-model="selectedEntityId" required class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="" disabled>-- Kullanıcı Seçiniz --</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} ({{ user.email }})</option>
                                </select>
                            </template>

                            <!-- Departman Seçimi -->
                            <template v-else-if="isDepartment">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Departman/Birim Seçin <span class="text-red-500">*</span></label>
                                <select v-model="selectedEntityId" required class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="" disabled>-- Departman Seçiniz --</option>
                                    <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                                </select>
                            </template>

                            <!-- Direktörlük Seçimi -->
                            <template v-else-if="isDirectorate">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Direktörlük Seçin <span class="text-red-500">*</span></label>
                                <select v-model="selectedEntityId" required class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="" disabled>-- Direktörlük Seçiniz --</option>
                                    <option v-for="dir in directorates" :key="dir.id" :value="dir.id">{{ dir.name }}</option>
                                </select>
                            </template>
                            
                            <!-- Manuel Metin Girişi (Diğer Tipler İçin) -->
                            <template v-else>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Düğüm Referans Adı <span class="text-red-500">*</span></label>
                                <input v-model="form.label" type="text" required class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 placeholder-gray-400" placeholder="Örn: CNC Makinesi 1">
                            </template>
                            
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Alt Kategori (Opsiyonel)</label>
                            <input v-model="form.node_subtype" list="subtypeList" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 placeholder-gray-400" placeholder="Örn: Bakım Personeli">
                            <datalist id="subtypeList">
                                <option v-for="st in subtypes" :key="st" :value="st" />
                            </datalist>
                        </div>
                    </div>

                    <!-- Dinamik Metadata Alanı (JSON Schema Render) -->
                    <div v-if="selectedSchema.length > 0" class="border-t border-gray-100 pt-5 mt-2">
                        <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">Dinamik Özellikler (Metadata)</h4>
                        
                        <div class="space-y-4">
                            <div v-for="field in selectedSchema" :key="field.field">
                                <label class="block text-xs font-semibold text-gray-700 mb-1 capitalize">
                                    {{ formatLabel(field.field) }} <span v-if="field.required" class="text-red-500">*</span>
                                </label>
                                
                                <select v-if="field.type === 'boolean'" v-model="form.metadata[field.field]" :required="field.required" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50">
                                    <option :value="true">Evet</option>
                                    <option :value="false">Hayır</option>
                                </select>
                                
                                <select v-else-if="field.type === 'select'" v-model="form.metadata[field.field]" :required="field.required" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50">
                                    <option value="" disabled>Seçiniz...</option>
                                    <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
                                </select>

                                <select v-else-if="field.type === 'multiselect'" multiple v-model="form.metadata[field.field]" :required="field.required" class="w-full rounded-md border-gray-300 text-sm h-24 focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50">
                                    <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
                                </select>
                                
                                <input v-else-if="field.type === 'number'" type="number" step="any" v-model="form.metadata[field.field]" :required="field.required" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50">
                                
                                <input v-else-if="field.type === 'date'" type="date" v-model="form.metadata[field.field]" :required="field.required" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50">
                                
                                <textarea v-else-if="field.type === 'textarea'" rows="2" v-model="form.metadata[field.field]" :required="field.required" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50"></textarea>
                                
                                <input v-else type="text" v-model="form.metadata[field.field]" :required="field.required" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-5 border-t border-gray-100">
                        <button type="button" @click="isModalOpen = false" class="px-5 py-2.5 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium transition">İptal</button>
                        <button type="submit" :disabled="isProcessing" class="px-5 py-2.5 text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg text-sm font-bold shadow-md transition flex items-center gap-2 disabled:opacity-50">
                            <span v-if="isProcessing">⏳</span> Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; }
</style>