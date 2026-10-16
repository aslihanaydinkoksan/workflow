<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class EnterprisePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Spatie izin önbelleğini sıfırla (PermissionDoesNotExist önbellek çakışmasını engeller)
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // 1. İş Zekası & Power BI
            'reports.view',
            'reports.create_dashboard',
            'reports.manage_widgets',
            'reports.export_excel',
            'reports.export_pdf',
            'reports.manage_schedules',

            // 2. Termin & SLA
            'termin.view_sla',
            'termin.reschedule',
            'termin.override',

            // 3. Numune & SAP
            'samples.view_pipeline',
            'samples.convert_order',
            'samples.download_coa',

            // 4. Dış Görev & Harcırah
            'travel.request',
            'travel.approve_manager',
            'travel.approve_gmy',
            'travel.admin_services',
            'travel.finance_advance',
            'travel.expense_settlement',
            'travel.download_order',

            // 5. Denetim & Kokpit
            'audit.view_diff',
            'governance.admin_cockpit',
            'mail.manage_guard',

            // 6. BPMN Tasarım
            'designer.view',
            'designer.edit',
            'designer.publish_version',

            // 7. Canlı Saha & Personel Radarı (Kim Nerede?)
            'duty_radar.view',
            'duty_radar.manage_categories',

            // 8. Yönetici Görev & Test Süreç Yönetimi (Admin Soft Delete & Edit)
            'tasks.admin_edit',
            'tasks.admin_delete',
            'tasks.admin_bulk_delete',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        // İzinler veritabanına eklendikten sonra önbelleği tekrar sıfırla
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Güvenli izin atama yardımcısı (Model bazlı sorgulama ile önbellek/guard hatasını önler)
        $safeGivePermissions = function ($role, array $permissionNames) {
            if (!$role) return;
            $perms = Permission::whereIn('name', $permissionNames)
                ->where('guard_name', $role->guard_name)
                ->get();
            $role->givePermissionTo($perms);
        };

        // Admin rolüne TÜM web yetkilerini ver
        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::where('guard_name', 'web')->get());

        // Süreç Tasarımcısı rolüne yetkiler
        $designerRole = Role::where('name', 'Süreç Tasarımcısı')->first();
        if ($designerRole) {
            $safeGivePermissions($designerRole, [
                'designer.view', 'designer.edit', 'designer.publish_version',
                'reports.view', 'reports.export_excel', 'reports.export_pdf',
                'audit.view_diff', 'termin.view_sla'
            ]);
        }

        // Müdür ve Direktör rollerine yetkiler
        $directorRole = Role::where('name', 'Direktör')->first();
        if ($directorRole) {
            $safeGivePermissions($directorRole, [
                'reports.view', 'reports.export_excel', 'reports.export_pdf',
                'termin.view_sla', 'termin.override',
                'travel.request', 'travel.approve_manager', 'travel.approve_gmy', 'travel.download_order',
                'samples.view_pipeline', 'samples.download_coa',
                'audit.view_diff',
                'duty_radar.view',
            ]);
        }

        $managerRole = Role::where('name', 'Müdür')->first();
        if ($managerRole) {
            $safeGivePermissions($managerRole, [
                'reports.view', 'reports.export_excel', 'reports.export_pdf',
                'termin.view_sla', 'termin.reschedule',
                'travel.request', 'travel.approve_manager', 'travel.download_order',
                'samples.view_pipeline', 'samples.download_coa',
                'audit.view_diff',
                'duty_radar.view',
            ]);
        }

        $chiefRole = Role::where('name', 'Amir')->first();
        if ($chiefRole) {
            $safeGivePermissions($chiefRole, [
                'termin.view_sla', 'termin.reschedule',
                'travel.request', 'travel.approve_manager',
                'samples.download_coa',
            ]);
        }

        $userRole = Role::where('name', 'Kullanıcı')->first();
        if ($userRole) {
            $safeGivePermissions($userRole, [
                'travel.request',
            ]);
        }
    }
}
