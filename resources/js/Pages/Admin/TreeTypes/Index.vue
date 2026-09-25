<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

const props = defineProps({
    treeTypes: {
        type: Array,
        default: () => []
    }
});

// Dinamik Alan Tipleri ve Özellikleri
const SCHEMA_FEATURES = {
    text: { requiresUnit: false, requiresOptions: false, label: "Kısa Metin", badgeClass: "bg-sky-50 text-sky-700 border-sky-200" },
    textarea: { requiresUnit: false, requiresOptions: false, label: "Uzun Metin", badgeClass: "bg-indigo-50 text-indigo-700 border-indigo-200" },
    number: { requiresUnit: true, requiresOptions: false, label: "Sayısal Değer", badgeClass: "bg-amber-50 text-amber-700 border-amber-200" },
    boolean: { requiresUnit: false, requiresOptions: false, label: "Evet / Hayır", badgeClass: "bg-teal-50 text-teal-700 border-teal-200" },
    date: { requiresUnit: false, requiresOptions: false, label: "Tarih", badgeClass: "bg-emerald-50 text-emerald-700 border-emerald-200" },
    select: { requiresUnit: false, requiresOptions: true, label: "Açılır Liste", badgeClass: "bg-purple-50 text-purple-700 border-purple-200" },
    multiselect: { requiresUnit: false, requiresOptions: true, label: "Çoklu Seçim", badgeClass: "bg-pink-50 text-pink-700 border-pink-200" }
};

// --- Toast Bildirim Sistemi ---
const toasts = ref([]);
const showToast = (type, message, title = '') => {
    const id = Date.now() + Math.random();
    toasts.value.push({ id, type, title, message });
    setTimeout(() => {
        toasts.value = toasts.value.filter(t => t.id !== id);
    }, 4000);
};
const removeToast = (id) => {
    toasts.value = toasts.value.filter(t => t.id !== id);
};

// --- Arama / Filtreleme ---
const searchQuery = ref('');
const filteredTreeTypes = computed(() => {
    if (!searchQuery.value.trim()) return props.treeTypes;
    const q = searchQuery.value.toLowerCase().trim();
    return props.treeTypes.filter(t => 
        (t.display_name && t.display_name.toLowerCase().includes(q)) ||
        (t.key && t.key.toLowerCase().includes(q)) ||
        (t.description && t.description.toLowerCase().includes(q))
    );
});

// --- Form State ---
const form = ref({
    id: null,
    display_name: '',
    key: '',
    description: '',
    is_active: true,
    schema: []
});

const isProcessing = ref(false);

const resetForm = () => {
    form.value = {
        id: null,
        display_name: '',
        key: '',
        description: '',
        is_active: true,
        schema: []
    };
};

const editTreeType = (type) => {
    form.value = {
        id: type.id,
        display_name: type.display_name,
        key: type.key,
        description: type.description || '',
        is_active: type.is_active !== undefined ? Boolean(type.is_active) : true,
        schema: Array.isArray(type.schema) ? type.schema.map(item => {
            return {
                ...item,
                label: item.label || item.field || item.name || '',
                name: item.name || item.field || '',
                unit: item.unit && item.unit !== 'null' ? item.unit : '',
                options: Array.isArray(item.options) ? item.options.join(', ') : (item.options || ''),
                required: Boolean(item.required),
                isNameManuallyEdited: true
            };
        }) : []
    };
};

// --- Şema Satır İşlemleri ---
const addSchemaRow = () => {
    form.value.schema.push({
        label: '',
        name: '',
        type: 'text',
        required: false,
        unit: '',
        options: '',
        isNameManuallyEdited: false
    });
};

const removeSchemaRow = (index) => {
    form.value.schema.splice(index, 1);
};

const moveSchemaRowUp = (index) => {
    if (index <= 0) return;
    const temp = form.value.schema[index];
    form.value.schema[index] = form.value.schema[index - 1];
    form.value.schema[index - 1] = temp;
};

const moveSchemaRowDown = (index) => {
    if (index >= form.value.schema.length - 1) return;
    const temp = form.value.schema[index];
    form.value.schema[index] = form.value.schema[index + 1];
    form.value.schema[index + 1] = temp;
};

const duplicateSchemaRow = (index) => {
    const src = form.value.schema[index];
    const clone = {
        ...JSON.parse(JSON.stringify(src)),
        label: src.label ? `${src.label} (Kopya)` : '',
        name: src.name ? `${src.name}_kopya` : '',
        isNameManuallyEdited: true
    };
    form.value.schema.splice(index + 1, 0, clone);
    showToast('info', `'${src.label || src.name}' alanı çoğaltıldı.`);
};

// Türkçe Slugify Helper
const generateKeyFromLabel = (item) => {
    if (!item.isNameManuallyEdited || item.name === '') {
        const turkishMap = {
            'ç': 'c', 'ğ': 'g', 'ı': 'i', 'ö': 'o', 'ş': 's', 'ü': 'u',
            'Ç': 'c', 'Ğ': 'g', 'İ': 'i', 'Ö': 'o', 'Ş': 's', 'Ü': 'u'
        };
        let slug = item.label || '';
        slug = slug.replace(/[çğıöşüÇĞİÖŞÜ]/g, match => turkishMap[match] || match);
        item.name = slug.toLowerCase()
                        .replace(/[\s-]+/g, '_')
                        .replace(/[^a-z0-9_]/g, '');
    }
};

const markAsManuallyEdited = (item) => {
    item.isNameManuallyEdited = true;
};

// --- Form Kaydetme ---
const submitForm = async () => {
    isProcessing.value = true;

    // Şemayı API yapısına dönüştür
    const processedSchema = form.value.schema
        .filter(item => item.label.trim() !== '' || item.name.trim() !== '')
        .map(item => {
            const formatted = {
                label: item.label.trim(),
                name: (item.name || '').trim().toLowerCase().replace(/[^a-z0-9_]/g, '_'),
                type: item.type,
                required: Boolean(item.required)
            };

            if (SCHEMA_FEATURES[item.type]?.requiresUnit) {
                formatted.unit = item.unit ? item.unit.trim() : null;
            }

            if (SCHEMA_FEATURES[item.type]?.requiresOptions) {
                formatted.options = typeof item.options === 'string'
                    ? item.options.split(',').map(s => s.trim()).filter(Boolean)
                    : (item.options || []);
            }

            return formatted;
        });

    const payload = {
        display_name: form.value.display_name.trim(),
        key: form.value.key ? form.value.key.trim() : null,
        description: form.value.description ? form.value.description.trim() : null,
        is_active: form.value.is_active,
        schema: processedSchema
    };

    try {
        let res;
        if (form.value.id) {
            res = await axios.put(route('admin.tree-types.update', { treeType: form.value.id }), payload);
        } else {
            res = await axios.post(route('admin.tree-types.store'), payload);
        }

        showToast('success', res.data?.message || 'Ağaç tipi başarıyla kaydedildi.', 'Başarılı');
        router.reload({ only: ['treeTypes'] });
        resetForm();
    } catch (error) {
        const errorMsg = error.response?.data?.message || 'Bir hata oluştu. Lütfen bilgileri kontrol edin.';
        showToast('error', errorMsg, 'Hata');
    } finally {
        isProcessing.value = false;
    }
};

// --- Silme Onay Modalı ---
const deleteModalOpen = ref(false);
const itemToDelete = ref(null);
const isDeleting = ref(false);

const confirmDelete = (type) => {
    itemToDelete.value = type;
    deleteModalOpen.value = true;
};

const executeDelete = async () => {
    if (!itemToDelete.value) return;
    isDeleting.value = true;

    try {
        const res = await axios.delete(route('admin.tree-types.destroy', { treeType: itemToDelete.value.id }));
        showToast('success', res.data?.message || 'Ağaç tipi silindi.', 'Silindi');
        deleteModalOpen.value = false;
        if (form.value.id === itemToDelete.value.id) {
            resetForm();
        }
        router.reload({ only: ['treeTypes'] });
    } catch (error) {
        const errorMsg = error.response?.data?.message || 'Silme işlemi başarısız oldu.';
        showToast('error', errorMsg, 'Silinemedi');
    } finally {
        isDeleting.value = false;
        itemToDelete.value = null;
    }
};
</script>

<template>
    <Head title="Şema Tasarımcısı (Tree Types)" />

    <AuthenticatedLayout>
        <!-- TOAST BİLDİRİM BİLEŞENİ -->
        <div class="fixed top-5 right-5 z-50 flex flex-col gap-2 max-w-sm pointer-events-none">
            <TransitionGroup 
                enter-active-class="transform ease-out duration-300 transition" 
                enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-4" 
                enter-to-class="translate-y-0 opacity-100 sm:translate-x-0" 
                leave-active-class="transition ease-in duration-200" 
                leave-from-class="opacity-100" 
                leave-to-class="opacity-0">
                <div v-for="toast in toasts" :key="toast.id" 
                     class="pointer-events-auto flex items-start gap-3 p-4 rounded-xl shadow-lg border backdrop-blur-sm transition-all"
                     :class="[
                         toast.type === 'success' ? 'bg-white/95 border-emerald-200 text-emerald-900 shadow-emerald-500/10' : '',
                         toast.type === 'error' ? 'bg-white/95 border-rose-200 text-rose-900 shadow-rose-500/10' : '',
                         toast.type === 'info' ? 'bg-white/95 border-indigo-200 text-indigo-900 shadow-indigo-500/10' : '',
                     ]">
                    <!-- İkonlar -->
                    <div class="flex-shrink-0 mt-0.5">
                        <svg v-if="toast.type === 'success'" class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <svg v-else-if="toast.type === 'error'" class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <svg v-else class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>

                    <div class="flex-1 text-sm">
                        <p v-if="toast.title" class="font-bold leading-tight mb-0.5">{{ toast.title }}</p>
                        <p class="text-xs leading-relaxed opacity-90">{{ toast.message }}</p>
                    </div>

                    <button @click="removeToast(toast.id)" class="text-gray-400 hover:text-gray-600 transition -mr-1 -mt-1 p-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            </TransitionGroup>
        </div>

        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-bold text-xl text-gray-800 leading-tight">Şema Tasarımcısı (Tree Types)</h2>
                    <p class="text-xs text-gray-500 mt-1">Organizasyon ve varlık hiyerarşisi için dinamik veri tiplerini ve alan şemalarını yönetin.</p>
                </div>
                <button @click="resetForm" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-sm font-semibold transition shadow-sm hover:shadow flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Yeni Ağaç Tipi Tasarla</span>
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-12 gap-8">
                
                <!-- Sol Panel: Mevcut Ağaç Tipleri ve Arama -->
                <div class="lg:col-span-4 flex flex-col gap-3">
                    
                    <!-- Arama Kutusu -->
                    <div class="relative">
                        <input v-model="searchQuery" type="text" placeholder="Ağaç tiplerinde ara..."
                               class="w-full pl-9 pr-4 py-2 text-sm bg-white border border-gray-200 rounded-xl shadow-sm focus:border-indigo-500 focus:ring-indigo-500 placeholder-gray-400" />
                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <button v-if="searchQuery" @click="searchQuery = ''" class="absolute right-3 top-2.5 text-xs text-gray-400 hover:text-gray-600">✕</button>
                    </div>

                    <!-- Liste -->
                    <div class="flex flex-col gap-3 max-h-[75vh] overflow-y-auto pr-1">
                        <div v-if="filteredTreeTypes.length === 0" class="text-gray-500 text-sm p-6 bg-white rounded-xl shadow-sm border border-gray-100 text-center">
                            <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span v-if="searchQuery">Aramanızla eşleşen ağaç tipi bulunamadı.</span>
                            <span v-else>Henüz tanımlı bir ağaç tipi bulunmuyor.</span>
                        </div>
                        
                        <div v-for="type in filteredTreeTypes" :key="type.id" @click="editTreeType(type)"
                             class="bg-white p-4 rounded-xl shadow-sm cursor-pointer hover:shadow-md transition-all group relative border-l-4"
                             :class="[
                                 type.is_active ? 'border-l-emerald-500' : 'border-l-rose-400 opacity-80',
                                 form.id === type.id ? 'ring-2 ring-indigo-500 bg-indigo-50/20' : 'border border-gray-100'
                             ]">
                            <div class="flex justify-between items-start gap-2">
                                <div class="min-w-0 flex-1">
                                    <h4 class="text-base font-bold text-gray-900 group-hover:text-indigo-600 transition truncate">
                                        {{ type.display_name }}
                                    </h4>
                                    <p class="text-[11px] text-gray-400 font-mono tracking-tight mt-0.5 truncate">
                                        {{ type.key }}
                                    </p>
                                </div>

                                <!-- Silme Butonu -->
                                <button @click.stop="confirmDelete(type)" 
                                        class="text-gray-300 hover:text-rose-600 hover:bg-rose-50 p-1.5 rounded-lg opacity-0 group-hover:opacity-100 transition" 
                                        title="Bu tipi sil">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>

                            <p v-if="type.description" class="text-xs text-gray-500 mt-2 line-clamp-2 leading-relaxed">
                                {{ type.description }}
                            </p>

                            <!-- İstatistik Rozetleri -->
                            <div class="flex items-center gap-2 mt-3 pt-2.5 border-t border-gray-50 text-[11px]">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md font-semibold bg-gray-100 text-gray-700">
                                    {{ type.schema?.length || 0 }} Alan
                                </span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md font-semibold"
                                      :class="type.nodes_count > 0 ? 'bg-indigo-50 text-indigo-700' : 'bg-gray-50 text-gray-400'">
                                    {{ type.nodes_count || 0 }} Düğüm
                                </span>
                                <span class="ml-auto inline-flex items-center gap-1 font-medium"
                                      :class="type.is_active ? 'text-emerald-600' : 'text-rose-500'">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="type.is_active ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                                    {{ type.is_active ? 'Aktif' : 'Pasif' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sağ Panel: Form ve Şema Tasarımcısı -->
                <div class="lg:col-span-8 bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">
                    <form @submit.prevent="submitForm">
                        
                        <!-- Başlık ve Durum Switch'i -->
                        <div class="flex flex-wrap justify-between items-center pb-5 mb-6 border-b border-gray-100 gap-4">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">
                                    {{ form.id ? 'Ağaç Tipi ve Şema Düzenle' : 'Yeni Ağaç Tipi Tasarla' }}
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    {{ form.id ? `Düzenlenen: ${form.display_name}` : 'Sisteme yeni bir varlık / hiyerarşi türü ve veri şeması ekleyin.' }}
                                </p>
                            </div>

                            <!-- Aktif / Pasif Toggle Switch (Eksik özellik tamamlandı) -->
                            <div class="flex items-center gap-3 bg-gray-50 px-3.5 py-2 rounded-xl border border-gray-200">
                                <span class="text-xs font-bold" :class="form.is_active ? 'text-emerald-700' : 'text-gray-500'">
                                    {{ form.is_active ? 'Aktif Tip' : 'Pasif Tip' }}
                                </span>
                                <button type="button" @click="form.is_active = !form.is_active" 
                                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                        :class="form.is_active ? 'bg-emerald-500' : 'bg-gray-300'">
                                    <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition duration-200 ease-in-out"
                                          :class="form.is_active ? 'translate-x-5' : 'translate-x-0'" />
                                </button>
                            </div>
                        </div>

                        <!-- Temel Bilgiler Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 mb-1.5">
                                    Ağaç Tipi (Görünen Ad) <span class="text-rose-500">*</span>
                                </label>
                                <input v-model="form.display_name" type="text" required 
                                       placeholder="Örn: CNC Makineleri, Saha Personeli" 
                                       class="w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 placeholder-gray-400 font-medium">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 mb-1.5">
                                    Sistem Anahtarı (Key)
                                </label>
                                <input v-model="form.key" type="text" 
                                       placeholder="Boş bırakılırsa otomatik üretilir" 
                                       class="w-full rounded-xl border-gray-300 bg-gray-50 text-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-gray-700">
                                <p class="text-[10px] text-gray-400 mt-1">İş akışı kural motorunda bu anahtarla tanımlanır.</p>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 mb-1.5">
                                Açıklama
                            </label>
                            <textarea v-model="form.description" rows="2" 
                                      placeholder="Bu ağaç tipinin nerede ve hangi amaçla kullanılacağını belirten kısa bir açıklama..."
                                      class="w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 placeholder-gray-400"></textarea>
                        </div>

                        <!-- Dinamik Şema Alanları Başlığı -->
                        <div class="border-t border-gray-100 pt-6">
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <h4 class="text-base font-bold text-gray-800">Dinamik Şema (Metadata Fields)</h4>
                                    <p class="text-xs text-gray-500">Bu tipe ait hiyerarşi düğümlerinde doldurulacak özel form alanları.</p>
                                </div>
                                <button type="button" @click="addSchemaRow" 
                                        class="text-xs text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-100 px-3.5 py-2 rounded-xl font-bold transition shadow-sm flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    <span>+ Yeni Alan Ekle</span>
                                </button>
                            </div>

                            <!-- Boş Durum (Empty State) -->
                            <div v-if="form.schema.length === 0" 
                                 class="p-8 text-center bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200">
                                <div class="w-12 h-12 rounded-full bg-indigo-50 text-indigo-500 flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                                </div>
                                <h5 class="text-sm font-bold text-gray-700">Henüz Dinamik Alan Tanımlanmadı</h5>
                                <p class="text-xs text-gray-500 max-w-sm mx-auto mt-1 mb-4 leading-relaxed">
                                    Yukarıdaki "+ Yeni Alan Ekle" butonuna basarak metin, sayı, tarih, evet/hayır veya açılır liste alanları tanımlayabilirsiniz.
                                </p>
                                <button type="button" @click="addSchemaRow" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                                    Hemen bir alan ekleyin &rarr;
                                </button>
                            </div>

                            <!-- Şema Satırları -->
                            <div v-else class="space-y-3.5">
                                <div v-for="(item, index) in form.schema" :key="index" 
                                     class="flex flex-col gap-3 bg-gray-50/80 p-4 sm:p-5 rounded-2xl border border-gray-200 shadow-sm transition-all hover:border-indigo-200 hover:shadow-md">
                                    
                                    <!-- Üst Başlık ve Satır Kontrolleri (Sıralama / Çoğaltma / Silme) -->
                                    <div class="flex items-center justify-between pb-2 border-b border-gray-200/70 text-xs">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-gray-400">#{{ index + 1 }}</span>
                                            
                                            <!-- Tip Rozeti (Badge) -->
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider border"
                                                  :class="SCHEMA_FEATURES[item.type]?.badgeClass || 'bg-gray-100 text-gray-600 border-gray-200'">
                                                {{ SCHEMA_FEATURES[item.type]?.label || item.type }}
                                            </span>

                                            <span v-if="item.required" class="bg-rose-50 text-rose-600 border border-rose-200 px-1.5 py-0.5 rounded text-[10px] font-bold">
                                                Zorunlu
                                            </span>
                                        </div>

                                        <!-- Eylem Butonları: Yukarı/Aşağı Sıralama, Kopyalama, Silme -->
                                        <div class="flex items-center gap-1">
                                            <!-- Yukarı Taşı -->
                                            <button type="button" @click="moveSchemaRowUp(index)" :disabled="index === 0"
                                                    class="p-1 rounded text-gray-400 hover:text-indigo-600 hover:bg-white disabled:opacity-30 disabled:hover:bg-transparent" title="Yukarı taşı">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                                            </button>
                                            
                                            <!-- Aşağı Taşı -->
                                            <button type="button" @click="moveSchemaRowDown(index)" :disabled="index === form.schema.length - 1"
                                                    class="p-1 rounded text-gray-400 hover:text-indigo-600 hover:bg-white disabled:opacity-30 disabled:hover:bg-transparent" title="Aşağı taşı">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                            </button>

                                            <!-- Kopyala (Duplicate) -->
                                            <button type="button" @click="duplicateSchemaRow(index)"
                                                    class="p-1 rounded text-gray-400 hover:text-amber-600 hover:bg-white transition" title="Bu alanı çoğalt">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                            </button>

                                            <!-- Sil -->
                                            <button type="button" @click="removeSchemaRow(index)"
                                                    class="p-1 rounded text-gray-400 hover:text-rose-600 hover:bg-white transition ml-1" title="Alanı sil">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Çift Girdi (Label ve Key) -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">
                                                Görünecek Ad (Label) <span class="text-rose-500">*</span>
                                            </label>
                                            <input v-model="item.label" @input="generateKeyFromLabel(item)" type="text" 
                                                   placeholder="Örn: Günlük Kapasite, Vardiya" required 
                                                   class="w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 bg-white">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">
                                                Veritabanı Anahtarı (Key) <span class="text-rose-500">*</span>
                                            </label>
                                            <input v-model="item.name" @input="markAsManuallyEdited(item)" type="text" 
                                                   placeholder="Örn: gunluk_kapasite, vardiya" required 
                                                   class="w-full rounded-xl border-gray-300 bg-white text-sm font-mono text-indigo-700 focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                    </div>

                                    <!-- Tip Seçimi ve Zorunlu Onay Kutusu -->
                                    <div class="flex flex-wrap md:flex-nowrap items-center gap-3">
                                        <div class="w-full md:w-60">
                                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Veri Tipi</label>
                                            <select v-model="item.type" 
                                                    class="w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 font-medium text-gray-800 bg-white">
                                                <option v-for="(feature, key) in SCHEMA_FEATURES" :key="key" :value="key">
                                                    {{ feature.label }}
                                                </option>
                                            </select>
                                        </div>
                                        
                                        <div class="flex items-center gap-2 mt-4 md:mt-5 bg-white px-3.5 py-2 rounded-xl border border-gray-200">
                                            <input v-model="item.required" type="checkbox" :id="'req_' + index" 
                                                   class="rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer">
                                            <label :for="'req_' + index" class="text-xs font-bold text-gray-700 cursor-pointer select-none">
                                                Zorunlu Alan
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <!-- Ek Özellikler: Birim veya Seçenek Listesi -->
                                    <div v-if="SCHEMA_FEATURES[item.type]?.requiresUnit || SCHEMA_FEATURES[item.type]?.requiresOptions" 
                                         class="flex flex-col sm:flex-row gap-3 p-3 bg-white rounded-xl border border-indigo-100">
                                        
                                        <!-- Sayı Birimi (kg, adet vb.) -->
                                        <div v-if="SCHEMA_FEATURES[item.type]?.requiresUnit" class="sm:w-1/3">
                                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Birim (Örn: kg, adet, TL)</label>
                                            <input v-model="item.unit" type="text" placeholder="Örn: kg, gün, adet" 
                                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>

                                        <!-- Açılır Liste Seçenekleri -->
                                        <div v-if="SCHEMA_FEATURES[item.type]?.requiresOptions" class="flex-1">
                                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">
                                                Seçenekler <span class="text-rose-500">*</span>
                                            </label>
                                            <input v-model="item.options" type="text" 
                                                   placeholder="Seçenekleri virgülle ayırarak yazın (Örn: Gündüz, Akşam, Gece)" 
                                                   required
                                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <span class="text-[10px] text-gray-400 mt-1 block">Örnek: Aktif, Pasif, Bakımda</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Butonlar -->
                        <div class="flex justify-between items-center gap-4 mt-8 pt-6 border-t border-gray-100">
                            <button v-if="form.id" type="button" @click="resetForm" 
                                    class="px-4 py-2.5 rounded-xl text-gray-600 bg-gray-100 hover:bg-gray-200 text-sm font-semibold transition">
                                Vazgeç / Yeni Oluştur
                            </button>
                            <span v-else></span>

                            <button type="submit" :disabled="isProcessing" 
                                    class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-md hover:shadow-lg disabled:opacity-50 flex items-center gap-2">
                                <span v-if="isProcessing">⏳</span>
                                <span>{{ isProcessing ? 'Kaydediliyor...' : (form.id ? 'Değişiklikleri Güncelle' : 'Ağaç Tipini Kaydet') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- SİLME ONAY MODALI (Tarayıcı confirm yerine) -->
        <div v-if="deleteModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 animate-scale-up">
                <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                </div>

                <h3 class="text-center font-bold text-lg text-gray-900">Ağaç Tipini Sil</h3>
                
                <p class="text-center text-sm text-gray-600 mt-2">
                    <strong>{{ itemToDelete?.display_name }}</strong> ağaç tipini silmek istediğinize emin misiniz?
                </p>

                <!-- Bağlı Düğüm Uyarısı -->
                <div v-if="itemToDelete?.nodes_count > 0" class="mt-4 p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800">
                    <p class="font-bold flex items-center gap-1.5">
                        <span>⚠️</span>
                        <span>Bağlı Düğümler Mevcut ({{ itemToDelete.nodes_count }} Düğüm)</span>
                    </p>
                    <p class="mt-1">
                        Bu ağaç tipine bağlı organizasyon düğümleri bulunduğu için silme işlemi engellenecektir. Öncelikle hiyerarşi ağacındaki düğümleri kaldırmalısınız.
                    </p>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" @click="deleteModalOpen = false" :disabled="isDeleting"
                            class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-xl transition">
                        İptal
                    </button>
                    <button type="button" @click="executeDelete" :disabled="isDeleting"
                            class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white text-sm font-bold rounded-xl transition shadow-md disabled:opacity-50">
                        {{ isDeleting ? 'Siliniyor...' : 'Evet, Sil' }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>