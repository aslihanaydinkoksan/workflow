<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { VueFlow } from '@vue-flow/core';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';

import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';
import '@vue-flow/controls/dist/style.css';

const props = defineProps({
    instances: {
        type: Array,
        default: () => [],
    }
});

const searchQuery = ref('');
const statusFilter = ref('all'); // 'all' | 'active' | 'completed' | 'rejected'

const isPreviewOpen = ref(false);
const selectedWorkflow = ref(null);
const previewNodes = ref([]);
const previewEdges = ref([]);

const openPreview = async (workflow) => {
    selectedWorkflow.value = workflow;
    
    previewNodes.value = (workflow.nodes || []).map(n => ({
        ...n,
        draggable: false,
        selectable: false
    }));
    
    previewEdges.value = (workflow.edges || []).map(e => ({
        ...e,
        animated: true,
        style: { stroke: '#94a3b8', strokeWidth: 2 }
    }));
    
    isPreviewOpen.value = true;
};

const getStatusBadge = (status) => {
    switch (status) {
        case 'running': return 'bg-blue-100 text-blue-800 border border-blue-200';
        case 'waiting': return 'bg-amber-100 text-amber-800 border border-amber-200';
        case 'completed': return 'bg-emerald-100 text-emerald-800 border border-emerald-200';
        case 'rejected': return 'bg-red-100 text-red-800 border border-red-200';
        case 'cancelled': return 'bg-gray-100 text-gray-700 border border-gray-200';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getStatusName = (status) => {
    switch (status) {
        case 'running': return 'İlerliyor';
        case 'waiting': return 'Onay / İşlem Bekliyor';
        case 'completed': return 'Tamamlandı';
        case 'rejected': return 'Reddedildi';
        case 'cancelled': return 'İptal Edildi';
        default: return status;
    }
};

const counts = computed(() => {
    const list = props.instances || [];
    return {
        all: list.length,
        active: list.filter(i => ['running', 'waiting'].includes(i.status)).length,
        completed: list.filter(i => i.status === 'completed').length,
        rejected: list.filter(i => ['rejected', 'cancelled'].includes(i.status)).length,
    };
});

const filteredInstances = computed(() => {
    let list = props.instances || [];

    // Durum filtresi
    if (statusFilter.value === 'active') {
        list = list.filter(i => ['running', 'waiting'].includes(i.status));
    } else if (statusFilter.value === 'completed') {
        list = list.filter(i => i.status === 'completed');
    } else if (statusFilter.value === 'rejected') {
        list = list.filter(i => ['rejected', 'cancelled'].includes(i.status));
    }

    // Arama filtresi
    const q = searchQuery.value.trim().toLocaleLowerCase('tr-TR');
    if (q) {
        list = list.filter(i => {
            const idStr = String(i.id);
            const nameStr = (i.workflow?.name || '').toLocaleLowerCase('tr-TR');
            const statusStr = getStatusName(i.status).toLocaleLowerCase('tr-TR');
            const assignees = (i.tasks || []).map(t => t.assigned_user?.name || t.assigned_role || '').join(' ').toLocaleLowerCase('tr-TR');
            return idStr.includes(q) || nameStr.includes(q) || statusStr.includes(q) || assignees.includes(q);
        });
    }

    return list;
});
</script>

<template>
    <Head title="Başlattığım Süreçler (Taleplerim)" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                        <Link :href="route('dashboard')" class="hover:text-indigo-600 transition-colors flex items-center gap-1">
                            <span>🏠</span> Ana Sayfa
                        </Link>
                        <span>/</span>
                        <span class="text-gray-700 font-semibold">📁 Taleplerim</span>
                    </div>

                    <h2 class="font-extrabold text-2xl text-gray-900 leading-tight">
                        Başlattığım Süreçler & Taleplerim
                    </h2>
                </div>

                <div class="flex items-center gap-3">
                    <Link
                        :href="route('processes.index')"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 shadow-md shadow-indigo-600/20 transition-all transform hover:-translate-y-0.5"
                    >
                        <span>🚀</span> Yeni Talep Başlat
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Arama ve Durum Sekmeleri -->
                <div class="bg-white rounded-2xl p-4 sm:p-5 shadow-sm border border-gray-200/80 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <!-- Durum Hap Butonları -->
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            @click="statusFilter = 'all'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                            :class="statusFilter === 'all' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                        >
                            <span>Tümü</span>
                            <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="statusFilter === 'all' ? 'bg-white/20' : 'bg-gray-200 text-gray-700'">
                                {{ counts.all }}
                            </span>
                        </button>

                        <button
                            type="button"
                            @click="statusFilter = 'active'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                            :class="statusFilter === 'active' ? 'bg-blue-600 text-white shadow-sm' : 'bg-blue-50 text-blue-700 hover:bg-blue-100'"
                        >
                            <span>Devam Edenler</span>
                            <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="statusFilter === 'active' ? 'bg-white/20' : 'bg-blue-200 text-blue-900'">
                                {{ counts.active }}
                            </span>
                        </button>

                        <button
                            type="button"
                            @click="statusFilter = 'completed'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                            :class="statusFilter === 'completed' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'"
                        >
                            <span>Tamamlananlar</span>
                            <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="statusFilter === 'completed' ? 'bg-white/20' : 'bg-emerald-200 text-emerald-900'">
                                {{ counts.completed }}
                            </span>
                        </button>

                        <button
                            type="button"
                            @click="statusFilter = 'rejected'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                            :class="statusFilter === 'rejected' ? 'bg-red-600 text-white shadow-sm' : 'bg-red-50 text-red-700 hover:bg-red-100'"
                        >
                            <span>Red / İptal</span>
                            <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="statusFilter === 'rejected' ? 'bg-white/20' : 'bg-red-200 text-red-900'">
                                {{ counts.rejected }}
                            </span>
                        </button>
                    </div>

                    <!-- Canlı Arama Inputu -->
                    <div class="relative w-full md:w-80">
                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Talep no, akış veya yetkili ara..."
                            class="w-full pl-9 pr-4 py-2 rounded-xl text-xs text-gray-900 bg-gray-50 border border-gray-200 focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                        />
                    </div>
                </div>

                <!-- Tablo Kartı -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-gray-200/80">
                    <div v-if="filteredInstances.length === 0" class="text-center py-16 px-4">
                        <div class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-gray-100 flex items-center justify-center text-3xl">
                            📁
                        </div>
                        <h4 class="text-base font-bold text-gray-800">Kayıtlı Talep Bulunamadı</h4>
                        <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">
                            {{ searchQuery ? 'Arama kriterlerinize uyan bir talep bulunamadı.' : 'Henüz başlattığınız aktif veya tamamlanmış bir süreç bulunmuyor.' }}
                        </p>
                        <div class="mt-4">
                            <Link
                                :href="route('processes.index')"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 transition-colors"
                            >
                                <span>🚀</span> Yeni Süreç Başlat
                            </Link>
                        </div>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200/70">
                            <thead class="bg-gray-50/75">
                                <tr>
                                    <th scope="col" class="px-6 py-3.5 text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider">Talep No</th>
                                    <th scope="col" class="px-6 py-3.5 text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider">Süreç / Akış Adı</th>
                                    <th scope="col" class="px-6 py-3.5 text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider">Durum</th>
                                    <th scope="col" class="px-6 py-3.5 text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider">Sırada Bekleyen Aşama / Yetkili</th>
                                    <th scope="col" class="px-6 py-3.5 text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider">Başlangıç Tarihi</th>
                                    <th scope="col" class="px-6 py-3.5 text-right text-[11px] font-bold text-gray-500 uppercase tracking-wider">İşlem</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <tr
                                    v-for="instance in filteredInstances"
                                    :key="instance.id"
                                    class="hover:bg-indigo-50/30 transition-colors group cursor-pointer"
                                    @click="$inertia.visit(route('processes.tracker', instance.id))"
                                >
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-extrabold text-indigo-700 font-mono">
                                        #{{ instance.id }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-bold text-gray-900 group-hover:text-indigo-600 transition-colors">
                                            {{ instance.workflow.name }}
                                        </div>
                                        <div class="text-xs text-gray-400 font-mono">
                                            {{ instance.workflow?.form_template?.code || 'Genel Akış' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="['px-2.5 py-1 inline-flex text-xs leading-5 font-bold rounded-full', getStatusBadge(instance.status)]">
                                            {{ getStatusName(instance.status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div v-if="['running', 'waiting'].includes(instance.status)">
                                            <div v-if="instance.tasks && instance.tasks.length > 0" class="space-y-1">
                                                <div v-for="task in instance.tasks" :key="task.id" class="text-xs">
                                                    <span v-if="task.assigned_user" class="inline-flex items-center px-2.5 py-0.5 rounded-full font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500 mr-1.5 animate-pulse"></span>
                                                        {{ task.assigned_user.name }}
                                                    </span>
                                                    <span v-else-if="task.assigned_role" class="inline-flex items-center px-2.5 py-0.5 rounded-full font-bold bg-purple-100 text-purple-800 border border-purple-200">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-purple-500 mr-1.5 animate-pulse"></span>
                                                        {{ task.assigned_role }} (Rol)
                                                    </span>
                                                    <span v-else class="text-xs text-gray-500 italic">İlgili Operasyon Birimi</span>
                                                </div>
                                            </div>
                                            <span v-else class="text-xs text-gray-400 italic">Yönlendiriliyor...</span>
                                        </div>

                                        <span v-else-if="instance.status === 'completed'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            ✓ Süreç Tamamlandı
                                        </span>

                                        <span v-else-if="instance.status === 'rejected'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800 border border-red-200">
                                            ✕ Reddedildi
                                        </span>

                                        <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-700 border border-gray-200">
                                            İptal Edildi
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 font-mono">
                                        {{ new Date(instance.created_at).toLocaleString('tr-TR') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-medium space-x-2" @click.stop>
                                        <button
                                            @click="openPreview(instance.workflow)"
                                            class="inline-flex items-center text-teal-700 hover:text-teal-900 bg-teal-50 hover:bg-teal-100 px-3 py-1.5 rounded-xl font-bold transition-colors"
                                        >
                                            <span class="mr-1">🗺️</span> Şema
                                        </button>

                                        <Link
                                            :href="route('processes.tracker', instance.id)"
                                            class="inline-flex items-center text-indigo-700 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-3.5 py-1.5 rounded-xl font-extrabold transition-colors shadow-sm"
                                        >
                                            <span class="mr-1">👉</span> Canlı Takip &rarr;
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- Akış Önizleme Modalı -->
        <div v-if="isPreviewOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/80 backdrop-blur-sm transition-opacity">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl h-[80vh] flex flex-col overflow-hidden border border-gray-100">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/80">
                    <div>
                        <h3 class="text-base font-bold text-gray-900">Akış Tasarımı: {{ selectedWorkflow?.name }}</h3>
                        <p class="text-xs text-gray-500">Sürecin model şeması ve adımları.</p>
                    </div>
                    <button @click="isPreviewOpen = false" class="text-gray-400 hover:text-gray-600 p-2 text-xl font-bold">
                        ✕
                    </button>
                </div>
                <div class="flex-1 w-full h-full relative bg-slate-50">
                    <VueFlow :nodes="previewNodes" :edges="previewEdges" :default-viewport="{ zoom: 0.9 }">
                        <Background pattern-color="#cbd5e1" gap="16" />
                        <Controls />
                    </VueFlow>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
