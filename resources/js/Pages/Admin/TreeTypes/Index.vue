<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

const props = defineProps({
    treeTypes: {
        type: Array,
        default: () => []
    }
});

const SCHEMA_FEATURES = {
    text: { requiresUnit: false, requiresOptions: false, label: "Kısa Metin" },
    textarea: { requiresUnit: false, requiresOptions: false, label: "Uzun Metin" },
    number: { requiresUnit: true, requiresOptions: false, label: "Sayısal Değer" },
    boolean: { requiresUnit: false, requiresOptions: false, label: "Evet/Hayır" },
    date: { requiresUnit: false, requiresOptions: false, label: "Tarih" },
    select: { requiresUnit: false, requiresOptions: true, label: "Açılır Liste (Tekli)" },
    multiselect: { requiresUnit: false, requiresOptions: true, label: "Çoklu Seçim" }
};

const form = ref({
    id: null,
    display_name: '',
    key: '',
    description: '',
    schema: []
});

const isProcessing = ref(false);

const resetForm = () => {
    form.value = { id: null, display_name: '', key: '', description: '', schema: [] };
};

const editTreeType = (type) => {
    form.value = {
        id: type.id,
        display_name: type.display_name,
        key: type.key,
        description: type.description || '',
        schema: Array.isArray(type.schema) ? type.schema.map(item => {
            // Eski veritabanı kayıtları (sadece 'field' içerenler) için geriye dönük uyumluluk köprüsü
            return {
                ...item,
                label: item.label || item.field || '',
                name: item.name || item.field || '',
                // Eğer seçenekler veritabanından dizi (Array) olarak geliyorsa, input'ta düzenlenebilmesi için string'e çevir
                options: Array.isArray(item.options) ? item.options.join(', ') : (item.options || ''),
                // Eski verileri açarken "otomatik slugify" çalışıp mevcut key'leri bozmasın diye manuel müdahale edilmiş sayıyoruz
                isNameManuallyEdited: true
            };
        }) : []
    };
};

// GÖREV 1: Dinamik satıra artık "label" ve "name" (key) olmak üzere iki ayrı değişken ekleniyor.
const addSchemaRow = () => {
    form.value.schema.push({ label: '', name: '', type: 'text', required: false, unit: '', options: '' });
};

const removeSchemaRow = (index) => {
    form.value.schema.splice(index, 1);
};

// GÖREV 2: Gerçek Zamanlı Türkçe Slugify Helper Fonksiyonu
const generateKeyFromLabel = (item) => {
    // Eğer kullanıcı daha önce key (name) alanına manuel müdahale etmediyse (sadece label dolduruyorsa)
    // veya key alanı boşsa otomatik doldur.
    if (!item.isNameManuallyEdited || item.name === '') {
        const turkishMap = {
            'ç': 'c', 'ğ': 'g', 'ı': 'i', 'ö': 'o', 'ş': 's', 'ü': 'u',
            'Ç': 'c', 'Ğ': 'g', 'İ': 'i', 'Ö': 'o', 'Ş': 's', 'Ü': 'u'
        };
        
        let slug = item.label;
        
        // Türkçe karakterleri harita ile dönüştür
        slug = slug.replace(/[çğıöşüÇĞİÖŞÜ]/g, match => turkishMap[match]);
        
        // Küçük harfe çevir, boşlukları ve tireleri alt çizgiye dönüştür, kalan geçersiz karakterleri sil
        item.name = slug.toLowerCase()
                        .replace(/[\s-]+/g, '_')
                        .replace(/[^a-z0-9_]/g, '');
    }
};

// Kullanıcı key alanına manuel müdahale ederse otomatik doldurmayı o satır için kapat
const markAsManuallyEdited = (item) => {
    item.isNameManuallyEdited = true;
};

// GÖREV 3: Form Gönderimindeki Payload (JSON Şema Güncellemesi)
const submitForm = async () => {
    isProcessing.value = true;

    const payload = {
        ...form.value,
        schema: form.value.schema.map(item => {
            // Şemayı API'nin beklediği { label, name, type, required } yapısına dönüştür
            const formatted = { 
                label: item.label.trim(),
                name: item.name.trim(), 
                type: item.type, 
                required: item.required 
            };
            
            if (SCHEMA_FEATURES[item.type].requiresUnit) formatted.unit = item.unit;
            
            if (SCHEMA_FEATURES[item.type].requiresOptions) {
                formatted.options = typeof item.options === 'string' 
                    ? item.options.split(',').map(s => s.trim()).filter(Boolean) 
                    : item.options;
            }
            return formatted;
        }).filter(item => item.label !== '' && item.name !== '') // Boş satırları yoksay
    };

    try {
        const url = payload.id ? `/admin/tree-types/${payload.id}` : '/admin/tree-types';
        const method = payload.id ? 'put' : 'post';
        
        await axios[method](url, payload);
        
        router.reload({ only: ['treeTypes'] });
        resetForm();
        alert('İşlem başarıyla kaydedildi.');
    } catch (error) {
        alert(error.response?.data?.message || 'Bir hata oluştu. Verileri kontrol edin.');
    } finally {
        isProcessing.value = false;
    }
};

const deleteTreeType = async (id) => {
    if (!confirm("Bu ağaç tipini silmek istediğinize emin misiniz? (Buna bağlı düğümler de silinebilir!)")) return;
    
    try {
        await axios.delete(`/admin/tree-types/${id}`);
        router.reload({ only: ['treeTypes'] });
        if (form.value.id === id) resetForm();
    } catch (error) {
        alert('Silme işlemi başarısız oldu.');
    }
};
</script>

<template>
    <Head title="Şema Tasarımcısı" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Şema Tasarımcısı (Tree Types)</h2>
                <button @click="resetForm" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">
                    + Yeni Ağaç Tipi Tasarla
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-12 gap-8">
                
                <!-- Sol Panel: Mevcut Ağaç Tipleri -->
                <div class="lg:col-span-4 flex flex-col gap-4 max-h-[80vh] overflow-y-auto">
                    <div v-if="treeTypes.length === 0" class="text-gray-500 text-sm p-4 bg-white rounded-lg shadow-sm border text-center">
                        Tanımlı ağaç tipi bulunamadı.
                    </div>
                    
                    <div v-for="type in treeTypes" :key="type.id" @click="editTreeType(type)"
                        class="bg-white p-5 rounded-xl shadow-sm cursor-pointer hover:shadow-md transition group relative border-l-4 border-gray-200"
                        :class="[type.is_active ? 'border-l-green-500' : 'border-l-red-500', form.id === type.id ? 'ring-2 ring-indigo-500' : '']">
                        <h4 class="text-lg font-bold text-gray-900">{{ type.display_name }}</h4>
                        <p class="text-xs text-gray-500 mt-1 uppercase font-mono">{{ type.key }}</p>
                        
                        <button @click.stop="deleteTreeType(type.id)" class="absolute top-4 right-4 text-red-400 opacity-0 group-hover:opacity-100 hover:text-red-600 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- Sağ Panel: Form -->
                <div class="lg:col-span-8 bg-white rounded-xl shadow-sm border border-gray-100 p-8">
                    <form @submit.prevent="submitForm">
                        <div class="grid grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Ağaç Tipi (Görünen Ad) <span class="text-red-500">*</span></label>
                                <input v-model="form.display_name" type="text" required class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 placeholder-gray-400" placeholder="Örn: CNC Makineleri">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Sistem Anahtarı (Key)</label>
                                <input v-model="form.key" type="text" placeholder="Boş bırakılırsa otomatik üretilir" class="w-full rounded-lg border-gray-300 bg-gray-50 focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm">
                            </div>
                        </div>

                        <div class="mb-8">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Açıklama</label>
                            <textarea v-model="form.description" rows="2" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        </div>

                        <div class="border-t border-gray-200 pt-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">Dinamik Şema (Metadata Fields)</h3>
                                <button type="button" @click="addSchemaRow" class="text-sm text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg font-medium transition shadow-sm">
                                    + Yeni Alan Ekle
                                </button>
                            </div>

                            <div class="space-y-4">
                                <div v-for="(item, index) in form.schema" :key="index" class="flex flex-col gap-3 bg-gray-50 p-5 rounded-xl border border-gray-200 shadow-sm transition-all hover:shadow-md">
                                    
                                    <!-- Çift Girdi (Label ve Key) Tasarımı -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <!-- Label Alanı (Görünen Ad) -->
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Görünecek Ad (Label)</label>
                                            <input v-model="item.label" @input="generateKeyFromLabel(item)" type="text" placeholder="Örn: Departman Görevi" required class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                        
                                        <!-- Key Alanı (Veritabanı Anahtarı) -->
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Veritabanı Anahtarı (Key)</label>
                                            <input v-model="item.name" @input="markAsManuallyEdited(item)" type="text" placeholder="Örn: departman_gorevi" required class="w-full rounded-md border-gray-300 bg-white text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                    </div>

                                    <!-- Tip ve Seçenekler -->
                                    <div class="flex flex-wrap md:flex-nowrap items-center gap-4 mt-1 border-t border-gray-200 pt-3">
                                        <div class="w-full md:w-56">
                                            <select v-model="item.type" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 font-medium text-gray-700">
                                                <option v-for="(feature, key) in SCHEMA_FEATURES" :key="key" :value="key">{{ feature.label }}</option>
                                            </select>
                                        </div>
                                        
                                        <div class="flex items-center gap-2 w-32 bg-white px-3 py-2 rounded-md border border-gray-200">
                                            <input v-model="item.required" type="checkbox" class="rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer">
                                            <label class="text-xs font-bold text-gray-600 cursor-pointer" @click="item.required = !item.required">Zorunlu Alan</label>
                                        </div>
                                        
                                        <div class="flex-1 flex justify-end">
                                            <button type="button" @click="removeSchemaRow(index)" class="text-red-500 hover:text-red-700 p-2 hover:bg-red-50 rounded transition" title="Bu alanı sil">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Opsiyonel Alanlar (Birim / Seçenek Listesi) -->
                                    <div v-if="SCHEMA_FEATURES[item.type]?.requiresUnit || SCHEMA_FEATURES[item.type]?.requiresOptions" class="flex gap-4 pl-4 border-l-2 border-indigo-300 mt-1">
                                        <div v-if="SCHEMA_FEATURES[item.type].requiresUnit" class="w-1/3">
                                            <input v-model="item.unit" type="text" placeholder="Birim (Örn: kg, adet, TL)" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                        <div v-if="SCHEMA_FEATURES[item.type].requiresOptions" class="flex-1">
                                            <input v-model="item.options" type="text" placeholder="Açılır liste seçeneklerini virgülle ayırarak yazın (Örn: Aktif, Pasif, Beklemede)" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <span class="text-[10px] text-gray-500 mt-1 block italic">Seçeneklerin arasına virgül koymayı unutmayın.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-4 mt-8 pt-6 border-t border-gray-200">
                            <button type="submit" :disabled="isProcessing" class="px-6 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium transition shadow-md disabled:opacity-50">
                                {{ isProcessing ? 'Kaydediliyor...' : (form.id ? 'Değişiklikleri Güncelle' : 'Yeni Kayıt Oluştur') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>