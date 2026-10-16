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
    terminSla: {
        type: Object,
        default: null,
    },
    terminRevisions: {
        type: Array,
        default: () => [],
    },
    terminCategories: {
        type: Object,
        default: () => ({}),
    },
    canRescheduleTermin: {
        type: Boolean,
        default: false,
    },
    fieldAudits: {
        type: Array,
        default: () => [],
    },
    isAdmin: {
        type: Boolean,
        default: false,
    },
    allUsers: {
        type: Array,
        default: () => [],
    },
    workflowNodes: {
        type: Array,
        default: () => [],
    },
    leadTimeAnalytics: {
        type: Object,
        default: () => null,
    },
    budgetControl: {
        type: Object,
        default: () => null,
    },
});

// Aktif Sekme Yönetimi: 'details' (varsayılan) | 'flow' | 'history' | 'lead_time' | 'sla_history' | 'audits'
const activeTab = ref('details');

// Yönetici Müdahale Kokpiti State
const showReassignModal = ref(false);
const reassignForm = useForm({
    task_id: null,
    new_user_id: '',
    reason: '',
});

const showJumpNodeModal = ref(false);
const jumpNodeForm = useForm({
    target_node_id: '',
    reason: '',
});

const showForceProgressModal = ref(false);
const forceProgressForm = useForm({
    task_id: null,
    reason: '',
});

const openReassignModal = (taskId = null) => {
    reassignForm.reset();
    reassignForm.task_id = taskId || (props.instance.tasks && props.instance.tasks[0]?.id) || null;
    showReassignModal.value = true;
};

const submitReassign = () => {
    reassignForm.post(route('processes.admin.reassign', props.instance.id), {
        preserveScroll: true,
        onSuccess: () => {
            showReassignModal.value = false;
        },
    });
};

const openJumpNodeModal = () => {
    jumpNodeForm.reset();
    showJumpNodeModal.value = true;
};

const submitJumpNode = () => {
    jumpNodeForm.post(route('processes.admin.jump-node', props.instance.id), {
        preserveScroll: true,
        onSuccess: () => {
            showJumpNodeModal.value = false;
        },
    });
};

const openForceProgressModal = (taskId = null) => {
    forceProgressForm.reset();
    forceProgressForm.task_id = taskId || (props.instance.tasks && props.instance.tasks[0]?.id) || null;
    showForceProgressModal.value = true;
};

const submitForceProgress = () => {
    forceProgressForm.post(route('processes.admin.force-progress', props.instance.id), {
        preserveScroll: true,
        onSuccess: () => {
            showForceProgressModal.value = false;
        },
    });
};

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

const hasPendingTasks = computed(() => (props.instance.tasks || []).some(t => t.status === 'pending'));
const isCompleted = computed(() => props.instance.status === 'completed');
const isCancelled = computed(() => props.instance.status === 'cancelled');
const isRejected = computed(() => props.instance.status === 'rejected' && !hasPendingTasks.value);
const isActive = computed(() => hasPendingTasks.value || (!isCancelled.value && !isCompleted.value && !isRejected.value));


const isTravelWorkflow = computed(() => {
    const name = (props.instance.workflow?.name || '').toLocaleLowerCase('tr-TR');
    return name.includes('görev') || name.includes('seyahat') || name.includes('yolluk') || name.includes('harcırah');
});

const isSampleWorkflow = computed(() => {
    const name = (props.instance.workflow?.name || '').toLocaleLowerCase('tr-TR');
    return (name.includes('numune') || name.includes('levha')) && !name.includes('döf');
});

const isMaintenanceWorkflow = computed(() => {
    const name = (props.instance.workflow?.name || '').toLocaleLowerCase('tr-TR');
    return name.includes('bakım') || name.includes('tpm') || name.includes('arıza');
});

const isDofWorkflow = computed(() => {
    const name = (props.instance.workflow?.name || '').toLocaleLowerCase('tr-TR');
    return name.includes('döf') || name.includes('dof') || name.includes('capa') || name.includes('8d');
});

const isPurchaseWorkflow = computed(() => {
    const name = (props.instance.workflow?.name || '').toLocaleLowerCase('tr-TR');
    return name.includes('satın alma') || name.includes('malzeme');
});

const isRevisionTask = computed(() => {
    if (!props.myPendingTask) return false;
    if (props.myPendingTask.is_revision) return true;
    if (props.instance.data?.isletme_karari === 'Revizyon İstendi') return true;
    const title = (props.myPendingTask.title || props.myPendingTask.node_label || '').toLowerCase();
    if (title.includes('reviz')) return true;
    if (String(props.myPendingTask.node_id) === '1' || String(props.myPendingTask.node_id) === 'node_start') {
        return (props.processHistory || []).some(h => (h.action || '').toLowerCase().includes('reviz')) ||
               !!props.instance.data?.isletme_notu;
    }
    return false;
});

const isRevisionRequired = computed(() => {
    return isRevisionTask.value ||
        props.instance.data?.isletme_karari === 'Revizyon İstendi' ||
        (props.processHistory || []).some(h => (h.action || '').toLowerCase().includes('reviz') && props.instance.status === 'waiting');
});

const revisionNote = computed(() => {
    return props.myPendingTask?.latest_revision_note
        || props.instance.data?.isletme_notu
        || (props.processHistory || []).find(h => (h.action || '').toLowerCase().includes('reviz') && h.comment)?.comment
        || null;
});

const revisionAuthor = computed(() => {
    return props.myPendingTask?.latest_revision_user
        || (props.processHistory || []).find(h => (h.action || '').toLowerCase().includes('reviz'))?.actor
        || null;
});

const currentActiveStepTitle = computed(() => {
    if (props.instance.tasks && props.instance.tasks.length > 0) {
        const t = props.instance.tasks[0];
        const matchingNode = (props.instance.workflow?.nodes || []).find(n => n.id === t.node_id);
        return matchingNode?.data?.customName || matchingNode?.data?.label || t.title || 'Onay ve Değerlendirme';
    }
    return 'İşlem ve Onay';
});

const formatDateTR = (dateStr) => {
    if (!dateStr) return '';
    try {
        const parts = String(dateStr).split('-');
        if (parts.length === 3 && parts[0].length === 4) {
            return `${parts[2]}.${parts[1]}.${parts[0]}`;
        }
        return dateStr;
    } catch {
        return dateStr;
    }
};

const formatFieldValue = (val) => {
    if (val === false || val === 'false' || val === 0) return 'Hayır';
    if (val === true || val === 'true' || val === 1) return 'Evet';
    if (!val) return '-';

    if (Array.isArray(val)) {
        const formatted = val.map(item => {
            if (typeof item === 'object' && item !== null) {
                if (item.start || item.end) {
                    const s = item.start ? formatDateTR(item.start) : '';
                    const e = item.end ? formatDateTR(item.end) : '';
                    const n = item.note ? ` (${item.note})` : '';
                    return (s && e ? `📅 ${s} - ${e}` : (s ? `📅 Başlangıç: ${s}` : `📅 Bitiş: ${e}`)) + n;
                }
                return Object.values(item).filter(Boolean).join(' - ');
            }
            return String(item);
        }).filter(Boolean);
        return formatted.length > 0 ? formatted.join(', ') : '-';
    }

    if (typeof val === 'string') {
        const trimmed = val.trim();
        if (trimmed.startsWith('[{') && trimmed.endsWith('}]')) {
            try {
                const parsed = JSON.parse(trimmed);
                return formatFieldValue(parsed);
            } catch (e) {
                // keep string
            }
        }
    }

    return String(val);
};

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
        } else if (isRejected.value && (isCurrentlyActive || props.instance.current_node_id === node.id)) {
            status = 'rejected';
        } else if (isCancelled.value) {
            status = isCurrentlyActive ? 'cancelled' : (historyItem ? 'completed' : 'upcoming');
        } else if (isCurrentlyActive) {
            status = 'current';
        } else if (historyItem || isStart) {
            status = 'completed';
        }

        let displayLabel = label;
        let subtitle = '';
        if (isStart) {
            if (status === 'current' && isRevisionRequired.value) {
                displayLabel = 'Talebi Revize Et';
                subtitle = '🔄 Revizyon Bekleniyor';
            } else {
                subtitle = props.instance.starter?.name ? `Başlatan: ${props.instance.starter.name}` : 'Talep Başlangıcı';
            }
        } else if (status === 'completed' && historyItem) {
            subtitle = `${historyItem.actor || 'Yetkili'} · ${formatDate(historyItem.at)}`;
        } else if (status === 'current') {
            const pendingForThisNode = pendingTasks.filter(t => t.node_id === node.id);
            if (pendingForThisNode.length > 1) {
                const names = pendingForThisNode.map(t => t.assigned_user?.name || t.assigned_role || 'Yetkili').filter(Boolean);
                const policyBadge = node.data?.approvalPolicy === 'all' ? '🤝 Konsensüs' : '⚡ İlk Onay';
                subtitle = `${policyBadge}: ${names.join(', ')}`;
            } else if (pendingForThisNode.length === 1) {
                const pendingTask = pendingForThisNode[0];
                if (pendingTask?.assigned_role) {
                    const roleName = pendingTask.assigned_role === 'Amir' ? 'Birim Amiri' : pendingTask.assigned_role === 'Müdür' ? 'Departman Müdürü' : pendingTask.assigned_role;
                    subtitle = `Bekleyen: ${roleName}`;
                } else if (pendingTask?.assigned_user?.name) {
                    subtitle = `Bekleyen: ${pendingTask.assigned_user.name}`;
                } else {
                    subtitle = 'İşlem Bekleniyor';
                }
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
            label: displayLabel,
            status,
            subtitle,
            isStart,
            isEnd,
            isRevision: isStart && status === 'current' && isRevisionRequired.value
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

// --- ENTERPRISE TERMIN & SLA YÖNETİMİ ---
const showRescheduleModal = ref(false);
const rescheduleForm = useForm({
    new_termin: '',
    reason_category: 'raw_material',
    reason_note: '',
});

const openRescheduleModal = () => {
    rescheduleForm.new_termin = props.terminSla?.termin_text || '';
    rescheduleForm.reason_category = 'raw_material';
    rescheduleForm.reason_note = '';
    showRescheduleModal.value = true;
};

const closeRescheduleModal = () => {
    showRescheduleModal.value = false;
    rescheduleForm.reset();
};

const applyReschedulePreset = (days) => {
    const today = new Date();
    const target = new Date();
    target.setDate(today.getDate() + days);
    
    const pad = (n) => String(n).padStart(2, '0');
    const todayStr = `${pad(today.getDate())}.${pad(today.getMonth() + 1)}.${today.getFullYear()}`;
    const targetStr = `${pad(target.getDate())}.${pad(target.getMonth() + 1)}.${target.getFullYear()}`;
    
    rescheduleForm.new_termin = `${todayStr} - ${targetStr}`;
};

const submitReschedule = () => {
    rescheduleForm.post(route('processes.reschedule-termin', props.instance.id), {
        preserveScroll: true,
        onSuccess: () => {
            showRescheduleModal.value = false;
        },
    });
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

                    <a
                        v-if="isMaintenanceWorkflow"
                        :href="route('processes.maintenance-order', instance.id)"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-orange-600 px-4 py-2 text-sm font-bold text-white shadow-md hover:bg-orange-700 transition-all transform hover:-translate-y-0.5"
                        title="Resmi Makine Arıza & Bakım İş Emrini İndir (PDF)"
                    >
                        <span>🛠️</span> Bakım Fişi (PDF)
                    </a>

                    <a
                        v-if="isDofWorkflow"
                        :href="route('processes.dof-report', instance.id)"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-purple-600 px-4 py-2 text-sm font-bold text-white shadow-md hover:bg-purple-700 transition-all transform hover:-translate-y-0.5"
                        title="Resmi DÖF / 8D Kalite Raporunu İndir (PDF)"
                    >
                        <span>🔬</span> DÖF Raporu (PDF)
                    </a>

                    <a
                        v-if="isPurchaseWorkflow"
                        :href="route('processes.purchase-order', instance.id)"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-md hover:bg-emerald-700 transition-all transform hover:-translate-y-0.5"
                        title="Resmi Malzeme Satın Alma Talep Fişini İndir (PDF)"
                    >
                        <span>🛒</span> Satın Alma Fişi (PDF)
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
                
                <!-- DURUM 0: SIRA SİZDE & REVİZYON TALEBİ -->
                <div
                    v-if="isRevisionTask"
                    class="rounded-2xl p-6 sm:p-7 bg-gradient-to-r from-amber-500 via-orange-500 to-rose-600 text-white shadow-xl shadow-orange-600/25 border-2 border-amber-300 relative overflow-hidden"
                >
                    <div class="absolute -right-8 -bottom-8 w-48 h-48 rounded-full bg-white/10 blur-2xl pointer-events-none"></div>

                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
                        <div class="flex items-start gap-4">
                            <div class="w-14 h-14 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-3xl shrink-0 shadow-inner">
                                🔄
                            </div>
                            <div>
                                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/25 text-white text-xs font-black tracking-wide uppercase mb-2">
                                    <span class="w-2 h-2 rounded-full bg-yellow-200 animate-ping"></span>
                                    DÜZELTME & REVİZYON İSTENDİ
                                </div>
                                <h3 class="text-xl sm:text-2xl font-black text-white">
                                    Talebiniz İçin Revizyon / Düzeltme İstendi
                                </h3>
                                <p class="text-amber-50 text-sm mt-1 max-w-2xl leading-relaxed">
                                    Yetkili başvurunuzu incelemiş ve düzeltme yapılmasını istemiştir. Lütfen iletilen gerekçeyi dikkate alarak formu güncelleyip tekrar onaya gönderiniz.
                                </p>
                                <div v-if="revisionNote" class="mt-3 p-3.5 rounded-xl bg-white/15 border border-white/30 backdrop-blur-sm max-w-2xl">
                                    <div class="text-xs font-bold text-amber-100 uppercase tracking-wider mb-0.5">
                                        📌 İletilen Revizyon Gerekçesi {{ revisionAuthor ? `(${revisionAuthor})` : '' }}:
                                    </div>
                                    <div class="text-sm font-semibold text-white italic">
                                        "{{ revisionNote }}"
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 shrink-0">
                            <Link
                                :href="route('tasks.show', myPendingTask.id)"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-7 py-3.5 rounded-xl text-base font-black text-orange-950 bg-white hover:bg-orange-50 shadow-2xl hover:shadow-white/25 transform hover:-translate-y-0.5 active:translate-y-0 transition-all"
                            >
                                <span>✏️ Talebi Düzenle ve Yeniden Gönder</span>
                                <span class="text-xl leading-none">&rarr;</span>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- DURUM A: SIRA SİZDE! (Kullanıcının üzerinde bekleyen normal görev var) -->
                <div
                    v-else-if="myPendingTask"
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

                <!-- ENTERPRISE TERMIN & SLA HERO MONITOR (Fortune 500 Grade) -->
                <div
                    v-if="terminSla && terminSla.has_termin"
                    class="rounded-2xl bg-white border border-slate-200/90 shadow-sm p-6 relative overflow-hidden transition-all duration-300 hover:shadow-md"
                >
                    <!-- Üst Başlık & Trafik Lambası -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-100 pb-4">
                        <div class="flex items-center gap-3">
                            <!-- Trafik Lambası Işığı -->
                            <div class="relative flex items-center justify-center">
                                <span
                                    v-if="terminSla.traffic_light === 'green'"
                                    class="w-4 h-4 rounded-full bg-emerald-500 shadow-md shadow-emerald-500/50"
                                ></span>
                                <span
                                    v-else-if="terminSla.traffic_light === 'amber'"
                                    class="w-4 h-4 rounded-full bg-amber-500 animate-ping shadow-md shadow-amber-500/50"
                                ></span>
                                <span
                                    v-else-if="terminSla.traffic_light === 'red' || terminSla.traffic_light === 'critical-red'"
                                    class="w-4 h-4 rounded-full bg-rose-600 animate-pulse shadow-md shadow-rose-600/50"
                                ></span>
                                <span v-else class="w-4 h-4 rounded-full bg-gray-400"></span>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-black tracking-wider uppercase text-slate-500">
                                        KURUMSAL TERMIN & SLA TAAHHÜT MONİTÖRÜ
                                    </span>
                                    <span v-if="terminSla.revisions_count > 0" class="px-2 py-0.5 rounded-full text-[10px] font-black bg-indigo-50 text-indigo-700 border border-indigo-200">
                                        {{ terminSla.revisions_count }} Kez Revize Edildi
                                    </span>
                                </div>
                                <h3 class="text-lg font-extrabold text-slate-900 mt-0.5 flex items-center gap-2">
                                    <span>📅 Taahhüt Edilen Termin:</span>
                                    <span class="font-mono text-indigo-700 font-black">{{ terminSla.termin_text }}</span>
                                </h3>
                            </div>
                        </div>

                        <!-- Canlı SLA Rozeti & Aksiyon Butonu -->
                        <div class="flex flex-wrap items-center gap-3 shrink-0">
                            <!-- Durum Rozeti -->
                            <span
                                :class="[
                                    'inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-black border shadow-xs transition-all',
                                    terminSla.badge_color
                                ]"
                            >
                                <span>{{ terminSla.badge_title }}</span>
                            </span>

                            <!-- Termini Revize Et Butonu -->
                            <button
                                v-if="canRescheduleTermin && !isCompleted && !isRejected"
                                type="button"
                                @click="openRescheduleModal"
                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-300 transition-all hover:scale-105 shadow-xs"
                                title="İşletme aksaklıklarında gerekçeli resmi erteleme bildirimi yap"
                            >
                                <span>🔄</span>
                                <span>Termini Ertele / Revize Et</span>
                            </button>
                        </div>
                    </div>

                    <!-- 3'lü KPI Göstergesi -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4 pt-1">
                        <div class="p-3.5 rounded-xl bg-slate-50/80 border border-slate-100">
                            <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider">Nihai Teslim Hedefi (Deadline)</span>
                            <div class="text-sm font-black text-slate-900 mt-1 flex items-center gap-1.5 font-mono">
                                <span>🎯</span> {{ terminSla.target_date_formatted }}
                            </div>
                            <span class="block text-[11px] text-slate-500 mt-0.5">Fabrika çıkış taahhüt tarihi</span>
                        </div>

                        <div class="p-3.5 rounded-xl bg-slate-50/80 border border-slate-100">
                            <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider">Kalan Süre / Performans</span>
                            <div class="text-sm font-black mt-1 flex items-center gap-1.5" :class="terminSla.is_overdue ? 'text-rose-600' : 'text-emerald-700'">
                                <span v-if="terminSla.is_completed">
                                    {{ terminSla.was_on_time ? '✓ Zamanında Tamamlandı' : `⚠️ +${terminSla.delay_days} Gün Gecikmeyle` }}
                                </span>
                                <span v-else>
                                    {{ terminSla.is_overdue ? `⛔ ${Math.abs(terminSla.days_remaining)} Gün Gecikmede` : `⏳ Kalan: ${terminSla.days_remaining} Gün (${terminSla.hours_remaining} Saat)` }}
                                </span>
                            </div>
                            <span class="block text-[11px] text-slate-500 mt-0.5">SLA eşik denetimi</span>
                        </div>

                        <div class="p-3.5 rounded-xl bg-slate-50/80 border border-slate-100">
                            <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider">Müşteri Şeffaflığı & OTIF</span>
                            <div class="text-sm font-black text-indigo-700 mt-1 flex items-center gap-1.5">
                                <span>🏢</span> {{ instance.data?.musteri_adi || 'Köksan Müşterisi' }}
                            </div>
                            <span class="block text-[11px] text-slate-500 mt-0.5">
                                {{ terminRevisions.length > 0 ? `Son Değişiklik: ${terminRevisions[0]?.reason_category}` : 'İlk taahhüt geçerli' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- KURUMSAL BÜTÇE & MASRAF MERKEZİ KONTROL PANELİ (Item D) -->
                <div 
                    v-if="budgetControl && budgetControl.has_budget_impact" 
                    class="rounded-2xl border p-5 shadow-sm transition-all"
                    :class="[
                        budgetControl.status === 'critical' 
                            ? 'bg-rose-50/70 border-rose-200' 
                            : (budgetControl.status === 'warning' 
                                ? 'bg-amber-50/70 border-amber-200' 
                                : 'bg-emerald-50/70 border-emerald-200')
                    ]"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                        <div class="flex items-center gap-3">
                            <div :class="['w-10 h-10 rounded-xl flex items-center justify-center text-xl shadow-xs', budgetControl.badge_color]">
                                💳
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h4 class="font-bold text-sm text-gray-900">Kurumsal Bütçe & Masraf Merkezi Kontrolü</h4>
                                    <span :class="['text-[11px] font-extrabold px-2.5 py-0.5 rounded-full border', budgetControl.badge_color]">
                                        {{ budgetControl.status_label }}
                                    </span>
                                </div>
                                <span class="text-xs text-gray-500">
                                    Masraf Merkezi: <strong>{{ budgetControl.cost_center }}</strong> · Dönem: {{ budgetControl.fiscal_period }}
                                </span>
                            </div>
                        </div>

                        <div class="text-right">
                            <span class="text-xs text-gray-500 block">Talep Edilen Tutar</span>
                            <span class="text-base font-black text-gray-900">{{ budgetControl.amount_formatted }}</span>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-xs font-semibold text-gray-700">
                            <span>Dönem Bütçesi: {{ budgetControl.allocated_budget_formatted }}</span>
                            <span>Tüketim Oranı: %{{ budgetControl.consumption_rate }}</span>
                        </div>
                        <div class="w-full bg-gray-200/80 rounded-full h-3 overflow-hidden shadow-inner flex">
                            <div 
                                :class="budgetControl.bar_color" 
                                class="h-full rounded-full transition-all duration-500"
                                :style="{ width: Math.min(100, budgetControl.consumption_rate) + '%' }"
                            ></div>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-gray-500 pt-0.5">
                            <span>Önceki Harcama: {{ budgetControl.base_consumed_formatted }} (%{{ budgetControl.previous_rate }})</span>
                            <span>Kalan Kullanılabilir: <strong class="text-gray-800">{{ budgetControl.remaining_budget_formatted }}</strong></span>
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
                        <span class="text-xs font-semibold px-3 py-1 rounded-full" :class="isCompleted ? 'bg-green-100 text-green-800' : (isRejected ? 'bg-red-100 text-red-800' : (isRevisionRequired ? 'bg-orange-100 text-orange-800 font-black border border-orange-300' : 'bg-blue-100 text-blue-800'))">
                            {{ isCompleted ? 'Tamamlandı' : (isRejected ? 'Reddedildi' : (isCancelled ? 'İptal' : (isRevisionRequired ? '🔄 Revizyon Bekleniyor' : 'İlerliyor'))) }}
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
                                            : (step.isRevision
                                                ? 'bg-orange-500 text-white shadow-xl shadow-orange-500/40 ring-4 ring-orange-100 animate-pulse'
                                                : (step.status === 'current'
                                                    ? 'bg-amber-500 text-white shadow-xl shadow-amber-500/40 ring-4 ring-amber-100 animate-pulse'
                                                    : (step.status === 'rejected'
                                                        ? 'bg-red-600 text-white ring-4 ring-red-50'
                                                        : 'bg-white border-2 border-gray-300 text-gray-400')))
                                    ]"
                                >
                                    <span v-if="step.status === 'completed'">✓</span>
                                    <span v-else-if="step.isRevision">🔄</span>
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

                <!-- YÖNETİCİ SÜREÇ MÜDAHALE KOKPİTİ (PROCESS ADMIN COCKPIT) -->
                <div
                    v-if="isAdmin && !isCompleted && !isCancelled"
                    class="rounded-2xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 border border-indigo-500/30 p-5 shadow-xl text-white relative overflow-hidden"
                >
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 relative z-10">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-500/20 border border-amber-500/40 text-amber-400 flex items-center justify-center text-xl font-black">
                                ⚡
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-black tracking-widest uppercase px-2 py-0.5 rounded-md bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                        ADMIN INTERVENTION COCKPIT
                                    </span>
                                    <span class="text-xs text-slate-400">Yönetici Müdahale Yetkisi</span>
                                </div>
                                <h4 class="text-base font-extrabold text-white mt-0.5">
                                    Süreç Operasyonel Müdahale Merkezi
                                </h4>
                            </div>
                        </div>

                        <!-- 3 Müdahale Butonu -->
                        <div class="flex flex-wrap items-center gap-2.5">
                            <button
                                type="button"
                                @click="openReassignModal()"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold text-white bg-slate-800 hover:bg-slate-700 border border-slate-600 transition-all hover:scale-105 shadow-md"
                            >
                                <span>👤</span> Görevi Yeniden Ata
                            </button>
                            <button
                                type="button"
                                @click="openJumpNodeModal"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold text-white bg-indigo-900/80 hover:bg-indigo-800 border border-indigo-600/50 transition-all hover:scale-105 shadow-md"
                            >
                                <span>🔀</span> Adım Değiştir / Taşı
                            </button>
                            <button
                                type="button"
                                @click="openForceProgressModal()"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-black text-amber-950 bg-amber-400 hover:bg-amber-300 transition-all hover:scale-105 shadow-lg shadow-amber-400/20"
                            >
                                <span>⏩</span> Zorla İlerlet (Force)
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 3. KULLANICI DOSTU SEKMELİ İÇERİK (TABS) -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 overflow-hidden">
                    <!-- Sekme Başlıkları -->
                    <div class="flex items-center border-b border-gray-200 bg-gray-50/75 px-4 sm:px-6 overflow-x-auto">
                        <button
                            type="button"
                            @click="activeTab = 'details'"
                            class="px-5 py-4 text-sm font-bold border-b-2 flex items-center gap-2 transition-all whitespace-nowrap"
                            :class="activeTab === 'details' ? 'border-indigo-600 text-indigo-600 bg-white shadow-sm' : 'border-transparent text-gray-500 hover:text-gray-900 hover:bg-gray-100/50'"
                        >
                            <span>📋</span> Talep Bilgileri & Başvuru Detayları
                        </button>

                        <button
                            type="button"
                            @click="activeTab = 'flow'"
                            class="px-5 py-4 text-sm font-bold border-b-2 flex items-center gap-2 transition-all whitespace-nowrap"
                            :class="activeTab === 'flow' ? 'border-indigo-600 text-indigo-600 bg-white shadow-sm' : 'border-transparent text-gray-500 hover:text-gray-900 hover:bg-gray-100/50'"
                        >
                            <span>🗺️</span> Canlı Akış Şeması (BPMN / Şema)
                        </button>

                        <button
                            type="button"
                            @click="activeTab = 'history'"
                            class="px-5 py-4 text-sm font-bold border-b-2 flex items-center gap-2 transition-all whitespace-nowrap"
                            :class="activeTab === 'history' ? 'border-indigo-600 text-indigo-600 bg-white shadow-sm' : 'border-transparent text-gray-500 hover:text-gray-900 hover:bg-gray-100/50'"
                        >
                            <span>📜</span> İşlem Geçmişi & Onaylar ({{ processHistory.length }})
                        </button>

                        <button
                            type="button"
                            @click="activeTab = 'audits'"
                            class="px-5 py-4 text-sm font-bold border-b-2 flex items-center gap-2 transition-all whitespace-nowrap"
                            :class="activeTab === 'audits' ? 'border-indigo-600 text-indigo-600 bg-white shadow-sm' : 'border-transparent text-gray-500 hover:text-gray-900 hover:bg-gray-100/50'"
                        >
                            <span>🔍</span> Değişiklik Günlüğü (Audit)
                            <span v-if="fieldAudits.length > 0" class="px-2 py-0.5 rounded-full text-xs font-black bg-indigo-100 text-indigo-700">
                                {{ fieldAudits.length }}
                            </span>
                        </button>

                        <button
                            v-if="terminRevisions.length > 0 || (terminSla && terminSla.has_termin)"
                            type="button"
                            @click="activeTab = 'sla_history'"
                            class="px-5 py-4 text-sm font-bold border-b-2 flex items-center gap-2 transition-all whitespace-nowrap"
                            :class="activeTab === 'sla_history' ? 'border-indigo-600 text-indigo-600 bg-white shadow-sm' : 'border-transparent text-gray-500 hover:text-gray-900 hover:bg-gray-100/50'"
                        >
                            <span>⏱️</span> Termin & SLA Geçmişi ({{ terminRevisions.length }})
                        </button>

                        <!-- SÜRE & DARBOĞAZ ANALİZİ SEKMESİ (Item A) -->
                        <button
                            type="button"
                            @click="activeTab = 'lead_time'"
                            class="px-5 py-4 text-sm font-bold border-b-2 flex items-center gap-2 transition-all whitespace-nowrap"
                            :class="activeTab === 'lead_time' ? 'border-indigo-600 text-indigo-600 bg-white shadow-sm' : 'border-transparent text-gray-500 hover:text-gray-900 hover:bg-gray-100/50'"
                        >
                            <span>⏳</span> Süre & Darboğaz Analizi
                            <span v-if="leadTimeAnalytics?.bottleneck_step" class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-700 animate-pulse">
                                Darboğaz
                            </span>
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
                                        {{ formatFieldValue(value) }}
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

                    <!-- SEKME 4: TERMIN & SLA REVIZYON GECMISI -->
                    <div v-show="activeTab === 'sla_history'" class="p-6 sm:p-8 space-y-6">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                            <div>
                                <h4 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                    <span>⏱️</span> Termin & SLA Revizyon Denetim Günlüğü
                                </h4>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    Bu talebe ait tüm termin taahhüt değişiklikleri ve fabrika gecikme gerekçeleri kayıtlıdır.
                                </p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-slate-100 text-slate-700 border border-slate-200">
                                Toplam: {{ terminRevisions.length }} Revizyon
                            </span>
                        </div>

                        <div v-if="terminRevisions.length === 0" class="text-center py-12 bg-gray-50/50 rounded-xl border border-dashed border-gray-200">
                            <span class="text-3xl block mb-2">🟢</span>
                            <h5 class="text-sm font-bold text-gray-800">Hiç Termin Revizyonu Yapılmadı</h5>
                            <p class="text-xs text-gray-500 mt-1 max-w-md mx-auto">
                                Süreç, işletmenin belirlediği ilk termin taahhüdüyle ({{ terminSla?.termin_text || 'İlk Termin' }}) zamanında ilerlemektedir.
                            </p>
                        </div>

                        <div v-else class="space-y-4">
                            <div
                                v-for="(rev, idx) in terminRevisions"
                                :key="rev.id"
                                class="rounded-xl border border-slate-200/90 bg-white p-5 shadow-xs transition-all hover:border-indigo-200"
                            >
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-100 pb-3 mb-3">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-700 font-mono text-xs font-black flex items-center justify-center shrink-0">
                                            #{{ terminRevisions.length - idx }}
                                        </span>
                                        <div>
                                            <span class="text-xs font-bold text-gray-900">{{ rev.requested_by?.name || 'İşletme Yetkilisi' }}</span>
                                            <span class="text-[11px] text-gray-400 ml-2">{{ formatDate(rev.created_at) }}</span>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                        <span>🏷️</span> {{ rev.reason_category ? (terminCategories[rev.reason_category]?.label || rev.reason_category) : 'Genel Revizyon' }}
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3 p-3 bg-slate-50/70 rounded-lg text-xs">
                                    <div>
                                        <span class="text-slate-400 font-semibold block uppercase text-[10px]">Önceki Termin</span>
                                        <span class="font-mono text-slate-700 font-bold mt-0.5 block line-through">{{ rev.previous_termin || 'Belirtilmedi' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 font-semibold block uppercase text-[10px]">Revize Edilen Yeni Termin</span>
                                        <span class="font-mono text-indigo-700 font-black mt-0.5 block">➔ {{ rev.new_termin }}</span>
                                    </div>
                                </div>

                                <div>
                                    <span class="text-[11px] font-bold text-gray-500 uppercase block mb-1">Açıklama / Gerekçe Notu:</span>
                                    <p class="text-xs text-gray-800 whitespace-pre-wrap leading-relaxed bg-white p-3 rounded-lg border border-gray-100">
                                        {{ rev.reason_note }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SEKME 5: ALAN BAZLI DEĞİŞİKLİK GÜNLÜĞÜ (AUDIT DIFF) -->
                    <div v-show="activeTab === 'audits'" class="p-6 sm:p-8 space-y-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-gray-100 pb-4">
                            <div>
                                <h4 class="text-base font-extrabold text-gray-900 flex items-center gap-2">
                                    <span>🔍</span> Alan Bazlı Denetim İzi & Değişiklik Günlüğü (Audit Diff)
                                </h4>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    Süreç boyunca yapılan her revizyon, form güncellemesi ve yönetici müdahalesi burada satır satır denetlenir.
                                </p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-black bg-indigo-50 text-indigo-700 border border-indigo-200">
                                Toplam: {{ fieldAudits.length }} Kayıt
                            </span>
                        </div>

                        <!-- Boş Durum -->
                        <div v-if="fieldAudits.length === 0" class="text-center py-12 bg-gray-50/50 rounded-2xl border border-dashed border-gray-200">
                            <span class="text-4xl block mb-2">🛡️</span>
                            <h5 class="text-sm font-bold text-gray-800">Hiçbir Alanda Değişiklik Yapılmadı</h5>
                            <p class="text-xs text-gray-500 mt-1 max-w-md mx-auto">
                                Talep ilk başvuru verileriyle yürütülmektedir. Henüz bir revizyon, veri güncellemesi veya yönetici müdahalesi kaydedilmedi.
                            </p>
                        </div>

                        <!-- Değişiklik Kartları Listesi -->
                        <div v-else class="space-y-4">
                            <div
                                v-for="audit in fieldAudits"
                                :key="audit.id"
                                class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs hover:shadow-md transition-all hover:border-indigo-200"
                            >
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-100 pb-3 mb-3">
                                    <div class="flex items-center gap-2.5">
                                        <span
                                            v-if="audit.event_type === 'revision'"
                                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-purple-50 text-purple-700 border border-purple-200 flex items-center gap-1"
                                        >
                                            <span>🔄</span> Revizyon Güncellemesi
                                        </span>
                                        <span
                                            v-else-if="audit.event_type === 'admin_reassign'"
                                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200 flex items-center gap-1"
                                        >
                                            <span>👤</span> Görev Yeniden Atandı
                                        </span>
                                        <span
                                            v-else-if="audit.event_type === 'admin_jump'"
                                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200 flex items-center gap-1"
                                        >
                                            <span>🔀</span> Adım Değiştirildi
                                        </span>
                                        <span
                                            v-else-if="audit.event_type === 'admin_force'"
                                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 flex items-center gap-1"
                                        >
                                            <span>⏩</span> Zorla İlerletildi
                                        </span>
                                        <span
                                            v-else-if="audit.event_type === 'sod_escalation'"
                                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-800 border border-indigo-200 flex items-center gap-1"
                                        >
                                            <span>🛡️</span> 4-Göz İlkesi (SoD Eskalasyon)
                                        </span>
                                        <span
                                            v-else-if="audit.event_type === 'doa_bypass'"
                                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-teal-50 text-teal-800 border border-teal-200 flex items-center gap-1"
                                        >
                                            <span>⚡</span> DoA Finansal Yetki Matrisi
                                        </span>
                                        <span
                                            v-else-if="audit.event_type === 'parallel_fork'"
                                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-800 border border-indigo-200 flex items-center gap-1"
                                        >
                                            <span>🔀</span> Paralel Dallanma (AND-Fork)
                                        </span>
                                        <span
                                            v-else-if="audit.event_type === 'parallel_join_wait'"
                                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200 flex items-center gap-1"
                                        >
                                            <span>⏳</span> Paralel Birleşme Beklemesi
                                        </span>
                                        <span
                                            v-else-if="audit.event_type === 'parallel_join_sync'"
                                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 flex items-center gap-1"
                                        >
                                            <span>🔗</span> Paralel Kollar Senkronize Edildi
                                        </span>
                                        <span
                                            v-else-if="audit.event_type === 'boundary_timer_escalate'"
                                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-50 text-rose-800 border border-rose-200 flex items-center gap-1"
                                        >
                                            <span>⏱️</span> Boundary Timer Eskalasyonu
                                        </span>
                                        <span
                                            v-else-if="audit.event_type === 'boundary_timer_auto_approve'"
                                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-teal-50 text-teal-800 border border-teal-200 flex items-center gap-1"
                                        >
                                            <span>⚡</span> Boundary Timer Otomatik Onay
                                        </span>
                                        <span
                                            v-else-if="audit.event_type === 'workflow_migration'"
                                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-purple-50 text-purple-800 border border-purple-200 flex items-center gap-1"
                                        >
                                            <span>🔄</span> Canlı Süreç Versiyon Göçü (Schema Migration)
                                        </span>
                                        <span
                                            v-else
                                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200 flex items-center gap-1"
                                        >
                                            <span>✏️</span> Form Düzenlemesi
                                        </span>

                                        <span class="text-xs font-bold text-gray-900">{{ audit.user?.name || 'Sistem' }}</span>
                                        <span class="text-[11px] text-gray-400">· {{ formatDate(audit.created_at) }}</span>
                                    </div>

                                    <span v-if="audit.task" class="text-xs text-gray-500 font-mono">
                                        Görev: {{ audit.task.title }}
                                    </span>
                                </div>

                                <!-- Alan Başlığı -->
                                <div class="mb-3">
                                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">Değişen Alan / İşlem:</span>
                                    <h5 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                                        <span>🏷️</span>
                                        <span>{{ audit.field_label || audit.field_key }}</span>
                                        <span class="text-[11px] font-mono text-slate-400 font-normal">({{ audit.field_key }})</span>
                                    </h5>
                                </div>

                                <!-- Yan Yana Git Stili Değer Karşılaştırması (Git-style Diff) -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3.5 bg-slate-50/80 rounded-xl border border-slate-100">
                                    <!-- Eski Değer -->
                                    <div class="p-3 rounded-lg bg-red-50/60 border border-red-200/80">
                                        <div class="flex items-center justify-between text-[11px] font-bold text-red-800 mb-1">
                                            <span class="flex items-center gap-1">
                                                <span>➖</span> Eski Değer
                                            </span>
                                            <span class="text-[10px] text-red-600 uppercase font-mono">Previous</span>
                                        </div>
                                        <div class="text-xs font-mono text-red-950 font-semibold break-words whitespace-pre-wrap line-through">
                                            {{ audit.old_value || '(Boş / Tanımsız)' }}
                                        </div>
                                    </div>

                                    <!-- Yeni Değer -->
                                    <div class="p-3 rounded-lg bg-emerald-50/60 border border-emerald-200/80">
                                        <div class="flex items-center justify-between text-[11px] font-bold text-emerald-800 mb-1">
                                            <span class="flex items-center gap-1">
                                                <span>➕</span> Yeni Değer
                                            </span>
                                            <span class="text-[10px] text-emerald-600 uppercase font-mono">Updated</span>
                                        </div>
                                        <div class="text-xs font-mono text-emerald-950 font-extrabold break-words whitespace-pre-wrap">
                                            {{ audit.new_value || '(Boş / Silindi)' }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Gerekçe / Not -->
                                <div v-if="audit.comment" class="mt-3 text-xs bg-white p-3 rounded-xl border border-gray-100 text-gray-700">
                                    <span class="font-bold text-gray-500 uppercase text-[10px] block mb-0.5">Açıklama / Gerekçe:</span>
                                    <span class="italic text-gray-800">{{ audit.comment }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SEKME 6: SÜRE VE DARBOĞAZ ANALİZİ (LEAD TIME & BOTTLENECK - Item A) -->
                    <div v-show="activeTab === 'lead_time'" class="p-6 sm:p-8 space-y-6">
                        <!-- Top KPI Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                                <span class="text-xs font-bold text-slate-400 uppercase">Toplam Süreç Süresi</span>
                                <div class="text-xl font-black text-slate-900 mt-1 font-mono">
                                    {{ leadTimeAnalytics?.total_duration_formatted || 'Hesaplanıyor' }}
                                </div>
                                <span class="text-[11px] text-slate-500 mt-0.5 block">Tüm adımların toplam harcanan zamanı</span>
                            </div>

                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                                <span class="text-xs font-bold text-slate-400 uppercase">Ortalama Adım Süresi</span>
                                <div class="text-xl font-black text-indigo-700 mt-1 font-mono">
                                    {{ leadTimeAnalytics?.average_step_formatted || '0 dk' }}
                                </div>
                                <span class="text-[11px] text-slate-500 mt-0.5 block">{{ leadTimeAnalytics?.total_steps_count || 0 }} adım üzerinden ortalama</span>
                            </div>

                            <div class="p-4 rounded-xl border" :class="leadTimeAnalytics?.bottleneck_step ? 'bg-rose-50/70 border-rose-200' : 'bg-emerald-50/70 border-emerald-200'">
                                <span class="text-xs font-bold uppercase" :class="leadTimeAnalytics?.bottleneck_step ? 'text-rose-500' : 'text-emerald-500'">
                                    {{ leadTimeAnalytics?.bottleneck_step ? '🚨 Tespit Edilen Darboğaz' : 'Akış Durumu' }}
                                </span>
                                <div class="text-base font-black truncate mt-1" :class="leadTimeAnalytics?.bottleneck_step ? 'text-rose-900' : 'text-emerald-900'">
                                    {{ leadTimeAnalytics?.bottleneck_step?.title || 'Dengeli Akış' }}
                                </div>
                                <span class="text-[11px] block mt-0.5" :class="leadTimeAnalytics?.bottleneck_step ? 'text-rose-600' : 'text-emerald-600'">
                                    {{ leadTimeAnalytics?.bottleneck_step ? `Süre: ${leadTimeAnalytics.bottleneck_step.duration_formatted} (Toplamın %${leadTimeAnalytics.bottleneck_step.percentage}'i)` : 'Tüm adımlar hedef süreler dahilinde.' }}
                                </span>
                            </div>
                        </div>

                        <!-- Lead Time Breakdown Table / Timeline -->
                        <div class="border border-gray-200 rounded-xl overflow-hidden">
                            <div class="p-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                                <h4 class="font-bold text-sm text-gray-900 flex items-center gap-2">
                                    <span>📊</span> Adım Bazlı Bekleme Süresi & Isı Haritası
                                </h4>
                                <span class="text-xs text-gray-500">🟢 Hızlı (&lt; 2h) · 🟡 Normal · 🔴 Darboğaz</span>
                            </div>

                            <div class="divide-y divide-gray-100">
                                <div v-for="step in leadTimeAnalytics?.steps || []" :key="step.task_id" class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-gray-50/80 transition">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h5 class="font-bold text-sm text-gray-900">{{ step.title }}</h5>
                                            <span v-if="step.is_bottleneck" class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-800 border border-rose-300">
                                                🚨 DARBOĞAZ
                                            </span>
                                            <span class="text-xs text-gray-400">· Sorumlu: {{ step.completed_user || step.assigned_user }}</span>
                                        </div>

                                        <!-- Progress bar showing percentage of total process time -->
                                        <div class="w-full bg-gray-100 rounded-full h-2 mt-2 overflow-hidden flex">
                                            <div 
                                                :class="step.is_bottleneck ? 'bg-rose-500' : (step.duration_minutes < 120 ? 'bg-emerald-500' : 'bg-amber-500')" 
                                                class="h-full rounded-full transition-all duration-300"
                                                :style="{ width: Math.max(4, step.percentage) + '%' }"
                                            ></div>
                                        </div>
                                    </div>

                                    <div class="text-right sm:w-48 shrink-0">
                                        <span class="text-sm font-black text-gray-900 block font-mono">{{ step.duration_formatted }}</span>
                                        <span class="text-[11px] text-gray-400 block">%{{ step.percentage }} toplam süre</span>
                                    </div>
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

        <!-- TERMIN REVIZYON & ERTELEME MODALI (Fortune 500 Reschedule Matrix) -->
        <div
            v-if="showRescheduleModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm"
            @click.self="closeRescheduleModal"
        >
            <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-gray-100 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-indigo-50/50 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <span>📅</span> Termin Taahhüdü Revizyonu
                        </h3>
                        <p class="text-xs text-gray-500 mt-1">
                            Aksaklık durumunda gerekçeli resmi tarih güncellemesi yapılır ve satış departmanına iletilir.
                        </p>
                    </div>
                    <button type="button" @click="closeRescheduleModal" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg">✕</button>
                </div>

                <div class="p-6 space-y-4">
                    <!-- Mevcut Termin Bilgisi -->
                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs flex items-center justify-between">
                        <span class="text-slate-500 font-medium">Mevcut Taahhüt:</span>
                        <strong class="font-mono text-indigo-700 text-sm">{{ terminSla?.termin_text || 'Belirtilmedi' }}</strong>
                    </div>

                    <!-- Yeni Termin Girişi & Hızlı Presetler -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-gray-700 uppercase">
                                Yeni Termin Aralığı / Tarihi <strong class="text-red-500">*</strong>
                            </label>
                            <span class="text-[11px] text-gray-500">Hızlı Preset:</span>
                        </div>
                        <div class="flex items-center gap-2 mb-2">
                            <button
                                type="button"
                                @click="applyReschedulePreset(3)"
                                class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors"
                            >
                                +3 Gün
                            </button>
                            <button
                                type="button"
                                @click="applyReschedulePreset(7)"
                                class="px-2.5 py-1 rounded-lg text-xs font-bold bg-indigo-50 hover:bg-indigo-100 text-indigo-700 transition-colors"
                            >
                                +7 Gün (1 Hafta)
                            </button>
                            <button
                                type="button"
                                @click="applyReschedulePreset(14)"
                                class="px-2.5 py-1 rounded-lg text-xs font-bold bg-purple-50 hover:bg-purple-100 text-purple-700 transition-colors"
                            >
                                +14 Gün (2 Hafta)
                            </button>
                        </div>
                        <input
                            v-model="rescheduleForm.new_termin"
                            type="text"
                            placeholder="Örn: 28.10.2026 - 04.11.2026"
                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono"
                        />
                        <p v-if="rescheduleForm.errors.new_termin" class="text-xs text-red-600 mt-1">{{ rescheduleForm.errors.new_termin }}</p>
                    </div>

                    <!-- Gecikme Gerekçe Kategorisi (Standart Fortune 500) -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1.5">
                            Revizyon / Gecikme Gerekçesi <strong class="text-red-500">*</strong>
                        </label>
                        <select
                            v-model="rescheduleForm.reason_category"
                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                            <option value="raw_material">📦 Hammadde / Malzeme Tedarik Gecikmesi</option>
                            <option value="machine_breakdown">⚙️ Ekstrüzyon / Makine Hattı Arızası & Bakım</option>
                            <option value="mold_change">🔧 Kalıp Değişimi & Ayar Süresi</option>
                            <option value="quality_retest">🔬 Laboratuvar / Kalite Test Tekrarı</option>
                            <option value="customer_change">🤝 Müşteri Talebi / Spesifikasyon Değişikliği</option>
                            <option value="other">📝 Diğer Operasyonel Gerekçe</option>
                        </select>
                    </div>

                    <!-- Açıklama Notu -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1.5">
                            Detaylı Açıklama <strong class="text-red-500">*</strong>
                        </label>
                        <textarea
                            v-model="rescheduleForm.reason_note"
                            rows="3"
                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            placeholder="Gecikmenin teknik veya lojistik nedenini detaylandırınız..."
                        />
                        <p v-if="rescheduleForm.errors.reason_note" class="text-xs text-red-600 mt-1">{{ rescheduleForm.errors.reason_note }}</p>
                    </div>

                    <!-- Kurumsal Bilgi Kutusu -->
                    <div class="p-3 bg-amber-50/70 border border-amber-200 rounded-xl text-xs text-amber-900 flex items-start gap-2">
                        <span class="text-base shrink-0">💡</span>
                        <span>Bu revizyon, satış departmanına ({{ instance.starter?.name || 'Satışçı' }}) anında bildirim ve e-posta olarak iletilir. Resmi SLA denetiminde kayıt altına alınır.</span>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-end gap-3">
                    <button
                        type="button"
                        @click="closeRescheduleModal"
                        class="px-4 py-2 text-xs font-bold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-100"
                    >
                        Vazgeç
                    </button>
                    <button
                        type="button"
                        @click="submitReschedule"
                        :disabled="rescheduleForm.processing"
                        class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 shadow-sm disabled:opacity-50 flex items-center gap-1.5"
                    >
                        <span>✓</span>
                        <span>{{ rescheduleForm.processing ? 'Kaydediliyor...' : 'Termin Revizyonunu Onayla' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 1. ADMIN MODAL: GÖREVİ YENİDEN ATA (REASSIGN TASK) -->
        <div
            v-if="showReassignModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
            @click.self="showReassignModal = false"
        >
            <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-gray-100 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 bg-slate-900 text-white flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-amber-400 text-lg">👤</span>
                            <h3 class="text-base font-extrabold text-white">Görevi Başka Personele Yeniden Ata</h3>
                        </div>
                        <p class="text-xs text-slate-300 mt-0.5">
                            İlgili personelin izinli veya meşgul olduğu durumlarda görevi devredin.
                        </p>
                    </div>
                    <button @click="showReassignModal = false" class="text-slate-400 hover:text-white text-xl">&times;</button>
                </div>

                <div class="p-6 space-y-4">
                    <!-- Görev Seçimi -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">
                            Atanacak Bekleyen Görev <strong class="text-red-500">*</strong>
                        </label>
                        <select
                            v-model="reassignForm.task_id"
                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                            <option
                                v-for="t in (instance.tasks || []).filter(task => task.status === 'pending')"
                                :key="t.id"
                                :value="t.id"
                            >
                                #{{ t.id }} - {{ t.title || t.node_label || 'Onay/Form Görevi' }} (Şu an: {{ t.assigned_user?.name || t.assigned_role || 'Atanmamış' }})
                            </option>
                        </select>
                        <p v-if="reassignForm.errors.task_id" class="text-xs text-red-600 mt-1">{{ reassignForm.errors.task_id }}</p>
                    </div>

                    <!-- Yeni Kullanıcı Seçimi -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">
                            Yeni Görevli Personel <strong class="text-red-500">*</strong>
                        </label>
                        <select
                            v-model="reassignForm.new_user_id"
                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                            <option value="">Lütfen personel seçiniz...</option>
                            <option
                                v-for="u in allUsers"
                                :key="u.id"
                                :value="u.id"
                            >
                                {{ u.name }} ({{ u.department?.name || 'Departmansız' }} - {{ u.email }})
                            </option>
                        </select>
                        <p v-if="reassignForm.errors.new_user_id" class="text-xs text-red-600 mt-1">{{ reassignForm.errors.new_user_id }}</p>
                    </div>

                    <!-- Gerekçe -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">
                            Yeniden Atama Gerekçesi <strong class="text-red-500">*</strong>
                        </label>
                        <textarea
                            v-model="reassignForm.reason"
                            rows="3"
                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            placeholder="Örn: Personel yıllık izinde olduğu için görev işletme müdürüne aktarılmıştır."
                        />
                        <p v-if="reassignForm.errors.reason" class="text-xs text-red-600 mt-1">{{ reassignForm.errors.reason }}</p>
                    </div>

                    <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-900 flex items-start gap-2">
                        <span class="text-base shrink-0">💡</span>
                        <span>Bu atama değişikliği yeni personele anında sistem içi bildirim olarak gönderilir ve süreç denetim izine kaydedilir.</span>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-end gap-3">
                    <button
                        type="button"
                        @click="showReassignModal = false"
                        class="px-4 py-2 text-xs font-bold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-100"
                    >
                        Vazgeç
                    </button>
                    <button
                        type="button"
                        @click="submitReassign"
                        :disabled="reassignForm.processing"
                        class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 shadow-sm disabled:opacity-50 flex items-center gap-1.5"
                    >
                        <span>✓</span>
                        <span>{{ reassignForm.processing ? 'Atanıyor...' : 'Görevi Devret / Ata' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 2. ADMIN MODAL: ADIM DEĞİŞTİR / TAŞI (JUMP NODE) -->
        <div
            v-if="showJumpNodeModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
            @click.self="showJumpNodeModal = false"
        >
            <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-gray-100 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 bg-slate-900 text-white flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-indigo-400 text-lg">🔀</span>
                            <h3 class="text-base font-extrabold text-white">Süreci Farklı Bir Adıma Taşı (Jump Node)</h3>
                        </div>
                        <p class="text-xs text-slate-300 mt-0.5">
                            Süreci akış üzerindeki herhangi bir adıma doğrudan ileri veya geri sarın.
                        </p>
                    </div>
                    <button @click="showJumpNodeModal = false" class="text-slate-400 hover:text-white text-xl">&times;</button>
                </div>

                <div class="p-6 space-y-4">
                    <!-- Hedef Düğüm -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">
                            Hedef İş Akışı Adımı <strong class="text-red-500">*</strong>
                        </label>
                        <select
                            v-model="jumpNodeForm.target_node_id"
                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                            <option value="">Lütfen hedef adımı seçiniz...</option>
                            <option
                                v-for="node in workflowNodes"
                                :key="node.id"
                                :value="node.id"
                            >
                                {{ node.label }} [Adım ID: {{ node.id }}]
                            </option>
                        </select>
                        <p v-if="jumpNodeForm.errors.target_node_id" class="text-xs text-red-600 mt-1">{{ jumpNodeForm.errors.target_node_id }}</p>
                    </div>

                    <!-- Gerekçe -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">
                            Adım Değiştirme Gerekçesi <strong class="text-red-500">*</strong>
                        </label>
                        <textarea
                            v-model="jumpNodeForm.reason"
                            rows="3"
                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            placeholder="Örn: Müşteri acil revize istediğinden süreç ilk onay adımına geri çekilmiştir."
                        />
                        <p v-if="jumpNodeForm.errors.reason" class="text-xs text-red-600 mt-1">{{ jumpNodeForm.errors.reason }}</p>
                    </div>

                    <div class="p-3 bg-rose-50 rounded-xl border border-rose-200 text-xs text-rose-900 flex items-start gap-2">
                        <span class="text-base shrink-0">⚠️</span>
                        <span>Mevcut bekleyen görevler iptal edilecek, seçilen hedef adımda yeni görevler oluşturulacaktır. Tüm hareketler denetim günlüğünde imzalanır.</span>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-end gap-3">
                    <button
                        type="button"
                        @click="showJumpNodeModal = false"
                        class="px-4 py-2 text-xs font-bold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-100"
                    >
                        Vazgeç
                    </button>
                    <button
                        type="button"
                        @click="submitJumpNode"
                        :disabled="jumpNodeForm.processing"
                        class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 shadow-sm disabled:opacity-50 flex items-center gap-1.5"
                    >
                        <span>✓</span>
                        <span>{{ jumpNodeForm.processing ? 'Taşınıyor...' : 'Süreci Taşı ve Başlat' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 3. ADMIN MODAL: ZORLA İLERLET (FORCE PROGRESS) -->
        <div
            v-if="showForceProgressModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
            @click.self="showForceProgressModal = false"
        >
            <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-gray-100 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 bg-slate-900 text-white flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-amber-400 text-lg">⏩</span>
                            <h3 class="text-base font-extrabold text-white">Görevi Yönetici Yetkisiyle Zorla Tamamla</h3>
                        </div>
                        <p class="text-xs text-slate-300 mt-0.5">
                            Tıkanmış veya bekleyen görevi yönetici onayı ile kapatıp akışı ilerletin.
                        </p>
                    </div>
                    <button @click="showForceProgressModal = false" class="text-slate-400 hover:text-white text-xl">&times;</button>
                </div>

                <div class="p-6 space-y-4">
                    <!-- Görev Seçimi -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">
                            Tamamlanacak Görev <strong class="text-red-500">*</strong>
                        </label>
                        <select
                            v-model="forceProgressForm.task_id"
                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm"
                        >
                            <option
                                v-for="t in (instance.tasks || []).filter(task => task.status === 'pending')"
                                :key="t.id"
                                :value="t.id"
                            >
                                #{{ t.id }} - {{ t.title || t.node_label || 'Onay/Form Görevi' }} (Bekleyen: {{ t.assigned_user?.name || t.assigned_role || 'Atanmamış' }})
                            </option>
                        </select>
                        <p v-if="forceProgressForm.errors.task_id" class="text-xs text-red-600 mt-1">{{ forceProgressForm.errors.task_id }}</p>
                    </div>

                    <!-- Gerekçe -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">
                            Yönetici Müdahale Açıklaması <strong class="text-red-500">*</strong>
                        </label>
                        <textarea
                            v-model="forceProgressForm.reason"
                            rows="3"
                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm"
                            placeholder="Örn: Fabrika müdürünün sözlü onayı doğrultusunda sistem yöneticisi tarafından onaylanmıştır."
                        />
                        <p v-if="forceProgressForm.errors.reason" class="text-xs text-red-600 mt-1">{{ forceProgressForm.errors.reason }}</p>
                    </div>

                    <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-900 flex items-start gap-2">
                        <span class="text-base shrink-0">⚖️</span>
                        <span>Bu işlem görev kaydını sizin adınıza 'completed' yapar ve yorum alanına yönetici onay şerhini düşer.</span>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-end gap-3">
                    <button
                        type="button"
                        @click="showForceProgressModal = false"
                        class="px-4 py-2 text-xs font-bold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-100"
                    >
                        Vazgeç
                    </button>
                    <button
                        type="button"
                        @click="submitForceProgress"
                        :disabled="forceProgressForm.processing"
                        class="px-5 py-2 text-xs font-black text-amber-950 bg-amber-400 hover:bg-amber-300 rounded-xl shadow-md disabled:opacity-50 flex items-center gap-1.5"
                    >
                        <span>✓</span>
                        <span>{{ forceProgressForm.processing ? 'İşleniyor...' : 'Zorla Onayla ve İlerlet' }}</span>
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
