<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { VueFlow } from '@vue-flow/core';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';

import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';
import '@vue-flow/controls/dist/style.css';

import StartNode from '@/Components/WorkflowNodes/StartNode.vue';
import EndNode from '@/Components/WorkflowNodes/EndNode.vue';
import TaskNode from '@/Components/WorkflowNodes/TaskNode.vue';
import DecisionNode from '@/Components/WorkflowNodes/DecisionNode.vue';
import IONode from '@/Components/WorkflowNodes/IONode.vue';
import StorageNode from '@/Components/WorkflowNodes/StorageNode.vue';
import DocumentNode from '@/Components/WorkflowNodes/DocumentNode.vue';

const props = defineProps({
    workflows: {
        type: Array,
        required: true,
    },
    showProcessTracking: {
        type: Boolean,
        default: false,
    },
});

const activeTab = ref('ongoing');

const ongoingWorkflows = computed(() =>
    props.workflows.filter((w) => !['completed', 'cancelled'].includes(w.runtime_status))
);

const completedWorkflows = computed(() =>
    props.workflows.filter((w) => ['completed', 'cancelled'].includes(w.runtime_status))
);

const displayedWorkflows = computed(() =>
    activeTab.value === 'ongoing' ? ongoingWorkflows.value : completedWorkflows.value
);

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

const formatAssignees = (tracking) => {
    if (!tracking?.assignees?.length) {
        if (tracking?.is_cancelled) {
            return 'Süreç iptal edildi';
        }
        return tracking?.is_completed ? 'Süreç tamamlandı' : 'Bekleyen atama yok';
    }

    const names = tracking.assignees.map((assignee) =>
        assignee.type === 'role' ? `${assignee.name} (rol)` : assignee.name
    );

    if (names.length <= 2) {
        return names.join(', ');
    }

    return `${names.slice(0, 2).join(', ')} +${names.length - 2}`;
};

const fullAssignees = (tracking) => {
    if (!tracking?.assignees?.length) {
        return '';
    }

    return tracking.assignees
        .map((assignee) => (assignee.type === 'role' ? `${assignee.name} (rol)` : assignee.name))
        .join(', ');
};
</script>

<template>
    <Head title="Süreç Akışları" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-gray-900 leading-tight tracking-tight">Süreç Akışları</h2>
                    <p class="text-sm text-gray-500 mt-1">Sistemdeki onay ve iş akış rotalarını yönetin.</p>
                </div>
                <Link
                    :href="route('workflows.create')"
                    class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200"
                >
                    <svg class="w-5 h-5 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Yeni Akış Çiz
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-[84rem] mx-auto sm:px-6 lg:px-8">
                <!-- Sekmeler -->
                <div class="mb-4 border-b border-gray-200">
                    <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" role="tablist">
                        <li class="mr-2" role="presentation">
                            <button
                                type="button"
                                @click="activeTab = 'ongoing'"
                                :class="[
                                    'inline-block p-4 border-b-2 rounded-t-lg transition-colors',
                                    activeTab === 'ongoing' ? 'border-indigo-600 text-indigo-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300'
                                ]"
                            >
                                Devam Eden Akışlar
                                <span class="ml-2 bg-indigo-100 text-indigo-800 py-0.5 px-2 rounded-full text-xs">{{ ongoingWorkflows.length }}</span>
                            </button>
                        </li>
                        <li class="mr-2" role="presentation">
                            <button
                                type="button"
                                @click="activeTab = 'completed'"
                                :class="[
                                    'inline-block p-4 border-b-2 rounded-t-lg transition-colors',
                                    activeTab === 'completed' ? 'border-indigo-600 text-indigo-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300'
                                ]"
                            >
                                Tamamlanan Akışlar
                                <span class="ml-2 bg-gray-100 text-gray-800 py-0.5 px-2 rounded-full text-xs">{{ completedWorkflows.length }}</span>
                            </button>
                        </li>
                    </ul>
                </div>

                <!-- İçerik Kartı -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
                    <div v-if="workflows.length === 0" class="p-12 flex flex-col items-center justify-center text-center">
                        <div class="w-24 h-24 mb-6 bg-indigo-50 rounded-full flex items-center justify-center">
                            <svg class="w-12 h-12 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-1">Henüz Süreç Akışı Yok</h3>
                        <p class="text-gray-500 mb-6 max-w-sm">Sisteminizde tanımlı hiçbir süreç rotası bulunmuyor. Yeni bir akış çizerek başlayabilirsiniz.</p>
                        <Link :href="route('workflows.create')" class="text-indigo-600 font-semibold hover:text-indigo-700">
                            Hemen Oluştur &rarr;
                        </Link>
                    </div>

                    <div v-else-if="displayedWorkflows.length === 0" class="p-12 text-center text-gray-500">
                        <template v-if="activeTab === 'ongoing'">
                            Devam eden bir süreç akışı bulunmuyor.
                        </template>
                        <template v-else>
                            Henüz tamamlanmış bir süreç akışı bulunmuyor.
                        </template>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full table-fixed divide-y divide-gray-100">
                            <thead class="bg-gray-50/50">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Akış Adı</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Bağlı Form / Kategori</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Durum</th>
                                    <th v-if="showProcessTracking" scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Mevcut Adım / Sıra</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-36">Devam Süresi</th>
                                    <th scope="col" class="px-4 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider w-72 min-w-[18rem]">İşlem</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100" :class="{ 'opacity-80': activeTab === 'completed' }">
                                <tr v-for="w in displayedWorkflows" :key="w.id" class="hover:bg-gray-50/50 transition-colors duration-150 group">
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-400 font-medium">#{{ w.id }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap max-w-[180px]">
                                        <div class="text-sm font-bold text-gray-900 truncate" :title="w.name">{{ w.name }}</div>
                                        <div class="text-xs text-gray-400 mt-0.5">{{ w.nodes?.length || 0 }} Adım</div>
                                    </td>
                                    <td class="px-6 py-3 max-w-[200px]">
                                        <div class="text-sm text-gray-700 font-medium flex items-center min-w-0">
                                            <svg class="w-4 h-4 mr-1.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                            <span class="truncate" :title="w.form_template ? w.form_template.name : 'Form Bağlanmamış'">
                                                {{ w.form_template ? w.form_template.name : 'Form Bağlanmamış' }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-400 mt-0.5 ml-5.5 truncate" :title="w.category || 'Kategorisiz'">{{ w.category || 'Kategorisiz' }}</div>
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap">
                                        <span v-if="w.status === 'draft'" class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-yellow-50 text-yellow-700 border border-yellow-200">
                                            <span class="w-1.5 h-1.5 mr-1.5 bg-yellow-500 rounded-full"></span>
                                            Taslak
                                        </span>
                                        <span v-else-if="w.status === 'archived'" class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-gray-50 text-gray-600 border border-gray-200">
                                            Arşivli
                                        </span>
                                        <span v-else-if="w.runtime_status === 'completed'" class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-green-50 text-green-700 border border-green-200">
                                            <span class="w-1.5 h-1.5 mr-1.5 bg-green-500 rounded-full"></span>
                                            Tamamlanmış
                                        </span>
                                        <span v-else-if="w.runtime_status === 'cancelled'" class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-red-50 text-red-700 border border-red-200">
                                            <span class="w-1.5 h-1.5 mr-1.5 bg-red-500 rounded-full"></span>
                                            İptal Edildi
                                        </span>
                                        <span v-else-if="w.runtime_status === 'in_progress'" class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                                            <span class="w-1.5 h-1.5 mr-1.5 bg-blue-500 rounded-full animate-pulse"></span>
                                            Devam Ediyor
                                        </span>
                                        <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-green-50 text-green-700 border border-green-200">
                                            <span class="w-1.5 h-1.5 mr-1.5 bg-green-500 rounded-full"></span>
                                            Aktif
                                        </span>
                                        <div v-if="w.latest_instance_id" class="text-[10px] text-gray-400 mt-1">
                                            Süreç #{{ w.latest_instance_id }}
                                        </div>
                                    </td>
                                    <td v-if="showProcessTracking" class="px-6 py-3 max-w-[220px]">
                                        <template v-if="w.process_tracking">
                                            <div
                                                class="text-sm font-semibold text-gray-900 flex items-center gap-1.5 min-w-0"
                                                :title="w.process_tracking.current_node_label"
                                            >
                                                <span
                                                    v-if="!w.process_tracking.is_completed"
                                                    class="inline-block h-2 w-2 shrink-0 rounded-full bg-emerald-500 animate-pulse"
                                                />
                                                <span class="truncate">{{ w.process_tracking.current_node_label }}</span>
                                            </div>
                                            <div
                                                class="text-xs text-gray-600 mt-0.5 truncate"
                                                :title="fullAssignees(w.process_tracking) || formatAssignees(w.process_tracking)"
                                            >
                                                <span class="font-medium text-gray-500">Sırada:</span>
                                                {{ formatAssignees(w.process_tracking) }}
                                                <span
                                                    v-if="w.process_tracking.pending_task_count > 1"
                                                    class="text-gray-400"
                                                >
                                                    · {{ w.process_tracking.pending_task_count }} görev
                                                </span>
                                            </div>
                                        </template>
                                        <span v-else class="text-xs text-gray-400">—</span>
                                    </td>
                                    <td class="px-6 py-3 align-middle w-36">
                                        <template v-if="w.duration">
                                            <div
                                                class="text-xs font-medium mb-1.5"
                                                :class="w.duration.is_cancelled ? 'text-red-600' : w.duration.is_completed ? 'text-slate-600' : 'text-gray-700'"
                                            >
                                                {{ w.duration.label }}
                                            </div>
                                            <div class="w-full max-w-[140px] h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                                <div
                                                    class="h-full rounded-full transition-all duration-300"
                                                    :class="w.duration.is_cancelled ? 'bg-red-500' : w.duration.is_completed ? 'bg-slate-500' : w.duration.progress >= 70 ? 'bg-amber-500' : 'bg-indigo-500'"
                                                    :style="{ width: `${w.duration.progress}%` }"
                                                />
                                            </div>
                                        </template>
                                        <span v-else class="text-xs text-gray-400">—</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium w-72 min-w-[18rem]">
                                        <div class="inline-flex items-center justify-end gap-1.5">
                                        <Link
                                            v-if="showProcessTracking && w.latest_instance_id"
                                            :href="route('processes.tracker', w.latest_instance_id)"
                                            class="inline-flex items-center text-emerald-600 hover:text-emerald-900 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-md transition-colors"
                                        >
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                                            Takip
                                        </Link>
                                        <button type="button" @click="openPreview(w)" class="inline-flex items-center text-teal-600 hover:text-teal-900 bg-teal-50 hover:bg-teal-100 px-3 py-1.5 rounded-md transition-colors">
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                            Görüntüle
                                        </button>
                                        <Link :href="route('workflows.edit', w.id)" class="inline-flex items-center text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-md transition-colors">
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                            Düzenle
                                        </Link>
                                        </div>
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
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl h-[85vh] flex flex-col overflow-hidden border border-gray-100">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/80">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Akış Önizlemesi</h3>
                        <p class="text-xs text-gray-500 mt-1">{{ selectedWorkflow?.name }}</p>
                    </div>
                    <button @click="isPreviewOpen = false" class="text-gray-400 hover:text-red-500 bg-white hover:bg-red-50 rounded-lg p-2 transition-colors border border-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <!-- VueFlow Canvas -->
                <div class="flex-1 relative bg-[#f8fafc]">
                    <VueFlow :nodes="previewNodes" :edges="previewEdges" :nodes-draggable="false" :nodes-connectable="false" :zoom-on-scroll="true" :pan-on-drag="true" :default-viewport="{ zoom: 0.8 }">
                        <Background pattern-color="#cbd5e1" gap="20" />
                        <Controls class="bg-white rounded-md shadow border border-gray-200 fill-gray-600" />
                        
                        <!-- Template renderers for custom nodes -->
                        <template #node-start="props">
                            <StartNode :id="props.id" :data="props.data" />
                        </template>
                        <template #node-end="props">
                            <EndNode :id="props.id" :data="props.data" />
                        </template>
                        <template #node-task="props">
                            <TaskNode :id="props.id" :data="props.data" />
                        </template>
                        <template #node-io="props">
                            <IONode :id="props.id" :data="props.data" />
                        </template>
                        <template #node-decision="props">
                            <DecisionNode :id="props.id" :data="props.data" />
                        </template>
                        <template #node-storage="props">
                            <StorageNode :id="props.id" :data="props.data" />
                        </template>
                        <template #node-document="props">
                            <DocumentNode :id="props.id" :data="props.data" />
                        </template>
                    </VueFlow>
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
