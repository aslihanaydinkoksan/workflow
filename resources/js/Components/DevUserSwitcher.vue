<script setup>
import { computed, ref, onMounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const page = usePage();
const devMode = computed(() => page.props.dev_mode ?? false);
const allUsers = computed(() => page.props.dev_users ?? []);
const allDepartments = computed(() => page.props.dev_departments ?? []);
const allRoles = computed(() => page.props.dev_roles ?? []);
const currentUser = computed(() => page.props.auth?.user || page.props.user);

const isCollapsed = ref(false);
const isModalOpen = ref(false);
const searchQuery = ref('');
const selectedDepartment = ref('all');
const selectedRole = ref('all');
const activeTab = ref('all'); // 'all', 'departments', 'roles'
const isSwitchingId = ref(null);

onMounted(() => {
    const saved = localStorage.getItem('dev_switcher_collapsed');
    if (saved !== null) {
        isCollapsed.value = saved === 'true';
    }
});

const toggleCollapse = () => {
    isCollapsed.value = !isCollapsed.value;
    localStorage.setItem('dev_switcher_collapsed', String(isCollapsed.value));
};

// Sık kullanılan test profilleri (varsa listeden otomatik bulunur)
const quickUsers = computed(() => {
    const targetEmails = [
        'ahmet_aslan@gmail.com',      // Satışçı (Ahmet Aslan)
        'aykut.celik@koksan.com',     // Levha Müdürü (Aykut Çelik)
        'mustafa.yilmaz@koksan.com',  // Levha Kalite (Mustafa Yılmaz)
        'admin@test.com',             // Sistem Yöneticisi
        'serkan.telek@koksan.com',    // Levha Şefi
        'idari.isler.test@koksan.com',// İdari İşler
        'muhasebe.test@koksan.com',   // Muhasebe
        'gmy.test@koksan.com'         // GMY
    ];

    return targetEmails.map(email => allUsers.value.find(u => u.email === email)).filter(Boolean);
});

// DİNAMİK DEPARTMAN LİSTESİ VE KULLANICI SAYILARI (Veritabanından otomatik)
const dynamicDepartments = computed(() => {
    const deptMap = new Map();

    // Önce kullanıcıların bağlı olduğu departmanları say
    allUsers.value.forEach(u => {
        const d = u.department || 'Departmansız / Tanımsız';
        deptMap.set(d, (deptMap.get(d) || 0) + 1);
    });

    // Sistemde kayıtlı tüm departmanları da ekle (varsa 0 olarak)
    allDepartments.value.forEach(d => {
        if (!deptMap.has(d.name)) {
            deptMap.set(d.name, 0);
        }
    });

    // Kullanıcısı olanları öne al, alfabetik sırala
    return Array.from(deptMap.entries())
        .map(([name, count]) => ({ name, count }))
        .sort((a, b) => b.count - a.count || a.name.localeCompare(b.name, 'tr'));
});

// DİNAMİK ROL LİSTESİ VE KULLANICI SAYILARI (Veritabanından otomatik)
const dynamicRoles = computed(() => {
    const roleMap = new Map();

    // Kullanıcıların rollerini say
    allUsers.value.forEach(u => {
        if (u.roles && u.roles.length > 0) {
            u.roles.forEach(r => {
                roleMap.set(r, (roleMap.get(r) || 0) + 1);
            });
        } else {
            roleMap.set('Rolsüz', (roleMap.get('Rolsüz') || 0) + 1);
        }
    });

    // Sistemdeki tüm rolleri de ekle
    allRoles.value.forEach(r => {
        if (!roleMap.has(r.name)) {
            roleMap.set(r.name, 0);
        }
    });

    return Array.from(roleMap.entries())
        .map(([name, count]) => ({ name, count }))
        .sort((a, b) => b.count - a.count || a.name.localeCompare(b.name, 'tr'));
});

// Arama ve dinamik filtreleme mantığı (100% veritabanı odaklı)
const filteredUsers = computed(() => {
    let list = allUsers.value;

    // Departman filtresi
    if (selectedDepartment.value !== 'all') {
        if (selectedDepartment.value === 'Departmansız / Tanımsız') {
            list = list.filter(u => !u.department);
        } else {
            list = list.filter(u => u.department === selectedDepartment.value);
        }
    }

    // Rol filtresi
    if (selectedRole.value !== 'all') {
        if (selectedRole.value === 'Rolsüz') {
            list = list.filter(u => !u.roles || u.roles.length === 0);
        } else {
            list = list.filter(u => u.roles?.includes(selectedRole.value));
        }
    }

    // Metin araması (İsim, e-posta, unvan, departman, rol)
    if (!searchQuery.value.trim()) return list;

    const q = searchQuery.value.toLowerCase().trim();
    return list.filter(u => 
        u.name?.toLowerCase().includes(q) ||
        u.email?.toLowerCase().includes(q) ||
        u.title?.toLowerCase().includes(q) ||
        u.department?.toLowerCase().includes(q) ||
        u.roles?.some(r => r.toLowerCase().includes(q))
    );
});

const resetFilters = () => {
    selectedDepartment.value = 'all';
    selectedRole.value = 'all';
    searchQuery.value = '';
    activeTab.value = 'all';
};

const hasActiveFilter = computed(() => {
    return selectedDepartment.value !== 'all' || selectedRole.value !== 'all' || searchQuery.value.trim() !== '';
});

const switchUser = (user) => {
    if (!user || user.id === currentUser.value?.id) return;

    isSwitchingId.value = user.id;
    router.post(route('dev.switch-user', { id: user.id }), {}, {
        preserveScroll: true,
        onFinish: () => {
            isSwitchingId.value = null;
            isModalOpen.value = false;
        }
    });
};
</script>

<template>
    <div v-if="devMode" class="dev-switcher-wrapper font-sans text-xs">
        <!-- Collapsed Floating Button (Sayfanın Sol Altında Minimalist Hap) -->
        <div v-if="isCollapsed" class="fixed bottom-4 left-4 z-50">
            <button 
                @click="toggleCollapse"
                class="flex items-center gap-2 px-3 py-2 bg-slate-900/95 hover:bg-slate-800 text-white rounded-full shadow-2xl border border-slate-700/80 backdrop-blur transition-all duration-200 hover:scale-105 active:scale-95 group"
                title="Kullanıcı & Rol Değiştiriciyi Aç"
            >
                <span class="flex h-2.5 w-2.5 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                </span>
                <span class="font-semibold text-slate-300">Rol:</span>
                <span class="font-bold text-amber-300 truncate max-w-[130px]">
                    {{ currentUser?.name || 'Giriş Yapılmadı' }}
                </span>
                <span class="text-slate-400 text-[10px] group-hover:text-white">▲ Değiştir</span>
            </button>
        </div>

        <!-- Expanded Top Bar (Navigasyonun Üstünde Kurumsal Test Çubuğu) -->
        <div v-else class="bg-gradient-to-r from-slate-950 via-slate-900 to-indigo-950 text-white border-b border-slate-800 shadow-md relative z-40 transition-all duration-300">
            <div class="max-w-7xl mx-auto px-4 py-2 flex flex-wrap items-center justify-between gap-2">
                
                <!-- Sol Kısım: Aktif Profil Rozeti -->
                <div class="flex items-center gap-2.5">
                    <div class="flex items-center gap-1.5 px-2.5 py-1 rounded bg-slate-800/80 border border-slate-700/80">
                        <span class="flex h-2 w-2 relative">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        <span class="text-[11px] font-medium text-slate-400">Aktif Oturum:</span>
                        <span class="font-bold text-emerald-400 text-sm">
                            {{ currentUser?.name || 'Misafir' }}
                        </span>
                        <span v-if="currentUser?.roles?.length" class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                            {{ currentUser.roles.join(', ') }}
                        </span>
                        <span v-if="currentUser?.title" class="text-[10px] text-slate-400 hidden sm:inline">
                            ({{ currentUser.title }})
                        </span>
                    </div>
                </div>

                <!-- Orta Kısım: Hızlı Geçiş Hapları (Quick Presets) -->
                <div class="flex items-center gap-1.5 overflow-x-auto py-0.5 max-w-full">
                    <span class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase mr-1 hidden lg:inline">
                        Hızlı Geçiş:
                    </span>
                    
                    <button 
                        v-for="u in quickUsers" 
                        :key="u.id"
                        @click="switchUser(u)"
                        :disabled="isSwitchingId !== null || u.id === currentUser?.id"
                        :class="[
                            'px-2.5 py-1 rounded-md text-[11px] font-medium transition-all duration-150 flex items-center gap-1 whitespace-nowrap border',
                            u.id === currentUser?.id 
                                ? 'bg-emerald-950/80 text-emerald-300 border-emerald-600/60 font-bold shadow-sm' 
                                : 'bg-slate-800/90 text-slate-300 border-slate-700 hover:bg-slate-700 hover:text-white active:scale-95'
                        ]"
                    >
                        <span v-if="isSwitchingId === u.id" class="animate-spin text-xs">⏳</span>
                        <span>{{ u.name.split(' ')[0] }}</span>
                        <span class="text-[9px] opacity-70">
                            ({{ u.roles?.[0] || u.title?.substring(0, 8) || 'Rol' }})
                        </span>
                        <span v-if="u.id === currentUser?.id" class="text-emerald-400 font-bold">✓</span>
                    </button>
                </div>

                <!-- Sağ Kısım: Tüm Kullanıcılar Modalı ve Gizle Butonu -->
                <div class="flex items-center gap-2">
                    <button 
                        @click="isModalOpen = true"
                        class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white rounded-md text-[11px] font-bold flex items-center gap-1.5 shadow-sm transition-all"
                    >
                        <span>🔍 Tüm Roller & Departmanlar</span>
                        <span class="bg-indigo-800/80 px-1.5 py-0.2 rounded text-[10px]">{{ allUsers.length }} Kişi</span>
                    </button>

                    <button 
                        @click="toggleCollapse"
                        class="p-1 text-slate-400 hover:text-white hover:bg-slate-800 rounded transition"
                        title="Çubuğu Küçült (Sol altta hap haline getir)"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                </div>

            </div>
        </div>

        <!-- TÜM KULLANICILAR & ROLLER ARAMA VE SEÇİM MODALI -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/75 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-4xl shadow-2xl overflow-hidden flex flex-col max-h-[88vh] animate-fade-in">
                
                <!-- Modal Başlığı -->
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/80">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-600/30 border border-indigo-500/40 flex items-center justify-center text-indigo-400 text-xl">
                            👥
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-white leading-tight">
                                    Dinamik Kullanıcı, Rol & Departman Seçici
                                </h3>
                                <span class="px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-[10px] font-bold">
                                    {{ allUsers.length }} Personel
                                </span>
                                <span class="px-2 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 text-[10px] font-bold">
                                    {{ dynamicDepartments.filter(d => d.count > 0).length }} Aktif Departman
                                </span>
                            </div>
                            <p class="text-xs text-slate-400 mt-0.5">
                                Sisteme yeni kullanıcı, departman veya rol eklendiğinde burada anında otomatik listelenir.
                            </p>
                        </div>
                    </div>
                    <button 
                        @click="isModalOpen = false" 
                        class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition text-sm"
                    >
                        ✕
                    </button>
                </div>

                <!-- Arama ve Dinamik Filtre Barı -->
                <div class="p-4 bg-slate-900 border-b border-slate-800 flex flex-col gap-3">
                    
                    <!-- Arama Çubuğu -->
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            🔍
                        </span>
                        <input 
                            v-model="searchQuery"
                            type="text" 
                            placeholder="İsim, e-posta, unvan, departman (örn: Preform, Levha, OPEX) veya rol ara..." 
                            class="w-full pl-9 pr-4 py-2 bg-slate-950 border border-slate-700 rounded-xl text-white placeholder-slate-500 text-xs focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
                            autofocus
                        />
                        <button 
                            v-if="searchQuery" 
                            @click="searchQuery = ''" 
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-white"
                        >
                            ✕
                        </button>
                    </div>

                    <!-- Dinamik Açılır Menüler (Departman & Rol Seçimi) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                        
                        <!-- 1. DİNAMİK DEPARTMAN SEÇİCİ -->
                        <div class="flex items-center gap-2 bg-slate-950/70 border border-slate-800 rounded-xl px-3 py-1.5">
                            <span class="text-slate-400 font-semibold whitespace-nowrap text-[11px]">🏢 Departman:</span>
                            <select 
                                v-model="selectedDepartment"
                                class="w-full bg-transparent text-white text-xs outline-none cursor-pointer focus:ring-0 border-0 py-0"
                            >
                                <option value="all" class="bg-slate-900 text-white">Tüm Departmanlar ({{ allUsers.length }})</option>
                                <option 
                                    v-for="dept in dynamicDepartments" 
                                    :key="dept.name" 
                                    :value="dept.name"
                                    class="bg-slate-900 text-white"
                                    :disabled="dept.count === 0"
                                >
                                    {{ dept.name }} ({{ dept.count }} kişi)
                                </option>
                            </select>
                        </div>

                        <!-- 2. DİNAMİK ROL SEÇİCİ -->
                        <div class="flex items-center gap-2 bg-slate-950/70 border border-slate-800 rounded-xl px-3 py-1.5">
                            <span class="text-slate-400 font-semibold whitespace-nowrap text-[11px]">🛡️ Yetki / Rol:</span>
                            <select 
                                v-model="selectedRole"
                                class="w-full bg-transparent text-white text-xs outline-none cursor-pointer focus:ring-0 border-0 py-0"
                            >
                                <option value="all" class="bg-slate-900 text-white">Tüm Roller ({{ allUsers.length }})</option>
                                <option 
                                    v-for="role in dynamicRoles" 
                                    :key="role.name" 
                                    :value="role.name"
                                    class="bg-slate-900 text-white"
                                    :disabled="role.count === 0"
                                >
                                    {{ role.name }} ({{ role.count }} kişi)
                                </option>
                            </select>
                        </div>

                    </div>

                    <!-- Otomatik Oluşan Dinamik Departman Etiketleri (Hızlı Filtre Çipleri) -->
                    <div class="flex items-center justify-between gap-2 pt-1 border-t border-slate-800/80">
                        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 max-w-[80%]">
                            <button 
                                @click="resetFilters"
                                :class="[
                                    'px-2.5 py-1 rounded-lg text-[11px] font-bold border transition whitespace-nowrap',
                                    !hasActiveFilter ? 'bg-indigo-600 text-white border-indigo-500' : 'bg-slate-800 text-slate-400 border-slate-700 hover:text-white'
                                ]"
                            >
                                Tümü ({{ allUsers.length }})
                            </button>

                            <!-- Personeli olan tüm departmanlar dinamik chip olarak listelenir -->
                            <button 
                                v-for="dept in dynamicDepartments.filter(d => d.count > 0)" 
                                :key="dept.name"
                                @click="selectedDepartment = selectedDepartment === dept.name ? 'all' : dept.name"
                                :class="[
                                    'px-2 py-1 rounded-lg text-[11px] border transition whitespace-nowrap flex items-center gap-1',
                                    selectedDepartment === dept.name 
                                        ? 'bg-indigo-600 text-white border-indigo-500 font-bold' 
                                        : 'bg-slate-800/90 text-slate-300 border-slate-700/80 hover:bg-slate-700 hover:text-white'
                                ]"
                            >
                                <span>{{ dept.name }}</span>
                                <span class="px-1 rounded-full text-[9px] bg-slate-700 text-slate-300">{{ dept.count }}</span>
                            </button>
                        </div>

                        <!-- Sıfırlama ve Sayaç -->
                        <div class="flex items-center gap-2 whitespace-nowrap">
                            <span class="text-[11px] text-slate-400">
                                <strong>{{ filteredUsers.length }}</strong> / {{ allUsers.length }} kişi
                            </span>
                            <button 
                                v-if="hasActiveFilter"
                                @click="resetFilters"
                                class="px-2 py-0.5 rounded bg-rose-500/20 text-rose-300 border border-rose-500/30 text-[10px] hover:bg-rose-500/30 transition"
                            >
                                ✕ Sıfırla
                            </button>
                        </div>
                    </div>

                </div>

                <!-- Kullanıcı Listesi -->
                <div class="flex-1 overflow-y-auto p-4 space-y-2">
                    <div v-if="filteredUsers.length === 0" class="text-center py-12 text-slate-500">
                        <div class="text-3xl mb-2">🔍</div>
                        <p class="font-medium text-slate-400">Seçilen filtre veya arama kriterine uygun kullanıcı bulunamadı.</p>
                        <button 
                            @click="resetFilters"
                            class="mt-3 px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold"
                        >
                            Filtreleri Temizle
                        </button>
                    </div>

                    <div 
                        v-for="u in filteredUsers" 
                        :key="u.id"
                        @click="switchUser(u)"
                        :class="[
                            'flex items-center justify-between p-3 rounded-xl border transition-all cursor-pointer group',
                            u.id === currentUser?.id 
                                ? 'bg-emerald-950/40 border-emerald-500/50 shadow-sm ring-1 ring-emerald-500/30' 
                                : 'bg-slate-850 bg-slate-800/50 border-slate-750 border-slate-700/60 hover:bg-slate-800 hover:border-indigo-500/50'
                        ]"
                    >
                        <div class="flex items-center gap-3">
                            <!-- Avatar / Baş harfler -->
                            <div :class="[
                                'w-9 h-9 rounded-xl flex items-center justify-center font-bold text-xs border',
                                u.id === currentUser?.id ? 'bg-emerald-600 text-white border-emerald-400' : 'bg-slate-700 text-slate-200 border-slate-600 group-hover:border-indigo-400 group-hover:text-white'
                            ]">
                                {{ u.name.substring(0, 2).toUpperCase() }}
                            </div>

                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-white text-xs group-hover:text-indigo-300 transition">
                                        {{ u.name }}
                                    </span>
                                    <span v-if="u.id === currentUser?.id" class="px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 text-[9px] font-bold">
                                        ŞU ANKİ OTURUM
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 mt-0.5 text-[11px] text-slate-400">
                                    <span>{{ u.email }}</span>
                                    <span v-if="u.department" class="text-slate-500">•</span>
                                    <span v-if="u.department" class="text-indigo-300 font-medium">{{ u.department }}</span>
                                    <span v-if="u.title" class="text-slate-500">•</span>
                                    <span v-if="u.title" class="text-slate-400 italic">{{ u.title }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Sağ Taraf: Rol Etiketleri ve Buton -->
                        <div class="flex items-center gap-2">
                            <div class="flex flex-wrap gap-1 justify-end max-w-[220px]">
                                <span 
                                    v-for="role in u.roles" 
                                    :key="role" 
                                    class="px-2 py-0.5 rounded bg-slate-700/80 text-slate-300 text-[10px] font-medium border border-slate-600/60"
                                >
                                    {{ role }}
                                </span>
                                <span v-if="!u.roles?.length && u.title" class="px-2 py-0.5 rounded bg-slate-800 text-slate-400 text-[10px]">
                                    {{ u.title }}
                                </span>
                            </div>

                            <button 
                                :disabled="isSwitchingId !== null || u.id === currentUser?.id"
                                :class="[
                                    'px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1',
                                    u.id === currentUser?.id 
                                        ? 'bg-emerald-600/30 text-emerald-300 cursor-default' 
                                        : 'bg-indigo-600 group-hover:bg-indigo-500 text-white shadow-sm'
                                ]"
                            >
                                <span v-if="isSwitchingId === u.id" class="animate-spin text-xs">⏳</span>
                                <span v-else-if="u.id === currentUser?.id">Aktif ✓</span>
                                <span v-else>Geçiş Yap →</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Modal Alt Barı -->
                <div class="px-6 py-3 border-t border-slate-800 bg-slate-950/80 flex items-center justify-between text-[11px] text-slate-400">
                    <div>
                        <span>⚡ <strong>Otomatik Keşif:</strong> İleride yeni bir departman veya rol oluşturulduğunda hiçbir kod yazmadan bu panelde kendiliğinden yer alır.</span>
                    </div>
                    <button 
                        @click="isModalOpen = false"
                        class="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition"
                    >
                        Kapat
                    </button>
                </div>

            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.98); }
    to { opacity: 1; transform: scale(1); }
}
.animate-fade-in {
    animation: fadeIn 0.15s ease-out forwards;
}
</style>
