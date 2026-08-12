<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
    alert: {
        type: Boolean,
        default: false,
    },
});

const classes = computed(() => {
    if (props.alert) {
        return props.active
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-red-500 text-sm font-semibold leading-5 text-red-600 focus:outline-none focus:border-red-700 transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-semibold leading-5 text-red-600 hover:text-red-700 hover:border-red-300 focus:outline-none focus:text-red-700 focus:border-red-300 transition duration-150 ease-in-out';
    }

    return props.active
        ? 'inline-flex items-center px-1 pt-1 border-b-2 border-indigo-400 text-sm font-medium leading-5 text-gray-900 focus:outline-none focus:border-indigo-700 transition duration-150 ease-in-out'
        : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out';
});
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
        <span
            v-if="alert"
            class="ml-1.5 inline-block h-2 w-2 shrink-0 rounded-full bg-red-500"
            aria-hidden="true"
        />
    </Link>
</template>
