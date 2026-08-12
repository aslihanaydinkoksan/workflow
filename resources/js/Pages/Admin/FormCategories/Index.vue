<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    categories: Array,
});

const isCreating = ref(false);
const isEditing = ref(false);
const isDeleting = ref(false);
const selectedCategory = ref(null);

const form = useForm({
    name: '',
    description: '',
});

const openCreateModal = () => {
    form.reset();
    isCreating.value = true;
};

const openEditModal = (category) => {
    selectedCategory.value = category;
    form.name = category.name;
    form.description = category.description || '';
    isEditing.value = true;
};

const openDeleteModal = (category) => {
    selectedCategory.value = category;
    isDeleting.value = true;
};

const closeModal = () => {
    isCreating.value = false;
    isEditing.value = false;
    isDeleting.value = false;
    selectedCategory.value = null;
    form.reset();
    form.clearErrors();
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('admin.form-categories.update', selectedCategory.value.id), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('admin.form-categories.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const deleteCategory = () => {
    router.delete(route('admin.form-categories.destroy', selectedCategory.value.id), {
        onSuccess: () => closeModal(),
    });
};
</script>

<template>
    <Head title="Form Kategorileri" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Form Kategorileri</h2>
                <PrimaryButton @click="openCreateModal" class="bg-teal-600 hover:bg-teal-700">Yeni Kategori Ekle</PrimaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori Adı</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Açıklama</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="category in categories" :key="category.id">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ category.name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ category.description || '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                                        <button @click="openEditModal(category)" class="text-indigo-600 hover:text-indigo-900">Düzenle</button>
                                        <button @click="openDeleteModal(category)" class="text-red-600 hover:text-red-900">Sil</button>
                                    </td>
                                </tr>
                                <tr v-if="categories.length === 0">
                                    <td colspan="3" class="px-6 py-8 text-center text-gray-500">Henüz hiç kategori eklenmemiş.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Modal :show="isCreating || isEditing" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">
                    {{ isEditing ? 'Kategoriyi Düzenle' : 'Yeni Kategori Ekle' }}
                </h2>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Kategori Adı</label>
                        <TextInput v-model="form.name" type="text" class="mt-1 block w-full" required />
                        <div v-if="form.errors.name" class="text-red-500 text-sm mt-1">{{ form.errors.name }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Açıklama (Opsiyonel)</label>
                        <textarea v-model="form.description" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" rows="3"></textarea>
                        <div v-if="form.errors.description" class="text-red-500 text-sm mt-1">{{ form.errors.description }}</div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <SecondaryButton @click="closeModal">İptal</SecondaryButton>
                        <PrimaryButton :disabled="form.processing" class="bg-teal-600 hover:bg-teal-700">
                            {{ isEditing ? 'Güncelle' : 'Kaydet' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Delete Modal -->
        <Modal :show="isDeleting" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Kategoriyi Sil</h2>
                <p class="mt-1 text-sm text-gray-600">
                    "{{ selectedCategory?.name }}" kategorisini silmek istediğinize emin misiniz? Bu işlem geri alınamaz.
                </p>
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="closeModal">İptal</SecondaryButton>
                    <PrimaryButton @click="deleteCategory" class="bg-red-600 hover:bg-red-700 focus:ring-red-500">
                        Evet, Sil
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
