<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue'; // computed EKLENDİ
import draggable from 'vuedraggable';

const props = defineProps({
    template: Object,
    categories: Array,
    app_logo: String,
    available_bindings: { // YENİ EKLENDİ
        type: Array,
        default: () => ({})
    }
});

const form = useForm({
    name: props.template?.name || '',
    description: props.template?.description || '',
    schema: props.template?.schema ? JSON.parse(JSON.stringify(props.template.schema)).map(field => {
        if (Array.isArray(field.options)) {
            field.options = field.options.join(', ');
        }
        return field;
    }) : [],
    category_id: props.template?.category_id || '',
    document_no: props.template?.document_no || '',
    publish_date: props.template?.publish_date ? props.template.publish_date.split('T')[0] : '',
    revision_no: props.template?.revision_no || '0',
    revision_date: props.template?.revision_date ? props.template.revision_date.split('T')[0] : new Date().toISOString().split('T')[0],
    page_no: props.template?.page_no || 1,
    logo_width: props.template?.logo_width || 4.5,
    logo_height: props.template?.logo_height || 1.5,
});

const availableTools = ref([
    { type: 'text', label: 'Kısa Metin', icon: '📝' },
    { type: 'textarea', label: 'Uzun Metin', icon: '📄' },
    { type: 'number', label: 'Sayı', icon: '🔢' },
    { type: 'select', label: 'Açılır Liste', icon: '🔽' },
    { type: 'radio', label: 'Tekli Seçim (Radio)', icon: '🔘' },
    { type: 'date', label: 'Tarih', icon: '📅' },
    { type: 'file', label: 'Dosya Yükleme', icon: '📎' },
    { type: 'checkbox', label: 'Onay Kutusu', icon: '☑️' },
    { type: 'header', label: 'Başlık / Açıklama', icon: '🏷️' },
]);

const selectedField = ref(null);

const cloneDog = (tool) => {
    return {
        id: 'field_' + Date.now() + Math.floor(Math.random() * 1000), // Benzersiz ID garantisi
        type: tool.type,
        label: 'Yeni ' + tool.label,
        required: false,
        placeholder: '',
        width: '12', // Grid sistemi için varsayılan tam genişlik (col-span-12)
        options: ['select', 'radio'].includes(tool.type) ? "Seçenek 1, Seçenek 2, Seçenek 3" : "",
        description: '' // Header veya ek açıklama alanları için
    };
};

const selectField = (field) => {
    selectedField.value = field;
};

const removeField = (index) => {
    if(selectedField.value && selectedField.value.id === form.schema[index].id) {
        selectedField.value = null;
    }
    form.schema.splice(index, 1);
};
// Veri bağlantısı yapıldığında ekranda etiketini göstermek için yardımcı metot
const getBindingLabel = (val) => {
    if (!props.available_bindings) return val;
    const binding = props.available_bindings.find(b => b.value === val);
    return binding ? binding.label : val;
};
const saveForm = () => {
    if (props.template) {
        form.put(route('form-templates.update', props.template.id));
    } else {
        form.post(route('form-templates.store'));
    }
};
const hasAutoOptions = computed(() => {
    if (!selectedField.value || !selectedField.value.data_bind) return false;
    const binding = props.available_bindings.find(b => b.value === selectedField.value.data_bind);
    return binding && binding.options && binding.options.length > 0;
});

// Veri bağlantısı değiştiğinde tetiklenir, options alanını otomatik doldurur
const handleDataBindChange = () => {
    if (!selectedField.value || !selectedField.value.data_bind) return;
    
    const binding = props.available_bindings.find(b => b.value === selectedField.value.data_bind);
    
    if (binding && binding.options) {
        if (Array.isArray(binding.options)) {
            selectedField.value.options = binding.options.join(', ');
        } else if (typeof binding.options === 'string') {
            selectedField.value.options = binding.options;
        }
    }
};
</script>

<template>
    <Head :title="template ? 'Form Düzenle' : 'Yeni Form Tasarla'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ template ? 'Form Düzenle' : 'Yeni Form Tasarla' }}
                </h2>
                <div class="flex space-x-3 items-center">
                    <select v-model="form.category_id" class="rounded border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">Kategori Seçin</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                    <input v-model="form.name" type="text" placeholder="Form Adı" class="rounded border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm w-64">
                    <button @click="saveForm" :disabled="form.processing" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded shadow disabled:opacity-50 transition-colors">
                        {{ form.processing ? 'Kaydediliyor...' : 'Kaydet' }}
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 flex h-[calc(100vh-130px)] max-w-[1600px] mx-auto px-4">
            
            <!-- SOL PANEL: Araçlar -->
            <div class="w-64 bg-white shadow-sm sm:rounded-lg flex flex-col h-full overflow-hidden mr-4">
                <div class="p-4 bg-indigo-50 border-b font-bold text-indigo-900">Form Elemanları</div>
                <div class="p-4 flex-1 overflow-y-auto">
                    <p class="text-xs text-gray-500 mb-4">Aşağıdaki araçları fare ile tutup ortadaki tasarım alanına sürükleyin.</p>
                    
                    <draggable
                        v-model="availableTools"
                        :group="{ name: 'formElements', pull: 'clone', put: false }"
                        :clone="cloneDog"
                        :sort="false"
                        item-key="type"
                        class="space-y-3"
                    >
                        <template #item="{ element }">
                            <div class="p-3 bg-white border border-gray-200 rounded-md shadow-sm cursor-grab hover:border-indigo-400 hover:bg-indigo-50 flex items-center space-x-3 transition-colors">
                                <span class="text-xl">{{ element.icon }}</span>
                                <span class="text-sm font-medium text-gray-700">{{ element.label }}</span>
                            </div>
                        </template>
                    </draggable>
                </div>
            </div>

            <!-- ORTA PANEL: Canvas (Tasarım Alanı) -->
            <div class="flex-1 bg-gray-100 flex flex-col h-full overflow-hidden rounded-lg shadow-inner border border-gray-200 relative">
                <div class="p-4 font-bold text-gray-700 text-center bg-white border-b shadow-sm z-10">
                    Tasarım Alanı (Sürükle Bırak)
                </div>
                
                <div class="flex-1 p-6 overflow-y-auto bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCI+PGNpcmNsZSBjeD0iMiIgY3k9IjIiIHI9IjIiIGZpbGw9IiNlNWU3ZWIiLz48L3N2Zz4=')]">
                    <div class="bg-white p-8 rounded-xl shadow-md min-h-full max-w-4xl mx-auto border border-gray-100">
                        <!-- KÖKSAN Form Başlığı Tablosu -->
                        <div class="mb-8 border-2 border-black w-full flex flex-col md:flex-row bg-white relative group" @click="selectField(null)">
                            <!-- Seçili Değilken Hover Efekti için gizli bir overlay (ayarlara yönlendirmek için) -->
                            <div class="absolute inset-0 border-2 border-transparent group-hover:border-indigo-300 pointer-events-none transition-colors"></div>

                            <!-- Logo Alanı -->
                            <div class="border-b-2 border-black md:border-b-0 md:border-r-2 border-black p-2 flex items-center justify-center min-w-[200px]">
                                <img v-if="app_logo" :src="app_logo" alt="App Logo" :style="{ width: form.logo_width + 'cm', height: form.logo_height + 'cm', objectFit: 'contain' }">
                                <div v-else class="text-xs text-gray-400 border border-dashed border-gray-300 p-4 flex items-center justify-center text-center" :style="{ width: form.logo_width + 'cm', height: form.logo_height + 'cm' }">
                                    Logo Yüklenmemiş<br>(Sistem Ayarları)
                                </div>
                            </div>
                            
                            <!-- Başlık Alanı -->
                            <div class="flex-1 flex flex-col items-center justify-center border-b-2 border-black md:border-b-0 md:border-r-2 border-black p-4">
                                <input v-model="form.name" type="text" placeholder="Form Başlığı (Otomatik Eşitlenir)" class="text-lg md:text-xl font-bold text-center border-none w-full focus:ring-0 focus:outline-none p-0 text-black uppercase">
                                <div class="w-3/4 h-[2px] bg-blue-600 mt-1"></div>
                                <input v-model="form.description" type="text" placeholder="Alt Açıklama (Opsiyonel)" class="mt-2 text-center border-none text-sm text-gray-500 w-full focus:ring-0 focus:outline-none p-0">
                            </div>
                            
                            <!-- Bilgi Alanı -->
                            <div class="w-full md:w-64 flex flex-col text-xs font-semibold">
                                <div class="flex border-b border-black">
                                    <div class="w-1/2 p-1.5 border-r border-black">Doküman No</div>
                                    <div class="w-1/2 p-0"><input v-model="form.document_no" type="text" class="w-full h-full border-none p-1.5 text-xs focus:ring-0" placeholder="FR-OPX-02"></div>
                                </div>
                                <div class="flex border-b border-black">
                                    <div class="w-1/2 p-1.5 border-r border-black">Yayın Tarihi</div>
                                    <div class="w-1/2 p-0"><input v-model="form.publish_date" type="date" class="w-full h-full border-none p-1.5 text-xs focus:ring-0"></div>
                                </div>
                                <div class="flex border-b border-black">
                                    <div class="w-1/2 p-1.5 border-r border-black">Rev. No / Tarih</div>
                                    <div class="w-1/2 p-0 flex">
                                        <input v-model="form.revision_no" type="text" class="w-1/3 border-none p-1.5 text-xs focus:ring-0 text-center" placeholder="0">
                                        <span class="py-1.5">/</span>
                                        <input v-model="form.revision_date" type="date" class="w-2/3 border-none p-1.5 text-xs focus:ring-0">
                                    </div>
                                </div>
                                <div class="flex">
                                    <div class="w-1/2 p-1.5 border-r border-black">Sayfa No</div>
                                    <div class="w-1/2 p-0"><input v-model="form.page_no" type="number" class="w-full h-full border-none p-1.5 text-xs focus:ring-0" placeholder="1"></div>
                                </div>
                            </div>
                        </div>
                        
                        <draggable
                            v-model="form.schema"
                            group="formElements"
                            item-key="id"
                            class="min-h-[400px] grid grid-cols-12 gap-4 p-4 rounded-lg border-2 border-dashed border-indigo-200 bg-indigo-50/30"
                            ghost-class="form-ghost"
                            animation="200"
                        >
                            <template #item="{ element, index }">
                                <div 
                                    class="p-5 border-2 rounded-lg relative group cursor-pointer transition-all bg-white shadow-sm"
                                    :class="[
                                        `col-span-${element.width || '12'}`,
                                        selectedField?.id === element.id ? 'border-indigo-500 ring-2 ring-indigo-200 shadow-md transform scale-[1.01]' : 'border-gray-200 hover:border-indigo-300'
                                    ]"
                                    @click="selectField(element)"
                                >
                                    <!-- Silme Butonu -->
                                    <div class="absolute -top-3 -right-3">
                                        <button @click.stop="removeField(index)" class="bg-red-100 text-red-600 hover:bg-red-500 hover:text-white rounded-full p-2 opacity-0 group-hover:opacity-100 transition-all shadow-sm">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </div>
                                    
                                    <!-- Başlık Tipi Render -->
                                    <div v-if="element.type === 'header'" class="prose max-w-none">
                                        <h2 class="text-xl font-bold text-gray-800 border-b pb-2 mb-2">{{ element.label }}</h2>
                                        <p class="text-sm text-gray-500">{{ element.description || 'Açıklama metni buraya gelecek.' }}</p>
                                    </div>

                                    <!-- Standart Input Render -->
                                    <div v-else>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                                            {{ element.label }} 
                                            <span v-if="element.required" class="text-red-500 ml-1" title="Zorunlu Alan">*</span>
                                        </label>
                                        
                                        <input v-if="['text', 'number', 'date'].includes(element.type)" :type="element.type" disabled 
                                            class="block w-full rounded-md shadow-sm sm:text-sm cursor-not-allowed transition-colors"
                                            :class="element.data_bind ? 'border-indigo-300 bg-indigo-50 text-indigo-600 font-medium placeholder-indigo-400' : 'border-gray-300 bg-gray-50 text-gray-500'" 
                                            :placeholder="element.data_bind ? '⚡ [Otomatik: ' + getBindingLabel(element.data_bind) + ']' : (element.placeholder || 'Kullanıcı veri girişi yapacak...')">
                                        
                                        <textarea v-if="element.type === 'textarea'" disabled 
                                            class="block w-full rounded-md shadow-sm sm:text-sm cursor-not-allowed transition-colors" rows="3"
                                            :class="element.data_bind ? 'border-indigo-300 bg-indigo-50 text-indigo-600 font-medium placeholder-indigo-400' : 'border-gray-300 bg-gray-50 text-gray-500'" 
                                            :placeholder="element.data_bind ? '⚡ [Otomatik: ' + getBindingLabel(element.data_bind) + ']' : (element.placeholder || 'Kullanıcı veri girişi yapacak...')"></textarea>
                                        
                                        <select v-if="element.type === 'select'" disabled 
                                            class="block w-full rounded-md shadow-sm sm:text-sm cursor-not-allowed transition-colors"
                                            :class="element.data_bind ? 'border-indigo-300 bg-indigo-50 text-indigo-600 font-medium' : 'border-gray-300 bg-gray-50 text-gray-500'">
                                            <option v-if="element.data_bind">⚡ [Otomatik Liste: {{ getBindingLabel(element.data_bind) }}]</option>
                                            <option v-else v-for="(opt, i) in (element.options ? element.options.split(',') : ['Seçenek 1'])" :key="i">
                                                {{ opt.trim() }}
                                            </option>
                                        </select>

                                        <select v-if="element.type === 'select'" disabled 
                                            class="block w-full rounded-md shadow-sm sm:text-sm cursor-not-allowed transition-colors"
                                            :class="element.data_bind ? 'border-indigo-300 bg-indigo-50 text-indigo-600 font-medium' : 'border-gray-300 bg-gray-50 text-gray-500'">
                                            <option v-for="(opt, i) in (element.options ? element.options.split(',') : ['Seçenek 1'])" :key="i">
                                                {{ element.data_bind && i === 0 ? '⚡ [Oto] ' : '' }}{{ opt.trim() }}
                                            </option>
                                        </select>

                                        <div v-if="element.type === 'radio'" class="space-y-2">
                                            <div v-if="element.data_bind" class="text-xs font-semibold text-indigo-500 mb-2 flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                                Otomatik Seçim: {{ getBindingLabel(element.data_bind) }}
                                            </div>
                                            
                                            <div v-for="(opt, i) in (element.options ? element.options.split(',') : ['Seçenek 1'])" :key="i" class="flex items-center">
                                                <input type="radio" disabled class="h-4 w-4 cursor-not-allowed"
                                                    :class="element.data_bind ? 'border-indigo-300 text-indigo-600 bg-indigo-100' : 'border-gray-300 text-indigo-600'">
                                                <label class="ml-2 block text-sm"
                                                    :class="element.data_bind ? 'text-indigo-700 font-medium' : 'text-gray-700'">
                                                    {{ opt.trim() }}
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div v-if="element.type === 'file'" class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center bg-gray-50 cursor-not-allowed">
                                            <span class="text-2xl mb-2 block">📎</span>
                                            <span class="text-sm text-gray-500">Dosya yükleme alanı</span>
                                        </div>
                                        
                                        <div v-if="element.type === 'checkbox'" class="flex items-center mt-2">
                                            <input type="checkbox" disabled :checked="!!element.data_bind" 
                                                class="h-5 w-5 rounded focus:ring-indigo-500 cursor-not-allowed"
                                                :class="element.data_bind ? 'border-indigo-300 text-indigo-600' : 'border-gray-300 text-indigo-600'">
                                            <label class="ml-2 block text-sm" :class="element.data_bind ? 'text-indigo-600 font-medium' : 'text-gray-700 opacity-70'">
                                                {{ element.data_bind ? '⚡ [Otomatik Onay: ' + getBindingLabel(element.data_bind) + ']' : 'Onay metni / Seçenek' }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </draggable>

                        <div v-if="form.schema.length === 0" class="text-center text-gray-400 mt-10 pointer-events-none absolute inset-0 flex items-center justify-center">
                            <div class="bg-white p-6 rounded-xl shadow-sm border border-dashed border-gray-300">
                                <span class="text-4xl block mb-2">📥</span>
                                Sol panelden form araçlarını buraya sürükleyin
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SAĞ PANEL: Özellikler -->
            <div class="w-80 bg-white shadow-sm sm:rounded-lg flex flex-col h-full overflow-hidden ml-4 border-l-4 border-indigo-500">
                <div class="p-4 bg-gray-50 border-b font-bold text-gray-700 flex justify-between items-center">
                    <span>Eleman Özellikleri</span>
                    <span v-if="selectedField" class="bg-indigo-100 text-indigo-800 text-xs px-2 py-1 rounded-full uppercase tracking-wider">{{ selectedField.type }}</span>
                </div>
                
                <div class="p-5 flex-1 overflow-y-auto">
                    <div v-if="selectedField" class="space-y-6">
                        
                        <!-- Genel Ayarlar -->
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-100 space-y-4">
                            <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Görünüm Ayarları</h4>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Alan Etiketi (Label)</label>
                                <input v-model="selectedField.label" type="text" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>

                            <div v-if="selectedField.type === 'header'">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Açıklama Metni</label>
                                <textarea v-model="selectedField.description" rows="3" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                            </div>

                            <div v-if="['text', 'textarea', 'number'].includes(selectedField.type)">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Yer Tutucu (Placeholder)</label>
                                <input v-model="selectedField.placeholder" type="text" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Örn: TC Kimlik Numaranız...">
                            </div>
                        </div>

                        <!-- Seçenek Ayarları (Select, Radio) -->
                        <div v-if="['select', 'radio'].includes(selectedField.type)" class="bg-blue-50 p-4 rounded-lg border border-blue-100 space-y-4">
                            <h4 class="text-xs font-bold text-blue-800 uppercase tracking-wider mb-2">Seçenekler (Options)</h4>
                            <div>
                                <label class="block text-xs text-blue-600 mb-2">Seçenekleri virgül (,) ile ayırarak yazın.</label>
                                <textarea v-model="selectedField.options" rows="4" 
                                    class="block w-full rounded-md border-blue-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm disabled:opacity-60 disabled:bg-gray-200 disabled:cursor-not-allowed transition-all" 
                                    placeholder="Elma, Armut, Çilek..."
                                    :disabled="hasAutoOptions"
                                ></textarea>
                                
                                <p v-if="hasAutoOptions" class="text-xs text-amber-600 mt-2 font-bold flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Bu seçenekler sistem şemasından otomatik çekilmektedir.
                                </p>
                            </div>
                            <div class="text-xs text-blue-500">
                                <strong>Önizleme:</strong> {{ selectedField.options ? selectedField.options.split(',').length : 0 }} seçenek algılandı.
                            </div>
                        </div>

                        <!-- Grid (Yerleşim) Ayarları -->
                        <div class="bg-purple-50 p-4 rounded-lg border border-purple-100 space-y-4">
                            <h4 class="text-xs font-bold text-purple-800 uppercase tracking-wider mb-2">Yerleşim (Grid)</h4>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Eleman Genişliği</label>
                                <select v-model="selectedField.width" class="block w-full rounded-md border-purple-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                    <option value="12">Tam Genişlik (%100)</option>
                                    <option value="6">Yarı Yarıya (%50)</option>
                                    <option value="4">Üçte Bir (%33)</option>
                                    <option value="3">Çeyrek (%25)</option>
                                </select>
                                <p class="text-xs text-purple-600 mt-2">Dar genişlik seçerek form elemanlarını yan yana dizebilirsiniz.</p>
                            </div>
                        </div>

                        <!-- Kurallar -->
                        <div v-if="selectedField.type !== 'header'" class="bg-red-50 p-4 rounded-lg border border-red-100 space-y-4">
                            <h4 class="text-xs font-bold text-red-800 uppercase tracking-wider mb-2">Doğrulama (Validation)</h4>
                            <div class="flex items-center">
                                <input :id="'req_'+selectedField.id" v-model="selectedField.required" type="checkbox" class="h-5 w-5 rounded border-red-300 text-red-600 focus:ring-red-500">
                                <label :for="'req_'+selectedField.id" class="ml-3 block text-sm font-bold text-red-900 cursor-pointer">Bu alan doldurulması ZORUNLU olsun mu?</label>
                            </div>
                        </div>
                        <div v-if="selectedField.type !== 'header'" class="bg-teal-50 p-4 rounded-lg border border-teal-100 space-y-4 mt-6">
                            <h4 class="text-xs font-bold text-teal-800 uppercase tracking-wider mb-2">Veri Bağlama (Data Binding)</h4>
                            
                            <div>
                                <label class="block text-sm font-medium text-teal-700 mb-1">Otomatik Veri Çek</label>
                                <select v-model="selectedField.data_bind" @change="handleDataBindChange" class="block w-full rounded-md border-teal-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 sm:text-sm bg-white">
                                    <option value="">Bağlantı Yok (Manuel Giriş)</option>
                                    <option v-for="binding in available_bindings" :key="binding.value" :value="binding.value">
                                        {{ binding.label }}
                                    </option>
                                </select>
                                <p class="text-xs text-teal-600 mt-2">Bu alan, formu açan kişinin (veya hedef aracın) sicilindeki bilgilerle otomatik doldurulur.</p>
                            </div>

                            <div v-if="selectedField.data_bind" class="flex items-center pt-2">
                                <input :id="'ro_'+selectedField.id" v-model="selectedField.is_readonly" type="checkbox" class="h-5 w-5 rounded border-teal-300 text-teal-600 focus:ring-teal-500">
                                <label :for="'ro_'+selectedField.id" class="ml-3 block text-sm font-bold text-teal-900 cursor-pointer">Kullanıcı değiştiremesin (Salt Okunur)</label>
                            </div>
                        </div>
                        <!-- Geliştirici Ayarları -->
                        <div class="pt-4 border-t border-gray-200">
                            <label class="block text-xs font-bold text-gray-400 mb-1">Sistem Kimliği (ID)</label>
                            <input v-model="selectedField.id" type="text" class="block w-full rounded-md border-gray-200 bg-gray-50 text-gray-400 shadow-sm focus:border-gray-300 focus:ring-0 sm:text-xs">
                        </div>

                    </div>
                    
                    <div v-else class="space-y-6">
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-100 space-y-4">
                            <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Doküman Ayarları</h4>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Logo Genişliği (cm)</label>
                                <input v-model="form.logo_width" type="number" step="0.1" min="1" max="10" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Logo Yüksekliği (cm)</label>
                                <input v-model="form.logo_height" type="number" step="0.1" min="1" max="10" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            
                            <p class="text-xs text-gray-500 mt-2">Logoyu Köksan standartlarında ayarlayabilirsiniz (Örn: En 4.5cm, Boy 1.5cm). Form elemanlarını seçerek alan özelliklerini görebilirsiniz.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Tailwind Grid sınıflarının dinamik üretildiğinde (col-span-12 vb.) silinmemesi için safelist veya bu şekilde css gerekebilir.
Vite/Tailwind jit kullandığı için dinamik sınıflar algılanmayabilir. 
Eğer çalışmazsa, width değerini style="grid-column: span X" olarak verebiliriz.
Burada geçici olarak en yaygın kullanılan col-span sınıflarını force'luyoruz. */
.col-span-12 { grid-column: span 12 / span 12; }
.col-span-6 { grid-column: span 6 / span 6; }
.col-span-4 { grid-column: span 4 / span 4; }
.col-span-3 { grid-column: span 3 / span 3; }

.form-ghost {
    opacity: 0.5;
    background-color: white;
    border: 2px solid #6366f1;
    border-radius: 0.5rem;
}
</style>
