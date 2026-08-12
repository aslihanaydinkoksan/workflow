<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Department;
use App\Models\Directorate;

class SyncMerkezi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:merkezi';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merkezi API (merkezi_app) veritabanından kullanıcı, departman ve direktörlükleri çeker';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Senkronizasyon Başlatılıyor...');

        try {
            DB::connection('merkezi_db')->getPdo();
        } catch (\Exception $e) {
            $this->error('Merkezi veritabanına bağlanılamadı: ' . $e->getMessage());
            return;
        }

        // 1. Sync Directorates
        $this->info('Direktörlükler senkronize ediliyor...');
        $directorates = DB::connection('merkezi_db')->table('directorates')->get();
        foreach ($directorates as $dir) {
            Directorate::updateOrCreate(
                ['id' => $dir->id],
                [
                    'name' => $dir->name,
                    'director_id' => $dir->director_id,
                    'is_active' => $dir->is_active ?? true,
                ]
            );
        }

        // 2. Sync Departments
        $this->info('Departmanlar senkronize ediliyor...');
        $departments = DB::connection('merkezi_db')->table('departments')->get();
        foreach ($departments as $dept) {
            Department::updateOrCreate(
                ['id' => $dept->id],
                [
                    'name' => $dept->name,
                    'parent_id' => $dept->parent_id ?? null,
                    'directorate_id' => $dept->directorate_id ?? null,
                    'is_active' => $dept->is_active ?? true,
                ]
            );
        }

        // 3. Sync Users
        $this->info('Kullanıcılar senkronize ediliyor...');
        $users = DB::connection('merkezi_db')->table('users')->get();
        
        // Track emails we've seen in this sync to prevent duplicates within the source data
        $seenEmails = [];
        
        foreach ($users as $user) {
            $fullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            if (empty($fullName)) {
                $fullName = $user->email ?? 'Bilinmeyen Kullanıcı';
            }

            $email = $user->email ?: 'user_' . $user->id . '@localhost.com';
            
            // Check if email already used by another ID in existing DB or in current sync run
            if (in_array($email, $seenEmails) || User::where('email', $email)->where('id', '!=', $user->id)->exists()) {
                $email = 'duplicate_' . $user->id . '_' . $email;
            }
            $seenEmails[] = $email;

            User::updateOrCreate(
                ['id' => $user->id],
                [
                    'name' => $fullName,
                    'first_name' => $user->first_name ?? null,
                    'last_name' => $user->last_name ?? null,
                    'tc_no' => $user->tc_no ?? null,
                    'email' => $email,
                    'password' => $user->password ?? bcrypt('password'),
                    'department_id' => $user->department_id ?? null,
                    'directorate_id' => $user->directorate_id ?? null,
                    'company_id' => $user->company_id ?? null,
                    'is_active' => $user->is_active ?? true,
                    'manager_id' => $user->manager_id ?? null,
                ]
            );
        }

        // 4. Sync Department Managers
        $this->info('Departman yöneticileri senkronize ediliyor...');
        if (DB::connection('merkezi_db')->getSchemaBuilder()->hasTable('department_managers')) {
            $deptManagers = DB::connection('merkezi_db')->table('department_managers')->get();
            DB::table('department_managers')->truncate(); // Clear existing to prevent duplicates
            
            $existingUsers = \App\Models\User::pluck('id')->toArray();
            $existingDepts = \App\Models\Department::pluck('id')->toArray();
            
            $insertData = [];
            foreach ($deptManagers as $dm) {
                if (in_array($dm->user_id, $existingUsers) && in_array($dm->department_id, $existingDepts)) {
                    $insertData[] = [
                        'user_id' => $dm->user_id,
                        'department_id' => $dm->department_id,
                        'type' => $dm->type ?? 'manager',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            if (count($insertData) > 0) {
                DB::table('department_managers')->insert($insertData);
            }
        }

        $this->info('Senkronizasyon Başarıyla Tamamlandı!');
    }
}
