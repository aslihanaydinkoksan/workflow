<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    role: Object,
    permissions: Array,
    rolePermissions: Array
});

const form = useForm({
    name: props.role?.name || '',
    permissions: props.rolePermissions || []
});

const submit = () => {
    if (props.role && props.role.id) {
        // Parametreyi süslü parantez (obje) içinde gönder!
        form.put(route('admin.roles.update', { role: props.role.id }));
    } else {
        form.post(route('admin.roles.store'));
    }
};


const permissionLabels = {
    // Sistem ve Yönetim
    'view_admin_panel': 'Admin Paneline Girebilir',
    'manage_users': 'Kullanıcı Havuzunu Yönetebilir',
    'manage_roles': 'Rolleri ve Yetki Matrisini Yönetebilir',
    'manage_departments': 'Departmanları Yönetebilir',
    'manage_directorates': 'Direktörlükleri Yönetebilir',
    'manage_settings': 'Sistem Ayarlarını Değiştirebilir',

    // Şablon ve Akış Tasarımı
    'manage_workflows': 'İş Akışı (Workflow) Tasarımı Yapabilir',
    'create_forms': 'Yeni Form Şablonu Oluşturabilir',
    'templates.edit': 'Şablonları Düzenleyebilir',
    'templates.delete': 'Şablonları Silebilir',
    'templates.publish': 'Şablonları Yayına (Kullanıma) Alabilir',

    // Süreç ve Görev Yönetimi
    'start_processes': 'Süreç Başlatabilir ve Kendi Görevlerini Yapabilir',
    'processes.approve': 'Formları ve Görevleri Onaylayabilir veya Reddedebilir',
    'processes.assign': 'Formları Başka Kullanıcılara Atayabilir',
    'processes.cancel': 'Süreçleri İptal Edebilir',
    'processes.view_own': 'Sadece Kendi Başlattığı/İçinde Olduğu Süreçleri Görebilir',
    'processes.view_department': 'Kendi Departmanına Ait Tüm Süreçleri Görebilir',
    'processes.view_directorate': 'Kendi Direktörlüğüne Bağlı Tüm Süreçleri Görebilir',
    'processes.view_all': 'Sistemdeki İstisnasız Tüm Süreçleri Görebilir',
};

const getPermissionLabel = (name) => {
    return permissionLabels[name] || name;
};

const permissionDescriptions = {
    'processes.cancel': 'Takip ekranından devam eden süreçleri iptal eder. Bekleyen görevler kapatılır ve süreç sonlandırılır.',
    'processes.approve': 'Kendisine atanan onay, inceleme ve form görevlerini tamamlayabilir.',
    'processes.assign': 'Görevleri başka kullanıcılara yeniden atayabilir.',
    'start_processes': 'Yeni süreç başlatabilir ve kendi görev listesini yönetebilir.',
};

const getPermissionDescription = (name) => {
    return permissionDescriptions[name] || null;
};

const permissionGroups = [
    {
        name: 'Süreç ve Görev Yönetimi',
        description: 'Süreç başlatma, onaylama, iptal etme ve izleme yetkileri',
        icon: '<svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
        keys: [
            'start_processes', 'processes.approve', 'processes.assign', 'processes.cancel',
            'processes.view_own', 'processes.view_department', 'processes.view_directorate', 'processes.view_all'
        ]
    },
    {
        name: 'Şablon ve Akış Tasarımı',
        description: 'Form ve iş akışı şablonlarının oluşturulması ve yönetimi',
        icon: '<svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>',
        keys: [
            'manage_workflows', 'create_forms', 'templates.edit', 'templates.delete', 'templates.publish'
        ]
    },
    {
        name: 'Sistem Yönetimi',
        description: 'Kullanıcı, departman ve genel sistem ayarları',
        icon: '<svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>',
        keys: [
            'view_admin_panel', 'manage_users', 'manage_roles', 'manage_departments', 'manage_directorates', 'manage_settings'
        ]
    }
];

const getGroupPermissions = (groupKeys) => {
    return props.permissions.filter(p => groupKeys.includes(p.name));
};

</script>

<template>
    <Head :title="role ? 'Rol Düzenle' : 'Yeni Rol Ekle'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center space-x-4">
                <Link :href="route('admin.roles.index')" class="text-gray-500 hover:text-gray-700">&larr; Geri</Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ role ? 'Rol Düzenle' : 'Yeni Rol Oluştur' }}
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-xl border border-gray-100">
                    <form @submit.prevent="submit" class="p-8 space-y-8">
                        
                        <div class="bg-gray-50 p-6 rounded-xl border border-gray-100">
                            <label class="block font-bold text-xs text-gray-600 uppercase tracking-wide mb-2">Rol Adı (Örn: Admin, Müdür, Müşteri)</label>
                            <input v-model="form.name" type="text" class="block w-full max-w-md rounded-lg sm:text-sm font-semibold bg-white border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required placeholder="Rol adını girin...">
                            <div v-if="form.errors.name" class="text-red-500 text-xs mt-2">{{ form.errors.name }}</div>
                            <p class="text-sm text-gray-500 mt-2">Bu rolü kullanıcılara atayarak yetki matrisinde seçtiğiniz haklara sahip olmalarını sağlayabilirsiniz. <strong>Uyarı:</strong> İsim değişikliği yapmak eski başlatılmış görevlerin detaylarındaki (snapshot) rol adını etkilemez ancak yetki matrisini hemen uygular.</p>
                        </div>

                        <div class="mt-8">
                            <h3 class="text-xl font-extrabold text-gray-900 border-b border-gray-200 pb-4 mb-6">Yetki Matrisi</h3>
                            
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                                <div v-for="group in permissionGroups" :key="group.name" class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                                    <div class="bg-gray-50 px-5 py-4 border-b border-gray-200 flex items-center">
                                        <div v-html="group.icon" class="mr-3 p-1.5 bg-white rounded-lg shadow-sm"></div>
                                        <div>
                                            <h4 class="font-bold text-gray-900 text-sm">{{ group.name }}</h4>
                                            <p class="text-xs text-gray-500 mt-0.5">{{ group.description }}</p>
                                        </div>
                                    </div>
                                    
                                    <div class="p-5 space-y-3">
                                        <div v-for="permission in getGroupPermissions(group.keys)" :key="permission.id" class="flex items-start">
                                            <div class="flex items-center h-5 mt-0.5">
                                                <input :id="'perm_'+permission.id" type="checkbox" :value="permission.name" v-model="form.permissions" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded cursor-pointer transition-colors">
                                            </div>
                                            <div class="ml-3 text-sm flex-1">
                                                <label :for="'perm_'+permission.id" class="font-bold text-gray-700 cursor-pointer block leading-tight">{{ getPermissionLabel(permission.name) }}</label>
                                                <p v-if="getPermissionDescription(permission.name)" class="text-gray-500 text-xs mt-1 leading-snug">
                                                    {{ getPermissionDescription(permission.name) }}
                                                </p>
                                                <p class="text-gray-400 text-[10px] font-mono mt-1">{{ permission.name }}</p>
                                            </div>
                                        </div>
                                        <div v-if="getGroupPermissions(group.keys).length === 0" class="text-sm text-gray-400 italic text-center py-4">Bu grupta yetki bulunamadı.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end border-t border-gray-100 mt-8 pt-6">
                            <button type="submit" :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-xl shadow-lg shadow-indigo-200 transition-all focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 text-sm tracking-wide">
                                {{ form.processing ? 'Kaydediliyor...' : 'Yetki Matrisini Kaydet' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
