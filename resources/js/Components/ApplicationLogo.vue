<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const logoSrc = computed(() => {
    let src = page.props.app_logo;
    const isSubfolder = typeof window !== 'undefined' && window.location.pathname.includes('/workflow_koksan');
    if (src) {
        if (isSubfolder && src === '/logo.jpg') {
            return '/workflow_koksan/logo.jpg';
        }
        return src;
    }
    return isSubfolder ? '/workflow_koksan/logo.jpg' : '/logo.jpg';
});

const onImageError = (e) => {
    if (e.target.src.includes('/workflow_koksan/logo.jpg')) {
        e.target.src = '/logo.jpg';
    } else if (e.target.src.includes('/logo.jpg')) {
        e.target.src = '/workflow_koksan/logo.jpg';
    } else {
        e.target.style.display = 'none';
    }
};
</script>

<template>
    <img :src="logoSrc" alt="Köksan Logo" @error="onImageError" />
</template>
