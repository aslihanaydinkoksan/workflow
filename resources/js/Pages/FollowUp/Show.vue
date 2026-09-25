<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import FormRenderer from '@/Components/FormRenderer.vue';

const props = defineProps({
    followUp: Object,
    reasons: Array,
});

const instance = computed(() => props.followUp?.process_instance || props.followUp?.processInstance || {});
const formData = computed(() => (instance.value?.data || {}));

const customerName = computed(() => formData.value.musteri_adi || formData.value.customer_name || formData.value.talep_eden || 'Genel Müşteri / Talep');
const productName = computed(() => formData.value.urun_adi || formData.value.material_name || formData.value.numune_adi || formData.value.dosya_adi || formData.value.talep_konusu || 'Süreç Kalemi');
const quantity = computed(() => formData.value.miktar || formData.value.quantity || formData.value.adet || '-');
const lotNo = computed(() => formData.value.lot_no || formData.value.parti_no || formData.value.dosya_no || ('ID-' + (instance.value?.id || props.followUp?.process_instance_id || '')));

const isAlreadyAnswered = computed(() => props.followUp.status !== 'pending');

// Form state
const responseType = ref('converted'); // 'converted' | 'not_converted' | 'rescheduled'

const form = useForm({
    response_status: 'converted',
    order_number: '',
    non_conversion_reason: props.reasons[0] || '',
    customer_feedback: '',
    new_date: '',
    sub_form_answers: props.followUp.metadata?.sub_form_answers || {},
});

const selectResponseType = (type) => {
    responseType.value = type;
    form.response_status = type;
};

const submit = () => {
    form.post(route('follow-ups.update', props.followUp.id), {
        preserveScroll: true,
    });
};

const retrySap = () => {
    useForm({}).post(route('follow-ups.retry-sap', props.followUp.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`${followUp.title || 'Süreç Takibi'} #${followUp.process_instance_id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-gray-900 leading-tight">
                        ⏱ {{ followUp.title || 'Süreç & Sipariş Takip Bildirimi' }} (Süreç #{{ followUp.process_instance_id }})
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">{{ followUp.prompt || 'Süreç tamamlandıktan sonraki güncel durumu ve geri bildirimleri sisteme işleyin.' }}</p>
                </div>
                <Link :href="route('follow-ups.index')" class="text-sm font-semibold text-gray-600 hover:text-gray-900 flex items-center gap-1">
                    &larr; Takip Listesine Dön
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- NUMUNE ÖZET KARTI -->
                <div class="bg-white rounded-2xl border border-gray-200/80 shadow-sm p-6 overflow-hidden">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-gray-100 pb-4 mb-4 gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-lg">
                                #{{ instance?.id || followUp.process_instance_id }}
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 text-lg">{{ customerName }}</h3>
                                <span class="text-xs text-gray-500 font-medium">{{ instance?.workflow?.name || 'Numune Talebi' }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <a 
                                v-if="instance?.id || followUp.process_instance_id"
                                :href="route('processes.certificate', instance?.id || followUp.process_instance_id)"
                                target="_blank"
                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 transition shadow-sm"
                            >
                                <span>📄</span> Analiz Sertifikası (PDF)
                            </a>
                            <Link 
                                v-if="instance?.id || followUp.process_instance_id"
                                :href="route('processes.tracker', instance?.id || followUp.process_instance_id)"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200 transition"
                            >
                                <span>🔍</span> Akış Takibi
                            </Link>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                        <div class="bg-gray-50/70 p-3 rounded-lg border border-gray-100">
                            <span class="text-xs text-gray-400 block font-medium">Ürün / Malzeme</span>
                            <span class="font-bold text-gray-800">{{ productName }}</span>
                        </div>
                        <div class="bg-gray-50/70 p-3 rounded-lg border border-gray-100">
                            <span class="text-xs text-gray-400 block font-medium">Miktar</span>
                            <span class="font-bold text-gray-800">{{ quantity }}</span>
                        </div>
                        <div class="bg-gray-50/70 p-3 rounded-lg border border-gray-100">
                            <span class="text-xs text-gray-400 block font-medium">Lot / Parti No</span>
                            <span class="font-bold text-gray-800 font-mono">{{ lotNo }}</span>
                        </div>
                        <div class="bg-gray-50/70 p-3 rounded-lg border border-gray-100">
                            <span class="text-xs text-gray-400 block font-medium">Satış Sorumlusu</span>
                            <span class="font-bold text-gray-800">{{ followUp.assigned_user?.name || instance?.starter?.name || '-' }}</span>
                        </div>
                    </div>
                </div>

                <!-- DURUM ZATEN CEVAPLANDIYSA -->
                <div v-if="isAlreadyAnswered" class="bg-white rounded-2xl border border-gray-200/80 shadow-sm p-6 space-y-6">
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                        <div class="flex items-center gap-3">
                            <div v-if="followUp.status === 'converted'" class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xl">
                                ✅
                            </div>
                            <div v-else class="w-10 h-10 rounded-full bg-rose-100 text-rose-700 flex items-center justify-center text-xl">
                                ❌
                            </div>
                            <div>
                                <h4 class="font-bold text-lg text-gray-900">
                                    {{ followUp.status === 'converted' ? 'Bu Numune Siparişe Dönüştürüldü' : 'Bu Numune Siparişe Dönmedi' }}
                                </h4>
                                <p class="text-xs text-gray-500">
                                    Cevaplayan: <strong>{{ followUp.responded_by_user?.name || '-' }}</strong> &bull; 
                                    Tarih: {{ new Date(followUp.responded_at).toLocaleString('tr-TR') }}
                                </p>
                            </div>
                        </div>

                        <span 
                            class="px-3 py-1 rounded-full text-xs font-bold"
                            :class="followUp.status === 'converted' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                        >
                            {{ followUp.status === 'converted' ? 'Siparişe Döndü' : 'Dönmedi' }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div v-if="followUp.order_number" class="p-4 bg-emerald-50/50 rounded-xl border border-emerald-100">
                            <span class="text-xs font-bold text-emerald-800 block uppercase">Sipariş Numarası</span>
                            <span class="text-xl font-black text-emerald-900 font-mono">{{ followUp.order_number }}</span>
                        </div>

                        <div v-if="followUp.non_conversion_reason" class="p-4 bg-rose-50/50 rounded-xl border border-rose-100">
                            <span class="text-xs font-bold text-rose-800 block uppercase">Dönmeme Gerekçesi</span>
                            <span class="text-base font-bold text-rose-900">{{ followUp.non_conversion_reason }}</span>
                        </div>

                        <div v-if="followUp.customer_feedback" class="md:col-span-2 p-4 bg-gray-50 rounded-xl border border-gray-200">
                            <span class="text-xs font-bold text-gray-500 block uppercase">Geri Bildirim / Açıklama Notları</span>
                            <p class="text-gray-800 mt-1 whitespace-pre-line">{{ followUp.customer_feedback }}</p>
                        </div>
                    </div>

                    <!-- Cevaplanmış Alt Form Varsa Göster -->
                    <div v-if="followUp.sub_form && followUp.metadata?.sub_form_answers" class="p-4 bg-gray-50 rounded-xl border border-gray-200 space-y-3">
                        <span class="text-xs font-bold text-indigo-700 block uppercase">Doldurulan Form: {{ followUp.sub_form.name }}</span>
                        <div class="bg-white p-4 rounded-lg border border-gray-200">
                            <FormRenderer
                                :elements="followUp.sub_form.schema"
                                :model-value="followUp.metadata.sub_form_answers"
                                :template="followUp.sub_form"
                                :disabled="true"
                            />
                        </div>
                    </div>

                    <!-- SAP S/4HANA Eşleşme Bilgisi -->
                    <div class="p-4 rounded-xl border bg-sky-50/60 border-sky-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-sky-100 text-sky-700 flex items-center justify-center font-bold text-sm">
                                SAP
                            </div>
                            <div>
                                <span class="text-xs font-bold text-sky-900 block">SAP S/4HANA Senkronizasyonu</span>
                                <span class="text-sm font-semibold text-sky-800">
                                    {{ followUp.sap_sales_order_id ? `Belge No: ${followUp.sap_sales_order_id}` : 'Henüz senkronize edilmedi' }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span 
                                class="px-2.5 py-1 rounded text-xs font-bold uppercase"
                                :class="{
                                    'bg-emerald-100 text-emerald-800': followUp.sap_sync_status === 'synced',
                                    'bg-rose-100 text-rose-800': followUp.sap_sync_status === 'failed',
                                    'bg-gray-100 text-gray-700': followUp.sap_sync_status === 'not_applicable' || !followUp.sap_sync_status,
                                }"
                            >
                                {{ followUp.sap_sync_status || 'not_applicable' }}
                            </span>

                            <button 
                                v-if="$page.props.auth.user && followUp.status === 'converted'"
                                @click="retrySap"
                                type="button"
                                class="px-3 py-1 bg-sky-600 hover:bg-sky-700 text-white rounded text-xs font-semibold transition"
                            >
                                🔄 Yeniden Senkronize Et
                            </button>
                        </div>
                    </div>
                </div>

                <!-- CEVAPLAMA FORMU (Henüz cevaplanmamışsa) -->
                <div v-else class="bg-white rounded-2xl border border-gray-200/80 shadow-sm p-6 space-y-6">
                    <div>
                        <h3 class="font-bold text-lg text-gray-900">👉 Bu numune siparişe dönüştü mü?</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Lütfen müşteri ile yapılan görüşme sonucuna göre aşağıdaki seçeneklerden birini işaretleyiniz.</p>
                    </div>

                    <!-- 3 Seçenek Buton Grubu -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <button 
                            type="button"
                            @click="selectResponseType('converted')"
                            class="p-4 rounded-xl border-2 text-left transition flex flex-col justify-between h-28"
                            :class="responseType === 'converted' ? 'border-emerald-500 bg-emerald-50/60 shadow-sm' : 'border-gray-200 hover:border-gray-300 bg-white'"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-2xl">🎉</span>
                                <span v-if="responseType === 'converted'" class="w-3 h-3 rounded-full bg-emerald-600"></span>
                            </div>
                            <div>
                                <span class="font-bold text-emerald-950 block text-sm">Evet, Siparişe Döndü</span>
                                <span class="text-[11px] text-emerald-700">Sipariş numarası işlenir</span>
                            </div>
                        </button>

                        <button 
                            type="button"
                            @click="selectResponseType('not_converted')"
                            class="p-4 rounded-xl border-2 text-left transition flex flex-col justify-between h-28"
                            :class="responseType === 'not_converted' ? 'border-rose-500 bg-rose-50/60 shadow-sm' : 'border-gray-200 hover:border-gray-300 bg-white'"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-2xl">❌</span>
                                <span v-if="responseType === 'not_converted'" class="w-3 h-3 rounded-full bg-rose-600"></span>
                            </div>
                            <div>
                                <span class="font-bold text-rose-950 block text-sm">Hayır, Siparişe Dönmedi</span>
                                <span class="text-[11px] text-rose-700">Dönmeme sebebi belirtilir</span>
                            </div>
                        </button>

                        <button 
                            type="button"
                            @click="selectResponseType('rescheduled')"
                            class="p-4 rounded-xl border-2 text-left transition flex flex-col justify-between h-28"
                            :class="responseType === 'rescheduled' ? 'border-amber-500 bg-amber-50/60 shadow-sm' : 'border-gray-200 hover:border-gray-300 bg-white'"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-2xl">⏳</span>
                                <span v-if="responseType === 'rescheduled'" class="w-3 h-3 rounded-full bg-amber-600"></span>
                            </div>
                            <div>
                                <span class="font-bold text-amber-950 block text-sm">Görüşmeler Sürüyor</span>
                                <span class="text-[11px] text-amber-700">Takip tarihi ileriye ertelenir</span>
                            </div>
                        </button>
                    </div>

                    <!-- SEÇİME GÖRE AÇILAN ALANLAR -->
                    <form @submit.prevent="submit" class="space-y-6 pt-2">
                        
                        <!-- DURUM 1: SİPARİŞE DÖNDÜ -->
                        <div v-show="responseType === 'converted'" class="space-y-4 p-5 bg-emerald-50/40 rounded-xl border border-emerald-100">
                            <div>
                                <label class="block text-sm font-bold text-emerald-900 mb-1">
                                    Sipariş Numarası <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    v-model="form.order_number"
                                    type="text" 
                                    placeholder="Örn: 4500123987 veya SIP-2026-0042"
                                    class="w-full rounded-lg border-emerald-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 font-mono font-bold text-sm"
                                />
                                <div v-if="form.errors.order_number" class="text-red-600 text-xs mt-1">{{ form.errors.order_number }}</div>
                                <span class="text-xs text-emerald-700 block mt-1.5">
                                    * Kaydedildiğinde SAP S/4HANA sistemine bu referans numarasıyla otomatik eşleme iletilecektir.
                                </span>
                            </div>
                        </div>

                        <!-- DURUM 2: DÖNMEDİ -->
                        <div v-show="responseType === 'not_converted'" class="space-y-4 p-5 bg-rose-50/40 rounded-xl border border-rose-100">
                            <div>
                                <label class="block text-sm font-bold text-rose-900 mb-1">
                                    Siparişe Dönmeme Gerekçesi <span class="text-red-500">*</span>
                                </label>
                                <select 
                                    v-model="form.non_conversion_reason"
                                    class="w-full rounded-lg border-rose-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm font-semibold text-gray-800"
                                >
                                    <option v-for="reason in reasons" :key="reason" :value="reason">
                                        {{ reason }}
                                    </option>
                                </select>
                                <div v-if="form.errors.non_conversion_reason" class="text-red-600 text-xs mt-1">{{ form.errors.non_conversion_reason }}</div>
                            </div>
                        </div>

                        <!-- DURUM 3: ERTELEME -->
                        <div v-show="responseType === 'rescheduled'" class="space-y-4 p-5 bg-amber-50/40 rounded-xl border border-amber-100">
                            <div>
                                <label class="block text-sm font-bold text-amber-900 mb-1">
                                    Yeni Takip Tarihi <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    v-model="form.new_date"
                                    type="date" 
                                    class="rounded-lg border-amber-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm"
                                />
                                <div v-if="form.errors.new_date" class="text-red-600 text-xs mt-1">{{ form.errors.new_date }}</div>
                                <span class="text-xs text-amber-700 block mt-1">
                                    Bu tarihe kadar bildirimler durdurulur ve belirlenen günde tekrar hatırlatılır.
                                </span>
                            </div>
                        </div>

                        <!-- BAĞLI ALT FORM VARSA FORM ALANLARI -->
                        <div v-if="followUp.sub_form" class="space-y-3 p-5 bg-indigo-50/30 rounded-xl border border-indigo-100">
                            <div class="flex items-center gap-2">
                                <span class="text-base">📋</span>
                                <h4 class="text-sm font-bold text-indigo-950">
                                    Bağlı Form: {{ followUp.sub_form.name }}
                                </h4>
                            </div>
                            <p class="text-xs text-indigo-700">Bu süreç adımı için tanımlanmış ek formu eksiksiz doldurunuz.</p>
                            <div class="bg-white p-4 rounded-lg border border-indigo-100">
                                <FormRenderer
                                    :elements="followUp.sub_form.schema"
                                    v-model="form.sub_form_answers"
                                    :template="followUp.sub_form"
                                />
                            </div>
                        </div>

                        <!-- ORTAK MÜŞTERİ NOTLARI / GERİ BİLDİRİM -->
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">
                                Geri Bildirim / Açıklama Notları
                            </label>
                            <textarea 
                                v-model="form.customer_feedback"
                                rows="3"
                                placeholder="Görüşme sonucu, fiyat/kalite yorumu, dosya durumu veya özel notlar..."
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            ></textarea>
                            <div v-if="form.errors.customer_feedback" class="text-red-500 text-xs mt-1">{{ form.errors.customer_feedback }}</div>
                        </div>

                        <!-- SUBMIT -->
                        <div class="flex items-center justify-end pt-4 border-t border-gray-100 gap-3">
                            <Link :href="route('follow-ups.index')" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">
                                İptal
                            </Link>
                            <PrimaryButton 
                                :disabled="form.processing"
                                class="bg-indigo-600 hover:bg-indigo-700 font-bold px-6 py-2.5 shadow-md"
                            >
                                {{ form.processing ? 'Kaydediliyor...' : '💾 Sonucu Kaydet' }}
                            </PrimaryButton>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
