<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    roles: { type: Array, default: () => [] },
    modules: { type: Array, default: () => [] },
});

const form = useForm({});

// Modül & İsim Filtresi
const selectedModuleFilter = ref('all');
const roleSearchQuery = ref('');

// Silme Modalı Durumları
const showWarningModal = ref(false);
const roleToDelete = ref(null);
const usageStats = ref(null);
const isLoadingUsage = ref(false);

// Rol Detay İnceleme Modalı
const showRoleDetailModal = ref(false);
const selectedRoleForDetail = ref(null);

// Yeni Dinamik Yetki Tanımlama Modalı (İleriye Dönüklük)
const showCreatePermissionModal = ref(false);
const isCustomModule = ref(false);
const newPermForm = useForm({
    module_key: 'reports',
    custom_module_key: '',
    custom_module_title: '',
    custom_module_icon: '🧩',
    name: '',
    label: '',
    description: '',
});

const openRoleDetail = (role) => {
    selectedRoleForDetail.value = role;
    showRoleDetailModal.value = true;
};

const initiateDelete = async (role) => {
    roleToDelete.value = role;
    showWarningModal.value = true;
    isLoadingUsage.value = true;
    usageStats.value = null;
    
    try {
        const response = await axios.get(route('admin.roles.check-usage', role.id));
        if (response.data && response.data.success) {
            usageStats.value = response.data.data;
        }
    } catch (error) {
        console.error('Kullanım istatistikleri alınamadı:', error);
    } finally {
        isLoadingUsage.value = false;
    }
};

const confirmDelete = () => {
    if (roleToDelete.value) {
        form.delete(route('admin.roles.destroy', roleToDelete.value.id), {
            onSuccess: () => {
                showWarningModal.value = false;
                roleToDelete.value = null;
            }
        });
    }
};

// Yeni Dinamik Yetki Kaydet
const submitNewPermission = () => {
    const finalModuleKey = isCustomModule.value ? newPermForm.custom_module_key.trim().toLowerCase() : newPermForm.module_key;
    newPermForm.transform((data) => ({
        ...data,
        module_key: finalModuleKey,
        module_title: isCustomModule.value && newPermForm.custom_module_title ? newPermForm.custom_module_title.trim() : null,
        module_icon: isCustomModule.value && newPermForm.custom_module_icon ? newPermForm.custom_module_icon.trim() : null,
    })).post(route('admin.permissions.store'), {
        onSuccess: () => {
            showCreatePermissionModal.value = false;
            newPermForm.reset();
            isCustomModule.value = false;
        }
    });
};

// Filtrelenmiş Rol Listesi
const filteredRoles = computed(() => {
    return props.roles.filter(role => {
        // İsim Filtresi
        if (roleSearchQuery.value.trim()) {
            const q = roleSearchQuery.value.toLowerCase().trim();
            if (!role.name.toLowerCase().includes(q)) return false;
        }

        // Modül Filtresi
        if (selectedModuleFilter.value !== 'all') {
            const hasModule = (role.module_badges || []).some(b => b.key === selectedModuleFilter.value);
            if (!hasModule) return false;
        }

        return true;
    });
});

// Modül Başına Rol Sayısı
const getModuleRoleCount = (moduleKey) => {
    if (moduleKey === 'all') return props.roles.length;
    return props.roles.filter(r => (r.module_badges || []).some(b => b.key === moduleKey)).length;
};

// Renk stilleri
const badgeColors = {
    indigo: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
    amber: 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border-amber-200 dark:border-amber-800',
    emerald: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
    teal: 'bg-teal-50 text-teal-700 dark:bg-teal-950/50 dark:text-teal-300 border-teal-200 dark:border-teal-800',
    blue: 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300 border-blue-200 dark:border-blue-800',
    rose: 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300 border-rose-200 dark:border-rose-800',
    purple: 'bg-purple-50 text-purple-700 dark:bg-purple-950/50 dark:text-purple-300 border-purple-200 dark:border-purple-800',
    cyan: 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950/50 dark:text-cyan-300 border-cyan-200 dark:border-cyan-800',
    slate: 'bg-slate-50 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
};

const getBadgeClass = (color) => {
    return badgeColors[color] || badgeColors.slate;
};
</script>

<template>
    <Head title="Rol ve Yetki Matrisi" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="font-black text-2xl text-slate-900 dark:text-white leading-tight flex items-center gap-2">
                        <span>🛡️</span>
                        Yetki Rolleri & Kurumsal Modül Matrisi
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Tüm modüller (Power BI, SLA, Numune, Dış Görev, İç Denetim, BPMN) için rol yetkilerini canlı matris üzerinden yönetin.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- Geleceğe Dönük Yeni Yetki Tanımla Butonu -->
                    <button
                        type="button"
                        @click="showCreatePermissionModal = true"
                        class="inline-flex items-center gap-1.5 bg-white dark:bg-slate-800 hover:bg-slate-50 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 font-bold py-2.5 px-4 rounded-xl shadow-2xs text-xs transition-all"
                        title="İleride sisteme eklenecek yeni modüller için dinamik yetki tanımla"
                    >
                        <span>✨</span>
                        Yeni Yetki / Modül Ekle
                    </button>

                    <!-- Yeni Rol Oluştur Butonu -->
                    <Link
                        :href="route('admin.roles.create')"
                        class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-5 rounded-xl shadow-sm shadow-indigo-500/20 transition-all text-xs"
                    >
                        <span>➕</span>
                        Yeni Rol Tanımla
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
            <!-- 1. HIZLI MODÜL FİLTRELEME & DİLİMLEYİCİ ÇUBUĞU -->
            <div class="bg-white dark:bg-slate-800 p-3 rounded-2xl border border-slate-200 dark:border-slate-700/60 shadow-xs flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <div class="flex items-center gap-1.5 overflow-x-auto py-0.5">
                    <span class="text-xs font-bold text-slate-400 mr-1 shrink-0">Modül Filtresi:</span>
                    <!-- Tümü -->
                    <button
                        type="button"
                        @click="selectedModuleFilter = 'all'"
                        class="px-3 py-1.5 text-xs font-bold rounded-xl whitespace-nowrap transition-all"
                        :class="selectedModuleFilter === 'all' ? 'bg-indigo-600 text-white shadow-2xs' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700'"
                    >
                        Tümü ({{ getModuleRoleCount('all') }})
                    </button>

                    <!-- Modül Butonları -->
                    <button
                        v-for="mod in modules"
                        :key="mod.id"
                        type="button"
                        @click="selectedModuleFilter = mod.id"
                        class="px-3 py-1.5 text-xs font-bold rounded-xl whitespace-nowrap transition-all flex items-center gap-1.5"
                        :class="selectedModuleFilter === mod.id ? 'bg-indigo-600 text-white shadow-2xs' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700'"
                    >
                        <span>{{ mod.icon }}</span>
                        <span>{{ mod.title.split(' ')[0] }}</span>
                        <span class="text-[10px] px-1.5 py-0.2 rounded-full font-semibold" :class="selectedModuleFilter === mod.id ? 'bg-white/20' : 'bg-slate-100 dark:bg-slate-900'">
                            {{ getModuleRoleCount(mod.id) }}
                        </span>
                    </button>
                </div>

                <!-- Arama Kutusu -->
                <div class="relative min-w-[200px] shrink-0">
                    <input
                        v-model="roleSearchQuery"
                        type="text"
                        placeholder="Rol adı ara..."
                        class="w-full text-xs py-1.5 pl-8 pr-3 rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 focus:ring-indigo-500 shadow-2xs"
                    />
                    <span class="absolute left-2.5 top-2 text-slate-400 text-xs">🔍</span>
                </div>
            </div>

            <!-- 2. ROL TABLOSU & MODÜLER ERİŞİM KAPSAMI -->
            <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-xs rounded-2xl border border-slate-200 dark:border-slate-700/60">
                <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-700/60">
                    <thead class="bg-slate-50/70 dark:bg-slate-900/40">
                        <tr>
                            <th scope="col" class="px-5 py-3.5 text-left text-[11px] font-extrabold text-slate-400 uppercase tracking-wider w-12">ID</th>
                            <th scope="col" class="px-5 py-3.5 text-left text-[11px] font-extrabold text-slate-400 uppercase tracking-wider min-w-[160px]">Rol Adı</th>
                            <th scope="col" class="px-5 py-3.5 text-left text-[11px] font-extrabold text-slate-400 uppercase tracking-wider">Modüler Yetki Kapsamı</th>
                            <th scope="col" class="px-5 py-3.5 text-left text-[11px] font-extrabold text-slate-400 uppercase tracking-wider w-36">Toplam Yetki</th>
                            <th scope="col" class="px-5 py-3.5 text-right text-[11px] font-extrabold text-slate-400 uppercase tracking-wider w-36">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-800 text-xs">
                        <tr v-for="role in filteredRoles" :key="role.id" class="hover:bg-slate-50/70 dark:hover:bg-slate-700/30 transition-colors group">
                            <!-- ID -->
                            <td class="px-5 py-3.5 whitespace-nowrap text-slate-400 font-mono text-xs">
                                #{{ role.id }}
                            </td>

                            <!-- Rol Adı -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold flex items-center justify-center text-sm shadow-2xs">
                                        {{ role.name.charAt(0) }}
                                    </div>
                                    <div>
                                        <div class="font-extrabold text-slate-900 dark:text-slate-100 text-sm">
                                            {{ role.name }}
                                        </div>
                                        <div class="text-[10px] text-slate-400">
                                            {{ role.id === 1 ? 'Tam Sistem Yöneticisi' : 'Operasyonel Kurumsal Rol' }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Modüler Yetki Rozetleri -->
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span
                                        v-for="badge in role.module_badges"
                                        :key="badge.key"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold border shadow-2xs"
                                        :class="getBadgeClass(badge.badge_color)"
                                        :title="badge.title + ': ' + badge.count + ' yetki'"
                                    >
                                        <span>{{ badge.icon }}</span>
                                        <span>{{ badge.title.split(' ')[0] }}</span>
                                        <span class="text-[9px] px-1 bg-black/10 dark:bg-white/10 rounded font-bold">
                                            {{ badge.count }}
                                        </span>
                                    </span>
                                    <span v-if="!role.module_badges || role.module_badges.length === 0" class="text-slate-400 italic text-[11px]">
                                        Yetki atanmamış
                                    </span>
                                </div>
                            </td>

                            <!-- Yetki Sayacı & İlerleme Çubuğu -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div>
                                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">
                                        <span>{{ role.permissions_count || 0 }} Yetki</span>
                                        <span class="text-slate-400 text-[10px]">%{{ Math.round(((role.permissions_count || 0) / 44) * 100) }}</span>
                                    </div>
                                    <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-1.5 overflow-hidden">
                                        <div
                                            class="bg-indigo-600 h-1.5 rounded-full transition-all"
                                            :style="{ width: Math.min(((role.permissions_count || 0) / 44) * 100, 100) + '%' }"
                                        ></div>
                                    </div>
                                </div>
                            </td>

                            <!-- Eylemler -->
                            <td class="px-5 py-3.5 whitespace-nowrap text-right font-medium">
                                <div class="flex items-center justify-end gap-1.5">
                                    <!-- Hızlı Yetki Detayı Modalı Butonu -->
                                    <button
                                        type="button"
                                        @click="openRoleDetail(role)"
                                        class="px-2 py-1 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg text-xs font-bold border border-slate-200 dark:border-slate-700 transition-colors shadow-2xs"
                                        title="Rol Yetkilerini Hızlı İncele"
                                    >
                                        👁️ Yetkiler
                                    </button>

                                    <!-- Düzenle -->
                                    <Link
                                        :href="route('admin.roles.edit', role.id)"
                                        class="px-2 py-1 text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 rounded-lg text-xs font-bold border border-indigo-200 dark:border-indigo-800 transition-colors shadow-2xs"
                                    >
                                        ✏️ Düzenle
                                    </Link>

                                    <!-- Sil -->
                                    <button
                                        v-if="role.id !== 1"
                                        type="button"
                                        @click="initiateDelete(role)"
                                        class="px-2 py-1 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg text-xs font-bold border border-rose-200 dark:border-rose-800/60 transition-colors shadow-2xs"
                                        title="Rolü Sil"
                                    >
                                        🗑️
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Boş Liste -->
                        <tr v-if="filteredRoles.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                                <span class="text-3xl block mb-2">🔍</span>
                                <p class="font-bold text-slate-600 dark:text-slate-300">Filtreye uygun rol bulunamadı</p>
                                <p class="text-xs text-slate-400 mt-1">Farklı bir modül seçin veya arama terimini temizleyin.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MODAL 1: YENİ DİNAMİK YETKİ / MODÜL TANIMLAMA (İLERİYE DÖNÜK MİMARİ) -->
        <Modal :show="showCreatePermissionModal" @close="showCreatePermissionModal = false" max-width="lg">
            <div class="p-6">
                <div class="flex items-center justify-between border-b pb-3 mb-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>✨</span> Yeni Yetki / Modül Tanımla
                        </h3>
                        <p class="text-xs text-slate-500">
                            Gelecekte projeye eklenecek yeni modüller için tek satır kod yazmadan anında yeni operasyonel yetki oluşturun.
                        </p>
                    </div>
                    <button type="button" @click="showCreatePermissionModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <form @submit.prevent="submitNewPermission" class="space-y-4">
                    <!-- Modül Seçimi -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Bağlı Olacağı Modül *</label>
                        <div class="space-y-2">
                            <select
                                v-if="!isCustomModule"
                                v-model="newPermForm.module_key"
                                class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800"
                            >
                                <option v-for="m in modules" :key="m.id" :value="m.id">
                                    {{ m.icon }} {{ m.title }}
                                </option>
                            </select>

                            <div v-else class="space-y-2">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-0.5">Modül Sistem Kodu (küçük harf, tek kelime/alt çizgi):</label>
                                    <input
                                        v-model="newPermForm.custom_module_key"
                                        type="text"
                                        required
                                        placeholder="Örn: warehouse, quality, maintenance..."
                                        class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 font-mono"
                                    />
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    <div class="sm:col-span-2">
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-0.5">Modül Görünen Başlığı:</label>
                                        <input
                                            v-model="newPermForm.custom_module_title"
                                            type="text"
                                            placeholder="Örn: Depo, Lojistik & Barkodlu WMS"
                                            class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-0.5">Modül İkonu:</label>
                                        <div class="flex items-center gap-1">
                                            <input
                                                v-model="newPermForm.custom_module_icon"
                                                type="text"
                                                maxlength="4"
                                                class="w-12 text-center text-sm rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800"
                                            />
                                            <div class="flex items-center gap-0.5 text-xs">
                                                <button type="button" @click="newPermForm.custom_module_icon = '📦'" class="hover:scale-125 transition-transform">📦</button>
                                                <button type="button" @click="newPermForm.custom_module_icon = '🛒'" class="hover:scale-125 transition-transform">🛒</button>
                                                <button type="button" @click="newPermForm.custom_module_icon = '🔧'" class="hover:scale-125 transition-transform">🔧</button>
                                                <button type="button" @click="newPermForm.custom_module_icon = '🎯'" class="hover:scale-125 transition-transform">🎯</button>
                                                <button type="button" @click="newPermForm.custom_module_icon = '🚚'" class="hover:scale-125 transition-transform">🚚</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="isCustomModule = !isCustomModule"
                                class="text-[11px] font-bold text-indigo-600 hover:underline inline-flex items-center gap-1"
                            >
                                <span>{{ isCustomModule ? '← Mevcut Modüllerden Seç' : '➕ Yepyeni Bir Modül Kodu & Başlığı Gir' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Yetki Kodu (Name) -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Teknik Yetki Kodu * (Format: modül.eylem)
                        </label>
                        <input
                            v-model="newPermForm.name"
                            type="text"
                            required
                            placeholder="Örn: reports.analytics, travel.vip_lounge"
                            class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 font-mono"
                        />
                        <div v-if="newPermForm.errors.name" class="text-rose-600 text-xs mt-1">
                            {{ newPermForm.errors.name }}
                        </div>
                    </div>

                    <!-- Yetki Başlığı (Label) -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Kullanıcı Dostu Yetki Başlığı *
                        </label>
                        <input
                            v-model="newPermForm.label"
                            type="text"
                            required
                            placeholder="Örn: Gelişmiş Analitik Raporlarını İnceleme"
                            class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800"
                        />
                    </div>

                    <!-- Açıklama -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Açıklama / Şirket İçi Kapsam</label>
                        <textarea
                            v-model="newPermForm.description"
                            rows="2"
                            placeholder="Bu yetkiye sahip personelin sistemde yapabileceği işlemler..."
                            class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t dark:border-slate-700">
                        <button type="button" @click="showCreatePermissionModal = false" class="px-3 py-1.5 text-xs text-slate-500">İptal</button>
                        <button
                            type="submit"
                            :disabled="newPermForm.processing"
                            class="px-4 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors disabled:opacity-50"
                        >
                            {{ newPermForm.processing ? 'Kaydediliyor...' : 'Yetkiyi Kaydet' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- MODAL 2: ROL YETKİ KAPSAMI İNCELEME MODALI -->
        <Modal :show="showRoleDetailModal" @close="showRoleDetailModal = false" max-width="2xl">
            <div class="p-6" v-if="selectedRoleForDetail">
                <div class="flex items-center justify-between border-b pb-3 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 font-bold flex items-center justify-center text-base">
                            {{ selectedRoleForDetail.name.charAt(0) }}
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                                {{ selectedRoleForDetail.name }} Yetki Matrisi
                            </h3>
                            <p class="text-xs text-slate-500">
                                Toplam {{ selectedRoleForDetail.permissions?.length || 0 }} adet operasyonel yetki tanımlı
                            </p>
                        </div>
                    </div>
                    <button type="button" @click="showRoleDetailModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-1">
                    <div
                        v-for="mod in modules"
                        :key="mod.id"
                        class="p-3.5 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700/60"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <div class="font-bold text-xs text-slate-900 dark:text-slate-100 flex items-center gap-1.5">
                                <span>{{ mod.icon }}</span>
                                <span>{{ mod.title }}</span>
                            </div>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-300">
                                {{ mod.permissions.filter(p => selectedRoleForDetail.permissions.some(rp => rp.name === p.name)).length }} / {{ mod.permissions.length }} Yetki
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                            <div
                                v-for="p in mod.permissions"
                                :key="p.id"
                                class="p-2 rounded-lg flex items-center gap-2"
                                :class="selectedRoleForDetail.permissions.some(rp => rp.name === p.name) ? 'bg-white dark:bg-slate-700/60 text-slate-800 dark:text-slate-200 shadow-2xs font-bold' : 'text-slate-400 opacity-50'"
                            >
                                <span>{{ selectedRoleForDetail.permissions.some(rp => rp.name === p.name) ? '✅' : '🔒' }}</span>
                                <span class="truncate" :title="p.description || p.label">{{ p.label }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4 mt-4 border-t dark:border-slate-700">
                    <Link
                        :href="route('admin.roles.edit', selectedRoleForDetail.id)"
                        class="px-4 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors"
                    >
                        ✏️ Bu Rolün Yetkilerini Düzenle
                    </Link>
                    <button type="button" @click="showRoleDetailModal = false" class="px-4 py-2 text-xs text-slate-600 hover:bg-slate-100 rounded-xl">Kapat</button>
                </div>
            </div>
        </Modal>

        <!-- MODAL 3: SİLME ONAY MODALI (CHECK USAGE) -->
        <Modal :show="showWarningModal" @close="showWarningModal = false" max-width="md">
            <div class="p-6">
                <div class="flex items-start gap-3">
                    <div class="p-2.5 bg-rose-100 text-rose-600 rounded-xl shrink-0">
                        <span class="text-xl">⚠️</span>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Rol Silme Onayı</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            <strong>"{{ roleToDelete?.name }}"</strong> rolünü silmek istediğinize emin misiniz?
                        </p>
                    </div>
                </div>

                <div v-if="isLoadingUsage" class="py-6 text-center text-xs text-slate-400">
                    Kullanım istatistikleri denetleniyor...
                </div>

                <div v-else-if="usageStats" class="my-4 space-y-2 text-xs bg-slate-50 p-3 rounded-xl">
                    <div class="flex justify-between">
                        <span>Sahip Kullanıcılar:</span>
                        <strong :class="usageStats.userCount > 0 ? 'text-rose-600' : ''">{{ usageStats.userCount }} Kişi</strong>
                    </div>
                    <div class="flex justify-between">
                        <span>Kullanılan Şablonlar:</span>
                        <strong :class="usageStats.workflowCount > 0 ? 'text-rose-600' : ''">{{ usageStats.workflowCount }} Şablon</strong>
                    </div>
                    <div class="flex justify-between">
                        <span>Bekleyen Görevler:</span>
                        <strong :class="usageStats.taskCount > 0 ? 'text-rose-600' : ''">{{ usageStats.taskCount }} Görev</strong>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="showWarningModal = false" class="px-3 py-1.5 text-xs text-slate-500">Vazgeç</button>
                    <button
                        type="button"
                        @click="confirmDelete"
                        :disabled="form.processing"
                        class="px-4 py-1.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl transition-colors disabled:opacity-50"
                    >
                        {{ form.processing ? 'Siliniyor...' : 'Evet, Rolü Sil' }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
