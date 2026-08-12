<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    userModel: Object,
    userRoleNames: Array,
    departments: Array,
    roles: Array,
    managers: Array,
    centralData: Object,
});

const form = useForm({
    roles: props.userRoleNames || [],
});

const submit = () => {
    form.put(route('admin.users.update', props.userModel.id));
};

const isSyncing = ref(false);
const showSyncModal = ref(false);
const syncPreviewData = ref(null);
const syncError = ref('');

const previewSync = async () => {
    isSyncing.value = true;
    syncError.value = '';
    
    try {
        const response = await axios.get(route('admin.users.sync-preview', props.userModel.id));
        if (response.data && response.data.success) {
            syncPreviewData.value = response.data;
            showSyncModal.value = true;
        } else {
            syncError.value = response.data.error || 'Merkezi sistemle iletişim kurulamadı.';
            alert(syncError.value);
        }
    } catch (e) {
        console.error(e);
        syncError.value = 'Bir hata oluştu.';
        alert(syncError.value);
    } finally {
        isSyncing.value = false;
    }
};

const applySync = () => {
    if (!syncPreviewData.value) return;

    router.post(route('admin.users.sync-apply', props.userModel.id), {
        changes: syncPreviewData.value.changes
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showSyncModal.value = false;
        }
    });
};
</script>

<template>
    <Head title="Kullanıcı Düzenle" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link :href="route('admin.users.index')" class="text-gray-500 hover:text-gray-700">&larr; Geri</Link>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight flex items-center">
                        Kullanıcı Düzenle: {{ userModel?.name }}
                        <span v-if="userModel?.is_customer" class="ml-3 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200 shadow-sm flex items-center">
                            <i class="fas fa-building mr-1.5"></i> Müşteri
                        </span>
                        <span v-if="userModel?.is_mavi_yaka" class="ml-3 px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-200 shadow-sm flex items-center">
                            <i class="fas fa-hard-hat mr-1.5"></i> Mavi Yaka
                        </span>
                    </h2>
                </div>
                <div class="flex items-center gap-4">
                    <button @click="previewSync" :disabled="isSyncing" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-bold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none transition-colors disabled:opacity-50">
                        <svg v-if="isSyncing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <svg v-else class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        {{ isSyncing ? 'Senkronize Ediliyor...' : 'Merkezden Senkronize Et' }}
                    </button>
                    <div class="text-sm text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full border border-indigo-200">
                        <i class="fas fa-info-circle mr-1"></i> Kullanıcı bilgileri Merkezi Sistem üzerinden yönetilmektedir.
                    </div>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-xl border border-gray-100">
                    <form @submit.prevent="submit" class="p-8 space-y-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Temel Bilgiler (Sadece Görüntüleme) -->
                            <div class="space-y-5 bg-gray-50/50 p-6 rounded-xl border border-gray-100">
                                <h3 class="font-extrabold text-lg text-gray-900 border-b pb-3">Kişisel Bilgiler <span class="text-xs text-gray-400 font-normal ml-2 tracking-wide uppercase">(Sadece Okunabilir)</span></h3>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="col-span-2">
                                        <label class="block font-bold text-xs text-gray-600 uppercase tracking-wide mb-1">Ad Soyad</label>
                                        <input type="text" :value="userModel?.name" disabled class="block w-full rounded-lg border-gray-200 bg-gray-100 text-gray-600 shadow-sm sm:text-sm">
                                    </div>
    
                                    <div class="col-span-2">
                                        <label class="block font-bold text-xs text-gray-600 uppercase tracking-wide mb-1">E-Posta</label>
                                        <input type="email" :value="userModel?.email" disabled class="block w-full rounded-lg border-gray-200 bg-gray-100 text-gray-600 shadow-sm sm:text-sm">
                                    </div>
                                    
                                    <div>
                                        <label class="block font-bold text-xs text-gray-600 uppercase tracking-wide mb-1">TC Kimlik No</label>
                                        <input type="text" :value="userModel?.tc_no || '-'" disabled class="block w-full rounded-lg border-gray-200 bg-gray-100 text-gray-600 shadow-sm sm:text-sm">
                                    </div>

                                    <div v-if="!userModel?.is_customer">
                                        <label class="block font-bold text-xs text-gray-600 uppercase tracking-wide mb-1">Sicil No</label>
                                        <input type="text" :value="userModel?.registration_no || '-'" disabled class="block w-full rounded-lg border-gray-200 bg-gray-100 text-gray-600 shadow-sm sm:text-sm">
                                    </div>
                                    
                                    <div class="col-span-2" v-if="!userModel?.is_customer">
                                        <label class="block font-bold text-xs text-gray-600 uppercase tracking-wide mb-1">Unvan</label>
                                        <input type="text" :value="userModel?.title || '-'" disabled class="block w-full rounded-lg border-gray-200 bg-gray-100 text-gray-600 shadow-sm sm:text-sm">
                                    </div>
    
                                    <div class="col-span-2" v-if="!userModel?.is_customer">
                                        <label class="block font-bold text-xs text-gray-600 uppercase tracking-wide mb-1">Departman / Bölüm</label>
                                        <input type="text" :value="departments.find(d => d.id === userModel?.department_id)?.name || '-'" disabled class="block w-full rounded-lg border-gray-200 bg-gray-100 text-gray-600 shadow-sm sm:text-sm">
                                    </div>

                                    <div class="col-span-2" v-if="userModel?.is_customer">
                                        <label class="block font-bold text-xs text-gray-600 uppercase tracking-wide mb-1">Bağlı Olduğu Şirket / Firma</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-building text-gray-400"></i>
                                            </div>
                                            <input type="text" :value="centralData?.company?.name || 'Firma Bilgisi Bekleniyor...'" disabled class="block w-full pl-10 rounded-lg border-gray-200 bg-amber-50 text-amber-900 font-semibold shadow-sm sm:text-sm border-amber-200">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Yetki Yönetimi (Düzenlenebilir) -->
                            <div class="space-y-4">
                                <h3 class="font-extrabold text-lg text-indigo-700 border-b border-indigo-100 pb-3">Uygulama Yetkileri</h3>

                                <div>
                                    <label class="block font-bold text-xs text-gray-700 uppercase tracking-wide mb-1">Yetki Rolleri</label>
                                    <p class="text-sm text-gray-500 mb-4">Bu kullanıcının İş Süreçleri sistemindeki yetkilerini belirleyin.</p>
                                    
                                    <div class="bg-white p-4 rounded-xl border border-gray-200 h-[320px] overflow-y-auto shadow-inner">
                                        <div v-for="role in roles" :key="role.id" class="flex items-center mb-2 p-3 hover:bg-indigo-50 rounded-lg transition-colors border border-transparent hover:border-indigo-100">
                                            <input type="checkbox" :id="'role_'+role.id" :value="role.name" v-model="form.roles" class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            <label :for="'role_'+role.id" class="ml-3 text-sm font-bold text-gray-700 cursor-pointer w-full">{{ role.name }}</label>
                                        </div>
                                        <div v-if="roles.length === 0" class="text-sm text-gray-500 text-center py-8">Sistemde hiç rol yok. Önce rol oluşturun.</div>
                                    </div>
                                    <div v-if="form.errors.roles" class="text-red-500 text-xs mt-2 font-semibold">{{ form.errors.roles }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-8 border-t border-gray-100 pt-6">
                            <button type="submit" :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-xl shadow-lg shadow-indigo-200 transition-all focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 text-sm tracking-wide">
                                <span v-if="form.processing">Kaydediliyor...</span>
                                <span v-else>Kaydet</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sync Preview Modal -->
        <div v-if="showSyncModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm overflow-y-auto">
            <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl p-0 overflow-hidden transform transition-all my-8">
                <div class="bg-indigo-600 px-6 py-4 flex items-center justify-between">
                    <h3 class="text-lg font-extrabold text-white flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        Senkronizasyon Raporu
                    </h3>
                    <button @click="showSyncModal = false" class="text-indigo-100 hover:text-white transition-colors">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                
                <div class="p-6">
                    <p class="text-sm text-gray-600 mb-6">Merkezi sistemden alınan verilere göre kullanıcının profilinde yapılacak değişiklikler aşağıda listelenmiştir. Lütfen inceleyip onaylayın.</p>
                    
                    <div v-if="Object.keys(syncPreviewData?.changes || {}).length > 0" class="space-y-3">
                        <div v-for="(change, field) in syncPreviewData.changes" :key="field" class="bg-gray-50 border border-gray-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="font-bold text-sm text-gray-700 capitalize w-1/4">
                                {{ field === 'tc_no' ? 'TC Kimlik No' : field === 'registration_no' ? 'Sicil No' : field === 'title' ? 'Ünvan' : field === 'department_id' ? 'Departman' : field === 'is_customer' ? 'Müşteri Mi?' : field === 'is_mavi_yaka' ? 'Mavi Yaka Mı?' : field }}
                            </div>
                            <div class="flex-1 flex items-center gap-3">
                                <div class="flex-1 bg-red-50 text-red-700 border border-red-100 p-2 rounded-lg text-sm text-center line-through opacity-70">
                                    {{ change.old || '(Boş)' }}
                                </div>
                                <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                <div class="flex-1 bg-emerald-50 text-emerald-700 border border-emerald-100 p-2 rounded-lg text-sm font-semibold text-center shadow-sm">
                                    {{ change.new || '(Boş)' }}
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-else class="text-center py-12 bg-gray-50 rounded-xl border border-gray-100">
                        <svg class="w-12 h-12 text-emerald-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <p class="text-gray-900 font-bold">Her şey güncel!</p>
                        <p class="text-gray-500 text-sm mt-1">Kullanıcının yerel verileri Merkezi Sistem ile birebir aynı.</p>
                    </div>
                </div>

                <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button" @click="showSyncModal = false" class="px-5 py-2 rounded-xl font-semibold text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 transition-colors">İptal</button>
                    <button v-if="Object.keys(syncPreviewData?.changes || {}).length > 0" type="button" @click="applySync" class="px-5 py-2 rounded-xl font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-md transition-colors">
                        Onayla ve Güncelle
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
