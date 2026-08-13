<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, router, usePage } from '@inertiajs/vue3';

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
const pendingTasksCount = computed(() => page.props.pending_tasks_count ?? 0);
const hasPendingTasks = computed(() => pendingTasksCount.value > 0);
const unreadNotificationsCount = computed(() => page.props.unread_notifications_count ?? 0);
const recentNotifications = computed(() => page.props.recent_notifications ?? []);
const hasUnreadNotifications = computed(() => unreadNotificationsCount.value > 0);

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
            <!-- Modern Top Navigation -->
            <nav class="bg-white shadow-sm border-b border-gray-100 sticky top-0 z-40">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-20 justify-between">
                        <div class="flex">
                            <!-- Logo Area -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')" class="flex items-center gap-3 group">
                                    <div
                                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-600 to-purple-600 flex items-center justify-center shadow-md group-hover:shadow-lg transition-all duration-300 transform group-hover:scale-105">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-white" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h1 class="text-xl font-black tracking-tight text-gray-900 leading-none">KÖKSAN
                                        </h1>
                                        <span
                                            class="text-[10px] font-bold text-indigo-600 tracking-widest uppercase">Süreç
                                            Portalı</span>
                                    </div>
                                </Link>
                            </div>

                            <!-- Desktop Navigation Links -->
                            <div class="hidden sm:-my-px sm:ml-10 sm:flex sm:space-x-8">
                                <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                    🏠 Ana Sayfa
                                </NavLink>

                                <NavLink v-if="hasPermission('start_processes')" :href="route('processes.index')"
                                    :active="route().current('processes.index')">
                                    🚀 Yeni Talep Başlat
                                </NavLink>

                                <NavLink :href="route('tasks.index')"
                                    :active="route().current('tasks.index') || route().current('tasks.show')"
                                    :alert="hasPendingTasks">
                                    ✅ Bekleyen Onaylarım
                                </NavLink>

                                <NavLink :href="route('processes.history')"
                                    :active="route().current('processes.history')">
                                    📁 Benim Taleplerim
                                </NavLink>

                                <NavLink v-if="hasPermission('processes.view_department')"
                                    :href="route('processes.department')"
                                    :active="route().current('processes.department')">
                                    Bölüm Süreçleri
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:flex sm:items-center sm:ml-6 space-x-4">
                            <!-- Bildirimler -->
                            <div class="relative">
                                <Dropdown align="right" width="80">
                                    <template #trigger>
                                        <button type="button"
                                            class="relative inline-flex items-center justify-center rounded-full p-2 text-gray-500 transition hover:bg-gray-100 hover:text-indigo-600 focus:outline-none"
                                            aria-label="Bildirimler">
                                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                            </svg>
                                            <span v-if="hasUnreadNotifications"
                                                class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                                                {{ unreadNotificationsCount > 9 ? '9+' : unreadNotificationsCount }}
                                            </span>
                                        </button>
                                    </template>
                                    <template #content>
                                        <div class="border-b border-gray-100 px-4 py-3">
                                            <div class="flex items-center justify-between gap-3">
                                                <p class="text-sm font-bold text-gray-900">Bildirimler</p>
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
                                        <DropdownLink v-if="hasPermission('manage_users')"
                                            :href="route('admin.users.index')">Kullanıcılar</DropdownLink>
                                        <DropdownLink v-if="hasPermission('manage_roles')"
                                            :href="route('admin.roles.index')">Roller & Yetkiler</DropdownLink>
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
                                        <div class="border-t border-gray-100 my-1"></div>
                                        <div
                                            class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                            Dinamik Organizasyon</div>
                                        <DropdownLink v-if="hasPermission('view_admin_panel')"
                                            :href="route('admin.tree-types.index')">Şema Tasarımcısı</DropdownLink>
                                        <DropdownLink v-if="hasPermission('view_admin_panel')"
                                            :href="route('admin.hierarchy.test')">Organizasyon Şeması</DropdownLink>
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
                        <div class="-mr-2 flex items-center sm:hidden">
                            <button @click="showingNavigationDropdown = !showingNavigationDropdown"
                                class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
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
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">🏠 Ana
                            Sayfa
                        </ResponsiveNavLink>

                        <ResponsiveNavLink v-if="hasPermission('start_processes')" :href="route('processes.index')"
                            :active="route().current('processes.index')">🚀 Yeni Talep Başlat</ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('tasks.index')"
                            :active="route().current('tasks.index') || route().current('tasks.show')"
                            :alert="hasPendingTasks">
                            ✅ Bekleyen Onaylarım
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('processes.history')"
                            :active="route().current('processes.history')">📁 Benim
                            Taleplerim</ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('notifications.index')"
                            :active="route().current('notifications.*')">
                            🔔 Bildirimler
                        </ResponsiveNavLink>

                        <ResponsiveNavLink v-if="hasPermission('processes.view_department')"
                            :href="route('processes.department')" :active="route().current('processes.department')">
                            Bölüm Süreçleri</ResponsiveNavLink>
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
                        <ResponsiveNavLink v-if="hasPermission('view_admin_panel')"
                            :href="route('admin.tree-types.index')">Şema
                            Tasarımcısı</ResponsiveNavLink>
                        <ResponsiveNavLink v-if="hasPermission('view_admin_panel')"
                            :href="route('admin.hierarchy.test')">Organizasyon
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
        </div>
    </div>
</template>