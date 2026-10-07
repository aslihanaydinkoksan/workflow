<script setup>
import { ref, computed, onMounted, nextTick, provide } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { VueFlow, useVueFlow, Handle } from '@vue-flow/core';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import StartNode from '@/Components/WorkflowNodes/StartNode.vue';
import EndNode from '@/Components/WorkflowNodes/EndNode.vue';
import TaskNode from '@/Components/WorkflowNodes/TaskNode.vue';
import DecisionNode from '@/Components/WorkflowNodes/DecisionNode.vue';
import IONode from '@/Components/WorkflowNodes/IONode.vue';
import StorageNode from '@/Components/WorkflowNodes/StorageNode.vue';
import DocumentNode from '@/Components/WorkflowNodes/DocumentNode.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import RuleBuilderModal from '@/Components/RuleBuilderModal.vue';

import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';
import '@vue-flow/controls/dist/style.css';

const props = defineProps({
    workflow: {
        type: Object,
        default: null,
    },
    workflowVersions: {
        type: Array,
        default: () => [],
    },
    forms: {
        type: Array,
        default: () => [],
    },
    users: {
        type: Array,
        default: () => [],
    },
    departments: {
        type: Array,
        default: () => [],
    },
    directorates: {
        type: Array,
        default: () => [],
    },
    roles: {
        type: Array,
        default: () => [],
    },
    workflowCategories: {
        type: Array,
        default: () => [],
    }
});

const form = useForm({
    name: props.workflow?.name || '',
    description: props.workflow?.description || '',
    form_template_id: props.workflow?.form_template_id || '',
    valid_from: props.workflow?.valid_from || '',
    valid_until: props.workflow?.valid_until || '',
    category: props.workflow?.category || [],
    allowed_departments: props.workflow?.allowed_departments || [],
    allowed_roles: props.workflow?.allowed_roles || [],
    allowed_users: props.workflow?.allowed_users || [],
    nodes: props.workflow?.nodes || [],
    edges: props.workflow?.edges || [],
    status: props.workflow?.status || 'active',
    auto_start_process: true,
});

const { addNodes, setNodes, setEdges, dimensions, project, vueFlowRef, onConnect, addEdges, onEdgeClick, removeEdges, getNodes, getEdges, removeNodes } = useVueFlow({
    defaultEdgeOptions: { 
        type: 'smoothstep', 
        animated: true,
        style: { stroke: '#94a3b8', strokeWidth: 2 }
    }
});

// forms, departmanlar ve kullanıcılar listesini çocuk düğüm bileşenlerine aktar (BaseNode inject ile alacak)
const formsRef = computed(() => props.forms);
provide('formsList', formsRef);
const departmentsRef = computed(() => props.departments);
provide('departmentsList', departmentsRef);
const usersRef = computed(() => props.users);
provide('usersList', usersRef);

const elements = ref([
    { id: '1', type: 'start', position: { x: 250, y: 5 }, data: { label: 'Başlangıç' } }
]);

onMounted(() => {
    if (form.nodes.length > 0) {
        form.nodes.forEach((node) => {
            ensureScheduleDefaults(node);
            ensureRejectDefaults(node);
            ensureAssigneeDefaults(node);
            ensureBoundaryTimerDefaults(node);
        });
        setNodes(form.nodes);
        setEdges(form.edges);
    } else {
        setNodes(elements.value);
    }
});

onConnect((params) => {
    addEdges([params]);
});

// Temel İş Akışı Elemanları
const temelNodeTypes = [
    { type: 'start', label: 'Başlangıç', bgColor: '#10b981', description: 'Süreci başlatır', taskType: 'start', iconLetter: '▶' },
    { type: 'io', label: 'Form Görevi', bgColor: '#3b82f6', description: 'Personele form doldurma görevi atar', taskType: 'form', iconLetter: '📋' },
    { type: 'decision', label: 'Onay Mekanizması', bgColor: '#f59e0b', description: 'Yönetici onayına sunar (Termin zorunluluğu destekler)', taskType: 'approval', iconLetter: '✓' },
    { type: 'decision', label: 'Paralel Dallanma (AND-Fork)', bgColor: '#6366f1', description: 'Akışı aynı anda birden fazla paralel kola böler (BPMN 2.0)', taskType: 'parallel_fork', iconLetter: '🔀' },
    { type: 'decision', label: 'Paralel Birleşme (AND-Join)', bgColor: '#4f46e5', description: 'Tüm paralel kollar tamamlanana kadar bekler ve senkronize eder (BPMN 2.0)', taskType: 'parallel_join', iconLetter: '🔗' },
    { type: 'io', label: 'Süreç Takibi', bgColor: '#0284c7', description: 'Belirlenen gün sonra satışçıya "Siparişe döndü mü?" takibi başlatır', taskType: 'follow_up', iconLetter: '⏱' },
    { type: 'task', label: 'İnceleme (Bilgi)', bgColor: '#8b5cf6', description: 'Sadece bilgilendirir', taskType: 'review', iconLetter: '👁' },
    { type: 'decision', label: 'Sistem Kararı (Kural)', bgColor: '#ec4899', description: 'Kurallara göre otomatik yönlendirir', taskType: 'system_rule', iconLetter: '⚙' },
    { type: 'storage', label: 'Depo / Bekleme', bgColor: '#eab308', description: 'Bekleme / Depolama Noktası', taskType: 'storage', iconLetter: '⏳' },
    { type: 'end', label: 'Bitiş (End)', bgColor: '#ef4444', description: 'Süreci tamamlar', taskType: 'end', iconLetter: '■' },
];

// Otomasyon ve Entegrasyon Araçları
const entegrasyonNodeTypes = [
    { type: 'io', label: 'Süreç Takibi', bgColor: '#0284c7', description: 'Belirli gün sonra anket / takip tetikler (Numune, sipariş vb.)', taskType: 'follow_up', iconLetter: '⏱' },
    { type: 'document', label: 'Belge & Sertifika Üretici', bgColor: '#475569', description: 'Otomatik Analiz Sertifikası veya PDF üretir', taskType: 'document_gen', iconLetter: '📜' },
    { type: 'io', label: 'SAP S/4HANA Aktarımı', bgColor: '#0369a1', description: 'SAP ile sipariş / malzeme entegrasyonu', taskType: 'sap_sync', iconLetter: '🔄' },
    { type: 'io', label: 'Mail Bildirimi', bgColor: '#06b6d4', description: 'Otomatik e-posta atar', taskType: 'notify', iconLetter: '✉' },
    { type: 'io', label: 'Webhook / Dış API', bgColor: '#334155', description: 'Dış sisteme HTTP API çağrısı yapar', taskType: 'notify', iconLetter: '🔗' },
];

const paletteSearch = ref('');
const filteredTemelNodes = computed(() => {
    if (!paletteSearch.value.trim()) return temelNodeTypes;
    const q = paletteSearch.value.toLowerCase();
    return temelNodeTypes.filter(n => n.label.toLowerCase().includes(q) || n.description.toLowerCase().includes(q));
});

const filteredEntegrasyonNodes = computed(() => {
    if (!paletteSearch.value.trim()) return entegrasyonNodeTypes;
    const q = paletteSearch.value.toLowerCase();
    return entegrasyonNodeTypes.filter(n => n.label.toLowerCase().includes(q) || n.description.toLowerCase().includes(q));
});

const selectedNodeForSettings = ref(null);
const settingsTab = ref('general');
const humanTaskTypes = ['approval', 'review', 'form'];
const isTopSettingsOpen = ref(false);
const isLeftPaletteOpen = ref(true);
const leftPaletteTab = ref('temel');

const openSettings = (id) => {
    const node = getNodes.value.find(n => n.id === id);
    if (node) {
        ensureScheduleDefaults(node);
        ensureRejectDefaults(node);
        ensureAssigneeDefaults(node);
        ensureBoundaryTimerDefaults(node);
    }
    selectedNodeForSettings.value = node ?? null;
    settingsTab.value = 'general';
};

const isHumanTaskNode = (node) => node && humanTaskTypes.includes(node.data?.taskType);

const ensureBoundaryTimerDefaults = (node) => {
    if (!node?.data) return;
    if (!node.data.boundaryTimer) {
        node.data.boundaryTimer = {
            enabled: false,
            duration_hours: 24,
            action: 'auto_escalate'
        };
    }
};

const ensureScheduleDefaults = (node) => {
    if (!node?.data || !isHumanTaskNode(node)) {
        return;
    }

    if (node.data.scheduleEnabled === undefined) {
        node.data.scheduleEnabled = false;
    }

    if (
        node.data.scheduleDays === undefined
        && node.data.scheduleHours === undefined
        && node.data.scheduleMinutes === undefined
    ) {
        const legacyValue = Number(node.data.scheduleValue ?? 24);
        const legacyUnit = node.data.scheduleUnit ?? 'hours';

        node.data.scheduleDays = legacyUnit === 'days' ? legacyValue : 0;
        node.data.scheduleHours = legacyUnit === 'hours' ? legacyValue : 0;
        node.data.scheduleMinutes = legacyUnit === 'minutes' ? legacyValue : 0;
    }

    node.data.scheduleDays = Math.max(0, Number(node.data.scheduleDays ?? 0));
    node.data.scheduleHours = Math.max(0, Number(node.data.scheduleHours ?? 0));
    node.data.scheduleMinutes = Math.max(0, Number(node.data.scheduleMinutes ?? 0));
};

const formatScheduleSummary = (data) => {
    const parts = [];

    if (data.scheduleDays > 0) {
        parts.push(`${data.scheduleDays} gün`);
    }
    if (data.scheduleHours > 0) {
        parts.push(`${data.scheduleHours} saat`);
    }
    if (data.scheduleMinutes > 0) {
        parts.push(`${data.scheduleMinutes} dakika`);
    }

    return parts.length ? parts.join(' ') : 'Süre tanımlanmadı';
};

const ensureRejectDefaults = (node) => {
    if (!node?.data || !['approval', 'review', 'form'].includes(node.data.taskType)) {
        return;
    }

    if (node.data.rejectEnabled === undefined) {
        node.data.rejectEnabled = node.data.taskType !== 'form';
    }
};

const ensureAssigneeDefaults = (node) => {
    if (!node?.data) return;
    if (!node.data.deptManagerType) {
        node.data.deptManagerType = 'manager';
    }
    if (node.data.assignedUserId === undefined) {
        node.data.assignedUserId = '';
    }
    if (!Array.isArray(node.data.assignedUserIds)) {
        node.data.assignedUserIds = node.data.assignedUserId ? [node.data.assignedUserId] : [];
    }
    if (!node.data.approvalPolicy) {
        node.data.approvalPolicy = 'any';
    }
};

const currentDepartmentUsers = computed(() => {
    if (!selectedNodeForSettings.value?.data?.assignValue) return [];
    const deptId = selectedNodeForSettings.value.data.assignValue;
    
    // 1. Departmanın birincil kullanıcıları (users tablosundan department_id eşleşenler)
    const primaryUsers = (props.users || []).filter(u => u.department_id == deptId);
    
    // 2. department_managers ilişkisinden gelen yöneticiler
    const deptObj = (props.departments || []).find(d => d.id == deptId);
    const managerUsers = deptObj?.all_managers || [];
    
    // Birleştir ve tekilleştir (id'ye göre)
    const userMap = new Map();
    primaryUsers.forEach(u => userMap.set(u.id, u));
    managerUsers.forEach(u => {
        if (!userMap.has(u.id)) {
            userMap.set(u.id, u);
        }
    });
    
    return Array.from(userMap.values());
});

const onDepartmentChanged = () => {
    if (selectedNodeForSettings.value?.data) {
        selectedNodeForSettings.value.data.assignedUserId = '';
        selectedNodeForSettings.value.data.assignedUserIds = [];
    }
};

const duplicateNode = (id) => {
    const node = getNodes.value.find(n => n.id === id);
    if (node) {
        const newNode = {
            id: `node_${Date.now()}`,
            type: node.type,
            position: { x: node.position.x + 50, y: node.position.y + 50 },
            data: { ...node.data }
        };
        addNodes([newNode]);
    }
};

const removeNode = (id) => {
    removeNodes([id]);
    if (selectedNodeForSettings.value?.id === id) selectedNodeForSettings.value = null;
};

const selectedNode = ref(null);
const selectedEdge = ref(null);

const onNodeClick = (event) => {
    selectedNode.value = event.node;
    selectedEdge.value = null;
};

const onPaneClick = () => {
    selectedNode.value = null;
    selectedEdge.value = null;
};

onEdgeClick((event) => {
    selectedEdge.value = event.edge;
    selectedNode.value = null;
});

const onDragStart = (event, nodeType) => {
    if (event.dataTransfer) {
        event.dataTransfer.setData('application/vueflow', JSON.stringify(nodeType));
        event.dataTransfer.effectAllowed = 'move';
    }
};

const onDragOver = (event) => {
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
};

const onDrop = (event) => {
    const nodeTypeData = event.dataTransfer?.getData('application/vueflow');
    if (!nodeTypeData) return;
    
    const nodeData = JSON.parse(nodeTypeData);
    const { left, top } = vueFlowRef.value.getBoundingClientRect();

    const position = project({
        x: event.clientX - left,
        y: event.clientY - top,
    });

    // Düğüm tipine göre akıllı varsayılan data oluştur
    const baseData = {
        label: nodeData.label,
        customName: nodeData.label,
        taskType: nodeData.taskType,
        color: nodeData.bgColor,
    };

    // Sadece onay/inceleme düğümleri için atama bilgisi
    if (['approval', 'review'].includes(nodeData.taskType)) {
        baseData.assignType = 'hierarchy';
        baseData.assignValue = 'manager_1';
        baseData.deptManagerType = 'manager';
        baseData.assignedUserId = '';
        baseData.assignedUserIds = [];
        baseData.approvalPolicy = 'any';
        baseData.description = '';
        baseData.subFormId = '';
        baseData.rejectEnabled = true;
        baseData.requireTermin = false;
        baseData.requireFieldOnApprove = false;
        baseData.requiredFieldName = '';
        baseData.requiredFieldErrorMessage = '';
    }

    if (nodeData.taskType === 'form') {
        baseData.rejectEnabled = false;
        baseData.subFormId = '';
        baseData.assignType = 'starter';
        baseData.assignValue = '';
        baseData.deptManagerType = 'manager';
        baseData.assignedUserId = '';
        baseData.description = '';
    }

    // Süreç Takibi Düğümü
    if (nodeData.taskType === 'follow_up') {
        baseData.followUpTitle = 'Numune Siparişe Döndü mü?';
        baseData.followUpPrompt = 'Sevk edilen numune siparişe dönüştü mü? Lütfen sipariş numarasını veya dönmeme gerekçesini sisteme işleyiniz.';
        baseData.subFormId = '';
        baseData.followUpDays = 15;
        baseData.followUpIntervalDays = 5;
        baseData.followUpMaxReminders = 3;
        baseData.assignType = 'starter';
        baseData.assignValue = '';
        baseData.followUpType = 'general_follow_up';
    }

    // Belge & Sertifika Üretici Düğümü
    if (nodeData.taskType === 'document_gen') {
        baseData.documentTitle = 'Analiz Sertifikası (CoA)';
        baseData.documentType = 'certificate_of_analysis';
    }

    // SAP S/4HANA Aktarımı Düğümü
    if (nodeData.taskType === 'sap_sync') {
        baseData.sapObjectType = 'sales_order';
        baseData.sapSimulation = true;
    }

    // Sadece bildirim düğümü için e-posta
    if (nodeData.taskType === 'notify') {
        baseData.notifyTo = '';
        baseData.notifyEmail = '';
        baseData.notifySubject = '';
        baseData.notifyMessage = '';
    }

    //  Bitiş düğümü için varsayılan durumu belirle
    if (nodeData.taskType === 'end') {
        baseData.processStatus = 'completed';
    }

    if (['approval', 'review', 'form'].includes(nodeData.taskType)) {
        baseData.scheduleEnabled = false;
        baseData.scheduleDays = 0;
        baseData.scheduleHours = 24;
        baseData.scheduleMinutes = 0;
    }

    const newNode = {
        id: `node_${Date.now()}`,
        type: nodeData.type,
        position,
        data: baseData,
    };

    addNodes([newNode]);
};


const saveWorkflow = (status = 'active') => {
    form.status = status;
    form.auto_start_process = true;
    form.nodes = getNodes.value.map(n => ({ id: n.id, type: n.type, position: n.position, label: n.label, style: n.style, data: n.data }));
    form.edges = getEdges.value.map(e => ({ id: e.id, source: e.source, target: e.target, sourceHandle: e.sourceHandle, targetHandle: e.targetHandle }));
    
    if (props.workflow) {
        form.put(route('workflows.update', props.workflow.id));
    } else {
        form.post(route('workflows.store'));
    }
};

// Zero-Downtime Versiyonlama ve Canlı Yayınlama
const isPublishVersionModalOpen = ref(false);
const publishVersionNotes = ref('');
const isPublishingVersion = ref(false);

const openPublishVersionModal = () => {
    publishVersionNotes.value = '';
    isPublishVersionModalOpen.value = true;
};

const publishNewVersion = () => {
    if (!props.workflow) return;
    isPublishingVersion.value = true;

    const payloadNodes = getNodes.value.map(n => ({ id: n.id, type: n.type, position: n.position, label: n.label, style: n.style, data: n.data }));
    const payloadEdges = getEdges.value.map(e => ({ id: e.id, source: e.source, target: e.target, sourceHandle: e.sourceHandle, targetHandle: e.targetHandle }));

    router.post(route('workflows.publish-version', props.workflow.id), {
        name: form.name,
        description: form.description,
        form_template_id: form.form_template_id,
        valid_from: form.valid_from,
        valid_until: form.valid_until,
        category: form.category,
        allowed_departments: form.allowed_departments,
        allowed_roles: form.allowed_roles,
        allowed_users: form.allowed_users,
        nodes: payloadNodes,
        edges: payloadEdges,
        status: 'active',
        auto_start_process: true,
        version_notes: publishVersionNotes.value || 'Yeni süreç versiyonu yayınlandı.',
    }, {
        onFinish: () => {
            isPublishingVersion.value = false;
            isPublishVersionModalOpen.value = false;
        }
    });
};

const switchWorkflowVersion = (versionId) => {
    if (versionId && Number(versionId) !== Number(props.workflow?.id)) {
        router.visit(route('workflows.edit', versionId));
    }
};

const isRuleModalOpen = ref(false);
</script>

<template>
    <Head :title="workflow ? 'Akış Düzenle' : 'Yeni Akış Çiz'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between px-6 py-4 bg-gradient-to-r from-teal-800 to-teal-600 text-white shadow-lg rounded-xl mt-2 relative z-10 mx-auto max-w-7xl">
                <div class="flex items-center space-x-6">
                    <div class="flex space-x-6 text-sm font-semibold tracking-wide items-center">
                        <button class="border-b-2 border-white pb-1">OLUŞTUR</button>
                        <button @click="isTopSettingsOpen = !isTopSettingsOpen" 
                                class="pb-1 transition-colors flex items-center" 
                                :class="isTopSettingsOpen ? 'text-white border-b-2 border-white' : 'text-teal-200 hover:text-white'">
                            AYARLAR
                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path v-if="!isTopSettingsOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Versiyon Rozeti ve Aile Seçicisi -->
                    <div v-if="workflow" class="flex items-center gap-2 pl-4 border-l border-teal-500/40">
                        <div class="flex items-center gap-1.5 bg-teal-900/60 px-2.5 py-1 rounded-lg border border-teal-400/30">
                            <span class="text-xs text-teal-300 font-medium">Sürüm:</span>
                            <span class="text-xs font-mono font-bold text-white bg-teal-700/80 px-1.5 py-0.5 rounded">v{{ workflow.version || 1 }}.0</span>
                            <span v-if="workflow.is_latest" class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-500/30 text-emerald-200 border border-emerald-400/40 font-semibold">Son Sürüm</span>
                            <span v-else class="text-[10px] px-1.5 py-0.5 rounded bg-amber-500/30 text-amber-200 border border-amber-400/40 font-semibold">Eski Sürüm</span>
                        </div>

                        <!-- Versiyon Değiştirme Dropdown -->
                        <div v-if="workflowVersions && workflowVersions.length > 1" class="relative">
                            <select 
                                @change="switchWorkflowVersion($event.target.value)" 
                                :value="workflow.id"
                                class="bg-teal-900/80 border border-teal-400/30 text-xs text-white rounded-lg px-2 py-1 pr-6 focus:ring-1 focus:ring-teal-300 cursor-pointer"
                                title="Farklı bir versiyonu görüntüle/düzenle"
                            >
                                <option v-for="ver in workflowVersions" :key="ver.id" :value="ver.id">
                                    v{{ ver.version }}.0 {{ ver.is_latest ? '(Son Sürüm)' : '' }} - {{ ver.created_at }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="flex flex-col items-end space-y-1 mt-4 md:mt-0">
                    <div class="flex items-center space-x-2">
                        <span class="text-xs mr-2 text-teal-100 hidden sm:inline">
                            {{ workflow ? 'Zero-Downtime Versiyonlama Aktif' : 'Kayıt = yayınla + süreci başlat' }}
                        </span>
                        
                        <!-- Mevcut Versiyonu Güncelle -->
                        <button 
                            @click="saveWorkflow('active')" 
                            :disabled="form.processing" 
                            class="bg-white/90 hover:bg-white text-teal-800 font-bold py-1.5 px-4 rounded shadow transition-colors text-xs flex items-center gap-1.5"
                            :title="workflow ? 'Mevcut versiyon şemasını günceller' : 'Yeni akışı kaydeder ve yayına alır'"
                        >
                            <svg class="w-3.5 h-3.5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                            <span>{{ form.processing ? 'Kaydediliyor...' : (workflow ? 'Mevcut Sürümü Kaydet' : 'Kaydet ve Yayınla') }}</span>
                        </button>

                        <!-- Yeni Versiyon Olarak Yayınla (Zero-Downtime v+1) -->
                        <button 
                            v-if="workflow"
                            type="button"
                            @click="openPublishVersionModal" 
                            :disabled="isPublishingVersion" 
                            class="bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold py-1.5 px-4 rounded shadow-md transition-all text-xs flex items-center gap-1.5 border border-purple-400/40"
                            title="Eski versiyonu ve canlı talepleri bozmadan v+1 yeni versiyon üretir"
                        >
                            <span>✨</span>
                            <span>Yeni Versiyon Yayınla (v{{ (workflow.version || 1) + 1 }}.0)</span>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Workflow Ayarları (Collapsible) -->
            <div v-show="isTopSettingsOpen" class="mt-6 bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <!-- 1. Satır: Temel Bilgiler -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-start">
                    <div class="col-span-1">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Akış Adı <span class="text-red-500">*</span></label>
                        <input v-model="form.name" type="text" placeholder="Örn: İzin Süreci" class="w-full rounded border-gray-300 shadow-sm focus:ring-teal-500 focus:border-teal-500 sm:text-sm">
                    </div>
                    <div class="col-span-1">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Kategori(ler)</label>
                        <MultiSelectDropdown v-model="form.category" :options="workflowCategories.map(c => ({id: c.name, name: c.name}))" placeholder="Kategori Seçin..." />
                    </div>
                    <div class="col-span-1">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Bağlı Form (Opsiyonel)</label>
                        <select v-model="form.form_template_id" class="w-full rounded border-gray-300 shadow-sm focus:ring-teal-500 focus:border-teal-500 sm:text-sm">
                            <option value="">-- Bağlı Form Yok --</option>
                            <option v-for="f in forms" :key="f.id" :value="f.id">{{ f.name }}</option>
                        </select>
                    </div>
                    <div class="col-span-1">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Geçerlilik Başlangıç</label>
                        <input v-model="form.valid_from" type="date" class="w-full rounded border-gray-300 shadow-sm focus:ring-teal-500 focus:border-teal-500 sm:text-sm">
                    </div>
                    <div class="col-span-1">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Geçerlilik Bitiş</label>
                        <input v-model="form.valid_until" type="date" class="w-full rounded border-gray-300 shadow-sm focus:ring-teal-500 focus:border-teal-500 sm:text-sm">
                    </div>
                </div>

                <!-- 2. Satır: Yetkilendirme Filtreleri -->
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-xs font-semibold text-gray-500 mb-3 uppercase tracking-wider">Bu Süreci Kimler Başlatabilir? <span class="text-gray-400 font-normal normal-case">(Opsiyonel – boş bırakılırsa herkes başlatabilir)</span></p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Yetkili Departmanlar</label>
                            <MultiSelectDropdown 
                                v-model="form.allowed_departments" 
                                :options="departments.map(d => ({id: d.id.toString(), name: d.name}))" 
                                placeholder="Tüm Departmanlar..." 
                            />
                            <p class="text-[10px] text-gray-400 mt-1">Seçilirse sadece bu departmanlardaki çalışanlar başlatabilir.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Yetkili Roller</label>
                            <MultiSelectDropdown 
                                v-model="form.allowed_roles" 
                                :options="roles.map(r => ({id: r.id.toString(), name: r.name}))" 
                                placeholder="Tüm Roller..." 
                            />
                            <p class="text-[10px] text-gray-400 mt-1">Seçilirse sadece bu rollerdeki kullanıcılar başlatabilir.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Yetkili Kullanıcılar</label>
                            <MultiSelectDropdown 
                                v-model="form.allowed_users" 
                                :options="users.map(u => ({id: u.id.toString(), name: u.name}))" 
                                placeholder="Tüm Kullanıcılar..." 
                            />
                            <p class="text-[10px] text-gray-400 mt-1">Seçilirse sadece bu kişiler başlatabilir.</p>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-2 flex h-[calc(100vh-140px)] relative">
            <!-- SOL PANEL: Node Paleti (Dark Mode) -->
            <div v-if="isLeftPaletteOpen" class="w-72 bg-[#1e293b] shadow-xl flex flex-col h-full overflow-hidden text-gray-200 rounded-lg ml-4 z-20">
                <div class="p-4 border-b border-[#334155] flex justify-between items-center bg-[#0f172a]">
                    <span class="font-bold text-white tracking-wide">İş Akışı Elemanları</span>
                    <button @click="isLeftPaletteOpen = false" class="text-gray-400 hover:text-white transition-colors" title="Paneli Gizle">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <div class="flex border-b border-[#334155] bg-[#0f172a]">
                    <button @click="leftPaletteTab = 'temel'" class="w-1/2 py-3 text-center border-b-2 text-xs tracking-wider transition-colors" :class="leftPaletteTab === 'temel' ? 'border-teal-500 font-semibold text-white' : 'border-transparent text-gray-400 hover:text-gray-300'">TEMEL</button>
                    <button @click="leftPaletteTab = 'entegrasyonlar'" class="w-1/2 py-3 text-center border-b-2 text-xs tracking-wider transition-colors" :class="leftPaletteTab === 'entegrasyonlar' ? 'border-teal-500 font-semibold text-white' : 'border-transparent text-gray-400 hover:text-gray-300'">ENTEGRASYONLAR</button>
                </div>

                <!-- Arama Kutusu (Tüm sekmeler için ortak) -->
                <div class="p-3 border-b border-[#334155]/60 bg-[#0f172a]/50">
                    <div class="relative">
                        <svg class="w-4 h-4 absolute left-3 top-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <input 
                            v-model="paletteSearch" 
                            type="text" 
                            placeholder="Eleman ara..." 
                            class="w-full bg-[#334155] border-none rounded text-xs text-white pl-9 pr-3 py-2 focus:ring-1 focus:ring-teal-500 placeholder-gray-400"
                        >
                    </div>
                </div>

                <!-- TEMEL SEKME -->
                <div v-if="leftPaletteTab === 'temel'" class="flex-1 overflow-y-auto pb-4 custom-scrollbar">
                    <div 
                        v-for="nt in filteredTemelNodes" 
                        :key="nt.label"
                        class="px-4 py-2.5 border-b border-[#334155]/60 hover:bg-[#334155] cursor-grab flex items-center group transition-colors"
                        draggable="true"
                        @dragstart="onDragStart($event, nt)"
                    >
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center mr-3 text-white shadow-sm shrink-0" :style="{ backgroundColor: nt.bgColor }">
                            <span class="text-xs font-bold">{{ nt.iconLetter }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <span class="font-medium text-xs text-gray-200 group-hover:text-white transition-colors block truncate">{{ nt.label }}</span>
                            <span class="text-[10px] text-gray-400 block truncate">{{ nt.description }}</span>
                        </div>
                    </div>
                    <div v-if="filteredTemelNodes.length === 0" class="p-4 text-center text-xs text-gray-500">
                        Aramanızla eşleşen temel eleman bulunamadı.
                    </div>
                </div>

                <!-- ENTEGRASYONLAR SEKME -->
                <div v-else-if="leftPaletteTab === 'entegrasyonlar'" class="flex-1 overflow-y-auto pb-4 custom-scrollbar">
                    <div 
                        v-for="nt in filteredEntegrasyonNodes" 
                        :key="nt.label"
                        class="px-4 py-2.5 border-b border-[#334155]/60 hover:bg-[#334155] cursor-grab flex items-center group transition-colors"
                        draggable="true"
                        @dragstart="onDragStart($event, nt)"
                    >
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center mr-3 text-white shadow-sm shrink-0" :style="{ backgroundColor: nt.bgColor }">
                            <span class="text-xs font-bold">{{ nt.iconLetter }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <span class="font-medium text-xs text-gray-200 group-hover:text-white transition-colors block truncate">{{ nt.label }}</span>
                            <span class="text-[10px] text-gray-400 block truncate">{{ nt.description }}</span>
                        </div>
                    </div>
                    <div v-if="filteredEntegrasyonNodes.length === 0" class="p-4 text-center text-xs text-gray-500">
                        Aramanızla eşleşen entegrasyon elemanı bulunamadı.
                    </div>
                </div>
            </div>

            <!-- Gizlenmiş Sol Panel Butonu -->
            <div v-else class="w-12 bg-[#1e293b] shadow-xl flex flex-col h-full rounded-lg ml-4 z-20 py-4 items-center">
                <button @click="isLeftPaletteOpen = true" class="text-gray-400 hover:text-white bg-[#334155] p-2 rounded-lg transition-colors" title="Paneli Göster">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
            </div>

            <!-- ORTA PANEL: Vue Flow Canvas -->
            <div class="flex-1 bg-[#f8fafc] border border-gray-200 rounded-lg shadow-inner relative overflow-hidden mx-4" @drop="onDrop" @dragover="onDragOver">
                <VueFlow @nodeClick="onNodeClick" @paneClick="onPaneClick" :default-viewport="{ zoom: 1 }">
                    <Background pattern-color="#cbd5e1" gap="20" />
                    <Controls class="bg-white rounded-md shadow border border-gray-200 fill-gray-600" />
                    
                    <template #node-start="props">
                        <StartNode :id="props.id" :data="props.data" @openSettings="openSettings" @duplicate="duplicateNode" @remove="removeNode" />
                    </template>
                    <template #node-end="props">
                        <EndNode :id="props.id" :data="props.data" @openSettings="openSettings" @duplicate="duplicateNode" @remove="removeNode" />
                    </template>
                    <template #node-output="props">
                        <EndNode :id="props.id" :data="props.data" @openSettings="openSettings" @duplicate="duplicateNode" @remove="removeNode" />
                    </template>
                    <template #node-task="props">
                        <TaskNode :id="props.id" :data="props.data" @openSettings="openSettings" @duplicate="duplicateNode" @remove="removeNode" />
                    </template>
                    <template #node-io="props">
                        <IONode :id="props.id" :data="props.data" @openSettings="openSettings" @duplicate="duplicateNode" @remove="removeNode" />
                    </template>
                    <template #node-decision="props">
                        <DecisionNode :id="props.id" :data="props.data" @openSettings="openSettings" @duplicate="duplicateNode" @remove="removeNode" />
                    </template>
                    <template #node-approval="props">
                        <DecisionNode :id="props.id" :data="props.data" @openSettings="openSettings" @duplicate="duplicateNode" @remove="removeNode" />
                    </template>
                    <template #node-storage="props">
                        <StorageNode :id="props.id" :data="props.data" @openSettings="openSettings" @duplicate="duplicateNode" @remove="removeNode" />
                    </template>
                    <template #node-document="props">
                        <DocumentNode :id="props.id" :data="props.data" @openSettings="openSettings" @duplicate="duplicateNode" @remove="removeNode" />
                    </template>
                </VueFlow>
            </div>

            <!-- SAĞ PANEL: Ayarlar Modal (Absolute Offcanvas) -->
            <div v-if="selectedNodeForSettings || selectedEdge" class="absolute top-0 right-0 w-80 h-full bg-[#1e293b] text-gray-200 shadow-2xl flex flex-col z-50 transform transition-transform border-l border-[#334155] rounded-l-lg mr-4">
                <div class="p-4 bg-[#0f172a] flex justify-between items-center rounded-tl-lg">
                    <span class="font-bold text-lg text-white tracking-wide">{{ selectedNodeForSettings ? selectedNodeForSettings.data?.label || selectedNodeForSettings.data?.customName || selectedNodeForSettings.label || 'Düğüm Ayarları' : 'Bağlantı Ayarları' }}</span>
                    <button @click="selectedNodeForSettings = null; selectedEdge = null" class="text-gray-400 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <div v-if="selectedNodeForSettings" class="flex border-b border-[#334155] bg-[#0f172a]">
                    <button
                        type="button"
                        @click="settingsTab = 'general'"
                        class="w-1/2 py-3 text-center border-b-2 text-xs tracking-wider transition-colors"
                        :class="settingsTab === 'general' ? 'border-teal-500 font-semibold text-white' : 'border-transparent text-gray-500 hover:text-gray-300'"
                    >
                        GENEL
                    </button>
                    <button
                        type="button"
                        @click="settingsTab = 'advanced'"
                        class="w-1/2 py-3 text-center border-b-2 text-xs tracking-wider transition-colors"
                        :class="settingsTab === 'advanced' ? 'border-teal-500 font-semibold text-white' : 'border-transparent text-gray-500 hover:text-gray-300'"
                    >
                        GELİŞMİŞ
                    </button>
                </div>

                <div class="p-5 flex-1 overflow-y-auto custom-scrollbar">
                    <div v-if="selectedNodeForSettings" class="space-y-5">

                        <div v-show="settingsTab === 'general'" class="space-y-5">
                        
                        <!-- Etiket (Tüm düğümler için ortak) -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-400 mb-1 uppercase tracking-wider">Etiket (İsim)</label>
                            <input v-model="selectedNodeForSettings.data.label" type="text" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2">
                        </div>

                        <div v-if="selectedNodeForSettings.data.taskType === 'system_rule'" class="pt-4 border-t border-[#334155]">
                            <label class="block text-xs font-semibold text-pink-400 mb-2 uppercase tracking-wider">Otomatik Yönlendirme</label>
                            <p class="text-[10px] text-gray-400 mb-4">Bu düğüm insana görev atamaz. Gelen form ve sistem verilerine göre kuralları çalıştırır ve akışı karar verilen yola saptırır.</p>
                            
                            <button v-if="workflow?.id" @click="isRuleModalOpen = true" class="w-full bg-pink-500/20 hover:bg-pink-500/40 text-pink-300 border border-pink-500/50 font-bold py-2.5 px-4 rounded text-xs transition-colors shadow-sm flex items-center justify-center gap-2 tracking-wide">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                KURALLARI YÖNET
                            </button>
                            <div v-else class="p-3 bg-pink-950/40 border border-pink-500/30 rounded-lg text-xs text-pink-200">
                                <p class="font-semibold text-pink-300 mb-1 flex items-center gap-1.5">
                                    <span>💡</span> Akış Henüz Kaydedilmedi
                                </p>
                                <p class="text-[10px] text-pink-200/80 leading-relaxed">
                                    Kural motoru veritabanındaki akış kimliğine (ID) bağlı çalışır. Akışınızı sağ üstteki <strong>"Kaydet ve Yayınla"</strong> butonuyla bir defa kaydettikten sonra akış düzenleme ekranında kurallarınızı tanımlayabilirsiniz.
                                </p>
                            </div>
                        </div>

                        <!-- Görev açıklaması (atanan kişi Görevlerim'de görür) -->
                        <div v-if="['approval', 'review', 'form'].includes(selectedNodeForSettings.data.taskType)" class="pt-2 border-t border-[#334155]">
                            <label class="block text-xs font-semibold text-gray-400 mb-1 uppercase tracking-wider mt-2">Açıklama</label>
                            <p class="text-[10px] text-gray-500 mb-2">Atanan kişi göreve tıkladığında bu metin gösterilir.</p>
                            <textarea
                                v-model="selectedNodeForSettings.data.description"
                                rows="4"
                                placeholder="Örn: Lütfen talebi inceleyip onaylayınız..."
                                class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2 resize-y min-h-[80px]"
                            ></textarea>
                        </div>

                        <!-- ========== FORM GÖREVİ AYARLARI ========== -->
                        <div v-if="selectedNodeForSettings.data.taskType === 'form'" class="pt-2 border-t border-[#334155] space-y-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-400 mb-1 uppercase tracking-wider mt-2">Bağlı Form</label>
                                <p class="text-[10px] text-gray-500 mb-2">Bu adımda personele doldurtulacak formu seçin (/form-templates/create ile oluşturulur).</p>
                                <select v-model="selectedNodeForSettings.data.subFormId" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2">
                                    <option value="">-- Form Seçin --</option>
                                    <option v-for="f in forms" :key="f.id" :value="f.id">{{ f.name }}</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-400 mb-1 uppercase tracking-wider">Atanan Kişi</label>
                                <select v-model="selectedNodeForSettings.data.assignType" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2">
                                    <option value="starter">Süreci Başlatan Kişi</option>
                                    <option value="department">Belirli Bir Departman Yetkilisi / Amiri</option>
                                    <option value="user">Belirli Bir Kullanıcı</option>
                                    <option value="role">Belirli Bir Rol</option>
                                </select>

                                <div v-if="selectedNodeForSettings.data.assignType === 'department'" class="mt-2 space-y-3">
                                    <div>
                                        <label class="block text-[10px] text-gray-400 mb-1">Departman Seçin</label>
                                        <select 
                                            v-model="selectedNodeForSettings.data.assignValue" 
                                            @change="onDepartmentChanged"
                                            class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2"
                                        >
                                            <option value="">-- Departman Seçiniz --</option>
                                            <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                                        </select>
                                    </div>

                                    <div v-if="selectedNodeForSettings.data.assignValue" class="space-y-2 bg-[#1e293b]/70 p-3 rounded-lg border border-[#334155]">
                                        <div>
                                            <label class="block text-[11px] font-bold text-teal-400 mb-1 flex items-center justify-between">
                                                <span>Hangi Yönetici / Amir?</span>
                                                <span class="text-[9px] text-gray-400 font-normal">Tek Yetkili</span>
                                            </label>
                                            <select 
                                                v-model="selectedNodeForSettings.data.deptManagerType" 
                                                class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2"
                                            >
                                                <optgroup label="🏢 Kadro / Görev Kademesine Göre (Tek Yetkili)">
                                                    <option value="manager">👔 Departman Müdürü (Genel / Asil)</option>
                                                    <option value="assistant_manager">🥈 Departman Müdür Yardımcısı</option>
                                                    <option value="chief">👷 İşletme Şefi / Birim Amiri</option>
                                                </optgroup>
                                                <optgroup label="👥 Kadro Bazlı Çoklu Atama (Tüm Kademeler)">
                                                    <option value="all_managers">👔 Departmandaki Tüm Müdürler & Yrd. (Çoklu)</option>
                                                    <option value="all_chiefs">👷 Departmandaki Tüm İşletme Şefleri / Amirler (Çoklu)</option>
                                                </optgroup>
                                                <optgroup label="👤 Departmandan Doğrudan Kişi(ler) Seç">
                                                    <option value="specific_user">🎯 Tek Personel / Yetkili Seç (İsimle)</option>
                                                    <option value="specific_users">👥 Birden Fazla Personel / Yetkili Seç (Çoklu)</option>
                                                </optgroup>
                                            </select>
                                        </div>

                                        <!-- Tekil Kişi Seçimi -->
                                        <div v-if="selectedNodeForSettings.data.deptManagerType === 'specific_user'" class="pt-2 border-t border-[#334155]">
                                            <label class="block text-[10px] font-semibold text-amber-300 mb-1">
                                                Departman Yetkilisi / Personeli
                                            </label>
                                            <select 
                                                v-if="currentDepartmentUsers.length > 0"
                                                v-model="selectedNodeForSettings.data.assignedUserId" 
                                                class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-amber-500 px-3 py-2"
                                            >
                                                <option value="">-- Personel Seçiniz --</option>
                                                <option v-for="u in currentDepartmentUsers" :key="u.id" :value="u.id">
                                                    {{ u.name }} — {{ u.title || 'Yetkili' }}
                                                </option>
                                            </select>
                                            <div v-else class="space-y-1">
                                                <p class="text-[10px] text-amber-200/90 bg-amber-950/40 p-2 rounded border border-amber-500/30">
                                                    ℹ Bu departmana doğrudan bağlı personel bulunamadı. Şirket genelinden atama yapabilirsiniz:
                                                </p>
                                                <select 
                                                    v-model="selectedNodeForSettings.data.assignedUserId" 
                                                    class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-amber-500 px-3 py-2"
                                                >
                                                    <option value="">-- Tüm Personellerden Seç --</option>
                                                    <option v-for="u in users" :key="u.id" :value="u.id">
                                                        {{ u.name }} ({{ u.title || 'Personel' }})
                                                    </option>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Çoklu Kişi Seçimi (specific_users) -->
                                        <div v-if="selectedNodeForSettings.data.deptManagerType === 'specific_users'" class="pt-2 border-t border-[#334155]">
                                            <div class="flex items-center justify-between mb-1">
                                                <label class="block text-[10px] font-semibold text-amber-300">
                                                    Departman Yetkilileri / Personeli (Çoklu)
                                                </label>
                                                <span class="text-[9px] text-amber-400 font-mono">
                                                    {{ selectedNodeForSettings.data.assignedUserIds?.length || 0 }} kişi seçili
                                                </span>
                                            </div>
                                            <div class="max-h-36 overflow-y-auto bg-[#0f172a]/70 rounded p-2 border border-amber-500/30 space-y-1">
                                                <label 
                                                    v-for="u in (currentDepartmentUsers.length > 0 ? currentDepartmentUsers : users)" 
                                                    :key="u.id" 
                                                    class="flex items-center gap-2 text-xs text-gray-200 hover:bg-[#1e293b] p-1 rounded cursor-pointer"
                                                >
                                                    <input 
                                                        type="checkbox" 
                                                        :value="u.id" 
                                                        v-model="selectedNodeForSettings.data.assignedUserIds"
                                                        class="rounded border-gray-600 bg-[#334155] text-amber-500 focus:ring-amber-500"
                                                    />
                                                    <span class="truncate">{{ u.name }} — {{ u.title || 'Yetkili' }}</span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="mt-2 p-2 rounded bg-emerald-950/30 border border-emerald-500/30 text-[10px] text-emerald-300 flex items-start gap-1.5">
                                            <span class="text-xs">🛡</span>
                                            <span class="leading-tight">
                                                <strong>Hedefli Bildirim:</strong>
                                                Görev ve e-posta bildirimleri yalnızca yukarıda belirlenen yetkili(ler)e iletilir.
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div v-else-if="selectedNodeForSettings.data.assignType === 'user'" class="mt-2 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-[10px] text-gray-400">Hedef Kullanıcı(lar)ı Seçin</label>
                                        <span class="text-[9px] text-teal-400 font-mono">
                                            {{ selectedNodeForSettings.data.assignedUserIds?.length || (selectedNodeForSettings.data.assignValue ? 1 : 0) }} kişi seçili
                                        </span>
                                    </div>
                                    <div class="max-h-40 overflow-y-auto bg-[#0f172a]/70 rounded p-2 border border-[#334155] space-y-1">
                                        <label 
                                            v-for="user in users" 
                                            :key="user.id" 
                                            class="flex items-center gap-2 text-xs text-gray-200 hover:bg-[#1e293b] p-1 rounded cursor-pointer"
                                        >
                                            <input 
                                                type="checkbox" 
                                                :value="user.id" 
                                                v-model="selectedNodeForSettings.data.assignedUserIds"
                                                class="rounded border-gray-600 bg-[#334155] text-teal-500 focus:ring-teal-500"
                                            />
                                            <span class="truncate">{{ user.name }} ({{ user.title || 'Kullanıcı' }})</span>
                                        </label>
                                    </div>
                                </div>
                                <div v-else-if="selectedNodeForSettings.data.assignType === 'role'" class="mt-2">
                                    <label class="block text-[10px] text-gray-400 mb-1">Rol Seçin</label>
                                    <select v-model="selectedNodeForSettings.data.assignValue" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2">
                                        <option value="">-- Rol Seçin --</option>
                                        <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-[#334155]">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        v-model="selectedNodeForSettings.data.rejectEnabled"
                                        type="checkbox"
                                        class="rounded border-gray-500 bg-[#334155] text-red-500 focus:ring-red-500"
                                    />
                                    <span class="text-sm text-gray-200">Reddetme / İade seçeneği</span>
                                </label>
                                <p class="text-[10px] text-gray-500 mt-1">
                                    Aktifken form dolduran personel talebi reddedebilir veya red çıkışından akış yönlendirilebilir.
                                </p>
                            </div>
                        </div>

                        <!-- ========== ONAY / İNCELEME AYARLARI ========== -->
                        <div v-if="['approval', 'review'].includes(selectedNodeForSettings.data.taskType)" class="space-y-4">
                            <div class="pt-2 border-t border-[#334155]">
                                <label class="block text-xs font-semibold text-gray-400 mb-1 uppercase tracking-wider mt-2">Onaylayan / Atanan Kişi</label>
                                <p class="text-[10px] text-gray-500 mb-2">Bu adımı kimin onaylayacağını veya inceleyeceğini belirleyin.</p>
                                <select v-model="selectedNodeForSettings.data.assignType" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2 mb-3">
                                    <option value="starter">Süreci Başlatan Kişi</option>
                                    <option value="hierarchy">Hiyerarşik Yönetici</option>
                                    <option value="department">Belirli Bir Departman Amiri</option>
                                    <option value="directorate">Belirli Bir Direktörlük Yöneticisi</option>
                                    <option value="user">Belirli Bir Kullanıcı</option>
                                    <option value="role">Belirli Bir Rol</option>
                                </select>

                                <div v-if="selectedNodeForSettings.data.assignType === 'hierarchy'">
                                    <select v-model="selectedNodeForSettings.data.assignValue" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2">
                                        <option value="manager_1">1. Amir (Doğrudan Yönetici)</option>
                                        <option value="manager_2">2. Amir (Yöneticinin Yöneticisi)</option>
                                    </select>
                                </div>
                                <div v-else-if="selectedNodeForSettings.data.assignType === 'department'" class="space-y-3">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-300 mb-1">Hedef Departman</label>
                                        <select 
                                            v-model="selectedNodeForSettings.data.assignValue" 
                                            @change="onDepartmentChanged"
                                            class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2"
                                        >
                                            <option value="">-- Departman Seçiniz --</option>
                                            <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                                        </select>
                                    </div>

                                    <div v-if="selectedNodeForSettings.data.assignValue" class="space-y-2 bg-[#1e293b]/70 p-3 rounded-lg border border-[#334155]">
                                        <div>
                                            <label class="block text-[11px] font-bold text-teal-400 mb-1 flex items-center justify-between">
                                                <span>Hangi Yönetici / Amir?</span>
                                                <span class="text-[9px] text-gray-400 font-normal">Tek Yetkili</span>
                                            </label>
                                            <select 
                                                v-model="selectedNodeForSettings.data.deptManagerType" 
                                                class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2"
                                            >
                                                <optgroup label="🏢 Kadro / Görev Kademesine Göre (Tek Yetkili)">
                                                    <option value="manager">👔 Departman Müdürü (Genel / Asil)</option>
                                                    <option value="assistant_manager">🥈 Departman Müdür Yardımcısı</option>
                                                    <option value="chief">👷 İşletme Şefi / Birim Amiri</option>
                                                </optgroup>
                                                <optgroup label="👥 Kadro Bazlı Çoklu Atama (Tüm Kademeler)">
                                                    <option value="all_managers">👔 Departmandaki Tüm Müdürler & Yrd. (Çoklu)</option>
                                                    <option value="all_chiefs">👷 Departmandaki Tüm İşletme Şefleri / Amirler (Çoklu)</option>
                                                </optgroup>
                                                <optgroup label="👤 Departmandan Doğrudan Kişi(ler) Seç">
                                                    <option value="specific_user">🎯 Tek Personel / Yetkili Seç (İsimle)</option>
                                                    <option value="specific_users">👥 Birden Fazla Personel / Yetkili Seç (Çoklu)</option>
                                                </optgroup>
                                            </select>
                                        </div>

                                        <!-- specific_user seçildiğinde (Tekil) -->
                                        <div v-if="selectedNodeForSettings.data.deptManagerType === 'specific_user'" class="pt-2 border-t border-[#334155]">
                                            <label class="block text-[10px] font-semibold text-amber-300 mb-1">
                                                Departman Yetkilisi / Personeli
                                            </label>
                                            <select 
                                                v-if="currentDepartmentUsers.length > 0"
                                                v-model="selectedNodeForSettings.data.assignedUserId" 
                                                class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-amber-500 px-3 py-2"
                                            >
                                                <option value="">-- Personel Seçiniz --</option>
                                                <option v-for="u in currentDepartmentUsers" :key="u.id" :value="u.id">
                                                    {{ u.name }} — {{ u.title || 'Yetkili' }}
                                                </option>
                                            </select>
                                            <div v-else class="space-y-1">
                                                <p class="text-[10px] text-amber-200/90 bg-amber-950/40 p-2 rounded border border-amber-500/30">
                                                    ℹ Bu departmana doğrudan bağlı personel bulunamadı. Şirket genelinden atama yapabilirsiniz:
                                                </p>
                                                <select 
                                                    v-model="selectedNodeForSettings.data.assignedUserId" 
                                                    class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-amber-500 px-3 py-2"
                                                >
                                                    <option value="">-- Tüm Personellerden Seç --</option>
                                                    <option v-for="u in users" :key="u.id" :value="u.id">
                                                        {{ u.name }} ({{ u.title || 'Personel' }})
                                                    </option>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- specific_users seçildiğinde (Çoklu Personel Seçimi) -->
                                        <div v-if="selectedNodeForSettings.data.deptManagerType === 'specific_users'" class="pt-2 border-t border-[#334155]">
                                            <div class="flex items-center justify-between mb-1">
                                                <label class="block text-[10px] font-semibold text-amber-300">
                                                    Departman Yetkilileri / Personeli (Çoklu)
                                                </label>
                                                <span class="text-[9px] text-amber-400 font-mono">
                                                    {{ selectedNodeForSettings.data.assignedUserIds?.length || 0 }} kişi seçili
                                                </span>
                                            </div>
                                            <div class="max-h-36 overflow-y-auto bg-[#0f172a]/70 rounded p-2 border border-amber-500/30 space-y-1">
                                                <label 
                                                    v-for="u in (currentDepartmentUsers.length > 0 ? currentDepartmentUsers : users)" 
                                                    :key="u.id" 
                                                    class="flex items-center gap-2 text-xs text-gray-200 hover:bg-[#1e293b] p-1 rounded cursor-pointer"
                                                >
                                                    <input 
                                                        type="checkbox" 
                                                        :value="u.id" 
                                                        v-model="selectedNodeForSettings.data.assignedUserIds"
                                                        class="rounded border-gray-600 bg-[#334155] text-amber-500 focus:ring-amber-500"
                                                    />
                                                    <span class="truncate">{{ u.name }} — {{ u.title || 'Yetkili' }}</span>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Hedefli Bildirim Güvencesi -->
                                        <div class="mt-2 p-2 rounded bg-emerald-950/30 border border-emerald-500/30 text-[10px] text-emerald-300 flex items-start gap-1.5">
                                            <span class="text-xs">🛡</span>
                                            <span class="leading-tight">
                                                <strong>Hedefli Bildirim:</strong>
                                                Görev ve e-posta bildirimleri yalnızca yukarıda belirlenen yetkili(ler)e iletilir.
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div v-else-if="selectedNodeForSettings.data.assignType === 'directorate'">
                                    <select v-model="selectedNodeForSettings.data.assignValue" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2">
                                        <option v-for="dir in directorates" :key="dir.id" :value="dir.id">{{ dir.name }}</option>
                                    </select>
                                </div>
                                <div v-else-if="selectedNodeForSettings.data.assignType === 'user'" class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-[10px] text-gray-400">Hedef Kullanıcı(lar)ı Seçin (Tekli / Çoklu)</label>
                                        <span class="text-[9px] text-teal-400 font-mono">
                                            {{ selectedNodeForSettings.data.assignedUserIds?.length || (selectedNodeForSettings.data.assignValue ? 1 : 0) }} kişi seçili
                                        </span>
                                    </div>
                                    <div class="max-h-40 overflow-y-auto bg-[#0f172a]/70 rounded p-2 border border-[#334155] space-y-1">
                                        <label 
                                            v-for="user in users" 
                                            :key="user.id" 
                                            class="flex items-center gap-2 text-xs text-gray-200 hover:bg-[#1e293b] p-1.5 rounded cursor-pointer transition-colors"
                                        >
                                            <input 
                                                type="checkbox" 
                                                :value="user.id" 
                                                v-model="selectedNodeForSettings.data.assignedUserIds"
                                                class="rounded border-gray-600 bg-[#334155] text-teal-500 focus:ring-teal-500"
                                            />
                                            <span class="truncate">{{ user.name }} ({{ user.title || 'Kullanıcı' }})</span>
                                        </label>
                                    </div>
                                </div>
                                <div v-else-if="selectedNodeForSettings.data.assignType === 'role'">
                                    <select v-model="selectedNodeForSettings.data.assignValue" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2">
                                        <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                                    </select>
                                </div>

                                <!-- ========== ONAY KARAR POLİTİKASI (ÇOKLU ONAY / KONSENSÜS / HAVUZ) ========== -->
                                <div class="pt-3 border-t border-[#334155]">
                                    <label class="block text-xs font-semibold text-teal-400 mb-1 uppercase tracking-wider">
                                        👥 Onay Karar Politikası (Çoklu Yetkili)
                                    </label>
                                    <p class="text-[10px] text-gray-400 mb-2">
                                        Birden fazla kişi veya rol atandığında onay akış kuralını belirleyin:
                                    </p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <label 
                                            class="flex flex-col p-2.5 rounded-lg border cursor-pointer transition-all text-xs"
                                            :class="selectedNodeForSettings.data.approvalPolicy !== 'all' 
                                                ? 'bg-teal-950/60 border-teal-500 text-teal-200 shadow-sm' 
                                                : 'bg-[#1e293b] border-gray-700 text-gray-400 hover:border-gray-600'"
                                        >
                                            <div class="flex items-center gap-1.5 font-bold mb-1">
                                                <input type="radio" value="any" v-model="selectedNodeForSettings.data.approvalPolicy" class="text-teal-500 focus:ring-0" />
                                                <span>⚡ İlk Onaylayan Yeterli</span>
                                            </div>
                                            <span class="text-[10px] text-gray-400 font-normal leading-tight">
                                                Vardiya / havuz modeli. Atanan kişilerden herhangi biri onaylayınca süreç hemen ilerler, diğer görevler otomatik iptal edilir.
                                            </span>
                                        </label>
                                        <label 
                                            class="flex flex-col p-2.5 rounded-lg border cursor-pointer transition-all text-xs"
                                            :class="selectedNodeForSettings.data.approvalPolicy === 'all' 
                                                ? 'bg-indigo-950/60 border-indigo-500 text-indigo-200 shadow-sm' 
                                                : 'bg-[#1e293b] border-gray-700 text-gray-400 hover:border-gray-600'"
                                        >
                                            <div class="flex items-center gap-1.5 font-bold mb-1">
                                                <input type="radio" value="all" v-model="selectedNodeForSettings.data.approvalPolicy" class="text-indigo-500 focus:ring-0" />
                                                <span>🤝 Tümünün Onayı Şart</span>
                                            </div>
                                            <span class="text-[10px] text-gray-400 font-normal leading-tight">
                                                Konsensüs modeli. Atanan tüm yetkililerin tek tek onay vermesi şarttır. Herkes onay verene kadar süreç bekler.
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-400 mb-1 uppercase tracking-wider mt-2">Bağlı Form (Opsiyonel)</label>
                                <p class="text-[10px] text-gray-500 mb-2">Onay makamının dolduracağı formu seçin (Örn: FR-LVH-02 İşletme Kabul ve Termin Bildirim Formu).</p>
                                <select v-model="selectedNodeForSettings.data.subFormId" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2">
                                    <option value="">-- Standart Dahili Onay (Formsuz) --</option>
                                    <option v-for="f in forms" :key="f.id" :value="f.id">{{ f.name }}</option>
                                </select>
                            </div>

                            <div class="pt-2 border-t border-[#334155]">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        v-model="selectedNodeForSettings.data.rejectEnabled"
                                        type="checkbox"
                                        class="rounded border-gray-500 bg-[#334155] text-red-500 focus:ring-red-500"
                                    />
                                    <span class="text-sm text-gray-200">Reddetme seçeneği</span>
                                </label>
                                <p class="text-[10px] text-gray-500 mt-1">
                                    Aktifken atanan kişi reddedebilir. Red çıkışını hangi düğüme bağlarsanız süreç oradan devam eder.
                                </p>
                            </div>

                            <!-- İşletme Termin Zorunluluğu (requireTermin) -->
                            <div class="pt-3 border-t border-[#334155]">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        v-model="selectedNodeForSettings.data.requireTermin"
                                        type="checkbox"
                                        class="rounded border-gray-500 bg-[#334155] text-amber-500 focus:ring-amber-500"
                                    />
                                    <span class="text-sm text-amber-300 font-semibold flex items-center gap-1.5">
                                        📅 İşletme Termin Bildirimi Zorunlu Tut
                                    </span>
                                </label>
                                <p class="text-[10px] text-gray-400 mt-1 leading-relaxed">
                                    Aktifken işletme/yönetici kabul onayı verirken bir <strong>Termin Aralığı</strong> girmeden onaylayamaz. Görev ekranında Termin Bildirimi paneli ve takvim girişi otomatik olarak açılır.
                                </p>
                            </div>

                            <!-- Koşullu Onay Doğrulaması (Zorunlu Alan Doğrulaması) -->
                            <div class="pt-3 border-t border-[#334155]">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        v-model="selectedNodeForSettings.data.requireFieldOnApprove"
                                        type="checkbox"
                                        class="rounded border-gray-500 bg-[#334155] text-amber-500 focus:ring-amber-500"
                                    />
                                    <span class="text-sm text-amber-300 font-semibold">Onaylarken Özel Alan Zorunlu Tut</span>
                                </label>
                                <p class="text-[10px] text-gray-400 mt-1">
                                    Onay butonuna basıldığında formda/süreçte belirli bir alanın (Örn: fatura_no, teslim_tarihi) doldurulmuş olmasını zorunlu kılar.
                                </p>

                                <div v-if="selectedNodeForSettings.data.requireFieldOnApprove" class="mt-3 space-y-2 bg-[#0f172a]/50 p-3 rounded-lg border border-amber-500/30">
                                    <div>
                                        <label class="block text-[11px] font-bold text-gray-300 mb-1 uppercase tracking-wider">Zorunlu Alan Anahtarı</label>
                                        <input 
                                            v-model="selectedNodeForSettings.data.requiredFieldName" 
                                            type="text" 
                                            placeholder="Örn: termin_tarihi, fatura_no, teslim_tarihi" 
                                            class="w-full bg-[#334155] border-none rounded text-xs text-white focus:ring-1 focus:ring-amber-500 px-3 py-2 font-mono"
                                        >
                                        <span class="text-[9px] text-gray-400">Form şablonundaki alanın değişken adı (field key)</span>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-bold text-gray-300 mb-1 uppercase tracking-wider">Özel Hata Mesajı</label>
                                        <input 
                                            v-model="selectedNodeForSettings.data.requiredFieldErrorMessage" 
                                            type="text" 
                                            placeholder="Örn: Numuneyi kabul ederken bir termin tarihi bildirmek zorunludur." 
                                            class="w-full bg-[#334155] border-none rounded text-xs text-white focus:ring-1 focus:ring-amber-500 px-3 py-2"
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ========== SÜREÇ TAKİBİ AYARLARI ========== -->
                        <div v-if="selectedNodeForSettings.data.taskType === 'follow_up'" class="pt-4 border-t border-[#334155] space-y-3">
                            <div class="flex items-center gap-2">
                                <span class="text-base">⏱</span>
                                <h4 class="text-xs font-bold text-sky-400 uppercase tracking-wider">Süreç Takibi Ayarları</h4>
                            </div>
                            <p class="text-[10px] text-gray-400 leading-relaxed">
                                Bu düğüm süreç bu adıma ulaştıktan sonra belirlenen gün kadar bekleyip sorumluya (satışçıya) otomatik takip görevi ve sorgulama e-postası oluşturur.
                            </p>

                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 mb-1 uppercase tracking-wider">Takip / Anket Başlığı</label>
                                <input 
                                    v-model="selectedNodeForSettings.data.followUpTitle" 
                                    type="text" 
                                    placeholder="Örn: Numune Sipariş Takibi veya Dosya Kontrolü" 
                                    class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-sky-500 px-3 py-2"
                                >
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 mb-1 uppercase tracking-wider">Soru / Açıklama Metni</label>
                                <textarea 
                                    v-model="selectedNodeForSettings.data.followUpPrompt" 
                                    rows="3" 
                                    placeholder="Örn: Numune siparişe dönüştü mü? Dönmediyse sebebini belirtiniz." 
                                    class="w-full bg-[#334155] border-none rounded text-xs text-white focus:ring-1 focus:ring-sky-500 px-3 py-2"
                                ></textarea>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 mb-1 uppercase tracking-wider">Bağlı Takip / Anket Formu (Opsiyonel)</label>
                                <p class="text-[10px] text-gray-500 mb-1.5">/form-templates/create adresinden oluşturulan takip formunu bağlayabilirsiniz.</p>
                                <select v-model="selectedNodeForSettings.data.subFormId" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-sky-500 px-3 py-2">
                                    <option value="">-- Standart Sonuç Seçenekleri (Formsuz) --</option>
                                    <option v-for="f in forms" :key="f.id" :value="f.id">{{ f.name }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 mb-1 uppercase tracking-wider">Kime Görev / Mail Atanacak?</label>
                                <select v-model="selectedNodeForSettings.data.assignType" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-sky-500 px-3 py-2">
                                    <option value="starter">Süreci Başlatan Kişi (Satış Yetkilisi / Talep Sahibi)</option>
                                    <option value="user">Belirli Bir Kullanıcı</option>
                                    <option value="role">Belirli Bir Rol</option>
                                </select>

                                <div v-if="selectedNodeForSettings.data.assignType === 'user'" class="mt-2">
                                    <label class="block text-[10px] text-gray-400 mb-1">Kullanıcı Seçin</label>
                                    <select v-model="selectedNodeForSettings.data.assignValue" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-sky-500 px-3 py-2">
                                        <option value="">-- Kullanıcı Seçin --</option>
                                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                                    </select>
                                </div>
                                <div v-else-if="selectedNodeForSettings.data.assignType === 'role'" class="mt-2">
                                    <label class="block text-[10px] text-gray-400 mb-1">Rol Seçin</label>
                                    <select v-model="selectedNodeForSettings.data.assignValue" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-sky-500 px-3 py-2">
                                        <option value="">-- Rol Seçin --</option>
                                        <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-2 pt-2 border-t border-[#334155]">
                                <div>
                                    <label class="block text-[10px] text-gray-400 mb-1">İlk Süre (Gün)</label>
                                    <input 
                                        v-model.number="selectedNodeForSettings.data.followUpDays" 
                                        type="number" 
                                        min="1" 
                                        placeholder="Ayarlar" 
                                        class="w-full bg-[#334155] border-none rounded text-xs text-white focus:ring-1 focus:ring-sky-500 px-2.5 py-1.5"
                                    >
                                    <span class="text-[9px] text-gray-500">Boşsa sistem ayarı</span>
                                </div>
                                <div>
                                    <label class="block text-[10px] text-gray-400 mb-1">Aralık (Gün)</label>
                                    <input 
                                        v-model.number="selectedNodeForSettings.data.followUpIntervalDays" 
                                        type="number" 
                                        min="1" 
                                        placeholder="Ayarlar" 
                                        class="w-full bg-[#334155] border-none rounded text-xs text-white focus:ring-1 focus:ring-sky-500 px-2.5 py-1.5"
                                    >
                                    <span class="text-[9px] text-gray-500">Hatırlatma aralığı</span>
                                </div>
                                <div>
                                    <label class="block text-[10px] text-gray-400 mb-1">Maks. Tekrar</label>
                                    <input 
                                        v-model.number="selectedNodeForSettings.data.followUpMaxReminders" 
                                        type="number" 
                                        min="1" 
                                        placeholder="Ayarlar" 
                                        class="w-full bg-[#334155] border-none rounded text-xs text-white focus:ring-1 focus:ring-sky-500 px-2.5 py-1.5"
                                    >
                                    <span class="text-[9px] text-gray-500">Maks. bildirim</span>
                                </div>
                            </div>
                        </div>

                        <!-- ========== BELGE & SERTİFİKA ÜRETİCİ AYARLARI ========== -->
                        <div v-if="selectedNodeForSettings.data.taskType === 'document_gen'" class="pt-4 border-t border-[#334155] space-y-3">
                            <div class="flex items-center gap-2">
                                <span class="text-base">📜</span>
                                <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider">Belge & Sertifika Üretici</h4>
                            </div>
                            <p class="text-[10px] text-gray-400 leading-relaxed">
                                Süreç verilerini derleyerek otomatik PDF dokümanı oluşturur ve süreç detay sayfasına ekler.
                            </p>

                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 mb-1 uppercase tracking-wider">Belge Başlığı</label>
                                <input 
                                    v-model="selectedNodeForSettings.data.documentTitle" 
                                    type="text" 
                                    placeholder="Örn: Analiz Sertifikası (CoA) veya Teslim Tutanağı" 
                                    class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2"
                                >
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 mb-1 uppercase tracking-wider">Belge Şablon Türü</label>
                                <select v-model="selectedNodeForSettings.data.documentType" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2">
                                    <option value="certificate_of_analysis">Analiz Sertifikası (CoA - Certificate of Analysis)</option>
                                    <option value="delivery_report">Teslim & Tesellüm Tutanağı</option>
                                    <option value="form_summary">Süreç & Form Verileri Özeti</option>
                                </select>
                            </div>
                        </div>

                        <!-- ========== SAP S/4HANA ENTEGRASYON AYARLARI ========== -->
                        <div v-if="selectedNodeForSettings.data.taskType === 'sap_sync'" class="pt-4 border-t border-[#334155] space-y-3">
                            <div class="flex items-center gap-2">
                                <span class="text-base">🔄</span>
                                <h4 class="text-xs font-bold text-sky-400 uppercase tracking-wider">SAP S/4HANA Aktarımı</h4>
                            </div>
                            <p class="text-[10px] text-gray-400 leading-relaxed">
                                Süreç bu adıma geldiğinde SAP S/4HANA servisine veri aktarımı tetikler.
                            </p>

                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 mb-1 uppercase tracking-wider">SAP Nesne Türü</label>
                                <select v-model="selectedNodeForSettings.data.sapObjectType" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-sky-500 px-3 py-2">
                                    <option value="sales_order">Müşteri Siparişi (VA01 - Sales Order)</option>
                                    <option value="purchase_requisition">Satınalma Talebi (ME51N - Purchase Requisition)</option>
                                    <option value="goods_movement">Malzeme Hareketi (MIGO - Goods Movement)</option>
                                    <option value="custom">Özel BAPI / OData Servisi</option>
                                </select>
                            </div>

                            <div class="pt-2">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        v-model="selectedNodeForSettings.data.sapSimulation"
                                        type="checkbox"
                                        class="rounded border-gray-500 bg-[#334155] text-sky-500 focus:ring-sky-500"
                                    />
                                    <span class="text-sm text-gray-200">Simülasyon / Test Modu</span>
                                </label>
                                <p class="text-[10px] text-gray-400 mt-1">
                                    İşaretliyken SAP sunucusuna gerçek çağrı atmak yerine simüle test yanıtı kaydeder.
                                </p>
                            </div>
                        </div>

                        <!-- ========== MAİL BİLDİRİMİ AYARLARI ========== -->
                        <div v-if="selectedNodeForSettings.data.taskType === 'notify'" class="pt-4 border-t border-[#334155] space-y-4">
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">E-Posta Gönderim Ayarları</h4>

                            <!-- Kime (To) -->
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 mb-1 uppercase tracking-widest">Kime Gönderilecek?</label>
                                <select v-model="selectedNodeForSettings.data.notifyTo" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2">
                                    <option value="" disabled>-- Alıcı Seçin --</option>
                                    <option value="initiator">Süreci Başlatan Kişi (Form Sahibi)</option>
                                    <option value="department_manager">Departman Yöneticisi</option>
                                    <option value="custom">Özel E-posta Adresi (Manuel Giriş)</option>
                                </select>
                            </div>

                            <!-- Özel E-posta (Eğer üstteki 'custom' seçildiyse görünür) -->
                            <div v-if="selectedNodeForSettings.data.notifyTo === 'custom'">
                                <label class="block text-[11px] font-bold text-gray-400 mb-1 uppercase tracking-widest">E-Posta Adresi</label>
                                <input v-model="selectedNodeForSettings.data.notifyEmail" type="email" placeholder="ornek@koksan.com" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2">
                            </div>

                            <!-- Konu (Subject) -->
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 mb-1 uppercase tracking-widest">Mail Konusu</label>
                                <input v-model="selectedNodeForSettings.data.notifySubject" type="text" placeholder="Örn: Süreç Bilgilendirmesi" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2">
                            </div>

                            <!-- Mesaj (Body) -->
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 mb-1 uppercase tracking-widest">Mesaj İçeriği</label>
                                <textarea v-model="selectedNodeForSettings.data.notifyMessage" rows="4" placeholder="Kullanıcıya gidecek mesajı buraya yazın..." class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2 custom-scrollbar"></textarea>
                            </div>
                        </div>

                        <!-- ========== BİTİŞ DÜĞÜMÜ AYARLARI ========== -->
                        <div v-if="selectedNodeForSettings.data.taskType === 'end'" class="pt-4 border-t border-[#334155]">
                            <label class="block text-xs font-semibold text-red-400 mb-2 uppercase tracking-wider">Süreç Sonuç Durumu</label>
                            <p class="text-[10px] text-gray-400 mb-3">Süreç bu düğüme ulaşıp sonlandığında veritabanına hangi durumla (status) kaydedileceğini seçin.</p>
                            <select v-model="selectedNodeForSettings.data.processStatus" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-red-500 px-3 py-2">
                                <option value="completed">Başarıyla Tamamlandı (Olumlu)</option>
                                <option value="rejected">Reddedildi / İptal Edildi (Olumsuz)</option>
                            </select>
                        </div>
                        </div>

                        <!-- ========== GELİŞMİŞ: ZAMANLAMA ========== -->
                        <div v-show="settingsTab === 'advanced'" class="space-y-5">
                            <div v-if="isHumanTaskNode(selectedNodeForSettings)" class="pt-2">
                                <label class="block text-xs font-semibold text-gray-400 mb-1 uppercase tracking-wider">Görev Zamanlaması</label>
                                <p class="text-[10px] text-gray-500 mb-3">Görev atandığında süre başlar. Atanan kişi Görevlerim'de kalan süreyi görür.</p>

                                <label class="flex items-center gap-2 mb-3 cursor-pointer">
                                    <input
                                        v-model="selectedNodeForSettings.data.scheduleEnabled"
                                        type="checkbox"
                                        class="rounded border-gray-500 bg-[#334155] text-teal-500 focus:ring-teal-500"
                                    />
                                    <span class="text-sm text-gray-200">Bu adım için süre tanımla</span>
                                </label>

                                <div v-if="selectedNodeForSettings.data.scheduleEnabled" class="space-y-2">
                                    <div class="grid grid-cols-3 gap-2">
                                        <div>
                                            <label class="block text-[10px] text-gray-500 mb-1">Gün</label>
                                            <input
                                                v-model.number="selectedNodeForSettings.data.scheduleDays"
                                                type="number"
                                                min="0"
                                                class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-gray-500 mb-1">Saat</label>
                                            <input
                                                v-model.number="selectedNodeForSettings.data.scheduleHours"
                                                type="number"
                                                min="0"
                                                class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-gray-500 mb-1">Dakika</label>
                                            <input
                                                v-model.number="selectedNodeForSettings.data.scheduleMinutes"
                                                type="number"
                                                min="0"
                                                class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2"
                                            />
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-teal-400">
                                        Toplam: {{ formatScheduleSummary(selectedNodeForSettings.data) }}
                                    </p>
                                </div>

                                <!-- BPMN 2.0 ZAMAN SINIR OLAYI & HAT KESİCİ (BOUNDARY TIMER) -->
                                <div class="pt-4 border-t border-[#334155] space-y-3">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-xs font-semibold text-rose-400 uppercase tracking-wider flex items-center gap-1.5">
                                            <span>⏱️</span> BPMN 2.0 Zaman Sınır Olayı (Boundary Timer)
                                        </label>
                                        <span class="text-[10px] font-mono text-gray-400 bg-[#0f172a] px-2 py-0.5 rounded border border-gray-700">Hat Kesici</span>
                                    </div>
                                    <p class="text-[10px] text-gray-400 leading-relaxed">
                                        Görev belirlenen süre içinde tamamlanmazsa süreci duraksatmadan otomatik üst amire eskale eder veya sistem adına onaylar.
                                    </p>

                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            v-model="selectedNodeForSettings.data.boundaryTimer.enabled"
                                            type="checkbox"
                                            class="rounded border-gray-500 bg-[#334155] text-rose-500 focus:ring-rose-500"
                                        />
                                        <span class="text-sm font-semibold text-rose-300">Zaman Sınır Olayını (Hat Kesici) Etkinleştir</span>
                                    </label>

                                    <div v-if="selectedNodeForSettings.data.boundaryTimer?.enabled" class="mt-3 space-y-3 bg-[#0f172a]/70 p-3 rounded-lg border border-rose-500/30">
                                        <div>
                                            <label class="block text-[11px] font-bold text-gray-300 mb-1">Maksimum Bekleme Süresi (SLA Saati)</label>
                                            <div class="flex items-center gap-2">
                                                <input 
                                                    v-model.number="selectedNodeForSettings.data.boundaryTimer.duration_hours" 
                                                    type="number" 
                                                    min="1" 
                                                    class="w-28 bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-rose-500 px-3 py-1.5"
                                                />
                                                <span class="text-xs text-gray-400">Saat sonra tetiklensin</span>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-[11px] font-bold text-gray-300 mb-1">Süre Aşımında Uygulanacak Hat Kesici Aksiyonu</label>
                                            <div class="space-y-2">
                                                <label class="flex items-start gap-2 text-xs text-gray-200 cursor-pointer p-1.5 rounded hover:bg-[#1e293b]">
                                                    <input type="radio" value="auto_escalate" v-model="selectedNodeForSettings.data.boundaryTimer.action" class="text-rose-500 mt-0.5" />
                                                    <div>
                                                        <strong class="text-rose-300 block">⚠️ Üst Amirine Eskale Et (Devret)</strong>
                                                        <span class="text-[10px] text-gray-400">Görev atanan personelden alınıp kullanıcının doğrudan bağlı olduğu departman amirine devredilir.</span>
                                                    </div>
                                                </label>

                                                <label class="flex items-start gap-2 text-xs text-gray-200 cursor-pointer p-1.5 rounded hover:bg-[#1e293b]">
                                                    <input type="radio" value="auto_approve" v-model="selectedNodeForSettings.data.boundaryTimer.action" class="text-teal-500 mt-0.5" />
                                                    <div>
                                                        <strong class="text-teal-300 block">⚡ Sistem Adına Otomatik Onayla (Auto-Approve)</strong>
                                                        <span class="text-[10px] text-gray-400">Zaman aşımında görev sistem tarafından otomatik onaylanır ve akış bir sonraki adıma geçirilir.</span>
                                                    </div>
                                                </label>

                                                <label class="flex items-start gap-2 text-xs text-gray-200 cursor-pointer p-1.5 rounded hover:bg-[#1e293b]">
                                                    <input type="radio" value="notify_reminder" v-model="selectedNodeForSettings.data.boundaryTimer.action" class="text-amber-500 mt-0.5" />
                                                    <div>
                                                        <strong class="text-amber-300 block">🚨 Acil SLA Hatırlatması Gönder</strong>
                                                        <span class="text-[10px] text-gray-400">Personele ve amirine kırmızı acil durum bildirimi fırlatır, görevi devretmez.</span>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-sm text-gray-500">
                                Bu düğüm tipi için gelişmiş ayar bulunmuyor.
                            </p>
                        </div>
                        
                    </div>
                    
                    <div v-else-if="selectedEdge" class="space-y-4">
                        <div class="bg-[#334155] p-3 rounded text-xs mb-4 text-gray-300">
                            <strong>Kaynak:</strong> {{ selectedEdge.source }} <br/>
                            <strong>Hedef:</strong> {{ selectedEdge.target }}
                        </div>

                        <button @click="removeEdges([selectedEdge.id]); selectedEdge = null" class="w-full bg-red-900/30 hover:bg-red-900/50 text-red-400 font-bold py-2 px-4 rounded text-sm transition-colors border border-red-900/50">
                            Bağlantıyı Kopar
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <RuleBuilderModal 
            :show="isRuleModalOpen" 
            :workflow-id="workflow?.id" 
            :node-id="selectedNodeForSettings?.id"
            :available-nodes="getNodes.filter(n => n.id !== selectedNodeForSettings?.id)"
            @close="isRuleModalOpen = false" 
        />

        <!-- Yeni Versiyon Yayınlama Modalı (Changelog & Publish) -->
        <div v-if="isPublishVersionModalOpen" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm animate-fadeIn">
            <div class="relative w-full max-w-lg bg-slate-900 border border-slate-700/80 rounded-2xl shadow-2xl overflow-hidden text-slate-200">
                <div class="px-6 py-4 border-b border-slate-800 bg-gradient-to-r from-slate-900 via-purple-950/40 to-slate-900 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-purple-500/20 border border-purple-500/30 flex items-center justify-center text-lg text-purple-400">
                            ✨
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white tracking-wide">Yeni Süreç Versiyonu Yayınla</h3>
                            <p class="text-xs text-slate-400">Zero-Downtime Process Versioning (v{{ (workflow?.version || 1) + 1 }}.0)</p>
                        </div>
                    </div>
                    <button @click="isPublishVersionModalOpen = false" class="text-slate-400 hover:text-white p-1 rounded-lg">✕</button>
                </div>
                <div class="p-6 space-y-4 text-xs">
                    <div class="p-3.5 rounded-xl bg-purple-950/30 border border-purple-500/30 text-purple-300 space-y-1.5">
                        <div class="font-bold flex items-center gap-1.5 text-white">
                            <span>🛡️</span> <span>Kesintisiz Üretim Güvencesi (Zero-Downtime)</span>
                        </div>
                        <p class="text-slate-300 leading-relaxed">
                            Mevcut sürüm (v{{ workflow?.version || 1 }}.0) üzerinde çalışan canlı süreçler kesintiye uğramaz ve kendi orijinal şemalarıyla tamamlanır. Yeni başlatılacak tüm talepler otomatik olarak bu yeni sürüme (v{{ (workflow?.version || 1) + 1 }}.0) yönlendirilecektir.
                        </p>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-300 mb-1.5">
                            Değişiklik Notları / Changelog <span class="text-purple-400">*</span>
                        </label>
                        <textarea
                            v-model="publishVersionNotes"
                            rows="3"
                            placeholder="Bu versiyonda neler değişti? (Örn: Paralel onay adımları eklendi, laboratuvar test toleransları güncellendi)"
                            class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:ring-1 focus:ring-purple-500 focus:border-purple-500"
                        ></textarea>
                    </div>
                </div>
                <div class="px-6 py-3 border-t border-slate-800 bg-slate-900/80 flex items-center justify-end gap-2.5">
                    <button 
                        type="button" 
                        @click="isPublishVersionModalOpen = false"
                        class="px-4 py-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"
                    >
                        Vazgeç
                    </button>
                    <button 
                        type="button"
                        @click="publishNewVersion"
                        :disabled="isPublishingVersion"
                        class="px-5 py-2 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold shadow-lg shadow-purple-600/20 transition-all flex items-center gap-1.5 disabled:opacity-50"
                    >
                        <span v-if="isPublishingVersion" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                        <span>{{ isPublishingVersion ? 'Yayınlanıyor...' : 'v' + ((workflow?.version || 1) + 1) + '.0 Olarak Yayınla' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
/* Vue flow stillerinin düzgün görünmesi için konteynere boyut verilmeli */
.vue-flow__wrapper {
  width: 100%;
  height: 100%;
}
</style>
