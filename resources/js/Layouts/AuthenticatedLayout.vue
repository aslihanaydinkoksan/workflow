<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import DevUserSwitcher from '@/Components/DevUserSwitcher.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';

const showingNavigationDropdown = ref(false);
const showPendingTasksToast = ref(false);
let pendingTasksToastTimer = null;

const page = usePage();
const user = computed(() => page.props.auth?.user || page.props.user);
const permissions = computed(() => page.props.auth?.permissions || []);
const userRoles = computed(() => {
    const roles = user.value?.roles || [];
    return roles.map(r => typeof r === 'string' ? r : r.name);
});
const ssoUrl = computed(() => page.props.centralSsoUrl);
const pendingTasksNotice = computed(() => page.props.flash?.pending_tasks_notice);

const hasPermission = (perm) => {
    if (userRoles.value.includes('Admin')) return true;
    
    const perms = page.props.auth?.permissions || page.props.user?.permissions || [];
    return perms.includes(perm);
};

// Kişisel ve Havuz Görev Sayıları
const pendingTasksCount = computed(() => page.props.pending_tasks_count ?? 0);
const totalPoolTasksCount = computed(() => page.props.total_pool_tasks_count ?? 0);
const overdueTasksCount = computed(() => page.props.overdue_tasks_count ?? 0);
const isAdminUser = computed(() => !!page.props.is_admin_user || userRoles.value.includes('Admin') || userRoles.value.includes('superadmin'));
const hasPendingTasks = computed(() => pendingTasksCount.value > 0);
const pendingFollowUpsCount = computed(() => page.props.pending_follow_ups_count ?? 0);
const hasPendingFollowUps = computed(() => pendingFollowUpsCount.value > 0);
const myRunningProcessesCount = computed(() => page.props.my_running_processes_count ?? 0);

// Akıllı ve Sakin İş Listesi Rozet Yönetimi (Alert Fatigue Önleyici)
const taskBadgeInfo = computed(() => {
    const personal = pendingTasksCount.value;
    const overdue = overdueTasksCount.value;
    const pool = totalPoolTasksCount.value;
    const isAdmin = isAdminUser.value;

    // Geciken görev varsa: Kırmızı alarm rengi yalnızca acil ve süresi aşılmış görevlere ayrılmıştır
    if (overdue > 0) {
        return {
            show: true,
            isOverdue: true,
            label: `${overdue} geciken`,
            tooltip: `${personal} bekleyen görev (${overdue} adedi terminini aştı!)`,
            classes: 'bg-rose-50 text-rose-700 border border-rose-300 font-bold shadow-xs',
            dotClass: 'bg-rose-500 animate-pulse'
        };
    }

    // Kişisel görevler varsa: Sakin, kurumsal slate/indigo rozet
    if (personal > 0) {
        return {
            show: true,
            isOverdue: false,
            label: personal,
            poolLabel: (isAdmin && pool > personal) ? `${pool} havuz` : null,
            tooltip: isAdmin ? `${personal} kişisel göreviniz · ${pool} şirket geneli havuz` : `${personal} bekleyen göreviniz`,
            classes: 'bg-slate-100 text-slate-700 border border-slate-200 hover:bg-slate-200 transition-colors font-semibold',
            dotClass: null
        };
    }

    // Admin kullanıcının kişisel görevi yoksa ancak şirket havuzunda işler varsa: Sakin gri havuz etiketi
    if (isAdmin && pool > 0) {
        return {
            show: true,
            isOverdue: false,
            label: `${pool} havuz`,
            tooltip: `Kişisel bekleyen göreviniz yok. Şirket genelinde ${pool} görev işlem bekliyor.`,
            classes: 'bg-slate-100 text-slate-500 border border-slate-200/80 font-normal text-[10px]',
            dotClass: null
        };
    }

    return { show: false };
});

// Bildirimler & Çan İkonu Yönetimi
const localUnreadCleared = ref(false);
const unreadNotificationsCount = computed(() => {
    if (localUnreadCleared.value) return 0;
    return page.props.unread_notifications_count ?? 0;
});
const recentNotifications = computed(() => page.props.recent_notifications ?? []);
const hasUnreadNotifications = computed(() => unreadNotificationsCount.value > 0);

watch(
    () => page.props.unread_notifications_count,
    () => {
        localUnreadCleared.value = false;
    }
);

// Çana tıklandığı an sayıyı sıfırla ve arka planda sessizce okundu yap
const handleBellClick = () => {
    if (unreadNotificationsCount.value > 0) {
        localUnreadCleared.value = true;
        if (Array.isArray(recentNotifications.value)) {
            recentNotifications.value.forEach(n => {
                if (!n.read_at) n.read_at = new Date().toISOString();
            });
        }
        axios.post(route('notifications.read-all'), {}, {
            headers: { 'Accept': 'application/json' }
        }).catch(() => {});
    }
};

const notificationTypeLabel = (notification) => getNotificationTypeLabel(notification);

const getNotificationTypeLabel = (notification) => {
    const reminderKey = notification.data?.reminder_key;

    if (notification.type === 'task_due_soon' && reminderKey?.startsWith('before_')) {
        const hours = notification.data?.hours_remaining;
        return hours === 1 ? '1 saat kaldı' : `${hours} saat kaldı`;
    }

    if (notification.type === 'task_overdue' && reminderKey === 'expired') {
        return 'Süresi doldu';
    }

    if (notification.type === 'task_overdue') {
        return 'Geciken görev';
    }

    switch (notification.type) {
        case 'task_assigned': return 'Yeni görev';
        case 'task_rejected': return 'Reddedildi';
        case 'task_due_soon': return 'Süre doluyor';
        case 'task_overdue': return 'Süresi doldu';
        case 'workflow_completed': return 'Tamamlandı';
        case 'sample_follow_up': return 'Süreç Takibi';
        case 'system': return 'Sistem Uyarısı';
        case 'delegation_assigned': return 'Vekalet';
        default: return 'Bildirim';
    }
};

const formatNotificationTime = (value) => {
    if (!value) return '';
    return new Date(value).toLocaleString('tr-TR', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const openNotification = (notification) => {
    if (!notification.read_at) {
        router.post(route('notifications.read', notification.id), {}, {
            preserveScroll: true,
            preserveState: true,
        });
    }

    const targetUrl = notification.link || notification.data?.url || notification.data?.action_url;

    if (targetUrl) {
        router.visit(targetUrl);
    }
};

const clearPendingTasksToastTimer = () => {
    if (pendingTasksToastTimer) {
        clearTimeout(pendingTasksToastTimer);
        pendingTasksToastTimer = null;
    }
};

const showPendingTasksAlert = () => {
    clearPendingTasksToastTimer();
    showPendingTasksToast.value = true;
    pendingTasksToastTimer = setTimeout(() => {
        showPendingTasksToast.value = false;
        pendingTasksToastTimer = null;
    }, 5000);
};
// Ad-Soyad veya Name varsa baş harfleri güvenlice çıkarır
const userInitials = computed(() => {
    if (!user.value) return 'US';

    // Veritabanınızdaki first_name ve last_name doluysa baş harflerini al
    if (user.value.first_name && user.value.last_name) {
        return (user.value.first_name.charAt(0) + user.value.last_name.charAt(0)).toUpperCase();
    }

    // Yoksa name kolonunu kontrol et
    if (user.value.name) {
        return String(user.value.name).substring(0, 2).toUpperCase();
    }

    return 'US';
});

// Ekranda gösterilecek isim (first_name + last_name öncelikli)
const displayUserName = computed(() => {
    if (!user.value) return 'Kullanıcı';

    if (user.value.first_name && user.value.last_name) {
        return `${user.value.first_name} ${user.value.last_name}`;
    }

    return user.value.name || 'Kullanıcı';
});

const displayRoleOrTitle = computed(() => {
    if (!user.value) return 'Personel';
    if (user.value.title) return user.value.title;
    if (userRoles.value.length > 0) return userRoles.value[0];
    return 'Personel';
});
watch(
    pendingTasksNotice,
    (message) => {
        if (message) {
            showPendingTasksAlert();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    clearPendingTasksToastTimer();
});

// --- GLOBAL VEKALET & İZİN YÖNETİMİ ---
const activeDelegationGiven = computed(() => page.props.active_delegation_given);
const activeDelegationsReceived = computed(() => page.props.active_delegations_received || []);
const allColleagues = computed(() => page.props.all_colleagues || []);

const showQuickDelegationModal = ref(false);
const quickDelegationForm = ref({
    delegatee_id: '',
    start_date: new Date().toISOString().split('T')[0],
    end_date: '',
    scope_type: 'all',
    reason: '',
});
const isSubmittingDelegation = ref(false);

const openQuickDelegationModal = () => {
    const today = new Date();
    const nextWeek = new Date();
    nextWeek.setDate(today.getDate() + 7);
    quickDelegationForm.value = {
        delegatee_id: '',
        start_date: today.toISOString().split('T')[0],
        end_date: nextWeek.toISOString().split('T')[0],
        scope_type: 'all',
        reason: 'Yıllık İzin / Şirket Dışı Görevlendirme',
    };
    showQuickDelegationModal.value = true;
};

const submitQuickDelegation = () => {
    if (!quickDelegationForm.value.delegatee_id || !quickDelegationForm.value.start_date || !quickDelegationForm.value.end_date) {
        alert('Lütfen vekil personel ve başlangıç-bitiş tarihlerini eksiksiz giriniz.');
        return;
    }
    isSubmittingDelegation.value = true;
    router.post(route('delegations.store'), quickDelegationForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            showQuickDelegationModal.value = false;
        },
        onFinish: () => {
            isSubmittingDelegation.value = false;
        },
    });
};

const cancelDelegation = (id) => {
    if (confirm('Aktif vekaletinizi sonlandırmak ve görevleri tekrar üzerinize almak istiyor musunuz?')) {
        router.delete(route('delegations.destroy', id), {
            preserveScroll: true,
        });
    }
};

const formatShortDate = (val) => {
    if (!val) return '';
    try {
        const d = new Date(val);
        return d.toLocaleDateString('tr-TR', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch {
        return val;
    }
};
</script>

<template>
    <div>
        <!-- Flash Messages Toast -->
        <div v-if="$page.props.flash?.success || $page.props.flash?.error || $page.props.flash?.warning || $page.props.flash?.info"
            class="fixed top-4 right-4 z-50 flex flex-col gap-2">

            <div v-if="$page.props.flash?.success"
                class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-lg max-w-md animate-fade-in-down flex items-start"
                role="alert">
                <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <div>
                    <p class="font-bold">Başarılı</p>
                    <p class="text-sm">{{ $page.props.flash.success }}</p>
                </div>
            </div>

            <div v-if="$page.props.flash?.error"
                class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-lg max-w-md animate-fade-in-down flex items-start"
                role="alert">
                <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                    <p class="font-bold">Hata</p>
                    <p class="text-sm">{{ $page.props.flash.error }}</p>
                </div>
            </div>

            <div v-if="$page.props.flash?.warning"
                class="bg-orange-100 border-l-4 border-orange-500 text-orange-700 p-4 rounded shadow-lg max-w-md animate-fade-in-down flex items-start"
                role="alert">
                <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                    </path>
                </svg>
                <div>
                    <p class="font-bold">Uyarı</p>
                    <p class="text-sm">{{ $page.props.flash.warning }}</p>
                </div>
            </div>

            <div v-if="$page.props.flash?.info"
                class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded shadow-lg max-w-md animate-fade-in-down flex items-start"
                role="alert">
                <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                    <p class="font-bold">Bilgi</p>
                    <p class="text-sm">{{ $page.props.flash.info }}</p>
                </div>
            </div>
        </div>

        <Transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-2">
            <div v-if="showPendingTasksToast && pendingTasksNotice"
                class="fixed bottom-4 right-4 z-50 max-w-sm rounded-lg border border-red-200 bg-white p-4 shadow-xl"
                role="alert">
                <div class="flex items-start gap-3">
                    <div
                        class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-900">Görev Bildirimi</p>
                        <p class="mt-1 text-sm text-gray-600">{{ pendingTasksNotice }}</p>
                        <Link :href="route('tasks.index')"
                            class="mt-2 inline-block text-sm font-medium text-red-600 hover:text-red-700"
                            @click="showPendingTasksToast = false">
                            Görevlerime git
                        </Link>
                    </div>
                    <button type="button" class="shrink-0 text-gray-400 hover:text-gray-600" aria-label="Kapat"
                        @click="showPendingTasksToast = false">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </Transition>

        <div class="min-h-screen bg-gray-50">
            <!-- Geliştirme & Test Ortamı İçin Rol Değiştirici -->
            <DevUserSwitcher />

            <!-- GLOBAL AKTİF VEKALET / İZİN BİLDİRİM BANNERI -->
            <div v-if="activeDelegationGiven"
                class="bg-gradient-to-r from-amber-500 via-orange-600 to-amber-600 text-white px-4 py-2.5 shadow-md flex flex-wrap items-center justify-between gap-3 text-xs border-b border-amber-600 z-50 relative">
                <div class="flex items-center gap-2.5">
                    <span class="text-lg animate-bounce">🏖️</span>
                    <div>
                        <span class="font-extrabold uppercase tracking-wide bg-amber-900/40 px-2 py-0.5 rounded text-[11px] mr-1.5">İzin & Vekalet Aktif</span>
                        <span>Görevleriniz <strong>{{ activeDelegationGiven.delegatee?.name }}</strong> personeline yönlendiriliyor (Bitiş: {{ formatShortDate(activeDelegationGiven.end_date) }}).</span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="cancelDelegation(activeDelegationGiven.id)"
                        class="px-3 py-1 bg-white hover:bg-amber-50 text-amber-900 font-bold rounded-lg shadow-xs text-xs transition flex items-center gap-1">
                        <span>❌</span> Vekaleti Sonlandır
                    </button>
                </div>
            </div>

            <div v-else-if="activeDelegationsReceived.length > 0"
                class="bg-gradient-to-r from-emerald-800 via-teal-900 to-slate-900 text-white px-4 py-2 shadow-xs flex flex-wrap items-center justify-between gap-3 text-xs border-b border-teal-700/60 z-50 relative">
                <div class="flex items-center gap-2">
                    <span class="text-base">💼</span>
                    <span>
                        <strong>Vekaleten İşlem Yetkisi:</strong>
                        <span v-for="(del, i) in activeDelegationsReceived" :key="del.id">
                            <strong>{{ del.delegator?.name }}</strong>{{ i < activeDelegationsReceived.length - 1 ? ', ' : '' }}
                        </span> adına vekaleten onay verme ve form doldurma yetkiniz bulunmaktadır.
                    </span>
                </div>
            </div>

            <!-- Modern Top Navigation -->
            <nav class="bg-white shadow-sm border-b border-gray-100 sticky top-0 z-40">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 sm:h-20 justify-between items-center">
                        <div class="flex items-center min-w-0">
                            <!-- Logo Area -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')" class="flex items-center gap-2 sm:gap-3 group">
                                    <ApplicationLogo class="h-10 sm:h-12 w-auto object-contain shrink-0" />
                                    <div class="shrink-0">
                                        <h1 class="text-lg sm:text-xl font-black tracking-tight text-gray-900 leading-none">KÖKSAN
                                        </h1>
                                        <span
                                            class="text-[9px] sm:text-[10px] font-bold text-indigo-600 tracking-widest uppercase">Süreç
                                            Portalı</span>
                                    </div>
                                </Link>
                            </div>

                            <!-- Desktop Navigation Links -->
                            <div class="hidden sm:-my-px sm:ml-10 sm:flex sm:space-x-8">
                                <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                    🏠 Ana Sayfa
                                </NavLink>

                                <NavLink :href="route('tasks.index')"
                                    :active="route().current('tasks.*')">
                                    <span class="inline-flex items-center">
                                        <span>📥 İş Listem</span>
                                        <template v-if="taskBadgeInfo.show">
                                            <span v-if="taskBadgeInfo.isOverdue"
                                                class="ml-1.5 inline-flex items-center gap-1 px-2 py-0.5 text-[11px] rounded-full"
                                                :class="taskBadgeInfo.classes"
                                                :title="taskBadgeInfo.tooltip">
                                                <span class="w-1.5 h-1.5 rounded-full" :class="taskBadgeInfo.dotClass"></span>
                                                <span>{{ taskBadgeInfo.label }}</span>
                                            </span>
                                            <span v-else
                                                class="ml-1.5 inline-flex items-center gap-1.5 px-2 py-0.5 text-[11px] rounded-full"
                                                :class="taskBadgeInfo.classes"
                                                :title="taskBadgeInfo.tooltip">
                                                <span>{{ taskBadgeInfo.label }}</span>
                                                <span v-if="taskBadgeInfo.poolLabel" class="text-[10px] text-slate-400 font-normal border-l border-slate-300 pl-1">
                                                    {{ taskBadgeInfo.poolLabel }}
                                                </span>
                                            </span>
                                        </template>
                                    </span>
                                </NavLink>

                                <NavLink v-if="hasPermission('start_processes')" :href="route('processes.index')"
                                    :active="route().current('processes.index') || route().current('processes.create')">
                                    🚀 Talep Başlat
                                </NavLink>

                                <NavLink :href="route('processes.history')"
                                    :active="route().current('processes.history') || route().current('processes.tracker')">
                                    📁 Taleplerim
                                    <span v-if="myRunningProcessesCount > 0" class="ml-1.5 px-2 py-0.5 text-[11px] font-bold bg-amber-100 text-amber-800 rounded-full">
                                        {{ myRunningProcessesCount }}
                                    </span>
                                </NavLink>

                                <NavLink :href="route('reports.index')"
                                    :active="route().current('reports.*')">
                                    📊 Raporlama & BI
                                </NavLink>

                                <NavLink v-if="hasPermission('duty_radar.view')"
                                    :href="route('duty-radar.index')"
                                    :active="route().current('duty-radar.*')">
                                    📍 Kim Nerede?
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:flex sm:items-center sm:ml-6 space-x-4">
                            <!-- Bildirimler -->
                            <div class="relative">
                                <Dropdown align="right" width="80">
                                    <template #trigger>
                                        <button type="button"
                                            @click="handleBellClick"
                                            class="relative inline-flex items-center justify-center rounded-full p-2 text-gray-500 transition hover:bg-gray-100 hover:text-indigo-600 focus:outline-none"
                                            aria-label="Bildirimler">
                                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                            </svg>
                                            <span v-if="hasUnreadNotifications"
                                                class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white transition-opacity">
                                                {{ unreadNotificationsCount > 9 ? '9+' : unreadNotificationsCount }}
                                            </span>
                                        </button>
                                    </template>
                                    <template #content>
                                        <div class="border-b border-gray-100 px-4 py-3">
                                            <div class="flex items-center justify-between gap-3">
                                                <div class="flex items-center gap-1.5">
                                                    <p class="text-sm font-bold text-gray-900">Bildirimler</p>
                                                    <span class="text-[10px] font-medium text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">✓ Güncel</span>
                                                </div>
                                                <Link :href="route('notifications.index')"
                                                    class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                                                    Tümünü gör
                                                </Link>
                                            </div>
                                        </div>

                                        <div v-if="recentNotifications.length === 0"
                                            class="px-4 py-6 text-center text-sm text-gray-500">
                                            Yeni bildirim yok.
                                        </div>

                                        <div v-else class="max-h-[70vh] overflow-y-auto w-80 sm:w-96">
                                            <button v-for="notification in recentNotifications" :key="notification.id"
                                                type="button"
                                                class="block w-full border-b border-gray-50 px-4 py-3 text-left transition hover:bg-gray-50"
                                                :class="!notification.read_at ? 'bg-indigo-50/40' : ''"
                                                @click="openNotification(notification)">
                                                <div class="flex items-start justify-between gap-2 mb-1">
                                                    <p
                                                        class="text-[11px] font-bold uppercase tracking-wider text-indigo-600">
                                                        {{ notificationTypeLabel(notification) }}
                                                    </p>
                                                    <span class="shrink-0 text-[10px] font-medium text-gray-400 mt-0.5">
                                                        {{ formatNotificationTime(notification.created_at) }}
                                                    </span>
                                                </div>

                                                <p
                                                    class="text-sm font-bold text-gray-900 whitespace-normal break-words leading-tight mt-1">
                                                    {{ notification.title }}
                                                </p>
                                                <p
                                                    class="mt-1.5 text-xs text-gray-600 whitespace-normal break-words leading-relaxed">
                                                    {{ notification.body }}
                                                </p>
                                            </button>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>

                            <!-- Admin Panel Dropdown -->
                            <div class="relative" v-if="hasPermission('view_admin_panel')">
                                <Dropdown align="right" width="56">
                                    <template #trigger>
                                        <button
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-bold rounded-full text-indigo-700 bg-indigo-50 hover:bg-indigo-100 hover:text-indigo-900 focus:outline-none transition-all duration-200">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1.5" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            ⚙️ Sistem Yönetimi
                                            <svg class="ml-1 -mr-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </template>
                                    <template #content>
                                        <div
                                            class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                            Ayarlar</div>
                                        <DropdownLink v-if="hasPermission('view_admin_panel')"
                                            :href="route('admin.settings.index')">Sistem Ayarları</DropdownLink>
                                        <DropdownLink v-if="hasPermission('view_admin_panel')"
                                            :href="route('admin.mail-settings.index')">📧 E-Posta Yönetimi</DropdownLink>
                                        <DropdownLink v-if="hasPermission('manage_users')"
                                            :href="route('admin.users.index')">Kullanıcılar</DropdownLink>
                                        <DropdownLink v-if="hasPermission('manage_roles')"
                                            :href="route('admin.roles.index')">Roller & Yetkiler</DropdownLink>
                                        <DropdownLink v-if="hasPermission('view_admin_panel')"
                                            :href="route('admin.audit-logs.index')">🛡️ Denetim İzi & Güvenlik Kokpiti</DropdownLink>
                                        <div class="border-t border-gray-100 my-1"></div>
                                        <div
                                            class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                            Süreç Mimarisi</div>
                                        <DropdownLink v-if="hasPermission('create_forms')"
                                            :href="route('form-templates.index')">Form Şablonları</DropdownLink>
                                        <DropdownLink v-if="hasPermission('manage_workflows')"
                                            :href="route('workflows.index')">Akış Tasarımcısı</DropdownLink>
                                        <DropdownLink v-if="hasPermission('manage_departments')"
                                            :href="route('admin.directorates.index')">Direktörlükler</DropdownLink>
                                        <DropdownLink v-if="hasPermission('manage_departments')"
                                            :href="route('admin.departments.index')">Departmanlar</DropdownLink>
                                        <DropdownLink v-if="hasPermission('manage_workflows')"
                                            :href="route('admin.workflow-categories.index')">Süreç Kategorileri
                                        </DropdownLink>
                                        <DropdownLink v-if="hasPermission('create_forms')"
                                            :href="route('admin.form-categories.index')">Form Kategorileri
                                        </DropdownLink>
                                        <DropdownLink v-if="hasPermission('duty_radar.manage_categories') || hasPermission('view_admin_panel')"
                                            :href="route('admin.duty-categories.index')">📍 Görev & Saha Türleri
                                        </DropdownLink>
                                        <DropdownLink :href="route('follow-ups.index')">⏱️ Numune & Süreç Takipleri (CRM)</DropdownLink>
                                        <div class="border-t border-gray-100 my-1"></div>
                                        <div
                                            class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                            Dinamik Organizasyon</div>
                                        <DropdownLink v-if="hasPermission('view_admin_panel')"
                                            :href="route('admin.tree-types.index')">Şema Tasarımcısı</DropdownLink>
                                        <DropdownLink v-if="hasPermission('view_admin_panel')"
                                            :href="route('admin.hierarchy.index')">Organizasyon Şeması</DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>

                            <!-- User Profile Dropdown -->
                            <div class="relative ml-3 border-l border-gray-200 pl-4" v-if="user">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <button
                                            class="flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 focus:outline-none transition duration-150 ease-in-out group">
                                            <div
                                                class="w-9 h-9 rounded-full bg-gray-200 border-2 border-white shadow-sm flex items-center justify-center text-gray-600 font-bold mr-2 group-hover:border-indigo-100 transition-colors">
                                                {{ userInitials }}
                                            </div>
                                            <div class="text-left hidden lg:block">
                                                <div class="text-sm font-bold text-gray-800 leading-tight">{{
                                                    displayUserName }}</div>
                                                <div class="text-xs text-gray-500">{{ displayRoleOrTitle }}</div>
                                            </div>
                                            <svg class="ml-2 h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </template>
                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')">Profilim</DropdownLink>
                                        <button type="button" @click="openQuickDelegationModal"
                                            class="block w-full px-4 py-2 text-left text-sm leading-5 text-amber-700 hover:bg-amber-50 font-semibold transition flex items-center gap-1.5">
                                            <span>🏖️</span> İzin & Vekalet Modu
                                        </button>
                                        <DropdownLink :href="`${ssoUrl}/profile`">Merkezi Ayarlar</DropdownLink>
                                        <div class="border-t border-gray-100"></div>
                                        <DropdownLink :href="route('logout')" method="post" as="button"
                                            class="text-red-600 hover:bg-red-50 hover:text-red-700 font-medium">Güvenli
                                            Çıkış</DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="flex items-center sm:hidden shrink-0 ml-2">
                            <button @click="showingNavigationDropdown = !showingNavigationDropdown"
                                class="inline-flex items-center justify-center p-2 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none transition duration-150 ease-in-out"
                                aria-label="Menüyü Aç/Kapat">
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        :class="{ 'hidden': showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }"
                                        stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16" />
                                    <path
                                        :class="{ 'hidden': !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                        stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{ 'block': showingNavigationDropdown, 'hidden': !showingNavigationDropdown }"
                    class="sm:hidden border-t border-gray-200">
                    <div class="pt-2 pb-3 space-y-1">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            🏠 Ana Sayfa
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('tasks.index')"
                            :active="route().current('tasks.*')">
                            <div class="flex items-center justify-between w-full">
                                <span>📥 İş Listem</span>
                                <template v-if="taskBadgeInfo.show">
                                    <span v-if="taskBadgeInfo.isOverdue"
                                        class="px-2 py-0.5 text-xs rounded-full inline-flex items-center gap-1"
                                        :class="taskBadgeInfo.classes">
                                        <span class="w-1.5 h-1.5 rounded-full" :class="taskBadgeInfo.dotClass"></span>
                                        <span>{{ taskBadgeInfo.label }}</span>
                                    </span>
                                    <span v-else
                                        class="px-2 py-0.5 text-xs rounded-full inline-flex items-center gap-1"
                                        :class="taskBadgeInfo.classes">
                                        <span>{{ taskBadgeInfo.label }}</span>
                                        <span v-if="taskBadgeInfo.poolLabel" class="text-[10px] opacity-70">
                                            ({{ taskBadgeInfo.poolLabel }})
                                        </span>
                                    </span>
                                </template>
                            </div>
                        </ResponsiveNavLink>

                        <ResponsiveNavLink v-if="hasPermission('start_processes')" :href="route('processes.index')"
                            :active="route().current('processes.index') || route().current('processes.create')">
                            🚀 Talep Başlat
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('processes.history')"
                            :active="route().current('processes.history') || route().current('processes.tracker')">
                            <div class="flex items-center justify-between w-full">
                                <span>📁 Taleplerim</span>
                                <span v-if="myRunningProcessesCount > 0" class="px-2 py-0.5 text-xs font-bold bg-amber-100 text-amber-800 rounded-full">
                                    {{ myRunningProcessesCount }}
                                </span>
                            </div>
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('reports.index')"
                            :active="route().current('reports.*')">
                            <div class="flex items-center justify-between w-full">
                                <span>📊 Raporlama & BI</span>
                            </div>
                        </ResponsiveNavLink>

                        <ResponsiveNavLink v-if="hasPermission('duty_radar.view')"
                            :href="route('duty-radar.index')"
                            :active="route().current('duty-radar.*')">
                            <div class="flex items-center justify-between w-full">
                                <span>📍 Kim Nerede?</span>
                            </div>
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('notifications.index')"
                            :active="route().current('notifications.*')">
                            <div class="flex items-center justify-between w-full">
                                <span>🔔 Bildirimler</span>
                                <span v-if="hasUnreadNotifications" class="px-2 py-0.5 text-xs font-bold bg-red-500 text-white rounded-full">
                                    {{ unreadNotificationsCount }}
                                </span>
                            </div>
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Admin Links -->
                    <div v-if="hasPermission('view_admin_panel')" class="pt-4 pb-1 border-t border-gray-200 bg-gray-50">
                        <div class="px-4 text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">⚙️ Sistem
                            Yönetimi</div>
                        <ResponsiveNavLink v-if="hasPermission('view_admin_panel')"
                            :href="route('admin.settings.index')">Sistem
                            Ayarları</ResponsiveNavLink>
                        <ResponsiveNavLink v-if="hasPermission('manage_users')" :href="route('admin.users.index')">
                            Kullanıcılar
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="hasPermission('manage_roles')" :href="route('admin.roles.index')">
                            Roller & Yetkiler
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="hasPermission('create_forms')" :href="route('form-templates.index')">
                            Form Şablonları
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="hasPermission('manage_workflows')" :href="route('workflows.index')">
                            Akış Tasarımcısı
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="hasPermission('manage_departments')"
                            :href="route('admin.directorates.index')">
                            Direktörlükler</ResponsiveNavLink>
                        <ResponsiveNavLink v-if="hasPermission('manage_departments')"
                            :href="route('admin.departments.index')">
                            Departmanlar</ResponsiveNavLink>
                        <ResponsiveNavLink v-if="hasPermission('manage_workflows')"
                            :href="route('admin.workflow-categories.index')">
                            Süreç Kategorileri</ResponsiveNavLink>
                        <ResponsiveNavLink v-if="hasPermission('create_forms')"
                            :href="route('admin.form-categories.index')">Form
                            Kategorileri</ResponsiveNavLink>
                        <ResponsiveNavLink v-if="hasPermission('duty_radar.manage_categories') || hasPermission('view_admin_panel')"
                            :href="route('admin.duty-categories.index')">📍 Görev & Saha Türleri</ResponsiveNavLink>
                        <ResponsiveNavLink v-if="hasPermission('view_admin_panel')"
                            :href="route('admin.tree-types.index')">Şema
                            Tasarımcısı</ResponsiveNavLink>
                        <ResponsiveNavLink v-if="hasPermission('view_admin_panel')"
                            :href="route('admin.hierarchy.index')">Organizasyon
                            Şeması</ResponsiveNavLink>
                    </div>

                    <div class="pt-4 pb-1 border-t border-gray-200" v-if="user">
                        <div class="px-4">
                            <div class="font-medium text-base text-gray-800">{{ user.name }}</div>
                            <div class="font-medium text-sm text-gray-500">{{ user.email }}</div>
                        </div>
                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">Profilim</ResponsiveNavLink>
                            <ResponsiveNavLink :href="`${ssoUrl}/profile`">Merkezi Ayarlar</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button" class="text-red-600">
                                Güvenli Çıkış
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header class="bg-white shadow-sm border-b border-gray-200" v-if="$slots.header">
                <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>

            <!-- HIZLI İZİN & VEKALET TANIMLAMA MODALI -->
            <div v-if="showQuickDelegationModal"
                class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
                <div class="relative bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden transform transition-all">
                    <!-- Modal Header -->
                    <div class="px-6 py-4 bg-gradient-to-r from-amber-500 to-orange-600 text-white flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="text-2xl">🏖️</span>
                            <div>
                                <h3 class="text-base font-extrabold text-white">İzin & Vekalet Tanımla</h3>
                                <p class="text-xs text-amber-100">İzin süreniz boyunca görevlerinizi devredin</p>
                            </div>
                        </div>
                        <button @click="showQuickDelegationModal = false" class="text-white/80 hover:text-white text-xl font-bold">&times;</button>
                    </div>

                    <!-- Modal Body -->
                    <form @submit.prevent="submitQuickDelegation" class="p-6 space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                Görevlerinizi Devralacak Vekil Personel <span class="text-rose-500">*</span>
                            </label>
                            <select v-model="quickDelegationForm.delegatee_id" required
                                class="w-full text-xs rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500 py-2">
                                <option value="" disabled>Personel seçiniz...</option>
                                <option v-for="c in allColleagues" :key="c.id" :value="c.id">
                                    {{ c.name }} ({{ c.title || c.email }})
                                </option>
                            </select>
                            <p class="mt-1 text-[11px] text-slate-400">İzinli olduğunuz süre boyunca onaylarınız bu kişiye iletilir.</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">
                                    Başlangıç Tarihi <span class="text-rose-500">*</span>
                                </label>
                                <input v-model="quickDelegationForm.start_date" type="date" required
                                    class="w-full text-xs rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500 py-1.5" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">
                                    Bitiş Tarihi <span class="text-rose-500">*</span>
                                </label>
                                <input v-model="quickDelegationForm.end_date" type="date" required
                                    class="w-full text-xs rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500 py-1.5" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                İzin Gerekçesi / Not
                            </label>
                            <input v-model="quickDelegationForm.reason" type="text"
                                placeholder="Örn: Yıllık İzin / Şirket Dışı Görevlendirme"
                                class="w-full text-xs rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500 py-1.5" />
                        </div>

                        <div class="p-3 bg-amber-50 rounded-xl border border-amber-200/80 text-[11px] text-amber-900 flex items-start gap-2">
                            <span class="text-base shrink-0">💡</span>
                            <span>İzin modunu dilediğiniz an sayfanın üstündeki turuncu bildirim çubuğundan tek tıkla sonlandırabilirsiniz.</span>
                        </div>

                        <!-- Modal Footer -->
                        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                            <button type="button" @click="showQuickDelegationModal = false"
                                class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition">
                                İptal
                            </button>
                            <button type="submit" :disabled="isSubmittingDelegation"
                                class="px-4 py-2 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white text-xs font-bold rounded-lg shadow-sm transition disabled:opacity-50 flex items-center gap-1.5">
                                <span v-if="isSubmittingDelegation">Kaydediliyor...</span>
                                <span v-else>🚀 İzin & Vekaleti Başlat</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>