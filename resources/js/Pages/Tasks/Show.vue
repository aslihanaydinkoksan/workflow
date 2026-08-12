<script setup>
import { onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import FormRenderer from '@/Components/FormRenderer.vue';

const props = defineProps({
    task: Object,
    nodeData: Object,
    subForm: Object,
    initialData: Object,
    previousForms: {
        type: Array,
        default: () => [],
    },
    previousTaskNotes: {
        type: Array,
        default: () => [],
    },
    prefilledFormData: {
        type: Object,
        default: () => ({}),
    },
    appLogo: {
        type: String,
        default: null,
    },
});

const form = useForm({
    task_action: '',
    comment: '',
    answers: { ...props.prefilledFormData },
});

onMounted(() => {
    if (props.prefilledFormData && Object.keys(props.prefilledFormData).length > 0) {
        form.answers = { ...props.prefilledFormData };
    }
});

const submitAction = (actionType) => {
    form.task_action = actionType;
    form.post(route('tasks.update', props.task.id));
};

const formatDate = (value) => {
    if (!value) return null;
    return new Date(value).toLocaleString('tr-TR');
};
</script>

<template>
    <Head :title="`Görev #${task.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ task.process_instance.workflow.name }} - {{ nodeData?.label || 'Görev' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Düğüm açıklaması (tasarımcının yazdığı talimat) -->
                <div v-if="nodeData?.data?.description" class="bg-indigo-50 border border-indigo-200 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-sm font-semibold text-indigo-900 uppercase tracking-wide mb-2">Görev Açıklaması</h3>
                        <p class="text-sm text-indigo-950 whitespace-pre-wrap leading-relaxed">{{ nodeData.data.description }}</p>
                    </div>
                </div>
                
                <!-- Önceki Adımlarda Doldurulan Formlar -->
                <div
                    v-for="(previousForm, index) in previousForms"
                    :key="`previous-form-${index}`"
                    class="bg-white overflow-hidden shadow-sm sm:rounded-lg"
                >
                    <div class="p-6 border-b border-gray-200 bg-amber-50">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">Doldurulan Form: {{ previousForm.node_label }}</h3>
                                <p class="text-sm text-gray-600">{{ previousForm.form?.name }}</p>
                            </div>
                            <div v-if="previousForm.submitted_by || previousForm.submitted_at" class="text-xs text-gray-500">
                                <span v-if="previousForm.submitted_by">{{ previousForm.submitted_by }}</span>
                                <span v-if="previousForm.submitted_by && previousForm.submitted_at"> · </span>
                                <span v-if="previousForm.submitted_at">{{ formatDate(previousForm.submitted_at) }}</span>
                            </div>
                        </div>
                        <FormRenderer
                            :elements="previousForm.form?.schema || []"
                            :model-value="previousForm.data || {}"
                            :template="previousForm.form"
                            :app-logo="appLogo"
                            :disabled="true"
                        />
                        <div v-if="previousForm.comment" class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4">
                            <h4 class="text-sm font-semibold text-blue-900 mb-1">Görev Notu</h4>
                            <p class="text-sm text-blue-950 whitespace-pre-wrap leading-relaxed">{{ previousForm.comment }}</p>
                        </div>
                    </div>
                </div>

                <!-- Önceki Adımlardan Gelen Görev Notları -->
                <div
                    v-if="previousTaskNotes.length > 0"
                    class="bg-white overflow-hidden shadow-sm sm:rounded-lg"
                >
                    <div class="p-6 bg-slate-50 border-b border-slate-200">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Önceki Adım Notları</h3>
                        <div class="space-y-4">
                            <div
                                v-for="(note, index) in previousTaskNotes"
                                :key="`task-note-${index}`"
                                class="rounded-lg border border-slate-200 bg-white p-4"
                            >
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-2">
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-900">{{ note.node_label }}</h4>
                                        <p v-if="note.author" class="text-xs text-gray-500">{{ note.author }}</p>
                                    </div>
                                    <span v-if="note.completed_at" class="text-xs text-gray-400">{{ formatDate(note.completed_at) }}</span>
                                </div>
                                <p class="text-sm text-gray-800 whitespace-pre-wrap leading-relaxed">{{ note.comment }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Ham süreç verileri (form şablonu olmayan alanlar) -->
                <div v-if="previousForms.length === 0 && initialData && Object.keys(initialData).length > 0" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Süreç Verileri</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div v-for="(value, key) in initialData" :key="key" class="bg-white p-3 rounded border">
                                <span class="block text-xs font-medium text-gray-500 uppercase">{{ key }}</span>
                                <span class="block text-sm font-semibold text-gray-900 mt-1">{{ value }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Aksiyon ve Alt Form -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white">
                        
                        <!-- Sub Form Varsa Göster -->
                        <div v-if="subForm" class="mb-8">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">Bu Adım İçin İstenen Ek Bilgiler: {{ subForm.name }}</h3>
                            <FormRenderer
                                :elements="subForm.schema"
                                v-model="form.answers"
                                :template="subForm"
                                :app-logo="appLogo"
                            />
                        </div>

                        <!-- Yorum Alanı -->
                        <div v-if="task.type === 'approval' || task.type === 'form' || (task.type === 'review' && nodeData?.data?.rejectEnabled)" class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Görev Notu / Yorumunuz (Opsiyonel)</label>
                            <textarea 
                                v-model="form.comment" 
                                rows="3" 
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                placeholder="Açıklama veya ret sebebi yazabilirsiniz..."
                            ></textarea>
                        </div>

                        <!-- Butonlar (Görev Bekliyorsa) -->
                        <div v-if="task.status === 'pending'" class="flex flex-wrap gap-4 justify-end border-t pt-4 mt-6">
                            
                            <!-- İnceleme (Gördüm/Anladım) -->
                            <template v-if="task.type === 'review'">
                                <button
                                    v-if="nodeData?.data?.rejectEnabled"
                                    type="button"
                                    @click="submitAction('reject')"
                                    :disabled="form.processing"
                                    class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded shadow"
                                    title="Akışta Red çıkışının bağlı olduğu adıma gönderir"
                                >
                                    Reddet
                                </button>
                                <button type="button" @click="submitAction('approve')" :disabled="form.processing" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-6 rounded shadow">
                                    Okudum, Anladım
                                </button>
                            </template>

                            <!-- Sadece Form Doldurma -->
                            <template v-else-if="task.type === 'form'">
                                <button
                                    v-if="nodeData?.data?.rejectEnabled"
                                    type="button"
                                    @click="submitAction('reject')"
                                    :disabled="form.processing"
                                    class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded shadow"
                                    title="Akışta Red çıkışının bağlı olduğu adıma gönderir"
                                >
                                    Reddet
                                </button>
                                <button type="button" @click="submitAction('approve')" :disabled="form.processing" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded shadow">
                                    {{ form.processing ? 'Gönderiliyor...' : 'Formu Gönder / Tamamla' }}
                                </button>
                            </template>

                            <!-- Onay Mekanizması -->
                            <template v-else-if="task.type === 'approval'">
                                <button type="button" @click="submitAction('revise')" :disabled="form.processing" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-6 rounded shadow" title="Formu başlatan kişiye düzeltmesi için geri gönderir">
                                    Revize İste (Geri Gönder)
                                </button>

                                <button type="button" @click="submitAction('reject')" :disabled="form.processing" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded shadow" title="Akışta Red çıkışının bağlı olduğu adıma gönderir">
                                    Reddet
                                </button>

                                <button type="button" @click="submitAction('approve')" :disabled="form.processing" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-8 rounded shadow" title="Bir sonraki adıma geçirir">
                                    Onayla
                                </button>
                            </template>
                            
                        </div>

                        <!-- Tamamlanmış Görev (Geri Al Butonu) -->
                        <div v-else class="mt-6 border-t pt-6">
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                                <h4 class="text-md font-bold text-gray-800 mb-2">Görev Tamamlanmış Durumda</h4>
                                <p class="text-sm text-gray-600 mb-2">Bu görevi <strong>{{ new Date(task.completed_at).toLocaleString() }}</strong> tarihinde 
                                    <span v-if="task.status === 'completed'" class="text-green-600 font-bold">Onayladınız</span>
                                    <span v-else-if="task.status === 'rejected'" class="text-red-600 font-bold">Reddettiniz</span>
                                    <span v-else-if="task.status === 'revised'" class="text-orange-600 font-bold">Revize İstediniz</span>.
                                </p>
                                <p v-if="task.comment" class="text-sm italic text-gray-500">Notunuz: "{{ task.comment }}"</p>
                            </div>
                            
                            <div class="flex justify-end">
                                <button @click="$inertia.post(route('tasks.undo', task.id))" class="bg-gray-600 hover:bg-gray-800 text-white font-bold py-2 px-6 rounded shadow flex items-center" title="İşlemi geri al (Sizden sonraki kişi işlem yapmadıysa çalışır)">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                                    İşlemi Geri Al (Undo)
                                </button>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
