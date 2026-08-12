<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import FormRenderer from '@/Components/FormRenderer.vue';

const props = defineProps({
    workflow: Object,
    prefilled_data: {
        type: Object,
        default: () => ({})
    }
});

// Arka plandan gelen önceden doldurulmuş (Data Bind) verileri form objesine aktar
const form = useForm({
    answers: { ...props.prefilled_data }
});

const submit = () => {
    form.post(route('processes.store', props.workflow.id), {
        onError: (errors) => {
            console.error("Inertia errors:", errors);
            alert("Bir hata oluştu: " + JSON.stringify(errors));
        }
    });
};
</script>

<template>
    <Head :title="workflow.name" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Süreci Başlat: {{ workflow.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        
                        <div v-if="!workflow.form_template" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800 mb-6">
                            Bu akışa bağlı başlangıç formu yok. Süreci form olmadan başlatabilirsiniz.
                        </div>

                        <form @submit.prevent="submit">
                            <FormRenderer 
                                v-if="workflow.form_template"
                                :elements="workflow.form_template.schema" 
                                v-model="form.answers" 
                                :template="workflow.form_template"
                                :appLogo="$page.props.app_logo"
                            />

                            <div class="mt-8 flex justify-end">
                                <button 
                                    type="submit" 
                                    :disabled="form.processing"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded shadow"
                                >
                                    {{ form.processing ? 'Başlatılıyor...' : 'Süreci Başlat' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
