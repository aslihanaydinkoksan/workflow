<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    settings: Object
});

const form = useForm({
    app_logo: null
});

const logoPreview = ref(props.settings.app_logo || null);

const handleLogoUpload = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.app_logo = file;
        logoPreview.value = URL.createObjectURL(file);
    }
};

const submit = () => {
    form.post(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Başarılı kayıtta formu temizleyebiliriz ama preview kalacak
            if(form.app_logo) {
               form.app_logo = null;
            }
        }
    });
};
</script>

<template>
    <Head title="Sistem Ayarları" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Sistem Ayarları</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    
                    <div class="mb-8 border-b pb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Görünüm ve Marka Ayarları</h3>
                        
                        <form @submit.prevent="submit" class="space-y-6 max-w-2xl">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Uygulama Logosu (Formlarda ve Menülerde Kullanılır)</label>
                                <div class="mt-1 flex items-center space-x-6">
                                    <div class="shrink-0">
                                        <img v-if="logoPreview" class="h-16 w-auto object-contain border p-2 rounded" :src="logoPreview" alt="App Logo">
                                        <div v-else class="h-16 w-48 border-2 border-dashed border-gray-300 flex items-center justify-center text-gray-400 rounded">
                                            Logo Yok
                                        </div>
                                    </div>
                                    <label class="block">
                                        <span class="sr-only">Logo seç</span>
                                        <input type="file" @change="handleLogoUpload" accept="image/*" class="block w-full text-sm text-gray-500
                                            file:mr-4 file:py-2 file:px-4
                                            file:rounded-md file:border-0
                                            file:text-sm file:font-semibold
                                            file:bg-teal-50 file:text-teal-700
                                            hover:file:bg-teal-100
                                        "/>
                                    </label>
                                </div>
                                <div v-if="form.errors.app_logo" class="text-red-500 text-sm mt-1">{{ form.errors.app_logo }}</div>
                            </div>

                            <!-- İleride eklenecek diğer ayarlar buraya gelebilir (Renkler, Default Mailler vb.) -->

                            <div class="flex items-center justify-end">
                                <PrimaryButton :disabled="form.processing" class="bg-teal-600 hover:bg-teal-700">
                                    {{ form.processing ? 'Kaydediliyor...' : 'Ayarları Kaydet' }}
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>

                    <div class="text-sm text-gray-500">
                        <p>Not: Bu sayfaya ilerleyen zamanlarda diğer sistem genel ayarları eklenecektir.</p>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
