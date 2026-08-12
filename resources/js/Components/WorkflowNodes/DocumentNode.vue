<script setup>
import BaseNode from './BaseNode.vue';


const props = defineProps({
    id: String,
    data: Object,
});

const emit = defineEmits(['openSettings', 'duplicate', 'remove']);

const icon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>`;
</script>
<template>
    <BaseNode :id="id" :data="data" color="#6b7280" :icon="icon" @openSettings="$emit('openSettings', id)" @duplicate="$emit('duplicate', id)" @remove="$emit('remove', id)" />
</template>
