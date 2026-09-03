<script setup>
import { ref, watch, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    show: Boolean,
    workflowId: [Number, String],
    nodeId: String,
    availableNodes: Array
});

const emit = defineEmits(['close']);

const availableFields = ref([]);
const existingRules = ref([]);
const isSaving = ref(false);
const editingRuleId = ref(null); // Düzenlenen kuralın ID'sini tutacak

const ruleForm = ref({
    name: '',
    priority: 1,
    condition_type: 'all',
    action_type: 'route_to',
    target_node_id: '',
    reason: '',
    conditions: []
});

// 1. İYİLEŞTİRME: Doğal Dil Operatör Haritası (Teknik Sembolleri Gizliyoruz)
const OPERATORS = {
    '==': 'Şuna Tam Eşitse:',
    '!=': 'Şundan Farklıysa:',
    '>': 'Şundan Büyükse:',
    '<': 'Şundan Küçükse:',
    'contains': 'İçinde Şu Geçiyorsa:',
    'is_not_empty': 'Doldurulmuşsa (Boş Değilse)',
    'is_empty': 'Boş Bırakılmışsa'
};

const fetchExistingRules = async () => {
    if (!props.workflowId || !props.nodeId) return;
    try {
        const response = await axios.get(route('admin.rules.node', { workflow: props.workflowId, node: props.nodeId }));
        existingRules.value = response.data.rules || [];
    } catch (error) {
        console.error("Mevcut kurallar çekilirken hata oluştu:", error);
    }
};

watch(() => props.show, async (newVal) => {
    if (newVal && props.workflowId) {
        resetForm();
        fetchExistingRules();
        try {
            const response = await axios.get(route('admin.rules.fields', { workflow: props.workflowId }));
            // Gelen alanların { value, label, type, options } formatında olduğunu varsayıyoruz
            availableFields.value = response.data.fields || [];
            if (ruleForm.value.conditions.length === 0) addCondition();
        } catch (error) {
            alert("Dinamik alanlar yüklenirken bir hata oluştu.");
        }
    }
});

const addCondition = () => {
    ruleForm.value.conditions.push({ field: '', operator: '==', value: '' });
};

const removeCondition = (index) => {
    ruleForm.value.conditions.splice(index, 1);
};

const resetForm = () => {
    editingRuleId.value = null;
    ruleForm.value = {
        name: '', priority: 1, condition_type: 'all', action_type: 'route_to', target_node_id: '', reason: '', conditions: []
    };
    addCondition();
};

// Yardımcı Fonksiyonlar: Alanın tipini ve seçeneklerini bulma
const getFieldDetails = (fieldValue) => {
    return availableFields.value.find(f => f.value === fieldValue) || { type: 'text' };
};

// YENİ: Düzenle (Edit) Fonksiyonu
const editRule = (rule) => {
    editingRuleId.value = rule.id;
    // Veritabanından gelen kuralı, forma yansıtıyoruz
    ruleForm.value = {
        name: rule.name,
        priority: rule.priority,
        condition_type: rule.condition_type,
        action_type: rule.action.type || 'route_to',
        target_node_id: rule.action.params?.target_node_id || '',
        reason: rule.action.params?.reason || '',
        conditions: JSON.parse(JSON.stringify(rule.conditions)) // Deep copy
    };
};

// 4. İYİLEŞTİRME: Hazır Şablonlar (Presets)
const applyPreset = (presetType) => {
    resetForm();
    if (presetType === 'src_kontrol') {
        ruleForm.value.name = "Araç Talep - SRC Güvenlik Kontrolü";
        ruleForm.value.action_type = "route_to";
        ruleForm.value.reason = "SRC belgesi bulunmadığı için araç talebi reddedilmelidir. Lütfen formu inceleyerek sadece ayakkabı/ekipman kısımlarını onaylayınız.";
        ruleForm.value.conditions = [
            { field: 'form.talep_edilen_arac', operator: 'is_not_empty', value: '' },
            { field: 'actor.metadata.src_belgesi', operator: '==', value: false }
        ];
    } else if (presetType === 'ambar_yonlendirme') {
        ruleForm.value.name = "İş Ayakkabısı Ambar Yönlendirmesi";
        ruleForm.value.action_type = "route_to";
        ruleForm.value.reason = "Kullanıcının iş ayakkabısı talebi bulunmaktadır. Lütfen stok kontrolü yapınız.";
        ruleForm.value.conditions = [
            { field: 'form.talep_edilen_ekipman', operator: 'contains', value: 'Ayakkabı' }
        ];
    }
};

// 3. İYİLEŞTİRME: Otomatik Kural Özeti (Doğal Dil Çevirmeni)
const ruleSummary = computed(() => {
    if (!ruleForm.value.conditions.length || !ruleForm.value.conditions[0].field) {
        return "Özet oluşturulabilmesi için lütfen aşağıdan koşul belirleyiniz.";
    }

    const conditionsText = ruleForm.value.conditions.map((c) => {
        if (!c.field) return "[Alan Seçilmedi]";
        const fieldObj = getFieldDetails(c.field);
        const fieldLabel = fieldObj.label || c.field;
        const operatorText = OPERATORS[c.operator] || '';

        let valueText = c.value;
        if (c.value === true || c.value === 'true') valueText = 'Evet / Var';
        else if (c.value === false || c.value === 'false') valueText = 'Hayır / Yok';

        if (['is_empty', 'is_not_empty'].includes(c.operator)) {
            return `"${fieldLabel}" alanı ${operatorText.toLowerCase()}`;
        }
        return `"${fieldLabel}" alanı ${operatorText.toLowerCase()} "${valueText || '?'}"`;
    });

    const joiner = ruleForm.value.condition_type === 'all' ? ' <b class="text-pink-400">VE</b> ' : ' <b class="text-pink-400">VEYA</b> ';
    let summary = `Eğer ${conditionsText.join(joiner)}`;

    const targetNode = props.availableNodes?.find(n => n.id === ruleForm.value.target_node_id);
    const targetName = targetNode?.data?.label || targetNode?.data?.customName || 'seçilen adıma';
    const actionText = ruleForm.value.action_type === 'reject_and_route' ? 'talebi tamamen reddederek' : 'süreci';

    if (ruleForm.value.target_node_id) {
        summary += ` ise; ${actionText} <b class="text-purple-300">"${targetName}"</b> adımına yönlendir.`;
    } else {
        summary += ' ise; <span class="opacity-50">(Lütfen yönlendirilecek hedef adımı seçin).</span>';
    }

    return summary;
});
const deleteRule = async (ruleId) => {
    if (!confirm('Bu kuralı silmek istediğinize emin misiniz?')) return;
    try {
        await axios.delete(route('admin.rules.destroy', { rule: ruleId }));
        await fetchExistingRules(); // Silince listeyi yenile
    } catch (error) {
        alert("Kural silinemedi.");
    }
};

const saveRule = async () => {
    if (!ruleForm.value.target_node_id) {
        alert("Lütfen kural sağlanırsa akışın yönlendirileceği hedef düğümü seçin.");
        return;
    }

    isSaving.value = true;

    const parsedConditions = ruleForm.value.conditions.filter(c => c.field && (c.value !== '' || ['is_empty', 'is_not_empty'].includes(c.operator))).map(c => {
        let parsedValue = c.value;
        if (parsedValue === 'true' || parsedValue === true) parsedValue = true;
        else if (parsedValue === 'false' || parsedValue === false) parsedValue = false;
        else if (!isNaN(parsedValue) && String(parsedValue).trim() !== '') parsedValue = Number(parsedValue);

        return { field: c.field, operator: c.operator, value: parsedValue };
    });

    const payload = {
        workflow_id: parseInt(props.workflowId),
        node_id: props.nodeId,
        name: ruleForm.value.name,
        priority: parseInt(ruleForm.value.priority),
        condition_type: ruleForm.value.condition_type,
        conditions: parsedConditions,
        action: {
            type: ruleForm.value.action_type,
            params: { target_node_id: ruleForm.value.target_node_id, reason: ruleForm.value.reason }
        },
        is_active: true
    };

    try {
        if (editingRuleId.value) {
            // Güncelleme Modu (PUT)
            await axios.put(route('admin.rules.update', { rule: editingRuleId.value }), payload);
            alert("Kural başarıyla güncellendi!");
        } else {
            // Yeni Ekleme Modu (POST)
            await axios.post(route('admin.rules.store'), payload);
            alert("Kural başarıyla eklendi!");
        }
        resetForm();
        await fetchExistingRules();
    } catch (error) {
        alert("Kural kaydedilemedi: " + (error.response?.data?.message || error.message));
    } finally {
        isSaving.value = false;
    }
};
</script>

<template>
    <div v-if="show"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-md transition-all duration-300 p-4 overflow-y-auto">
        <div
            class="glass-rule-panel w-full max-w-5xl p-8 rounded-3xl transform transition-transform duration-300 shadow-2xl my-8">

            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-light tracking-wide text-white flex items-center gap-3">
                    <div class="p-2 bg-pink-500/20 rounded-xl">
                        <svg class="w-6 h-6 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    Akıllı Kural Motoru
                </h3>
                <button @click="$emit('close')"
                    class="text-white/50 hover:text-white transition p-2 bg-white/5 hover:bg-white/10 rounded-full">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <!-- HAZIR ŞABLONLAR (PRESETS) -->
            <div class="mb-8 flex gap-3 pb-6 border-b border-white/10">
                <span class="text-xs text-white/50 uppercase tracking-widest flex items-center mr-2">Hızlı
                    Şablonlar:</span>
                <button @click="applyPreset('src_kontrol')" type="button"
                    class="px-4 py-1.5 text-xs font-medium rounded-lg bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 hover:bg-indigo-500/40 transition">
                    ⚡ SRC / Araç Kontrolü
                </button>
                <button @click="applyPreset('ambar_yonlendirme')" type="button"
                    class="px-4 py-1.5 text-xs font-medium rounded-lg bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 hover:bg-emerald-500/40 transition">
                    📦 Ekipman Ambar Yönlendirmesi
                </button>
                <button @click="resetForm" type="button"
                    class="px-4 py-1.5 text-xs font-medium rounded-lg bg-white/5 text-white/70 hover:bg-white/10 transition ml-auto">
                    Tümünü Temizle
                </button>
            </div>

            <!-- YENİ: MEVCUT KURALLAR LİSTESİ -->
            <div class="mb-8 p-5 bg-black/40 rounded-2xl border border-white/10 shadow-inner">
                <h4 class="text-xs font-bold text-pink-400 mb-4 uppercase tracking-widest flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                        </path>
                    </svg>
                    Bu Düğüme Tanımlanmış Aktif Kurallar
                </h4>

                <!-- Eğer liste boşsa veya yüklenemediyse burası görünecek -->
                <div v-if="existingRules.length === 0"
                    class="text-center py-5 border-2 border-dashed border-white/10 rounded-xl bg-white/5">
                    <span class="text-sm text-white/50 font-medium">Bu düğüme henüz bir kural eklenmemiş veya kurallar
                        yüklenemedi.</span>
                </div>

                <!-- Eğer kurallar başarıyla geldiyse liste görünecek -->
                <div v-else class="space-y-3">
                    <div v-for="rule in existingRules" :key="rule.id"
                        class="flex justify-between items-center p-3 bg-white/5 hover:bg-white/10 transition rounded-xl border border-white/5 group">
                        <div class="flex items-center gap-3">
                            <span
                                class="bg-gradient-to-r from-pink-500 to-purple-500 text-white text-[10px] px-2 py-1 rounded-md font-bold shadow-md">
                                Öncelik: {{ rule.priority }}
                            </span>
                            <span class="text-sm text-white/90 font-medium">{{ rule.name }}</span>
                            <span v-if="rule.action?.params?.target_node_id" class="text-xs text-white/40">
                                ➾ Hedef: {{ rule.action.params.target_node_id }}
                            </span>
                        </div>

                        <!-- Butonları saran wrapper -->
                        <div class="flex items-center">
                            <!-- Düzenle Butonu -->
                            <button type="button" @click="editRule(rule)"
                                class="text-blue-400/50 hover:text-blue-400 hover:bg-blue-400/10 transition p-2 rounded-lg opacity-0 group-hover:opacity-100"
                                title="Kuralı Düzenle">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z">
                                    </path>
                                </svg>
                            </button>
                            <!-- Sil Butonu -->
                            <button type="button" @click="deleteRule(rule.id)"
                                class="text-red-400/50 hover:text-red-400 hover:bg-red-400/10 transition p-2 rounded-lg opacity-0 group-hover:opacity-100"
                                title="Kuralı Sil">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                    </path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <form @submit.prevent="saveRule">
                <!-- Üst Bilgiler -->
                <div class="grid grid-cols-12 gap-6 mb-6">
                    <div class="col-span-8">
                        <label class="block text-[11px] font-bold text-white/50 mb-2 uppercase tracking-widest">Kuralın
                            Tanımı / Adı <span class="text-red-400">*</span></label>
                        <input v-model="ruleForm.name" type="text" placeholder="Örn: SRC Yoksa İK'ya Gönder" required
                            class="w-full rule-input rounded-xl px-4 py-3 text-sm transition focus:ring-2 focus:ring-pink-500/50">
                    </div>
                    <div class="col-span-4">
                        <label class="block text-[11px] font-bold text-white/50 mb-2 uppercase tracking-widest">Çalışma
                            Önceliği (1 = İlk) <span class="text-red-400">*</span></label>
                        <input v-model.number="ruleForm.priority" type="number" min="1" required
                            class="w-full rule-input rounded-xl px-4 py-3 text-sm text-center">
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-8">

                    <!-- SOL PANEL: Koşullar -->
                    <div class="lg:col-span-7 flex flex-col">
                        <div class="flex justify-between items-center mb-4">
                            <label class="block text-[11px] font-bold text-white/50 uppercase tracking-widest">Sistemin
                                Kontrol Edeceği Şartlar</label>
                            <select v-model="ruleForm.condition_type"
                                class="rule-input rounded-lg px-3 py-1.5 text-xs bg-black/40 border-none outline-none [&>option]:text-black cursor-pointer">
                                <option value="all">TÜM Şartlar Sağlanmalı (VE)</option>
                                <option value="any">BİR Şart Sağlanmalı (VEYA)</option>
                            </select>
                        </div>

                        <div class="space-y-3 max-h-[300px] overflow-y-auto custom-scrollbar pr-2 mb-4">
                            <div v-for="(cond, index) in ruleForm.conditions" :key="index"
                                class="flex flex-wrap items-center gap-2 bg-white/5 p-3 rounded-xl border border-white/10 group">

                                <!-- Form / Veri Alanı -->
                                <div class="w-full sm:flex-1 min-w-[200px]">
                                    <select v-model="cond.field" @change="cond.value = ''"
                                        class="w-full rule-input rounded-lg px-3 py-2 text-sm [&>option]:text-black"
                                        required>
                                        <option value="" disabled>-- Form Alanı / Veri Seç --</option>
                                        <option v-for="field in availableFields" :key="field.value"
                                            :value="field.value">{{ field.label }}</option>
                                    </select>
                                </div>

                                <!-- Operatör (İnsan Dilinde) -->
                                <div class="w-full sm:w-[160px]">
                                    <select v-model="cond.operator"
                                        class="w-full rule-input rounded-lg px-3 py-2 text-sm [&>option]:text-black bg-indigo-900/30 font-medium">
                                        <option v-for="(label, val) in OPERATORS" :key="val" :value="val">{{ label }}
                                        </option>
                                    </select>
                                </div>

                                <!-- 2. İYİLEŞTİRME: Akıllı Girdi (Smart Inputs) -->
                                <div class="w-full sm:flex-1 min-w-[150px]"
                                    v-show="!['is_empty', 'is_not_empty'].includes(cond.operator)">
                                    <!-- Boolean (Evet/Hayır) ise -->
                                    <select v-if="getFieldDetails(cond.field).type === 'boolean'" v-model="cond.value"
                                        class="w-full rule-input rounded-lg px-3 py-2 text-sm [&>option]:text-black bg-pink-900/30"
                                        required>
                                        <option value="" disabled>-- Seçin --</option>
                                        <option :value="true">Evet / Var</option>
                                        <option :value="false">Hayır / Yok</option>
                                    </select>
                                    <!-- Select (Açılır Liste) ise -->
                                    <select v-else-if="getFieldDetails(cond.field).type === 'select'"
                                        v-model="cond.value"
                                        class="w-full rule-input rounded-lg px-3 py-2 text-sm [&>option]:text-black bg-pink-900/30"
                                        required>
                                        <option value="" disabled>-- Listeden Seçin --</option>
                                        <option v-for="opt in getFieldDetails(cond.field).options" :key="opt"
                                            :value="opt">{{ opt }}</option>
                                    </select>
                                    <!-- Standart Metin/Sayı ise -->
                                    <input v-else v-model="cond.value" type="text" placeholder="Aranan değer..."
                                        class="w-full rule-input rounded-lg px-3 py-2 text-sm placeholder-white/30 bg-pink-900/30">
                                </div>

                                <!-- Sil Butonu -->
                                <button type="button" @click="removeCondition(index)"
                                    class="p-2 text-red-400 hover:text-red-300 hover:bg-red-400/10 rounded-lg transition opacity-50 group-hover:opacity-100">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <button type="button" @click="addCondition"
                            class="w-full py-2.5 rounded-xl border border-dashed border-white/20 text-white/50 hover:text-white/80 hover:border-white/40 hover:bg-white/5 transition text-sm font-medium">
                            + Yeni Bir Şart Daha Ekle
                        </button>
                    </div>

                    <!-- SAĞ PANEL: Aksiyon ve Sonuç -->
                    <div
                        class="lg:col-span-5 flex flex-col gap-5 bg-gradient-to-br from-indigo-900/40 to-purple-900/40 p-6 rounded-2xl border border-indigo-500/20">
                        <div>
                            <label
                                class="block text-[11px] font-bold text-indigo-300 mb-2 uppercase tracking-widest">Şartlar
                                Sağlanırsa Ne Olsun?</label>
                            <select v-model="ruleForm.action_type"
                                class="w-full rule-input rounded-xl px-4 py-3 text-sm [&>option]:text-black mb-3">
                                <option value="route_to">Süreci Saptır / Başka Birime Gönder</option>
                                <option value="reject_and_route">Talebi İptal Et ve Geri Çevir</option>
                            </select>

                            <select v-model="ruleForm.target_node_id"
                                class="w-full rule-input rounded-xl px-4 py-3 text-sm [&>option]:text-black font-medium text-pink-100"
                                required>
                                <option value="" disabled>-- Hedef İşlemi Seçin --</option>
                                <option v-for="node in availableNodes" :key="node.id" :value="node.id">
                                    Hedef ➾ {{ node.data?.label || node.data?.customName || node.id }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label
                                class="block text-[11px] font-bold text-indigo-300 mb-2 uppercase tracking-widest">Kullanıcıya
                                Gösterilecek Bildirim Notu</label>
                            <textarea v-model="ruleForm.reason" rows="3"
                                placeholder="Örn: Sistem kayıtlarında SRC belgeniz bulunamadığı için talebiniz İK departmanına yönlendirilmiştir."
                                class="w-full rule-input rounded-xl px-4 py-3 text-sm custom-scrollbar leading-relaxed"></textarea>
                        </div>
                    </div>
                </div>

                <!-- 3. İYİLEŞTİRME: Canlı Çeviri (Doğal Dil Özeti) -->
                <div
                    class="mb-8 p-5 rounded-2xl bg-gradient-to-r from-emerald-900/20 to-teal-900/20 border border-emerald-500/20 shadow-inner flex items-start gap-4">
                    <div class="p-2 bg-emerald-500/20 rounded-full shrink-0">
                        <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-emerald-400 uppercase tracking-widest mb-1">Kural Okuması
                            (Özet)</h4>
                        <p class="text-sm text-emerald-50/90 leading-relaxed" v-html="ruleSummary"></p>
                    </div>
                </div>

                <!-- Footer Butonlar -->
                <div class="flex justify-end gap-3 pt-6 border-t border-white/10">
                    <button type="button" @click="$emit('close')"
                        class="px-6 py-3 rounded-xl bg-white/5 hover:bg-white/10 text-white transition text-sm font-medium">İptal
                        Et</button>
                    <button type="submit" :disabled="isSaving"
                        class="px-8 py-3 rounded-xl bg-gradient-to-r from-pink-600 to-purple-600 hover:from-pink-500 hover:to-purple-500 text-white font-bold transition shadow-lg shadow-pink-500/20 text-sm disabled:opacity-50 flex items-center gap-2">
                        <svg v-if="isSaving" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none"
                            viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        {{ isSaving ? 'Kaydediliyor...' : (editingRuleId ? 'Değişiklikleri Güncelle' : 'Kuralı Aktifleştir') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.glass-rule-panel {
    background: rgba(15, 23, 42, 0.95);
    /* Çok koyu lacivert/siyah */
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.rule-input {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: white;
}

.rule-input:focus {
    border-color: rgba(236, 72, 153, 0.6);
    background: rgba(0, 0, 0, 0.4);
    outline: none;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.25);
}
</style>