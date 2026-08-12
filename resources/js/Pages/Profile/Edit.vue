<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    centralSsoUrl: {
        type: String,
        default: 'http://localhost:8001'
    }
});

const user = usePage().props.auth.user;

const getInitials = (name) => {
    return name
        .split(' ')
        .map(word => word[0])
        .join('')
        .substring(0, 2)
        .toUpperCase();
};
</script>

<template>
    <Head title="Profilim" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Profilim
            </h2>
        </template>

        <div class="py-12 bg-gray-50 min-h-[calc(100vh-130px)]">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100">
                    <!-- Üst Arka Plan (Banner) -->
                    <div class="h-32 bg-gradient-to-r from-indigo-500 to-purple-600"></div>
                    
                    <div class="px-4 sm:px-6 lg:px-8 pb-8">
                        <div class="relative flex justify-between items-end -mt-16 mb-8">
                            <!-- Avatar -->
                            <div class="h-32 w-32 rounded-full border-4 border-white bg-indigo-100 flex items-center justify-center text-4xl font-bold text-indigo-700 shadow-md">
                                {{ getInitials(user.name) }}
                            </div>
                            
                            <!-- Hızlı Aksiyon Butonu -->
                            <div class="flex space-x-3 mb-2">
                                <a 
                                    :href="`${centralSsoUrl}/profile`" 
                                    target="_blank"
                                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                    Profili Düzenle
                                </a>
                            </div>
                        </div>

                        <!-- Kullanıcı Bilgileri -->
                        <div class="mb-10">
                            <h1 class="text-3xl font-bold text-gray-900 mb-1">{{ user.name }}</h1>
                            <p class="text-gray-500 font-medium flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                {{ user.email }}
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            
                            <!-- Bilgi Kartı -->
                            <div class="bg-gray-50 rounded-xl p-6 border border-gray-100">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Kurumsal Bilgiler</h3>
                                
                                <dl class="space-y-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Departman</dt>
                                        <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ user.department?.name || 'Belirtilmemiş' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Unvan</dt>
                                        <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ user.title || 'Belirtilmemiş' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Sistem Rolü (Yetkiler)</dt>
                                        <dd class="mt-1">
                                            <span v-if="user.roles && user.roles.length > 0" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                {{ user.roles[0]?.name }}
                                            </span>
                                            <span v-else class="text-sm text-gray-500 italic">Sistem rolü atanmamış</span>
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Ayarlar ve Güvenlik -->
                            <div class="bg-indigo-50 rounded-xl p-6 border border-indigo-100">
                                <h3 class="text-lg font-semibold text-indigo-900 mb-4 border-b border-indigo-200 pb-2">Güvenlik ve Ayarlar</h3>
                                
                                <p class="text-sm text-indigo-700 mb-6">
                                    Güvenlik politikalarımız gereği; şifre değiştirme, hesap silme ve isim/e-posta güncelleme işlemleri yalnızca Merkezi Kimlik (SSO) Sistemi üzerinden yapılmaktadır.
                                </p>
                                
                                <div class="space-y-3">
                                    <a 
                                        :href="`${centralSsoUrl}/profile`" 
                                        target="_blank"
                                        class="w-full flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                        Şifremi Değiştir
                                    </a>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
