<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    stats: Object,
    user: Object,
});

const showAdminSection = ref(true);

const getTaskTypeBadge = (type) => {
    switch (type) {
        case 'approval':
            return { label: 'Onay Bekliyor', bg: 'bg-amber-100 text-amber-900 border-amber-300' };
        case 'form':
            return { label: 'Form Doldurma', bg: 'bg-blue-100 text-blue-900 border-blue-300' };
        case 'review':
            return { label: 'İnceleme & Bilgi', bg: 'bg-purple-100 text-purple-900 border-purple-300' };
        default:
            return { label: 'Görev', bg: 'bg-gray-100 text-gray-800 border-gray-300' };
    }
};

const formatDate = (val) => {
    if (!val) return '-';
    return new Date(val).toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' });
};
</script>

<template>
    <Head title="Ana Sayfa - Aksiyon Merkezi" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="font-extrabold text-2xl text-gray-900 leading-tight flex items-center gap-2">
                        Hoş Geldiniz, {{ user.name }} 👋
                    </h2>
                    <p class="text-sm text-gray-500 mt-1 flex items-center gap-2">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span class="font-semibold text-gray-700">{{ user.title || 'Personel' }}</span>
                        <span>&bull;</span>
                        <span>{{ user.department?.name || 'Köksan Pet ve Plastik Ambalaj Sanayi A.Ş.' }}</span>
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Link
                        :href="route('processes.index')"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm shadow-md hover:shadow-lg transition-all"
                    >
                        <span>🚀</span> Yeni Talep Başlat
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

                <!-- 1. ÜÇ BÜYÜK CANLI AKSİYON KARTI -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    
                    <!-- Kart 1: İş Listem -->
                    <Link 
                        :href="route('tasks.index')"
                        class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-rose-500 via-rose-600 to-red-600 p-6 text-white shadow-lg hover:shadow-2xl hover:-translate-y-0.5 transition-all duration-300"
                    >
                        <div class="relative z-10 flex items-start justify-between">
                            <div>
                                <span class="text-xs font-bold tracking-wider uppercase text-rose-100 block mb-1">
                                    Gelen Kutusu
                                </span>
                                <h3 class="text-xl font-black">Bekleyen İşlerim</h3>
                                <p class="text-xs text-rose-100/90 mt-1 max-w-[220px]">
                                    Onayınızı veya bilgi girişinizi bekleyen görevler.
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="text-4xl font-black tracking-tight block">
                                    {{ stats.pending_tasks_count }}
                                </span>
                                <span class="text-[11px] font-bold text-rose-200">Görev</span>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-rose-400/30 flex items-center justify-between text-xs font-bold text-rose-100 group-hover:text-white">
                            <span>İş Listesine Git</span>
                            <span class="transform group-hover:translate-x-1 transition-transform">&rarr;</span>
                        </div>
                        <div class="absolute -bottom-6 -right-6 text-8xl text-white/10 select-none pointer-events-none">📥</div>
                    </Link>

                    <!-- Kart 2: Devam Eden Taleplerim -->
                    <Link 
                        :href="route('processes.history')"
                        class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-indigo-700 p-6 text-white shadow-lg hover:shadow-2xl hover:-translate-y-0.5 transition-all duration-300"
                    >
                        <div class="relative z-10 flex items-start justify-between">
                            <div>
                                <span class="text-xs font-bold tracking-wider uppercase text-indigo-200 block mb-1">
                                    Başlattıklarım
                                </span>
                                <h3 class="text-xl font-black">Devam Eden Taleplerim</h3>
                                <p class="text-xs text-indigo-100/90 mt-1 max-w-[220px]">
                                    Açtığınız ve onay süreçlerinde ilerleyen talepler.
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="text-4xl font-black tracking-tight block">
                                    {{ stats.my_running_count }}
                                </span>
                                <span class="text-[11px] font-bold text-indigo-200">Süreç</span>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-indigo-400/30 flex items-center justify-between text-xs font-bold text-indigo-100 group-hover:text-white">
                            <span>Taleplerimi İncele</span>
                            <span class="transform group-hover:translate-x-1 transition-transform">&rarr;</span>
                        </div>
                        <div class="absolute -bottom-6 -right-6 text-8xl text-white/10 select-none pointer-events-none">📁</div>
                    </Link>

                    <!-- Kart 3: Takipler & Geri Bildirim -->
                    <Link 
                        :href="route('follow-ups.index')"
                        class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-purple-600 via-purple-700 to-fuchsia-700 p-6 text-white shadow-lg hover:shadow-2xl hover:-translate-y-0.5 transition-all duration-300"
                    >
                        <div class="relative z-10 flex items-start justify-between">
                            <div>
                                <span class="text-xs font-bold tracking-wider uppercase text-purple-200 block mb-1">
                                    Süreç Sonrası
                                </span>
                                <h3 class="text-xl font-black">Takipler & Geri Bildirim</h3>
                                <p class="text-xs text-purple-100/90 mt-1 max-w-[220px]">
                                    Tamamlanan süreçlerin sonuç ve sipariş durumları.
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="text-4xl font-black tracking-tight block">
                                    {{ stats.pending_follow_ups_count }}
                                </span>
                                <span class="text-[11px] font-bold text-purple-200">Bekleyen</span>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-purple-400/30 flex items-center justify-between text-xs font-bold text-purple-100 group-hover:text-white">
                            <span>Takipleri Yanıtla</span>
                            <span class="transform group-hover:translate-x-1 transition-transform">&rarr;</span>
                        </div>
                        <div class="absolute -bottom-6 -right-6 text-8xl text-white/10 select-none pointer-events-none">⏱️</div>
                    </Link>

                </div>

                <!-- 2. BÖLÜM: BANA ATANAN ACİL İŞLER (GELEN KUTUSU LİSTESİ) -->
                <div class="bg-white rounded-2xl border border-gray-200/80 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 text-rose-600 font-bold text-lg">
                                📥
                            </span>
                            <div>
                                <h3 class="font-bold text-lg text-gray-900">Üzerimdeki İşler (Aksiyon Bekleyenler)</h3>
                                <p class="text-xs text-gray-500">Herhangi bir süreçten doğrudan size veya biriminize yönlendirilen görevler</p>
                            </div>
                        </div>

                        <Link 
                            :href="route('tasks.index')"
                            class="text-xs font-bold text-indigo-600 hover:text-indigo-800 hover:underline flex items-center gap-1"
                        >
                            Tüm İş Listemi Gör ({{ stats.pending_tasks_count }}) &rarr;
                        </Link>
                    </div>

                    <!-- Görev Listesi -->
                    <div v-if="stats.pending_tasks && stats.pending_tasks.length > 0" class="divide-y divide-gray-100">
                        <div 
                            v-for="task in stats.pending_tasks" 
                            :key="task.id"
                            class="p-5 hover:bg-gray-50/70 transition flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
                        >
                            <div class="flex items-start gap-3.5">
                                <div class="mt-1">
                                    <span 
                                        class="px-2.5 py-1 text-[11px] font-bold rounded-lg border"
                                        :class="getTaskTypeBadge(task.type).bg"
                                    >
                                        {{ getTaskTypeBadge(task.type).label }}
                                    </span>
                                </div>
                                <div>
                                    <h4 class="font-bold text-gray-900 text-sm">
                                        {{ task.title || task.node_id }}
                                    </h4>
                                    <div class="flex flex-wrap items-center gap-2 mt-1 text-xs text-gray-500">
                                        <span class="font-semibold text-gray-700">{{ task.process_instance?.workflow?.name || 'Süreç' }}</span>
                                        <span>&bull;</span>
                                        <span>Talep No: #{{ task.process_instance_id }}</span>
                                        <span v-if="task.process_instance?.starter">&bull;</span>
                                        <span v-if="task.process_instance?.starter">Başlatan: {{ task.process_instance.starter.name }}</span>
                                        <span>&bull;</span>
                                        <span>Atanma: {{ formatDate(task.created_at) }}</span>
                                    </div>
                                </div>
                            </div>

                            <Link 
                                :href="route('tasks.show', task.id)"
                                class="inline-flex items-center justify-center px-4 py-2 rounded-xl text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm hover:shadow transition shrink-0"
                            >
                                Göreve Git &rarr;
                            </Link>
                        </div>
                    </div>

                    <!-- Boş Durum -->
                    <div v-else class="p-12 text-center">
                        <div class="w-14 h-14 mx-auto rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl mb-3">
                            🎉
                        </div>
                        <h4 class="font-bold text-gray-900 text-base">Harika! Bekleyen işiniz bulunmuyor.</h4>
                        <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">
                            Şu anda adınıza atanmış bekleyen herhangi bir onay veya form doldurma görevi yok.
                        </p>
                    </div>
                </div>

                <!-- 3. BÖLÜM: CEVAP BEKLEYEN NUMUNE VE SÜREÇ TAKİPLERİ (VARSA) -->
                <div v-if="stats.pending_follow_ups && stats.pending_follow_ups.length > 0" class="bg-gradient-to-r from-purple-50 via-fuchsia-50 to-indigo-50 rounded-2xl border border-purple-200/80 p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2.5">
                            <span class="p-2 rounded-xl bg-purple-600 text-white font-bold text-sm">⏱️</span>
                            <div>
                                <h3 class="font-bold text-base text-purple-950">Geri Bildirim Bekleyen Takipler</h3>
                                <p class="text-xs text-purple-800">Tamamlanan numunelerin veya taleplerin güncel durumunu sisteme işleyiniz.</p>
                            </div>
                        </div>

                        <Link :href="route('follow-ups.index')" class="text-xs font-bold text-purple-700 hover:underline">
                            Tümünü Gör ({{ stats.pending_follow_ups_count }}) &rarr;
                        </Link>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div 
                            v-for="fu in stats.pending_follow_ups" 
                            :key="fu.id"
                            class="bg-white rounded-xl border border-purple-100 p-4 shadow-sm flex items-center justify-between"
                        >
                            <div>
                                <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded bg-purple-100 text-purple-800">
                                    {{ fu.process_instance?.workflow?.name || 'Numune / Takip' }} #{{ fu.process_instance_id }}
                                </span>
                                <h4 class="font-bold text-gray-900 text-sm mt-1.5">{{ fu.title }}</h4>
                                <p class="text-xs text-gray-500 mt-0.5 line-clamp-1">{{ fu.prompt }}</p>
                            </div>

                            <Link 
                                :href="route('follow-ups.show', fu.id)"
                                class="px-3.5 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-sm transition shrink-0 ml-3"
                            >
                                Yanıtla &rarr;
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- 4. BÖLÜM: HIZLI SÜREÇ BAŞLATMA (EVRENSEL KATALOG KISAYOLLARI) -->
                <div class="bg-white rounded-2xl border border-gray-200/80 shadow-sm p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100 mb-6">
                        <div class="flex items-center gap-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 font-bold text-lg">
                                🚀
                            </span>
                            <div>
                                <h3 class="font-bold text-lg text-gray-900">Süreç Başlat (Katalog Kısayolları)</h3>
                                <p class="text-xs text-gray-500">Şirket genelinde departmanınıza ve yetkinize tanımlı süreçler</p>
                            </div>
                        </div>

                        <Link 
                            :href="route('processes.index')" 
                            class="text-xs font-bold text-indigo-600 hover:text-indigo-800 hover:underline flex items-center gap-1"
                        >
                            Tüm Süreç Kataloğunu İncele &rarr;
                        </Link>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <div 
                            v-for="wf in stats.quick_workflows" 
                            :key="wf.id"
                            class="rounded-xl border border-gray-200 hover:border-indigo-400 p-5 bg-white hover:bg-indigo-50/20 transition-all flex flex-col justify-between group shadow-sm hover:shadow"
                        >
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-xs font-bold px-2 py-0.5 rounded-md bg-gray-100 text-gray-700 uppercase">
                                        {{ wf.category || 'Genel Süreç' }}
                                    </span>
                                    <span class="text-lg group-hover:scale-110 transition-transform">📋</span>
                                </div>
                                <h4 class="font-bold text-gray-900 text-base group-hover:text-indigo-600 transition-colors">
                                    {{ wf.name }}
                                </h4>
                                <p class="text-xs text-gray-500 mt-1 line-clamp-2 leading-relaxed">
                                    {{ wf.description || 'Bu süreç için açıklama tanımlanmamıştır.' }}
                                </p>
                            </div>

                            <div class="mt-4 pt-4 border-t border-gray-100 flex justify-end">
                                <Link 
                                    :href="route('processes.create', wf.id)"
                                    class="w-full text-center py-2 px-4 rounded-lg bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white font-bold text-xs transition-all shadow-sm"
                                >
                                    Formu Doldur ve Başlat &rarr;
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. BÖLÜM: BAŞLATTIĞIM TALEPLERİN CANLI DURUMU (TAKİP) -->
                <div v-if="stats.my_recent_processes && stats.my_recent_processes.length > 0" class="bg-white rounded-2xl border border-gray-200/80 shadow-sm p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100 mb-4">
                        <div class="flex items-center gap-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600 font-bold text-lg">
                                📁
                            </span>
                            <div>
                                <h3 class="font-bold text-lg text-gray-900">Son Başlattığım Talepler</h3>
                                <p class="text-xs text-gray-500">Açtığınız taleplerin hangi aşamada olduğunu anlık takip edin</p>
                            </div>
                        </div>

                        <Link :href="route('processes.history')" class="text-xs font-bold text-indigo-600 hover:underline">
                            Tüm Taleplerim ({{ stats.my_running_count + stats.my_completed_count }}) &rarr;
                        </Link>
                    </div>

                    <div class="divide-y divide-gray-100">
                        <div 
                            v-for="proc in stats.my_recent_processes" 
                            :key="proc.id"
                            class="py-3.5 flex items-center justify-between gap-4"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-gray-50 border border-gray-200 text-gray-700 flex items-center justify-center font-bold text-xs font-mono">
                                    #{{ proc.id }}
                                </div>
                                <div>
                                    <h4 class="font-bold text-gray-900 text-sm">{{ proc.workflow?.name }}</h4>
                                    <span class="text-xs text-gray-400">Başlatma: {{ formatDate(proc.created_at) }}</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <span 
                                    class="px-2.5 py-1 text-xs font-bold rounded-full"
                                    :class="{
                                        'bg-amber-100 text-amber-800': proc.status === 'running',
                                        'bg-emerald-100 text-emerald-800': proc.status === 'completed',
                                        'bg-rose-100 text-rose-800': proc.status === 'rejected' || proc.status === 'cancelled',
                                    }"
                                >
                                    {{ proc.status === 'running' ? 'İşlemde / Onayda' : (proc.status === 'completed' ? 'Tamamlandı' : 'Sonlandı') }}
                                </span>

                                <Link 
                                    :href="route('processes.tracker', proc.id)"
                                    class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition"
                                >
                                    Akış Takibi &rarr;
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. BÖLÜM: YÖNETİCİ GENEL BAKIŞ (SADECE ADMİNLER İÇİN EN ALTTA) -->
                <div v-if="stats.is_admin && stats.admin_stats" class="bg-slate-900 rounded-2xl p-6 text-white shadow-xl">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-800 mb-6">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">⚙️</span>
                            <div>
                                <h3 class="font-bold text-lg text-white">Şirket Geneli BPM Yönetim Özeti</h3>
                                <p class="text-xs text-slate-400">Köksan kurumsal süreç motorunun genel sistem metrikleri</p>
                            </div>
                        </div>
                        <span class="px-3 py-1 rounded-full bg-slate-800 text-slate-300 text-xs font-bold border border-slate-700">
                            Sistem Yöneticisi Görünümü
                        </span>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                        <div class="bg-slate-800/80 p-4 rounded-xl border border-slate-700">
                            <span class="text-xs text-slate-400 block mb-1">Toplam Kullanıcı</span>
                            <span class="text-2xl font-black text-white">{{ stats.admin_stats.total_users }}</span>
                        </div>
                        <div class="bg-slate-800/80 p-4 rounded-xl border border-slate-700">
                            <span class="text-xs text-slate-400 block mb-1">Aktif Akış Tasarımı</span>
                            <span class="text-2xl font-black text-indigo-400">{{ stats.admin_stats.active_workflows }}</span>
                        </div>
                        <div class="bg-slate-800/80 p-4 rounded-xl border border-slate-700">
                            <span class="text-xs text-slate-400 block mb-1">Şirkette Devam Eden</span>
                            <span class="text-2xl font-black text-amber-400">{{ stats.admin_stats.running_processes }}</span>
                        </div>
                        <div class="bg-slate-800/80 p-4 rounded-xl border border-slate-700">
                            <span class="text-xs text-slate-400 block mb-1">Tamamlanan Süreç</span>
                            <span class="text-2xl font-black text-emerald-400">{{ stats.admin_stats.completed_processes }}</span>
                        </div>
                        <div class="bg-slate-800/80 p-4 rounded-xl border border-slate-700">
                            <span class="text-xs text-slate-400 block mb-1">Toplam Süreç Takibi</span>
                            <span class="text-2xl font-black text-purple-400">{{ stats.admin_stats.total_follow_ups }}</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>