<template>
  <div class="relative w-full" ref="dropdownRef">
    <div 
      class="w-full flex items-center flex-wrap gap-1 min-h-[38px] p-1.5 rounded-xl border border-gray-300 shadow-sm bg-white focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500 cursor-text"
      @click="focusInput"
    >
      <!-- Selected Chips (Multiple Mode) -->
      <template v-if="multiple">
        <span 
          v-for="opt in selectedOptions" 
          :key="opt[valueKey]"
          class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-md text-xs font-semibold border border-indigo-100"
        >
          {{ opt[labelKey] }}
          <button type="button" @click.stop="removeOption(opt)" class="hover:bg-indigo-200 rounded-full p-0.5 text-indigo-500 hover:text-indigo-700 transition-colors">
            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </span>
      </template>

      <!-- Input Field -->
      <input
        ref="inputRef"
        type="text"
        v-model="searchQuery"
        @focus="isOpen = true"
        @input="isOpen = true"
        @keydown.delete="handleDelete"
        :placeholder="showPlaceholder ? placeholder : ''"
        class="flex-grow min-w-[50px] border-none bg-transparent p-0 m-0 text-sm focus:ring-0"
        :disabled="disabled"
      />
      
      <!-- Clear Button (Single Mode) -->
      <div v-if="!multiple && selectedOption && !disabled" class="flex-shrink-0 flex items-center px-2">
        <button type="button" @click.stop="clearSelection" class="text-gray-400 hover:text-gray-600 focus:outline-none">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Arrow Icon -->
      <div v-if="(!selectedOption || multiple) && !disabled" class="flex-shrink-0 flex items-center px-2 pointer-events-none">
        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </div>
    </div>

    <!-- Dropdown Menu -->
    <div v-if="isOpen" class="absolute z-50 w-full mt-1 bg-white rounded-md shadow-lg max-h-60 overflow-auto border border-gray-200">
      <ul class="py-1 text-sm text-gray-700">
        <li 
          v-for="option in filteredOptions" 
          :key="option[valueKey]"
          @click="selectOption(option)"
          class="px-4 py-2 hover:bg-indigo-50 cursor-pointer flex justify-between items-center"
          :class="{'bg-indigo-50 font-semibold text-indigo-700': isSelected(option)}"
        >
          <div class="flex items-center gap-2">
            <div v-if="multiple" class="flex-shrink-0">
              <svg v-if="isSelected(option)" class="h-4 w-4 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
              <div v-else class="h-4 w-4 border border-gray-300 rounded-sm"></div>
            </div>
            <span>{{ option[labelKey] }}</span>
          </div>
          <span v-if="option.email" class="text-xs text-gray-400">{{ option.email }}</span>
        </li>
        <li v-if="filteredOptions.length === 0" class="px-4 py-2 text-gray-500 text-sm text-center">
          Sonuç bulunamadı
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  modelValue: {
    type: [String, Number, Array, null],
    default: null
  },
  multiple: {
    type: Boolean,
    default: false
  },
  options: {
    type: Array,
    required: true,
    default: () => []
  },
  labelKey: {
    type: String,
    default: 'name'
  },
  valueKey: {
    type: String,
    default: 'id'
  },
  placeholder: {
    type: String,
    default: 'Seçiniz...'
  },
  disabled: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const searchQuery = ref('');
const dropdownRef = ref(null);
const inputRef = ref(null);

const focusInput = () => {
  if (!props.disabled && inputRef.value) {
    inputRef.value.focus();
    isOpen.value = true;
  }
};

const showPlaceholder = computed(() => {
  if (props.multiple) {
    return (!props.modelValue || props.modelValue.length === 0) && !searchQuery.value;
  }
  return !searchQuery.value && !selectedOption.value;
});

// Single Mode logic
const selectedOption = computed(() => {
  if (props.multiple) return null;
  return props.options.find(opt => opt[props.valueKey] === props.modelValue) || null;
});

// Multiple Mode logic
const selectedOptions = computed(() => {
  if (!props.multiple) return [];
  if (!Array.isArray(props.modelValue)) return [];
  return props.options.filter(opt => props.modelValue.includes(opt[props.valueKey]));
});

const isSelected = (option) => {
  if (props.multiple) {
    return Array.isArray(props.modelValue) && props.modelValue.includes(option[props.valueKey]);
  }
  return props.modelValue === option[props.valueKey];
};

const filteredOptions = computed(() => {
  if (!searchQuery.value) {
    return props.options;
  }
  if (!props.multiple && selectedOption.value && searchQuery.value === selectedOption.value[props.labelKey]) {
    return props.options;
  }
  
  const query = searchQuery.value.toLowerCase();
  return props.options.filter(opt => {
    const labelMatch = String(opt[props.labelKey]).toLowerCase().includes(query);
    const emailMatch = opt.email ? String(opt.email).toLowerCase().includes(query) : false;
    const tcMatch = opt.tc_no ? String(opt.tc_no).includes(query) : false;
    return labelMatch || emailMatch || tcMatch;
  });
});

watch(() => props.modelValue, (newVal) => {
  if (!props.multiple) {
    if (newVal) {
      const opt = props.options.find(o => o[props.valueKey] === newVal);
      searchQuery.value = opt ? opt[props.labelKey] : '';
    } else {
      searchQuery.value = '';
    }
  } else {
    // In multiple mode, input is cleared when options change externally
    if (!isOpen.value) {
      searchQuery.value = '';
    }
  }
}, { immediate: true });

const selectOption = (option) => {
  if (props.multiple) {
    let currentValues = Array.isArray(props.modelValue) ? [...props.modelValue] : [];
    const val = option[props.valueKey];
    
    if (currentValues.includes(val)) {
      currentValues = currentValues.filter(v => v !== val);
    } else {
      currentValues.push(val);
    }
    
    searchQuery.value = '';
    emit('update:modelValue', currentValues);
    emit('change', currentValues);
    // Keep dropdown open for multiple selection
    if(inputRef.value) inputRef.value.focus();
  } else {
    searchQuery.value = option[props.labelKey];
    emit('update:modelValue', option[props.valueKey]);
    emit('change', option);
    isOpen.value = false;
  }
};

const removeOption = (option) => {
  if (!props.multiple) return;
  const val = option[props.valueKey];
  let currentValues = Array.isArray(props.modelValue) ? [...props.modelValue] : [];
  currentValues = currentValues.filter(v => v !== val);
  emit('update:modelValue', currentValues);
  emit('change', currentValues);
};

const clearSelection = () => {
  if (props.multiple) {
    emit('update:modelValue', []);
    emit('change', []);
  } else {
    searchQuery.value = '';
    emit('update:modelValue', null);
    emit('change', null);
  }
  isOpen.value = false;
};

const handleDelete = (e) => {
  if (props.multiple && searchQuery.value === '' && Array.isArray(props.modelValue) && props.modelValue.length > 0) {
    // Remove last item if backspace is pressed on empty input
    const currentValues = [...props.modelValue];
    currentValues.pop();
    emit('update:modelValue', currentValues);
    emit('change', currentValues);
  }
};

const closeDropdown = (e) => {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
    isOpen.value = false;
    if (props.multiple) {
      searchQuery.value = '';
    } else {
      if (selectedOption.value) {
        searchQuery.value = selectedOption.value[props.labelKey];
      } else {
        searchQuery.value = '';
      }
    }
  }
};

onMounted(() => {
  document.addEventListener('click', closeDropdown);
});

onUnmounted(() => {
  document.removeEventListener('click', closeDropdown);
});
</script>
