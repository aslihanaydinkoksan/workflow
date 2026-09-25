<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import FormRenderer from '@/Components/FormRenderer.vue';
import { ref } from 'vue';

const props = defineProps({
    workflow: Object,
    prefilled_data: {
        type: Object,
        default: () => ({})
    }
});

const errorMessage = ref('');

// Arka plandan gelen önceden doldurulmuş (Data Bind) verileri form objesine aktar
const form = useForm({
    answers: { ...props.prefilled_data }
});

const submit = () => {
    errorMessage.value = '';
    form.post(route('processes.store', props.workflow.id), {
        onError: (errors) => {
            console.error("Inertia errors:", errors);
            const messages = Object.values(errors).flat();
            errorMessage.value = messages.length > 0
                ? messages.join(' ')
                : 'Form gönderilirken bir hata oluştu. Lütfen zorunlu alanları kontrol ediniz.';
        }
    });
};
</script>

<template>
    <Head :title="`Süreç Başlat: ${workflow.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <!-- Breadcrumbs -->
                    <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                        <Link :href="route('dashboard')" class="hover:text-indigo-600 transition-colors flex items-center gap-1">
                            <span>🏠</span> Ana Sayfa
                        </Link>
                        <span>/</span>
                        <Link :href="route('processes.index')" class="hover:text-indigo-600 transition-colors">
                            Süreç Kataloğu
                        </Link>
                        <span>/</span>
                        <span class="text-gray-700 font-medium truncate max-w-xs">{{ workflow.name }}</span>
                    </div>

                    <h2 class="font-bold text-2xl text-gray-900 leading-tight">
                        {{ workflow.name }}
                    </h2>
                </div>

                <div class="flex items-center gap-3">
                    <Link
                        :href="route('processes.index')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold text-gray-600 bg-white border border-gray-200 hover:bg-gray-50 hover:text-gray-900 shadow-sm transition-all"
                    >
                        <span>&larr;</span> Kataloğa Dön
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Süreç Bilgi ve Rehberlik Kartı -->
                <div class="bg-gradient-to-r from-indigo-500/10 via-blue-500/5 to-transparent border border-indigo-100 rounded-2xl p-5 shadow-sm">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-xl shrink-0 shadow-md shadow-indigo-600/20">
                            🚀
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-indigo-100 text-indigo-800">
                                    {{ workflow.category || 'Kurumsal Süreç' }}
                                </span>
                                <span v-if="workflow.form_template?.code" class="text-xs font-medium px-2 py-0.5 rounded-md bg-gray-100 text-gray-600 font-mono">
                                    {{ workflow.form_template.code }}
                                </span>
                            </div>
                            <h3 class="text-base font-bold text-gray-900">
                                Yeni Talep Başlatma Formu
                            </h3>
                            <p class="text-xs sm:text-sm text-gray-600 mt-1 leading-relaxed">
                                Bu formu doldurup gönderdiğinizde, talebiniz sistem tarafından kayıt altına alınarak ilgili onay/işlem zincirine otomatik iletilecektir. Sürecinizin anlık durumunu adım adım canlı takip ekranından izleyebilirsiniz.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Form Konteyneri -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-gray-200/80">
                    <div class="p-6 sm:p-8">

                        <div v-if="!workflow.form_template" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800 mb-6 flex items-center gap-3">
                            <span class="text-xl">ℹ️</span>
                            <div class="text-sm">
                                <p class="font-semibold">Başlangıç Formu Tanımlı Değil</p>
                                <p class="text-xs text-amber-700 mt-0.5">Bu akışa bağlı özel bir veri formu bulunmuyor. Süreci doğrudan başlatabilirsiniz.</p>
                            </div>
                        </div>

                        <!-- Hata Bildirimi -->
                        <div v-if="errorMessage" class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800 text-sm mb-6 flex items-start gap-3 shadow-sm">
                            <svg class="w-5 h-5 mt-0.5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <div>
                                <p class="font-bold">Form Gönderilemedi</p>
                                <p class="mt-0.5 text-xs sm:text-sm">{{ errorMessage }}</p>
                            </div>
                        </div>

                        <!-- Dinamik Form Bileşeni -->
                        <form @submit.prevent="submit" class="space-y-8">
                            <FormRenderer 
                                v-if="workflow.form_template"
                                :elements="workflow.form_template.schema" 
                                v-model="form.answers" 
                                :template="workflow.form_template"
                                :appLogo="$page.props.app_logo"
                            />

                            <!-- Aksiyon ve Buton Çubuğu -->
                            <div class="pt-6 border-t border-gray-100 flex flex-col-reverse sm:flex-row items-center justify-between gap-4">
                                <Link
                                    :href="route('processes.index')"
                                    class="w-full sm:w-auto text-center px-5 py-2.5 rounded-xl text-sm font-semibold text-gray-600 hover:text-gray-900 hover:bg-gray-100 transition-colors"
                                >
                                    &larr; Vazgeç ve Kataloğa Dön
                                </Link>

                                <button 
                                    type="submit" 
                                    :disabled="form.processing"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white font-bold py-3 px-8 rounded-xl shadow-lg shadow-indigo-600/25 disabled:opacity-50 transition-all transform hover:-translate-y-0.5 active:translate-y-0"
                                >
                                    <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>{{ form.processing ? 'Talebiniz Oluşturuluyor...' : '🚀 Talebi Oluştur ve Süreci Başlat' }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
