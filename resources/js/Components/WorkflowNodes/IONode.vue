<script setup>
import BaseNode from './BaseNode.vue';
import { Handle, Position } from '@vue-flow/core';
import { computed } from 'vue';

const props = defineProps({
    id: String,
    data: Object,
});

const emit = defineEmits(['openSettings', 'duplicate', 'remove']);

const nodeColor = computed(() => {
    if (props.data?.taskType === 'notify') return '#06b6d4';
    if (props.data?.taskType === 'follow_up') return '#0284c7';
    if (props.data?.taskType === 'sap_sync') return '#0369a1';
    return props.data?.color || '#3b82f6';
});

const icon = computed(() => {
    if (props.data?.taskType === 'follow_up') {
        return `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;
    }
    if (props.data?.taskType === 'sap_sync') {
        return `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>`;
    }
    if (props.data?.taskType === 'notify') {
        return `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>`;
    }
    return `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>`;
});
</script>
<template>
    <BaseNode :id="id" :data="data" :color="nodeColor" :icon="icon" @openSettings="$emit('openSettings', id)" @duplicate="$emit('duplicate', id)" @remove="$emit('remove', id)">
        <template v-if="data.taskType === 'form' && data.rejectEnabled" #source-handles>
            <Handle id="approved" type="source" :position="Position.Bottom" class="w-3 h-3 border-2 border-white rounded-full bg-green-500" style="left: 25%;" title="Gönder" />
            <div class="absolute -bottom-5 left-[25%] -translate-x-1/2 text-[9px] font-bold text-green-600">Gönder</div>

            <Handle id="rejected" type="source" :position="Position.Bottom" class="w-3 h-3 border-2 border-white rounded-full bg-red-500" style="left: 75%;" title="Reddet" />
            <div class="absolute -bottom-5 left-[75%] -translate-x-1/2 text-[9px] font-bold text-red-600">Red</div>
        </template>
    </BaseNode>
</template>
