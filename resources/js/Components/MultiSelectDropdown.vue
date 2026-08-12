<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => []
    },
    options: {
        type: Array,
        required: true
    },
    placeholder: {
        type: String,
        default: 'Seçim yapın...'
    }
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const dropdownRef = ref(null);

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
};

const closeDropdown = (e) => {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', closeDropdown);
});

onUnmounted(() => {
    document.removeEventListener('click', closeDropdown);
});

const toggleOption = (optionId) => {
    const newValue = [...props.modelValue];
    const index = newValue.indexOf(optionId);
    
    if (index === -1) {
        newValue.push(optionId);
    } else {
        newValue.splice(index, 1);
    }
    
    emit('update:modelValue', newValue);
};

const removeOption = (optionId) => {
    const newValue = [...props.modelValue];
    const index = newValue.indexOf(optionId);
    if (index !== -1) {
        newValue.splice(index, 1);
        emit('update:modelValue', newValue);
    }
};

const selectedOptionsObjects = computed(() => {
    return props.modelValue.map(id => {
        const option = props.options.find(opt => opt.id === id || opt.id === parseInt(id));
        return option ? option : { id, name: id };
    });
});
</script>

<template>
    <div class="relative" ref="dropdownRef">
        <div 
            @click="toggleDropdown"
            class="min-h-[38px] w-full bg-white border border-gray-300 rounded-md shadow-sm flex flex-wrap items-center gap-1 p-1 cursor-pointer focus-within:ring-1 focus-within:ring-indigo-500 focus-within:border-indigo-500"
            :class="{'ring-1 ring-indigo-500 border-indigo-500': isOpen}"
        >
            <div v-if="selectedOptionsObjects.length === 0" class="text-gray-500 text-sm px-2 py-1">
                {{ placeholder }}
            </div>
            
            <div 
                v-for="opt in selectedOptionsObjects" 
                :key="opt.id"
                class="bg-indigo-100 text-indigo-800 text-xs font-medium px-2 py-1 rounded-full flex items-center gap-1"
                @click.stop
            >
                <span>{{ opt.name }}</span>
                <button 
                    @click.stop="removeOption(opt.id)"
                    class="text-indigo-500 hover:text-indigo-900 focus:outline-none rounded-full p-0.5 hover:bg-indigo-200 transition-colors"
                >
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <div class="ml-auto text-gray-400 px-2">
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </div>
        </div>

        <div v-if="isOpen" class="absolute z-50 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
            <div 
                v-for="option in options" 
                :key="option.id"
                @click="toggleOption(option.id)"
                class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-indigo-50 transition-colors flex items-center"
                :class="{'bg-indigo-50': modelValue.includes(option.id) || modelValue.includes(option.id.toString())}"
            >
                <div class="flex items-center h-5">
                    <input 
                        type="checkbox" 
                        :checked="modelValue.includes(option.id) || modelValue.includes(option.id.toString())"
                        class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 cursor-pointer pointer-events-none"
                    >
                </div>
                <div class="ml-3">
                    <span class="block truncate" :class="{'font-semibold text-indigo-700': modelValue.includes(option.id), 'font-normal text-gray-900': !modelValue.includes(option.id)}">
                        {{ option.name }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
