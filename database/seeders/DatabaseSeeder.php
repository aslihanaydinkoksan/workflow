<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Department;
use App\Models\Directorate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Spatie önbelleğini tamamen temizle
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        /* 
         * DİKKAT: Canlı ortamda veri kaybı (kendi hesabının silinmesi vb.) yaşanmaması için 
         * truncate() fonksiyonları kaldırılmıştır. 
         * findOrCreate ve firstOrCreate kullanılarak sadece eksik veriler eklenecektir. 
         */

        // İzinleri (Permissions) Oluştur - Spatie'nin en güvenli metodu ile
        $permissions = [
            'view_admin_panel',
            'manage_users',
            'manage_roles',
            'manage_departments',
            'manage_directorates',
            'manage_settings',
            'manage_workflows',
            'create_forms',
            'templates.edit',
            'templates.delete',
            'templates.publish',
            'start_processes',
            'processes.approve',
            'processes.assign',
            'processes.cancel',
            'processes.view_own',
            'processes.view_department',
            'processes.view_directorate',
            'processes.view_all',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Rolleri Oluştur ve İzinleri Ata - syncPermissions kullanarak tekrarları önlüyoruz

        // 1. Admin
        $roleAdmin = Role::findOrCreate('Admin', 'web');
        $roleAdmin->syncPermissions(Permission::all());

        // 2. Süreç Tasarımcısı
        $roleTasarimci = Role::findOrCreate('Süreç Tasarımcısı', 'web');
        $roleTasarimci->syncPermissions([
            'view_admin_panel',
            'manage_workflows',
            'create_forms',
            'templates.edit',
            'templates.delete',
            'templates.publish',
            'processes.view_all',
            'start_processes'
        ]);

        // 3. Direktör
        $roleDirektor = Role::findOrCreate('Direktör', 'web');
        $roleDirektor->syncPermissions([
            'start_processes',
            'processes.approve',
            'processes.assign',
            'processes.cancel',
            'processes.view_directorate',
            'processes.view_department',
            'processes.view_own'
        ]);

        // 4. Müdür
        $roleMudur = Role::findOrCreate('Müdür', 'web');
        $roleMudur->syncPermissions([
            'start_processes',
            'processes.approve',
            'processes.assign',
            'processes.cancel',
            'processes.view_department',
            'processes.view_own'
        ]);

        // 5. Müdür Yardımcısı / Amir
        $roleAmir = Role::findOrCreate('Amir', 'web');
        $roleAmir->syncPermissions([
            'start_processes',
            'processes.approve',
            'processes.assign',
            'processes.view_department',
            'processes.view_own'
        ]);

        // 6. Kullanıcı (Standart Personel)
        $roleKullanici = Role::findOrCreate('Kullanıcı', 'web');
        $roleKullanici->syncPermissions([
            'start_processes',
            'processes.approve',
            'processes.view_own'
        ]);

        // 7. Müşteri
        $roleMusteri = Role::findOrCreate('Müşteri', 'web');
        $roleMusteri->syncPermissions([
            'start_processes',
            'processes.approve',
            'processes.view_own'
        ]);

        // 8. Mavi Yaka
        $roleMaviYaka = Role::findOrCreate('Mavi Yaka', 'web');
        $roleMaviYaka->syncPermissions([
            'start_processes',
            'processes.approve',
            'processes.view_own'
        ]);


        // Örnek Hiyerarşi Oluşturma - Var olanı ezmemek için firstOrCreate
        $dirUretim = Directorate::firstOrCreate(['name' => 'Üretim Direktörlüğü']);

        $deptYonetim = Department::firstOrCreate(['name' => 'Yönetim'], ['directorate_id' => $dirUretim->id]);
        $deptIK = Department::firstOrCreate(['name' => 'İnsan Kaynakları'], ['parent_id' => $deptYonetim->id, 'directorate_id' => $dirUretim->id]);
        $deptBT = Department::firstOrCreate(['name' => 'Bilgi Teknolojileri'], ['parent_id' => $deptYonetim->id, 'directorate_id' => $dirUretim->id]);


        // Test Kullanıcılarını Oluştur - Gerçek hesaplarla çakışmaması için firstOrCreate
        $password = bcrypt('password');

        $admin = User::firstOrCreate(
            ['email' => 'admin@test.com'],
            ['name' => 'Sistem Yöneticisi', 'password' => $password, 'title' => 'Admin', 'department_id' => $deptBT->id]
        );
        $admin->assignRole('Admin');

        $tasarimci = User::firstOrCreate(
            ['email' => 'tasarimci@test.com'],
            ['name' => 'Süreç Tasarımcısı', 'password' => $password, 'title' => 'İş Analisti', 'department_id' => $deptBT->id]
        );
        $tasarimci->assignRole('Süreç Tasarımcısı');

        $direktor = User::firstOrCreate(
            ['email' => 'direktor@test.com'],
            ['name' => 'Şirket Direktörü', 'password' => $password, 'title' => 'İnsan Kaynakları Direktörü', 'department_id' => $deptIK->id, 'directorate_id' => $dirUretim->id]
        );
        $dirUretim->update(['director_id' => $direktor->id]);
        $direktor->assignRole('Direktör');

        $mudur = User::firstOrCreate(
            ['email' => 'mudur@test.com'],
            ['name' => 'Departman Müdürü', 'password' => $password, 'title' => 'İşe Alım Müdürü', 'department_id' => $deptIK->id, 'manager_id' => $direktor->id]
        );
        $mudur->assignRole('Müdür');

        $amir = User::firstOrCreate(
            ['email' => 'amir@test.com'],
            ['name' => 'Birim Amiri', 'password' => $password, 'title' => 'Takım Lideri', 'department_id' => $deptIK->id, 'manager_id' => $mudur->id]
        );
        $amir->assignRole('Amir');

        $kullanici = User::firstOrCreate(
            ['email' => 'kullanici@test.com'],
            ['name' => 'Düz Kullanıcı', 'password' => $password, 'title' => 'Personel', 'department_id' => $deptIK->id, 'manager_id' => $amir->id]
        );
        $kullanici->assignRole('Kullanıcı');

        // Hiyerarşi Seeder'ını çağır
        $this->call([
            HierarchySeeder::class,
        ]);
    }
}
