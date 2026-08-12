<script setup>
import BaseNode from './BaseNode.vue';
import { Handle, Position } from '@vue-flow/core';


const props = defineProps({
    id: String,
    data: Object,
});

const emit = defineEmits(['openSettings', 'duplicate', 'remove']);

const icon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-1.14.76a2 2 0 01-2.22 0l-1.14-.76" /></svg>`;
</script>
<template>
    <BaseNode :id="id" :data="data" :color="data.taskType === 'notify' ? '#06b6d4' : '#3b82f6'" :icon="icon" @openSettings="$emit('openSettings', id)" @duplicate="$emit('duplicate', id)" @remove="$emit('remove', id)">
        <template v-if="data.taskType === 'form' && data.rejectEnabled" #source-handles>
            <Handle id="approved" type="source" :position="Position.Bottom" class="w-3 h-3 border-2 border-white rounded-full bg-green-500" style="left: 25%;" title="Gönder" />
            <div class="absolute -bottom-5 left-[25%] -translate-x-1/2 text-[9px] font-bold text-green-600">Gönder</div>

            <Handle id="rejected" type="source" :position="Position.Bottom" class="w-3 h-3 border-2 border-white rounded-full bg-red-500" style="left: 75%;" title="Reddet" />
            <div class="absolute -bottom-5 left-[75%] -translate-x-1/2 text-[9px] font-bold text-red-600">Red</div>
        </template>
    </BaseNode>
</template>
