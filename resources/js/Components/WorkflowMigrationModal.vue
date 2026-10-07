<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    show: Boolean,
    workflow: Object,
});

const emit = defineEmits(['close', 'migrated']);

const loading = ref(false);
const migrating = ref(false);
const schemaData = ref(null);
const targetWorkflowId = ref('');
const nodeMapping = ref({});
const errorMessage = ref('');
const successMessage = ref('');

const fetchSchema = async () => {
    if (!props.workflow?.id) return;
    loading.value = true;
    errorMessage.value = '';
    try {
        const url = `/workflows/${props.workflow.id}/migration-schema` + (targetWorkflowId.value ? `?target_workflow_id=${targetWorkflowId.value}` : '');
        const res = await axios.get(url);
        schemaData.value = res.data;
        nodeMapping.value = { ...res.data.suggested_mapping };
        if (!targetWorkflowId.value && res.data.target_workflow?.id) {
            targetWorkflowId.value = res.data.target_workflow.id;
        }
    } catch (err) {
        errorMessage.value = err.response?.data?.message || 'Eşleştirme şeması yüklenemedi.';
    } finally {
        loading.value = false;
    }
};

watch(() => [props.show, props.workflow?.id], ([newShow, wfId]) => {
    if (newShow && wfId) {
        successMessage.value = '';
        errorMessage.value = '';
        targetWorkflowId.value = '';
        fetchSchema();
    }
});

const executeMigration = async () => {
    if (!props.workflow?.id || !targetWorkflowId.value) return;
    migrating.value = true;
    errorMessage.value = '';
    try {
        const res = await axios.post(`/workflows/${props.workflow.id}/migrate-instances`, {
            target_workflow_id: targetWorkflowId.value,
            node_mapping: nodeMapping.value,
        });

        successMessage.value = res.data.message || 'Canlı süreç göçü başarıyla tamamlandı.';
        emit('migrated', res.data);
        setTimeout(() => {
            emit('close');
        }, 1500);
    } catch (err) {
        errorMessage.value = err.response?.data?.message || 'Göç işlemi sırasında hata oluştu.';
    } finally {
        migrating.value = false;
    }
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm animate-fadeIn">
        <div class="relative w-full max-w-3xl bg-slate-900 border border-slate-700/80 rounded-2xl shadow-2xl overflow-hidden text-slate-200">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-slate-800 bg-gradient-to-r from-slate-900 via-indigo-950/40 to-slate-900 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-purple-500/20 border border-purple-500/30 flex items-center justify-center text-xl text-purple-400">
                        🔄
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white tracking-wide flex items-center gap-2">
                            <span>BPMN 2.0 Canlı Süreç Versiyon Göçü</span>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30 font-mono">Zero-Downtime</span>
                        </h3>
                        <p class="text-xs text-slate-400">
                            Aktif yürüyen süreçleri kesintisiz olarak hedef akış şemasına eşleştirip taşır.
                        </p>
                    </div>
                </div>

                <button @click="$emit('close')" class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-slate-800 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-5 max-h-[70vh] overflow-y-auto custom-scrollbar">
                <!-- Bildirimler -->
                <div v-if="successMessage" class="p-4 rounded-xl bg-emerald-950/50 border border-emerald-500/40 text-emerald-300 text-sm flex items-center gap-2">
                    <span class="text-base">✅</span>
                    <span>{{ successMessage }}</span>
                </div>
                <div v-if="errorMessage" class="p-4 rounded-xl bg-rose-950/50 border border-rose-500/40 text-rose-300 text-sm flex items-center gap-2">
                    <span class="text-base">⚠️</span>
                    <span>{{ errorMessage }}</span>
                </div>

                <!-- Yükleniyor Durumu -->
                <div v-if="loading" class="py-12 text-center text-slate-400 space-y-2">
                    <div class="w-8 h-8 border-2 border-purple-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
                    <p class="text-xs">Şema analiz ediliyor ve düğüm eşleştirmeleri hazırlanıyor...</p>
                </div>

                <div v-else-if="schemaData" class="space-y-5">
                    <!-- Kaynak vs Hedef Karşılaştırma Kartları -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 rounded-xl bg-slate-800/60 border border-slate-700/60">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Kaynak Akış (Eski Versiyon)</span>
                            <div class="text-sm font-bold text-white flex items-center gap-1.5">
                                <span>{{ schemaData.source_workflow.name }}</span>
                                <span class="px-2 py-0.5 rounded bg-slate-700 text-slate-300 text-xs font-mono">v{{ schemaData.source_workflow.version }}.0</span>
                            </div>
                            <div class="mt-2 text-xs text-amber-400 flex items-center gap-1.5 font-medium">
                                <span>⏳</span>
                                <span>{{ schemaData.source_workflow.active_instances_count }} Aktif Canlı Süreç</span>
                            </div>
                        </div>

                        <div class="p-4 rounded-xl bg-purple-950/30 border border-purple-500/30">
                            <span class="text-[10px] font-bold text-purple-400 uppercase tracking-wider block mb-1">Hedef Akış (Yeni Versiyon)</span>
                            <div class="text-sm font-bold text-white flex items-center gap-1.5">
                                <span>{{ schemaData.target_workflow.name }}</span>
                                <span class="px-2 py-0.5 rounded bg-purple-500/20 text-purple-300 border border-purple-500/30 text-xs font-mono">v{{ schemaData.target_workflow.version }}.0 (Hedef)</span>
                            </div>
                            <div class="mt-2 text-xs text-emerald-400 flex items-center gap-1.5 font-medium">
                                <span>✨</span>
                                <span>Yeni Standart Şema (Zero-Downtime)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Düğüm Eşleştirme Tablosu -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
                                <span>🔀</span> Düğüm Eşleştirme Matrisi (Node Mapping)
                            </h4>
                            <span class="text-[10px] text-slate-400">
                                {{ schemaData.source_nodes.length }} Kaynak Düğüm $\rightarrow$ {{ schemaData.target_nodes.length }} Hedef Düğüm
                            </span>
                        </div>

                        <div class="border border-slate-700/80 rounded-xl overflow-hidden bg-slate-950/40">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-800/80 text-slate-400 uppercase font-semibold text-[10px]">
                                    <tr>
                                        <th class="py-2.5 px-4">Eski Akış Düğümü (v{{ schemaData.source_workflow.version }}.0)</th>
                                        <th class="py-2.5 px-2 text-center w-8"></th>
                                        <th class="py-2.5 px-4">Hedef Akış Düğümü (v{{ schemaData.target_workflow.version }}.0)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/60">
                                    <tr v-for="sNode in schemaData.source_nodes" :key="sNode.id" class="hover:bg-slate-800/30 transition-colors">
                                        <td class="py-3 px-4">
                                            <div class="font-medium text-white flex items-center gap-2">
                                                <span>{{ sNode.label }}</span>
                                                <span v-if="sNode.active_tasks_count > 0" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30 animate-pulse">
                                                    {{ sNode.active_tasks_count }} Aktif Süreç
                                                </span>
                                            </div>
                                            <span class="text-[10px] font-mono text-slate-500">{{ sNode.id }} ({{ sNode.task_type }})</span>
                                        </td>
                                        <td class="py-3 px-2 text-center text-purple-400 font-bold text-sm">
                                            ➔
                                        </td>
                                        <td class="py-3 px-4">
                                            <select 
                                                v-model="nodeMapping[sNode.id]" 
                                                class="w-full bg-slate-800 border border-slate-700 rounded-lg text-xs text-white px-3 py-1.5 focus:ring-1 focus:ring-purple-500"
                                            >
                                                <option v-for="tNode in schemaData.target_nodes" :key="tNode.id" :value="tNode.id">
                                                    {{ tNode.label }} ({{ tNode.id }})
                                                </option>
                                            </select>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Bilgilendirme Kalkanı -->
                    <div class="p-3 rounded-xl bg-purple-950/20 border border-purple-500/20 text-purple-300 text-xs flex items-start gap-2">
                        <span class="text-sm">🛡️</span>
                        <span class="leading-relaxed">
                            <strong>Kesintisiz Canlı Geçiş:</strong>
                            Göç butonuna tıklandığında, bekleyen görevler yeni düğüm şemasıyla anında senkronize edilir. Kullanıcılar görevlerini kaldıkları yerden yeni versiyon kurallarıyla tamamlar.
                        </span>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-slate-800 bg-slate-900/80 flex items-center justify-between">
                <button 
                    @click="$emit('close')" 
                    class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"
                >
                    Vazgeç
                </button>

                <button 
                    @click="executeMigration" 
                    :disabled="migrating || loading || !schemaData?.source_workflow?.active_instances_count"
                    class="px-5 py-2.5 rounded-xl text-xs font-bold bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white shadow-lg shadow-purple-900/30 transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span v-if="migrating" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    <span v-else>🚀</span>
                    <span>{{ migrating ? 'Göç Yürütülüyor...' : 'Canlı Süreçleri Yeni Versiyona Taşı' }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.98); }
    to { opacity: 1; transform: scale(1); }
}
.animate-fadeIn {
    animation: fadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
</style>
