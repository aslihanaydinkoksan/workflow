<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    templates: Array,
    filters: Object
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');

// Filtre değiştiğinde otomatik arama yap
watch([search, status], () => {
    router.get(route('form-templates.index'), {
        search: search.value,
        status: status.value
    }, {
        preserveState: true,
        replace: true
    });
});

const form = useForm({});

const toggleStatus = (id) => {
    form.post(route('form-templates.toggle-status', id), {
        preserveScroll: true
    });
};

const deleteTemplate = (id) => {
    if (confirm('Bu form şablonunu silmek istediğinize emin misiniz? (Bağlı olduğu süreçler bozulabilir)')) {
        form.delete(route('form-templates.destroy', id), {
            preserveScroll: true
        });
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('tr-TR', {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
    });
};
</script>

<template>
    <Head title="Form Şablonları" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Form Şablonları</h2>
                <Link :href="route('form-templates.create')" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded shadow">
                    + Yeni Form Tasarla
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Filtreleme Alanı -->
                <div class="bg-white p-4 rounded-lg shadow-sm mb-6 flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4 items-center">
                    <div class="w-full sm:w-1/2">
                        <input type="text" v-model="search" placeholder="Form adı ile ara..." class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div class="w-full sm:w-1/4">
                        <select v-model="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">Tümü (Aktif/Pasif)</option>
                            <option value="active">Sadece Aktifler</option>
                            <option value="passive">Sadece Pasifler</option>
                        </select>
                    </div>
                    <div class="w-full sm:w-1/4 text-right">
                        <button @click="search=''; status=''" class="text-sm text-gray-500 hover:text-gray-700">Filtreleri Temizle</button>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 mt-4">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12">#</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Form Adı</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bağlı Akışlar</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Oluşturan</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Oluşturulma Tarihi</th>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Durum</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="(template, index) in templates" :key="template.id" class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-medium">{{ index + 1 }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-bold text-gray-900">{{ template.name }}</div>
                                            <div class="text-xs text-gray-500 truncate w-48" :title="template.description">{{ template.description || 'Açıklama yok' }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div v-if="template.primary_workflows && template.primary_workflows.length > 0" class="flex flex-wrap gap-1">
                                                <span v-for="wf in template.primary_workflows" :key="wf.id" class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                                                    {{ wf.name }}
                                                </span>
                                            </div>
                                            <div v-else class="text-xs text-gray-400 italic">Bağlı akış yok</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            {{ template.creator?.name || 'Sistem' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ formatDate(template.created_at) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <button @click="toggleStatus(template.id)" :class="template.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full cursor-pointer hover:opacity-80 transition-opacity">
                                                {{ template.is_active ? 'Aktif' : 'Pasif' }}
                                            </button>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link :href="route('form-templates.edit', template.id)" class="text-indigo-600 hover:text-indigo-900 mr-4">Tasarla / Düzenle</Link>
                                            <button @click="deleteTemplate(template.id)" class="text-red-600 hover:text-red-900">Sil</button>
                                        </td>
                                    </tr>
                                    <tr v-if="templates.length === 0">
                                        <td colspan="6" class="px-6 py-8 whitespace-nowrap text-sm text-gray-500 text-center">
                                            Hiç form şablonu bulunamadı. Hemen yeni bir form tasarlayın.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
