<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    notifications: Object,
});

const typeMeta = {
    task_assigned: { label: 'Yeni görev', className: 'bg-indigo-100 text-indigo-700' },
    task_rejected: { label: 'Red', className: 'bg-red-100 text-red-700' },
    task_due_soon: { label: 'Süre doluyor', className: 'bg-amber-100 text-amber-700' },
    task_overdue: { label: 'Süresi doldu', className: 'bg-orange-100 text-orange-700' },
};

const getTypeMeta = (notification) => {
    const reminderKey = notification.data?.reminder_key;

    if (notification.type === 'task_due_soon' && reminderKey?.startsWith('before_')) {
        const hours = notification.data?.hours_remaining;
        const label = hours === 1 ? '1 saat kaldı' : `${hours} saat kaldı`;

        return { label, className: 'bg-amber-100 text-amber-700' };
    }

    if (notification.type === 'task_overdue' && reminderKey === 'expired') {
        return { label: 'Süresi doldu', className: 'bg-red-100 text-red-700' };
    }

    if (notification.type === 'task_overdue') {
        return { label: 'Geciken görev', className: 'bg-orange-100 text-orange-700' };
    }

    return typeMeta[notification.type] ?? { label: 'Bildirim', className: 'bg-gray-100 text-gray-700' };
};

const formatDate = (value) => {
    if (!value) return '';
    return new Date(value).toLocaleString('tr-TR');
};

const markAsRead = (notification) => {
    if (notification.read_at) {
        return;
    }

    router.post(route('notifications.read', notification.id), {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const markAllAsRead = () => {
    router.post(route('notifications.read-all'), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Bildirimler" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Bildirim Merkezi</h2>
                <button
                    v-if="notifications.data?.some((item) => !item.read_at)"
                    type="button"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                    @click="markAllAsRead"
                >
                    Tümünü okundu işaretle
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-xl border border-gray-100">
                    <div v-if="notifications.data.length === 0" class="p-10 text-center text-gray-500">
                        Henüz bildiriminiz yok.
                    </div>

                    <ul v-else class="divide-y divide-gray-100">
                        <li
                            v-for="notification in notifications.data"
                            :key="notification.id"
                            class="p-5 transition-colors"
                            :class="notification.read_at ? 'bg-white' : 'bg-indigo-50/40'"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="mb-2 flex flex-wrap items-center gap-2">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                            :class="getTypeMeta(notification).className"
                                        >
                                            {{ getTypeMeta(notification).label }}
                                        </span>
                                        <span class="text-xs text-gray-400">{{ formatDate(notification.created_at) }}</span>
                                        <span
                                            v-if="!notification.read_at"
                                            class="inline-flex h-2 w-2 rounded-full bg-indigo-500"
                                            aria-hidden="true"
                                        />
                                    </div>

                                    <h3 class="text-base font-semibold text-gray-900">{{ notification.title }}</h3>
                                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-600">{{ notification.body }}</p>

                                    <div class="mt-4 flex flex-wrap items-center gap-3">
                                        <Link
                                            v-if="notification.link || notification.data?.url || notification.data?.action_url"
                                            :href="notification.link || notification.data?.url || notification.data?.action_url"
                                            class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                            @click="markAsRead(notification)"
                                        >
                                            Detaya git
                                        </Link>
                                        <button
                                            v-if="!notification.read_at"
                                            type="button"
                                            class="text-sm text-gray-500 hover:text-gray-700"
                                            @click="markAsRead(notification)"
                                        >
                                            Okundu işaretle
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>

                    <div
                        v-if="notifications.links?.length > 3"
                        class="flex flex-wrap items-center justify-center gap-2 border-t border-gray-100 px-4 py-4"
                    >
                        <template v-for="(link, index) in notifications.links" :key="index">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="rounded-md px-3 py-1.5 text-sm"
                                :class="link.active ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="rounded-md bg-gray-50 px-3 py-1.5 text-sm text-gray-400"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
