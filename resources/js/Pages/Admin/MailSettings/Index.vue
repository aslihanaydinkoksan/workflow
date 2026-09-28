<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';

const props = defineProps({
    settings: {
        type: Object,
        required: true,
    },
    events: {
        type: Array,
        required: true,
    },
    users: {
        type: Array,
        default: () => [],
    },
    roles: {
        type: Array,
        default: () => [],
    },
    departments: {
        type: Array,
        default: () => [],
    },
    availableDays: {
        type: Object,
        default: () => ({
            Monday: 'Pazartesi',
            Tuesday: 'Salı',
            Wednesday: 'Çarşamba',
            Thursday: 'Perşembe',
            Friday: 'Cuma',
            Saturday: 'Cumartesi',
            Sunday: 'Pazar',
        }),
    },
});

// ==========================================
// 1. GLOBAL GÜVENLİK AYARLARI FORMU
// ==========================================
const globalForm = useForm({
    global_kill_switch: Boolean(props.settings.global_kill_switch),
    test_mode: Boolean(props.settings.test_mode),
    test_recipient_email: props.settings.test_recipient_email || 'aslihan.aydin@koksan.com',
});

const saveGlobalSettings = () => {
    globalForm.post(route('admin.mail-settings.global.update'), {
        preserveScroll: true,
    });
};

// ==========================================
// 2. SEÇİLİ OLAY (EVENT) VE AKORDİYON DURUMU
// ==========================================
const activeEventId = ref(props.events.length > 0 ? props.events[0].id : null);
const selectedEventTab = ref('rule'); // 'rule' | 'template'

const activeEvent = computed(() => {
    return props.events.find((e) => e.id === activeEventId.value) || props.events[0] || null;
});

const selectEvent = (eventId) => {
    activeEventId.value = eventId;
    const ev = props.events.find((e) => e.id === eventId);
    if (ev) {
        initRuleForm(ev);
        initTemplateForm(ev);
    }
};

// Olay Durumu Aç/Kapa (is_active toggle)
const updatingEventStatus = ref(null);
const toggleEventStatus = (event) => {
    updatingEventStatus.value = event.id;
    router.patch(
        route('admin.mail-settings.events.status', event.id),
        { is_active: !event.is_active },
        {
            preserveScroll: true,
            onFinish: () => {
                updatingEventStatus.value = null;
            },
        }
    );
};

// ==========================================
// 3. KURAL DÜZENLEME FORMU (MailRule)
// ==========================================
const ruleForm = useForm({
    is_active: true,
    target_users: [],
    target_roles: [],
    target_departments: [],
    allowed_days: [],
    allowed_time_start: '08:00',
    allowed_time_end: '19:00',
});

const initRuleForm = (targetEvent = null) => {
    const ev = targetEvent || activeEvent.value;
    if (!ev) return;
    const rule = ev.active_rule || ev.rules?.[0] || {};
    ruleForm.is_active = rule.is_active !== undefined ? Boolean(rule.is_active) : true;
    ruleForm.target_users = Array.isArray(rule.target_users) ? [...rule.target_users] : [];
    ruleForm.target_roles = Array.isArray(rule.target_roles) ? [...rule.target_roles] : [];
    ruleForm.target_departments = Array.isArray(rule.target_departments) ? [...rule.target_departments] : [];
    ruleForm.allowed_days = Array.isArray(rule.allowed_days)
        ? [...rule.allowed_days]
        : ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    ruleForm.allowed_time_start = rule.allowed_time_start ? rule.allowed_time_start.substring(0, 5) : '08:00';
    ruleForm.allowed_time_end = rule.allowed_time_end ? rule.allowed_time_end.substring(0, 5) : '19:00';
};

const toggleDay = (dayKey) => {
    const idx = ruleForm.allowed_days.indexOf(dayKey);
    if (idx > -1) {
        ruleForm.allowed_days.splice(idx, 1);
    } else {
        ruleForm.allowed_days.push(dayKey);
    }
};

const setWeekdaysOnly = () => {
    ruleForm.allowed_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
};

const setAllDays = () => {
    ruleForm.allowed_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
};

const saveRules = () => {
    const ruleId = activeEvent.value?.active_rule?.id || activeEvent.value?.rules?.[0]?.id;
    if (!ruleId) {
        alert('Bu olay için kural kaydı bulunamadı.');
        return;
    }

    ruleForm.put(route('admin.mail-settings.rules.update', ruleId), {
        preserveScroll: true,
    });
};

// Roller seçeneklerini { id: name, name: name } formatına dönüştür
const roleOptions = computed(() => {
    return props.roles.map((r) => ({
        id: r.name,
        name: r.name,
    }));
});

// ==========================================
// 4. ŞABLON DÜZENLEME FORMU (MailTemplate)
// ==========================================
const templateForm = useForm({
    subject: '',
    body_content: '',
    is_active: true,
});

const showHtmlPreview = ref(false);

const initTemplateForm = (targetEvent = null) => {
    const ev = targetEvent || activeEvent.value;
    if (!ev) return;
    const template = ev.active_template || ev.templates?.[0] || {};
    templateForm.subject = template.subject || '';
    templateForm.body_content = template.body_content || '';
    templateForm.is_active = template.is_active !== undefined ? Boolean(template.is_active) : true;
    showHtmlPreview.value = false;
};

// İlk yükleme
initRuleForm();
initTemplateForm();

// Props güncellendiğinde (örneğin kaydetme sonrası) formları senkronize et
watch(
    () => props.events,
    (newEvents) => {
        const ev = newEvents.find((e) => e.id === activeEventId.value) || newEvents[0];
        if (ev) {
            initRuleForm(ev);
            initTemplateForm(ev);
        }
    },
    { deep: true }
);

const insertVariable = (variable) => {
    templateForm.body_content += ` ${variable} `;
};

const saveTemplate = () => {
    const templateId = activeEvent.value?.active_template?.id || activeEvent.value?.templates?.[0]?.id;
    if (!templateId) {
        alert('Bu olay için şablon kaydı bulunamadı.');
        return;
    }

    templateForm.put(route('admin.mail-settings.templates.update', templateId), {
        preserveScroll: true,
    });
};

// ==========================================
// 5. TEST E-POSTASI GÖNDERME ARACI
// ==========================================
const testForm = useForm({
    event_code: props.events.length > 0 ? props.events[0].event_code : 'task_assigned',
    recipient_email: 'gercek.personel@koksan.com',
    subject: 'Mail Guard Test Gönderimi',
});

const sendTestMail = () => {
    testForm.post(route('admin.mail-settings.test-mail'), {
        preserveScroll: true,
    });
};

// ==========================================
// 6. YENİ E-POSTA OLAYI OLUŞTURMA MODALI
// ==========================================
const showNewEventModal = ref(false);
const newEventForm = useForm({
    name: '',
    event_code: '',
    description: '',
});

// Türkçe karakterleri temizleyen otomatik kod önerisi
const slugify = (text) => {
    const trMap = {
        'ç': 'c', 'Ç': 'c',
        'ğ': 'g', 'Ğ': 'g',
        'ı': 'i', 'I': 'i', 'İ': 'i',
        'ö': 'o', 'Ö': 'o',
        'ş': 's', 'Ş': 's',
        'ü': 'u', 'Ü': 'u',
    };
    let slug = text.trim();
    for (const key in trMap) {
        slug = slug.replaceAll(key, trMap[key]);
    }
    return slug.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
};

const handleNameChange = () => {
    if (!newEventForm.event_code || newEventForm.event_code === slugify(newEventForm.name.slice(0, -1))) {
        newEventForm.event_code = slugify(newEventForm.name);
    }
};

const submitNewEvent = () => {
    newEventForm.post(route('admin.mail-settings.events.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showNewEventModal.value = false;
            newEventForm.reset();
        },
    });
};

const deleteEvent = (event) => {
    if (confirm(`"${event.name}" (${event.event_code}) e-posta olayını ve ilişkili kurallarını silmek istediğinize emin misiniz?`)) {
        router.delete(route('admin.mail-settings.events.destroy', event.id), {
            preserveScroll: true,
            onSuccess: () => {
                if (activeEventId.value === event.id && props.events.length > 0) {
                    activeEventId.value = props.events[0].id;
                }
            },
        });
    }
};
</script>

<template>
    <Head title="E-Posta Yönetimi & Kural Motoru" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-gray-900 leading-tight flex items-center gap-2">
                        <span>🛡️</span> Dinamik E-Posta Yönetimi & Kural Motoru
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Sistem e-posta akışını yönetin, global kill-switch ile test güvenliğini sağlayın ve olay bazlı mesai kurallarını yapılandırın.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span 
                        class="px-3 py-1 text-xs font-semibold rounded-full flex items-center gap-1.5 shadow-sm"
                        :class="globalForm.global_kill_switch ? 'bg-red-100 text-red-700 border border-red-200' : 'bg-green-100 text-green-700 border border-green-200'"
                    >
                        <span class="h-2 w-2 rounded-full" :class="globalForm.global_kill_switch ? 'bg-red-500 animate-pulse' : 'bg-green-500'"></span>
                        {{ globalForm.global_kill_switch ? 'E-Posta Çıkışı Kapalı (Bloklu)' : 'E-Posta Akışı Açık' }}
                    </span>
                    <span 
                        class="px-3 py-1 text-xs font-semibold rounded-full flex items-center gap-1.5 shadow-sm"
                        :class="globalForm.test_mode ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-gray-100 text-gray-600'"
                    >
                        <span>🧪</span>
                        {{ globalForm.test_mode ? 'Test Sandbox Aktif' : 'Canlı Gönderim Modu' }}
                    </span>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

                <!-- SECTION 1: GLOBAL MAIL CONTROLS (KILL SWITCH & SANDBOX) -->
                <div 
                    class="rounded-2xl p-6 shadow-sm border transition-all duration-300"
                    :class="globalForm.global_kill_switch 
                        ? 'bg-red-50/90 border-red-200 ring-2 ring-red-400/30' 
                        : globalForm.test_mode 
                            ? 'bg-amber-50/70 border-amber-200' 
                            : 'bg-white border-gray-200'"
                >
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 pb-6 border-b border-gray-200/80">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="text-2xl">{{ globalForm.global_kill_switch ? '⛔' : '⚡' }}</span>
                                <h3 class="text-lg font-bold text-gray-900">
                                    Global E-Posta Güvenlik Kalkanı & Test Modu
                                </h3>
                            </div>
                            <p class="text-sm text-gray-600 max-w-3xl">
                                Test aşamalarında gerçek personellere veya şirket gruplarına yanlışlıkla e-posta gitmesini önleyin.
                                Tüm outbound e-postalar bu merkezi kalkan tarafından anında denetlenir.
                            </p>
                        </div>

                        <!-- Büyük Kill Switch Butonu -->
                        <div class="flex items-center gap-3 shrink-0">
                            <label class="relative inline-flex items-center cursor-pointer select-none">
                                <input 
                                    type="checkbox" 
                                    v-model="globalForm.global_kill_switch" 
                                    class="sr-only peer"
                                />
                                <div 
                                    class="w-16 h-8 bg-green-500 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-7 after:transition-all peer-checked:bg-red-600 shadow-inner"
                                ></div>
                                <span class="ml-3 text-sm font-bold" :class="globalForm.global_kill_switch ? 'text-red-700' : 'text-gray-900'">
                                    {{ globalForm.global_kill_switch ? '🛑 KILL SWITCH AKTİF (Tüm Gönderimler Durduruldu)' : '🟢 E-Posta Çıkışı Serbest' }}
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Test Modu ve Hedef Alıcı Alanı -->
                    <form @submit.prevent="saveGlobalSettings" class="mt-6 grid grid-cols-1 md:grid-cols-12 gap-6 items-end">
                        <div class="md:col-span-4">
                            <div class="flex items-center justify-between bg-white/80 p-3 rounded-xl border border-gray-200 shadow-sm">
                                <div>
                                    <span class="text-sm font-bold text-gray-900 block">🧪 Test Modu (Sandbox)</span>
                                    <span class="text-xs text-gray-500">Gerçek alıcıları yoksay, test adresine yönlendir</span>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input 
                                        type="checkbox" 
                                        v-model="globalForm.test_mode" 
                                        class="sr-only peer"
                                    />
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                                </label>
                            </div>
                        </div>

                        <div class="md:col-span-6">
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1.5">
                                🎯 Test Alıcı E-Postası (Sandbox Trap)
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400 text-sm">
                                    ✉️
                                </div>
                                <input 
                                    type="email" 
                                    v-model="globalForm.test_recipient_email" 
                                    :disabled="!globalForm.test_mode"
                                    placeholder="aslihan.aydin@koksan.com" 
                                    class="w-full pl-9 pr-4 py-2.5 bg-white border border-gray-300 rounded-xl text-sm font-semibold text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-100 disabled:text-gray-400 transition"
                                />
                            </div>
                            <p class="text-xs text-gray-500 mt-1" v-if="globalForm.test_mode">
                                Test modunda tüm e-postalar bu adrese gönderilir ve konu başına <code class="bg-white/80 px-1 py-0.5 rounded text-amber-800">[TEST - To: ...]</code> eklenir.
                            </p>
                        </div>

                        <div class="md:col-span-2">
                            <button 
                                type="submit" 
                                :disabled="globalForm.processing"
                                class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-sm transition disabled:opacity-50 flex items-center justify-center gap-2"
                            >
                                <svg v-if="globalForm.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <span>Kaydet</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- SECTION 2: EVENT & RULE MANAGEMENT -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                    
                    <!-- Sol Taraf: Olay Listesi Kartları (Events List) -->
                    <div class="lg:col-span-4 space-y-3">
                        <div class="flex items-center justify-between px-1">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-700">
                                🔔 Olaylar ({{ events.length }})
                            </h3>
                            <button 
                                type="button" 
                                @click="showNewEventModal = true"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition"
                            >
                                <span>➕</span> Yeni Olay Ekle
                            </button>
                        </div>

                        <div 
                            v-for="event in events" 
                            :key="event.id"
                            @click="selectEvent(event.id)"
                            class="p-4 rounded-xl border transition-all duration-200 cursor-pointer relative"
                            :class="activeEventId === event.id 
                                ? 'bg-indigo-50/90 border-indigo-500 shadow-md ring-1 ring-indigo-500' 
                                : 'bg-white border-gray-200 hover:border-gray-300 shadow-sm'"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-sm text-gray-900">{{ event.name }}</span>
                                        <span 
                                            class="text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                                            :class="event.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                        >
                                            {{ event.is_active ? 'AKTİF' : 'PASİF' }}
                                        </span>
                                    </div>
                                    <code class="text-[11px] text-indigo-700 bg-indigo-100/60 px-1.5 py-0.5 rounded mt-1 inline-block font-mono">
                                        {{ event.event_code }}
                                    </code>
                                </div>

                                <div class="flex items-center gap-1.5 shrink-0">
                                    <!-- Hızlı Aktif/Pasif Butonu -->
                                    <button 
                                        type="button" 
                                        @click.stop="toggleEventStatus(event)"
                                        :disabled="updatingEventStatus === event.id"
                                        class="text-xs px-2.5 py-1 rounded-lg font-semibold transition border"
                                        :class="event.is_active 
                                            ? 'bg-green-50 text-green-700 border-green-200 hover:bg-green-100' 
                                            : 'bg-gray-100 text-gray-600 border-gray-200 hover:bg-gray-200'"
                                        :title="event.is_active ? 'Bu olayı kapat' : 'Bu olayı aktif et'"
                                    >
                                        {{ event.is_active ? 'Kapat' : 'Aç' }}
                                    </button>

                                    <!-- Sil Butonu -->
                                    <button 
                                        type="button" 
                                        @click.stop="deleteEvent(event)"
                                        class="text-gray-400 hover:text-red-600 p-1 rounded-lg hover:bg-red-50 transition"
                                        title="Bu olayı sil"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <p class="text-xs text-gray-500 mt-2 line-clamp-2 leading-relaxed">
                                {{ event.description || 'Açıklama girilmemiş.' }}
                            </p>

                            <!-- Mini Özet Rozetleri -->
                            <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between text-[11px] text-gray-500">
                                <span>🕒 {{ event.active_rule?.allowed_time_start?.substring(0,5) || '08:00' }} - {{ event.active_rule?.allowed_time_end?.substring(0,5) || '19:00' }}</span>
                                <span>📅 {{ event.active_rule?.allowed_days?.length || 0 }} gün</span>
                            </div>
                        </div>
                    </div>

                    <!-- Sağ Taraf: Olay Kural & Şablon Detayı (Rule & Template Editor) -->
                    <div class="lg:col-span-8 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden" v-if="activeEvent">
                        
                        <!-- Başlık & Sekmeler -->
                        <div class="p-6 border-b border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-lg font-bold text-gray-900">{{ activeEvent.name }}</h3>
                                    <code class="text-xs font-mono bg-white px-2 py-0.5 rounded border border-gray-200 text-indigo-700">
                                        {{ activeEvent.event_code }}
                                    </code>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">{{ activeEvent.description }}</p>
                            </div>

                            <!-- Sekme Butonları -->
                            <div class="flex bg-gray-200/70 p-1 rounded-xl text-xs font-bold shrink-0">
                                <button 
                                    type="button" 
                                    @click="selectedEventTab = 'rule'"
                                    class="px-4 py-1.5 rounded-lg transition"
                                    :class="selectedEventTab === 'rule' ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                                >
                                    ⏱️ Zaman & Hedef Kuralı
                                </button>
                                <button 
                                    type="button" 
                                    @click="selectedEventTab = 'template'"
                                    class="px-4 py-1.5 rounded-lg transition"
                                    :class="selectedEventTab === 'template' ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                                >
                                    📝 E-Posta Şablonu
                                </button>
                            </div>
                        </div>

                        <!-- SEKME 1: KURAL VE HEDEF KİTLE YÖNETİMİ -->
                        <div v-show="selectedEventTab === 'rule'" class="p-6 space-y-6">
                            <form @submit.prevent="saveRules" class="space-y-6">

                                <!-- Kural Aktiflik Anahtarı -->
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-100">
                                    <div>
                                        <span class="text-sm font-bold text-gray-900 block">Kural Motorunu Uygula</span>
                                        <span class="text-xs text-gray-500">Pasif yapılırsa gün ve saat kısıtlamaları uygulanmaz, her zaman gönderilir</span>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" v-model="ruleForm.is_active" class="sr-only peer" />
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                    </label>
                                </div>

                                <!-- Zaman Penceresi (Saatler) -->
                                <div class="space-y-2">
                                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700">
                                        🕒 İzin Verilen Gönderim Saat Aralığı (Mesai Penceresi)
                                    </label>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <span class="text-xs text-gray-500 mb-1 block">Başlangıç Saati</span>
                                            <input 
                                                type="time" 
                                                v-model="ruleForm.allowed_time_start"
                                                class="w-full px-3 py-2 bg-white border border-gray-300 rounded-xl text-sm font-semibold focus:ring-indigo-500 focus:border-indigo-500"
                                            />
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-500 mb-1 block">Bitiş Saati</span>
                                            <input 
                                                type="time" 
                                                v-model="ruleForm.allowed_time_end"
                                                class="w-full px-3 py-2 bg-white border border-gray-300 rounded-xl text-sm font-semibold focus:ring-indigo-500 focus:border-indigo-500"
                                            />
                                        </div>
                                    </div>
                                    <p class="text-[11px] text-gray-500">
                                        * Belirlenen saat penceresi dışında sistem bu olay için e-posta göndermez, iptal eder ve güvenle loglar (Europe/Istanbul saati).
                                    </p>
                                </div>

                                <!-- İzin Verilen Günler -->
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-700">
                                            📅 İzin Verilen Günler
                                        </label>
                                        <div class="flex gap-2">
                                            <button 
                                                type="button" 
                                                @click="setWeekdaysOnly" 
                                                class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold"
                                            >
                                                Hafta İçi
                                            </button>
                                            <span class="text-gray-300">|</span>
                                            <button 
                                                type="button" 
                                                @click="setAllDays" 
                                                class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold"
                                            >
                                                Tüm Günler
                                            </button>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2 pt-1">
                                        <button 
                                            v-for="(dayLabel, dayKey) in availableDays" 
                                            :key="dayKey"
                                            type="button"
                                            @click="toggleDay(dayKey)"
                                            class="px-3.5 py-1.5 rounded-xl text-xs font-semibold border transition"
                                            :class="ruleForm.allowed_days.includes(dayKey) 
                                                ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' 
                                                : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                                        >
                                            {{ dayLabel }}
                                        </button>
                                    </div>
                                </div>

                                <div class="border-t border-gray-100 my-4"></div>

                                <!-- Hedef Kitle Kısıtlamaları (Kullanıcı, Rol, Departman) -->
                                <div class="space-y-4">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700 flex items-center gap-1.5">
                                        <span>🎯</span> Hedef Kitle Filtreleri (Opsiyonel)
                                    </h4>
                                    <p class="text-xs text-gray-500">
                                        Boş bırakılırsa bu olay ilgili adımdaki tüm kullanıcılara ve rollere uygulanır. Belirli roller veya departmanlar seçilirse kısıtlama uygulanır.
                                    </p>

                                    <!-- Roller -->
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                                            Hedef Roller
                                        </label>
                                        <MultiSelectDropdown 
                                            v-model="ruleForm.target_roles" 
                                            :options="roleOptions"
                                            placeholder="Rol seçin veya boş bırakın..."
                                        />
                                    </div>

                                    <!-- Departmanlar -->
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                                            Hedef Departmanlar
                                        </label>
                                        <MultiSelectDropdown 
                                            v-model="ruleForm.target_departments" 
                                            :options="departments"
                                            placeholder="Departman seçin veya boş bırakın..."
                                        />
                                    </div>

                                    <!-- Kullanıcılar -->
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                                            Hedef Spesifik Kullanıcılar
                                        </label>
                                        <MultiSelectDropdown 
                                            v-model="ruleForm.target_users" 
                                            :options="users"
                                            placeholder="Kullanıcı seçin veya boş bırakın..."
                                        />
                                    </div>
                                </div>

                                <!-- Kaydet Butonu -->
                                <div class="flex justify-end pt-4 border-t border-gray-100">
                                    <button 
                                        type="submit" 
                                        :disabled="ruleForm.processing"
                                        class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl shadow-sm transition disabled:opacity-50 flex items-center gap-2"
                                    >
                                        <svg v-if="ruleForm.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                        </svg>
                                        <span>Kuralları Kaydet</span>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- SEKME 2: ŞABLON DÜZENLEME (TEMPLATE EDITOR) -->
                        <div v-show="selectedEventTab === 'template'" class="p-6 space-y-6">
                            <form @submit.prevent="saveTemplate" class="space-y-6">

                                <!-- Şablon Konusu -->
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1.5">
                                        E-Posta Konusu (Subject)
                                    </label>
                                    <input 
                                        type="text" 
                                        v-model="templateForm.subject"
                                        class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-xl text-sm font-semibold text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder="Yeni Görev: {{ workflow_name }} - {{ node_label }}"
                                    />
                                </div>

                                <!-- Değişken Kısayolları -->
                                <div>
                                    <span class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1.5">
                                        Kullanılabilir Dinamik Değişkenler (Tıklayıp Ekleyin):
                                    </span>
                                    <div class="flex flex-wrap gap-1.5">
                                        <button 
                                            type="button"
                                            v-for="v in ['{{ workflow_name }}', '{{ node_label }}', '{{ user_name }}', '{{ starter_name }}', '{{ instance_id }}', '{{ action_url }}', '{{ tracker_url }}']"
                                            :key="v"
                                            @click="insertVariable(v)"
                                            class="px-2.5 py-1 bg-gray-100 hover:bg-indigo-50 hover:text-indigo-700 text-gray-700 text-xs font-mono rounded-lg border border-gray-200 transition"
                                        >
                                            + {{ v }}
                                        </button>
                                    </div>
                                </div>

                                <!-- HTML Editör & Önizleme Sekmesi -->
                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-700">
                                            E-Posta Gövdesi (HTML İçerik)
                                        </label>
                                        <button 
                                            type="button" 
                                            @click="showHtmlPreview = !showHtmlPreview"
                                            class="text-xs font-bold text-indigo-600 hover:text-indigo-800"
                                        >
                                            {{ showHtmlPreview ? '✏️ Koda Dön' : '👁️ Canlı Önizleme' }}
                                        </button>
                                    </div>

                                    <div v-if="showHtmlPreview" class="p-6 border border-gray-200 rounded-xl bg-gray-50 min-h-[220px]">
                                        <div class="bg-white p-6 rounded-lg shadow-sm max-w-xl mx-auto border border-gray-100" v-html="templateForm.body_content"></div>
                                    </div>

                                    <textarea 
                                        v-else
                                        v-model="templateForm.body_content"
                                        rows="8"
                                        class="w-full px-4 py-3 bg-white border border-gray-300 rounded-xl text-sm font-mono text-gray-800 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 leading-relaxed"
                                        placeholder="<p>Sayın <strong>{{ user_name }}</strong>...</p>"
                                    ></textarea>
                                </div>

                                <!-- Kaydet Butonu -->
                                <div class="flex justify-end pt-4 border-t border-gray-100">
                                    <button 
                                        type="submit" 
                                        :disabled="templateForm.processing"
                                        class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl shadow-sm transition disabled:opacity-50 flex items-center gap-2"
                                    >
                                        <svg v-if="templateForm.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                        </svg>
                                        <span>Şablonu Kaydet</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: INTERACTIVE TESTING TOOL (SANDBOX TESTER) -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xl">🚀</span>
                        <h3 class="text-base font-bold text-gray-900">Anlık Güvenlik ve Kural Test Aracı</h3>
                    </div>
                    <p class="text-xs text-gray-500 mb-6">
                        Tanımladığınız kuralları ve test modu yönlendirmesini test edin. Gönderilen e-posta <code class="bg-gray-100 px-1 py-0.5 rounded text-indigo-700">MailGuardListener</code> tarafından filtrelenir.
                    </p>

                    <form @submit.prevent="sendTestMail" class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-end">
                        <div class="sm:col-span-4">
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Test Edilecek Olay</label>
                            <select 
                                v-model="testForm.event_code"
                                class="w-full px-3 py-2 bg-white border border-gray-300 rounded-xl text-sm font-semibold text-gray-900 focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option v-for="ev in events" :key="ev.event_code" :value="ev.event_code">
                                    {{ ev.name }} ({{ ev.event_code }})
                                </option>
                            </select>
                        </div>

                        <div class="sm:col-span-5">
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Simüle Edilecek Hedef Personel E-Postası</label>
                            <input 
                                type="email" 
                                v-model="testForm.recipient_email"
                                placeholder="ornek.personel@koksan.com"
                                class="w-full px-3 py-2 bg-white border border-gray-300 rounded-xl text-sm font-semibold text-gray-900 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>

                        <div class="sm:col-span-3">
                            <button 
                                type="submit" 
                                :disabled="testForm.processing"
                                class="w-full py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-sm transition disabled:opacity-50 flex items-center justify-center gap-2"
                            >
                                <svg v-if="testForm.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <span>📧 Test Gönder</span>
                            </button>
                        </div>
                    </form>

                    <!-- Simülasyon Durum Bilgisi -->
                    <div class="mt-4 p-3 bg-gray-50 rounded-xl border border-gray-100 text-xs text-gray-600 flex items-center gap-2">
                        <span>ℹ️</span>
                        <span>
                            Şu anki ayarlara göre: 
                            <strong v-if="globalForm.global_kill_switch" class="text-red-600">Kill Switch açık olduğu için e-posta iptal edilecek.</strong>
                            <strong v-else-if="globalForm.test_mode" class="text-amber-700">Test modu açık: E-posta <u>{{ globalForm.test_recipient_email }}</u> adresine "[TEST - To: {{ testForm.recipient_email }}]" başlığıyla gidecek.</strong>
                            <strong v-else class="text-emerald-700">Canlı mod: E-posta doğrudan <u>{{ testForm.recipient_email }}</u> adresine teslim edilecek.</strong>
                        </span>
                    </div>
                </div>

            </div>
        </div>

        <!-- YENİ E-POSTA OLAYI TANIMLAMA MODALI -->
        <div 
            v-if="showNewEventModal" 
            class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm"
        >
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-gray-100 space-y-5" @click.stop>
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">✨</span>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Yeni E-Posta Olayı Tanımla</h3>
                            <p class="text-xs text-gray-500">Sisteme yeni bir e-posta tetikleyicisi ekleyin</p>
                        </div>
                    </div>
                    <button @click="showNewEventModal = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg text-lg">✕</button>
                </div>

                <form @submit.prevent="submitNewEvent" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1">
                            Olay Adı <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            v-model="newEventForm.name" 
                            @input="handleNameChange"
                            placeholder="Örn: Sözleşme Vadesi Doldu Bildirimi"
                            class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-semibold focus:ring-indigo-500 focus:border-indigo-500"
                            required
                        />
                        <div v-if="newEventForm.errors.name" class="text-xs text-red-500 mt-1">{{ newEventForm.errors.name }}</div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1">
                            Sistem Olay Kodu (event_code) <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            v-model="newEventForm.event_code" 
                            placeholder="Örn: sozlesme_vadesi_doldu"
                            class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-mono focus:ring-indigo-500 focus:border-indigo-500"
                            required
                        />
                        <p class="text-[11px] text-gray-500 mt-1">
                            Kodda veya Mailable'da <code>X-Mail-Event</code> olarak geçeceğiniz benzersiz anahtar.
                        </p>
                        <div v-if="newEventForm.errors.event_code" class="text-xs text-red-500 mt-1">{{ newEventForm.errors.event_code }}</div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1">
                            Açıklama (Opsiyonel)
                        </label>
                        <textarea 
                            v-model="newEventForm.description"
                            rows="3"
                            placeholder="Bu e-postanın ne zaman ve kime gönderildiğini açıklayın..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-indigo-500 focus:border-indigo-500"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100">
                        <button 
                            type="button" 
                            @click="showNewEventModal = false"
                            class="px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100 rounded-xl transition"
                        >
                            Vazgeç
                        </button>
                        <button 
                            type="submit" 
                            :disabled="newEventForm.processing"
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-sm transition disabled:opacity-50 flex items-center gap-2"
                        >
                            <svg v-if="newEventForm.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>Olayı Oluştur</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
