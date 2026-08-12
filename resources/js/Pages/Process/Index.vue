<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    groupedWorkflows: Object
});
</script>

<template>
    <Head title="Süreç Başlat" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Süreç Başlat (Katalog)</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

                <div v-if="Object.keys(groupedWorkflows).length === 0" class="p-12 text-center text-gray-500 bg-white shadow-sm sm:rounded-lg">
                    <p class="text-lg">Şu an departmanınıza veya rolünüze atanmış başlatılabilecek bir form bulunmuyor.</p>
                </div>

                <!-- Kategori Bazlı Gruplama -->
                <div v-for="(workflows, category) in groupedWorkflows" :key="category">
                    <h3 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">{{ category }}</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="workflow in workflows" :key="workflow.id" class="bg-white overflow-hidden shadow-sm sm:rounded-lg border hover:border-indigo-500 transition-colors flex flex-col justify-between">
                            <div class="p-6">
                                <div class="flex items-center mb-4">
                                    <div class="h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 font-bold text-xl">
                                        {{ workflow.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <h4 class="text-lg font-bold text-gray-900 ml-3">{{ workflow.name }}</h4>
                                </div>
                                <p class="text-sm text-gray-500">{{ workflow.description || 'Bu süreç için açıklama girilmemiş.' }}</p>
                            </div>
                            
                            <div class="p-4 bg-gray-50 border-t flex justify-between items-center">
                                <span class="text-xs text-gray-400">Sürüm: v1.0</span>
                                <Link :href="route('processes.create', workflow.id)" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded text-sm shadow">
                                    Başlat &rarr;
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
