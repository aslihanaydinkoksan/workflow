<script setup>
import { defineProps, defineEmits, ref, watch, computed } from 'vue';
// html2pdf importunu SİLDİK!

const props = defineProps({
    elements: {
        type: Array,
        required: true,
    },
    modelValue: {
        type: Object,
        default: () => ({}),
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    template: {
        type: Object,
        default: null,
    },
    appLogo: {
        type: String,
        default: null,
    },
    // YENİ: Dışa aktarım yapabilmemiz için Hangi Görevde olduğumuzu bilmemiz lazım
    taskId: {
        type: [Number, String],
        default: null,
    },
    // Revizyon modu: tüm alanların düzenlenmesine izin verilir
    isRevision: {
        type: Boolean,
        default: false,
    }
});

const emit = defineEmits(['update:modelValue']);
const formData = ref({ ...props.modelValue });

watch(() => props.modelValue, (newVal) => {
    if (props.disabled) {
        formData.value = { ...(newVal || {}) };
    }
}, { deep: true, immediate: true });

watch(formData, (newVal) => {
    emit('update:modelValue', newVal);
}, { deep: true });

// --- KOŞULLU ALAN GÖRÜNÜRLÜK MOTORU (CONDITIONAL LOGIC) ---
const isFieldVisible = (element) => {
    if (!element || !element.depends_on) {
        return true;
    }
    const parentVal = formData.value[element.depends_on];
    if (parentVal === undefined || parentVal === null || String(parentVal).trim() === '') {
        return false;
    }
    const parentStr = String(parentVal).toLowerCase().trim();

    if (element.show_if) {
        const targets = Array.isArray(element.show_if)
            ? element.show_if.map(s => String(s).toLowerCase().trim())
            : [String(element.show_if).toLowerCase().trim()];
        const match = targets.some(target => parentStr.includes(target));
        if (!match) return false;
    }

    if (element.show_if_not) {
        const targets = Array.isArray(element.show_if_not)
            ? element.show_if_not.map(s => String(s).toLowerCase().trim())
            : [String(element.show_if_not).toLowerCase().trim()];
        const match = targets.some(target => parentStr.includes(target));
        if (match) return false;
    }

    return true;
};

// --- OTOMATİK TARİH SÜRESİ HESAPLAYICI (Çıkış & Dönüş -> Toplam Gün) ---
watch(
    [
        () => formData.value['cikis_tarihi'] || formData.value['baslangic_tarihi'],
        () => formData.value['donus_tarihi'] || formData.value['bitis_tarihi']
    ],
    ([startDate, endDate]) => {
        if (!startDate || !endDate) return;
        const targetElement = (props.elements || []).find(e => e.id === 'toplam_gun' || e.id === 'sure_gun');
        if (!targetElement) return;

        try {
            const d1 = new Date(startDate);
            const d2 = new Date(endDate);
            if (!isNaN(d1.getTime()) && !isNaN(d2.getTime()) && d2 >= d1) {
                const diffTime = Math.abs(d2.getTime() - d1.getTime());
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                const currentVal = String(formData.value[targetElement.id] || '').trim();
                if (!currentVal || currentVal.includes('Gün')) {
                    formData.value[targetElement.id] = `${diffDays} Gün`;
                }
            }
        } catch (e) {
            console.warn("Otomatik gün hesabı hatası:", e);
        }
    }
);

// --- MASRAF & HARCAMA CANLI FORMÜL MOTORU (Financial Auto-Reconciliation) ---
watch(
    [
        () => formData.value['belgeli_harcama_toplam'],
        () => formData.value['belgesiz_harcirah_toplam'],
        () => formData.value['onceden_alinan_avans'],
        () => formData.value['belgesiz_harcirah_gun'],
        () => formData.value['gunluk_harcirah_tutari'],
    ],
    ([belgeliStr, belgesizStr, avansStr, gunStr, gunlukUcret]) => {
        // 1. Belgesiz harcırah toplamı (Gün x Günlük Ücret)
        if (gunStr && gunlukUcret) {
            const g = parseFloat(gunStr) || 0;
            const u = parseFloat(gunlukUcret) || 0;
            if (g > 0 && u > 0) {
                formData.value['belgesiz_harcirah_toplam'] = `${g * u} TL`;
            }
        }

        // Sayısal değerleri ayrıştır
        const parseAmount = (val) => {
            if (!val) return 0;
            const match = String(val).replace(/\./g, '').replace(/,/g, '.').match(/[-+]?\d*\.?\d+/);
            return match ? parseFloat(match[0]) : 0;
        };

        const b1 = parseAmount(belgeliStr);
        const b2 = parseAmount(belgesizStr || formData.value['belgesiz_harcirah_toplam']);
        const avans = parseAmount(avansStr);

        // Para birimi tespiti
        const currency = String(belgeliStr || belgesizStr || avansStr || 'TL').includes('EUR') ? 'EUR'
            : (String(belgeliStr || belgesizStr || avansStr || '').includes('USD') ? 'USD' : 'TL');

        const genelToplamElement = (props.elements || []).find(e => e.id === 'genel_toplam_masraf');
        const netIadeElement = (props.elements || []).find(e => e.id === 'odenecek_iade_net_tutar');

        if (genelToplamElement && (b1 > 0 || b2 > 0)) {
            const genelToplam = b1 + b2;
            formData.value['genel_toplam_masraf'] = `${genelToplam.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`;

            if (netIadeElement && avans > 0) {
                const net = genelToplam - avans;
                if (net < 0) {
                    formData.value['odenecek_iade_net_tutar'] = `${net.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency} (Şirkete İade Edilecek)`;
                } else if (net > 0) {
                    formData.value['odenecek_iade_net_tutar'] = `+${net.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency} (Personele Ödenecek)`;
                } else {
                    formData.value['odenecek_iade_net_tutar'] = `0.00 ${currency} (Tam Mahsup - Bakiye Sıfır)`;
                }
            }
        }
    }
);

// --- EVRENSEL VE DİNAMİK BÖLÜM & AKILLI SPOTLIGHT HESAPLAYICI ---
const parsedSections = computed(() => {
    const list = [];
    let current = {
        header: null,
        elements: [],
        totalFields: 0,
        filledFields: 0,
        requiredFields: 0,
        filledRequiredFields: 0,
        hasEditableFields: false,
    };

    (props.elements || []).forEach(el => {
        if (el.type === 'header') {
            if (current.elements.length > 0 || current.header) {
                list.push(current);
            }
            current = {
                header: el,
                elements: [],
                totalFields: 0,
                filledFields: 0,
                requiredFields: 0,
                filledRequiredFields: 0,
                hasEditableFields: false,
            };
        } else {
            current.elements.push(el);

            const visible = isFieldVisible(el);
            if (visible) {
                current.totalFields++;

                const isElDisabled = props.disabled || !!el.data_bind || !!el.is_readonly;
                if (!isElDisabled) {
                    current.hasEditableFields = true;
                }

                if (el.required) {
                    current.requiredFields++;
                }

                const val = formData.value[el.id];
                let isFilled = false;
                if (el.type === 'table' || el.type === 'repeater') {
                    isFilled = Array.isArray(val) && val.length > 0 && val.some(r => Object.values(r).some(v => v !== null && v !== undefined && String(v).trim() !== ''));
                } else {
                    isFilled = val !== undefined && val !== null && String(val).trim() !== '' && JSON.stringify(val) !== '[]' && JSON.stringify(val) !== '{}';
                }
                if (isFilled) {
                    current.filledFields++;
                    if (el.required) {
                        current.filledRequiredFields++;
                    }
                }
            }
        }
    });

    if (current.elements.length > 0 || current.header) {
        list.push(current);
    }

    if (props.disabled) {
        return list.map(s => ({ ...s, isSpotlight: false, isCompleted: true }));
    }

    // Yeni süreç başlatma formu (props.taskId yok): Tüm bölümler düzenlenebilir olmalı, kilitlenmemeli
    if (!props.taskId) {
        return list.map(s => ({
            ...s,
            isSpotlight: false,
            isCompleted: false,
        }));
    }

    // Bir bölümün "Önceden Doldurulmuş / Tamamlanmış" sayılması (Sadece sonraki görev adımlarında):
    // Required alanları varsa ve tamamı doluysa VEYA alanlarının en az %50'si doluysa
    const isSectionCompleted = (s) => {
        if (s.totalFields === 0) return true;
        if (s.requiredFields > 0) {
            return s.filledRequiredFields >= s.requiredFields;
        }
        return (s.filledFields / s.totalFields) >= 0.5;
    };

    // Hedef Spotlight Bölümünü Belirle:
    // Doldurulabilir ve henüz tamamlanmamış (boş veya eksik) İLK bölümü ara.
    // (Örn: Mustafa'nın kalite ekranında Bölüm 1, 2, 3 dolu olduğu için otomatik Bölüm 4 parlar!)
    const uncompletedSection = list.find(s => s.hasEditableFields && !isSectionCompleted(s));
    const target = uncompletedSection || list.find(s => s.hasEditableFields) || list[0];

    return list.map(s => {
        const isSpotlight = s === target;
        const isCompleted = isSectionCompleted(s) && !isSpotlight;
        return {
            ...s,
            isSpotlight,
            isCompleted,
        };
    });
});

const getOptions = (optionsStr) => {
    if (!optionsStr) return [];
    if (Array.isArray(optionsStr)) return optionsStr;
    if (typeof optionsStr === 'string') return optionsStr.split(',').map(o => o.trim());
    return [];
};

// Tarih ve Tarih Aralığı Yardımcı Fonksiyonları
const formatDisplayDate = (val) => {
    if (!val) return '-';
    try {
        const d = new Date(val);
        if (isNaN(d.getTime())) return val;
        return d.toLocaleDateString('tr-TR');
    } catch {
        return val;
    }
};

const isRangeMode = (element) => {
    return element.type === 'date_range' || element.date_mode === 'range';
};

const isFieldDisabled = (element, section = null) => {
    if (props.disabled || !!element.data_bind || !!element.is_readonly) {
        return true;
    }
    // Sadece sonraki görev adımlarında (props.taskId mevcutsa) ve revizyon modu değilse önceki tamamlanmış bölümleri korumaya al (salt-okunur)
    if (props.taskId && !props.isRevision && section && section.isCompleted && !section.isSpotlight) {
        return true;
    }
    return false;
};

const getDateRange = (elementId) => {
    const raw = formData.value[elementId];
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) {
        if (typeof raw === 'string' && raw.trim() !== '') {
            try {
                const parsed = JSON.parse(raw);
                if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                    formData.value[elementId] = parsed;
                    return formData.value[elementId];
                }
            } catch {}
            formData.value[elementId] = { start: raw, end: '', note: '' };
        } else {
            formData.value[elementId] = { start: '', end: '', note: '' };
        }
    }
    if (!formData.value[elementId].start) formData.value[elementId].start = '';
    if (!formData.value[elementId].end) formData.value[elementId].end = '';
    if (!formData.value[elementId].note) formData.value[elementId].note = '';
    return formData.value[elementId];
};

const getDateWithNote = (elementId) => {
    const raw = formData.value[elementId];
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) {
        if (typeof raw === 'string' && raw.trim() !== '') {
            try {
                const parsed = JSON.parse(raw);
                if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                    formData.value[elementId] = parsed;
                    return formData.value[elementId];
                }
            } catch {}
            formData.value[elementId] = { date: raw, note: '' };
        } else {
            formData.value[elementId] = { date: '', note: '' };
        }
    }
    if (!formData.value[elementId].date) formData.value[elementId].date = '';
    if (!formData.value[elementId].note) formData.value[elementId].note = '';
    return formData.value[elementId];
};

const getMultipleDateItems = (elementId, isRange) => {
    const raw = formData.value[elementId];
    if (!Array.isArray(raw)) {
        if (raw && typeof raw === 'object') {
            formData.value[elementId] = [raw];
        } else if (typeof raw === 'string' && raw.trim() !== '') {
            try {
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) {
                    formData.value[elementId] = parsed;
                } else if (parsed && typeof parsed === 'object') {
                    formData.value[elementId] = [parsed];
                } else {
                    formData.value[elementId] = [isRange ? { start: raw, end: '', note: '' } : { date: raw, note: '' }];
                }
            } catch {
                formData.value[elementId] = [isRange ? { start: raw, end: '', note: '' } : { date: raw, note: '' }];
            }
        } else {
            formData.value[elementId] = [];
        }
    }
    if (!props.disabled && formData.value[elementId].length === 0) {
        formData.value[elementId].push(isRange ? { start: '', end: '', note: '' } : { date: '', note: '' });
    }
    return formData.value[elementId];
};

const addMultipleDateItem = (elementId, isRange) => {
    if (!Array.isArray(formData.value[elementId])) {
        formData.value[elementId] = [];
    }
    formData.value[elementId].push(isRange ? { start: '', end: '', note: '' } : { date: '', note: '' });
};

const removeMultipleDateItem = (elementId, index) => {
    if (Array.isArray(formData.value[elementId]) && formData.value[elementId].length > 1) {
        formData.value[elementId].splice(index, 1);
    }
};

// --- DİNAMİK TABLO & REPEATER GRID MOTORU ---
const getTableRows = (element) => {
    let raw = formData.value[element.id];
    if (typeof raw === 'string' && raw.trim() !== '') {
        try {
            raw = JSON.parse(raw);
            formData.value[element.id] = raw;
        } catch {}
    }
    if (!Array.isArray(raw)) {
        const initialRow = {};
        (element.columns || []).forEach(col => {
            initialRow[col.id] = col.default !== undefined ? col.default : (col.type === 'number' ? null : '');
        });
        formData.value[element.id] = [initialRow];
    }
    if (!props.disabled && formData.value[element.id].length === 0) {
        const initialRow = {};
        (element.columns || []).forEach(col => {
            initialRow[col.id] = col.default !== undefined ? col.default : (col.type === 'number' ? null : '');
        });
        formData.value[element.id].push(initialRow);
    }
    return formData.value[element.id];
};

const addTableRow = (element) => {
    const rows = getTableRows(element);
    const newRow = {};
    (element.columns || []).forEach(col => {
        newRow[col.id] = col.default !== undefined ? col.default : (col.type === 'number' ? null : '');
    });
    rows.push(newRow);
    onTableRowChange(element, rows.length - 1);
};

const removeTableRow = (element, rIdx) => {
    const rows = getTableRows(element);
    if (rows.length > 1) {
        rows.splice(rIdx, 1);
        calculateTableSummary(element);
    }
};

const onTableRowChange = (element, rIdx) => {
    const rows = getTableRows(element);
    const row = rows[rIdx];
    if (row) {
        (element.columns || []).forEach(col => {
            if (col.type === 'calculated' && col.formula) {
                row[col.id] = evaluateRowFormula(col.formula, row);
            }
        });
    }
    calculateTableSummary(element);
};

const evaluateRowFormula = (formula, row) => {
    try {
        let expr = String(formula);
        Object.keys(row).forEach(k => {
            const val = parseFloat(row[k]) || 0;
            expr = expr.replace(new RegExp(`\\b${k}\\b`, 'g'), val);
        });
        if (/^[\d\s\+\-\*\/\.\(\)]+$/.test(expr)) {
            const res = Function(`"use strict"; return (${expr})`)();
            return isNaN(res) ? 0 : Math.round(res * 100) / 100;
        }
    } catch {
        return 0;
    }
    return 0;
};

const calculateTableSummary = (element) => {
    const rows = getTableRows(element);
    let sum = 0;
    const formulaCol = (element.columns || []).find(c => c.type === 'calculated') || (element.columns || []).find(c => c.id === 'toplam' || c.id === 'tutar');
    const targetColId = formulaCol ? formulaCol.id : (element.footer_summary?.column || 'toplam');

    rows.forEach(r => {
        const val = parseFloat(r[targetColId]) || 0;
        sum += val;
    });
    sum = Math.round(sum * 100) / 100;

    if (element.footer_summary?.target_field) {
        const cur = element.currency ? ` ${element.currency}` : '';
        formData.value[element.footer_summary.target_field] = `${sum.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}${cur}`;
    }

    return sum;
};

const formatNumberOrCurrency = (val, currency = '') => {
    if (val === undefined || val === null || val === '') return '-';
    const num = parseFloat(String(val).replace(/\./g, '').replace(/,/g, '.'));
    if (isNaN(num)) return val;
    const formatted = num.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return currency ? `${formatted} ${currency}` : formatted;
};

// YENİ: ZIGGY İLE DOĞRUDAN BACKEND'E YÖNLENDİRME
const exportToPDF = () => {
    if (!props.taskId) {
        console.warn("Görev kimliği bulunamadı.");
        return;
    }
    window.open(route('tasks.export.pdf', props.taskId), '_blank');
};

const exportToExcel = () => {
    if (!props.taskId) {
        console.warn("Görev kimliği bulunamadı.");
        return;
    }
    // Excel dosyaları yeni sekme açmadan doğrudan indirme tetikler
    window.location.href = route('tasks.export.excel', props.taskId);
};
</script>

<template>
    <div class="form-renderer-wrapper w-full">
        <!-- İndirme Butonları (Sadece template varsa) -->
        <div v-if="template && taskId" class="flex justify-end space-x-3 mb-4 print:hidden">
            <button @click="exportToExcel" type="button"
                class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md shadow-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                    </path>
                </svg>
                Excel İndir
            </button>
            <button @click="exportToPDF" type="button"
                class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md shadow-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                    </path>
                </svg>
                PDF İndir
            </button>
        </div>

        <!-- A4 Container (veya normal container) -->
        <div ref="a4Document"
            :class="template ? 'bg-white shadow-xl mx-auto p-8 border border-gray-200 print:shadow-none print:border-none w-full' : ''"
            :style="template ? 'min-height: 29.7cm;' : ''">

            <!-- Köksan Doküman Başlığı (Sadece template varsa) -->
            <div v-if="template && taskId" class="mb-8 border-2 border-black w-full flex flex-col md:flex-row">
                <!-- Logo Alanı -->
                <div
                    class="border-b-2 border-black md:border-b-0 md:border-r-2 border-black p-2 flex items-center justify-center min-w-[200px]">
                    <img v-if="appLogo" :src="appLogo" alt="App Logo"
                        :style="{ width: (template.logo_width || 4.5) + 'cm', height: (template.logo_height || 1.5) + 'cm', objectFit: 'contain' }">
                    <div v-else
                        class="text-xs text-gray-400 border border-dashed border-gray-300 p-4 flex items-center justify-center text-center"
                        :style="{ width: (template.logo_width || 4.5) + 'cm', height: (template.logo_height || 1.5) + 'cm' }">
                        Logo Yok
                    </div>
                </div>

                <!-- Başlık Alanı -->
                <div
                    class="flex-1 flex flex-col items-center justify-center border-b-2 border-black md:border-b-0 md:border-r-2 border-black p-4 text-center">
                    <h1 class="text-lg md:text-xl font-bold text-black uppercase m-0">{{ template.name }}</h1>
                    <div class="w-3/4 h-[2px] bg-blue-600 mt-1 mb-1"></div>
                    <span v-if="template.description" class="text-sm text-gray-500">{{ template.description }}</span>
                </div>

                <!-- Bilgi Alanı -->
                <div class="w-full md:w-64 flex flex-col text-xs font-semibold">
                    <div class="flex border-b border-black">
                        <div class="w-1/2 p-1.5 border-r border-black">Doküman No</div>
                        <div class="w-1/2 p-1.5">{{ template.document_no || '-' }}</div>
                    </div>
                    <div class="flex border-b border-black">
                        <div class="w-1/2 p-1.5 border-r border-black">Yayın Tarihi</div>
                        <div class="w-1/2 p-1.5">{{ template.publish_date ? template.publish_date.split('T')[0] : '-' }}
                        </div>
                    </div>
                    <div class="flex border-b border-black">
                        <div class="w-1/2 p-1.5 border-r border-black">Rev. No / Tarih</div>
                        <div class="w-1/2 p-1.5">{{ template.revision_no || '0' }} / {{ template.revision_date ?
                            template.revision_date.split('T')[0] : '-' }}</div>
                    </div>
                    <div class="flex">
                        <div class="w-1/2 p-1.5 border-r border-black">Sayfa No</div>
                        <div class="w-1/2 p-1.5">{{ template.page_no || '1' }}</div>
                    </div>
                </div>
            </div>

            <!-- Form İçeriği (Akıllı Bölümler & Smart Spotlight) -->
            <div class="space-y-6">
                <div
                    v-for="(section, sIdx) in parsedSections"
                    :key="sIdx"
                    :id="section.isSpotlight ? 'active-task-focus' : null"
                    :class="[
                        'transition-all duration-300 rounded-2xl',
                        section.isSpotlight
                            ? 'p-6 bg-gradient-to-b from-emerald-50/50 via-white to-white border-2 border-emerald-500 ring-4 ring-emerald-500/20 shadow-xl shadow-emerald-500/10'
                            : (section.isCompleted && !disabled
                                ? 'p-5 bg-slate-50/70 border border-slate-200/90 shadow-2xs'
                                : (section.header ? 'p-5 bg-white border border-gray-200 shadow-2xs' : ''))
                    ]"
                >
                    <!-- Bölüm Başlığı & Spotlight Rozetleri -->
                    <div v-if="section.header" class="mb-4 pb-3 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <div v-if="section.isSpotlight" class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-600 text-white text-xs font-black shadow-md shadow-emerald-600/25 mb-1.5 animate-pulse">
                                <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
                                <span>✨ SİZİN DOLDURMANIZ GEREKEN BÖLÜM</span>
                            </div>
                            <h2 :class="['text-xl font-black', section.isSpotlight ? 'text-emerald-950 flex items-center gap-2' : 'text-gray-900']">
                                <span v-if="section.isSpotlight" class="text-xl">👉</span>
                                <span>{{ section.header.label }}</span>
                            </h2>
                            <p v-if="section.header.description" :class="['text-xs mt-0.5', section.isSpotlight ? 'text-emerald-800 font-medium' : 'text-gray-500']">
                                {{ section.header.description }}
                            </p>
                        </div>

                        <div class="shrink-0 flex items-center gap-2">
                            <span v-if="section.isSpotlight" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-emerald-100 text-emerald-800 text-xs font-bold border border-emerald-300 shadow-2xs">
                                <span>✍️</span> Lütfen bu alanları doldurunuz
                            </span>
                            <span v-else-if="section.isCompleted && !disabled" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold border border-slate-300">
                                <span>🔒</span> Önceki Adım Verisi (Salt Okunur)
                            </span>
                        </div>
                    </div>

                    <!-- Bölüm Elemanları Izgarası (Grid) -->
                    <div class="grid grid-cols-12 gap-5">
                        <div v-for="element in section.elements" :key="element.id" v-show="isFieldVisible(element)" :class="`col-span-${element.width || '12'} w-full transition-all duration-200`">
                            <div class="relative">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                            {{ element.label }} <span v-if="element.required && isFieldVisible(element)" class="text-red-500 ml-1">*</span>
                        </label>

                        <input v-if="element.type === 'text'" :id="'field_' + element.id" type="text" v-model="formData[element.id]"
                            :required="element.required && isFieldVisible(element)"
                            :disabled="isFieldDisabled(element, section)"
                            :placeholder="element.placeholder"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100 disabled:text-gray-600 disabled:cursor-not-allowed disabled:opacity-80 disabled:border-gray-200 transition-colors" />

                        <textarea v-else-if="element.type === 'textarea'" :id="'field_' + element.id" v-model="formData[element.id]"
                            :required="element.required && isFieldVisible(element)"
                            :disabled="isFieldDisabled(element, section)"
                            :placeholder="element.placeholder" rows="3"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100 disabled:text-gray-600 disabled:cursor-not-allowed disabled:opacity-80 disabled:border-gray-200 transition-colors"></textarea>

                        <input v-else-if="element.type === 'number'" :id="'field_' + element.id" type="number" v-model="formData[element.id]"
                            :required="element.required && isFieldVisible(element)"
                            :disabled="isFieldDisabled(element, section)"
                            :placeholder="element.placeholder"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100 disabled:text-gray-600 disabled:cursor-not-allowed disabled:opacity-80 disabled:border-gray-200 transition-colors" />

                        <!-- TARİH & TARİH ARALIĞI ELEMANLARI -->
                        <div v-else-if="element.type === 'date' || element.type === 'date_range'" class="space-y-2">
                            <!-- 1. BİRDEN FAZLA TARİH / TARİH ARALIĞI (ALLOW_MULTIPLE) -->
                            <div v-if="element.allow_multiple" class="space-y-2">
                                <!-- Okuma / Disabled Modu -->
                                <div v-if="isFieldDisabled(element)" class="space-y-2">
                                    <div v-if="getMultipleDateItems(element.id, isRangeMode(element)).length === 0" class="text-sm text-gray-500 italic bg-gray-50 border border-gray-200 rounded-md p-2.5">
                                        Tarih girilmedi
                                    </div>
                                    <div v-for="(item, idx) in getMultipleDateItems(element.id, isRangeMode(element))" :key="idx"
                                         class="flex flex-col sm:flex-row sm:items-center justify-between p-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-800 gap-2">
                                        <div class="flex items-center gap-2 font-medium">
                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold shrink-0">
                                                {{ idx + 1 }}
                                            </span>
                                            <template v-if="isRangeMode(element)">
                                                <span class="text-gray-700 font-medium">📅 {{ formatDisplayDate(item.start) }}</span>
                                                <span class="text-gray-400 font-bold">➔</span>
                                                <span class="text-gray-700 font-medium">📅 {{ formatDisplayDate(item.end) }}</span>
                                            </template>
                                            <template v-else>
                                                <span class="text-gray-700 font-medium">📅 {{ formatDisplayDate(item.date) }}</span>
                                            </template>
                                        </div>
                                        <div v-if="element.allow_note && item.note" class="text-xs text-gray-600 bg-white px-2.5 py-1 rounded border border-gray-200 flex items-center gap-1.5 shadow-xs">
                                            <span class="font-semibold text-gray-500">Not:</span>
                                            <span>{{ item.note }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Düzenleme / Giriş Modu -->
                                <div v-else class="space-y-2.5">
                                    <div v-for="(item, idx) in getMultipleDateItems(element.id, isRangeMode(element))" :key="idx"
                                         class="p-3 bg-gray-50 border border-gray-200 rounded-lg space-y-2 relative group hover:border-indigo-300 transition-colors">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold shrink-0">
                                                {{ idx + 1 }}
                                            </span>
                                            
                                            <!-- Tarih Aralığı Girişleri -->
                                            <template v-if="isRangeMode(element)">
                                                <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-2 items-center">
                                                    <div class="relative">
                                                        <span class="text-[10px] uppercase font-bold text-gray-500 absolute -top-2 left-2 bg-white px-1 rounded shadow-xs">Başlangıç</span>
                                                        <input type="date" v-model="item.start" :required="element.required && isFieldVisible(element) && idx === 0"
                                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs" />
                                                    </div>
                                                    <div class="relative">
                                                        <span class="text-[10px] uppercase font-bold text-gray-500 absolute -top-2 left-2 bg-white px-1 rounded shadow-xs">Bitiş</span>
                                                        <input type="date" v-model="item.end" :required="element.required && isFieldVisible(element) && idx === 0"
                                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs" />
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- Tek Tarih Girişi -->
                                            <template v-else>
                                                <div class="flex-1">
                                                    <input type="date" v-model="item.date" :required="element.required && isFieldVisible(element) && idx === 0"
                                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                                </div>
                                            </template>

                                            <!-- Silme Butonu (En az 1 tane kalabilir) -->
                                            <button v-if="getMultipleDateItems(element.id, isRangeMode(element)).length > 1"
                                                @click="removeMultipleDateItem(element.id, idx)" type="button"
                                                class="p-1.5 text-gray-400 hover:text-red-600 rounded-md hover:bg-red-50 transition-colors"
                                                title="Bu tarihi kaldır">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>

                                        <!-- Açıklama Alanı (Aktifse) -->
                                        <div v-if="element.allow_note" class="pt-1">
                                            <input type="text" v-model="item.note" placeholder="Tarih açıklaması / not ekleyin..."
                                                class="block w-full rounded-md border-gray-300 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs placeholder-gray-400" />
                                        </div>
                                    </div>

                                    <!-- Yeni Tarih Ekle Butonu -->
                                    <button @click="addMultipleDateItem(element.id, isRangeMode(element))" type="button"
                                        class="inline-flex items-center px-3 py-1.5 border border-dashed border-indigo-300 text-xs font-medium rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        {{ isRangeMode(element) ? '+ Yeni Tarih Aralığı Ekle' : '+ Yeni Tarih Ekle' }}
                                    </button>
                                </div>
                            </div>

                            <!-- 2. TEK TARİH ARALIĞI (allow_multiple = false, date_mode = 'range' veya date_range) -->
                            <div v-else-if="isRangeMode(element)" class="space-y-2">
                                <!-- Disabled Modu -->
                                <div v-if="isFieldDisabled(element)"
                                    class="flex flex-col sm:flex-row sm:items-center justify-between p-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-800 gap-2">
                                    <div class="flex items-center gap-2 font-medium">
                                        <span class="text-gray-700">📅 {{ formatDisplayDate(getDateRange(element.id).start) }}</span>
                                        <span class="text-gray-400 font-bold">➔</span>
                                        <span class="text-gray-700">📅 {{ formatDisplayDate(getDateRange(element.id).end) }}</span>
                                    </div>
                                    <div v-if="element.allow_note && getDateRange(element.id).note" class="text-xs text-gray-600 bg-white px-2.5 py-1 rounded border border-gray-200 shadow-xs">
                                        <span class="font-semibold text-gray-500">Not:</span> {{ getDateRange(element.id).note }}
                                    </div>
                                </div>

                                <!-- Editable Modu -->
                                <div v-else class="space-y-2">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 items-center">
                                        <div class="relative">
                                            <span class="text-[10px] uppercase font-bold text-gray-500 absolute -top-2 left-2 bg-white px-1 rounded shadow-xs">Başlangıç</span>
                                            <input type="date" v-model="getDateRange(element.id).start" :required="element.required && isFieldVisible(element)"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                        </div>
                                        <div class="relative">
                                            <span class="text-[10px] uppercase font-bold text-gray-500 absolute -top-2 left-2 bg-white px-1 rounded shadow-xs">Bitiş</span>
                                            <input type="date" v-model="getDateRange(element.id).end" :required="element.required && isFieldVisible(element)"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                        </div>
                                    </div>
                                    <div v-if="element.allow_note">
                                        <input type="text" v-model="getDateRange(element.id).note" placeholder="Tarih aralığı açıklaması / seyahat nedeni..."
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs placeholder-gray-400" />
                                    </div>
                                </div>
                            </div>

                            <!-- 3. TEK TARİH + AÇIKLAMA (allow_multiple = false, allow_note = true) -->
                            <div v-else-if="element.allow_note" class="space-y-2">
                                <!-- Disabled Modu -->
                                <div v-if="isFieldDisabled(element)"
                                    class="flex flex-col sm:flex-row sm:items-center justify-between p-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-800 gap-2">
                                    <div class="flex items-center gap-2 font-medium">
                                        <span class="text-gray-700">📅 {{ formatDisplayDate(getDateWithNote(element.id).date) }}</span>
                                    </div>
                                    <div v-if="getDateWithNote(element.id).note" class="text-xs text-gray-600 bg-white px-2.5 py-1 rounded border border-gray-200 shadow-xs">
                                        <span class="font-semibold text-gray-500">Not:</span> {{ getDateWithNote(element.id).note }}
                                    </div>
                                </div>

                                <!-- Editable Modu -->
                                <div v-else class="space-y-2">
                                    <input type="date" v-model="getDateWithNote(element.id).date" :required="element.required && isFieldVisible(element)"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                    <input type="text" v-model="getDateWithNote(element.id).note" placeholder="Tarih açıklaması / not ekleyin..."
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs placeholder-gray-400" />
                                </div>
                            </div>

                            <!-- 4. STANDART TEK TARİH (Klasik Geriye Dönük Uyumluluk) -->
                            <div v-else>
                                <input type="date" v-model="formData[element.id]"
                                    :required="element.required && isFieldVisible(element)"
                                    :disabled="isFieldDisabled(element, section)"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100 disabled:text-gray-600 disabled:cursor-not-allowed disabled:opacity-80 disabled:border-gray-200 transition-colors" />
                            </div>
                        </div>

                        <select v-else-if="element.type === 'select'" :id="'field_' + element.id" v-model="formData[element.id]"
                            :required="element.required && isFieldVisible(element)"
                            :disabled="isFieldDisabled(element, section)"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100 disabled:text-gray-600 disabled:cursor-not-allowed disabled:opacity-80 disabled:border-gray-200 transition-colors">
                            <option value="" disabled>Seçiniz...</option>
                            <option v-for="(opt, i) in getOptions(element.options)" :key="i"
                                :value="opt.trim ? opt.trim() : opt">{{ opt.trim ? opt.trim() : opt }}</option>
                        </select>

                        <div v-else-if="element.type === 'radio'" class="space-y-2 mt-2">
                            <div v-for="(opt, i) in getOptions(element.options)" :key="i" class="flex items-center">
                                <input type="radio" :value="opt.trim ? opt.trim() : opt" v-model="formData[element.id]"
                                    :required="element.required && isFieldVisible(element)"
                                    :disabled="isFieldDisabled(element, section)"
                                    :name="element.id"
                                    class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-80 transition-colors" />
                                <label class="ml-2 block text-sm"
                                    :class="isFieldDisabled(element, section) ? 'text-gray-500' : 'text-gray-700'">
                                    {{ opt.trim ? opt.trim() : opt }}
                                </label>
                            </div>
                        </div>

                        <div v-else-if="element.type === 'file'">
                            <input type="file" :required="element.required && isFieldVisible(element)" :disabled="isFieldDisabled(element, section)"
                                class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 disabled:opacity-50 disabled:cursor-not-allowed transition-all" />
                        </div>

                        <div v-else-if="element.type === 'checkbox'" class="flex items-center mt-2">
                            <input type="checkbox" v-model="formData[element.id]" :required="element.required && isFieldVisible(element)"
                                :disabled="isFieldDisabled(element, section)"
                                class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-80 transition-colors" />
                            <span class="ml-2 text-sm"
                                :class="isFieldDisabled(element, section) ? 'text-gray-500' : 'text-gray-700'">Evet/Onaylıyorum</span>
                        </div>

                        <!-- TABLO & DİNAMİK SATIR ELEMANI (REPEATER GRID) -->
                        <div v-else-if="element.type === 'table' || element.type === 'repeater'" class="space-y-3 w-full">
                            <!-- 1. Salt Okunur (Readonly) / Tamamlanmış Görünüm -->
                            <div v-if="isFieldDisabled(element, section)" class="overflow-x-auto rounded-xl border border-gray-200 shadow-2xs bg-white">
                                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                                    <thead class="bg-slate-100 text-slate-700 font-bold uppercase tracking-wider">
                                        <tr>
                                            <th class="px-3 py-2.5 w-10 text-center text-slate-500">#</th>
                                            <th v-for="col in element.columns" :key="col.id" class="px-3 py-2.5 text-slate-700">
                                                {{ col.label }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                        <tr v-if="getTableRows(element).length === 0">
                                            <td :colspan="(element.columns?.length || 1) + 1" class="px-4 py-4 text-center text-gray-400 italic">
                                                Kayıtlı kalem bulunamadı.
                                            </td>
                                        </tr>
                                        <tr v-for="(row, rIdx) in getTableRows(element)" :key="rIdx" class="hover:bg-slate-50/80 transition-colors">
                                            <td class="px-3 py-2 text-center font-bold text-gray-400">{{ rIdx + 1 }}</td>
                                            <td v-for="col in element.columns" :key="col.id" class="px-3 py-2 text-gray-800">
                                                <span v-if="col.type === 'select'" class="inline-flex px-2 py-0.5 rounded-md text-[11px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                                    {{ row[col.id] || '-' }}
                                                </span>
                                                <span v-else-if="col.type === 'calculated' || col.is_currency" class="font-bold text-slate-900">
                                                    {{ formatNumberOrCurrency(row[col.id], element.currency) }}
                                                </span>
                                                <span v-else>{{ row[col.id] || '-' }}</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <!-- Alt Toplam Satırı (Footer) -->
                                    <tfoot v-if="element.footer_summary && getTableRows(element).length > 0" class="bg-slate-50 font-bold border-t-2 border-slate-300">
                                        <tr>
                                            <td :colspan="element.columns.length" class="px-3 py-2.5 text-right uppercase tracking-wider text-slate-600 font-bold text-xs">
                                                {{ element.footer_summary.label || 'Genel Toplam' }}:
                                            </td>
                                            <td class="px-3 py-2.5 text-emerald-700 font-black text-sm">
                                                {{ formatNumberOrCurrency(calculateTableSummary(element), element.currency) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- 2. Düzenlenebilir (Editable) Giriş Modu -->
                            <div v-else class="space-y-3">
                                <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm bg-white">
                                    <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                                        <thead class="bg-indigo-50/70 text-indigo-900 font-bold uppercase tracking-wider">
                                            <tr>
                                                <th class="px-3 py-2.5 w-10 text-center text-indigo-600">#</th>
                                                <th v-for="col in element.columns" :key="col.id" class="px-3 py-2.5" :style="{ width: col.width || 'auto' }">
                                                    {{ col.label }} <span v-if="col.required" class="text-red-500">*</span>
                                                </th>
                                                <th class="px-3 py-2.5 w-12 text-center text-indigo-600">Sil</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr v-for="(row, rIdx) in getTableRows(element)" :key="rIdx" class="hover:bg-slate-50/60 transition-colors">
                                                <td class="px-3 py-2 text-center font-bold text-gray-400">{{ rIdx + 1 }}</td>
                                                <td v-for="col in element.columns" :key="col.id" class="px-2 py-2">
                                                    <!-- Text -->
                                                    <input v-if="col.type === 'text'" type="text" v-model="row[col.id]" @input="onTableRowChange(element, rIdx)"
                                                        :placeholder="col.placeholder || col.label"
                                                        :required="col.required && isFieldVisible(element)"
                                                        class="block w-full rounded-md border-gray-300 shadow-2xs focus:border-indigo-500 focus:ring-indigo-500 text-xs py-1.5 px-2.5" />

                                                    <!-- Number -->
                                                    <input v-else-if="col.type === 'number'" type="number" step="any" v-model.number="row[col.id]" @input="onTableRowChange(element, rIdx)"
                                                        :placeholder="col.placeholder || '0'"
                                                        :required="col.required && isFieldVisible(element)"
                                                        class="block w-full rounded-md border-gray-300 shadow-2xs focus:border-indigo-500 focus:ring-indigo-500 text-xs py-1.5 px-2.5" />

                                                    <!-- Date -->
                                                    <input v-else-if="col.type === 'date'" type="date" v-model="row[col.id]" @input="onTableRowChange(element, rIdx)"
                                                        :required="col.required && isFieldVisible(element)"
                                                        class="block w-full rounded-md border-gray-300 shadow-2xs focus:border-indigo-500 focus:ring-indigo-500 text-xs py-1.5 px-2.5" />

                                                    <!-- Select -->
                                                    <select v-else-if="col.type === 'select'" v-model="row[col.id]" @change="onTableRowChange(element, rIdx)"
                                                        :required="col.required && isFieldVisible(element)"
                                                        class="block w-full rounded-md border-gray-300 shadow-2xs focus:border-indigo-500 focus:ring-indigo-500 text-xs py-1.5 px-2.5">
                                                        <option value="" disabled>Seçiniz...</option>
                                                        <option v-for="(opt, optIdx) in getOptions(col.options)" :key="optIdx" :value="opt.trim ? opt.trim() : opt">
                                                            {{ opt.trim ? opt.trim() : opt }}
                                                        </option>
                                                    </select>

                                                    <!-- Calculated (Canlı Formül) -->
                                                    <div v-else-if="col.type === 'calculated'" class="px-2.5 py-1.5 bg-slate-100 rounded-md border border-slate-200 font-bold text-slate-800 text-xs text-right">
                                                        {{ formatNumberOrCurrency(row[col.id], element.currency) }}
                                                    </div>
                                                </td>
                                                <td class="px-2 py-2 text-center">
                                                    <button v-if="getTableRows(element).length > 1" @click="removeTableRow(element, rIdx)" type="button"
                                                        class="p-1.5 text-gray-400 hover:text-red-600 rounded-md hover:bg-red-50 transition-colors"
                                                        title="Bu satırı sil">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <!-- Footer Summary (Live) -->
                                        <tfoot v-if="element.footer_summary" class="bg-indigo-50/50 font-bold border-t border-indigo-200">
                                            <tr>
                                                <td :colspan="element.columns.length" class="px-4 py-2.5 text-right uppercase tracking-wider text-indigo-950 font-bold text-xs">
                                                    {{ element.footer_summary.label || 'Genel Toplam' }}:
                                                </td>
                                                <td colspan="2" class="px-3 py-2.5 text-emerald-700 font-black text-sm">
                                                    {{ formatNumberOrCurrency(calculateTableSummary(element), element.currency) }}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <!-- Yeni Satır Ekle Butonu & Bilgi -->
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                    <button @click="addTableRow(element)" type="button"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-50 border border-dashed border-indigo-300 text-indigo-700 hover:bg-indigo-100 text-xs font-bold shadow-2xs hover:shadow-xs transition-all">
                                        <span>➕</span> {{ element.add_button_text || '+ Yeni Kalem / Satır Ekle' }}
                                    </button>
                                    <span v-if="element.footer_summary" class="text-xs font-medium text-gray-500">
                                        Toplam Kalem Sayısı: <span class="font-bold text-gray-700">{{ getTableRows(element).length }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <p v-if="element.description && element.type !== 'header'" class="mt-1 text-xs text-gray-500">{{
                            element.description }}</p>

                        <p v-if="element.data_bind"
                            class="mt-1.5 text-[11px] text-indigo-600 font-semibold flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            Bu veri sistem profilinizden otomatik çekilmiştir.
                        </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="elements.length === 0"
                    class="col-span-12 text-sm text-gray-500 italic p-6 bg-gray-50 rounded-lg border-2 border-dashed text-center">
                    Bu form için henüz bir alan tasarlanmamış.
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Tailwind Grid sınıflarının dinamik üretildiğinde (col-span-12 vb.) silinmemesi için */
.col-span-12 {
    grid-column: span 12 / span 12;
}

.col-span-6 {
    grid-column: span 6 / span 6;
}

.col-span-4 {
    grid-column: span 4 / span 4;
}

.col-span-3 {
    grid-column: span 3 / span 3;
}
</style>
