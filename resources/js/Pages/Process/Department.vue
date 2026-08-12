<script setup>
import { ref, nextTick } from 'vue';
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

defineProps({
    instances: Array
});

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
        case 'running':
        case 'waiting': return 'bg-blue-100 text-blue-800';
        case 'completed': return 'bg-green-100 text-green-800';
        case 'rejected': return 'bg-red-100 text-red-800';
        case 'cancelled': return 'bg-gray-100 text-gray-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getStatusName = (status) => {
    switch (status) {
        case 'running': return 'Çalışıyor';
        case 'waiting': return 'Onay Bekliyor';
        case 'completed': return 'Tamamlandı';
        case 'rejected': return 'Reddedildi';
        case 'cancelled': return 'İptal Edildi';
        default: return status;
    }
};
</script>

<template>
    <Head :title="`${departmentName} Süreçleri`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ departmentName }} Süreçleri
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <div v-if="instances.length === 0" class="text-center py-8 text-gray-500">
                            Bölümünüzden başlatılmış herhangi bir süreç bulunmuyor.
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Süreç No</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Akış Tipi / Başlatan</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Durum</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Beklenen Onay</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Başlangıç Tarihi</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="instance in instances" :key="instance.id" class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                            #{{ instance.id }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-bold text-gray-900">{{ instance.workflow.name }}</div>
                                            <div class="text-xs text-gray-500 mt-1 flex items-center">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                                {{ instance.starter?.name || 'Bilinmiyor' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="['px-2 inline-flex text-xs leading-5 font-semibold rounded-full', getStatusBadge(instance.status)]">
                                                {{ getStatusName(instance.status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div v-if="instance.status === 'waiting' && instance.tasks && instance.tasks.length > 0">
                                                <div v-for="task in instance.tasks" :key="task.id" class="text-sm">
                                                    <span v-if="task.assigned_user" class="font-medium text-indigo-600 flex items-center">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                                        {{ task.assigned_user.name }}
                                                    </span>
                                                    <span v-else-if="task.assigned_role" class="font-medium text-purple-600 flex items-center">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                                        {{ task.assigned_role }} (Rol)
                                                    </span>
                                                    <span v-else class="text-gray-500 italic">Sistem/Departman Bekleniyor</span>
                                                </div>
                                            </div>
                                            <span v-else class="text-xs text-gray-400">-</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ new Date(instance.created_at).toLocaleString() }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                            <button @click="openPreview(instance.workflow)" class="inline-flex items-center text-teal-600 hover:text-teal-900 bg-teal-50 hover:bg-teal-100 px-3 py-1.5 rounded-md transition-colors">
                                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                Akışı Görüntüle
                                            </button>
                                            <Link :href="route('processes.tracker', instance.id)" class="inline-flex items-center text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-md transition-colors font-bold">
                                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                                                Canlı Takip
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
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
