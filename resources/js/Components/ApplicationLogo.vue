<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const logoSrc = computed(() => {
    return page.props.app_logo || '/logo.jpg';
});

const onImageError = (e) => {
    if (e.target.src.endsWith('/workflow_koksan/logo.jpg')) {
        e.target.src = '/logo.jpg';
    } else if (e.target.src.endsWith('/logo.jpg')) {
        e.target.src = '/workflow_koksan/logo.jpg';
    } else {
        e.target.style.display = 'none';
    }
};
</script>

<template>
    <img :src="logoSrc" alt="Köksan Logo" @error="onImageError" />
</template>
