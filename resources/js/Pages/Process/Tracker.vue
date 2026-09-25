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
    myPendingTask: {
        type: Object,
        default: null,
    },
    followUp: {
        type: Object,
        default: null,
    },
    expenseWorkflowId: {
        type: Number,
        default: null,
    },
});

// Aktif Sekme Yönetimi: 'details' (varsayılan) | 'flow' | 'history'
const activeTab = ref('details');

const nodes = ref([]);
const edges = ref([]);

onMounted(() => {
    // Aktif bekleyen görevlerin node_id'lerini topla
    const activeNodeIds = (props.instance.tasks || []).map(t => t.node_id);

    // Düğümleri VueFlow formatına çevir
    nodes.value = (props.instance.workflow?.nodes || []).map(n => {
        const isProcessDone = ['completed', 'cancelled', 'rejected'].includes(props.instance.status);
        const isActiveNode = !isProcessDone && activeNodeIds.includes(n.id);
        
        return {
            id: n.id,
            type: 'custom',
            position: n.position || { x: 100, y: 100 },
            data: { 
                label: n.data?.label || n.data?.customName || n.label || 'Adım', 
                isActive: isActiveNode 
            },
            style: isActiveNode ? { border: '3px solid #10b981', boxShadow: '0 0 15px rgba(16, 185, 129, 0.5)', borderRadius: '8px' } : {}
        };
    });

    // Okları (Edges) çevir
    edges.value = (props.instance.workflow?.edges || []).map(e => ({
        id: e.id,
        source: e.source,
        target: e.target,
        animated: true,
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
    if (tasks.length === 0) return [];

    // Rol bazlı onaylarda kişileri değil yetki/rol unvanını göster
    const roles = [...new Set(tasks.map(t => t.assigned_role).filter(Boolean))];
    if (roles.length > 0) {
        return roles.map(r => r === 'Amir' ? 'Birim Amiri' : r === 'Müdür' ? 'Departman Müdürü' : r);
    }

    // Departman veya kullanıcı unvanı
    const names = [...new Set(tasks.map((task) => {
        if (task.assigned_user?.name) {
            return task.assigned_user.name;
        }
        return 'İlgili Birim';
    }))];

    return names;
};

const pendingAssigneesText = computed(() => {
    const assignees = getPendingAssignees();
    return assignees.length > 0 ? assignees.join(', ') : 'İlgili Operasyon Birimi';
});

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleString('tr-TR');
};

const isRejected = computed(() => props.instance.status === 'rejected');
const isCancelled = computed(() => props.instance.status === 'cancelled');
const isCompleted = computed(() => props.instance.status === 'completed');
const isActive = computed(() => !isCancelled.value && !isCompleted.value && !isRejected.value);

const isTravelWorkflow = computed(() => {
    const name = (props.instance.workflow?.name || '').toLocaleLowerCase('tr-TR');
    return name.includes('görev') || name.includes('seyahat') || name.includes('yolluk') || name.includes('harcırah');
});

const isSampleWorkflow = computed(() => {
    const name = (props.instance.workflow?.name || '').toLocaleLowerCase('tr-TR');
    return name.includes('numune') || name.includes('levha') || name.includes('kalite');
});

const currentActiveStepTitle = computed(() => {
    if (props.instance.tasks && props.instance.tasks.length > 0) {
        const t = props.instance.tasks[0];
        const matchingNode = (props.instance.workflow?.nodes || []).find(n => n.id === t.node_id);
        return matchingNode?.data?.customName || matchingNode?.data?.label || t.title || 'Onay ve Değerlendirme';
    }
    return 'İşlem ve Onay';
});

// Doğrusal Süreç Yol Haritası (Linear Stepper) Hesaplama
const processSteps = computed(() => {
    const rawNodes = props.instance.workflow?.nodes || [];
    const rawEdges = props.instance.workflow?.edges || [];
    const history = props.processHistory || [];
    const pendingTasks = props.instance.tasks || [];
    const activeNodeIds = pendingTasks.map(t => t.node_id);

    if (rawNodes.length === 0) return [];

    // 1. Başlangıç düğümünü bul
    const startNode = rawNodes.find(n => n.type === 'start' || n.data?.taskType === 'start') || rawNodes[0];

    // 2. Mutlu yol (happy path) oklarını filtrele
    const happyEdges = rawEdges.filter(e => {
        const edgeLabel = String(e.label || e.data?.label || '').toLowerCase();
        const targetNode = rawNodes.find(n => n.id === e.target);
        const targetLabel = String(targetNode?.data?.label || targetNode?.data?.customName || targetNode?.id || '').toLowerCase();
        return !edgeLabel.includes('reject') && !edgeLabel.includes('red') && !targetLabel.includes('reject') && !targetLabel.includes('red');
    });

    // 3. Sıralı yol oluştur
    const ordered = [];
    const visited = new Set();
    let current = startNode;

    while (current && !visited.has(current.id)) {
        visited.add(current.id);
        ordered.push(current);
        const nextEdge = happyEdges.find(e => e.source === current.id);
        if (nextEdge) {
            current = rawNodes.find(n => n.id === nextEdge.target);
        } else {
            break;
        }
    }

    // Eğer akış karmaşık veya kopuk ise pozisyon sırasına göre tamamla
    if (ordered.length < 2) {
        const sorted = [...rawNodes].sort((a, b) => (a.position?.x || 0) - (b.position?.x || 0));
        ordered.length = 0;
        ordered.push(...sorted);
    }

    // 4. Her bir düğümü adım objesine dönüştür
    return ordered.map((node, index) => {
        const label = node.data?.customName || node.data?.label || node.label || `Adım ${index + 1}`;
        const isStart = node.type === 'start' || node.data?.taskType === 'start' || index === 0;
        const isEnd = node.type === 'end' || node.data?.taskType === 'end' || index === ordered.length - 1;

        // Geçmişte tamamlanmış mı?
        const historyItem = history.find(h => {
            if (isStart && (h.action || '').includes('başlattı')) return true;
            if (h.node_id && h.node_id === node.id) return true;
            const hNode = (h.node_label || '').toLowerCase();
            const nLabel = label.toLowerCase();
            return hNode === nLabel;
        });

        const isCurrentlyActive = activeNodeIds.includes(node.id) || (!isCompleted.value && !isRejected.value && !isCancelled.value && props.instance.current_node_id === node.id);

        let status = 'upcoming'; // 'completed' | 'current' | 'rejected' | 'upcoming'
        if (isCompleted.value) {
            status = 'completed';
        } else if (isRejected.value && isCurrentlyActive) {
            status = 'rejected';
        } else if (isCancelled.value) {
            status = isCurrentlyActive ? 'cancelled' : (historyItem ? 'completed' : 'upcoming');
        } else if (isCurrentlyActive) {
            status = 'current';
        } else if (historyItem || isStart) {
            status = 'completed';
        }

        let subtitle = '';
        if (isStart) {
            subtitle = props.instance.starter?.name ? `Başlatan: ${props.instance.starter.name}` : 'Talep Başlangıcı';
        } else if (status === 'completed' && historyItem) {
            subtitle = `${historyItem.actor || 'Yetkili'} · ${formatDate(historyItem.at)}`;
        } else if (status === 'current') {
            const pendingTask = pendingTasks.find(t => t.node_id === node.id);
            if (pendingTask?.assigned_role) {
                const roleName = pendingTask.assigned_role === 'Amir' ? 'Birim Amiri' : pendingTask.assigned_role === 'Müdür' ? 'Departman Müdürü' : pendingTask.assigned_role;
                subtitle = `Bekleyen: ${roleName}`;
            } else if (pendingTask?.assigned_user?.name) {
                subtitle = `Bekleyen: ${pendingTask.assigned_user.name}`;
            } else {
                subtitle = 'İşlem Bekleniyor';
            }
        } else if (isEnd) {
            subtitle = isCompleted.value ? 'Süreç Tamamlandı' : 'Sonuç';
        } else {
            if (node.data?.assignedRole) {
                subtitle = `Sorumlu: ${Array.isArray(node.data.assignedRole) ? node.data.assignedRole.join(', ') : node.data.assignedRole}`;
            } else {
                subtitle = 'Planlanan Aşama';
            }
        }

        return {
            id: node.id,
            index: index + 1,
            label,
            status,
            subtitle,
            isStart,
            isEnd
        };
    });
});

const instanceFormData = computed(() => {
    const data = props.instance.data || {};
    return Object.fromEntries(
        Object.entries(data).filter(([key]) => !key.startsWith('_'))
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

// İşlem Geçmişi Arama & Filtreleme
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

const isEntryInDateRange = (entry) => {
    if (!entry?.at) return false;
    const entryDate = new Date(entry.at);
    const fromDate = historyDateFrom.value ? new Date(historyDateFrom.value) : null;
    const toDate = historyDateTo.value ? new Date(historyDateTo.value) : null;

    if (fromDate && entryDate < fromDate) return false;
    if (toDate && entryDate > toDate) return false;
    return true;
};

const filteredProcessHistory = computed(() => {
    const terms = historySearchTerms.value;

    return props.processHistory.filter((entry) => {
        if (hasHistoryDateFilter.value && !isEntryInDateRange(entry)) {
            return false;
        }
        if (!terms.length) return true;
        const haystack = historySearchableText(entry);
        return terms.every((term) => haystack.includes(term));
    });
});

const HISTORY_PAGE_SIZE = 8;
const historyPage = ref(1);

watch([filteredProcessHistory, historySearchTerms, historyDateFrom, historyDateTo], () => {
    historyPage.value = 1;
});

const totalHistoryPages = computed(() => {
    return Math.max(1, Math.ceil(filteredProcessHistory.value.length / HISTORY_PAGE_SIZE));
});

const paginatedProcessHistory = computed(() => {
    const start = (historyPage.value - 1) * HISTORY_PAGE_SIZE;
    return filteredProcessHistory.value.slice(start, start + HISTORY_PAGE_SIZE);
});

const goToHistoryPage = (page) => {
    if (page >= 1 && page <= totalHistoryPages.value) {
        historyPage.value = page;
    }
};
</script>

<template>
    <Head :title="`Talep #${instance.id} - ${instance.workflow.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <!-- Breadcrumbs -->
                    <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                        <Link :href="route('dashboard')" class="hover:text-indigo-600 transition-colors flex items-center gap-1">
                            <span>🏠</span> Ana Sayfa
                        </Link>
                        <span>/</span>
                        <Link :href="route('processes.history')" class="hover:text-indigo-600 transition-colors">
                            📁 Taleplerim
                        </Link>
                        <span>/</span>
                        <span class="text-gray-700 font-semibold">Talep #{{ instance.id }}</span>
                    </div>

                    <div class="flex items-center gap-3">
                        <h2 class="font-extrabold text-2xl text-gray-900 leading-tight">
                            {{ instance.workflow.name }}
                        </h2>
                        <span class="px-3 py-1 rounded-full text-xs font-bold font-mono bg-gray-100 text-gray-700 border border-gray-200">
                            #{{ instance.id }}
                        </span>
                    </div>
                </div>

                <!-- Hızlı Aksiyon Menüsü -->
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <a
                        v-if="isCompleted && isSampleWorkflow"
                        :href="route('processes.certificate', instance.id)"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-md hover:bg-indigo-700 transition-all transform hover:-translate-y-0.5"
                        title="Resmi Analiz ve Kalite Sertifikasını İndir (PDF)"
                    >
                        <span>📄</span> Analiz Sertifikası (PDF)
                    </a>

                    <a
                        v-if="isTravelWorkflow"
                        :href="route('processes.travel-order', instance.id)"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-md hover:bg-blue-700 transition-all transform hover:-translate-y-0.5"
                        title="Resmi Dış Görevlendirme ve Seyahat İzin Belgesini İndir (PDF)"
                    >
                        <span>📄</span> Dış Görev Belgesi (PDF)
                    </a>

                    <Link
                        v-if="isCompleted && isTravelWorkflow && expenseWorkflowId"
                        :href="route('processes.create', expenseWorkflowId)"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 px-4 py-2 text-sm font-bold text-white shadow-md transition-all transform hover:-translate-y-0.5"
                        title="Dış görev sonrası harcırah ve masraf kapatma formunu başlat"
                    >
                        <span>💳</span> Masraf Kapama Formu
                    </Link>

                    <button
                        v-if="canCancelProcess"
                        type="button"
                        @click="openCancelModal"
                        class="inline-flex items-center gap-1 rounded-xl bg-red-50 text-red-700 border border-red-200 px-3.5 py-2 text-xs font-bold hover:bg-red-100 transition-colors"
                    >
                        <span>🛑</span> Süreci İptal Et
                    </button>

                    <Link
                        :href="route('processes.history')"
                        class="inline-flex items-center gap-1 rounded-xl bg-white border border-gray-200 px-3.5 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 shadow-sm transition-colors"
                    >
                        <span>📁</span> Taleplerime Dön
                    </Link>

                    <Link
                        :href="route('processes.index')"
                        class="inline-flex items-center gap-1 rounded-xl bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-200 px-3.5 py-2 text-xs font-bold text-indigo-700 hover:bg-indigo-100 shadow-sm transition-colors"
                    >
                        <span>🚀</span> Yeni Talep
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- 1. AKILLI REHBERLİK & AKSİYON KARTI (HERO GUIDANCE BANNER) -->
                
                <!-- DURUM A: SIRA SİZDE! (Kullanıcının üzerinde bekleyen görev var) -->
                <div
                    v-if="myPendingTask"
                    class="rounded-2xl p-6 sm:p-7 bg-gradient-to-r from-emerald-600 via-teal-600 to-indigo-700 text-white shadow-xl shadow-emerald-700/20 border border-emerald-400/30 relative overflow-hidden"
                >
                    <div class="absolute -right-8 -bottom-8 w-48 h-48 rounded-full bg-white/10 blur-2xl pointer-events-none"></div>

                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
                        <div class="flex items-start gap-4">
                            <div class="w-14 h-14 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-3xl shrink-0 shadow-inner">
                                ⚡
                            </div>
                            <div>
                                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/25 text-white text-xs font-extrabold tracking-wide uppercase mb-2">
                                    <span class="w-2 h-2 rounded-full bg-amber-300 animate-ping"></span>
                                    SIRA SİZDE · AKSİYON BEKLENİYOR
                                </div>
                                <h3 class="text-xl sm:text-2xl font-black text-white">
                                    Bu Süreç Sizin Onayınızı / İşleminizi Bekliyor
                                </h3>
                                <p class="text-emerald-50 text-sm mt-1 max-w-2xl leading-relaxed">
                                    Bekleyen Göreviniz: <strong class="text-white underline">{{ myPendingTask.title || myPendingTask.node_label || 'Onay ve Form Değerlendirmesi' }}</strong>.
                                    Gerekli bilgileri girmek veya kararı bildirmek için hemen görevi açabilirsiniz.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 shrink-0">
                            <Link
                                :href="route('tasks.show', myPendingTask.id)"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-7 py-3.5 rounded-xl text-base font-black text-emerald-950 bg-white hover:bg-emerald-50 shadow-2xl hover:shadow-white/25 transform hover:-translate-y-0.5 active:translate-y-0 transition-all"
                            >
                                <span>👉 Göreve Git ve Tamamla</span>
                                <span class="text-xl leading-none">&rarr;</span>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- DURUM B: İŞLEM DEVAM EDİYOR (Başka birimde bekliyor) -->
                <div
                    v-else-if="isActive"
                    class="rounded-2xl p-6 sm:p-7 bg-gradient-to-r from-blue-600 via-indigo-600 to-slate-800 text-white shadow-xl shadow-blue-700/15 border border-blue-400/20 relative overflow-hidden"
                >
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
                        <div class="flex items-start gap-4">
                            <div class="w-14 h-14 rounded-2xl bg-white/10 backdrop-blur-md flex items-center justify-center text-3xl shrink-0">
                                ⏳
                            </div>
                            <div>
                                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/15 text-blue-100 text-xs font-semibold uppercase mb-2">
                                    <span class="w-2 h-2 rounded-full bg-blue-300 animate-pulse"></span>
                                    SÜREÇ İŞLEMDE · ONAY BEKLENİYOR
                                </div>
                                <h3 class="text-xl sm:text-2xl font-bold text-white">
                                    Talebiniz İlgili Birimde Değerlendiriliyor
                                </h3>
                                <p class="text-blue-100 text-sm mt-1 max-w-2xl leading-relaxed">
                                    Şu anda <strong class="text-white">{{ currentActiveStepTitle }}</strong> aşamasında.
                                    Sırada: <strong class="text-amber-300">{{ pendingAssigneesText }}</strong>.
                                    Yetkili işlem yaptığında süreç otomatik ilerleyecek ve size e-posta/bildirim iletilecektir.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <Link
                                v-if="myPendingTask"
                                :href="route('tasks.show', myPendingTask.id)"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold text-white bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 shadow-lg shadow-emerald-950/40 ring-2 ring-emerald-300 transition-all hover:scale-105"
                            >
                                ⚡ Görevi Aç ve Onayla →
                            </Link>
                            <Link
                                :href="route('processes.history')"
                                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold text-white bg-white/15 hover:bg-white/25 backdrop-blur-sm transition-all"
                            >
                                📁 Taleplerim
                            </Link>
                            <Link
                                :href="route('dashboard')"
                                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold text-white bg-white/15 hover:bg-white/25 backdrop-blur-sm transition-all"
                            >
                                🏠 Ana Sayfa
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- DURUM C: TAMAMLANDI -->
                <div
                    v-else-if="isCompleted"
                    class="rounded-2xl p-6 sm:p-7 bg-gradient-to-r from-emerald-600 to-teal-700 text-white shadow-xl shadow-emerald-700/20 border border-emerald-400/20 relative overflow-hidden"
                >
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
                        <div class="flex items-start gap-4">
                            <div class="w-14 h-14 rounded-2xl bg-white/15 backdrop-blur-md flex items-center justify-center text-3xl shrink-0">
                                🎉
                            </div>
                            <div>
                                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/20 text-white text-xs font-bold uppercase mb-2">
                                    ✓ SÜREÇ BAŞARIYLA TAMAMLANDI
                                </div>
                                <h3 class="text-xl sm:text-2xl font-black text-white">
                                    Tebrikler! Bu Talep Tüm Onaylardan Geçti
                                </h3>
                                <p class="text-emerald-100 text-sm mt-1 max-w-2xl leading-relaxed">
                                    Tüm operasyonel ve onay süreçleri başarıyla sonuçlandırılmıştır. Resmi analiz sertifikasına veya takip anketine aşağıdaki butonlardan ulaşabilirsiniz.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 shrink-0">
                            <a
                                v-if="isSampleWorkflow"
                                :href="route('processes.certificate', instance.id)"
                                target="_blank"
                                class="inline-flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-extrabold text-emerald-950 bg-white hover:bg-emerald-50 shadow-lg hover:shadow-xl transition-all"
                            >
                                <span>📄</span>
                                <span>Analiz Sertifikası (PDF)</span>
                            </a>

                            <a
                                v-if="isTravelWorkflow"
                                :href="route('processes.travel-order', instance.id)"
                                target="_blank"
                                class="inline-flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-extrabold text-blue-950 bg-white hover:bg-blue-50 shadow-lg hover:shadow-xl transition-all"
                            >
                                <span>📄</span>
                                <span>Dış Görev Belgesi (PDF)</span>
                            </a>

                            <Link
                                v-if="isTravelWorkflow && expenseWorkflowId"
                                :href="route('processes.create', expenseWorkflowId)"
                                class="inline-flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-black text-white bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 shadow-xl hover:shadow-orange-500/25 transition-all transform hover:-translate-y-0.5"
                            >
                                <span>💳</span>
                                <span>Harcırah & Masraf Formunu Başlat &rarr;</span>
                            </Link>

                            <Link
                                v-if="followUp"
                                :href="route('follow-ups.show', followUp.id)"
                                class="inline-flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-bold text-white bg-purple-600 hover:bg-purple-700 shadow-lg transition-all"
                            >
                                <span>⏱️</span>
                                <span>{{ followUp.status === 'pending' ? 'Sipariş Durumunu Bildir' : 'Takip Kaydı' }}</span>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- DURUM D: REDDEDİLDİ VEYA İPTAL EDİLDİ -->
                <div
                    v-else
                    class="rounded-2xl p-6 sm:p-7 bg-gradient-to-r from-red-600 to-rose-700 text-white shadow-xl shadow-red-700/20 border border-red-400/20 relative overflow-hidden"
                >
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
                        <div class="flex items-start gap-4">
                            <div class="w-14 h-14 rounded-2xl bg-white/15 backdrop-blur-md flex items-center justify-center text-3xl shrink-0">
                                🛑
                            </div>
                            <div>
                                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/20 text-white text-xs font-bold uppercase mb-2">
                                    {{ isRejected ? 'SÜREÇ REDDEDİLDİ' : 'SÜREÇ İPTAL EDİLDİ' }}
                                </div>
                                <h3 class="text-xl sm:text-2xl font-bold text-white">
                                    {{ isRejected ? 'Talep İlgili Birim Tarafından Reddedildi' : 'Talep Manuel Olarak İptal Edildi' }}
                                </h3>
                                <p v-if="cancellationInfo?.reason" class="text-red-100 text-sm mt-1 max-w-2xl leading-relaxed">
                                    <strong>Gerekçe:</strong> {{ cancellationInfo.reason }}
                                </p>
                                <p v-else class="text-red-100 text-sm mt-1 max-w-2xl leading-relaxed">
                                    Bu talep sonlandırılmıştır. Dilerseniz revize ederek yeni bir talep başlatabilirsiniz.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 shrink-0">
                            <Link
                                :href="route('processes.create', instance.workflow_id)"
                                class="inline-flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-bold text-red-950 bg-white hover:bg-red-50 shadow-lg transition-all"
                            >
                                <span>🔄</span>
                                <span>Yeni Talep Oluştur</span>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- 2. DOĞRUSAL SÜREÇ YOL HARİTASI (LINEAR PROCESS STEPPER) -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-6 sm:p-8 overflow-hidden">
                    <div class="flex items-center justify-between mb-6 border-b border-gray-100 pb-4">
                        <div>
                            <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                <span>🗺️</span> Süreç Yol Haritası ve İlerleme Durumu
                            </h3>
                            <p class="text-xs text-gray-500 mt-0.5">Talebin geçtiği ve sırada bekleyen aşamaları gösterir.</p>
                        </div>
                        <span class="text-xs font-semibold px-3 py-1 rounded-full" :class="isCompleted ? 'bg-green-100 text-green-800' : (isRejected ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800')">
                            {{ isCompleted ? 'Tamamlandı' : (isRejected ? 'Reddedildi' : (isCancelled ? 'İptal' : 'İlerliyor')) }}
                        </span>
                    </div>

                    <!-- Stepper Bar (Horizontal Scrollable for Mobile) -->
                    <div class="overflow-x-auto pb-4 pt-2">
                        <div class="flex items-center min-w-[700px] justify-between relative">
                            <!-- Connecting Line Background -->
                            <div class="absolute top-5 left-8 right-8 h-1 bg-gray-200 -z-0"></div>

                            <div
                                v-for="(step, index) in processSteps"
                                :key="step.id"
                                class="flex flex-col items-center text-center relative z-10 flex-1 px-2"
                            >
                                <!-- Step Circle Node -->
                                <div
                                    class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all duration-300"
                                    :class="[
                                        step.status === 'completed'
                                            ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/30 ring-4 ring-emerald-50'
                                            : (step.status === 'current'
                                                ? 'bg-amber-500 text-white shadow-xl shadow-amber-500/40 ring-4 ring-amber-100 animate-pulse'
                                                : (step.status === 'rejected'
                                                    ? 'bg-red-600 text-white ring-4 ring-red-50'
                                                    : 'bg-white border-2 border-gray-300 text-gray-400'))
                                    ]"
                                >
                                    <span v-if="step.status === 'completed'">✓</span>
                                    <span v-else-if="step.status === 'current'">⏳</span>
                                    <span v-else-if="step.status === 'rejected'">✕</span>
                                    <span v-else>{{ step.index }}</span>
                                </div>

                                <!-- Step Label -->
                                <div class="mt-3">
                                    <h4
                                        class="text-xs sm:text-sm font-bold line-clamp-2 max-w-[150px]"
                                        :class="step.status === 'current' ? 'text-amber-700' : (step.status === 'completed' ? 'text-gray-900' : 'text-gray-400')"
                                    >
                                        {{ step.label }}
                                    </h4>
                                    <p
                                        v-if="step.subtitle"
                                        class="text-[11px] mt-0.5 max-w-[150px] line-clamp-1"
                                        :class="step.status === 'current' ? 'text-amber-600 font-medium' : 'text-gray-400'"
                                    >
                                        {{ step.subtitle }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. KULLANICI DOSTU SEKMELİ İÇERİK (TABS) -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 overflow-hidden">
                    <!-- Sekme Başlıkları -->
                    <div class="flex items-center border-b border-gray-200 bg-gray-50/75 px-4 sm:px-6">
                        <button
                            type="button"
                            @click="activeTab = 'details'"
                            class="px-5 py-4 text-sm font-bold border-b-2 flex items-center gap-2 transition-all"
                            :class="activeTab === 'details' ? 'border-indigo-600 text-indigo-600 bg-white shadow-sm' : 'border-transparent text-gray-500 hover:text-gray-900 hover:bg-gray-100/50'"
                        >
                            <span>📋</span> Talep Bilgileri & Başvuru Detayları
                        </button>

                        <button
                            type="button"
                            @click="activeTab = 'flow'"
                            class="px-5 py-4 text-sm font-bold border-b-2 flex items-center gap-2 transition-all"
                            :class="activeTab === 'flow' ? 'border-indigo-600 text-indigo-600 bg-white shadow-sm' : 'border-transparent text-gray-500 hover:text-gray-900 hover:bg-gray-100/50'"
                        >
                            <span>🗺️</span> Canlı Akış Şeması (BPMN / Şema)
                        </button>

                        <button
                            type="button"
                            @click="activeTab = 'history'"
                            class="px-5 py-4 text-sm font-bold border-b-2 flex items-center gap-2 transition-all"
                            :class="activeTab === 'history' ? 'border-indigo-600 text-indigo-600 bg-white shadow-sm' : 'border-transparent text-gray-500 hover:text-gray-900 hover:bg-gray-100/50'"
                        >
                            <span>📜</span> İşlem Geçmişi & Onaylar ({{ processHistory.length }})
                        </button>
                    </div>

                    <!-- SEKME 1: TALEP BİLGİLERİ VE BAŞVURU DETAYLARI -->
                    <div v-show="activeTab === 'details'" class="p-6 sm:p-8 space-y-6">
                        <!-- Özet Kartları -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 rounded-xl bg-gray-50/80 border border-gray-100">
                            <div>
                                <span class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Talep Sahibi</span>
                                <span class="block text-sm font-bold text-gray-900 mt-0.5">{{ instance.starter?.name || 'Sistem' }}</span>
                                <span class="block text-xs text-gray-500">{{ instance.starter?.department?.name || 'Köksan' }}</span>
                            </div>
                            <div>
                                <span class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Başlangıç Zamanı</span>
                                <span class="block text-sm font-bold text-gray-900 mt-0.5">{{ formatDate(instance.created_at) }}</span>
                            </div>
                            <div>
                                <span class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Akış Şablonu</span>
                                <span class="block text-sm font-bold text-gray-900 mt-0.5 truncate">{{ instance.workflow?.name }}</span>
                                <span class="block text-xs text-gray-500 font-mono">{{ instance.workflow?.form_template?.code || 'Genel Süreç' }}</span>
                            </div>
                            <div>
                                <span class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Son Güncelleme</span>
                                <span class="block text-sm font-bold text-gray-900 mt-0.5">{{ formatDate(instance.updated_at) }}</span>
                            </div>
                        </div>

                        <!-- Form Verileri Grid -->
                        <div v-if="Object.keys(instanceFormData).length > 0">
                            <h4 class="text-sm font-bold text-gray-900 mb-3 flex items-center gap-2">
                                <span>📄</span> Başvuru Formunda Girilen Veriler
                            </h4>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <div
                                    v-for="(value, key) in instanceFormData"
                                    :key="key"
                                    class="bg-white p-3.5 rounded-xl border border-gray-100 shadow-sm hover:border-indigo-100 transition-colors"
                                >
                                    <span class="block text-xs font-semibold text-gray-400 mb-1">
                                        {{ getFieldLabel(key) }}
                                    </span>
                                    <span class="block text-sm font-bold text-gray-900 break-words">
                                        {{ value === false || value === 'false' || value === 0 ? 'Hayır' : (value === true || value === 'true' || value === 1 ? 'Evet' : (value || '-')) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-gray-400 text-sm">
                            Bu süreç için ek bir form alanı bulunmamaktadır.
                        </div>

                        <!-- SAP & Takip Durumu Varsa -->
                        <div v-if="followUp" class="mt-6 p-5 rounded-2xl bg-purple-50/60 border border-purple-100">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-purple-600 text-white flex items-center justify-center font-bold text-lg">
                                        ⏱️
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-purple-950">Numune Takip & Müşteri Geri Bildirimi</h4>
                                        <p class="text-xs text-purple-700">Bu numunenin siparişe dönüşüm ve takip süreci aktiftir.</p>
                                    </div>
                                </div>
                                <Link
                                    :href="route('follow-ups.show', followUp.id)"
                                    class="px-4 py-2 rounded-xl text-xs font-bold text-purple-700 bg-white border border-purple-200 hover:bg-purple-100 shadow-sm transition-colors"
                                >
                                    {{ followUp.status === 'pending' ? 'Durumu Bildir' : 'Detayları Gör' }} &rarr;
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- SEKME 2: CANLI AKIŞ ŞEMASI (VUEFLOW BPMN) -->
                    <div v-show="activeTab === 'flow'" class="p-6">
                        <div class="mb-3 flex items-center justify-between text-xs text-gray-500">
                            <p>Sürecin tüm adımlarını, karar noktalarını ve anlık konumunu şema üzerinde görebilirsiniz.</p>
                            <span class="inline-flex items-center gap-1.5 font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-200">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                Yeşil çerçeveli düğüm aktif adımdır
                            </span>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-gray-200 h-[520px] relative bg-slate-50">
                            <VueFlow
                                :nodes="nodes"
                                :edges="edges"
                                :nodes-draggable="false"
                                :nodes-connectable="false"
                                :zoom-on-scroll="false"
                                :default-viewport="{ zoom: 1 }"
                            >
                                <Background pattern-color="#cbd5e1" gap="16" />

                                <template #node-custom="flowProps">
                                    <div
                                        class="px-4 py-3 rounded-xl shadow-md border relative min-w-[160px] text-center font-bold bg-white transition-all"
                                        :class="flowProps.data.isActive ? 'bg-emerald-50 border-emerald-500 text-emerald-900 shadow-emerald-500/20' : 'border-gray-200 text-gray-700'"
                                    >
                                        <div class="text-xs sm:text-sm">{{ flowProps.data.label }}</div>
                                        <div v-if="flowProps.data.isActive" class="text-[11px] text-emerald-600 mt-1 animate-pulse font-extrabold">
                                            ⏳ ŞU AN BURADA
                                        </div>
                                    </div>
                                </template>
                            </VueFlow>
                        </div>
                    </div>

                    <!-- SEKME 3: İŞLEM GEÇMİŞİ (AUDIT LOG) -->
                    <div v-show="activeTab === 'history'" class="p-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                            <div>
                                <h4 class="text-base font-bold text-gray-900">Kronolojik İşlem Geçmişi</h4>
                                <p class="text-xs text-gray-500 mt-0.5">Süreç boyunca gerçekleştirilen tüm onay, form ve sistem aksiyonları.</p>
                            </div>

                            <div v-if="processHistory.length > 0" class="relative w-full sm:w-72">
                                <input
                                    v-model="historySearch"
                                    type="search"
                                    placeholder="Kişi, adım veya not ara..."
                                    class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-4 pr-9 text-xs text-gray-900 placeholder:text-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                                />
                            </div>
                        </div>

                        <div v-if="paginatedProcessHistory.length === 0" class="text-center py-10 text-gray-400 text-sm">
                            Kayıtlı bir işlem geçmişi bulunamadı.
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="(entry, idx) in paginatedProcessHistory"
                                :key="idx"
                                class="p-4 rounded-xl border border-gray-100 bg-white hover:bg-gray-50/60 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3 transition-colors"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">
                                        {{ idx + 1 }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-bold text-gray-900">{{ entry.actor || 'Yetkili' }}</span>
                                            <span class="text-xs px-2 py-0.5 rounded-md font-semibold bg-gray-100 text-gray-700">
                                                {{ entry.action }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-0.5">
                                            Adım: <strong class="text-gray-700">{{ entry.node_label }}</strong>
                                        </p>
                                        <p v-if="entry.comment" class="text-xs text-gray-700 mt-1 italic bg-amber-50/70 border border-amber-100 p-2 rounded-md">
                                            "{{ entry.comment }}"
                                        </p>
                                    </div>
                                </div>

                                <div class="text-left sm:text-right shrink-0">
                                    <span class="text-xs font-mono text-gray-400">{{ formatDate(entry.at) }}</span>
                                </div>
                            </div>

                            <!-- Sayfalama -->
                            <div v-if="totalHistoryPages > 1" class="flex justify-between items-center pt-4 border-t border-gray-100">
                                <span class="text-xs text-gray-500">
                                    Sayfa {{ historyPage }} / {{ totalHistoryPages }}
                                </span>
                                <div class="flex gap-1">
                                    <button
                                        v-for="p in totalHistoryPages"
                                        :key="p"
                                        @click="goToHistoryPage(p)"
                                        class="px-2.5 py-1 rounded text-xs font-bold"
                                        :class="p === historyPage ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                    >
                                        {{ p }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- İPTAL ONAY MODALI -->
        <div
            v-if="showCancelModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm"
            @click.self="showCancelModal = false"
        >
            <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl border border-gray-100 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="text-lg font-bold text-gray-900">Süreci İptal Et</h3>
                    <p class="text-xs text-gray-500 mt-1">
                        Bu işlem geri alınamaz. Bekleyen tüm görevler ve onay adımları kapatılır.
                    </p>
                </div>
                <div class="p-6">
                    <label class="block text-xs font-semibold text-gray-700 mb-2">İptal Gerekçesi (Opsiyonel)</label>
                    <textarea
                        v-model="cancelForm.reason"
                        rows="4"
                        class="w-full rounded-xl border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 text-sm"
                        placeholder="İptal nedenini belirtebilirsiniz..."
                    />
                    <p v-if="cancelForm.errors.reason" class="text-xs text-red-600 mt-2">{{ cancelForm.errors.reason }}</p>
                </div>
                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-end gap-3">
                    <button
                        type="button"
                        @click="showCancelModal = false"
                        class="px-4 py-2 text-xs font-bold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-100"
                    >
                        Vazgeç
                    </button>
                    <button
                        type="button"
                        @click="submitCancel"
                        :disabled="cancelForm.processing"
                        class="px-5 py-2 text-xs font-bold text-white bg-red-600 rounded-xl hover:bg-red-700 shadow-sm disabled:opacity-50"
                    >
                        {{ cancelForm.processing ? 'İptal Ediliyor...' : 'Evet, Süreci İptal Et' }}
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
