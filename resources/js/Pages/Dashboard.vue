<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    stats: Object,
    user: Object,
});
</script>

<template>
    <Head title="Ana Sayfa" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-2xl text-gray-800 leading-tight">Hoş Geldiniz, {{ user.name }} 👋</h2>
            <p class="text-sm text-gray-500 mt-1">{{ user.title || 'Unvan Yok' }} | {{ user.department?.name || 'Departman Yok' }}</p>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
                
                <!-- YÖNETİCİ / ADMIN EKRANI -->
                <template v-if="stats.role === 'admin'">
                    <!-- Kpi Kartları -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border-l-4 border-indigo-500 p-6 flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Toplam Kullanıcı</p>
                                <p class="text-3xl font-bold text-gray-800">{{ stats.total_users }}</p>
                            </div>
                            <div class="text-indigo-200">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            </div>
                        </div>

                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border-l-4 border-blue-500 p-6 flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Sistemdeki Akışlar</p>
                                <p class="text-3xl font-bold text-gray-800">{{ stats.active_workflows }}</p>
                            </div>
                            <div class="text-blue-200">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                            </div>
                        </div>

                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border-l-4 border-yellow-500 p-6 flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Devam Eden Süreçler</p>
                                <p class="text-3xl font-bold text-gray-800">{{ stats.running_processes }}</p>
                            </div>
                            <div class="text-yellow-200">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            </div>
                        </div>

                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border-l-4 border-green-500 p-6 flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Tamamlanan</p>
                                <p class="text-3xl font-bold text-gray-800">{{ stats.completed_processes }}</p>
                            </div>
                            <div class="text-green-200">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                        </div>
                    </div>

                    <!-- Son Aktiviteler -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 border-b">
                            <h3 class="text-lg font-bold text-gray-800">Şirket Geneli Son Başlatılan Süreçler</h3>
                        </div>
                        <div class="p-6">
                            <ul class="divide-y divide-gray-200">
                                <li v-for="proc in stats.recent_processes" :key="proc.id" class="py-4 flex justify-between items-center">
                                    <div>
                                        <p class="text-sm font-bold text-gray-900">{{ proc.workflow?.name }}</p>
                                        <p class="text-xs text-gray-500">Başlatan: {{ proc.starter?.name }} | Süreç No: #{{ proc.id }}</p>
                                    </div>
                                    <div>
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full" 
                                              :class="proc.status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'">
                                            {{ proc.status === 'completed' ? 'Tamamlandı' : 'Devam Ediyor' }}
                                        </span>
                                    </div>
                                </li>
                                <li v-if="!stats.recent_processes || stats.recent_processes.length === 0" class="text-sm text-gray-500 text-center py-4">Sistemde henüz başlatılmış süreç yok.</li>
                            </ul>
                        </div>
                    </div>
                </template>

                <!-- STANDART KULLANICI / PERSONEL EKRANI -->
                <template v-else>
                    <!-- 3 BÜYÜK ANA BUTON -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        
                        <!-- 1. Talep Oluştur -->
                        <Link :href="route('processes.index')" class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl p-8 text-white shadow-lg hover:shadow-2xl hover:-translate-y-1 transition-all duration-300 relative overflow-hidden group block">
                            <div class="relative z-10">
                                <div class="text-5xl mb-4 group-hover:scale-110 transition-transform">🚀</div>
                                <h3 class="text-2xl font-black mb-2">Yeni Talep Başlat</h3>
                                <p class="text-green-100 text-sm font-medium leading-relaxed">Yeni bir cihaz, izin veya yetki talep etmek için buraya tıklayın.</p>
                            </div>
                            <div class="absolute -bottom-4 -right-4 text-white opacity-10 text-9xl transform -rotate-12 group-hover:rotate-0 transition-transform duration-500">➕</div>
                        </Link>

                        <!-- 2. Bekleyen İşlerim -->
                        <Link :href="route('tasks.index')" class="bg-gradient-to-br from-orange-500 to-red-500 rounded-2xl p-8 text-white shadow-lg hover:shadow-2xl hover:-translate-y-1 transition-all duration-300 relative overflow-hidden group block">
                            <div class="relative z-10">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="text-5xl group-hover:scale-110 transition-transform">✅</div>
                                    <div v-if="stats.pending_tasks > 0" class="bg-white text-red-600 font-black text-xl px-4 py-1 rounded-full shadow-md animate-pulse">
                                        {{ stats.pending_tasks }} Bekleyen İş
                                    </div>
                                </div>
                                <h3 class="text-2xl font-black mb-2">Bekleyen Onaylarım</h3>
                                <p class="text-red-100 text-sm font-medium leading-relaxed">Sizin incelemenizi ve onayınızı bekleyen evraklar.</p>
                            </div>
                        </Link>

                        <!-- 3. Taleplerimin Durumu -->
                        <Link :href="route('processes.history')" class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl p-8 text-white shadow-lg hover:shadow-2xl hover:-translate-y-1 transition-all duration-300 relative overflow-hidden group block">
                            <div class="relative z-10">
                                <div class="text-5xl mb-4 group-hover:scale-110 transition-transform">📁</div>
                                <h3 class="text-2xl font-black mb-2">Benim Taleplerim</h3>
                                <p class="text-blue-100 text-sm font-medium leading-relaxed">Daha önce istediğiniz şeylerin şu an hangi aşamada olduğunu görün.</p>
                            </div>
                            <div class="absolute -bottom-4 -right-4 text-white opacity-10 text-9xl transform -rotate-12 group-hover:rotate-0 transition-transform duration-500">🔎</div>
                        </Link>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Gelen Kutusu (Bekleyen Görevlerim) -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-gray-100 h-full">
                            <div class="p-6 border-b border-gray-50 bg-gray-50/50 flex justify-between items-center">
                                <h3 class="text-lg font-black text-gray-800 flex items-center gap-2">
                                    <span class="text-xl">📥</span> Gelen Kutusu
                                </h3>
                                <Link v-if="stats.recent_tasks && stats.recent_tasks.length > 0" :href="route('tasks.index')" class="text-sm text-indigo-600 hover:text-indigo-800 font-bold">Tümünü Gör</Link>
                            </div>
                            <div class="p-0">
                                <ul class="divide-y divide-gray-100">
                                    <li v-for="task in stats.recent_tasks" :key="task.id" class="p-6 hover:bg-gray-50 transition-colors">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center font-black text-lg mr-4">!</div>
                                                <div>
                                                    <p class="text-sm font-bold text-gray-900">{{ task.process_instance?.workflow?.name }}</p>
                                                    <p class="text-xs text-gray-500 mt-0.5">Tarih: {{ new Date(task.created_at).toLocaleDateString('tr-TR') }}</p>
                                                </div>
                                            </div>
                                            <Link :href="route('tasks.show', task.id)" class="text-sm bg-indigo-50 hover:bg-indigo-100 text-indigo-700 py-2 px-5 rounded-lg font-bold shadow-sm transition-colors">
                                                İşlem Yap
                                            </Link>
                                        </div>
                                    </li>
                                    <!-- Boş Durum (Empty State) -->
                                    <li v-if="!stats.recent_tasks || stats.recent_tasks.length === 0" class="p-12 text-center flex flex-col items-center">
                                        <div class="text-6xl mb-4">☕</div>
                                        <h4 class="text-lg font-bold text-gray-700 mb-2">Şu an bekleyen bir onayınız yok, harika!</h4>
                                        <p class="text-gray-500 text-sm">Tüm işlerinizi halletmişsiniz. Gönül rahatlığıyla kahvenizi yudumlayabilirsiniz.</p>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Kendi Başlattığı Süreçler (Empty State Odaklı) -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-gray-100 h-full">
                            <div class="p-6 border-b border-gray-50 bg-gray-50/50">
                                <h3 class="text-lg font-black text-gray-800 flex items-center gap-2">
                                    <span class="text-xl">📋</span> Son Taleplerim
                                </h3>
                            </div>
                            <div class="p-0 flex flex-col h-[calc(100%-70px)] justify-center">
                                <!-- Eğer kullanıcı hiç süreç başlatmamışsa -->
                                <div v-if="stats.my_running_processes === 0 && stats.my_completed_processes === 0" class="p-12 text-center flex flex-col items-center">
                                    <div class="text-6xl mb-4">🌱</div>
                                    <h4 class="text-lg font-bold text-gray-700 mb-2">Henüz hiçbir talepte bulunmamışsınız.</h4>
                                    <p class="text-gray-500 text-sm mb-6">Sistem üzerinden yeni bir cihaz istemek veya izin formu doldurmak çok kolay.</p>
                                    <Link :href="route('processes.index')" class="bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-8 rounded-full shadow-lg shadow-green-200 transition-all transform hover:scale-105">
                                        🚀 İlk Talebinizi Başlatın
                                    </Link>
                                </div>
                                <!-- Geçmiş talebi varsa özet bilgi -->
                                <div v-else class="p-8">
                                    <p class="text-gray-600 mb-6 text-center text-sm font-medium">Sistemde başlattığınız taleplerin güncel durumu:</p>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="bg-yellow-50 rounded-xl p-6 text-center border border-yellow-100">
                                            <p class="text-4xl font-black text-yellow-600 mb-1">{{ stats.my_running_processes }}</p>
                                            <p class="text-xs font-bold text-yellow-800 uppercase tracking-wide">Devam Eden</p>
                                        </div>
                                        <div class="bg-green-50 rounded-xl p-6 text-center border border-green-100">
                                            <p class="text-4xl font-black text-green-600 mb-1">{{ stats.my_completed_processes }}</p>
                                            <p class="text-xs font-bold text-green-800 uppercase tracking-wide">Tamamlanan</p>
                                        </div>
                                    </div>
                                    <div class="mt-6 text-center">
                                        <Link :href="route('processes.history')" class="text-indigo-600 font-bold hover:text-indigo-800 text-sm">Tüm listeyi görüntüle &rarr;</Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

            </div>
        </div>
    </AuthenticatedLayout>
</template>