<script setup>
import BaseNode from './BaseNode.vue';
import { computed } from 'vue';

const props = defineProps({
    id: String,
    data: Object,
});

const emit = defineEmits(['openSettings', 'duplicate', 'remove']);

const isRejected = computed(() => {
    const status = props.data?.processStatus || '';
    const label = String(props.data?.label || props.data?.customName || '').toLowerCase();
    return status === 'rejected' || label.includes('red') || label.includes('iptal');
});

const nodeColor = computed(() => {
    if (props.data?.color) {
        return props.data.color;
    }
    return isRejected.value ? '#ef4444' : '#10b981';
});

const successIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
</svg>`;

const rejectIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
</svg>`;

const icon = computed(() => isRejected.value ? rejectIcon : successIcon);
</script>

<template>
    <BaseNode 
        :id="id" 
        :data="data" 
        :color="nodeColor" 
        :icon="icon" 
        @openSettings="$emit('openSettings', id)"
        @duplicate="$emit('duplicate', id)"
        @remove="$emit('remove', id)"
    >
        <!-- Bitiş düğümünden dışarıya ok çıkmasın diye source handle gizlenir -->
        <template #source-handles>
            <span></span>
        </template>
    </BaseNode>
</template>
