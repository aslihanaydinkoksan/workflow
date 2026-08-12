<script setup>
import BaseNode from './BaseNode.vue';
import { Handle, Position } from '@vue-flow/core';


const props = defineProps({
    id: String,
    data: Object,
});

const emit = defineEmits(['openSettings', 'duplicate', 'remove']);

const icon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>`;
</script>
<template>
    <BaseNode :id="id" :data="data" color="#f59e0b" :icon="icon" @openSettings="$emit('openSettings', id)" @duplicate="$emit('duplicate', id)" @remove="$emit('remove', id)">
        <template #source-handles>
            <!-- Onay Handle -->
            <Handle id="approved" type="source" :position="Position.Bottom" class="w-3 h-3 border-2 border-white rounded-full bg-green-500" style="left: 20%;" title="Onay" />
            <div class="absolute -bottom-5 left-[20%] -translate-x-1/2 text-[9px] font-bold text-green-600">Onay</div>
            
            <!-- Geri Dön / Revize Handle -->
            <Handle id="revised" type="source" :position="Position.Bottom" class="w-3 h-3 border-2 border-white rounded-full bg-yellow-500" style="left: 50%;" title="Geri Dön" />
            <div class="absolute -bottom-5 left-[50%] -translate-x-1/2 text-[9px] font-bold text-yellow-600 whitespace-nowrap">Geri Dön</div>

            <!-- Red Handle -->
            <Handle id="rejected" type="source" :position="Position.Bottom" class="w-3 h-3 border-2 border-white rounded-full bg-red-500" style="left: 80%;" title="Reddet" />
            <div class="absolute -bottom-5 left-[80%] -translate-x-1/2 text-[9px] font-bold text-red-600">Red</div>
        </template>
    </BaseNode>
</template>
