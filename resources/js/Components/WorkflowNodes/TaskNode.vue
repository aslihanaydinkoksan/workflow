<script setup>
import BaseNode from './BaseNode.vue';
import { Handle, Position } from '@vue-flow/core';


const props = defineProps({
    id: String,
    data: Object,
});

const emit = defineEmits(['openSettings', 'duplicate', 'remove']);

const icon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>`;
</script>
<template>
    <BaseNode :id="id" :data="data" color="#8b5cf6" :icon="icon" @openSettings="$emit('openSettings', id)" @duplicate="$emit('duplicate', id)" @remove="$emit('remove', id)">
        <template v-if="data.rejectEnabled" #source-handles>
            <Handle id="approved" type="source" :position="Position.Bottom" class="w-3 h-3 border-2 border-white rounded-full bg-green-500" style="left: 25%;" title="Onay" />
            <div class="absolute -bottom-5 left-[25%] -translate-x-1/2 text-[9px] font-bold text-green-600">Onay</div>

            <Handle id="rejected" type="source" :position="Position.Bottom" class="w-3 h-3 border-2 border-white rounded-full bg-red-500" style="left: 75%;" title="Reddet" />
            <div class="absolute -bottom-5 left-[75%] -translate-x-1/2 text-[9px] font-bold text-red-600">Red</div>
        </template>
    </BaseNode>
</template>
