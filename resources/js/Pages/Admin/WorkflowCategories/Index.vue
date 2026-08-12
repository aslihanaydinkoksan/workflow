<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    categories: Array
});

const form = useForm({
    name: ''
});

const isProcessing = ref(false);

const addCategory = () => {
    form.post(route('admin.workflow-categories.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('name'),
    });
};

const editingId = ref(null);
const editForm = useForm({
    name: ''
});

const startEditing = (category) => {
    editingId.value = category.id;
    editForm.name = category.name;
    editForm.clearErrors();
};

const cancelEditing = () => {
    editingId.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const updateCategory = (id) => {
    isProcessing.value = true;
    editForm.put(route('admin.workflow-categories.update', id), {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
        },
        onFinish: () => {
            isProcessing.value = false;
        }
    });
};

const deleteCategory = (id) => {
    if (confirm('Bu kategoriyi silmek istediğinize emin misiniz? Eski akışlarda kategori ismi görünmeye devam edecektir (Snapshot özelliği sayesinde bozulmaz).')) {
        isProcessing.value = true;
        router.delete(route('admin.workflow-categories.destroy', id), {
            preserveScroll: true,
            onFinish: () => {
                isProcessing.value = false;
            }
        });
    }
};
</script>

<template>
    <Head title="Süreç Kategorileri" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Süreç Kategorileri</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Başarı Mesajı -->
                <div v-if="$page.props.flash && $page.props.flash.success" class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                    <span class="block sm:inline">{{ $page.props.flash.success }}</span>
                </div>

                <!-- Hata Mesajı -->
                <div v-if="Object.keys(form.errors).length > 0" class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                    <ul class="list-disc list-inside">
                        <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
                    </ul>
                </div>
                
                <div v-if="Object.keys(editForm.errors).length > 0" class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                    <ul class="list-disc list-inside">
                        <li v-for="(error, key) in editForm.errors" :key="key">{{ error }}</li>
                    </ul>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        
                        <!-- Yeni Ekle Formu -->
                        <div class="mb-8 p-4 bg-gray-50 rounded-lg border border-gray-100">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Yeni Kategori Ekle</h3>
                            <form @submit.prevent="addCategory" class="flex items-end gap-4">
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Kategori Adı</label>
                                    <input 
                                        v-model="form.name" 
                                        type="text" 
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        placeholder="Örn: Pazarlama, İdari İşler, vb."
                                        required
                                    >
                                </div>
                                <button 
                                    type="submit" 
                                    :disabled="form.processing"
                                    class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                                >
                                    {{ form.processing ? 'Ekleniyor...' : 'Ekle' }}
                                </button>
                            </form>
                        </div>

                        <!-- Liste -->
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Mevcut Kategoriler</h3>
                            <span class="text-sm text-gray-500 italic">Not: Bir kategorinin silinmesi veya değişmesi eski akışlardaki kaydı (Snapshot) bozmaz.</span>
                        </div>
                        
                        <div v-if="categories.length === 0" class="text-gray-500 text-center py-8 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                            Henüz hiç kategori eklenmemiş.
                        </div>
                        
                        <div v-else class="overflow-x-auto border rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori Adı</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="category in categories" :key="category.id" class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            #{{ category.id }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <!-- Düzenleme Modu -->
                                            <div v-if="editingId === category.id">
                                                <input 
                                                    v-model="editForm.name" 
                                                    type="text" 
                                                    class="block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                    @keyup.enter="updateCategory(category.id)"
                                                    @keyup.esc="cancelEditing"
                                                    autofocus
                                                >
                                            </div>
                                            <!-- Normal Görünüm -->
                                            <div v-else class="text-sm font-medium text-gray-900">
                                                {{ category.name }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                            
                                            <template v-if="editingId === category.id">
                                                <button 
                                                    @click="updateCategory(category.id)" 
                                                    :disabled="isProcessing"
                                                    class="text-green-600 hover:text-green-900 p-2 rounded-md hover:bg-green-50 focus:outline-none transition-colors disabled:opacity-50"
                                                    title="Kaydet"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </button>
                                                <button 
                                                    @click="cancelEditing" 
                                                    :disabled="isProcessing"
                                                    class="text-gray-500 hover:text-gray-700 p-2 rounded-md hover:bg-gray-100 focus:outline-none transition-colors disabled:opacity-50"
                                                    title="İptal"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </template>

                                            <template v-else>
                                                <button 
                                                    @click="startEditing(category)" 
                                                    :disabled="isProcessing"
                                                    class="text-indigo-600 hover:text-indigo-900 p-2 rounded-md hover:bg-indigo-50 focus:outline-none transition-colors disabled:opacity-50"
                                                    title="Düzenle"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>
                                                <button 
                                                    @click="deleteCategory(category.id)" 
                                                    :disabled="isProcessing"
                                                    class="text-red-600 hover:text-red-900 p-2 rounded-md hover:bg-red-50 focus:outline-none transition-colors disabled:opacity-50"
                                                    title="Sil"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </template>
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
