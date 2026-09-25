<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    groupedWorkflows: Object
});

const searchQuery = ref('');
const selectedCategory = ref('all');

const categories = computed(() => {
    return Object.keys(props.groupedWorkflows || {});
});

const totalWorkflowsCount = computed(() => {
    let count = 0;
    for (const cat in props.groupedWorkflows) {
        count += (props.groupedWorkflows[cat] || []).length;
    }
    return count;
});

const filteredGroupedWorkflows = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    const result = {};

    for (const category in props.groupedWorkflows) {
        if (selectedCategory.value !== 'all' && selectedCategory.value !== category) {
            continue;
        }

        const workflows = (props.groupedWorkflows[category] || []).filter(wf => {
            if (!q) return true;
            const nameMatch = (wf.name || '').toLowerCase().includes(q);
            const descMatch = (wf.description || '').toLowerCase().includes(q);
            const catMatch = (category || '').toLowerCase().includes(q);
            return nameMatch || descMatch || catMatch;
        });

        if (workflows.length > 0) {
            result[category] = workflows;
        }
    }

    return result;
});
</script>

<template>
    <Head title="Süreç Kataloğu - Yeni Talep Başlat" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="font-extrabold text-2xl text-gray-900 leading-tight">
                        🚀 Süreç Kataloğu (Yeni Talep Başlat)
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Departmanınıza ve rolünüze açık tüm kurumsal formları tek tıkla arayın ve başlatın.
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 bg-gray-100 px-3 py-1.5 rounded-lg border border-gray-200">
                    <span>Toplam Tanımlı Süreç:</span>
                    <span class="text-indigo-600 font-bold text-sm">{{ totalWorkflowsCount }}</span>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- CANLI ARAMA VE KATEGORİ FİLTRELEME ÇUBUĞU -->
                <div class="bg-white rounded-2xl border border-gray-200/80 p-5 shadow-sm space-y-4">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 text-lg">
                            🔍
                        </div>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Süreç veya form ara... (Örn: numune, yıllık izin, yetki, bakım, arıza, satınalma...)"
                            class="w-full pl-11 pr-4 py-3 rounded-xl border-gray-200 bg-gray-50/50 focus:bg-white focus:border-indigo-500 focus:ring-indigo-500 text-sm font-medium transition shadow-inner"
                        />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            type="button"
                            class="absolute inset-y-0 right-0 pr-4 flex items-center text-xs text-gray-400 hover:text-gray-600"
                        >
                            ✕ Temizle
                        </button>
                    </div>

                    <!-- Kategori Butonları -->
                    <div v-if="categories.length > 1" class="flex flex-wrap items-center gap-2 pt-2 border-t border-gray-100">
                        <span class="text-xs font-bold text-gray-500 uppercase mr-1">Kategori:</span>
                        <button
                            type="button"
                            @click="selectedCategory = 'all'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
                            :class="selectedCategory === 'all' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-gray-100 hover:bg-gray-200 text-gray-700'"
                        >
                            Tümü ({{ totalWorkflowsCount }})
                        </button>
                        <button
                            v-for="cat in categories"
                            :key="cat"
                            type="button"
                            @click="selectedCategory = cat"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
                            :class="selectedCategory === cat ? 'bg-indigo-600 text-white shadow-sm' : 'bg-gray-100 hover:bg-gray-200 text-gray-700'"
                        >
                            {{ cat }} ({{ (groupedWorkflows[cat] || []).length }})
                        </button>
                    </div>
                </div>

                <!-- SONUÇ BULUNAMADI UYARISI -->
                <div v-if="Object.keys(filteredGroupedWorkflows).length === 0" class="p-12 text-center bg-white rounded-2xl border border-gray-200 shadow-sm">
                    <div class="text-4xl mb-3">🔍</div>
                    <h3 class="font-bold text-lg text-gray-800">Aramanıza Uygun Süreç Bulunamadı</h3>
                    <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">
                        "{{ searchQuery }}" terimiyle eşleşen aktif bir form bulunamadı. Lütfen farklı bir arama yapınız veya filtreyi temizleyiniz.
                    </p>
                    <button
                        @click="searchQuery = ''; selectedCategory = 'all'"
                        class="mt-4 px-4 py-2 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-bold hover:bg-indigo-100 transition"
                    >
                        Tüm Süreçleri Göster
                    </button>
                </div>

                <!-- KATEGORİ VE FORMLAR LİSTESİ -->
                <div v-for="(workflows, category) in filteredGroupedWorkflows" :key="category" class="space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-200 pb-2">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                            {{ category }}
                        </h3>
                        <span class="text-xs font-semibold text-gray-400">{{ workflows.length }} Süreç</span>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div 
                            v-for="workflow in workflows" 
                            :key="workflow.id" 
                            class="bg-white rounded-2xl border border-gray-200 hover:border-indigo-400 transition-all shadow-sm hover:shadow-md flex flex-col justify-between overflow-hidden group"
                        >
                            <div class="p-6">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="h-11 w-11 rounded-xl bg-gradient-to-br from-indigo-50 to-indigo-100 border border-indigo-200 flex items-center justify-center text-indigo-700 font-black text-lg group-hover:scale-105 transition-transform shrink-0">
                                        {{ workflow.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div>
                                        <h4 class="text-base font-bold text-gray-900 leading-snug group-hover:text-indigo-600 transition-colors">
                                            {{ workflow.name }}
                                        </h4>
                                        <span class="text-[11px] font-semibold text-gray-400">
                                            {{ workflow.form_template?.document_no || 'Form' }}
                                        </span>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-600 line-clamp-2 leading-relaxed">
                                    {{ workflow.description || 'Bu süreç için açıklama tanımlanmamıştır.' }}
                                </p>
                            </div>
                            
                            <div class="p-4 bg-gray-50/80 border-t border-gray-100 flex justify-between items-center">
                                <span class="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                    ✓ Aktif
                                </span>
                                <Link 
                                    :href="route('processes.create', workflow.id)" 
                                    class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-xl text-xs shadow-sm hover:shadow transition"
                                >
                                    <span>Talebi Başlat</span>
                                    <span>&rarr;</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
