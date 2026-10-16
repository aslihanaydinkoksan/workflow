<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    role: Object,
    modules: { type: Array, default: () => [] },
    permissions: { type: Array, default: () => [] },
    rolePermissions: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.role?.name || '',
    permissions: props.rolePermissions || [],
});

const searchQuery = ref('');

const submit = () => {
    if (props.role && props.role.id) {
        form.put(route('admin.roles.update', { role: props.role.id }));
    } else {
        form.post(route('admin.roles.store'));
    }
};

// Modül bazında yetki sayısı hesaplayıcı
const selectedCountInModule = (module) => {
    return module.permissions.filter(p => form.permissions.includes(p.name)).length;
};

const isModuleAllSelected = (module) => {
    if (!module.permissions.length) return false;
    return module.permissions.every(p => form.permissions.includes(p.name));
};

const toggleModule = (module) => {
    const allSelected = isModuleAllSelected(module);
    const modPermNames = module.permissions.map(p => p.name);

    if (allSelected) {
        // Hepsini kaldır
        form.permissions = form.permissions.filter(name => !modPermNames.includes(name));
    } else {
        // Hepsini ekle
        const newSet = new Set([...form.permissions, ...modPermNames]);
        form.permissions = Array.from(newSet);
    }
};

// Global Tümünü Seç / Temizle
const selectAllEverywhere = () => {
    const allNames = props.modules.flatMap(m => m.permissions.map(p => p.name));
    form.permissions = Array.from(new Set(allNames));
};

const deselectAllEverywhere = () => {
    form.permissions = [];
};

// Canlı Arama Filtresi
const filteredModules = computed(() => {
    if (!searchQuery.value.trim()) return props.modules;
    const q = searchQuery.value.toLowerCase().trim();

    return props.modules.map(mod => {
        const matchingPerms = mod.permissions.filter(p => 
            p.name.toLowerCase().includes(q) ||
            p.label.toLowerCase().includes(q) ||
            (p.description && p.description.toLowerCase().includes(q))
        );
        return {
            ...mod,
            permissions: matchingPerms,
        };
    }).filter(mod => mod.permissions.length > 0);
});

const totalPermissionsCount = computed(() => {
    return props.modules.reduce((sum, m) => sum + m.permissions.length, 0);
});

// Renk stilleri haritası
const colorClasses = {
    indigo: {
        border: 'border-indigo-200 dark:border-indigo-800/60',
        header: 'bg-indigo-50/70 dark:bg-indigo-950/40 text-indigo-900 dark:text-indigo-200',
        badge: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-300',
        ring: 'focus:ring-indigo-500 text-indigo-600',
    },
    amber: {
        border: 'border-amber-200 dark:border-amber-800/60',
        header: 'bg-amber-50/70 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200',
        badge: 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300',
        ring: 'focus:ring-amber-500 text-amber-600',
    },
    emerald: {
        border: 'border-emerald-200 dark:border-emerald-800/60',
        header: 'bg-emerald-50/70 dark:bg-emerald-950/40 text-emerald-900 dark:text-emerald-200',
        badge: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300',
        ring: 'focus:ring-emerald-500 text-emerald-600',
    },
    teal: {
        border: 'border-teal-200 dark:border-teal-800/60',
        header: 'bg-teal-50/70 dark:bg-teal-950/40 text-teal-900 dark:text-teal-200',
        badge: 'bg-teal-100 text-teal-800 dark:bg-teal-900/60 dark:text-teal-300',
        ring: 'focus:ring-teal-500 text-teal-600',
    },
    blue: {
        border: 'border-blue-200 dark:border-blue-800/60',
        header: 'bg-blue-50/70 dark:bg-blue-950/40 text-blue-900 dark:text-blue-200',
        badge: 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300',
        ring: 'focus:ring-blue-500 text-blue-600',
    },
    rose: {
        border: 'border-rose-200 dark:border-rose-800/60',
        header: 'bg-rose-50/70 dark:bg-rose-950/40 text-rose-900 dark:text-rose-200',
        badge: 'bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-300',
        ring: 'focus:ring-rose-500 text-rose-600',
    },
    purple: {
        border: 'border-purple-200 dark:border-purple-800/60',
        header: 'bg-purple-50/70 dark:bg-purple-950/40 text-purple-900 dark:text-purple-200',
        badge: 'bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300',
        ring: 'focus:ring-purple-500 text-purple-600',
    },
    cyan: {
        border: 'border-cyan-200 dark:border-cyan-800/60',
        header: 'bg-cyan-50/70 dark:bg-cyan-950/40 text-cyan-900 dark:text-cyan-200',
        badge: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/60 dark:text-cyan-300',
        ring: 'focus:ring-cyan-500 text-cyan-600',
    },
    slate: {
        border: 'border-slate-200 dark:border-slate-700/60',
        header: 'bg-slate-50/70 dark:bg-slate-800 text-slate-800 dark:text-slate-200',
        badge: 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
        ring: 'focus:ring-slate-500 text-slate-600',
    },
};

const getModuleStyle = (color) => {
    return colorClasses[color] || colorClasses.slate;
};
</script>

<template>
    <Head :title="role ? 'Rol Düzenle: ' + role.name : 'Yeni Rol Oluştur'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <Link :href="route('admin.roles.index')" class="p-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-50 transition-colors shadow-2xs text-xs font-bold">
                        &larr; Rol Listesi
                    </Link>
                    <div>
                        <h2 class="font-black text-xl text-slate-900 dark:text-white leading-tight flex items-center gap-2">
                            <span>🛡️</span>
                            {{ role ? 'Rol & Yetki Düzenleme: ' + role.name : 'Yeni Kurumsal Rol Tanımla' }}
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Geleceğe dönük modüler yetki matrisi üzerinden rolün sistemdeki tüm erişim kapsamını belirleyin.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                        Seçili: <strong>{{ form.permissions.length }}</strong> / {{ totalPermissionsCount }} Yetki
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- 1. Rol Adı & Temel Bilgiler Kartı -->
                <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700/60 shadow-xs">
                    <div class="max-w-xl">
                        <label class="block font-bold text-xs text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                            Rol Adı * (Örn: Admin, Satış Temsilcisi, İşletme Şefi, İdari İşler)
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="Rol adını girin..."
                            class="w-full text-sm font-bold rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:ring-indigo-500 focus:border-indigo-500 shadow-2xs"
                        />
                        <div v-if="form.errors.name" class="text-rose-600 text-xs mt-1.5 font-bold">
                            {{ form.errors.name }}
                        </div>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1.5 leading-relaxed">
                            Bu rol kullanıcılara veya iş akışı adımlarına atandığında aşağıdaki seçilen yetkiler anında devreye girer.
                        </p>
                    </div>
                </div>

                <!-- 2. Matris Kontrol ve Canlı Arama Çubuğu -->
                <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700/60 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="text-base">🧩</span>
                        <div>
                            <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">
                                Modüler Yetki Matrisi ({{ filteredModules.length }} Modül)
                            </h3>
                            <p class="text-[11px] text-slate-400">
                                Gelecekte eklenen modüller buraya otomatik entegre olur.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Arama Kutusu -->
                        <div class="relative min-w-[220px]">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Yetki veya modül ara..."
                                class="w-full text-xs py-1.5 pl-8 pr-3 rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 focus:ring-indigo-500 shadow-2xs"
                            />
                            <span class="absolute left-2.5 top-2 text-slate-400 text-xs">🔍</span>
                        </div>

                        <!-- Hızlı Hepsini Seç / Kaldır Butonları -->
                        <button
                            type="button"
                            @click="selectAllEverywhere"
                            class="px-3 py-1.5 text-xs font-bold text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 rounded-xl transition-colors border border-indigo-200 dark:border-indigo-800/60"
                        >
                            ✓ Tümünü Seç
                        </button>
                        <button
                            type="button"
                            @click="deselectAllEverywhere"
                            class="px-3 py-1.5 text-xs font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl transition-colors border border-slate-200 dark:border-slate-700"
                        >
                            ✕ Temizle
                        </button>
                    </div>
                </div>

                <!-- 3. DİNAMİK MODÜL KARTLARI GRİDİ (2 Kolon) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div
                        v-for="mod in filteredModules"
                        :key="mod.id"
                        class="bg-white dark:bg-slate-800 rounded-2xl border overflow-hidden shadow-xs hover:shadow-md transition-shadow flex flex-col justify-between"
                        :class="getModuleStyle(mod.badge_color).border"
                    >
                        <div>
                            <!-- Modül Kartı Başlığı -->
                            <div class="p-4 border-b flex items-start justify-between gap-3" :class="getModuleStyle(mod.badge_color).header">
                                <div class="flex items-start gap-2.5">
                                    <span class="text-xl p-1.5 rounded-xl bg-white dark:bg-slate-900 shadow-2xs shrink-0">
                                        {{ mod.icon }}
                                    </span>
                                    <div>
                                        <h4 class="font-extrabold text-xs tracking-tight flex items-center gap-2">
                                            {{ mod.title }}
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="getModuleStyle(mod.badge_color).badge">
                                                {{ selectedCountInModule(mod) }} / {{ mod.permissions.length }}
                                            </span>
                                        </h4>
                                        <p class="text-[11px] opacity-80 mt-0.5 leading-snug">
                                            {{ mod.description }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Modülün Tümünü Seç/Kaldır Butonu -->
                                <button
                                    type="button"
                                    @click="toggleModule(mod)"
                                    class="text-[11px] font-bold px-2 py-1 bg-white/70 dark:bg-slate-900/70 hover:bg-white dark:hover:bg-slate-900 rounded-lg transition-colors border border-current/20 shrink-0"
                                >
                                    {{ isModuleAllSelected(mod) ? 'Kaldır' : 'Tümünü Seç' }}
                                </button>
                            </div>

                            <!-- Modülün Yetki Listesi -->
                            <div class="p-4 space-y-3">
                                <div
                                    v-for="perm in mod.permissions"
                                    :key="perm.id"
                                    class="p-2.5 rounded-xl transition-colors flex items-start gap-3 hover:bg-slate-50 dark:hover:bg-slate-900/50 border border-transparent hover:border-slate-200/60 dark:hover:border-slate-700/50"
                                    :class="form.permissions.includes(perm.name) ? 'bg-indigo-50/30 dark:bg-indigo-950/20' : ''"
                                >
                                    <input
                                        type="checkbox"
                                        :id="'perm_' + perm.id"
                                        :value="perm.name"
                                        v-model="form.permissions"
                                        class="mt-0.5 rounded border-slate-300 dark:border-slate-700 transition-colors shadow-2xs"
                                        :class="getModuleStyle(mod.badge_color).ring"
                                    />
                                    <label :for="'perm_' + perm.id" class="flex-1 cursor-pointer select-none">
                                        <div class="text-xs font-bold text-slate-800 dark:text-slate-100 flex items-center justify-between">
                                            <span>{{ perm.label }}</span>
                                            <code class="text-[10px] font-mono text-slate-400 font-normal ml-2">
                                                {{ perm.name }}
                                            </code>
                                        </div>
                                        <p v-if="perm.description" class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed">
                                            {{ perm.description }}
                                        </p>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Kart Alt Durum Çizgisi -->
                        <div class="px-4 py-2 bg-slate-50 dark:bg-slate-900/40 border-t border-slate-100 dark:border-slate-800 text-[10px] text-slate-400 flex items-center justify-between">
                            <span>Modül Kodu: <strong>{{ mod.id }}</strong></span>
                            <span>{{ selectedCountInModule(mod) === mod.permissions.length ? '✅ Tam Yetkili' : (selectedCountInModule(mod) > 0 ? '⚡ Kısmi Yetki' : '🔒 Kapalı') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Boş Arama Sonucu -->
                <div v-if="filteredModules.length === 0" class="text-center py-12 bg-white dark:bg-slate-800 rounded-2xl border border-dashed border-slate-300 dark:border-slate-700">
                    <span class="text-3xl block mb-2">🔍</span>
                    <h4 class="text-sm font-bold text-slate-800 dark:text-slate-100">Aramanıza Uygun Yetki Bulunamadı</h4>
                    <p class="text-xs text-slate-400 mt-1">"{{ searchQuery }}" terimi ile eşleşen bir modül veya yetki kaydı yok.</p>
                </div>

                <!-- 4. Kaydet Butonu Çubuğu -->
                <div class="sticky bottom-4 bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-lg flex items-center justify-between gap-4 z-20">
                    <div class="text-xs text-slate-500 dark:text-slate-400 hidden sm:block">
                        Toplam <strong>{{ form.permissions.length }} adet</strong> operasyonel yetki bu role atanacak.
                    </div>

                    <div class="flex items-center gap-3 ml-auto">
                        <Link
                            :href="route('admin.roles.index')"
                            class="px-4 py-2 text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl transition-colors"
                        >
                            İptal
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-6 py-2.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-all shadow-md shadow-indigo-500/20 disabled:opacity-50 flex items-center gap-2"
                        >
                            <span>💾</span>
                            {{ form.processing ? 'Kaydediliyor...' : 'Rolü & Yetkileri Kaydet' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
