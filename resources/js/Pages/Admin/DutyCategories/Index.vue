<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
});

const isCreateModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const selectedCategory = ref(null);

const form = useForm({
    name: '',
    code: '',
    icon: '🎪',
    color: 'indigo',
    description: '',
    is_field_duty: true,
    is_active: true,
    sort_order: 0,
});

const editForm = useForm({
    id: null,
    name: '',
    code: '',
    icon: '🎪',
    color: 'indigo',
    description: '',
    is_field_duty: true,
    is_active: true,
    sort_order: 0,
});

const presetIcons = ['🎪', '✈️', '🚗', '🛠️', '🔄', '🔍', '🎓', '🏛️', '💼', '🏖️', '📦', '🚢', '🧪', '🤝', '⚙️', '🎯'];
const presetColors = [
    { id: 'indigo', label: 'Indigo (Lacivert)', bg: 'bg-indigo-100 text-indigo-800 border-indigo-300' },
    { id: 'emerald', label: 'Zümrüt (Yeşil)', bg: 'bg-emerald-100 text-emerald-800 border-emerald-300' },
    { id: 'blue', label: 'Mavi', bg: 'bg-blue-100 text-blue-800 border-blue-300' },
    { id: 'amber', label: 'Amber (Turuncu)', bg: 'bg-amber-100 text-amber-800 border-amber-300' },
    { id: 'purple', label: 'Mor', bg: 'bg-purple-100 text-purple-800 border-purple-300' },
    { id: 'teal', label: 'Teal (Camgöbeği)', bg: 'bg-teal-100 text-teal-800 border-teal-300' },
    { id: 'slate', label: 'Slate (Koyu Gri)', bg: 'bg-slate-100 text-slate-800 border-slate-300' },
    { id: 'rose', label: 'Gül (Kırmızı)', bg: 'bg-rose-100 text-rose-800 border-rose-300' },
];

const getColorBadgeClass = (colorId) => {
    const found = presetColors.find(c => c.id === colorId);
    return found ? found.bg : 'bg-slate-100 text-slate-800 border-slate-300';
};

const openCreateModal = () => {
    form.reset();
    form.sort_order = props.categories.length + 1;
    isCreateModalOpen.value = true;
};

const submitCreate = () => {
    form.post(route('admin.duty-categories.store'), {
        onSuccess: () => {
            isCreateModalOpen.value = false;
            form.reset();
        },
    });
};

const openEditModal = (cat) => {
    selectedCategory.value = cat;
    editForm.id = cat.id;
    editForm.name = cat.name;
    editForm.code = cat.code;
    editForm.icon = cat.icon;
    editForm.color = cat.color;
    editForm.description = cat.description || '';
    editForm.is_field_duty = !!cat.is_field_duty;
    editForm.is_active = !!cat.is_active;
    editForm.sort_order = cat.sort_order;
    isEditModalOpen.value = true;
};

const submitEdit = () => {
    editForm.put(route('admin.duty-categories.update', editForm.id), {
        onSuccess: () => {
            isEditModalOpen.value = false;
        },
    });
};

const openDeleteModal = (cat) => {
    selectedCategory.value = cat;
    isDeleteModalOpen.value = true;
};

const submitDelete = () => {
    if (!selectedCategory.value) return;
    router.delete(route('admin.duty-categories.destroy', selectedCategory.value.id), {
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            selectedCategory.value = null;
        },
    });
};

const toggleStatus = (cat) => {
    router.post(route('admin.duty-categories.toggle', cat.id), {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Görev & Saha Türleri Yönetimi" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-black text-slate-900 flex items-center gap-2">
                        <span>📍</span> Görev & Saha Türleri Yönetimi (Dinamik Radarlar)
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Fuar, müşteri ziyareti, saha servisi ve tüm dış görev kategorilerini buradan dinamik olarak yönetebilirsiniz.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition-all"
                    >
                        <span>➕</span> Yeni Görev Türü Ekle
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Bilgi Kartı -->
            <div class="p-4 bg-gradient-to-r from-indigo-50 via-white to-indigo-50 rounded-2xl border border-indigo-200/80 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600/10 text-indigo-700 flex items-center justify-center text-xl font-bold">
                        ⚡
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-slate-900">Dinamik & Esnek Saha Entegrasyonu</h4>
                        <p class="text-[11px] text-slate-600 mt-0.5">
                            Buraya eklediğiniz tüm görev türleri; <strong>"Kim Nerede?"</strong> canlı personel radarında otomatik sekme olarak listelenir ve dış görev formlarında seçenek olarak sunulur.
                        </p>
                    </div>
                </div>
                <div class="shrink-0 text-right">
                    <span class="text-xs font-bold text-indigo-800 bg-white px-3 py-1.5 rounded-xl border border-indigo-200 shadow-2xs">
                        Toplam {{ categories.length }} Kategori
                    </span>
                </div>
            </div>

            <!-- Liste Tablosu -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                        <thead class="bg-slate-50 text-slate-700 font-bold uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3.5 w-16 text-center">Sıra</th>
                                <th class="px-4 py-3.5 w-16 text-center">İkon</th>
                                <th class="px-4 py-3.5">Kategori Adı & Kod</th>
                                <th class="px-4 py-3.5">Açıklama</th>
                                <th class="px-4 py-3.5 text-center">Görsel Rozet</th>
                                <th class="px-4 py-3.5 text-center">Saha Radarı</th>
                                <th class="px-4 py-3.5 text-center">Durum</th>
                                <th class="px-4 py-3.5 text-right w-32">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="categories.length === 0">
                                <td colspan="8" class="px-6 py-10 text-center text-slate-400 italic">
                                    Henüz kayıtlı bir görev türü bulunmuyor. Yeni bir kategori ekleyin.
                                </td>
                            </tr>
                            <tr v-for="cat in categories" :key="cat.id" class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-4 py-3 text-center font-bold text-slate-400">
                                    {{ cat.sort_order }}
                                </td>
                                <td class="px-4 py-3 text-center text-xl">
                                    {{ cat.icon }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900 text-sm flex items-center gap-2">
                                        <span>{{ cat.name }}</span>
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                        kod: {{ cat.code }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-600 max-w-xs truncate">
                                    {{ cat.description || '-' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border shadow-2xs', getColorBadgeClass(cat.color)]">
                                        <span>{{ cat.icon }}</span>
                                        <span>{{ cat.name }}</span>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span v-if="cat.is_field_duty" class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                                        <span>✓</span> Radarda Göster
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full border border-slate-200">
                                        Gizli
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button
                                        type="button"
                                        @click="toggleStatus(cat)"
                                        :class="[
                                            'px-2.5 py-1 text-[11px] font-bold rounded-lg border transition-all',
                                            cat.is_active 
                                                ? 'bg-emerald-50 text-emerald-800 border-emerald-300 hover:bg-emerald-100'
                                                : 'bg-slate-100 text-slate-500 border-slate-300 hover:bg-slate-200'
                                        ]"
                                        :title="cat.is_active ? 'Pasife Al' : 'Aktif Et'"
                                    >
                                        {{ cat.is_active ? '🟢 Aktif' : '⚪ Pasif' }}
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            type="button"
                                            @click="openEditModal(cat)"
                                            class="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors"
                                            title="Düzenle"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                        </button>
                                        <button
                                            type="button"
                                            @click="openDeleteModal(cat)"
                                            class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                                            title="Sil"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- CREATE MODAL -->
        <Modal :show="isCreateModalOpen" @close="isCreateModalOpen = false" max-width="lg">
            <div class="p-6">
                <div class="flex items-center justify-between border-b pb-3 mb-4">
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <span>➕</span> Yeni Görev & Saha Türü Ekle
                    </h3>
                    <button type="button" @click="isCreateModalOpen = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <form @submit.prevent="submitCreate" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Kategori / Görev Adı *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="Örn: Fuar & Etkinlik Katılımı"
                            class="w-full text-xs rounded-xl border-slate-200 focus:ring-indigo-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Sistem Kodu (Otomatik üretilir)</label>
                        <input
                            v-model="form.code"
                            type="text"
                            placeholder="Örn: fuar (Boş bırakırsanız otomatik üretilir)"
                            class="w-full text-xs rounded-xl border-slate-200 focus:ring-indigo-500 font-mono"
                        />
                    </div>

                    <!-- İkon Seçici -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Kategori İkonu / Emoji *</label>
                        <div class="flex flex-wrap gap-2 items-center mb-2">
                            <button
                                v-for="ico in presetIcons"
                                :key="ico"
                                type="button"
                                @click="form.icon = ico"
                                :class="[
                                    'w-9 h-9 rounded-xl text-lg flex items-center justify-center border transition-all',
                                    form.icon === ico ? 'border-indigo-600 bg-indigo-50 ring-2 ring-indigo-500/20 shadow-xs' : 'border-slate-200 hover:bg-slate-50'
                                ]"
                            >
                                {{ ico }}
                            </button>
                        </div>
                        <input
                            v-model="form.icon"
                            type="text"
                            required
                            class="w-24 text-center text-sm rounded-xl border-slate-200 focus:ring-indigo-500"
                        />
                    </div>

                    <!-- Renk Teması -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Renk Teması *</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                v-for="clr in presetColors"
                                :key="clr.id"
                                type="button"
                                @click="form.color = clr.id"
                                :class="[
                                    'px-3 py-2 rounded-xl text-xs font-bold border text-left flex items-center gap-2 transition-all',
                                    form.color === clr.id ? 'ring-2 ring-indigo-500 border-indigo-600 bg-indigo-50/40 shadow-xs' : 'border-slate-200 hover:bg-slate-50'
                                ]"
                            >
                                <span :class="['w-3 h-3 rounded-full', clr.bg]"></span>
                                <span>{{ clr.label }}</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Açıklama / Kapsam Notu</label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            placeholder="Bu görev türünün kullanım amacını belirtiniz..."
                            class="w-full text-xs rounded-xl border-slate-200 focus:ring-indigo-500"
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-2 border-t">
                        <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
                            <input v-model="form.is_field_duty" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            <span>Saha Radarında Gösterilsin</span>
                        </label>
                        <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            <span>Kategori Aktif</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t">
                        <button type="button" @click="isCreateModalOpen = false" class="px-4 py-2 text-xs text-slate-600 hover:bg-slate-100 rounded-xl">İptal</button>
                        <button type="submit" :disabled="form.processing" class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-sm">
                            {{ form.processing ? 'Kaydediliyor...' : 'Oluştur' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- EDIT MODAL -->
        <Modal :show="isEditModalOpen" @close="isEditModalOpen = false" max-width="lg">
            <div class="p-6">
                <div class="flex items-center justify-between border-b pb-3 mb-4">
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <span>✏️</span> Görev Türünü Düzenle
                    </h3>
                    <button type="button" @click="isEditModalOpen = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <form @submit.prevent="submitEdit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Kategori / Görev Adı *</label>
                        <input
                            v-model="editForm.name"
                            type="text"
                            required
                            class="w-full text-xs rounded-xl border-slate-200 focus:ring-indigo-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Sistem Kodu *</label>
                        <input
                            v-model="editForm.code"
                            type="text"
                            required
                            class="w-full text-xs rounded-xl border-slate-200 focus:ring-indigo-500 font-mono"
                        />
                    </div>

                    <!-- İkon Seçici -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Kategori İkonu / Emoji *</label>
                        <div class="flex flex-wrap gap-2 items-center mb-2">
                            <button
                                v-for="ico in presetIcons"
                                :key="ico"
                                type="button"
                                @click="editForm.icon = ico"
                                :class="[
                                    'w-9 h-9 rounded-xl text-lg flex items-center justify-center border transition-all',
                                    editForm.icon === ico ? 'border-indigo-600 bg-indigo-50 ring-2 ring-indigo-500/20 shadow-xs' : 'border-slate-200 hover:bg-slate-50'
                                ]"
                            >
                                {{ ico }}
                            </button>
                        </div>
                        <input
                            v-model="editForm.icon"
                            type="text"
                            required
                            class="w-24 text-center text-sm rounded-xl border-slate-200 focus:ring-indigo-500"
                        />
                    </div>

                    <!-- Renk Teması -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Renk Teması *</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                v-for="clr in presetColors"
                                :key="clr.id"
                                type="button"
                                @click="editForm.color = clr.id"
                                :class="[
                                    'px-3 py-2 rounded-xl text-xs font-bold border text-left flex items-center gap-2 transition-all',
                                    editForm.color === clr.id ? 'ring-2 ring-indigo-500 border-indigo-600 bg-indigo-50/40 shadow-xs' : 'border-slate-200 hover:bg-slate-50'
                                ]"
                            >
                                <span :class="['w-3 h-3 rounded-full', clr.bg]"></span>
                                <span>{{ clr.label }}</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Açıklama / Kapsam Notu</label>
                        <textarea
                            v-model="editForm.description"
                            rows="2"
                            class="w-full text-xs rounded-xl border-slate-200 focus:ring-indigo-500"
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-2 border-t">
                        <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
                            <input v-model="editForm.is_field_duty" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            <span>Saha Radarında Gösterilsin</span>
                        </label>
                        <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
                            <input v-model="editForm.is_active" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            <span>Kategori Aktif</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t">
                        <button type="button" @click="isEditModalOpen = false" class="px-4 py-2 text-xs text-slate-600 hover:bg-slate-100 rounded-xl">İptal</button>
                        <button type="submit" :disabled="editForm.processing" class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-sm">
                            {{ editForm.processing ? 'Kaydediliyor...' : 'Değişiklikleri Kaydet' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- DELETE CONFIRM MODAL -->
        <Modal :show="isDeleteModalOpen" @close="isDeleteModalOpen = false" max-width="md">
            <div class="p-6">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center text-xl shrink-0">
                        ⚠️
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Görev Türünü Sil</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            <strong>"{{ selectedCategory?.name }}"</strong> kategorisini silmek istediğinize emin misiniz?
                        </p>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-4 mt-4 border-t">
                    <button type="button" @click="isDeleteModalOpen = false" class="px-3 py-1.5 text-xs text-slate-600">İptal</button>
                    <button type="button" @click="submitDelete" class="px-4 py-1.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl">
                        Evet, Sil
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
