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

// RBAC İzin Kontrolleri (Spatie Yetki Matrisi /admin/roles üzerinden)
const canAdminEdit = computed(() => {
    const perms = page.props.auth?.permissions || [];
    const roles = user.value?.roles || [];
    return roles.includes('Admin') || roles.includes('superadmin') || perms.includes('tasks.admin_edit');
});

const canAdminDelete = computed(() => {
    const perms = page.props.auth?.permissions || [];
    const roles = user.value?.roles || [];
    return roles.includes('Admin') || roles.includes('superadmin') || perms.includes('tasks.admin_delete');
});

const canAdminBulkDelete = computed(() => {
    const perms = page.props.auth?.permissions || [];
    const roles = user.value?.roles || [];
    return roles.includes('Admin') || roles.includes('superadmin') || perms.includes('tasks.admin_bulk_delete') || perms.includes('tasks.admin_delete');
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
    all_users: {
        type: Array,
        default: () => [],
    },
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

// Çoklu Seçim & Toplu Karar (Bulk Approve / Reject - SAP MyInbox Standardı)
const selectedTaskIds = ref([]);
const isBulkModalOpen = ref(false);
const bulkActionType = ref('approve'); // 'approve' | 'reject'
const bulkComment = ref('');
const isBulkSubmitting = ref(false);

const eligibleSelectTasks = computed(() => {
    if (canAdminBulkDelete.value) {
        return filteredPendingTasks.value;
    }
    return filteredPendingTasks.value.filter(t => t.type === 'approval');
});

const isAllSelected = computed(() => {
    return eligibleSelectTasks.value.length > 0 &&
        eligibleSelectTasks.value.every(t => selectedTaskIds.value.includes(t.id));
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedTaskIds.value = [];
    } else {
        selectedTaskIds.value = eligibleSelectTasks.value.map(t => t.id);
    }
};

const toggleTaskSelection = (taskId) => {
    const idx = selectedTaskIds.value.indexOf(taskId);
    if (idx > -1) {
        selectedTaskIds.value.splice(idx, 1);
    } else {
        selectedTaskIds.value.push(taskId);
    }
};

// Admin Tekil Düzenleme Modalı
const isEditModalOpen = ref(false);
const selectedTaskForEdit = ref(null);
const isEditSubmitting = ref(false);
const editForm = useForm({
    assigned_to: '',
    due_date: '',
    status: 'pending',
    comment: '',
});

const openEditModal = (task) => {
    selectedTaskForEdit.value = task;
    let formattedDue = '';
    if (task.due_date) {
        const d = new Date(task.due_date);
        // yyyy-MM-ddThh:mm formatı datetime-local için
        const pad = (n) => String(n).padStart(2, '0');
        formattedDue = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }
    editForm.assigned_to = task.assigned_to || '';
    editForm.due_date = formattedDue;
    editForm.status = task.status || 'pending';
    editForm.comment = '';
    isEditModalOpen.value = true;
};

const submitEdit = () => {
    if (!selectedTaskForEdit.value) return;
    isEditSubmitting.value = true;
    editForm.put(route('tasks.admin-update', selectedTaskForEdit.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            isEditModalOpen.value = false;
            selectedTaskForEdit.value = null;
            isEditSubmitting.value = false;
        },
        onError: () => {
            isEditSubmitting.value = false;
        }
    });
};

// Admin Tekil Silme Modalı (Soft Delete)
const isDeleteModalOpen = ref(false);
const selectedTaskForDelete = ref(null);
const isDeleteSubmitting = ref(false);
const deleteForm = useForm({
    delete_scope: 'process', // 'process' | 'task'
});

const openDeleteModal = (task) => {
    selectedTaskForDelete.value = task;
    deleteForm.delete_scope = 'process';
    isDeleteModalOpen.value = true;
};

const submitDelete = () => {
    if (!selectedTaskForDelete.value) return;
    isDeleteSubmitting.value = true;
    deleteForm.delete(route('tasks.admin-destroy', selectedTaskForDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            selectedTaskForDelete.value = null;
            isDeleteSubmitting.value = false;
        },
        onError: () => {
            isDeleteSubmitting.value = false;
        }
    });
};

// Admin Toplu Silme Modalı (Bulk Soft Delete)
const isBulkDeleteModalOpen = ref(false);
const isBulkDeleteSubmitting = ref(false);
const bulkDeleteForm = useForm({
    task_ids: [],
    delete_scope: 'process', // 'process' | 'task'
});

const openBulkDeleteModal = () => {
    bulkDeleteForm.task_ids = [...selectedTaskIds.value];
    bulkDeleteForm.delete_scope = 'process';
    isBulkDeleteModalOpen.value = true;
};

const submitBulkDelete = () => {
    if (bulkDeleteForm.task_ids.length === 0) return;
    isBulkDeleteSubmitting.value = true;
    bulkDeleteForm.post(route('tasks.admin-bulk-destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            isBulkDeleteModalOpen.value = false;
            selectedTaskIds.value = [];
            isBulkDeleteSubmitting.value = false;
        },
        onError: () => {
            isBulkDeleteSubmitting.value = false;
        }
    });
};

const openBulkModal = (type) => {
    bulkActionType.value = type;
    bulkComment.value = '';
    isBulkModalOpen.value = true;
};


const submitBulkAction = () => {
    if (selectedTaskIds.value.length === 0) return;
    isBulkSubmitting.value = true;
    useForm({
        task_ids: selectedTaskIds.value,
        action: bulkActionType.value,
        comment: bulkComment.value,
    }).post(route('tasks.bulk-action'), {
        onSuccess: () => {
            selectedTaskIds.value = [];
            isBulkModalOpen.value = false;
            isBulkSubmitting.value = false;
        },
        onError: () => {
            isBulkSubmitting.value = false;
        }
    });
};

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
                                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-10">
                                                <input 
                                                    v-if="eligibleSelectTasks.length > 0"
                                                    type="checkbox" 
                                                    :checked="isAllSelected" 
                                                    @change="toggleSelectAll" 
                                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4 cursor-pointer"
                                                    title="Tüm Görevleri Seç / Kaldır"
                                                />

                                            </th>
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
                                        <tr v-for="task in filteredPendingTasks" :key="task.id" :class="[selectedTaskIds.includes(task.id) ? 'bg-indigo-50/70' : 'hover:bg-gray-50']">
                                            <td class="px-4 py-4 whitespace-nowrap text-center text-sm">
                                                <input 
                                                    v-if="task.type === 'approval' || canAdminBulkDelete"
                                                    type="checkbox" 
                                                    :checked="selectedTaskIds.includes(task.id)" 
                                                    @change="toggleTaskSelection(task.id)"
                                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4 cursor-pointer"
                                                />

                                                <span v-else class="text-gray-300 text-xs">-</span>
                                            </td>
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
                                                <div class="flex items-center justify-end gap-1.5">
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
                                                    <button 
                                                        v-if="canAdminEdit"
                                                        type="button"
                                                        @click="openEditModal(task)"
                                                        class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition"
                                                        title="Görevi Düzenle (Admin)"
                                                    >
                                                        ✏️
                                                    </button>
                                                    <button 
                                                        v-if="canAdminDelete"
                                                        type="button"
                                                        @click="openDeleteModal(task)"
                                                        class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                                        title="Görevi / Süreci Sil (Soft Delete)"
                                                    >
                                                        🗑️
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Toplu Karar Merkezi (Floating Action Bar - SAP MyInbox) -->
                            <div v-if="selectedTaskIds.length > 0" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-slate-900/95 backdrop-blur-md text-white px-6 py-3.5 rounded-2xl shadow-2xl border border-slate-700 flex items-center gap-4 animate-in fade-in slide-in-from-bottom duration-300">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-600 text-white text-xs font-black">
                                        {{ selectedTaskIds.length }}
                                    </span>
                                    <span class="text-xs font-bold text-slate-200">Onay Görevi Seçildi</span>
                                </div>
                                <div class="h-5 w-px bg-slate-700"></div>
                                <button 
                                    type="button" 
                                    @click="openBulkModal('approve')" 
                                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-black shadow-md flex items-center gap-1.5 transition active:scale-95 cursor-pointer"
                                >
                                    <span>✅</span>
                                    <span>Toplu Onayla</span>
                                </button>
                                <button 
                                    type="button" 
                                    @click="openBulkModal('reject')" 
                                    class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-black shadow-md flex items-center gap-1.5 transition active:scale-95 cursor-pointer"
                                >
                                    <span>❌</span>
                                    <span>Toplu Reddet</span>
                                </button>
                                <button 
                                    v-if="canAdminBulkDelete"
                                    type="button" 
                                    @click="openBulkDeleteModal" 
                                    class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-xl text-xs font-black shadow-md flex items-center gap-1.5 transition active:scale-95 cursor-pointer"
                                >
                                    <span>🗑️</span>
                                    <span>Toplu Sil (Soft Delete)</span>
                                </button>

                                <button 
                                    type="button" 
                                    @click="selectedTaskIds = []" 
                                    class="text-xs text-slate-400 hover:text-white underline ml-1 cursor-pointer"
                                >
                                    Vazgeç
                                </button>
                            </div>

                            <!-- Toplu Karar Onay Modalı -->
                            <div v-if="isBulkModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                                <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100">
                                    <div class="flex items-center gap-3 mb-4">
                                        <div :class="['w-10 h-10 rounded-xl flex items-center justify-center text-lg', bulkActionType === 'approve' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700']">
                                            {{ bulkActionType === 'approve' ? '✅' : '❌' }}
                                        </div>
                                        <div>
                                            <h3 class="font-bold text-gray-900 text-base">
                                                {{ bulkActionType === 'approve' ? 'Toplu Onaylama Kararı' : 'Toplu Ret Kararı' }}
                                            </h3>
                                            <p class="text-xs text-gray-500">
                                                Seçilen {{ selectedTaskIds.length }} adet görev tek seferde işlenecektir.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="mb-4 bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs text-slate-700 space-y-1">
                                        <div class="flex justify-between">
                                            <span class="text-slate-500">Seçilen Görevler:</span>
                                            <span class="font-bold">#{{ selectedTaskIds.join(', #') }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-slate-500">Yetkili / Onaylayan:</span>
                                            <span class="font-bold">{{ user.name }}</span>
                                        </div>
                                    </div>

                                    <div class="mb-5">
                                        <label class="block text-xs font-bold text-gray-700 mb-1.5">
                                            Toplu Karar Notu (Opsiyonel):
                                        </label>
                                        <textarea 
                                            v-model="bulkComment" 
                                            rows="2" 
                                            class="w-full text-xs rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                            :placeholder="bulkActionType === 'approve' ? 'Talepler incelendi ve toplu onaylandı...' : 'Toplu ret gerekçesi...'"
                                        ></textarea>
                                    </div>

                                    <div class="flex justify-end gap-2.5">
                                        <button 
                                            type="button" 
                                            @click="isBulkModalOpen = false" 
                                            class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-xl"
                                        >
                                            İptal
                                        </button>
                                        <button 
                                            type="button" 
                                            @click="submitBulkAction" 
                                            :disabled="isBulkSubmitting"
                                            :class="['px-5 py-2 text-xs font-bold text-white rounded-xl shadow-md transition flex items-center gap-1.5', bulkActionType === 'approve' ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-rose-600 hover:bg-rose-500', isBulkSubmitting ? 'opacity-50' : '']"
                                        >
                                            <span v-if="isBulkSubmitting">İşleniyor...</span>
                                            <span v-else>{{ bulkActionType === 'approve' ? '🚀 Topluca Onayla' : '🛑 Topluca Reddet' }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <!-- Admin Görev Düzenleme Modalı -->
                            <div v-if="isEditModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                                <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-gray-100 animate-in fade-in duration-200">
                                    <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-4">
                                        <div class="flex items-center gap-2.5">
                                            <span class="text-xl">✏️</span>
                                            <div>
                                                <h3 class="text-sm font-bold text-gray-900">Görev Yönetici Düzenleme</h3>
                                                <p class="text-xs text-gray-500">Görev No: #{{ selectedTaskForEdit?.id }} · Süreç: {{ selectedTaskForEdit?.process_instance?.workflow?.name }}</p>
                                            </div>
                                        </div>
                                        <button @click="isEditModalOpen = false" class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
                                    </div>

                                    <form @submit.prevent="submitEdit" class="space-y-4">
                                        <div>
                                            <label class="block text-xs font-bold text-gray-700 mb-1">Atanan Personel / Yetkili:</label>
                                            <select 
                                                v-model="editForm.assigned_to" 
                                                class="w-full text-xs rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                <option value="">Atama Yapılmadı / Havuzda</option>
                                                <option v-for="u in (props.all_users && props.all_users.length > 0 ? props.all_users : props.users)" :key="u.id" :value="u.id">
                                                    {{ u.name }} (ID: {{ u.id }})
                                                </option>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-bold text-gray-700 mb-1">Son Teslim Tarihi / Vade (Due Date):</label>
                                            <input 
                                                type="datetime-local" 
                                                v-model="editForm.due_date"
                                                class="w-full text-xs rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                            />
                                        </div>

                                        <div>
                                            <label class="block text-xs font-bold text-gray-700 mb-1">Görev Durumu:</label>
                                            <select 
                                                v-model="editForm.status" 
                                                class="w-full text-xs rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                <option value="pending">Bekliyor (pending)</option>
                                                <option value="completed">Tamamlandı (completed)</option>
                                                <option value="cancelled">İptal Edildi (cancelled)</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-bold text-gray-700 mb-1">Yönetici Notu / Gerekçe (İsteğe Bağlı):</label>
                                            <textarea 
                                                v-model="editForm.comment" 
                                                rows="2" 
                                                class="w-full text-xs rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="Müdahale gerekçesi, audit günlüğüne işlenecektir..."
                                            ></textarea>
                                        </div>

                                        <div class="flex justify-end gap-2.5 pt-3 border-t border-gray-100">
                                            <button 
                                                type="button" 
                                                @click="isEditModalOpen = false" 
                                                class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-xl"
                                            >
                                                Vazgeç
                                            </button>
                                            <button 
                                                type="submit" 
                                                :disabled="isEditSubmitting"
                                                class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-500 rounded-xl shadow-md transition disabled:opacity-50"
                                            >
                                                <span v-if="isEditSubmitting">Kaydediliyor...</span>
                                                <span v-else>💾 Değişiklikleri Kaydet</span>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Admin Görev / Süreç Silme Modalı (Soft Delete) -->
                            <div v-if="isDeleteModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                                <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 animate-in fade-in duration-200">
                                    <div class="flex items-center gap-3 mb-3">
                                        <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-xl shrink-0 font-bold">
                                            🗑️
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-bold text-gray-900">Görevi / Süreci Sil (Soft Delete)</h3>
                                            <p class="text-xs text-gray-500">Görev No: #{{ selectedTaskForDelete?.id }} · Talep No: #{{ selectedTaskForDelete?.process_instance_id }}</p>
                                        </div>
                                    </div>

                                    <div class="p-3 bg-amber-50/80 rounded-xl border border-amber-200 text-[11px] text-amber-800 mb-4 leading-relaxed">
                                        🛡️ <strong>Güvenli Soft Delete:</strong> Bu kayıtlar veritabanından kalıcı olarak yok edilmez. <code>deleted_at</code> damgası ile güvenle arşivlenir. Gerekirse sistem yöneticisi tarafından kurtarılabilir.
                                    </div>

                                    <div class="space-y-2.5 mb-5">
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Silme Kapsamını Seçin:</label>
                                        
                                        <label :class="['flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition', deleteForm.delete_scope === 'process' ? 'border-rose-300 bg-rose-50/50' : 'border-gray-200 hover:bg-gray-50']">
                                            <input type="radio" value="process" v-model="deleteForm.delete_scope" class="mt-0.5 text-rose-600 focus:ring-rose-500" />
                                            <div>
                                                <div class="text-xs font-bold text-gray-900">🚀 Bağlı Test Sürecini ve Tüm Görevlerini Arşivle</div>
                                                <div class="text-[11px] text-gray-500">Süreci (#{{ selectedTaskForDelete?.process_instance_id }}) ve ona ait bekleyen tüm görevleri temizler (Önerilen).</div>
                                            </div>
                                        </label>

                                        <label :class="['flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition', deleteForm.delete_scope === 'task' ? 'border-rose-300 bg-rose-50/50' : 'border-gray-200 hover:bg-gray-50']">
                                            <input type="radio" value="task" v-model="deleteForm.delete_scope" class="mt-0.5 text-rose-600 focus:ring-rose-500" />
                                            <div>
                                                <div class="text-xs font-bold text-gray-900">📋 Yalnızca Bu Tekil Görevi Arşivle</div>
                                                <div class="text-[11px] text-gray-500">Süreç açık kalmaya devam eder, yalnızca bu personelin görevi soft delete yapılır.</div>
                                            </div>
                                        </label>
                                    </div>

                                    <div class="flex justify-end gap-2.5 pt-3 border-t border-gray-100">
                                        <button 
                                            type="button" 
                                            @click="isDeleteModalOpen = false" 
                                            class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-xl"
                                        >
                                            Vazgeç
                                        </button>
                                        <button 
                                            type="button" 
                                            @click="submitDelete"
                                            :disabled="isDeleteSubmitting"
                                            class="px-5 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-500 rounded-xl shadow-md transition disabled:opacity-50"
                                        >
                                            <span v-if="isDeleteSubmitting">Siliniyor...</span>
                                            <span v-else>🗑️ Güvenle Sil (Soft Delete)</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Admin Toplu Silme Modalı (Bulk Soft Delete) -->
                            <div v-if="isBulkDeleteModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                                <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 animate-in fade-in duration-200">
                                    <div class="flex items-center gap-3 mb-3">
                                        <div class="w-10 h-10 rounded-xl bg-red-50 text-red-600 flex items-center justify-center text-xl shrink-0 font-bold">
                                            🧹
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-bold text-gray-900">Toplu Görev & Test Temizliği</h3>
                                            <p class="text-xs text-gray-500">Seçilen {{ selectedTaskIds.length }} adet görev soft delete yapılacak</p>
                                        </div>
                                    </div>

                                    <div class="p-3 bg-amber-50/80 rounded-xl border border-amber-200 text-[11px] text-amber-800 mb-4 leading-relaxed">
                                        🛡️ <strong>Güvenli Soft Delete:</strong> Seçtiğiniz tüm kayıtlar geri alınabilir şekilde arşivlenir. Sunucuda biriken test süreçlerini tek seferde temizlemek için idealdir.
                                    </div>

                                    <div class="space-y-2.5 mb-5">
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Silme Kapsamı:</label>
                                        
                                        <label :class="['flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition', bulkDeleteForm.delete_scope === 'process' ? 'border-red-300 bg-red-50/50' : 'border-gray-200 hover:bg-gray-50']">
                                            <input type="radio" value="process" v-model="bulkDeleteForm.delete_scope" class="mt-0.5 text-red-600 focus:ring-red-500" />
                                            <div>
                                                <div class="text-xs font-bold text-gray-900">🚀 Bağlı Tüm Test Süreçlerini Arşivle</div>
                                                <div class="text-[11px] text-gray-500">Seçilen görevlerin ait olduğu süreçleri ve alt görevlerini kökten temizler.</div>
                                            </div>
                                        </label>

                                        <label :class="['flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition', bulkDeleteForm.delete_scope === 'task' ? 'border-red-300 bg-red-50/50' : 'border-gray-200 hover:bg-gray-50']">
                                            <input type="radio" value="task" v-model="bulkDeleteForm.delete_scope" class="mt-0.5 text-red-600 focus:ring-red-500" />
                                            <div>
                                                <div class="text-xs font-bold text-gray-900">📋 Yalnızca Seçilen Görevleri Arşivle</div>
                                                <div class="text-[11px] text-gray-500">Süreçler silinmez, yalnızca seçilen görev kayıtları soft delete yapılır.</div>
                                            </div>
                                        </label>
                                    </div>

                                    <div class="flex justify-end gap-2.5 pt-3 border-t border-gray-100">
                                        <button 
                                            type="button" 
                                            @click="isBulkDeleteModalOpen = false" 
                                            class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-xl"
                                        >
                                            Vazgeç
                                        </button>
                                        <button 
                                            type="button" 
                                            @click="submitBulkDelete"
                                            :disabled="isBulkDeleteSubmitting"
                                            class="px-5 py-2 text-xs font-bold text-white bg-red-600 hover:bg-red-500 rounded-xl shadow-md transition disabled:opacity-50"
                                        >
                                            <span v-if="isBulkDeleteSubmitting">Temizleniyor...</span>
                                            <span v-else>🗑️ {{ selectedTaskIds.length }} Kaydı Güvenle Sil</span>
                                        </button>
                                    </div>
                                </div>
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
                                                <div class="flex items-center justify-end gap-1.5">
                                                    <Link :href="route('tasks.show', task.id)"
                                                        class="inline-flex items-center text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-md transition-colors font-bold text-xs">
                                                        Detaylar &rarr;
                                                    </Link>
                                                    <button 
                                                        v-if="canAdminDelete"
                                                        type="button"
                                                        @click="openDeleteModal(task)"
                                                        class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                                        title="Görevi / Süreci Sil (Soft Delete)"
                                                    >
                                                        🗑️
                                                    </button>
                                                </div>
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
