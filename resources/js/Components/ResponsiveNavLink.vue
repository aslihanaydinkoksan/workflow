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
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-red-500 text-start text-base font-semibold text-red-700 bg-red-50 focus:outline-none focus:text-red-800 focus:bg-red-100 focus:border-red-700 transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-semibold text-red-600 hover:text-red-700 hover:bg-red-50 hover:border-red-300 focus:outline-none focus:text-red-700 focus:bg-red-50 focus:border-red-300 transition duration-150 ease-in-out';
    }

    return props.active
        ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-indigo-400 text-start text-base font-medium text-indigo-700 bg-indigo-50 focus:outline-none focus:text-indigo-800 focus:bg-indigo-100 focus:border-indigo-700 transition duration-150 ease-in-out'
        : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:text-gray-800 focus:bg-gray-50 focus:border-gray-300 transition duration-150 ease-in-out';
});
</script>

<template>
    <Link :href="href" :class="classes">
        <span class="inline-flex items-center">
            <slot />
            <span
                v-if="alert"
                class="ml-2 inline-block h-2 w-2 shrink-0 rounded-full bg-red-500"
                aria-hidden="true"
            />
        </span>
    </Link>
</template>
