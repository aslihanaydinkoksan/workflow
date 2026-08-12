<script setup>
import BaseNode from './BaseNode.vue';


const props = defineProps({
    id: String,
    data: Object,
});

const emit = defineEmits(['openSettings', 'duplicate', 'remove']);

const icon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
</svg>`;
</script>

<template>
    <BaseNode 
        :id="id" 
        :data="data" 
        color="#ef4444" 
        :icon="icon" 
        @openSettings="$emit('openSettings', id)"
        @duplicate="$emit('duplicate', id)"
        @remove="$emit('remove', id)"
    />
</template>
