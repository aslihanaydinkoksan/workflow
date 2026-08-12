<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import axios from 'axios';

const props = defineProps({
    departments: Array,
    totalDepartments: Number,
    directorates: Array,
    users: Array
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showSyncModal = ref(false);
const isSyncing = ref(false);
const centralDepartments = ref([]);
const selectedCentralDeptId = ref('');
const selectedCentralDeptIdEdit = ref('');
const isMatching = ref(false);
const isMatchingEdit = ref(false);

const form = useForm({
    name: '',
    directorate_id: '',
    manager_ids: [],
    assistant_manager_ids: []
});

const existingDepartment = computed(() => {
    if (!form.name) return null;
    return props.departments.find(d => d.name.toLowerCase() === form.name.toLowerCase());
});

const editForm = useForm({
    id: null,
    name: '',
    directorate_id: '',
    manager_ids: [],
    assistant_manager_ids: []
});

const openCreateModal = () => {
    form.reset();
    form.clearErrors();
    selectedCentralDeptId.value = '';
    showCreateModal.value = true;
};

const openEditModal = (dept) => {
    editForm.reset();
    editForm.clearErrors();
    selectedCentralDeptIdEdit.value = '';
    editForm.id = dept.id;
    editForm.name = dept.name;
    editForm.directorate_id = dept.directorate_id || '';
    editForm.manager_ids = (dept.managers || []).map(m => m.id);
    editForm.assistant_manager_ids = (dept.assistant_managers || []).map(m => m.id); // Note: backend sends assistant_managers as assistantManagers in camelCase maybe?
    // Let's fallback and support both camelCase and snake_case
    const assistantMgrs = dept.assistantManagers || dept.assistant_managers || [];
    editForm.assistant_manager_ids = assistantMgrs.map(m => m.id);

    showEditModal.value = true;
};

const submitCreate = () => {
    if (existingDepartment.value) {
        form.put(route('admin.departments.update', existingDepartment.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showCreateModal.value = false;
                form.reset();
            }
        });
    } else {
        form.post(route('admin.departments.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showCreateModal.value = false;
                form.reset();
            }
        });
    }
};

const submitEdit = () => {
    editForm.put(route('admin.departments.update', editForm.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
        }
    });
};

const deleteDepartment = (id) => {
    if (confirm('Bu departmanı silmek istediğinizden emin misiniz? Alt departmanları varsa silinemez.')) {
        router.delete(route('admin.departments.destroy', id), {
            preserveScroll: true
        });
    }
};

const getInitials = (name) => {
    if (!name) return 'DP';
    return name.substring(0, 2).toUpperCase();
};

const fetchCentralDepartments = async () => {
    try {
        const response = await axios.get(route('admin.departments.central-list'));
        if (response.data && response.data.success) {
            centralDepartments.value = response.data.departments || [];
        } else if (response.data && Array.isArray(response.data)) {
            centralDepartments.value = response.data;
        }
    } catch (e) {
        console.error('Merkezi departmanlar çekilemedi', e);
    }
};

onMounted(() => {
    fetchCentralDepartments();
});

const onCentralDeptChange = () => {
    if (selectedCentralDeptId.value) {
        const dept = centralDepartments.value.find(d => d.id === selectedCentralDeptId.value);
        if (dept) {
            form.name = dept.name;
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
    
    if (centralUser.name) {
        const matchByName = props.users.find(u => String(u.name).toLowerCase() === String(centralUser.name).toLowerCase());
        if (matchByName) return matchByName.id;
    }
    
    return null;
};

const matchCentralUsers = () => {
    if (!selectedCentralDeptId.value) return;
    
    isMatching.value = true;
    const dept = centralDepartments.value.find(d => d.id === selectedCentralDeptId.value);
    
    if (dept) {
        form.manager_ids = [];
        form.assistant_manager_ids = [];
        form.directorate_id = '';

        if (dept.managers && dept.managers.length > 0) {
            const mIds = dept.managers.map(m => findUserMatch(m)).filter(id => id !== null);
            form.manager_ids = mIds;
        }
        
        if (dept.assistant_managers && dept.assistant_managers.length > 0) {
            const aIds = dept.assistant_managers.map(m => findUserMatch(m)).filter(id => id !== null);
            form.assistant_manager_ids = aIds;
        }

        if (dept.director) {
            const directorUserId = findUserMatch(dept.director);
            if (directorUserId) {
                const matchingDir = props.directorates.find(d => d.director_id === directorUserId);
                if (matchingDir) {
                    form.directorate_id = matchingDir.id;
                }
            }
        }
        
        form.name = dept.name;
    }
    
    setTimeout(() => { isMatching.value = false; }, 500);
};

const onCentralDeptChangeEdit = () => {
    if (selectedCentralDeptIdEdit.value) {
        const dept = centralDepartments.value.find(d => d.id === selectedCentralDeptIdEdit.value);
        if (dept) {
            editForm.name = dept.name;
        }
    }
};

const matchCentralUsersEdit = () => {
    if (!selectedCentralDeptIdEdit.value) return;
    
    isMatchingEdit.value = true;
    const dept = centralDepartments.value.find(d => d.id === selectedCentralDeptIdEdit.value);
    
    if (dept) {
        editForm.manager_ids = [];
        editForm.assistant_manager_ids = [];
        editForm.directorate_id = '';

        if (dept.managers && dept.managers.length > 0) {
            const mIds = dept.managers.map(m => findUserMatch(m)).filter(id => id !== null);
            editForm.manager_ids = mIds;
        }
        
        if (dept.assistant_managers && dept.assistant_managers.length > 0) {
            const aIds = dept.assistant_managers.map(m => findUserMatch(m)).filter(id => id !== null);
            editForm.assistant_manager_ids = aIds;
        }

        if (dept.director) {
            const directorUserId = findUserMatch(dept.director);
            if (directorUserId) {
                const matchingDir = props.directorates.find(d => d.director_id === directorUserId);
                if (matchingDir) {
                    editForm.directorate_id = matchingDir.id;
                }
            }
        }
        
        editForm.name = dept.name;
    }
    
    setTimeout(() => { isMatchingEdit.value = false; }, 500);
};

const confirmSync = () => {
    isSyncing.value = true;
    router.post(route('admin.departments.sync'), {}, {
        preserveScroll: true,
        onFinish: () => {
            isSyncing.value = false;
            showSyncModal.value = false;
        }
    });
};

const parseManagerInfo = (info) => {
    if (!info) return null;
    try {
        const parsed = JSON.parse(info);
        return parsed;
    } catch (e) {
        // Fallback for old plain text
        return { plain: info };
    }
};
</script>

<template>
    <Head title="Departmanlar" />

    <AuthenticatedLayout>
        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="flex justify-between items-end mb-8">
                    <div>
                        <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Departman Yönetimi</h1>
                        <p class="mt-2 text-sm text-gray-500 font-medium">Şirket hiyerarşisini, direktörlükleri, müdür ve müdür yardımcılarını yönetin.</p>
                    </div>
                    <div class="flex gap-3">
                        <button @click="showSyncModal = true" :disabled="isSyncing" class="inline-flex items-center px-4 py-2.5 bg-white border-2 border-indigo-100 rounded-xl font-bold text-sm text-indigo-700 shadow-sm hover:bg-indigo-50 hover:border-indigo-200 transition-all duration-200 focus:outline-none disabled:opacity-50">
                            <svg v-if="isSyncing" class="animate-spin -ml-1 mr-2 h-5 w-5 text-indigo-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            {{ isSyncing ? 'Senkronize Ediliyor...' : 'Merkezden Senkronize Et' }}
                        </button>

                        <button @click="openCreateModal" class="inline-flex items-center px-6 py-2.5 bg-gradient-to-r from-indigo-600 to-blue-600 border border-transparent rounded-xl font-bold text-sm text-white shadow-lg hover:shadow-indigo-500/30 hover:scale-105 transition-all duration-200 focus:outline-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                            </svg>
                            Yeni Departman Ekle
                        </button>
                    </div>
                </div>

                <!-- Flash Messages -->
                <div v-if="$page.props.flash?.success" class="mb-6 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg">
                    <p class="text-sm font-bold text-emerald-800">{{ $page.props.flash.success }}</p>
                </div>
                <div v-if="$page.props.errors?.error" class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">
                    <p class="text-sm font-bold text-red-800">{{ $page.props.errors.error }}</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr class="bg-gray-50 border-b-2 border-gray-100">
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Departman Adı</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Bağlı Olduğu Direktörlük</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Yöneticiler (Müdür / Müdür Yrd)</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Durum</th>
                                    <th scope="col" class="px-6 py-4 text-right text-xs font-extrabold text-gray-500 uppercase tracking-wider">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <tr v-for="dept in departments" :key="dept.id" class="hover:bg-gray-50/80 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-sm">
                                                {{ getInitials(dept.name) }}
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                                    {{ dept.name }}
                                                    <span v-if="dept.is_synced" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-100 text-blue-700 border border-blue-200" title="Merkezden Senkronize Edildi">
                                                        <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                                        Merkezden
                                                    </span>
                                                </div>
                                                <div class="text-xs text-gray-500">ID: #{{ dept.id }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 font-medium" v-if="dept.directorate_id">
                                            {{ directorates.find(d => d.id === dept.directorate_id)?.name }}
                                            <div class="text-xs text-indigo-600 font-semibold mt-0.5" v-if="dept.directorate?.director">
                                                Direktör: {{ dept.directorate.director.name }}
                                            </div>
                                            <div class="text-xs text-gray-500 mt-0.5" v-else-if="dept.director_info">
                                                Direktör: {{ dept.director_info }}
                                            </div>
                                        </div>
                                        <span v-else class="text-xs text-gray-400 italic">Ana Departman</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900" v-if="(dept.managers && dept.managers.length) || ((dept.assistantManagers && dept.assistantManagers.length) || (dept.assistant_managers && dept.assistant_managers.length)) || dept.manager_info">
                                            
                                            <!-- Normal Managers -->
                                            <template v-if="dept.managers && dept.managers.length > 0">
                                                <div class="font-semibold text-indigo-700 text-xs mb-1">
                                                    Müdür: <span v-for="(m, i) in dept.managers" :key="m.id">{{ m.name }}{{ i < dept.managers.length - 1 ? ', ' : '' }}</span>
                                                </div>
                                            </template>
                                            <!-- Synced Managers (JSON fallback) -->
                                            <template v-else-if="dept.manager_info && parseManagerInfo(dept.manager_info)">
                                                <div v-if="parseManagerInfo(dept.manager_info).managers?.length > 0" class="font-semibold text-indigo-700 text-xs mb-1">
                                                    Müdür: <span>{{ parseManagerInfo(dept.manager_info).managers.join(', ') }}</span>
                                                </div>
                                                <div v-else-if="parseManagerInfo(dept.manager_info).plain" class="font-semibold text-indigo-700 text-xs mb-1">
                                                    Yönetici: {{ parseManagerInfo(dept.manager_info).plain }}
                                                </div>
                                            </template>
                                            
                                            <!-- Normal Assistant Managers -->
                                            <template v-if="(dept.assistantManagers && dept.assistantManagers.length > 0) || (dept.assistant_managers && dept.assistant_managers.length > 0)">
                                                <div class="text-xs font-semibold text-emerald-600 mt-1 bg-emerald-50 border border-emerald-100 inline-block px-2 py-0.5 rounded shadow-sm">
                                                    Müdür Yrd: <span v-for="(a, i) in (dept.assistantManagers || dept.assistant_managers)" :key="a.id">{{ a.name }}{{ i < (dept.assistantManagers || dept.assistant_managers).length - 1 ? ', ' : '' }}</span>
                                                </div>
                                            </template>
                                            <!-- Synced Assistant Managers (JSON fallback) -->
                                            <template v-else-if="dept.manager_info && parseManagerInfo(dept.manager_info)?.assistant_managers?.length > 0">
                                                <div class="text-xs font-semibold text-emerald-600 mt-1 bg-emerald-50 border border-emerald-100 inline-block px-2 py-0.5 rounded shadow-sm">
                                                    Müdür Yrd: <span>{{ parseManagerInfo(dept.manager_info).assistant_managers.join(', ') }}</span>
                                                </div>
                                            </template>
                                            
                                        </div>
                                        <span v-else class="text-xs text-gray-400 italic">Atanmamış</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span v-if="dept.is_active" class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-100 text-emerald-800">
                                            <svg class="mr-1.5 h-2 w-2 text-emerald-500" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3" /></svg>
                                            Aktif
                                        </span>
                                        <span v-else class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-gray-100 text-gray-800">
                                            Pasif
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end gap-2">
                                            <button @click="openEditModal(dept)" class="p-2 text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors" title="Düzenle">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                            </button>
                                            <button @click="deleteDepartment(dept.id)" class="p-2 text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-colors" title="Sil">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="departments.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500 font-medium">
                                        Henüz hiç departman eklenmemiş.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- CREATE MODAL -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg transform transition-all my-8">
                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-xl font-extrabold text-gray-900">Yeni Departman Ekle</h3>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-500 focus:outline-none transition-colors">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="p-6">
                    <div class="bg-indigo-50 rounded-xl p-4 mb-6 border border-indigo-100">
                        <h4 class="text-xs font-bold text-indigo-800 uppercase tracking-wide mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            Merkezi Sistemden Eşleştir (Opsiyonel)
                        </h4>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <div class="flex-grow relative">
                                <SearchableSelect 
                                    v-model="selectedCentralDeptId" 
                                    :options="centralDepartments" 
                                    labelKey="name" 
                                    valueKey="id" 
                                    placeholder="Merkezi Bölüm Seç..." 
                                    @change="onCentralDeptChange"
                                    :multiple="false"
                                />
                            </div>
                            <button @click="matchCentralUsers" :disabled="!selectedCentralDeptId || isMatching" type="button" class="whitespace-nowrap px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-semibold hover:bg-indigo-700 transition disabled:opacity-50">
                                {{ isMatching ? 'Eşleştiriliyor...' : 'Yöneticileri Çek' }}
                            </button>
                        </div>
                    </div>

                    <form @submit.prevent="submitCreate" class="space-y-5">
                        <div v-if="existingDepartment" class="p-4 bg-amber-50 border border-amber-200 rounded-xl flex items-start gap-3 text-amber-800">
                            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            <div>
                                <p class="text-sm font-bold">Mevcut Bölüm</p>
                                <p class="text-xs mt-0.5">Bu departman Workflow uygulamanızda zaten kayıtlı. Devam ederseniz yeni bir kayıt açılmaz, mevcut bölüm güncellenir.</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Departman Adı <span class="text-red-500">*</span></label>
                            <input v-model="form.name" type="text" required class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Bağlı Olduğu Direktörlük</label>
                            <SearchableSelect 
                                v-model="form.directorate_id" 
                                :options="directorates" 
                                labelKey="name" 
                                valueKey="id" 
                                placeholder="Direktörlük Ara veya Seç..." 
                                :multiple="false"
                            />
                            <p v-if="form.errors.directorate_id" class="mt-1 text-xs text-red-600">{{ form.errors.directorate_id }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Müdür(ler)</label>
                            <SearchableSelect 
                                v-model="form.manager_ids" 
                                :options="users" 
                                labelKey="name" 
                                valueKey="id" 
                                placeholder="Müdür Ekle..." 
                                :multiple="true"
                            />
                            <p v-if="form.errors.manager_ids" class="mt-1 text-xs text-red-600">{{ form.errors.manager_ids }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Müdür Yardımcısı (ları)</label>
                            <SearchableSelect 
                                v-model="form.assistant_manager_ids" 
                                :options="users" 
                                labelKey="name" 
                                valueKey="id" 
                                placeholder="Müdür Yardımcısı Ekle..." 
                                :multiple="true"
                            />
                            <p v-if="form.errors.assistant_manager_ids" class="mt-1 text-xs text-red-600">{{ form.errors.assistant_manager_ids }}</p>
                        </div>
                        <div class="pt-4 flex justify-end gap-3 border-t border-gray-100">
                            <button type="button" @click="showCreateModal = false" class="px-5 py-2.5 rounded-xl font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 transition-colors">İptal</button>
                            <button type="submit" :disabled="form.processing" :class="[existingDepartment ? 'bg-amber-500 hover:bg-amber-600' : 'bg-indigo-600 hover:bg-indigo-700', 'px-5 py-2.5 rounded-xl font-semibold text-white shadow-md transition-colors disabled:opacity-50']">
                                <span v-if="form.processing">{{ existingDepartment ? 'Güncelleniyor...' : 'Kaydediliyor...' }}</span>
                                <span v-else>{{ existingDepartment ? 'Güncelle' : 'Kaydet' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- EDIT MODAL -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm overflow-y-auto">
            <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl p-6 relative transform transition-all my-8" @click.stop>
                <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-4">
                    <h3 class="text-xl font-extrabold text-gray-900">Departman Düzenle</h3>
                    <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-600 bg-gray-100 rounded-lg p-1.5 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="bg-indigo-50 rounded-xl p-4 mb-6 border border-indigo-100">
                    <h4 class="text-xs font-bold text-indigo-800 uppercase tracking-wide mb-3 flex items-center">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        Merkezi Sistemden Eşleştir (Opsiyonel)
                    </h4>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="flex-grow relative">
                            <SearchableSelect 
                                v-model="selectedCentralDeptIdEdit" 
                                :options="centralDepartments" 
                                labelKey="name" 
                                valueKey="id" 
                                placeholder="Merkezi Bölüm Seç..." 
                                @change="onCentralDeptChangeEdit"
                                :multiple="false"
                            />
                        </div>
                        <button @click="matchCentralUsersEdit" :disabled="!selectedCentralDeptIdEdit || isMatchingEdit" type="button" class="whitespace-nowrap px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-semibold hover:bg-indigo-700 transition disabled:opacity-50">
                            {{ isMatchingEdit ? 'Eşleştiriliyor...' : 'Yöneticileri Çek' }}
                        </button>
                    </div>
                </div>

                <form @submit.prevent="submitEdit" class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Departman Adı <span class="text-red-500">*</span></label>
                        <input v-model="editForm.name" type="text" required class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <p v-if="editForm.errors.name" class="mt-1 text-xs text-red-600">{{ editForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Bağlı Olduğu Direktörlük</label>
                        <SearchableSelect 
                            v-model="editForm.directorate_id" 
                            :options="directorates" 
                            labelKey="name" 
                            valueKey="id" 
                            placeholder="Direktörlük Ara veya Seç..." 
                            :multiple="false"
                        />
                        <p v-if="editForm.errors.directorate_id" class="mt-1 text-xs text-red-600">{{ editForm.errors.directorate_id }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Müdür(ler)</label>
                        <SearchableSelect 
                            v-model="editForm.manager_ids" 
                            :options="users" 
                            labelKey="name" 
                            valueKey="id" 
                            placeholder="Müdür Ekle..." 
                            :multiple="true"
                        />
                        <p v-if="editForm.errors.manager_ids" class="mt-1 text-xs text-red-600">{{ editForm.errors.manager_ids }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Müdür Yardımcısı (ları)</label>
                        <SearchableSelect 
                            v-model="editForm.assistant_manager_ids" 
                            :options="users" 
                            labelKey="name" 
                            valueKey="id" 
                            placeholder="Müdür Yardımcısı Ekle..." 
                            :multiple="true"
                        />
                        <p v-if="editForm.errors.assistant_manager_ids" class="mt-1 text-xs text-red-600">{{ editForm.errors.assistant_manager_ids }}</p>
                    </div>
                    <div class="pt-4 flex justify-end gap-3 border-t border-gray-100">
                        <button type="button" @click="showEditModal = false" class="px-5 py-2.5 rounded-xl font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 transition-colors">İptal</button>
                        <button type="submit" :disabled="editForm.processing" class="px-5 py-2.5 rounded-xl font-semibold text-white bg-indigo-600 hover:bg-indigo-700 shadow-md transition-colors disabled:opacity-50">
                            <span v-if="editForm.processing">Güncelleniyor...</span>
                            <span v-else>Güncelle</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- SYNC MODAL -->
        <div v-if="showSyncModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md transform transition-all my-8">
                <div class="bg-indigo-600 px-6 py-4 flex items-center rounded-t-2xl">
                    <div class="bg-white/20 rounded-full p-2 mr-3 text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white">Merkezden Senkronize Et</h3>
                        <p class="text-indigo-100 text-xs">Departmanları içe aktarma işlemi</p>
                    </div>
                </div>
                
                <div class="p-6">
                    <p class="text-gray-700 text-sm font-medium mb-4">
                        Merkezi sistemde bulunan <strong>{{ centralDepartments.length }}</strong> departman ve hiyerarşi bilgisi Workflow veritabanına aktarılacaktır. <br><br>Eğer daha önce aktarılmış departmanlar varsa bilgileri güncellenecektir. Yeni departmanlar eklenecektir. İşlem veritabanı boyutuna göre kısa bir süre alabilir.
                    </p>

                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
                        <button type="button" @click="showSyncModal = false" class="px-4 py-2 bg-white border border-gray-300 rounded-xl font-bold text-gray-700 hover:bg-gray-50 transition-colors">Vazgeç</button>
                        <button type="button" @click="confirmSync" :disabled="isSyncing" class="px-4 py-2 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 transition-colors shadow-md flex items-center disabled:opacity-50">
                            <svg v-if="isSyncing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            {{ isSyncing ? 'Senkronize Ediliyor...' : 'Senkronizasyonu Başlat' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
