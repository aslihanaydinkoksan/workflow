<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    settings: Object
});

const activeTab = ref('sample'); // 'sample' | 'sap' | 'brand'

const form = useForm({
    app_logo: null,
    sample_follow_up_days: props.settings.sample_follow_up_days || '30',
    sample_follow_up_max_reminders: props.settings.sample_follow_up_max_reminders || '3',
    sample_follow_up_reminder_interval_days: props.settings.sample_follow_up_reminder_interval_days || '7',
    sap_integration_enabled: props.settings.sap_integration_enabled === '1' || props.settings.sap_integration_enabled === 1 || props.settings.sap_integration_enabled === true,
    sap_api_url: props.settings.sap_api_url || '',
    sap_client: props.settings.sap_client || '100',
    sap_company_code: props.settings.sap_company_code || '1000',
    sap_sales_org: props.settings.sap_sales_org || '1000',
});

const logoPreview = ref(props.settings.app_logo || null);

const handleLogoUpload = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.app_logo = file;
        logoPreview.value = URL.createObjectURL(file);
    }
};

const submit = () => {
    form.post(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            if (form.app_logo) {
                form.app_logo = null;
            }
        }
    });
};
</script>

<template>
    <Head title="Sistem Ayarları" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-gray-900 leading-tight">⚙️ Sistem & Süreç Ayarları</h2>
                    <p class="text-sm text-gray-500 mt-1">Numune takip parametreleri, SAP entegrasyonu ve genel sistem yapılandırması</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Tab Header -->
                <div class="flex border-b border-gray-200 bg-white px-6 pt-4 rounded-t-xl shadow-sm space-x-8">
                    <button 
                        type="button"
                        @click="activeTab = 'sample'"
                        class="pb-4 px-2 text-sm font-semibold border-b-2 transition flex items-center gap-2"
                        :class="activeTab === 'sample' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    >
                        <span>🧪</span> Numune Takip (Follow-up) Ayarları
                    </button>
                    <button 
                        type="button"
                        @click="activeTab = 'sap'"
                        class="pb-4 px-2 text-sm font-semibold border-b-2 transition flex items-center gap-2"
                        :class="activeTab === 'sap' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    >
                        <span>🔄</span> SAP S/4HANA Entegrasyonu
                    </button>
                    <button 
                        type="button"
                        @click="activeTab = 'brand'"
                        class="pb-4 px-2 text-sm font-semibold border-b-2 transition flex items-center gap-2"
                        :class="activeTab === 'brand' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    >
                        <span>🎨</span> Görünüm ve Logo
                    </button>
                </div>

                <div class="bg-white overflow-hidden shadow-sm rounded-b-xl p-8 border-t-0 border border-gray-100">
                    <form @submit.prevent="submit" class="space-y-8">

                        <!-- TAB 1: NUMUNE TAKİP AYARLARI -->
                        <div v-show="activeTab === 'sample'" class="space-y-6">
                            <div class="bg-indigo-50/70 border border-indigo-100 rounded-xl p-5 mb-6">
                                <h4 class="font-bold text-indigo-900 text-base flex items-center gap-2">
                                    <span>ℹ️</span> Dinamik Numune Takip Parametreleri
                                </h4>
                                <p class="text-xs text-indigo-700 mt-1 leading-relaxed">
                                    Numune üretilip teslim edildikten sonra satış temsilcisine sorulacak "Numune siparişe dönüştü mü?" bildirimlerinin 
                                    başlangıç vadesi, hatırlatma sıklığı ve maksimum deneme sayısını buradan dilediğiniz an değiştirebilirsiniz.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="bg-gray-50/60 p-5 rounded-xl border border-gray-200/80">
                                    <label class="block text-sm font-bold text-gray-800 mb-1">
                                        ⏱️ Takip Başlangıç Süresi (Gün)
                                    </label>
                                    <span class="text-xs text-gray-500 block mb-3">
                                        Numune tamamlandıktan kaç gün sonra ilk anket/mail gönderilsin?
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <input 
                                            v-model="form.sample_follow_up_days" 
                                            type="number" 
                                            min="1" 
                                            max="365" 
                                            class="w-32 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-center font-bold text-lg"
                                        />
                                        <span class="text-sm font-semibold text-gray-600">gün</span>
                                    </div>
                                    <div v-if="form.errors.sample_follow_up_days" class="text-red-500 text-xs mt-1">{{ form.errors.sample_follow_up_days }}</div>
                                </div>

                                <div class="bg-gray-50/60 p-5 rounded-xl border border-gray-200/80">
                                    <label class="block text-sm font-bold text-gray-800 mb-1">
                                        🔁 Hatırlatma Aralığı (Gün)
                                    </label>
                                    <span class="text-xs text-gray-500 block mb-3">
                                        Satışçı cevap vermezse kaç günde bir tekrar hatırlatılsın?
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <input 
                                            v-model="form.sample_follow_up_reminder_interval_days" 
                                            type="number" 
                                            min="1" 
                                            max="90" 
                                            class="w-32 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-center font-bold text-lg"
                                        />
                                        <span class="text-sm font-semibold text-gray-600">gün aralıkla</span>
                                    </div>
                                    <div v-if="form.errors.sample_follow_up_reminder_interval_days" class="text-red-500 text-xs mt-1">{{ form.errors.sample_follow_up_reminder_interval_days }}</div>
                                </div>

                                <div class="bg-gray-50/60 p-5 rounded-xl border border-gray-200/80">
                                    <label class="block text-sm font-bold text-gray-800 mb-1">
                                        🔔 Maksimum Hatırlatma Sayısı
                                    </label>
                                    <span class="text-xs text-gray-500 block mb-3">
                                        Cevap gelmezse en fazla kaç kez e-posta & bildirim atılsın?
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <input 
                                            v-model="form.sample_follow_up_max_reminders" 
                                            type="number" 
                                            min="1" 
                                            max="20" 
                                            class="w-32 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-center font-bold text-lg"
                                        />
                                        <span class="text-sm font-semibold text-gray-600">kez</span>
                                    </div>
                                    <div v-if="form.errors.sample_follow_up_max_reminders" class="text-red-500 text-xs mt-1">{{ form.errors.sample_follow_up_max_reminders }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: SAP S/4HANA ENTEGRASYONU -->
                        <div v-show="activeTab === 'sap'" class="space-y-6">
                            <div class="bg-sky-50/70 border border-sky-100 rounded-xl p-5 mb-6">
                                <h4 class="font-bold text-sky-900 text-base flex items-center gap-2">
                                    <span>🌐</span> SAP S/4HANA Entegrasyon Yapılandırması
                                </h4>
                                <p class="text-xs text-sky-700 mt-1 leading-relaxed">
                                    Köksan'ın SAP S/4HANA geçişine paralel olarak numunelerin siparişleşme verileri OData / REST servisleri üzerinden
                                    SAP sistemine otomatik eşleştirilebilir. Entegrasyon kapalıyken veya test ortamındayken güvenli simülasyon modunda çalışır.
                                </p>
                            </div>

                            <div class="space-y-5 max-w-3xl">
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-200">
                                    <div>
                                        <label class="font-bold text-sm text-gray-800">SAP S/4HANA Otomatik Sipariş Eşleşmesi</label>
                                        <p class="text-xs text-gray-500">Siparişe dönen numuneler anında SAP API'sine push edilsin mi?</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" v-model="form.sap_integration_enabled" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                                        <span class="ml-3 text-sm font-semibold" :class="form.sap_integration_enabled ? 'text-emerald-700' : 'text-gray-500'">
                                            {{ form.sap_integration_enabled ? 'Aktif' : 'Pasif (Simülasyon Modu)' }}
                                        </span>
                                    </label>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-bold text-gray-700 mb-1">SAP S/4HANA API Gateway Endpoint URL</label>
                                        <input 
                                            v-model="form.sap_api_url" 
                                            type="text" 
                                            placeholder="https://s4hana-gateway.koksan.com/sap/opu/odata/sap/API_SALES_ORDER_SRV" 
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 text-sm font-mono"
                                        />
                                        <div v-if="form.errors.sap_api_url" class="text-red-500 text-xs mt-1">{{ form.errors.sap_api_url }}</div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-1">SAP Client (İstemci No)</label>
                                        <input 
                                            v-model="form.sap_client" 
                                            type="text" 
                                            placeholder="100" 
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 text-sm"
                                        />
                                    </div>

                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-1">SAP Şirket Kodu (Company Code)</label>
                                        <input 
                                            v-model="form.sap_company_code" 
                                            type="text" 
                                            placeholder="1000" 
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 text-sm"
                                        />
                                    </div>

                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-1">SAP Satış Organizasyonu (Sales Org)</label>
                                        <input 
                                            v-model="form.sap_sales_org" 
                                            type="text" 
                                            placeholder="1000" 
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 text-sm"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 3: GÖRÜNÜM VE LOGO -->
                        <div v-show="activeTab === 'brand'" class="space-y-6">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Uygulama Logosu (Formlarda, Menülerde ve Analiz Sertifikasında Kullanılır)</label>
                                <div class="mt-1 flex items-center space-x-6">
                                    <div class="shrink-0">
                                        <img v-if="logoPreview" class="h-20 w-auto object-contain border p-2 rounded-lg bg-white shadow-sm" :src="logoPreview" alt="App Logo">
                                        <div v-else class="h-20 w-48 border-2 border-dashed border-gray-300 flex items-center justify-center text-gray-400 rounded-lg">
                                            Logo Yok
                                        </div>
                                    </div>
                                    <label class="block">
                                        <span class="sr-only">Logo seç</span>
                                        <input type="file" @change="handleLogoUpload" accept="image/*" class="block w-full text-sm text-gray-500
                                            file:mr-4 file:py-2.5 file:px-5
                                            file:rounded-lg file:border-0
                                            file:text-sm file:font-semibold
                                            file:bg-indigo-50 file:text-indigo-700
                                            hover:file:bg-indigo-100 cursor-pointer
                                        "/>
                                    </label>
                                </div>
                                <div v-if="form.errors.app_logo" class="text-red-500 text-sm mt-1">{{ form.errors.app_logo }}</div>
                            </div>
                        </div>

                        <!-- SUBMIT BUTTON -->
                        <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                            <span class="text-xs text-gray-500">Değişiklikler anında tüm sisteme yansıtılır.</span>
                            <PrimaryButton :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-700 px-6 py-2.5 font-bold shadow-md">
                                {{ form.processing ? 'Kaydediliyor...' : '💾 Ayarları Kaydet' }}
                            </PrimaryButton>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
