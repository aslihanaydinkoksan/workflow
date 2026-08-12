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
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Temizlik
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        User::truncate();
        Department::truncate();
        Directorate::truncate();
        Role::truncate();
        Permission::truncate();
        DB::table('model_has_permissions')->truncate();
        DB::table('model_has_roles')->truncate();
        DB::table('role_has_permissions')->truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        // İzinleri (Permissions) Oluştur
        $permissions = [
            // Sistem ve Yönetim
            'view_admin_panel',
            'manage_users',
            'manage_roles',
            'manage_departments',
            'manage_directorates',
            'manage_settings',

            // Şablon ve Akış Tasarımı
            'manage_workflows',
            'create_forms',
            'templates.edit',
            'templates.delete',
            'templates.publish',

            // Süreç ve Görev Yönetimi
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
            Permission::create(['name' => $permission]);
        }

        // Rolleri Oluştur ve İzinleri Ata
        
        // 1. Admin
        $roleAdmin = Role::create(['name' => 'Admin']);
        $roleAdmin->givePermissionTo(Permission::all()); // Bütün yetkileri alır

        // 2. Süreç Tasarımcısı
        $roleTasarimci = Role::create(['name' => 'Süreç Tasarımcısı']);
        $roleTasarimci->givePermissionTo([
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
        $roleDirektor = Role::create(['name' => 'Direktör']);
        $roleDirektor->givePermissionTo([
            'start_processes',
            'processes.approve',
            'processes.assign',
            'processes.cancel',
            'processes.view_directorate',
            'processes.view_department',
            'processes.view_own'
        ]);

        // 4. Müdür
        $roleMudur = Role::create(['name' => 'Müdür']);
        $roleMudur->givePermissionTo([
            'start_processes',
            'processes.approve',
            'processes.assign',
            'processes.cancel',
            'processes.view_department',
            'processes.view_own'
        ]);

        // 5. Müdür Yardımcısı / Amir
        $roleAmir = Role::create(['name' => 'Amir']);
        $roleAmir->givePermissionTo([
            'start_processes',
            'processes.approve',
            'processes.assign',
            'processes.view_department',
            'processes.view_own'
        ]);

        // 6. Kullanıcı (Standart Personel)
        $roleKullanici = Role::create(['name' => 'Kullanıcı']);
        $roleKullanici->givePermissionTo([
            'start_processes',
            'processes.approve',
            'processes.view_own'
        ]);

        // 7. Müşteri
        $roleMusteri = Role::create(['name' => 'Müşteri']);
        $roleMusteri->givePermissionTo([
            'start_processes',
            'processes.approve',
            'processes.view_own'
        ]);

        // 8. Mavi Yaka
        $roleMaviYaka = Role::create(['name' => 'Mavi Yaka']);
        $roleMaviYaka->givePermissionTo([
            'start_processes',
            'processes.approve',
            'processes.view_own'
        ]);

        // Örnek Hiyerarşi Oluşturma
        $dirUretim = Directorate::create(['name' => 'Üretim Direktörlüğü']);

        $deptYonetim = Department::create(['name' => 'Yönetim', 'directorate_id' => $dirUretim->id]);
        $deptIK = Department::create(['name' => 'İnsan Kaynakları', 'parent_id' => $deptYonetim->id, 'directorate_id' => $dirUretim->id]);
        $deptBT = Department::create(['name' => 'Bilgi Teknolojileri', 'parent_id' => $deptYonetim->id, 'directorate_id' => $dirUretim->id]);

        // Kullanıcıları Oluştur
        $password = bcrypt('password');

        $admin = User::create([
            'name' => 'Sistem Yöneticisi',
            'email' => 'admin@test.com',
            'password' => $password,
            'title' => 'Admin',
            'department_id' => $deptBT->id,
        ]);
        $admin->assignRole('Admin');

        $tasarimci = User::create([
            'name' => 'Süreç Tasarımcısı',
            'email' => 'tasarimci@test.com',
            'password' => $password,
            'title' => 'İş Analisti',
            'department_id' => $deptBT->id,
        ]);
        $tasarimci->assignRole('Süreç Tasarımcısı');

        $direktor = User::create([
            'name' => 'Şirket Direktörü',
            'email' => 'direktor@test.com',
            'password' => $password,
            'title' => 'İnsan Kaynakları Direktörü',
            'department_id' => $deptIK->id,
            'directorate_id' => $dirUretim->id,
        ]);
        $dirUretim->update(['director_id' => $direktor->id]);
        $direktor->assignRole('Direktör');

        $mudur = User::create([
            'name' => 'Departman Müdürü',
            'email' => 'mudur@test.com',
            'password' => $password,
            'title' => 'İşe Alım Müdürü',
            'department_id' => $deptIK->id,
            'manager_id' => $direktor->id,
        ]);
        $mudur->assignRole('Müdür');

        $amir = User::create([
            'name' => 'Birim Amiri',
            'email' => 'amir@test.com',
            'password' => $password,
            'title' => 'Takım Lideri',
            'department_id' => $deptIK->id,
            'manager_id' => $mudur->id,
        ]);
        $amir->assignRole('Amir');

        $kullanici = User::create([
            'name' => 'Düz Kullanıcı',
            'email' => 'kullanici@test.com',
            'password' => $password,
            'title' => 'Personel',
            'department_id' => $deptIK->id,
            'manager_id' => $amir->id,
        ]);
        $kullanici->assignRole('Kullanıcı');
        $this->call([
            HierarchySeeder::class,
        ]);
    }
}
