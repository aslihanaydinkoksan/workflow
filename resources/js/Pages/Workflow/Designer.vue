<script setup>
import { ref, computed, onMounted, nextTick, provide } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
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

// forms listesini çocuk düğüm bileşenlerine aktar (BaseNode inject ile alacak)
const formsRef = computed(() => props.forms);
provide('formsList', formsRef);

const elements = ref([
    { id: '1', type: 'start', position: { x: 250, y: 5 }, data: { label: 'Başlangıç' } }
]);

onMounted(() => {
    if (form.nodes.length > 0) {
        form.nodes.forEach((node) => {
            ensureScheduleDefaults(node);
            ensureRejectDefaults(node);
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

const nodeTypes = [
    { type: 'start', label: 'Başlangıç', bgColor: '#10b981', description: 'Süreci başlatır', taskType: 'start' },
    { type: 'io', label: 'Form Görevi', bgColor: '#3b82f6', description: 'Personele form atar', taskType: 'form' },
    { type: 'decision', label: 'Onay Mekanizması', bgColor: '#f59e0b', description: 'Yönetici onayına sunar', taskType: 'approval' },
    { type: 'task', label: 'İnceleme (Bilgi)', bgColor: '#8b5cf6', description: 'Sadece bilgilendirir', taskType: 'review' },
    { type: 'io', label: 'Mail Bildirimi', bgColor: '#06b6d4', description: 'Otomatik e-posta atar', taskType: 'notify' },
    { type: 'task', label: 'Onayla ve İmzala', bgColor: '#14b8a6', description: 'E-İmza ile Onay', taskType: 'review' },
    { type: 'document', label: 'İmza Belgesi', bgColor: '#64748b', description: 'İmzalı Belge Çıktısı', taskType: 'document' },
    { type: 'io', label: 'Scheduled Email', bgColor: '#8b5cf6', description: 'Zamanlanmış e-posta', taskType: 'notify' },
    { type: 'task', label: 'PDF Üret', bgColor: '#475569', description: 'Sistemsel PDF Çıktısı', taskType: 'review' },
    { type: 'io', label: 'Webhook', bgColor: '#334155', description: 'Dış API tetiklemesi', taskType: 'notify' },
    { type: 'storage', label: 'Depo / Bekleme', bgColor: '#eab308', description: 'Bekleme / Depolama Noktası', taskType: 'storage' },
    { type: 'decision', label: 'Sistem Kararı (Kural)', bgColor: '#ec4899', description: 'Kurallara göre otomatik yönlendirir', taskType: 'system_rule' },
    { type: 'end', label: 'Bitiş (End)', bgColor: '#ef4444', description: 'Süreci tamamlar', taskType: 'end' },
];

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
    }
    selectedNodeForSettings.value = node ?? null;
    settingsTab.value = 'general';
};

const isHumanTaskNode = (node) => node && humanTaskTypes.includes(node.data?.taskType);

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
        baseData.description = '';
        baseData.rejectEnabled = true;
    }

    if (nodeData.taskType === 'form') {
        baseData.rejectEnabled = false;
    }

    // Sadece form düğümü için form seçimi
    if (nodeData.taskType === 'form') {
        baseData.subFormId = '';
        baseData.assignType = 'starter';
        baseData.assignValue = '';
        baseData.description = '';
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
                        <button @click="saveWorkflow('active')" class="text-teal-200 hover:text-white pb-1 transition-colors">YAYINLA</button>
                    </div>
                </div>
                
                <div class="flex flex-col items-end space-y-1 mt-4 md:mt-0">
                    <div class="flex items-center space-x-3">
                        <span class="text-sm mr-2 text-green-300">
                            Kayıt = yayınla + süreci başlat
                        </span>
                        <button @click="saveWorkflow('active')" :disabled="form.processing" class="bg-white text-teal-700 hover:bg-gray-100 font-bold py-1.5 px-6 rounded shadow transition-colors text-sm">
                            {{ form.processing ? 'Kaydediliyor...' : 'Kaydet ve Yayınla' }}
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

                <div v-if="leftPaletteTab === 'temel'" class="flex-1 overflow-y-auto pb-4 custom-scrollbar">
                    <div class="p-4">
                        <div class="relative">
                            <svg class="w-4 h-4 absolute left-3 top-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            <input type="text" placeholder="Ara..." class="w-full bg-[#334155] border-none rounded text-sm text-white pl-9 pr-3 py-2 focus:ring-1 focus:ring-teal-500 placeholder-gray-400">
                        </div>
                    </div>
                    <div 
                        v-for="nt in nodeTypes" 
                        :key="nt.label"
                        class="px-4 py-3 border-b border-[#334155] hover:bg-[#334155] cursor-grab flex items-center group transition-colors"
                        draggable="true"
                        @dragstart="onDragStart($event, nt)"
                    >
                        <div class="w-7 h-7 rounded flex items-center justify-center mr-3 text-white shadow-sm" :style="{ backgroundColor: nt.bgColor }">
                            <span class="text-xs font-bold">{{ nt.label.charAt(0) }}</span>
                        </div>
                        <span class="font-medium flex-1 text-sm text-gray-300 group-hover:text-white transition-colors">{{ nt.label }}</span>
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
                    <template #node-task="props">
                        <TaskNode :id="props.id" :data="props.data" @openSettings="openSettings" @duplicate="duplicateNode" @remove="removeNode" />
                    </template>
                    <template #node-io="props">
                        <IONode :id="props.id" :data="props.data" @openSettings="openSettings" @duplicate="duplicateNode" @remove="removeNode" />
                    </template>
                    <template #node-decision="props">
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
                    <span class="font-bold text-lg text-white tracking-wide">{{ selectedNodeForSettings ? selectedNodeForSettings.label || 'Düğüm Ayarları' : 'Bağlantı Ayarları' }}</span>
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
                            <p v-else class="text-[10px] text-red-400 mt-2 font-semibold">Kuralları yazabilmek için önce akışı kaydedip yayınlamalısınız.</p>
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
                        <div v-if="selectedNodeForSettings.data.taskType === 'form'" class="pt-2 border-t border-[#334155]">
                            <label class="block text-xs font-semibold text-gray-400 mb-1 uppercase tracking-wider mt-2">Bağlı Form</label>
                            <p class="text-[10px] text-gray-500 mb-2">Bu adımda doldurulacak formu seçin.</p>
                            <select v-model="selectedNodeForSettings.data.subFormId" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2 mb-3">
                                <option value="">-- Form Seçin --</option>
                                <option v-for="f in forms" :key="f.id" :value="f.id">{{ f.name }}</option>
                            </select>
                            <label class="block text-xs font-semibold text-gray-400 mb-1 uppercase tracking-wider">Atanan Kişi</label>
                            <select v-model="selectedNodeForSettings.data.assignType" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2">
                                <option value="starter">Süreci Başlatan Kişi</option>
                                <option value="user">Belirli Bir Kullanıcı</option>
                                <option value="role">Belirli Bir Rol</option>
                            </select>

                            <div v-if="selectedNodeForSettings.data.assignType === 'user'" class="mt-3">
                                <label class="block text-xs font-semibold text-gray-400 mb-1 uppercase tracking-wider">Kullanıcı</label>
                                <select v-model="selectedNodeForSettings.data.assignValue" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2">
                                    <option value="">-- Kullanıcı Seçin --</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                                </select>
                            </div>
                            <div v-else-if="selectedNodeForSettings.data.assignType === 'role'" class="mt-3">
                                <label class="block text-xs font-semibold text-gray-400 mb-1 uppercase tracking-wider">Rol</label>
                                <select v-model="selectedNodeForSettings.data.assignValue" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2">
                                    <option value="">-- Rol Seçin --</option>
                                    <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                                </select>
                            </div>
                            <div v-if="selectedNodeForSettings.data.taskType === 'end'" class="pt-4 border-t border-[#334155]">
                            <label class="block text-xs font-semibold text-red-400 mb-2 uppercase tracking-wider">Süreç Sonuç Durumu</label>
                            <p class="text-[10px] text-gray-400 mb-3">Süreç bu düğüme ulaşıp sonlandığında veritabanına hangi durumla (status) kaydedileceğini seçin.</p>
                            <select v-model="selectedNodeForSettings.data.processStatus" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-red-500 px-3 py-2">
                                <option value="completed">Başarıyla Tamamlandı (Olumlu)</option>
                                <option value="rejected">Reddedildi / İptal Edildi (Olumsuz)</option>
                            </select>
                        </div>
                        </div>

                        <!-- ========== ONAY / İNCELEME AYARLARI ========== -->
                        <div v-if="['approval', 'review'].includes(selectedNodeForSettings.data.taskType)">
                            <div class="pt-2 border-t border-[#334155]">
                                <label class="block text-xs font-semibold text-gray-400 mb-1 uppercase tracking-wider mt-2">Onaylayan / Atanan Kişi</label>
                                <p class="text-[10px] text-gray-500 mb-2">Bu adımı kimin işlemesi gerektiğini belirleyin.</p>
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
                                <div v-else-if="selectedNodeForSettings.data.assignType === 'department'">
                                    <select v-model="selectedNodeForSettings.data.assignValue" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2">
                                        <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                                    </select>
                                </div>
                                <div v-else-if="selectedNodeForSettings.data.assignType === 'directorate'">
                                    <select v-model="selectedNodeForSettings.data.assignValue" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2">
                                        <option v-for="dir in directorates" :key="dir.id" :value="dir.id">{{ dir.name }}</option>
                                    </select>
                                </div>
                                <div v-else-if="selectedNodeForSettings.data.assignType === 'user'">
                                    <select v-model="selectedNodeForSettings.data.assignValue" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2">
                                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                                    </select>
                                </div>
                                <div v-else-if="selectedNodeForSettings.data.assignType === 'role'">
                                    <select v-model="selectedNodeForSettings.data.assignValue" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2">
                                        <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-[#334155]">
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
                        </div>

                        <div v-if="selectedNodeForSettings.data.taskType === 'form'" class="pt-3 border-t border-[#334155]">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="selectedNodeForSettings.data.rejectEnabled"
                                    type="checkbox"
                                    class="rounded border-gray-500 bg-[#334155] text-red-500 focus:ring-red-500"
                                />
                                <span class="text-sm text-gray-200">Reddetme seçeneği</span>
                            </label>
                            <p class="text-[10px] text-gray-500 mt-1">
                                Aktifken Red çıkışını bağladığınız düğüme geri dönülebilir.
                            </p>
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
                                <input v-model="selectedNodeForSettings.data.notifySubject" type="text" placeholder="Örn: SAP EWM Süreci Tamamlandı" class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2">
                            </div>

                            <!-- Mesaj (Body) -->
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 mb-1 uppercase tracking-widest">Mesaj İçeriği</label>
                                <textarea v-model="selectedNodeForSettings.data.notifyMessage" rows="4" placeholder="Kullanıcıya gidecek mesajı buraya yazın..." class="w-full bg-[#334155] border-none rounded text-sm text-white focus:ring-1 focus:ring-teal-500 px-3 py-2 custom-scrollbar"></textarea>
                            </div>
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
    </AuthenticatedLayout>
</template>

<style>
/* Vue flow stillerinin düzgün görünmesi için konteynere boyut verilmeli */
.vue-flow__wrapper {
  width: 100%;
  height: 100%;
}
</style>
