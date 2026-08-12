<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch, onMounted } from 'vue';
import debounce from 'lodash.debounce';
import axios from 'axios';

const props = defineProps({
    users: Object,
    departments: Array,
    roles: Array,
    filters: Object
});

const search = ref(props.filters?.search || '');
const department_ids = ref(props.filters?.department_ids || []);
const role_ids = ref(props.filters?.role_ids || []);
const activeTab = ref(props.filters?.tab || 'users');

const executeFilter = debounce(function () {
    router.get(route('admin.users.index'), {
        search: search.value,
        department_ids: department_ids.value,
        role_ids: role_ids.value,
        tab: activeTab.value
    }, {
        preserveState: true,
        replace: true
    });
}, 300);

watch(search, executeFilter);
watch(department_ids, executeFilter, { deep: true });
watch(role_ids, executeFilter, { deep: true });
watch(activeTab, executeFilter);

const isSyncingAll = ref(false);
const showSyncModal = ref(false);
const syncPreviewData = ref([]);
const selectedUsersToSync = ref([]);
const selectAllSync = ref(false);
const isApplyingSync = ref(false);

const previewAllSync = async () => {
    isSyncingAll.value = true;
    try {
        const response = await axios.get(route('admin.users.sync-all-preview'));
        if (response.data && response.data.success) {
            syncPreviewData.value = response.data.users;
            selectedUsersToSync.value = syncPreviewData.value.map(u => u.user_id);
            selectAllSync.value = true;
            showSyncModal.value = true;
        } else {
            alert(response.data.error || 'Merkezi sistemle iletişim kurulamadı.');
        }
    } catch (e) {
        console.error(e);
        alert('Bir hata oluştu.');
    } finally {
        isSyncingAll.value = false;
    }
};

watch(selectAllSync, (val) => {
    if (val) {
        selectedUsersToSync.value = syncPreviewData.value.map(u => u.user_id);
    } else if (selectedUsersToSync.value.length === syncPreviewData.value.length) {
        selectedUsersToSync.value = [];
    }
});

watch(selectedUsersToSync, (val) => {
    if (val.length === syncPreviewData.value.length && syncPreviewData.value.length > 0) {
        selectAllSync.value = true;
    } else {
        selectAllSync.value = false;
    }
});

const applyAllSync = () => {
    if (selectedUsersToSync.value.length === 0) {
        alert('Lütfen güncellenecek en az bir kullanıcı seçin.');
        return;
    }

    const updatesToApply = syncPreviewData.value
        .filter(u => selectedUsersToSync.value.includes(u.user_id))
        .map(u => ({
            user_id: u.user_id,
            name: u.name,
            email: u.email,
            changes: u.changes
        }));

    isApplyingSync.value = true;
    router.post(route('admin.users.sync-all-apply'), {
        updates: updatesToApply
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showSyncModal.value = false;
            syncPreviewData.value = [];
            selectedUsersToSync.value = [];
        },
        onFinish: () => {
            isApplyingSync.value = false;
        }
    });
};

const form = useForm({});

const deleteUser = (id) => {
    if (confirm('Bu kullanıcıyı sistemden tamamen silmek istediğinize emin misiniz?')) {
        form.delete(route('admin.users.destroy', id));
    }
};
</script>

<template>
    <Head title="Kullanıcı Havuzu" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Personel ve Kullanıcı Havuzu</h2>
                <button @click="previewAllSync" :disabled="isSyncingAll" class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-lg font-bold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 focus:outline-none transition-colors disabled:opacity-50 shadow-md">
                    <svg v-if="isSyncingAll" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <svg v-else class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    {{ isSyncingAll ? 'Toplu Senkronize Ediliyor...' : 'Tümünü Merkezden Senkronize Et' }}
                </button>
            </div>
            
            <!-- Tabs -->
            <div class="mt-6 border-b border-gray-200">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <button 
                        @click="activeTab = 'users'"
                        :class="[activeTab === 'users' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors flex items-center']"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        Kullanıcılar
                    </button>
                    <button 
                        @click="activeTab = 'customers'"
                        :class="[activeTab === 'customers' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors flex items-center']"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        Müşteriler
                    </button>
                    <button 
                        @click="activeTab = 'maviyaka'"
                        :class="[activeTab === 'maviyaka' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors flex items-center']"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        Mavi Yaka
                    </button>
                </nav>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 bg-gray-50 p-4 rounded-xl border border-gray-100">
                            <div class="relative">
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Arama</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    </div>
                                    <input v-model="search" type="text" placeholder="İsim, E-posta veya TC..." class="pl-10 w-full h-[38px] border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm sm:text-sm">
                                </div>
                            </div>
                            <div class="relative">
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Departmana Göre Filtrele</label>
                                <SearchableSelect 
                                    v-model="department_ids" 
                                    :options="departments" 
                                    labelKey="name" 
                                    valueKey="id" 
                                    placeholder="Departman(lar) Seçin..." 
                                    :multiple="true"
                                />
                            </div>
                            <div class="relative">
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Role Göre Filtrele</label>
                                <SearchableSelect 
                                    v-model="role_ids" 
                                    :options="roles" 
                                    labelKey="name" 
                                    valueKey="id" 
                                    placeholder="Rol(ler) Seçin..." 
                                    :multiple="true"
                                />
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 mt-4">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kullanıcı</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Departman / Unvan</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roller</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-bold text-gray-900">{{ user.name }}</div>
                                            <div class="text-xs text-gray-500">{{ user.email }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-bold text-indigo-700">{{ user.department?.name || 'Departman Yok' }}</div>
                                            <div class="text-xs text-gray-500">{{ user.title || 'Unvan Yok' }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span v-for="role in user.roles" :key="role.id" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 mr-1">
                                                {{ role.name }}
                                            </span>
                                            <span v-if="user.roles.length === 0" class="text-xs text-gray-400">Rol Yok</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex items-center justify-end space-x-3">
                                                <Link :href="route('admin.users.edit', user.id)" class="inline-flex items-center px-3 py-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white rounded-lg transition-colors border border-indigo-100 hover:border-transparent font-semibold shadow-sm">
                                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                    Düzenle / Rol Ata
                                                </Link>
                                                <button @click="deleteUser(user.id)" class="inline-flex items-center px-3 py-1.5 bg-red-50 text-red-700 hover:bg-red-600 hover:text-white rounded-lg transition-colors border border-red-100 hover:border-transparent font-semibold shadow-sm">
                                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    Sil
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="users.data.length === 0">
                                        <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">Hiç kullanıcı bulunamadı.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-6 flex justify-center">
                            <template v-for="(link, key) in users.links" :key="key">
                                <Link 
                                    v-if="link.url"
                                    :href="link.url" 
                                    v-html="link.label"
                                    class="px-4 py-2 border text-sm font-medium"
                                    :class="link.active ? 'bg-indigo-50 border-indigo-500 text-indigo-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'"
                                />
                                <span 
                                    v-else
                                    v-html="link.label"
                                    class="px-4 py-2 border text-sm font-medium bg-gray-100 border-gray-300 text-gray-400 cursor-not-allowed"
                                ></span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sync Preview Modal -->
        <div v-if="showSyncModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm overflow-y-auto">
            <div class="bg-white rounded-2xl w-full max-w-5xl shadow-2xl p-0 overflow-hidden transform transition-all my-8 flex flex-col max-h-[90vh]">
                <div class="bg-indigo-600 px-6 py-4 flex items-center justify-between flex-shrink-0">
                    <h3 class="text-lg font-extrabold text-white flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        Toplu Senkronizasyon Onay Tablosu
                    </h3>
                    <button @click="showSyncModal = false" class="text-indigo-100 hover:text-white transition-colors">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                
                <div class="p-6 overflow-y-auto flex-1">
                    <p class="text-sm text-gray-600 mb-4">Merkezi sistemden alınan verilere göre, aşağıdaki kullanıcıların profilinde değişiklik tespit edilmiştir. Güncellenmesini istediklerinizi seçip onaylayın.</p>
                    
                    <div v-if="syncPreviewData.length > 0" class="border border-gray-200 rounded-xl overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left w-12">
                                        <input type="checkbox" v-model="selectAllSync" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Kullanıcı</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Değişiklikler (Eski &rarr; Yeni)</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="user in syncPreviewData" :key="user.user_id" class="hover:bg-indigo-50/30 transition-colors" :class="{'bg-indigo-50/50': selectedUsersToSync.includes(user.user_id)}">
                                    <td class="px-6 py-4 whitespace-nowrap text-left">
                                        <input type="checkbox" :value="user.user_id" v-model="selectedUsersToSync" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-bold text-gray-900">{{ user.name }}</div>
                                        <div class="text-xs text-gray-500">{{ user.email }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="space-y-2">
                                            <div v-for="(change, field) in user.changes" :key="field" class="flex items-center text-xs">
                                                <span class="font-bold text-gray-700 capitalize w-24 flex-shrink-0">
                                                    {{ field === 'tc_no' ? 'TC No' : field === 'registration_no' ? 'Sicil No' : field === 'title' ? 'Ünvan' : field === 'department_id' ? 'Departman' : field === 'is_customer' ? 'Müşteri Mi?' : field === 'is_mavi_yaka' ? 'Mavi Yaka Mı?' : field }}:
                                                </span>
                                                <span class="text-red-500 line-through mr-2">{{ change.old || '(Boş)' }}</span>
                                                <svg class="w-3 h-3 text-gray-400 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                                <span class="text-emerald-600 font-bold bg-emerald-50 px-1.5 py-0.5 rounded">{{ change.new || '(Boş)' }}</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div v-else class="text-center py-12 bg-gray-50 rounded-xl border border-gray-100">
                        <svg class="w-12 h-12 text-emerald-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <p class="text-gray-900 font-bold">Her şey güncel!</p>
                        <p class="text-gray-500 text-sm mt-1">Sistemdeki tüm kullanıcıların bilgileri Merkezi Sistem ile birebir aynı.</p>
                    </div>
                </div>

                <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 flex justify-between items-center flex-shrink-0">
                    <div class="text-sm font-semibold text-gray-600">
                        <span v-if="syncPreviewData.length > 0">{{ selectedUsersToSync.length }} kullanıcı seçildi</span>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" @click="showSyncModal = false" :disabled="isApplyingSync" class="px-5 py-2 rounded-xl font-semibold text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 transition-colors disabled:opacity-50">İptal</button>
                        <button v-if="syncPreviewData.length > 0" type="button" @click="applyAllSync" :disabled="selectedUsersToSync.length === 0 || isApplyingSync" class="px-5 py-2 rounded-xl font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-md transition-colors disabled:opacity-50 flex items-center">
                            <svg v-if="isApplyingSync" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            {{ isApplyingSync ? 'Güncelleniyor...' : 'Seçilenleri Onayla ve Güncelle' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
