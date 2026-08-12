<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    roles: Array
});

const form = useForm({});

const showWarningModal = ref(false);
const roleToDelete = ref(null);
const usageStats = ref(null);
const isLoadingUsage = ref(false);

const initiateDelete = async (role) => {
    roleToDelete.value = role;
    showWarningModal.value = true;
    isLoadingUsage.value = true;
    usageStats.value = null;
    
    try {
        const response = await axios.get(route('admin.roles.check-usage', role.id));
        if (response.data && response.data.success) {
            usageStats.value = response.data.data;
        }
    } catch (error) {
        console.error('Kullanım istatistikleri alınamadı:', error);
    } finally {
        isLoadingUsage.value = false;
    }
};

const confirmDelete = () => {
    if (roleToDelete.value) {
        form.delete(route('admin.roles.destroy', roleToDelete.value.id), {
            onSuccess: () => {
                showWarningModal.value = false;
                roleToDelete.value = null;
            }
        });
    }
};

</script>

<template>
    <Head title="Rol Yönetimi" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-semibold text-2xl text-gray-800 leading-tight">Yetki Rolleri Yönetimi</h2>
                    <p class="text-sm text-gray-500 mt-1">Kullanıcı yetkilerini ve erişim izinlerini dilediğiniz gibi yönetin.</p>
                </div>
                <Link :href="route('admin.roles.create')" class="inline-flex items-center bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-5 rounded-xl shadow-md shadow-indigo-200 transition-all text-sm focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Yeni Rol Oluştur
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-gray-100">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50/80">
                            <tr>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider w-16">ID</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Rol Adı</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Yetki Sayısı</th>
                                <th scope="col" class="px-6 py-4 text-right text-xs font-extrabold text-gray-500 uppercase tracking-wider">İşlem</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <tr v-for="role in roles" :key="role.id" class="hover:bg-indigo-50/30 transition-colors group">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400 font-mono">#{{ role.id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-lg bg-indigo-100 text-indigo-600">
                                            <i class="fas fa-user-tag text-lg"></i>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-extrabold text-gray-900">{{ role.name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-full bg-gray-200 rounded-full h-2.5 max-w-[100px] mr-2">
                                            <div class="bg-indigo-600 h-2.5 rounded-full" :style="{ width: Math.min((role.permissions_count / 15) * 100, 100) + '%' }"></div>
                                        </div>
                                        <span class="text-xs font-bold text-gray-700">{{ role.permissions_count || 0 }} Yetki</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <Link :href="route('admin.roles.edit', role.id)" class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none transition-colors shadow-sm">
                                            <i class="fas fa-pen mr-1.5 text-gray-400"></i> Düzenle
                                        </Link>
                                        <button @click="initiateDelete(role)" class="inline-flex items-center px-3 py-1.5 bg-red-50 border border-red-200 rounded-lg text-xs font-bold text-red-700 hover:bg-red-100 focus:outline-none transition-colors shadow-sm">
                                            <i class="fas fa-trash-alt mr-1.5"></i> Sil
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="roles.length === 0">
                                <td colspan="4" class="px-6 py-12 whitespace-nowrap text-sm text-gray-500 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="h-16 w-16 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center mb-4">
                                            <i class="fas fa-users-slash text-2xl"></i>
                                        </div>
                                        <p class="font-bold text-gray-600">Hiç rol bulunamadı</p>
                                        <p class="text-xs text-gray-400 mt-1">Henüz sisteme bir rol tanımlanmamış. Yeni bir rol oluşturarak başlayın.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Warning Modal for Deletion -->
        <div v-if="showWarningModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl p-0 overflow-hidden transform transition-all">
                <div class="bg-red-600 px-6 py-4 flex items-center">
                    <div class="bg-white/20 rounded-full p-2 mr-3">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white">Rol Silme Onayı</h3>
                        <p class="text-red-100 text-xs">Bu işlem geri alınamaz ve kritik sonuçlar doğurabilir!</p>
                    </div>
                </div>
                
                <div class="p-6">
                    <p class="text-gray-800 text-sm font-semibold mb-4">
                        <strong class="text-red-600 text-lg">{{ roleToDelete?.name }}</strong> adlı rolü silmek üzeresiniz.
                    </p>

                    <div v-if="isLoadingUsage" class="flex flex-col items-center justify-center py-6 text-gray-500">
                        <svg class="animate-spin h-8 w-8 text-indigo-500 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span class="text-sm font-bold">Kullanım istatistikleri hesaplanıyor...</span>
                    </div>

                    <div v-else-if="usageStats" class="space-y-3 bg-gray-50 p-4 rounded-xl border border-gray-100 mb-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 font-semibold"><i class="fas fa-users w-5 text-indigo-400"></i> Sahip Olan Kullanıcılar</span>
                            <span class="px-2 py-1 bg-white border border-gray-200 rounded font-bold" :class="usageStats.userCount > 0 ? 'text-red-600' : 'text-gray-500'">{{ usageStats.userCount }} Kişi</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 font-semibold"><i class="fas fa-project-diagram w-5 text-indigo-400"></i> Kullanılan Şablonlar</span>
                            <span class="px-2 py-1 bg-white border border-gray-200 rounded font-bold" :class="usageStats.workflowCount > 0 ? 'text-red-600' : 'text-gray-500'">{{ usageStats.workflowCount }} Şablon</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 font-semibold"><i class="fas fa-tasks w-5 text-indigo-400"></i> Bekleyen Görevler</span>
                            <span class="px-2 py-1 bg-white border border-gray-200 rounded font-bold" :class="usageStats.taskCount > 0 ? 'text-red-600' : 'text-gray-500'">{{ usageStats.taskCount }} Görev</span>
                        </div>
                    </div>

                    <div v-if="usageStats && (usageStats.userCount > 0 || usageStats.workflowCount > 0 || usageStats.taskCount > 0)" class="bg-red-50 border border-red-100 p-3 rounded-lg text-xs text-red-700 font-medium mb-4">
                        <i class="fas fa-exclamation-triangle mr-1"></i> <strong>Dikkat:</strong> Bu rolü sildiğinizde yukarıdaki personeller yetkilerini kaybedecek ve bağlı olan şablonlar ile bekleyen görevler sahipsiz kalacaktır.
                    </div>
                    <p class="text-sm text-gray-600 mb-2">Tüm bunlara rağmen silme işlemine devam etmek istiyor musunuz?</p>
                </div>
                
                <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3 border-t border-gray-100">
                    <button type="button" @click="showWarningModal = false" class="px-4 py-2 bg-white border border-gray-300 rounded-xl font-bold text-gray-700 hover:bg-gray-50 transition-colors">Vazgeç</button>
                    <button type="button" @click="confirmDelete" :disabled="form.processing" class="px-4 py-2 bg-red-600 text-white rounded-xl font-bold hover:bg-red-700 transition-colors shadow-md flex items-center disabled:opacity-50">
                        <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Yine de Sil
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
