<script setup>
import { defineProps, defineEmits, ref, watch } from 'vue';
// html2pdf importunu SİLDİK!

const props = defineProps({
    elements: {
        type: Array,
        required: true,
    },
    modelValue: {
        type: Object,
        default: () => ({}),
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    template: {
        type: Object,
        default: null,
    },
    appLogo: {
        type: String,
        default: null,
    },
    // YENİ: Dışa aktarım yapabilmemiz için Hangi Görevde olduğumuzu bilmemiz lazım
    taskId: {
        type: [Number, String],
        default: null,
    }
});

const emit = defineEmits(['update:modelValue']);
const formData = ref({ ...props.modelValue });

watch(() => props.modelValue, (newVal) => {
    if (props.disabled) {
        formData.value = { ...(newVal || {}) };
    }
}, { deep: true, immediate: true });

watch(formData, (newVal) => {
    emit('update:modelValue', newVal);
}, { deep: true });

const getOptions = (optionsStr) => {
    if (!optionsStr) return [];
    if (Array.isArray(optionsStr)) return optionsStr;
    if (typeof optionsStr === 'string') return optionsStr.split(',').map(o => o.trim());
    return [];
};

// YENİ: ZIGGY İLE DOĞRUDAN BACKEND'E YÖNLENDİRME
const exportToPDF = () => {
    if (!props.taskId) {
        alert("Görev kimliği bulunamadı.");
        return;
    }
    window.open(route('tasks.export.pdf', props.taskId), '_blank');
};

const exportToExcel = () => {
    if (!props.taskId) {
        alert("Görev kimliği bulunamadı.");
        return;
    }
    // Excel dosyaları yeni sekme açmadan doğrudan indirme tetikler
    window.location.href = route('tasks.export.excel', props.taskId);
};
</script>

<template>
    <div class="form-renderer-wrapper w-full">
        <!-- İndirme Butonları (Sadece template varsa) -->
        <div v-if="template && taskId" class="flex justify-end space-x-3 mb-4 print:hidden">
            <button @click="exportToExcel" type="button"
                class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md shadow-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                    </path>
                </svg>
                Excel İndir
            </button>
            <button @click="exportToPDF" type="button"
                class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md shadow-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                    </path>
                </svg>
                PDF İndir
            </button>
        </div>

        <!-- A4 Container (veya normal container) -->
        <div ref="a4Document"
            :class="template ? 'bg-white shadow-xl mx-auto p-8 border border-gray-200 print:shadow-none print:border-none w-full' : ''"
            :style="template ? 'min-height: 29.7cm;' : ''">

            <!-- Köksan Doküman Başlığı (Sadece template varsa) -->
            <div v-if="template && taskId" class="mb-8 border-2 border-black w-full flex flex-col md:flex-row">
                <!-- Logo Alanı -->
                <div
                    class="border-b-2 border-black md:border-b-0 md:border-r-2 border-black p-2 flex items-center justify-center min-w-[200px]">
                    <img v-if="appLogo" :src="appLogo" alt="App Logo"
                        :style="{ width: (template.logo_width || 4.5) + 'cm', height: (template.logo_height || 1.5) + 'cm', objectFit: 'contain' }">
                    <div v-else
                        class="text-xs text-gray-400 border border-dashed border-gray-300 p-4 flex items-center justify-center text-center"
                        :style="{ width: (template.logo_width || 4.5) + 'cm', height: (template.logo_height || 1.5) + 'cm' }">
                        Logo Yok
                    </div>
                </div>

                <!-- Başlık Alanı -->
                <div
                    class="flex-1 flex flex-col items-center justify-center border-b-2 border-black md:border-b-0 md:border-r-2 border-black p-4 text-center">
                    <h1 class="text-lg md:text-xl font-bold text-black uppercase m-0">{{ template.name }}</h1>
                    <div class="w-3/4 h-[2px] bg-blue-600 mt-1 mb-1"></div>
                    <span v-if="template.description" class="text-sm text-gray-500">{{ template.description }}</span>
                </div>

                <!-- Bilgi Alanı -->
                <div class="w-full md:w-64 flex flex-col text-xs font-semibold">
                    <div class="flex border-b border-black">
                        <div class="w-1/2 p-1.5 border-r border-black">Doküman No</div>
                        <div class="w-1/2 p-1.5">{{ template.document_no || '-' }}</div>
                    </div>
                    <div class="flex border-b border-black">
                        <div class="w-1/2 p-1.5 border-r border-black">Yayın Tarihi</div>
                        <div class="w-1/2 p-1.5">{{ template.publish_date ? template.publish_date.split('T')[0] : '-' }}
                        </div>
                    </div>
                    <div class="flex border-b border-black">
                        <div class="w-1/2 p-1.5 border-r border-black">Rev. No / Tarih</div>
                        <div class="w-1/2 p-1.5">{{ template.revision_no || '0' }} / {{ template.revision_date ?
                            template.revision_date.split('T')[0] : '-' }}</div>
                    </div>
                    <div class="flex">
                        <div class="w-1/2 p-1.5 border-r border-black">Sayfa No</div>
                        <div class="w-1/2 p-1.5">{{ template.page_no || '1' }}</div>
                    </div>
                </div>
            </div>

            <!-- Form İçeriği -->
            <div class="grid grid-cols-12 gap-6">
                <div v-for="element in elements" :key="element.id" :class="`col-span-${element.width || '12'} w-full`">

                    <!-- Başlık Tipi Render -->
                    <div v-if="element.type === 'header'" class="prose max-w-none mt-2 mb-2">
                        <h2 class="text-xl font-bold text-gray-800 border-b pb-2 mb-2">{{ element.label }}</h2>
                        <p v-if="element.description" class="text-sm text-gray-500">{{ element.description }}</p>
                    </div>

                    <!-- Diğer Input Tipleri -->
                    <div v-else class="relative">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                            {{ element.label }} <span v-if="element.required" class="text-red-500 ml-1">*</span>
                        </label>

                        <input v-if="element.type === 'text'" type="text" v-model="formData[element.id]"
                            :required="element.required"
                            :disabled="disabled || !!element.data_bind || element.is_readonly"
                            :placeholder="element.placeholder"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100 disabled:text-gray-600 disabled:cursor-not-allowed disabled:opacity-80 disabled:border-gray-200 transition-colors" />

                        <textarea v-else-if="element.type === 'textarea'" v-model="formData[element.id]"
                            :required="element.required"
                            :disabled="disabled || !!element.data_bind || element.is_readonly"
                            :placeholder="element.placeholder" rows="3"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100 disabled:text-gray-600 disabled:cursor-not-allowed disabled:opacity-80 disabled:border-gray-200 transition-colors"></textarea>

                        <input v-else-if="element.type === 'number'" type="number" v-model="formData[element.id]"
                            :required="element.required"
                            :disabled="disabled || !!element.data_bind || element.is_readonly"
                            :placeholder="element.placeholder"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100 disabled:text-gray-600 disabled:cursor-not-allowed disabled:opacity-80 disabled:border-gray-200 transition-colors" />

                        <input v-else-if="element.type === 'date'" type="date" v-model="formData[element.id]"
                            :required="element.required"
                            :disabled="disabled || !!element.data_bind || element.is_readonly"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100 disabled:text-gray-600 disabled:cursor-not-allowed disabled:opacity-80 disabled:border-gray-200 transition-colors" />

                        <select v-else-if="element.type === 'select'" v-model="formData[element.id]"
                            :required="element.required"
                            :disabled="disabled || !!element.data_bind || element.is_readonly"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100 disabled:text-gray-600 disabled:cursor-not-allowed disabled:opacity-80 disabled:border-gray-200 transition-colors">
                            <option value="" disabled>Seçiniz...</option>
                            <option v-for="(opt, i) in getOptions(element.options)" :key="i"
                                :value="opt.trim ? opt.trim() : opt">{{ opt.trim ? opt.trim() : opt }}</option>
                        </select>

                        <div v-else-if="element.type === 'radio'" class="space-y-2 mt-2">
                            <div v-for="(opt, i) in getOptions(element.options)" :key="i" class="flex items-center">
                                <input type="radio" :value="opt.trim ? opt.trim() : opt" v-model="formData[element.id]"
                                    :required="element.required"
                                    :disabled="disabled || !!element.data_bind || element.is_readonly"
                                    :name="element.id"
                                    class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-80 transition-colors" />
                                <label class="ml-2 block text-sm"
                                    :class="(disabled || !!element.data_bind || element.is_readonly) ? 'text-gray-500' : 'text-gray-700'">
                                    {{ opt.trim ? opt.trim() : opt }}
                                </label>
                            </div>
                        </div>

                        <div v-else-if="element.type === 'file'">
                            <input type="file" :required="element.required" :disabled="disabled || element.is_readonly"
                                class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 disabled:opacity-50 disabled:cursor-not-allowed transition-all" />
                        </div>

                        <div v-else-if="element.type === 'checkbox'" class="flex items-center mt-2">
                            <input type="checkbox" v-model="formData[element.id]" :required="element.required"
                                :disabled="disabled || !!element.data_bind || element.is_readonly"
                                class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-80 transition-colors" />
                            <span class="ml-2 text-sm"
                                :class="(disabled || !!element.data_bind || element.is_readonly) ? 'text-gray-500' : 'text-gray-700'">Evet/Onaylıyorum</span>
                        </div>

                        <p v-if="element.description && element.type !== 'header'" class="mt-1 text-xs text-gray-500">{{
                            element.description }}</p>

                        <p v-if="element.data_bind"
                            class="mt-1.5 text-[11px] text-indigo-600 font-semibold flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            Bu veri sistem profilinizden otomatik çekilmiştir.
                        </p>
                    </div>
                </div>

                <div v-if="elements.length === 0"
                    class="col-span-12 text-sm text-gray-500 italic p-6 bg-gray-50 rounded-lg border-2 border-dashed text-center">
                    Bu form için henüz bir alan tasarlanmamış.
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Tailwind Grid sınıflarının dinamik üretildiğinde (col-span-12 vb.) silinmemesi için */
.col-span-12 {
    grid-column: span 12 / span 12;
}

.col-span-6 {
    grid-column: span 6 / span 6;
}

.col-span-4 {
    grid-column: span 4 / span 4;
}

.col-span-3 {
    grid-column: span 3 / span 3;
}
</style>
