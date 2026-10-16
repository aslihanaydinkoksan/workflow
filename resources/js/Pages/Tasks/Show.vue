<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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
    fieldAudits: {
        type: Array,
        default: () => [],
    },
    documents: {
        type: Array,
        default: () => [],
    },
    budgetControl: {
        type: Object,
        default: () => ({ has_budget_impact: false }),
    },
    leadTimeAnalytics: {
        type: Object,
        default: () => null,
    },
});

// Yan Yana Belge / Fatura Önizleyici (Split-Screen Viewer)
const isSplitViewOpen = ref(false);
const activeDocumentIndex = ref(0);
const activeDocument = computed(() => {
    if (!props.documents || props.documents.length === 0) return null;
    return props.documents[activeDocumentIndex.value] || props.documents[0];
});

const toggleSplitView = () => {
    isSplitViewOpen.value = !isSplitViewOpen.value;
};

const isRevision = computed(() => {
    return !!(
        props.task?.is_revision ||
        props.nodeData?.data?.isRevision ||
        props.initialData?.isletme_karari === 'Revizyon İstendi' ||
        (props.task?.title || '').toLowerCase().includes('reviz')
    );
});

const revisionNote = computed(() => {
    return props.task?.latest_revision_note
        || props.initialData?.isletme_notu
        || props.previousTaskNotes?.find(n => n.comment)?.comment
        || null;
});

const revisionUser = computed(() => {
    return props.task?.latest_revision_user
        || props.previousTaskNotes?.find(n => n.comment)?.author
        || null;
});

const isTerminRequired = computed(() => {
    return !isRevision.value && !!(props.nodeData?.data?.requireTermin || props.nodeData?.data?.require_termin);
});

const isRequiredFieldOnApprove = computed(() => {
    return !!(props.nodeData?.data?.requireFieldOnApprove && props.nodeData?.data?.requiredFieldName);
});

const form = useForm({
    task_action: '',
    comment: '',
    answers: {
        isletme_karari: props.initialData?.isletme_karari || 'Kabul Edildi',
        termin_araligi: props.initialData?.termin_araligi || '',
        isletme_notu: props.initialData?.isletme_notu || '',
        ...props.prefilledFormData,
    },
});

// --- Termin Aralığı (Tarih Seçimi ve Çoklu Aralık Yönetimi) ---
const parseDateToISO = (str) => {
    if (!str) return '';
    str = String(str).trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(str)) return str;
    const parts = str.split(/[./-]/);
    if (parts.length === 3) {
        if (parts[0].length === 4) return `${parts[0]}-${parts[1].padStart(2, '0')}-${parts[2].padStart(2, '0')}`;
        if (parts[2].length === 4) return `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`;
    }
    return '';
};

const formatDateToTR = (isoStr) => {
    if (!isoStr) return '';
    const parts = String(isoStr).split('-');
    if (parts.length === 3 && parts[0].length === 4) {
        return `${parts[2]}.${parts[1]}.${parts[0]}`;
    }
    return isoStr;
};

const terminRanges = ref([
    { start: '', end: '', note: '' }
]);

const initTerminRanges = () => {
    const rawRanges = props.initialData?.termin_ranges;
    if (Array.isArray(rawRanges) && rawRanges.length > 0) {
        terminRanges.value = rawRanges.map(r => ({
            start: parseDateToISO(r.start),
            end: parseDateToISO(r.end),
            note: r.note || ''
        }));
        return;
    }

    const rawStr = props.initialData?.termin_araligi || form.answers.termin_araligi;
    if (typeof rawStr === 'string' && rawStr.trim()) {
        const str = rawStr.trim();
        const match = str.match(/(\d{2,4}[-./]\d{2}[-./]\d{2,4})\s*-\s*(\d{2,4}[-./]\d{2}[-./]\d{2,4})/);
        if (match) {
            terminRanges.value = [{
                start: parseDateToISO(match[1]),
                end: parseDateToISO(match[2]),
                note: ''
            }];
            return;
        }
    }
};

const addTerminRange = () => {
    let nextStart = '';
    const lastRange = terminRanges.value[terminRanges.value.length - 1];
    if (lastRange && lastRange.end) {
        try {
            const d = new Date(lastRange.end);
            d.setDate(d.getDate() + 1);
            nextStart = d.toISOString().split('T')[0];
        } catch {
            nextStart = '';
        }
    }
    terminRanges.value.push({ start: nextStart, end: '', note: '' });
};

const removeTerminRange = (index) => {
    if (terminRanges.value.length > 1) {
        terminRanges.value.splice(index, 1);
    } else {
        terminRanges.value[0] = { start: '', end: '', note: '' };
    }
    syncTerminString();
};

const applyQuickTermin = (days) => {
    const today = new Date();
    const startDate = today.toISOString().split('T')[0];
    const target = new Date();
    target.setDate(today.getDate() + days);
    const endDate = target.toISOString().split('T')[0];

    if (terminRanges.value.length === 0) {
        terminRanges.value.push({ start: startDate, end: endDate, note: '' });
    } else {
        terminRanges.value[0].start = startDate;
        terminRanges.value[0].end = endDate;
    }
    syncTerminString();
};

const calculatedTerminDays = computed(() => {
    const first = terminRanges.value[0];
    if (!first?.start || !first?.end) return null;
    const s = new Date(first.start);
    const e = new Date(first.end);
    const diff = Math.round((e - s) / (1000 * 60 * 60 * 24));
    return diff > 0 ? diff : null;
});

const syncTerminString = () => {
    const validRanges = terminRanges.value.filter(r => r.start || r.end);
    if (validRanges.length === 0) {
        return;
    }

    if (validRanges.length === 1) {
        const r = validRanges[0];
        const s = formatDateToTR(r.start);
        const e = formatDateToTR(r.end);
        const n = r.note ? ` (${r.note})` : '';
        form.answers.termin_araligi = `${s} - ${e}${n}`;
    } else {
        const formatted = validRanges.map((r, i) => {
            const s = formatDateToTR(r.start);
            const e = formatDateToTR(r.end);
            const n = r.note ? ` (${r.note})` : '';
            return `${i + 1}. Parti: ${s} - ${e}${n}`;
        });
        form.answers.termin_araligi = formatted.join(', ');
    }
    form.answers.termin_ranges = terminRanges.value;
};

watch(terminRanges, () => {
    syncTerminString();
}, { deep: true });

// --- AKILLI GÖREV ODAKLAYICI & ADIM REHBERİ YARDIMCILARI ---
const stepGuideIcon = computed(() => {
    if (isRevision.value) return '🔄';
    if (props.task?.type === 'approval') return '⚖️';
    if (props.task?.type === 'review') return '👀';
    return '✍️';
});

const stepGuideBadge = computed(() => {
    if (isRevision.value) return 'DÜZELTME & REVİZYON ADIMI';
    if (props.task?.type === 'approval') return 'SİZİN ONAYINIZ BEKLENİYOR';
    if (props.task?.type === 'review') return 'BİLGİLENDİRME & İNCELEME';
    return 'DOLDURULACAK FORM ADIMI';
});

const stepGuideTitle = computed(() => {
    if (isRevision.value) return 'Talebi Düzeltip Tekrar Onaya Gönderiniz';
    if (props.task?.type === 'approval') {
        return props.nodeData?.label || 'İşletme Onay ve Termin Kararı';
    }
    if (props.task?.type === 'review') {
        return props.nodeData?.label || 'Süreç İnceleme & Bildirim';
    }
    return props.nodeData?.label || props.subForm?.name || 'Sorumlu Olduğunuz Form Alanlarını Doldurunuz';
});

const stepGuideDescription = computed(() => {
    if (isRevision.value) {
        return 'Amir veya yetkili tarafından revizyon istendi. İletilen revizyon gerekçesini dikkate alarak formda gerekli düzeltmeleri yapınız.';
    }
    if (props.task?.type === 'approval') {
        return 'Önceki adımlardan gelen verileri kontrol ediniz. Sayfa altındaki kabul/termin alanını doldurup Onayla veya Revize İste butonuna basınız.';
    }
    if (props.task?.type === 'review') {
        return 'Süreç kapsamında üretilen doküman ve sonuçları inceleyip işlemi tamamlayınız.';
    }
    return 'Bu aşamada yeşil parıltıyla vurgulanan bölüm sizin doldurmanız gereken alandır. Önceki adımların verileri kilitlidir.';
});

const stepGuideButtonText = computed(() => {
    if (isRevision.value) return 'Düzeltilecek Forma Git';
    if (props.task?.type === 'approval') return 'Karar & Termin Alanına Git';
    if (props.task?.type === 'review') return 'Onay Butonuna Git';
    return 'Doldurulacak Alana Odaklan';
});

const goToActiveFocus = () => {
    const target = document.getElementById('active-task-focus') || document.getElementById('action-buttons-box');
    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
};

const scrollToActiveFocus = () => {
    setTimeout(() => {
        const target = document.getElementById('active-task-focus');
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, 600);
};

onMounted(() => {
    if (props.prefilledFormData && Object.keys(props.prefilledFormData).length > 0) {
        form.answers = { ...form.answers, ...props.prefilledFormData };
    }
    initTerminRanges();
    if (form.answers.termin_araligi) {
        syncTerminString();
    }
    scrollToActiveFocus();
});

const showActionModal = ref(false);
const actionModalType = ref(''); // 'revise' | 'reject'
const actionModalReason = ref('');
const actionModalError = ref('');

const openActionModal = (type) => {
    actionModalType.value = type;
    actionModalReason.value = '';
    actionModalError.value = '';
    showActionModal.value = true;
};

const cancelActionModal = () => {
    showActionModal.value = false;
    actionModalReason.value = '';
    actionModalError.value = '';
};

const confirmActionModal = () => {
    const trimmed = (actionModalReason.value || '').trim();
    if (!trimmed) {
        actionModalError.value = actionModalType.value === 'revise'
            ? 'Revizyon gerekçesi belirtmek zorunludur! Formu başlatan kişinin neyi düzeltmesi gerektiğini yazınız.'
            : 'Ret gerekçesi belirtmek zorunludur! Açıklama yapmadan ret işlemi yapılamaz.';
        return;
    }

    form.comment = trimmed;
    form.answers.isletme_notu = trimmed;

    if (actionModalType.value === 'revise') {
        form.answers.isletme_karari = 'Revizyon İstendi';
    } else if (actionModalType.value === 'reject') {
        form.answers.isletme_karari = 'Reddedildi';
    }

    showActionModal.value = false;
    executeSubmit(actionModalType.value);
};

const executeSubmit = (actionType) => {
    if (props.task.type === 'approval' && form.answers.isletme_notu && !form.comment) {
        form.comment = form.answers.isletme_notu;
    }

    form.task_action = actionType;
    form.post(route('tasks.update', props.task.id));
};

const submitAction = (actionType) => {
    if (actionType === 'revise' || actionType === 'reject') {
        openActionModal(actionType);
        return;
    }

    if (actionType === 'approve') {
        if (!isRevision.value && form.answers.isletme_karari === 'Reddedildi') {
            const confirmChange = confirm("İşletme Kararı açılır kutuda 'Reddedildi' olarak seçilmiş. Bu talebi ONAYLAMAK istediğinize emin misiniz? (Onaylarsanız karar 'Kabul Edildi' olarak kaydedilecektir.)");
            if (!confirmChange) return;
            form.answers.isletme_karari = 'Kabul Edildi';
        }

        if (isTerminRequired.value && form.answers.isletme_karari !== 'Reddedildi') {
            syncTerminString();
            if (!form.answers.termin_araligi || !String(form.answers.termin_araligi).trim()) {
                alert('Numuneyi onaylamak için lütfen Termin Aralığı (Başlangıç ve Bitiş Tarihleri) seçiniz!');
                return;
            }
        }

        if (isRequiredFieldOnApprove.value) {
            const reqKey = props.nodeData?.data?.requiredFieldName;
            if (!form.answers[reqKey] || !String(form.answers[reqKey]).trim()) {
                alert(props.nodeData?.data?.requiredFieldErrorMessage || `'${reqKey}' alanının doldurulması zorunludur.`);
                return;
            }
        }
    }

    executeSubmit(actionType);
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
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                        <Link :href="route('dashboard')" class="hover:text-indigo-600 transition-colors flex items-center gap-1">
                            <span>🏠</span> Ana Sayfa
                        </Link>
                        <span>/</span>
                        <Link :href="route('tasks.index')" class="hover:text-indigo-600 transition-colors">
                            📥 İş Listem
                        </Link>
                        <span>/</span>
                        <span class="text-gray-700 font-semibold">Görev #{{ task.id }}</span>
                    </div>

                    <h2 class="font-extrabold text-2xl text-gray-900 leading-tight flex items-center gap-2">
                        <span>{{ task.process_instance.workflow.name }}</span>
                        <span class="text-gray-400 font-normal">·</span>
                        <span v-if="isRevision" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-orange-100 text-orange-800 text-sm font-black border border-orange-200">
                            <span>🔄</span> Talebi Revize Et ve Yeniden Gönder
                        </span>
                        <span v-else class="text-indigo-600">{{ nodeData?.label || 'Görev' }}</span>
                    </h2>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <!-- Yan Yana Belge / Fatura Önizleme Butonu (Item C) -->
                    <button
                        v-if="documents && documents.length > 0"
                        type="button"
                        @click="toggleSplitView"
                        :class="[
                            'inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold transition-all shadow-sm cursor-pointer',
                            isSplitViewOpen 
                                ? 'bg-purple-600 text-white shadow-purple-200' 
                                : 'bg-purple-50 text-purple-700 border border-purple-200 hover:bg-purple-100'
                        ]"
                        title="Faturayı veya eki yan panelde açarak formu aynı anda doldurun"
                    >
                        <span>📄</span>
                        <span>{{ isSplitViewOpen ? 'Tek Panele Dön' : 'Yan Yana Belge Önizle' }}</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="isSplitViewOpen ? 'bg-purple-800 text-white' : 'bg-purple-200 text-purple-800'">
                            {{ documents.length }}
                        </span>
                    </button>

                    <Link
                        :href="route('processes.tracker', task.process_instance_id)"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 hover:bg-indigo-100 shadow-sm transition-colors"
                        title="Bu sürecin genel ilerleyişini ve şemasını gör"
                    >
                        <span>🔍</span> Süreç Akışını Gör
                    </Link>

                    <Link
                        :href="route('tasks.index')"
                        class="inline-flex items-center gap-1 px-3.5 py-2 rounded-xl text-xs font-semibold text-gray-600 bg-white border border-gray-200 hover:bg-gray-50 shadow-sm transition-colors"
                    >
                        <span>&larr;</span> İş Listem
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div :class="[isSplitViewOpen ? 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8' : 'max-w-4xl mx-auto sm:px-6 lg:px-8']">
                <div :class="[isSplitViewOpen ? 'lg:grid lg:grid-cols-12 lg:gap-8 items-start' : 'space-y-6']">
                    
                    <!-- SOL KOLON (FORM VE ONAY İŞLEMLERİ) -->
                    <div :class="[isSplitViewOpen ? 'lg:col-span-7 space-y-6' : 'space-y-6']">

                        <!-- KURUMSAL BÜTÇE & MASRAF MERKEZİ KONTROL PANELİ (Item D) -->
                        <div 
                            v-if="budgetControl && budgetControl.has_budget_impact" 
                            class="rounded-2xl border p-4.5 shadow-sm transition-all"
                            :class="[
                                budgetControl.status === 'critical' 
                                    ? 'bg-rose-50/70 border-rose-200' 
                                    : (budgetControl.status === 'warning' 
                                        ? 'bg-amber-50/70 border-amber-200' 
                                        : 'bg-emerald-50/70 border-emerald-200')
                            ]"
                        >
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2.5">
                                    <div :class="['w-9 h-9 rounded-xl flex items-center justify-center text-lg shadow-xs', budgetControl.badge_color]">
                                        💳
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-bold text-sm text-gray-900">Kurumsal Bütçe & Masraf Merkezi Kontrolü</h4>
                                            <span :class="['text-[11px] font-extrabold px-2.5 py-0.5 rounded-full border', budgetControl.badge_color]">
                                                {{ budgetControl.status_label }}
                                            </span>
                                        </div>
                                        <span class="text-xs text-gray-500">
                                            Masraf Merkezi: <strong>{{ budgetControl.cost_center }}</strong> · Dönem: {{ budgetControl.fiscal_period }}
                                        </span>
                                    </div>
                                </div>

                                <div class="text-right">
                                    <span class="text-xs text-gray-500 block">Talep Edilen Tutar</span>
                                    <span class="text-base font-black text-gray-900">{{ budgetControl.amount_formatted }}</span>
                                </div>
                            </div>

                            <!-- Progress Bar -->
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between text-xs font-semibold text-gray-700">
                                    <span>Dönem Bütçesi: {{ budgetControl.allocated_budget_formatted }}</span>
                                    <span>Yeni Tüketim Oranı: %{{ budgetControl.consumption_rate }}</span>
                                </div>
                                <div class="w-full bg-gray-200/80 rounded-full h-3 overflow-hidden shadow-inner flex">
                                    <div 
                                        :class="budgetControl.bar_color" 
                                        class="h-full rounded-full transition-all duration-500"
                                        :style="{ width: Math.min(100, budgetControl.consumption_rate) + '%' }"
                                    ></div>
                                </div>
                                <div class="flex items-center justify-between text-[11px] text-gray-500 pt-0.5">
                                    <span>Önceki Harcama: {{ budgetControl.base_consumed_formatted }} (%{{ budgetControl.previous_rate }})</span>
                                    <span>Kalan Kullanılabilir: <strong class="text-gray-800">{{ budgetControl.remaining_budget_formatted }}</strong></span>
                                </div>
                            </div>
                        </div>

                <!-- AKILLI GÖREV ODAKLAYICI & ADIM REHBERİ (SMART TASK SPOTLIGHT BANNER) -->
                <div v-if="task.status === 'pending'" class="relative overflow-hidden rounded-2xl border border-indigo-200/80 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-5 text-white shadow-xl shadow-indigo-950/20">
                    <!-- Ambient Glow Efektleri -->
                    <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-emerald-500/20 blur-2xl pointer-events-none"></div>
                    <div class="absolute -left-10 -bottom-10 h-40 w-40 rounded-full bg-indigo-500/20 blur-2xl pointer-events-none"></div>

                    <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex items-start gap-3.5">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-teal-500 text-2xl shadow-lg shadow-emerald-500/30">
                                {{ stepGuideIcon }}
                            </div>
                            <div>
                                <div class="flex items-center gap-2 mb-1 flex-wrap">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-black tracking-wider uppercase bg-emerald-500/20 text-emerald-300 border border-emerald-400/30">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
                                        {{ stepGuideBadge }}
                                    </span>
                                    <span class="text-xs text-slate-300">
                                        Sorumlu Kişi / Rol: <strong class="text-white">{{ task.assigned_user?.name || task.assigned_role || 'Siz' }}</strong>
                                    </span>
                                </div>
                                <h3 class="text-base font-extrabold text-white">
                                    {{ stepGuideTitle }}
                                </h3>
                                <p class="text-xs text-slate-300 mt-0.5 leading-relaxed max-w-2xl">
                                    {{ stepGuideDescription }}
                                </p>
                            </div>
                        </div>

                        <div class="shrink-0 flex items-center gap-2">
                            <button
                                type="button"
                                @click="goToActiveFocus"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs shadow-lg shadow-emerald-500/25 transition-all transform hover:-translate-y-0.5 cursor-pointer"
                            >
                                <span>🎯</span>
                                <span>{{ stepGuideButtonText }}</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- REVİZYON BİLGİLENDİRME VE GEREKÇE KARTI -->
                <div v-if="isRevision" class="rounded-2xl border-2 border-orange-400 bg-gradient-to-br from-amber-50 via-orange-50 to-amber-100/60 p-6 shadow-md">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-orange-500 text-white flex items-center justify-center text-2xl shrink-0 shadow-inner">
                            🔄
                        </div>
                        <div class="flex-1">
                            <div class="inline-flex items-center gap-2 px-3 py-0.5 rounded-full bg-orange-200 text-orange-900 text-xs font-black uppercase mb-1">
                                <span class="w-2 h-2 rounded-full bg-orange-600 animate-ping"></span>
                                DÜZELTME & REVİZYON TALEBİ
                            </div>
                            <h3 class="text-lg font-black text-orange-950 mt-1">
                                Bu Talep İçin Yetkili Tarafından Revizyon İstendi
                            </h3>
                            <p class="text-sm text-orange-900 mt-1 leading-relaxed">
                                Formdaki bilgileri kontrol edip eksik veya düzeltilmesi gereken alanları güncelleyiniz. Ardından sayfa altındaki <strong>"Revize Edildi, Tekrar Onaya Gönder"</strong> butonuna basınız.
                            </p>
                            <div v-if="revisionNote" class="mt-4 p-4 rounded-xl bg-white border border-orange-300 shadow-sm">
                                <div class="text-xs font-bold text-orange-800 uppercase tracking-wider mb-1 flex items-center gap-1">
                                    <span>📌</span> İletilen Revizyon Gerekçesi / Notu:
                                </div>
                                <div class="text-sm font-semibold text-gray-900 italic">
                                    "{{ revisionNote }}"
                                </div>
                                <div v-if="revisionUser" class="text-xs text-gray-500 mt-1.5 flex items-center gap-1">
                                    <span>👤</span> Yetkili: <strong>{{ revisionUser }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ALAN BAZLI DEĞİŞİKLİK GÜNLÜĞÜ BİLGİLENDİRMESİ (FIELD DIFF BADGES) -->
                <div v-if="fieldAudits && fieldAudits.length > 0" class="rounded-2xl border border-indigo-200 bg-gradient-to-r from-indigo-50 via-white to-purple-50 p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-base">🔍</span>
                            <h4 class="text-sm font-extrabold text-indigo-950">
                                Bu Talebe Ait Son Değişiklikler (Audit Diff)
                            </h4>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-indigo-100 text-indigo-700">
                            {{ fieldAudits.length }} Değişiklik
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div
                            v-for="audit in fieldAudits.slice(0, 4)"
                            :key="audit.id"
                            class="p-2.5 rounded-xl bg-white border border-indigo-100 text-xs shadow-xs"
                        >
                            <div class="flex items-center justify-between font-bold text-gray-800 mb-1">
                                <span class="truncate">{{ audit.field_label || audit.field_key }}</span>
                                <span class="text-[10px] text-gray-400 font-normal">{{ formatDate(audit.created_at) }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 font-mono text-[11px]">
                                <span class="line-through text-red-600 truncate max-w-[120px]">{{ audit.old_value || '(Boş)' }}</span>
                                <span class="text-gray-400">➔</span>
                                <span class="text-emerald-700 font-bold truncate max-w-[140px]">{{ audit.new_value || '(Boş)' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

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
                            :task-id="task.id"
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
                                :task-id="task.id"
                                :is-revision="isRevision"
                            />
                        </div>

                        <!-- İşletme Kabul & Termin Bildirimi Bölümü (requireTermin aktifse) -->
                        <div
                            v-if="task.status === 'pending' && isTerminRequired"
                            id="active-task-focus"
                            class="mb-6 rounded-2xl border-2 border-emerald-500 bg-gradient-to-br from-emerald-50/50 via-amber-50/30 to-white p-6 shadow-xl ring-4 ring-emerald-500/20 shadow-emerald-500/10 transition-all duration-300"
                        >
                            <!-- Spotlight Rozeti -->
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-600 text-white text-xs font-black shadow-md shadow-emerald-600/25 mb-4 animate-pulse">
                                <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
                                <span>✨ SİZİN DEĞERLENDİRMENİZ VE TERMİN SEÇMENİZ GEREKEN BÖLÜM</span>
                            </div>

                            <div class="flex items-center gap-3 mb-4 border-b border-amber-200/80 pb-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500 text-white shadow-sm">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </div>
                                <div>
                                    <h4 class="text-base font-bold text-amber-950">İşletme Kabul ve Termin Bildirimi</h4>
                                    <p class="text-xs text-amber-800">Numuneyi onaylayabilmek için termin aralığı ve değerlendirmenizi giriniz.</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1.5">
                                        İşletme Değerlendirme Kararı <span class="text-red-500">*</span>
                                    </label>
                                    <select 
                                        v-model="form.answers.isletme_karari" 
                                        class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 bg-white"
                                    >
                                        <option value="Kabul Edildi">Kabul Edildi</option>
                                        <option value="Şartlı Kabul">Şartlı Kabul</option>
                                        <option value="Reddedildi">Reddedildi</option>
                                    </select>
                                </div>

                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide">
                                            Termin Aralığı / Tahmini Teslim <span v-if="form.answers.isletme_karari !== 'Reddedildi'" class="text-red-500">*</span>
                                        </label>
                                        <button
                                            v-if="form.answers.isletme_karari !== 'Reddedildi'"
                                            type="button"
                                            @click="addTerminRange"
                                            class="text-xs font-bold text-amber-800 hover:text-amber-900 bg-amber-200/80 hover:bg-amber-300/80 px-2 py-0.5 rounded transition-colors flex items-center gap-1 shadow-xs"
                                        >
                                            <span>+</span> Çoklu Termin / Aralık Ekle
                                        </button>
                                    </div>

                                    <!-- Çoklu / Tekli Tarih Seçim Listesi -->
                                    <div class="space-y-2.5">
                                        <div
                                            v-for="(range, rIdx) in terminRanges"
                                            :key="rIdx"
                                            class="p-2.5 rounded-lg border border-amber-200 bg-white shadow-xs relative"
                                        >
                                            <div class="flex items-center justify-between mb-1.5" v-if="terminRanges.length > 1">
                                                <span class="text-[11px] font-bold text-amber-900 bg-amber-100 px-2 py-0.5 rounded">
                                                    {{ rIdx + 1 }}. Termin Aralığı (Parti {{ rIdx + 1 }})
                                                </span>
                                                <button
                                                    type="button"
                                                    @click="removeTerminRange(rIdx)"
                                                    class="text-xs text-red-500 hover:text-red-700 font-bold px-1.5 py-0.5 rounded hover:bg-red-50"
                                                    title="Bu aralığı kaldır"
                                                >
                                                    ✕ Sil
                                                </button>
                                            </div>

                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                <div class="relative">
                                                    <span class="text-[10px] uppercase font-bold text-gray-500 absolute -top-2 left-2 bg-white px-1 rounded shadow-xs z-10">Başlangıç</span>
                                                    <input
                                                        type="date"
                                                        v-model="range.start"
                                                        :disabled="form.answers.isletme_karari === 'Reddedildi'"
                                                        class="w-full text-xs rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 bg-white disabled:bg-gray-100"
                                                    />
                                                </div>
                                                <div class="relative">
                                                    <span class="text-[10px] uppercase font-bold text-gray-500 absolute -top-2 left-2 bg-white px-1 rounded shadow-xs z-10">Bitiş</span>
                                                    <input
                                                        type="date"
                                                        v-model="range.end"
                                                        :disabled="form.answers.isletme_karari === 'Reddedildi'"
                                                        class="w-full text-xs rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 bg-white disabled:bg-gray-100"
                                                    />
                                                </div>
                                            </div>

                                            <!-- Opsiyonel Açıklama / Parti Notu -->
                                            <div class="mt-2" v-if="terminRanges.length > 1 || range.note">
                                                <input
                                                    type="text"
                                                    v-model="range.note"
                                                    placeholder="Örn: 1. Parti 500 kg teslimatı..."
                                                    :disabled="form.answers.isletme_karari === 'Reddedildi'"
                                                    class="w-full text-[11px] rounded-md border-gray-200 shadow-sm focus:border-amber-500 focus:ring-amber-500 placeholder-gray-400 py-1"
                                                />
                                            </div>
                                        </div>

                                        <!-- Hızlı Süre Ön Ayarları (Presets) -->
                                        <div v-if="form.answers.isletme_karari !== 'Reddedildi'" class="flex items-center gap-1.5 flex-wrap pt-0.5 text-[11px] text-gray-500">
                                            <span class="font-medium">Hızlı Süre:</span>
                                            <button
                                                type="button"
                                                @click="applyQuickTermin(7)"
                                                class="px-2 py-0.5 rounded bg-amber-100/70 border border-amber-300 text-amber-900 hover:bg-amber-200 font-semibold"
                                            >
                                                +7 Gün (1 Hafta)
                                            </button>
                                            <button
                                                type="button"
                                                @click="applyQuickTermin(14)"
                                                class="px-2 py-0.5 rounded bg-amber-100/70 border border-amber-300 text-amber-900 hover:bg-amber-200 font-semibold"
                                            >
                                                +14 Gün (2 Hafta)
                                            </button>
                                            <button
                                                type="button"
                                                @click="applyQuickTermin(21)"
                                                class="px-2 py-0.5 rounded bg-amber-100/70 border border-amber-300 text-amber-900 hover:bg-amber-200 font-semibold"
                                            >
                                                +21 Gün (3 Hafta)
                                            </button>
                                            <button
                                                type="button"
                                                @click="applyQuickTermin(30)"
                                                class="px-2 py-0.5 rounded bg-amber-100/70 border border-amber-300 text-amber-900 hover:bg-amber-200 font-semibold"
                                            >
                                                +30 Gün (1 Ay)
                                            </button>
                                        </div>

                                        <!-- Belirlenen Özet Metin & Gün Sayacı -->
                                        <div v-if="form.answers.termin_araligi && form.answers.isletme_karari !== 'Reddedildi'" class="text-xs text-amber-950 font-bold bg-amber-100/80 p-2 rounded-lg border border-amber-300 flex items-center justify-between">
                                            <div class="flex items-center gap-1.5 truncate">
                                                <span>📆</span>
                                                <span class="truncate">Belirlenen Termin: {{ form.answers.termin_araligi }}</span>
                                            </div>
                                            <span v-if="calculatedTerminDays" class="text-[11px] bg-amber-600 text-white px-2 py-0.5 rounded-full font-extrabold shrink-0 ml-2">
                                                {{ calculatedTerminDays }} Gün
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1.5">
                                        İşletme Notu / Planlama Açıklaması
                                    </label>
                                    <input 
                                        type="text" 
                                        v-model="form.answers.isletme_notu" 
                                        placeholder="Örn: Betapak kalıp ölçülerine uygundur, üretim planına alındı." 
                                        class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 bg-white"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Koşullu Alan Girişi (requireFieldOnApprove aktifse) -->
                        <div v-if="task.status === 'pending' && isRequiredFieldOnApprove" class="mb-6 rounded-xl border border-indigo-200 bg-indigo-50/70 p-5 shadow-sm">
                            <label class="block text-xs font-bold text-indigo-950 uppercase tracking-wide mb-1.5">
                                {{ nodeData?.data?.requiredFieldName }} <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                v-model="form.answers[nodeData.data.requiredFieldName]" 
                                :placeholder="nodeData?.data?.requiredFieldErrorMessage || `${nodeData.data.requiredFieldName} değerini giriniz`" 
                                class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-white"
                                required
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
                        <div v-if="task.status === 'pending'" id="action-buttons-box" class="flex flex-wrap gap-4 justify-end border-t pt-4 mt-6">
                            
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
                                <button
                                    type="button"
                                    @click="submitAction('approve')"
                                    :disabled="form.processing"
                                    :class="[
                                        'font-black py-2.5 px-8 rounded-xl shadow-lg transition-all flex items-center gap-2',
                                        isRevision
                                            ? 'bg-gradient-to-r from-orange-500 to-amber-600 hover:from-orange-600 hover:to-amber-700 text-white shadow-orange-500/25 transform hover:-translate-y-0.5'
                                            : 'bg-blue-600 hover:bg-blue-700 text-white'
                                    ]"
                                >
                                    <span v-if="isRevision">🔄</span>
                                    <span>{{ form.processing ? 'Gönderiliyor...' : (isRevision ? 'Revize Edildi, Tekrar Onaya Gönder' : 'Formu Gönder / Tamamla') }}</span>
                                </button>
                            </template>

                            <!-- Onay Mekanizması -->
                            <template v-else-if="task.type === 'approval'">
                                <button type="button" @click="submitAction('revise')" :disabled="form.processing" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-6 rounded shadow flex items-center gap-1.5" title="Formu başlatan kişiye düzeltmesi için geri gönderir">
                                    <span>🔄</span> Revize İste (Geri Gönder)
                                </button>

                                <button type="button" @click="submitAction('reject')" :disabled="form.processing" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded shadow flex items-center gap-1.5" title="Akışta Red çıkışının bağlı olduğu adıma gönderir">
                                    <span>⛔</span> Reddet
                                </button>

                                <button type="button" @click="submitAction('approve')" :disabled="form.processing" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-8 rounded shadow flex items-center gap-1.5" title="Bir sonraki adıma geçirir">
                                    <span>✅</span> Onayla
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
                                    <span v-else-if="task.status === 'revised'" class="text-orange-600 font-bold">Revize İstediniz (Geri Gönderdiniz)</span>.
                                </p>
                                <p v-if="task.comment" class="text-sm italic text-gray-500">Notunuz: "{{ task.comment }}"</p>
                                <div v-if="initialData?.termin_araligi && task.status === 'completed'" class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-amber-300 bg-amber-50 text-xs font-semibold text-amber-900">
                                    <span>📅 Bildirilen Termin: <strong>{{ initialData.termin_araligi }}</strong></span>
                                    <span v-if="initialData?.isletme_karari" class="text-amber-700">| Karar: {{ initialData.isletme_karari }}</span>
                                </div>
                                <div v-else-if="task.status === 'rejected'" class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-red-300 bg-red-50 text-xs font-semibold text-red-900">
                                    <span>⛔ Karar: <strong>Reddedildi</strong></span>
                                </div>
                                <div v-else-if="task.status === 'revised'" class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-orange-300 bg-orange-50 text-xs font-semibold text-orange-900">
                                    <span>🔄 Karar: <strong>Revizyon İstendi (Başlangıç Formuna Geri Gönderildi)</strong></span>
                                </div>
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
            </div> <!-- End Sol Kolon -->

                    <!-- SAĞ KOLON: BÖLÜNMÜŞ EKRANDA CANLI BELGE / FATURA ÖNİZLEYİCİ (Item C) -->
                    <div v-if="isSplitViewOpen && documents && documents.length > 0" class="lg:col-span-5 sticky top-6 space-y-4">
                        <div class="bg-white rounded-2xl border border-purple-200 shadow-xl overflow-hidden flex flex-col">
                            <!-- Header -->
                            <div class="p-4 bg-gradient-to-r from-purple-900 via-indigo-900 to-slate-900 text-white flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-purple-500/30 flex items-center justify-center text-lg shadow-xs">
                                        📄
                                    </div>
                                    <div>
                                        <h4 class="font-extrabold text-sm leading-tight text-white">Belge & Fatura Önizleyici</h4>
                                        <span class="text-[11px] text-purple-200">Yan Yana Canlı İnceleme</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a 
                                        v-if="activeDocument" 
                                        :href="activeDocument.url" 
                                        target="_blank" 
                                        class="px-2.5 py-1 text-xs bg-white/10 hover:bg-white/20 rounded-lg text-white font-bold transition flex items-center gap-1" 
                                        title="Ayrı Sekmede Aç"
                                    >
                                        <span>↗️</span>
                                        <span>Ayrı Sekme</span>
                                    </a>
                                    <button 
                                        type="button" 
                                        @click="isSplitViewOpen = false" 
                                        class="p-1.5 text-xs bg-white/10 hover:bg-white/20 rounded-lg text-white font-bold cursor-pointer" 
                                        title="Kapat"
                                    >
                                        ✕
                                    </button>
                                </div>
                            </div>

                            <!-- Document Tabs -->
                            <div class="px-3 py-2 bg-purple-50/80 border-b border-purple-100 flex items-center gap-1.5 overflow-x-auto text-xs">
                                <button
                                    v-for="(doc, idx) in documents"
                                    :key="doc.id"
                                    type="button"
                                    @click="activeDocumentIndex = idx"
                                    :class="[
                                        'px-3 py-1.5 rounded-lg font-bold text-xs shrink-0 flex items-center gap-1.5 transition-all cursor-pointer',
                                        activeDocumentIndex === idx 
                                            ? 'bg-purple-700 text-white shadow-xs' 
                                            : 'bg-white text-purple-900 hover:bg-purple-100 border border-purple-200'
                                    ]"
                                >
                                    <span>{{ doc.icon || '📄' }}</span>
                                    <span class="truncate max-w-[130px]">{{ doc.title }}</span>
                                </button>
                            </div>

                            <!-- Document Preview Window -->
                            <div class="p-3 bg-slate-900/5 min-h-[580px] max-h-[750px] flex items-center justify-center overflow-auto">
                                <iframe 
                                    v-if="activeDocument && (activeDocument.type === 'pdf' || activeDocument.url.endsWith('.pdf'))" 
                                    :src="activeDocument.url" 
                                    class="w-full h-[640px] rounded-xl border border-slate-300 bg-white shadow-inner"
                                ></iframe>
                                <div 
                                    v-else-if="activeDocument && (activeDocument.type === 'image' || activeDocument.type === 'file')" 
                                    class="p-4 text-center w-full"
                                >
                                    <img 
                                        :src="activeDocument.url" 
                                        alt="Belge" 
                                        class="max-h-[600px] max-w-full mx-auto rounded-xl shadow-md border border-slate-200 object-contain" 
                                    />
                                </div>
                                <div v-else class="text-center py-16 text-gray-400">
                                    <span class="text-4xl block mb-2">📁</span>
                                    <p class="text-xs">Önizlenecek belge seçilmedi.</p>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="p-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                                <span class="font-medium truncate">{{ activeDocument?.title }}</span>
                                <span class="shrink-0 text-[11px] font-bold text-purple-700">{{ activeDocument?.badge }}</span>
                            </div>
                        </div>
                    </div> <!-- End Sağ Kolon -->

                </div>
            </div>
        </div>

        <!-- Revizyon ve Ret Karar Gerekçesi Modalı -->
        <div v-if="showActionModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <!-- Arka Plan Karartma (Backdrop) -->
                <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity" @click="cancelActionModal"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <!-- Modal Kutusu -->
                <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-100">
                    <!-- Başlık Şeridi -->
                    <div :class="[
                        'p-6 border-b flex items-start gap-4',
                        actionModalType === 'revise' ? 'bg-gradient-to-r from-orange-50 to-amber-50 border-orange-100' : 'bg-gradient-to-r from-red-50 to-rose-50 border-red-100'
                    ]">
                        <div :class="[
                            'w-12 h-12 rounded-xl flex items-center justify-center text-2xl shrink-0 shadow-sm',
                            actionModalType === 'revise' ? 'bg-orange-500 text-white shadow-orange-500/20' : 'bg-red-600 text-white shadow-red-600/20'
                        ]">
                            <span v-if="actionModalType === 'revise'">🔄</span>
                            <span v-else>⛔</span>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-black text-gray-900" id="modal-title">
                                {{ actionModalType === 'revise' ? 'Revizyon Talebi & Düzeltme Notu' : 'Talebi Reddetme Gerekçesi' }}
                            </h3>
                            <p class="text-xs text-gray-600 mt-1 leading-relaxed">
                                {{ actionModalType === 'revise' 
                                    ? 'Bu talep, formu başlatan satışçıya eksik veya hatalı bilgileri düzeltmesi için geri gönderilecektir. Lütfen neyin güncellenmesi gerektiğini açıkça yazınız.' 
                                    : 'Bu talep işletme tarafından kesin olarak reddedilecek ve süreç sonlandırılacaktır. Lütfen ret nedenini yazınız.' }}
                            </p>
                        </div>
                        <button type="button" @click="cancelActionModal" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg">
                            ✕
                        </button>
                    </div>

                    <!-- Gövde / Açıklama Formu -->
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1.5 flex items-center justify-between">
                                <span>{{ actionModalType === 'revise' ? 'Revizyon / Düzeltme Gerekçesi' : 'Ret Gerekçesi' }} <strong class="text-red-500">*</strong></span>
                                <span class="text-[11px] text-red-500 font-semibold">Zorunlu Alan</span>
                            </label>
                            <textarea
                                v-model="actionModalReason"
                                rows="4"
                                :placeholder="actionModalType === 'revise' 
                                    ? 'Örn: Lütfen mikron kalınlığını 550+50 toleransıyla güncelleyiniz...' 
                                    : 'Örn: Fabrikamızda bu ebatta kalıp bulunmadığı için talep karşılanamamaktadır...'"
                                :class="[
                                    'w-full text-sm rounded-xl border shadow-xs focus:ring-2 p-3 transition-colors',
                                    actionModalError 
                                        ? 'border-red-400 focus:border-red-500 focus:ring-red-200 bg-red-50/30' 
                                        : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-100'
                                ]"
                                autofocus
                            ></textarea>
                            <p v-if="actionModalError" class="text-xs text-red-600 font-bold mt-2 flex items-center gap-1.5 bg-red-50 p-2.5 rounded-lg border border-red-200">
                                <span>⚠️</span> <span>{{ actionModalError }}</span>
                            </p>
                        </div>

                        <!-- Bilgi Notu -->
                        <div v-if="actionModalType === 'revise'" class="p-3 bg-amber-50/70 border border-amber-200 rounded-xl text-xs text-amber-900 flex items-start gap-2">
                            <span class="text-base shrink-0">💡</span>
                            <span>Yazdığınız bu gerekçe, talep sahibinin (satışçı) ekranındaki turuncu panoda ve formun üzerinde aynen gösterilecektir.</span>
                        </div>
                    </div>

                    <!-- Alt Butonlar -->
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="cancelActionModal"
                            class="px-4 py-2 text-xs font-bold text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-100 transition-colors shadow-xs"
                        >
                            Vazgeç
                        </button>
                        <button
                            type="button"
                            @click="confirmActionModal"
                            :disabled="form.processing"
                            :class="[
                                'px-5 py-2.5 text-xs font-black text-white rounded-xl shadow-md transition-all flex items-center gap-1.5',
                                actionModalType === 'revise' 
                                    ? 'bg-orange-500 hover:bg-orange-600 shadow-orange-500/20 hover:shadow-lg' 
                                    : 'bg-red-600 hover:bg-red-700 shadow-red-600/20 hover:shadow-lg'
                            ]"
                        >
                            <span v-if="actionModalType === 'revise'">🔄 Revize İste ve Geri Gönder</span>
                            <span v-else>⛔ Talebi Kesin Olarak Reddet</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
