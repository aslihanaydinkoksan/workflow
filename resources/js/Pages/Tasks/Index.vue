<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user || page.props.user);
const isAdmin = computed(() => {
    const roles = user.value?.roles || [];
    return roles.includes('Admin') || roles.includes('superadmin') || roles.some(r => r === 'Admin' || r.name === 'Admin');
});

// Admin Hızlı Kapsam Filtresi: 'assigned_to_me' (Bana Atananlar) veya 'all' (Tüm Şirket Havuzu)
const adminTaskScope = ref('all');

const myPersonalPendingCount = computed(() => {
    if (!props.tasks) return 0;
    return props.tasks.filter(t => t.assigned_to === user.value?.id).length;
});

const filteredPendingTasks = computed(() => {
    if (!props.tasks) return [];
    if (!isAdmin.value || adminTaskScope.value === 'all') {
        return props.tasks;
    }
    return props.tasks.filter(t => t.assigned_to === user.value?.id);
});

const props = defineProps({
    tasks: Array,
    completedTasks: Array,
    users: Array,
    given_delegations: Array,
    received_delegations: Array,
    pending_follow_ups: {
        type: Array,
        default: () => [],
    },
    active_workflows: {
        type: Array,
        default: () => [],
    },
    workflow_categories: {
        type: Array,
        default: () => [],
    },
});

const activeTab = ref('pending'); // 'pending' or 'completed'
const isDelegationPanelOpen = ref(false);

// Vekalet Formu (Fortune 500 Scoped Delegation)
const delegationForm = useForm({
    delegatee_id: '',
    start_date: '',
    end_date: '',
    scope_type: 'all', // 'all', 'category', 'workflow'
    scope_value: '',
    reason: '',
});

// Arama Kutusu (Kullanıcı Filtreleme)
const searchQuery = ref('');
const filteredUsers = computed(() => {
    if (!searchQuery.value) return props.users;
    return props.users.filter(u => u.name.toLowerCase().includes(searchQuery.value.toLowerCase()));
});

// Seçilen vekilin adını reaktif olarak UI'da göstermek için
const selectedDelegateeName = computed(() => {
    if (!delegationForm.delegatee_id) return null;
    const user = props.users.find(u => u.id === delegationForm.delegatee_id);
    return user ? user.name : null;
});

const submitDelegation = () => {
    delegationForm.post(route('delegations.store'), {
        onSuccess: () => {
            delegationForm.reset();
            searchQuery.value = '';
            isDelegationPanelOpen.value = false;
        }
    });
};

const isRevisionTask = (task) => {
    return !!(
        task.is_revision ||
        task.process_instance?.data?.isletme_karari === 'Revizyon İstendi' ||
        (task.title || '').toLowerCase().includes('reviz')
    );
};

const getBadgeColor = (task) => {
    if (typeof task === 'object' && isRevisionTask(task)) {
        return 'bg-orange-100 text-orange-800 border border-orange-300 font-bold';
    }
    const type = typeof task === 'object' ? task.type : task;
    switch (type) {
        case 'approval': return 'bg-yellow-100 text-yellow-800';
        case 'review': return 'bg-purple-100 text-purple-800';
        case 'form': return 'bg-blue-100 text-blue-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getTypeName = (task) => {
    if (typeof task === 'object' && isRevisionTask(task)) {
        return '🔄 Revizyon / Düzeltme';
    }
    const type = typeof task === 'object' ? task.type : task;
    switch (type) {
        case 'approval': return 'Onay Bekliyor';
        case 'review': return 'İnceleme (Bilgi)';
        case 'form': return 'Form Doldurma';
        default: return 'Görev';
    }
};

const getDueInfo = (task) => {
    if (!task.due_date) {
        return { label: 'Süre tanımsız', className: 'text-gray-400', sub: null };
    }

    const due = new Date(task.due_date);
    const now = new Date();
    const diffMs = due.getTime() - now.getTime();
    const sub = due.toLocaleString('tr-TR');

    if (diffMs < 0) {
        return { label: 'Süresi geçti', className: 'text-red-600 font-semibold', sub };
    }

    const totalMinutes = Math.floor(diffMs / 60000);
    const days = Math.floor(totalMinutes / (60 * 24));
    const hours = Math.floor((totalMinutes % (60 * 24)) / 60);
    const minutes = totalMinutes % 60;

    if (days > 0) {
        return { label: `${days} gün ${hours} saat kaldı`, className: 'text-amber-600 font-medium', sub };
    }

    if (hours > 0) {
        return { label: `${hours} saat ${minutes} dk kaldı`, className: 'text-amber-600 font-medium', sub };
    }

    return { label: `${Math.max(minutes, 1)} dk kaldı`, className: 'text-orange-600 font-medium', sub };
};
</script>

<template>

    <Head title="Görevlerim" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Görevlerim (Inbox)</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="flex justify-end mb-4">
                    <button @click="isDelegationPanelOpen = true"
                        class="inline-flex items-center px-4 py-2 bg-indigo-50 border border-indigo-200 rounded-lg shadow-sm text-sm font-medium text-indigo-700 hover:bg-indigo-100 hover:border-indigo-300 focus:outline-none transition-colors">
                        <span class="mr-2 text-lg">✈️</span> Vekalet Ayarları
                    </button>
                </div>
                <!-- Sekmeler (Tabs) -->
                <div class="mb-4 border-b border-gray-200">
                    <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" role="tablist">
                        <li class="mr-2" role="presentation">
                            <button @click="activeTab = 'pending'" :class="[
                                'inline-block p-4 border-b-2 rounded-t-lg transition-colors',
                                activeTab === 'pending' ? 'border-indigo-600 text-indigo-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300'
                            ]">
                                Bekleyen Görevler
                                <span class="ml-2 bg-indigo-100 text-indigo-800 py-0.5 px-2 rounded-full text-xs">{{
                                    filteredPendingTasks.length }}</span>
                            </button>
                        </li>
                        <li class="mr-2" role="presentation">
                            <button @click="activeTab = 'completed'" :class="[
                                'inline-block p-4 border-b-2 rounded-t-lg transition-colors',
                                activeTab === 'completed' ? 'border-indigo-600 text-indigo-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300'
                            ]">
                                Geçmişte Tamamlananlar
                                <span class="ml-2 bg-gray-100 text-gray-800 py-0.5 px-2 rounded-full text-xs">{{
                                    completedTasks.length }}</span>
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">

                        <!-- Pending Tasks Tab -->
                        <div v-if="activeTab === 'pending'">
                            <!-- Zamanı Gelen Numune / Süreç Takipleri -->
                            <div v-if="pending_follow_ups && pending_follow_ups.length > 0" class="mb-6 space-y-3">
                                <div v-for="fu in pending_follow_ups" :key="fu.id"
                                    class="p-4 bg-gradient-to-r from-sky-50 to-indigo-50 border border-sky-200 rounded-xl flex items-center justify-between shadow-sm">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-10 h-10 rounded-full bg-sky-100 text-sky-700 flex items-center justify-center font-bold text-lg shrink-0">
                                            🧪
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="text-xs font-bold px-2 py-0.5 rounded-full bg-sky-200 text-sky-800">Numune
                                                    & Süreç Takibi</span>
                                                <span class="text-xs text-gray-500">Talep #{{ fu.process_instance_id
                                                    }}</span>
                                            </div>
                                            <h4 class="text-sm font-bold text-gray-900 mt-0.5">{{ fu.title }}</h4>
                                            <p class="text-xs text-gray-600 mt-0.5">{{ fu.prompt }}</p>
                                        </div>
                                    </div>
                                    <Link :href="route('follow-ups.show', fu.id)"
                                        class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold rounded-lg shadow transition shrink-0 ml-4">
                                    Takibi Yanıtla →
                                    </Link>
                                </div>
                            </div>

                            <!-- Admin Kapsam Filtresi (Bana Atananlar vs Tüm Şirket Havuzu) -->
                            <div v-if="isAdmin" class="mb-4 p-3 bg-slate-50 border border-slate-200/80 rounded-xl flex flex-wrap items-center justify-between gap-3 shadow-xs">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">Görünüm:</span>
                                    <div class="inline-flex rounded-lg bg-slate-200/80 p-0.5">
                                        <button
                                            type="button"
                                            @click="adminTaskScope = 'assigned_to_me'"
                                            :class="[
                                                'px-3 py-1.5 text-xs font-bold rounded-md transition-all flex items-center gap-1.5',
                                                adminTaskScope === 'assigned_to_me' ? 'bg-white text-indigo-700 shadow-xs' : 'text-slate-600 hover:text-slate-900'
                                            ]"
                                        >
                                            <span>📌 Bana Atananlar</span>
                                            <span class="px-1.5 py-0.2 text-[10px] rounded-full" :class="adminTaskScope === 'assigned_to_me' ? 'bg-indigo-100 text-indigo-800 font-bold' : 'bg-slate-300 text-slate-700'">
                                                {{ myPersonalPendingCount }}
                                            </span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="adminTaskScope = 'all'"
                                            :class="[
                                                'px-3 py-1.5 text-xs font-bold rounded-md transition-all flex items-center gap-1.5',
                                                adminTaskScope === 'all' ? 'bg-white text-indigo-700 shadow-xs' : 'text-slate-600 hover:text-slate-900'
                                            ]"
                                        >
                                            <span>🏢 Tüm Şirket Havuzu</span>
                                            <span class="px-1.5 py-0.2 text-[10px] rounded-full" :class="adminTaskScope === 'all' ? 'bg-indigo-100 text-indigo-800 font-bold' : 'bg-slate-300 text-slate-700'">
                                                {{ tasks.length }}
                                            </span>
                                        </button>
                                    </div>
                                </div>
                                <span class="text-xs text-slate-500 font-medium">
                                    {{ adminTaskScope === 'assigned_to_me' ? 'Yalnızca doğrudan size atanmış kişisel görevler listeleniyor.' : 'Tüm departmanlara ait genel havuz listeleniyor.' }}
                                </span>
                            </div>

                            <div v-if="filteredPendingTasks.length === 0 && (!pending_follow_ups || pending_follow_ups.length === 0)" class="text-center py-8 text-gray-500">
                                <p class="text-sm font-medium">
                                    {{ adminTaskScope === 'assigned_to_me' ? 'Size doğrudan atanmış bekleyen bir görev bulunmuyor.' : 'Şu an bekleyen bir görev bulunmuyor. Harika! 🎉' }}
                                </p>
                                <button v-if="adminTaskScope === 'assigned_to_me' && tasks.length > 0"
                                    type="button"
                                    @click="adminTaskScope = 'all'"
                                    class="mt-2 text-xs font-semibold text-indigo-600 hover:text-indigo-800 underline">
                                    Tüm şirket havuzunu görüntüle ({{ tasks.length }} görev)
                                </button>
                            </div>

                            <div v-if="filteredPendingTasks.length > 0" class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Görev No</th>
                                            <th scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Süreç / Başlatan</th>
                                            <th scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Tip</th>
                                            <th scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Süre</th>
                                            <th scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Oluşturulma</th>
                                            <th scope="col"
                                                class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                İşlem</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="task in filteredPendingTasks" :key="task.id" class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">#{{ task.id }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-bold text-gray-900">{{
                                                    task.process_instance.workflow.name }}</div>
                                                <div v-if="task.title && !task.title.startsWith('node_')" class="text-xs font-semibold text-indigo-600 mt-0.5">
                                                    {{ task.title }}
                                                </div>
                                                <div class="text-xs text-gray-500 flex items-center mt-1">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z">
                                                        </path>
                                                    </svg>
                                                    {{ task.process_instance.starter?.name || 'Sistem' }}
                                                </div>
                                                <div v-if="task.process_instance?.data?.termin_araligi" class="inline-flex items-center gap-1.5 mt-1.5 px-2 py-0.5 rounded text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <span>⏱️ Termin:</span>
                                                    <span>{{ task.process_instance.data.termin_araligi }}</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span
                                                    :class="['px-2 inline-flex text-xs leading-5 font-semibold rounded-full', getBadgeColor(task)]">
                                                    {{ getTypeName(task) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div :class="['text-sm', getDueInfo(task).className]">
                                                    {{ getDueInfo(task).label }}
                                                </div>
                                                <div v-if="getDueInfo(task).sub" class="text-xs text-gray-400 mt-0.5">
                                                    Son: {{ getDueInfo(task).sub }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ new Date(task.created_at).toLocaleString() }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <Link :href="route('tasks.show', task.id)"
                                                    :class="[
                                                        'inline-flex items-center px-3.5 py-1.5 rounded-md transition-colors font-bold text-xs gap-1',
                                                        isRevisionTask(task)
                                                            ? 'bg-orange-500 hover:bg-orange-600 text-white shadow-sm'
                                                            : 'text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100'
                                                    ]">
                                                    <span>{{ isRevisionTask(task) ? '✏️ Revize Et' : 'Göreve Git' }}</span>
                                                    <span>&rarr;</span>
                                                </Link>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Completed Tasks Tab -->
                        <div v-if="activeTab === 'completed'">
                            <div v-if="completedTasks.length === 0" class="text-center py-8 text-gray-500">
                                Henüz tamamlanmış bir göreviniz bulunmuyor.
                            </div>

                            <div v-else class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Görev No</th>
                                            <th scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Süreç / Başlatan</th>
                                            <th scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                İşlem / Karar</th>
                                            <th scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Tamamlanma</th>
                                            <th scope="col"
                                                class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Aksiyon</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="task in completedTasks" :key="task.id"
                                            class="hover:bg-gray-50 opacity-80">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">#{{ task.id }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-bold text-gray-900">{{
                                                    task.process_instance.workflow.name }}</div>
                                                <div v-if="task.title && !task.title.startsWith('node_')" class="text-xs font-semibold text-indigo-600 mt-0.5">
                                                    {{ task.title }}
                                                </div>
                                                <div class="text-xs text-gray-500 flex items-center mt-1">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z">
                                                        </path>
                                                    </svg>
                                                    {{ task.process_instance.starter?.name || 'Sistem' }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">

                                                <template v-if="task.type === 'form'">
                                                    <span
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                        Form Gönderildi
                                                    </span>
                                                </template>

                                                <template v-else>
                                                    <span v-if="task.status === 'completed'"
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        Onaylandı
                                                    </span>
                                                    <span v-else-if="task.status === 'rejected'"
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                        Reddedildi
                                                    </span>
                                                    <span v-else-if="task.status === 'revised'"
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-orange-100 text-orange-800">
                                                        Revize İstendi
                                                    </span>
                                                    <span v-else
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                        {{ task.status }}
                                                    </span>
                                                </template>

                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ new Date(task.completed_at).toLocaleString() }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <Link :href="route('tasks.show', task.id)"
                                                    class="inline-flex items-center text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-md transition-colors font-bold">
                                                    Detaylar &rarr;
                                                </Link>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <div v-if="isDelegationPanelOpen" class="fixed inset-0 z-[60] overflow-hidden"
            aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                @click="isDelegationPanelOpen = false">
            </div>

            <div class="fixed inset-y-0 right-0 max-w-full flex">
                <div class="w-screen max-w-md transform transition-all duration-300 shadow-2xl">
                    <div class="h-full flex flex-col bg-white shadow-xl">

                        <div class="px-6 py-5 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                            <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <span>✈️</span> Vekalet Yönetimi
                            </h2>
                            <button @click="isDelegationPanelOpen = false"
                                class="text-gray-400 hover:text-gray-500 transition-colors">
                                <span class="sr-only">Kapat</span>
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="flex-1 overflow-y-auto p-6 space-y-8">

                            <div>
                                <h3
                                    class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4 border-b pb-2">
                                    Yeni
                                    Vekalet Ver</h3>
                                <form @submit.prevent="submitDelegation" class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Vekil (Yerime
                                            Bakacak
                                            Kişi)</label>
                                        <div class="relative">
                                            <input type="text" v-model="searchQuery" placeholder="Kişi Ara..."
                                                class="w-full text-sm border-gray-300 rounded-t-md border-b-0 focus:ring-indigo-500 focus:border-indigo-500">
                                            <select v-model="delegationForm.delegatee_id" size="4" required
                                                class="w-full text-sm border-gray-300 rounded-b-md focus:ring-indigo-500 focus:border-indigo-500 custom-scrollbar">
                                                <option v-for="user in filteredUsers" :key="user.id" :value="user.id"
                                                    class="py-1 px-2 border-b border-gray-50 hover:bg-indigo-50">{{
                                                    user.name }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div v-if="selectedDelegateeName"
                                        class="mt-3 p-3 bg-indigo-50 border border-indigo-200 rounded-lg flex items-center gap-2 text-sm text-indigo-800 font-bold shadow-sm transition-all duration-300">
                                        <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Seçilen Vekil: {{ selectedDelegateeName }}
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label
                                                class="block text-sm font-medium text-gray-700 mb-1">Başlangıç</label>
                                            <input type="date" v-model="delegationForm.start_date" required
                                                class="w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Bitiş</label>
                                            <input type="date" v-model="delegationForm.end_date" required
                                                class="w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                        </div>
                                    </div>

                                    <!-- Kapsam Seçimi (Scope) -->
                                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-200 space-y-2.5">
                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                                            Vekalet Kapsamı
                                        </label>
                                        <div class="grid grid-cols-3 gap-1.5 text-xs font-semibold">
                                            <button
                                                type="button"
                                                @click="delegationForm.scope_type = 'all'; delegationForm.scope_value = ''"
                                                class="p-2 rounded-lg border text-center transition-all"
                                                :class="delegationForm.scope_type === 'all' ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-100'"
                                            >
                                                🌐 Tüm Süreçler
                                            </button>
                                            <button
                                                type="button"
                                                @click="delegationForm.scope_type = 'category'"
                                                class="p-2 rounded-lg border text-center transition-all"
                                                :class="delegationForm.scope_type === 'category' ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-100'"
                                            >
                                                📁 Kategori
                                            </button>
                                            <button
                                                type="button"
                                                @click="delegationForm.scope_type = 'workflow'"
                                                class="p-2 rounded-lg border text-center transition-all"
                                                :class="delegationForm.scope_type === 'workflow' ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-100'"
                                            >
                                                📄 İş Akışı
                                            </button>
                                        </div>

                                        <!-- Kategori Seçici -->
                                        <div v-if="delegationForm.scope_type === 'category'" class="mt-2">
                                            <select
                                                v-model="delegationForm.scope_value"
                                                class="w-full text-xs rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500"
                                                required
                                            >
                                                <option value="">Kategori seçiniz...</option>
                                                <option v-for="cat in workflow_categories" :key="cat" :value="cat">
                                                    {{ cat }}
                                                </option>
                                            </select>
                                        </div>

                                        <!-- Akış Seçici -->
                                        <div v-if="delegationForm.scope_type === 'workflow'" class="mt-2">
                                            <select
                                                v-model="delegationForm.scope_value"
                                                class="w-full text-xs rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500"
                                                required
                                            >
                                                <option value="">İş akışı seçiniz...</option>
                                                <option v-for="wf in active_workflows" :key="wf.id" :value="String(wf.id)">
                                                    #{{ wf.id }} - {{ wf.name }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Vekalet Gerekçesi -->
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Gerekçe / Neden (Opsiyonel)</label>
                                        <input
                                            type="text"
                                            v-model="delegationForm.reason"
                                            placeholder="Örn: Yıllık İzin, Sağlık Raporu, Fuar Katılımı"
                                            class="w-full text-xs border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                        >
                                    </div>

                                    <button type="submit" :disabled="delegationForm.processing"
                                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50">
                                        {{ delegationForm.processing ? 'Kaydediliyor...' : 'Vekalet Tanımla' }}
                                    </button>
                                </form>
                            </div>

                            <div>
                                <h3
                                    class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4 border-b pb-2">
                                    Verdiğim Vekaletler</h3>
                                <div v-if="given_delegations.length === 0" class="text-sm text-gray-500 italic">Tanımlı
                                    vekaletiniz bulunmuyor.</div>
                                <div v-else class="space-y-3">
                                    <div v-for="del in given_delegations" :key="del.id"
                                        class="bg-gray-50 p-3 rounded-lg border border-gray-200 flex items-center justify-between">
                                        <div class="text-sm">
                                            <div class="font-bold text-gray-800 flex items-center gap-2">
                                                <span>{{ del.delegatee?.name }}</span>
                                                <span v-if="del.scope_type === 'category'" class="px-2 py-0.5 rounded text-[10px] font-black bg-purple-100 text-purple-800 border border-purple-200">
                                                    📁 {{ del.scope_value }}
                                                </span>
                                                <span v-else-if="del.scope_type === 'workflow'" class="px-2 py-0.5 rounded text-[10px] font-black bg-blue-100 text-blue-800 border border-blue-200">
                                                    📄 Akış #{{ del.scope_value }}
                                                </span>
                                                <span v-else class="px-2 py-0.5 rounded text-[10px] font-black bg-slate-100 text-slate-700 border border-slate-200">
                                                    🌐 Tüm Süreçler
                                                </span>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">{{ del.start_date }} <span
                                                    class="mx-1">→</span> {{ del.end_date }}</div>
                                            <div v-if="del.reason" class="text-[11px] text-gray-600 italic mt-0.5">
                                                "{{ del.reason }}"
                                            </div>
                                            <span :class="del.status === 'active' ? 'text-green-600' : 'text-red-500'"
                                                class="text-[10px] font-bold uppercase mt-1 block">{{ del.status }}</span>
                                        </div>
                                        <Link v-if="del.status === 'active'"
                                            :href="route('delegations.destroy', del.id)" method="delete" as="button"
                                            class="text-red-500 hover:text-red-700 hover:bg-red-50 p-2 rounded-md transition-colors"
                                            title="İptal Et">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                </path>
                                            </svg>
                                        </Link>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-blue-50 -mx-6 p-6 border-t border-blue-100 h-full">
                                <h3
                                    class="text-sm font-semibold text-blue-900 uppercase tracking-wider mb-4 border-b border-blue-200 pb-2">
                                    Devraldığım Vekaletler</h3>
                                <div v-if="received_delegations.length === 0" class="text-sm text-blue-500 italic">Şu an
                                    kimsenin yerine bakmıyorsunuz.</div>
                                <div v-else class="space-y-3">
                                    <div v-for="del in received_delegations" :key="del.id"
                                        class="bg-white p-3 rounded-lg border border-blue-200 shadow-sm">
                                        <div class="text-sm">
                                            <div class="flex items-center gap-1.5 mb-1">
                                                <span class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full font-bold inline-block">
                                                    Yerine Baktığınız:
                                                </span>
                                                <span v-if="del.scope_type === 'category'" class="px-2 py-0.5 rounded text-[10px] font-black bg-purple-100 text-purple-800">
                                                    📁 {{ del.scope_value }}
                                                </span>
                                                <span v-else-if="del.scope_type === 'workflow'" class="px-2 py-0.5 rounded text-[10px] font-black bg-blue-100 text-blue-800">
                                                    📄 Akış #{{ del.scope_value }}
                                                </span>
                                            </div>
                                            <div class="font-bold text-gray-800">{{ del.delegator?.name }}</div>
                                            <div class="text-xs text-gray-500 mt-1">{{ del.start_date }} <span
                                                    class="mx-1">→</span> {{ del.end_date }}</div>
                                            <div v-if="del.reason" class="text-[11px] text-gray-600 italic mt-0.5">
                                                "{{ del.reason }}"
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
