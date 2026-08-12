<script setup>
import { ref, onMounted } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import axios from 'axios';

const props = defineProps({
    directorates: Array,
    totalDirectorates: Number,
    users: Array
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const isProcessing = ref(false);
const centralDirectorates = ref([]);
const selectedCentralDirId = ref('');
const isMatching = ref(false);

const showDeleteModal = ref(false);
const isDeleting = ref(false);
const usageStats = ref(null);
const selectedDeleteDir = ref(null);

const form = useForm({
    name: '',
    director_id: '',
    is_active: true
});

const editForm = useForm({
    id: null,
    name: '',
    director_id: '',
    is_active: true
});

const openCreateModal = () => {
    form.reset();
    form.clearErrors();
    selectedCentralDirId.value = '';
    showCreateModal.value = true;
};

const openEditModal = (dir) => {
    editForm.reset();
    editForm.clearErrors();
    editForm.id = dir.id;
    editForm.name = dir.name;
    editForm.director_id = dir.director_id || '';
    editForm.is_active = dir.is_active;
    showEditModal.value = true;
};

const submitCreate = () => {
    form.post(route('admin.directorates.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        }
    });
};

const submitEdit = () => {
    editForm.put(route('admin.directorates.update', editForm.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
        }
    });
};

const confirmDelete = async (dir) => {
    selectedDeleteDir.value = dir;
    usageStats.value = null;
    showDeleteModal.value = true;
    
    try {
        const response = await axios.get(route('admin.directorates.check-usage', dir.id));
        usageStats.value = response.data;
    } catch (e) {
        console.error('Kullanım verileri alınamadı', e);
        usageStats.value = { departments_count: 0, users_count: 0 };
    }
};

const executeDelete = () => {
    if (!selectedDeleteDir.value) return;
    
    isDeleting.value = true;
    router.delete(route('admin.directorates.destroy', selectedDeleteDir.value.id), {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            showDeleteModal.value = false;
        }
    });
};

const getInitials = (name) => {
    if (!name) return 'DR';
    return name.substring(0, 2).toUpperCase();
};

const fetchCentralDirectorates = async () => {
    try {
        const response = await axios.get(route('admin.directorates.central-list'));
        if (response.data && response.data.success) {
            centralDirectorates.value = response.data.directorates || [];
        } else if (response.data && Array.isArray(response.data)) {
            centralDirectorates.value = response.data;
        }
    } catch (e) {
        console.error('Merkezi direktörlükler çekilemedi', e);
    }
};

onMounted(() => {
    fetchCentralDirectorates();
});

const onCentralDirChange = () => {
    if (selectedCentralDirId.value) {
        const dir = centralDirectorates.value.find(d => d.id === selectedCentralDirId.value);
        if (dir) {
            form.name = dir.name;
        }
    }
};

const findUserMatch = (centralUser) => {
    if (!centralUser) return null;
    
    if (centralUser.tc_no) {
        const matchByTc = props.users.find(u => u.tc_no === centralUser.tc_no);
        if (matchByTc) return matchByTc.id;
    }
    
    if (centralUser.email) {
        const matchByEmail = props.users.find(u => String(u.email).toLowerCase() === String(centralUser.email).toLowerCase());
        if (matchByEmail) return matchByEmail.id;
    }
    
    return null;
};

const matchCentralUsers = () => {
    if (!selectedCentralDirId.value) return;
    
    isMatching.value = true;
    const dir = centralDirectorates.value.find(d => d.id === selectedCentralDirId.value);
    
    if (dir) {
        form.name = dir.name;
        form.director_id = '';
        
        if (dir.director) {
            const directorId = findUserMatch(dir.director);
            if (directorId) {
                form.director_id = directorId;
            }
        }
    }
    
    setTimeout(() => { isMatching.value = false; }, 500);
};

</script>

<template>
    <Head title="Direktörlükler" />

    <AuthenticatedLayout>
        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="mb-8">
                    <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Direktörlük Yönetimi</h1>
                    <p class="mt-2 text-sm text-gray-500 font-medium">Sistemdeki tüm direktörlükleri buradan yönetebilir ve direktör atayabilirsiniz.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex items-center justify-between transition-transform hover:-translate-y-1 duration-300">
                        <div>
                            <p class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-1">TOPLAM DİREKTÖRLÜK</p>
                            <h3 class="text-4xl font-black text-gray-900">{{ totalDirectorates }}</h3>
                        </div>
                        <div class="w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                    </div>

                    <div class="md:col-span-2 flex items-center justify-end gap-3">
                        <button @click="openCreateModal" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 border border-transparent rounded-xl font-bold text-sm text-white shadow-lg hover:shadow-blue-500/30 hover:scale-105 transition-all duration-200 focus:outline-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                            </svg>
                            Yeni Direktörlük Ekle
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr class="bg-gray-50 border-b-2 border-gray-100">
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Direktörlük Adı</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Direktör</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Durum</th>
                                    <th scope="col" class="px-6 py-4 text-right text-xs font-extrabold text-gray-500 uppercase tracking-wider">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <tr v-for="dir in directorates" :key="dir.id" class="hover:bg-gray-50/80 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm">
                                                {{ getInitials(dir.name) }}
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-bold text-gray-900">{{ dir.name }}</div>
                                                <div class="text-xs text-gray-500">ID: #{{ dir.id }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900" v-if="dir.director">
                                            <div class="font-semibold text-indigo-700">{{ dir.director.name }}</div>
                                            <div class="text-xs text-gray-500">{{ dir.director.email }}</div>
                                        </div>
                                        <span v-else class="text-xs text-gray-400 italic">Atanmamış</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span v-if="dir.is_active" class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-100 text-emerald-800">
                                            <svg class="mr-1.5 h-2 w-2 text-emerald-500" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3" /></svg>
                                            Aktif
                                        </span>
                                        <span v-else class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-gray-100 text-gray-800">
                                            Pasif
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end gap-2">
                                            <button @click="openEditModal(dir)" class="p-2 text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors" title="Düzenle">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                            </button>
                                            <button @click="confirmDelete(dir)" class="p-2 text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-colors" title="Sil">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="directorates.length === 0">
                                    <td colspan="4" class="px-6 py-12 text-center text-gray-500 font-medium">
                                        Henüz hiç direktörlük eklenmemiş.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg transform transition-all my-8">
                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-xl font-extrabold text-gray-900">Yeni Direktörlük Ekle</h3>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-500 focus:outline-none transition-colors">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <div class="p-6">
                    <!-- Merkezi Eşleştirme Bölümü -->
                    <div class="bg-indigo-50 rounded-xl p-4 mb-6 border border-indigo-100">
                        <h4 class="text-xs font-bold text-indigo-800 uppercase tracking-wide mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            Merkezi Sistemden Eşleştir (Opsiyonel)
                        </h4>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <div class="flex-grow relative">
                                <SearchableSelect 
                                    v-model="selectedCentralDirId" 
                                    :options="centralDirectorates" 
                                    labelKey="name" 
                                    valueKey="id" 
                                    placeholder="Merkezi Direktörlük Seç..." 
                                    @change="onCentralDirChange"
                                    :multiple="false"
                                />
                            </div>
                            <button @click="matchCentralUsers" :disabled="!selectedCentralDirId || isMatching" type="button" class="whitespace-nowrap px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-semibold hover:bg-indigo-700 transition disabled:opacity-50">
                                {{ isMatching ? 'Eşleştiriliyor...' : 'Yöneticileri Çek' }}
                            </button>
                        </div>
                    </div>

                    <form @submit.prevent="submitCreate">
                        <div class="space-y-5">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Direktörlük Adı <span class="text-red-500">*</span></label>
                                <input v-model="form.name" type="text" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" required>
                                <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                            </div>
                            <div class="relative">
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Direktör Seçimi</label>
                                <SearchableSelect 
                                    v-model="form.director_id" 
                                    :options="users" 
                                    labelKey="name" 
                                    valueKey="id" 
                                    placeholder="Direktör Ara veya Seç..." 
                                    :multiple="false"
                                />
                                <p v-if="form.errors.director_id" class="mt-1 text-xs text-red-600">{{ form.errors.director_id }}</p>
                            </div>
                            <div class="flex items-center mt-4">
                                <input id="create_is_active" v-model="form.is_active" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="create_is_active" class="ml-2 block text-sm text-gray-900 font-medium">
                                    Aktif mi?
                                </label>
                            </div>
                            <div class="pt-4 flex justify-end gap-3 border-t border-gray-100">
                                <button type="button" @click="showCreateModal = false" class="px-5 py-2.5 rounded-xl font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 transition-colors">İptal</button>
                                <button type="submit" :disabled="form.processing" class="px-5 py-2.5 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-md transition-colors disabled:opacity-50">
                                    <span v-if="form.processing">Kaydediliyor...</span>
                                    <span v-else>Kaydet</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg transform transition-all my-8">
                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-xl font-extrabold text-gray-900">Direktörlük Düzenle</h3>
                    <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-500 focus:outline-none transition-colors">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form @submit.prevent="submitEdit" class="p-6">
                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Direktörlük Adı <span class="text-red-500">*</span></label>
                            <input v-model="editForm.name" type="text" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" required>
                            <p v-if="editForm.errors.name" class="mt-1 text-xs text-red-600">{{ editForm.errors.name }}</p>
                        </div>
                        <div class="relative">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Direktör Seçimi</label>
                            <SearchableSelect 
                                v-model="editForm.director_id" 
                                :options="users" 
                                labelKey="name" 
                                valueKey="id" 
                                placeholder="Direktör Ara veya Seç..." 
                                :multiple="false"
                            />
                            <p v-if="editForm.errors.director_id" class="mt-1 text-xs text-red-600">{{ editForm.errors.director_id }}</p>
                        </div>
                        <div class="flex items-center mt-4">
                            <input id="edit_is_active" v-model="editForm.is_active" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="edit_is_active" class="ml-2 block text-sm text-gray-900 font-medium">
                                Aktif mi?
                            </label>
                        </div>
                        <div class="pt-4 flex justify-end gap-3 border-t border-gray-100">
                            <button type="button" @click="showEditModal = false" class="px-5 py-2.5 rounded-xl font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 transition-colors">İptal</button>
                            <button type="submit" :disabled="editForm.processing" class="px-5 py-2.5 rounded-xl font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-md transition-colors disabled:opacity-50">
                                <span v-if="editForm.processing">Güncelleniyor...</span>
                                <span v-else>Güncelle</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- Delete Warning Modal -->
        <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg transform transition-all my-8 overflow-hidden">
                <div class="bg-red-50 px-6 py-6 border-b border-red-100 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-extrabold text-red-900">Direktörlük Siliniyor!</h3>
                        <p class="text-red-700 text-sm mt-1 font-medium">{{ selectedDeleteDir?.name }}</p>
                    </div>
                </div>

                <div class="p-6">
                    <p class="text-gray-700 font-medium mb-4">Bu direktörlüğü silmek istediğinize emin misiniz?</p>

                    <div v-if="!usageStats" class="flex justify-center items-center py-6">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                        <span class="ml-3 text-sm text-gray-500 font-medium">Veriler taranıyor...</span>
                    </div>

                    <div v-else class="space-y-4">
                        <div v-if="usageStats.departments_count > 0 || usageStats.users_count > 0" class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                            <p class="text-sm text-amber-800 font-bold mb-3 flex items-center">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                DİKKAT: Bu Direktörlüğe Bağlı Veriler Var!
                            </p>
                            
                            <ul class="space-y-2 text-sm text-amber-700 ml-1">
                                <li v-if="usageStats.departments_count > 0" class="flex items-start">
                                    <span class="inline-flex items-center justify-center bg-amber-200 text-amber-900 font-bold rounded-full w-5 h-5 text-[11px] mr-2 shrink-0">{{ usageStats.departments_count }}</span>
                                    <span>Adet <b>Departman</b> boşluğa düşecek (Bağlı direktörlük alanı boşalacak).</span>
                                </li>
                                <li v-if="usageStats.users_count > 0" class="flex items-start">
                                    <span class="inline-flex items-center justify-center bg-amber-200 text-amber-900 font-bold rounded-full w-5 h-5 text-[11px] mr-2 shrink-0">{{ usageStats.users_count }}</span>
                                    <span>Adet <b>Kullanıcı</b> (Çalışan) boşluğa düşecek.</span>
                                </li>
                            </ul>
                        </div>
                        
                        <div v-else class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex items-center gap-3">
                            <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <p class="text-sm text-emerald-800 font-medium">Harika! Bu direktörlüğe bağlı hiçbir departman veya çalışan bulunmuyor. Güvenle silebilirsiniz.</p>
                        </div>
                    </div>

                    <div class="pt-6 mt-6 flex justify-end gap-3 border-t border-gray-100">
                        <button type="button" @click="showDeleteModal = false" class="px-5 py-2.5 rounded-xl font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 transition-colors">
                            İptal, Silme
                        </button>
                        <button type="button" @click="executeDelete" :disabled="isDeleting || !usageStats" class="px-5 py-2.5 rounded-xl font-semibold text-white bg-red-600 hover:bg-red-700 shadow-md shadow-red-500/20 transition-all focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-50 flex items-center">
                            <svg v-if="isDeleting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span v-if="isDeleting">Siliniyor...</span>
                            <span v-else>Evet, Zorla Sil</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
