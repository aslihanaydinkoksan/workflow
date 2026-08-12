<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { VueFlow } from '@vue-flow/core';
import { Background } from '@vue-flow/background';

import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';

const props = defineProps({
    instance: Object,
    processHistory: {
        type: Array,
        default: () => [],
    },
    canCancelProcess: {
        type: Boolean,
        default: false,
    },
});

const nodes = ref([]);
const edges = ref([]);

onMounted(() => {
    // Aktif bekleyen görevlerin node_id'lerini topla
    const activeNodeIds = (props.instance.tasks || []).map(t => t.node_id);

    // Düğümleri VueFlow formatına çevir
    nodes.value = props.instance.workflow.nodes.map(n => {
        // UX Düzeltmesi: Süreç reddedildiğinde de aktif animasyon dursun
        const isProcessDone = ['completed', 'cancelled', 'rejected'].includes(props.instance.status);
        const isActiveNode = !isProcessDone && activeNodeIds.includes(n.id);
        
        return {
            id: n.id,
            type: 'custom',
            position: n.position,
            data: { 
                label: n.data?.label || n.data?.customName || n.label || 'Adım', 
                isActive: isActiveNode 
            },
            // Aktif düğümü vurgula
            style: isActiveNode ? { border: '3px solid #10b981', boxShadow: '0 0 15px rgba(16, 185, 129, 0.5)', borderRadius: '6px' } : {}
        };
    });

    // Okları (Edges) çevir
    edges.value = props.instance.workflow.edges.map(e => ({
        id: e.id,
        source: e.source,
        target: e.target,
        animated: true, // Akış animasyonu
        style: { stroke: '#94a3b8', strokeWidth: 2 }
    }));
});

const getFieldLabel = (key) => {
    const schema = props.instance.workflow?.form_template?.schema;
    if (!schema) return key;
    
    const findLabel = (elements) => {
        for (const el of elements) {
            if (el.id === key) return el.label || key;
            if (el.elements && Array.isArray(el.elements)) {
                const found = findLabel(el.elements);
                if (found !== key) return found;
            }
        }
        return key;
    };
    
    return findLabel(schema);
};

const getPendingAssignees = () => {
    const tasks = props.instance.tasks || [];

    return tasks.map((task) => {
        if (task.assigned_user?.name) {
            return task.assigned_user.name;
        }
        if (task.assigned_role) {
            return `${task.assigned_role} (rol)`;
        }
        return 'Atanmamış';
    });
};

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleString('tr-TR');
};

const historySearch = ref('');
const historyDateFrom = ref('');
const historyDateTo = ref('');

const historySearchableText = (entry) => {
    return [
        entry.actor,
        entry.action,
        entry.node_label,
        entry.comment,
        formatDate(entry.at),
    ]
        .filter(Boolean)
        .join(' ')
        .toLocaleLowerCase('tr-TR');
};

const historySearchTerms = computed(() => {
    return historySearch.value
        .trim()
        .toLocaleLowerCase('tr-TR')
        .split(/\s+/)
        .filter(Boolean);
});

const hasHistoryDateFilter = computed(() => {
    return Boolean(historyDateFrom.value || historyDateTo.value);
});

const hasHistoryFilters = computed(() => {
    return historySearchTerms.value.length > 0 || hasHistoryDateFilter.value;
});

const parseHistoryDate = (value) => {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
};

const isEntryInDateRange = (entry) => {
    const entryDate = parseHistoryDate(entry.at);

    if (!entryDate) {
        return false;
    }

    const fromDate = parseHistoryDate(historyDateFrom.value);
    const toDate = parseHistoryDate(historyDateTo.value);

    if (fromDate && entryDate < fromDate) {
        return false;
    }

    if (toDate && entryDate > toDate) {
        return false;
    }

    return true;
};

const filteredProcessHistory = computed(() => {
    const terms = historySearchTerms.value;

    return props.processHistory.filter((entry) => {
        if (hasHistoryDateFilter.value && !isEntryInDateRange(entry)) {
            return false;
        }

        if (!terms.length) {
            return true;
        }

        const haystack = historySearchableText(entry);
        return terms.every((term) => haystack.includes(term));
    });
});

const clearHistorySearch = () => {
    historySearch.value = '';
};

const clearHistoryDateFilter = () => {
    historyDateFrom.value = '';
    historyDateTo.value = '';
};

const clearHistoryFilters = () => {
    clearHistorySearch();
    clearHistoryDateFilter();
};

const HISTORY_PAGE_SIZE = 8;
const historyPage = ref(1);

watch([filteredProcessHistory, historySearchTerms, historyDateFrom, historyDateTo], () => {
    historyPage.value = 1;
});

const totalHistoryPages = computed(() => {
    return Math.max(1, Math.ceil(filteredProcessHistory.value.length / HISTORY_PAGE_SIZE));
});

watch(totalHistoryPages, (pages) => {
    if (historyPage.value > pages) {
        historyPage.value = pages;
    }
});

const paginatedProcessHistory = computed(() => {
    const start = (historyPage.value - 1) * HISTORY_PAGE_SIZE;

    return filteredProcessHistory.value.slice(start, start + HISTORY_PAGE_SIZE);
});

const historyPageInfo = computed(() => {
    const total = filteredProcessHistory.value.length;

    if (total === 0) {
        return { start: 0, end: 0, total: 0 };
    }

    const start = (historyPage.value - 1) * HISTORY_PAGE_SIZE + 1;
    const end = Math.min(historyPage.value * HISTORY_PAGE_SIZE, total);

    return { start, end, total };
});

const visibleHistoryPages = computed(() => {
    const total = totalHistoryPages.value;
    const current = historyPage.value;
    const pages = new Set([1, total, current - 1, current, current + 1]);

    return [...pages]
        .filter((page) => page >= 1 && page <= total)
        .sort((a, b) => a - b);
});

const goToHistoryPage = (page) => {
    if (page >= 1 && page <= totalHistoryPages.value) {
        historyPage.value = page;
    }
};

const isRejected = computed(() => props.instance.status === 'rejected');
const isCancelled = computed(() => props.instance.status === 'cancelled');
const isCompleted = computed(() => props.instance.status === 'completed');
const isActive = computed(() => !isCancelled.value && !isCompleted.value && !isRejected.value);

const statusLabel = computed(() => {
    if (isRejected.value) {
        return 'Reddedildi / İptal Edildi';
    }
    if (isCancelled.value) {
        return 'Manuel İptal Edildi';
    }
    if (isCompleted.value) {
        return 'Tamamlandı / Onaylandı';
    }
    return 'Devam Ediyor (Görev Bekliyor)';
});

const statusBorderClass = computed(() => {
    if (isRejected.value) {
        return 'border-red-500';
    }
    if (isCancelled.value) {
        return 'border-gray-400';
    }
    if (isCompleted.value) {
        return 'border-green-500';
    }
    return 'border-blue-500';
});

const statusTextClass = computed(() => {
    if (isRejected.value) {
        return 'text-red-600';
    }
    if (isCancelled.value) {
        return 'text-orange-600';
    }
    if (isCompleted.value) {
        return 'text-green-600';
    }
    return 'text-blue-600';
});

const instanceFormData = computed(() => {
    const data = props.instance.data || {};

    return Object.fromEntries(
        Object.entries(data).filter(([key]) => ! key.startsWith('_'))
    );
});

const cancellationInfo = computed(() => props.instance.data?._cancellation ?? null);

const showCancelModal = ref(false);

const cancelForm = useForm({
    reason: '',
});

const openCancelModal = () => {
    cancelForm.reset();
    cancelForm.clearErrors();
    showCancelModal.value = true;
};

const submitCancel = () => {
    cancelForm.post(route('processes.cancel', props.instance.id), {
        preserveScroll: true,
        onSuccess: () => {
            showCancelModal.value = false;
        },
    });
};
</script>

<template>
    <Head :title="`Süreç Takibi #${instance.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Süreç Takibi: {{ instance.workflow.name }} (No: #{{ instance.id }})
                </h2>
                <div class="flex items-center gap-3 shrink-0">
                    <button
                        v-if="canCancelProcess"
                        type="button"
                        @click="openCancelModal"
                        class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-red-700 transition-colors"
                    >
                        Süreci İptal Et
                    </button>
                    <Link :href="route('processes.history')" class="text-sm text-gray-600 hover:text-gray-900">
                        &larr; Geri Dön
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Durum Kartı -->
                <div class="bg-white p-4 rounded-lg shadow-sm mb-6 flex justify-between items-center border-l-4"
                     :class="statusBorderClass">
                    <div>
                        <span class="text-sm text-gray-500 block">Süreç Durumu</span>
                        <span class="text-lg font-bold" :class="statusTextClass">
                            {{ statusLabel }}
                        </span>
                        <div v-if="isActive && getPendingAssignees().length" class="text-sm text-gray-700 mt-2">
                            <span class="font-medium text-gray-500">Sırada:</span>
                            {{ getPendingAssignees().join(', ') }}
                        </div>
                        <p v-if="cancellationInfo?.reason" class="text-sm text-red-700 mt-2">
                            <span class="font-medium">İptal gerekçesi:</span> {{ cancellationInfo.reason }}
                        </p>
                    </div>
                    <div class="text-right">
                        <span class="text-sm text-gray-500 block">Başlangıç</span>
                        <span class="text-md font-semibold text-gray-800">{{ new Date(instance.created_at).toLocaleString() }}</span>
                    </div>
                </div>

                <!-- Başvuru Detayları (Form Verileri) -->
                <div v-if="Object.keys(instanceFormData).length > 0" class="bg-white p-6 rounded-lg shadow-sm mb-6 border border-gray-200">
                    <h3 class="text-md font-semibold text-gray-800 border-b pb-2 mb-4">Başvuru Detayları</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-for="(value, key) in instanceFormData" :key="key" class="bg-gray-50 p-3 rounded border border-gray-100">
                            <span class="block text-xs text-gray-500 font-medium mb-1">{{ getFieldLabel(key) }}</span>
                            <span class="block text-sm text-gray-900 font-medium">
                                {{ value === false || value === 'false' || value === 0 ? 'Hayır' : (value === true || value === 'true' || value === 1 ? 'Evet' : (value || '-')) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Vue Flow Harita -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg h-[500px] border border-gray-200 relative">
                    <VueFlow :nodes="nodes" :edges="edges" :nodes-draggable="false" :nodes-connectable="false" :zoom-on-scroll="false" :default-viewport="{ zoom: 1 }">
                        <Background pattern-color="#aaa" gap="15" />

                        <template #node-custom="props">
                            <div class="px-4 py-3 rounded shadow-md border border-gray-300 relative min-w-[150px] text-center font-semibold bg-white"
                                 :class="props.data.isActive ? 'bg-green-50 text-green-800' : 'text-gray-700'">
                                <div class="text-sm">{{ props.data.label }}</div>
                                <div v-if="props.data.isActive" class="text-xs text-green-600 mt-1 animate-pulse font-bold">
                                    ⏳ ŞU AN BURADA
                                </div>
                            </div>
                        </template>

                    </VueFlow>
                </div>

                <!-- İşlem Geçmişi -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6 border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="text-md font-semibold text-gray-800">İşlem Geçmişi</h3>
                                <p class="text-xs text-gray-500 mt-1">Akıştaki işlemler zamana göre sıralıdır.</p>
                            </div>
                            <div v-if="processHistory.length > 0" class="relative w-full sm:w-80 shrink-0">
                                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <input
                                    v-model="historySearch"
                                    type="search"
                                    placeholder="Kişi, işlem, adım veya not ara..."
                                    class="w-full rounded-lg border border-gray-200 bg-white py-2 pl-9 pr-9 text-sm text-gray-900 placeholder:text-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                />
                                <button
                                    v-if="historySearch"
                                    type="button"
                                    @click="clearHistorySearch"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                    title="Aramayı temizle"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="processHistory.length > 0"
                            class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto] gap-3 items-end"
                        >
                            <div>
                                <label class="block text-[11px] font-medium text-gray-500 mb-1">Başlangıç tarihi</label>
                                <input
                                    v-model="historyDateFrom"
                                    type="datetime-local"
                                    class="w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                />
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-gray-500 mb-1">Bitiş tarihi</label>
                                <input
                                    v-model="historyDateTo"
                                    type="datetime-local"
                                    class="w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                />
                            </div>
                            <button
                                v-if="hasHistoryDateFilter"
                                type="button"
                                @click="clearHistoryDateFilter"
                                class="text-sm text-indigo-600 hover:text-indigo-800 font-medium py-2 px-1"
                            >
                                Tarihi temizle
                            </button>
                        </div>

                        <p
                            v-if="filteredProcessHistory.length > 0"
                            class="text-xs text-gray-500 mt-3"
                        >
                            <template v-if="hasHistoryFilters">
                                <span class="text-indigo-600">{{ filteredProcessHistory.length }} / {{ processHistory.length }} kayıt</span>
                                <span class="mx-1">·</span>
                            </template>
                            {{ historyPageInfo.start }}-{{ historyPageInfo.end }} / {{ historyPageInfo.total }} gösteriliyor
                        </p>
                    </div>

                    <div v-if="processHistory.length === 0" class="p-8 text-center text-sm text-gray-500">
                        Henüz kayıtlı bir işlem bulunmuyor.
                    </div>

                    <div v-else-if="filteredProcessHistory.length === 0" class="p-8 text-center text-sm text-gray-500">
                        <p>Seçtiğiniz filtrelere uygun işlem bulunamadı.</p>
                        <button
                            type="button"
                            @click="clearHistoryFilters"
                            class="mt-2 text-indigo-600 hover:text-indigo-800 font-medium"
                        >
                            Filtreleri temizle
                        </button>
                    </div>

                    <div v-else>
                        <div class="divide-y divide-gray-100">
                            <div
                                v-for="(entry, index) in paginatedProcessHistory"
                                :key="`${entry.at}-${index}`"
                                class="px-6 py-4 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-semibold text-gray-900">{{ entry.actor }}</span>
                                        <span class="text-sm text-gray-600">{{ entry.action }}</span>
                                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 border border-indigo-100">
                                            {{ entry.node_label }}
                                        </span>
                                    </div>
                                    <p v-if="entry.comment" class="text-sm text-gray-500 mt-2 italic whitespace-pre-wrap">
                                        "{{ entry.comment }}"
                                    </p>
                                </div>
                                <div class="text-sm text-gray-500 whitespace-nowrap shrink-0">
                                    {{ formatDate(entry.at) }}
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="totalHistoryPages > 1"
                            class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                        >
                            <p class="text-xs text-gray-500">
                                Sayfa {{ historyPage }} / {{ totalHistoryPages }}
                            </p>
                            <nav class="inline-flex rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden" aria-label="İşlem geçmişi sayfaları">
                                <button
                                    type="button"
                                    @click="goToHistoryPage(historyPage - 1)"
                                    :disabled="historyPage <= 1"
                                    class="px-3 py-2 text-sm border-r border-gray-200 transition-colors"
                                    :class="historyPage <= 1 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-50'"
                                >
                                    &laquo; Önceki
                                </button>
                                <template v-for="(page, pageIndex) in visibleHistoryPages" :key="page">
                                    <span
                                        v-if="pageIndex > 0 && page - visibleHistoryPages[pageIndex - 1] > 1"
                                        class="px-3 py-2 text-sm text-gray-400 border-r border-gray-200"
                                    >
                                        …
                                    </span>
                                    <button
                                        type="button"
                                        @click="goToHistoryPage(page)"
                                        class="min-w-[2.5rem] px-3 py-2 text-sm border-r border-gray-200 transition-colors"
                                        :class="page === historyPage ? 'bg-indigo-50 text-indigo-600 font-semibold' : 'text-gray-600 hover:bg-gray-50'"
                                    >
                                        {{ page }}
                                    </button>
                                </template>
                                <button
                                    type="button"
                                    @click="goToHistoryPage(historyPage + 1)"
                                    :disabled="historyPage >= totalHistoryPages"
                                    class="px-3 py-2 text-sm transition-colors"
                                    :class="historyPage >= totalHistoryPages ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-50'"
                                >
                                    Sonraki &raquo;
                                </button>
                            </nav>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- İptal Onay Modalı -->
        <div
            v-if="showCancelModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm"
            @click.self="showCancelModal = false"
        >
            <div class="w-full max-w-md rounded-xl bg-white shadow-2xl border border-gray-100">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-lg font-bold text-gray-900">Süreci İptal Et</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Bu işlem geri alınamaz. Bekleyen tüm görevler kapatılır.
                    </p>
                </div>
                <div class="px-6 py-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">İptal gerekçesi (opsiyonel)</label>
                    <textarea
                        v-model="cancelForm.reason"
                        rows="4"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 text-sm"
                        placeholder="İptal nedenini yazabilirsiniz..."
                    />
                    <p v-if="cancelForm.errors.reason" class="text-sm text-red-600 mt-2">{{ cancelForm.errors.reason }}</p>
                    <p v-if="cancelForm.errors.process" class="text-sm text-red-600 mt-2">{{ cancelForm.errors.process }}</p>
                </div>
                <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                    <button
                        type="button"
                        @click="showCancelModal = false"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                    >
                        Vazgeç
                    </button>
                    <button
                        type="button"
                        @click="submitCancel"
                        :disabled="cancelForm.processing"
                        class="px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50"
                    >
                        {{ cancelForm.processing ? 'İptal ediliyor...' : 'Evet, İptal Et' }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
.vue-flow__wrapper {
  width: 100%;
  height: 100%;
}
</style>
