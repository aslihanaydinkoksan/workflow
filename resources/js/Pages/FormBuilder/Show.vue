<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FormRenderer from '@/Components/FormRenderer.vue';

const props = defineProps({
    template: {
        type: Object,
        required: true,
    },
    app_logo: {
        type: String,
        default: null,
    },
});

const previewMode = ref('interactive'); // 'interactive' or 'readonly'
const testFormData = ref({});

const resetTestData = () => {
    testFormData.value = {};
};
</script>

<template>
    <Head :title="`${template.name} - Form Önizleme`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <Link :href="route('form-templates.index')" 
                          class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-bold text-gray-800 leading-tight">
                                {{ template.name }}
                            </h2>
                            <span :class="template.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                  class="px-2.5 py-0.5 rounded-full text-xs font-semibold">
                                {{ template.is_active ? 'Aktif' : 'Pasif' }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ template.document_no ? `${template.document_no} • ` : '' }}
                            Rev: {{ template.revision_no || '0' }} • 
                            {{ template.category?.name || 'Genel Kategori' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <!-- Görünüm Modu Değiştirici -->
                    <div class="bg-gray-100 p-1 rounded-lg border border-gray-200 flex items-center text-xs">
                        <button type="button"
                                @click="previewMode = 'interactive'"
                                :class="previewMode === 'interactive' ? 'bg-white shadow-xs font-semibold text-indigo-700' : 'text-gray-600 hover:text-gray-900'"
                                class="px-3 py-1.5 rounded-md transition-all flex items-center gap-1.5">
                            <span>✏️</span>
                            <span>İnteraktif Test Modu</span>
                        </button>
                        <button type="button"
                                @click="previewMode = 'readonly'"
                                :class="previewMode === 'readonly' ? 'bg-white shadow-xs font-semibold text-indigo-700' : 'text-gray-600 hover:text-gray-900'"
                                class="px-3 py-1.5 rounded-md transition-all flex items-center gap-1.5">
                            <span>👁️</span>
                            <span>Salt Okunur (Görüntüleme)</span>
                        </button>
                    </div>

                    <Link :href="route('form-templates.edit', template.id)"
                          class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Tasarla / Düzenle
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Bilgi Kutusu -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-4 flex items-start justify-between shadow-xs">
                    <div class="flex items-start space-x-3">
                        <span class="text-2xl">📋</span>
                        <div>
                            <h3 class="text-sm font-bold text-blue-900">Form Şablon Önizleme ve Test Ekranı</h3>
                            <p class="text-xs text-blue-700 mt-0.5">
                                Bu ekranda tasarladığınız formun hem son kullanıcı tarafından doldurulma aşamasını (tek tarih, tarih aralığı, birden fazla tarih, açıklama notları vb.) hem de onay/görev takip ekranlarındaki salt okunur görünümünü canlı olarak test edebilirsiniz.
                            </p>
                        </div>
                    </div>
                    <button v-if="previewMode === 'interactive'" @click="resetTestData" type="button"
                            class="text-xs text-blue-600 hover:text-blue-800 underline font-medium shrink-0 ml-4">
                        Formu Sıfırla
                    </button>
                </div>

                <!-- Form Render Alanı -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-8">
                    <FormRenderer
                        :key="previewMode"
                        :elements="template.schema"
                        :template="template"
                        :app-logo="app_logo"
                        :disabled="previewMode === 'readonly'"
                        v-model="testFormData"
                    />
                </div>

                <!-- Bağlı İş Akışları (Varsa) -->
                <div v-if="template.primary_workflows && template.primary_workflows.length > 0"
                     class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Bu Formu Kullanan İş Akışları</h4>
                    <div class="flex flex-wrap gap-2">
                        <Link v-for="wf in template.primary_workflows" :key="wf.id"
                              :href="route('workflows.edit', wf.id)"
                              class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-gray-100 hover:bg-indigo-50 hover:text-indigo-700 text-gray-700 transition-colors border border-gray-200">
                            <span>⚡ {{ wf.name }}</span>
                        </Link>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
