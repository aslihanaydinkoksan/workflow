<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    followUps: Object,
    filters: Object,
    stats: Object,
});

const search = ref(props.filters.search || '');
const currentStatus = ref(props.filters.status || 'all');

const filterStatus = (status) => {
    currentStatus.value = status;
    router.get(route('follow-ups.index'), {
        status: status === 'all' ? null : status,
        search: search.value || null,
    }, {
        preserveState: true,
        replace: true,
    });
};

const handleSearch = () => {
    router.get(route('follow-ups.index'), {
        status: currentStatus.value === 'all' ? null : currentStatus.value,
        search: search.value || null,
    }, {
        preserveState: true,
        replace: true,
    });
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('tr-TR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
};
</script>

<template>
    <Head title="Numune Takip & Sipariş Sorgulama" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="font-bold text-2xl text-gray-900 leading-tight">⏱ Süreç & Sipariş Takip Bildirimleri</h2>
                    <p class="text-sm text-gray-500 mt-1">Süreç tamamlandıktan sonra tetiklenen takiplerin durumlarını, geri bildirimleri ve dönüşümleri izleyin</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- İSTATİSTİK KARTLARI -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl font-bold">
                            📦
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block">Toplam Takip</span>
                            <span class="text-2xl font-black text-gray-900">{{ stats.total }}</span>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl font-bold">
                            ⏳
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block">Cevap Bekleyen</span>
                            <span class="text-2xl font-black text-amber-600">{{ stats.pending }}</span>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl font-bold">
                            ✅
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block">Siparişe Dönen</span>
                            <span class="text-2xl font-black text-emerald-600">{{ stats.converted }}</span>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl font-bold">
                            📈
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block">Dönüşüm Oranı</span>
                            <span class="text-2xl font-black text-purple-600">%{{ stats.conversion_rate }}</span>
                        </div>
                    </div>
                </div>

                <!-- FİLTRE & ARAMA ALANI -->
                <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <!-- Status Filter Tabs -->
                    <div class="flex items-center gap-2 overflow-x-auto pb-1">
                        <button 
                            @click="filterStatus('all')"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition"
                            :class="currentStatus === 'all' ? 'bg-gray-900 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                        >
                            Tümü ({{ stats.total }})
                        </button>
                        <button 
                            @click="filterStatus('pending')"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5"
                            :class="currentStatus === 'pending' ? 'bg-amber-600 text-white shadow-sm' : 'bg-amber-50 text-amber-700 hover:bg-amber-100'"
                        >
                            <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                            Bekleyenler ({{ stats.pending }})
                        </button>
                        <button 
                            @click="filterStatus('converted')"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5"
                            :class="currentStatus === 'converted' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'"
                        >
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            Siparişe Dönen ({{ stats.converted }})
                        </button>
                        <button 
                            @click="filterStatus('not_converted')"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5"
                            :class="currentStatus === 'not_converted' ? 'bg-rose-600 text-white shadow-sm' : 'bg-rose-50 text-rose-700 hover:bg-rose-100'"
                        >
                            <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                            Dönmeyenler ({{ stats.not_converted }})
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div class="flex items-center gap-2">
                        <input 
                            v-model="search"
                            @keyup.enter="handleSearch"
                            type="text" 
                            placeholder="Müşteri, ürün veya sipariş no ara..." 
                            class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 w-full sm:w-64"
                        />
                        <button 
                            @click="handleSearch"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-3.5 py-2 rounded-lg text-sm font-semibold transition shrink-0"
                        >
                            🔍 Ara
                        </button>
                    </div>
                </div>

                <!-- TAKİP LİSTESİ TABLOSU -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                            <thead class="bg-gray-50/80 text-xs font-bold text-gray-500 uppercase tracking-wider">
                                <tr>
                                    <th class="py-3.5 px-4">Talep & Süreç</th>
                                    <th class="py-3.5 px-4">Müşteri & Ürün</th>
                                    <th class="py-3.5 px-4">Temsilci</th>
                                    <th class="py-3.5 px-4">Planlanan Tarih</th>
                                    <th class="py-3.5 px-4">Sipariş Durumu</th>
                                    <th class="py-3.5 px-4">SAP S/4HANA</th>
                                    <th class="py-3.5 px-4 text-right">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-for="item in followUps.data" :key="item.id" class="hover:bg-gray-50/60 transition">
                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-indigo-600">#{{ item.process_instance_id }}</span>
                                            <span class="text-xs text-gray-500">({{ item.process_instance?.workflow?.name || 'Numune Talebi' }})</span>
                                        </div>
                                        <span class="text-[11px] text-gray-400 block mt-0.5">Oluşturuldu: {{ formatDate(item.created_at) }}</span>
                                    </td>

                                    <td class="py-4 px-4">
                                        <div class="font-bold text-gray-900">
                                            {{ item.process_instance?.data?.musteri_adi || item.process_instance?.data?.customer_name || 'Bilinmeyen Müşteri' }}
                                        </div>
                                        <div class="text-xs text-gray-500 truncate max-w-xs">
                                            {{ item.process_instance?.data?.urun_adi || item.process_instance?.data?.material_name || 'Numune Ürünü' }}
                                            <span v-if="item.process_instance?.data?.miktar" class="text-gray-400">({{ item.process_instance?.data?.miktar }})</span>
                                        </div>
                                    </td>

                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <span class="text-gray-900 font-medium">{{ item.assigned_user?.name || item.process_instance?.starter?.name || '-' }}</span>
                                    </td>

                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <div class="text-gray-900 font-semibold">{{ formatDate(item.scheduled_at) }}</div>
                                        <div v-if="item.reminder_count > 0" class="text-[11px] text-amber-600 font-medium">
                                            🔔 {{ item.reminder_count }}. Hatırlatma
                                        </div>
                                    </td>

                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <div v-if="item.status === 'converted'">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                                <span>🎉</span> Siparişe Döndü
                                            </span>
                                            <span v-if="item.order_number" class="text-xs font-mono font-bold text-emerald-700 block mt-0.5">
                                                No: {{ item.order_number }}
                                            </span>
                                        </div>
                                        <div v-else-if="item.status === 'not_converted'">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                                                <span>❌</span> Dönmedi
                                            </span>
                                            <span class="text-[11px] text-gray-500 block mt-0.5 truncate max-w-[160px]">
                                                {{ item.non_conversion_reason }}
                                            </span>
                                        </div>
                                        <div v-else>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                                <span>⏳</span> Cevap Bekliyor
                                            </span>
                                        </div>
                                    </td>

                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <div v-if="item.sap_sales_order_id">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-sky-50 text-sky-700 border border-sky-200">
                                                <span>SAP:</span> {{ item.sap_sales_order_id }}
                                            </span>
                                        </div>
                                        <div v-else-if="item.status === 'converted'">
                                            <span class="text-[11px] text-gray-400 italic">Eşleşme bekleniyor</span>
                                        </div>
                                        <div v-else>
                                            <span class="text-gray-300 text-xs">-</span>
                                        </div>
                                    </td>

                                    <td class="py-4 px-4 whitespace-nowrap text-right space-x-2">
                                        <a 
                                            :href="route('processes.certificate', item.process_instance_id)"
                                            target="_blank"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 transition"
                                            title="Analiz Sertifikasını İndir (PDF)"
                                        >
                                            <span>📄</span> CoA
                                        </a>

                                        <Link 
                                            :href="route('follow-ups.show', item.id)"
                                            class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-bold transition shadow-sm"
                                            :class="item.status === 'pending' ? 'bg-indigo-600 hover:bg-indigo-700 text-white' : 'bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200'"
                                        >
                                            {{ item.status === 'pending' ? '✍️ Cevapla' : 'İncele' }}
                                        </Link>
                                    </td>
                                </tr>

                                <tr v-if="followUps.data.length === 0">
                                    <td colspan="7" class="text-center py-12 text-gray-400">
                                        <p class="text-base font-semibold">Herhangi bir numune takibi bulunamadı.</p>
                                        <p class="text-xs text-gray-400 mt-1">Numune süreçleri tamamlandıkça burada otomatik listelenecektir.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Sayfalama (Pagination) -->
                    <div v-if="followUps.links && followUps.links.length > 3" class="p-4 border-t border-gray-100 flex items-center justify-between">
                        <span class="text-xs text-gray-500">
                            Toplam {{ followUps.total }} kayıttan {{ followUps.from }}-{{ followUps.to }} arası gösteriliyor
                        </span>
                        <div class="flex items-center gap-1">
                            <Link 
                                v-for="(link, i) in followUps.links" 
                                :key="i"
                                :href="link.url || '#'"
                                v-html="link.label"
                                class="px-3 py-1 rounded text-xs font-semibold transition"
                                :class="link.active ? 'bg-indigo-600 text-white' : (link.url ? 'bg-gray-50 text-gray-700 hover:bg-gray-100' : 'text-gray-300 pointer-events-none')"
                            />
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
