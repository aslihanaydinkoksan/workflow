<?php

namespace App\Actions;

use App\Models\User;
use App\Models\Department;

class SyncUsersPreviewAction
{
    /**
     * MYS'den gelen kullanıcı listesi ile yerel veritabanını karşılaştırır.
     * N+1 sorgu problemini engellemek için yerel verileri bellekte (Memory) eşleştirir.
     *
     * @param array $centralUsers MYS'den gelen kullanıcı listesi
     * @return array Değişiklik tespit edilen kullanıcılar listesi
     */
    public function execute(array $centralUsers): array
    {
        // YEREL ORTAM (LOCAL) TESTİ İÇİN KORUMA: Sadece ilk 5 kullanıcıyı al
        if (app()->environment('local')) {
            $centralUsers = array_slice($centralUsers, 0, 10);
        }
        // 1. TÜM DEPARTMANLARI TEK SORGUDAN ÇEK (N+1 Çözümü)
        // Sonuç: ['Bilgi İşlem' => 1, 'İnsan Kaynakları' => 2] formatında bir dizi
        $localDepartments = Department::pluck('id', 'name')->toArray();
        $localDepartmentNames = Department::pluck('name', 'id')->toArray();

        // 2. TÜM KULLANICILARI TEK SORGUDAN ÇEK VE İNDEKSLERE AYIR (N+1 Çözümü)
        $localUsers = User::with('roles')->get();
        $usersByEmail = $localUsers->keyBy('email');
        $usersByTc = $localUsers->keyBy('tc_no')->filter(fn($user, $key) => !empty($key));

        // 3. Departman yöneticileri ve Direktörleri önbelleğe al
        $deptManagerUserIds = \Illuminate\Support\Facades\DB::table('department_managers')->pluck('type', 'user_id')->toArray();
        $directorUserIds = \App\Models\Directorate::whereNotNull('director_id')->pluck('director_id')->toArray();

        $usersWithChanges = [];

        foreach ($centralUsers as $centralUser) {
            // Kullanıcıyı E-posta VEYA TC'sine göre bul (O(1) karmaşıklığı ile bellekten)
            $user = $usersByEmail->get($centralUser['email']) ??
                (!empty($centralUser['tc_no']) ? $usersByTc->get($centralUser['tc_no']) : null);

            // MYS'den gelen departmanın yerel DB'deki ID'sini bul
            $centralDeptName = $centralUser['department']['name'] ?? null;
            $newDeptId = $centralDeptName ? ($localDepartments[$centralDeptName] ?? null) : null;

            $changes = [];

            if ($user) {
                // --- MEVCUT KULLANICI KARŞILAŞTIRMASI ---

                if ($user->tc_no !== $centralUser['tc_no']) {
                    $changes['tc_no'] = ['old' => $user->tc_no, 'new' => $centralUser['tc_no']];
                }
                if ($user->registration_no !== $centralUser['registration_no']) {
                    $changes['registration_no'] = ['old' => $user->registration_no, 'new' => $centralUser['registration_no']];
                }
                if ($user->title !== $centralUser['job_title']) {
                    $changes['title'] = ['old' => $user->title, 'new' => $centralUser['job_title']];
                }
                if ((bool)$user->is_customer !== (bool)$centralUser['is_customer']) {
                    $changes['is_customer'] = ['old' => $user->is_customer ? 'Evet' : 'Hayır', 'new' => $centralUser['is_customer'] ? 'Evet' : 'Hayır', 'new_val' => $centralUser['is_customer']];
                }
                if ((bool)$user->is_mavi_yaka !== (bool)$centralUser['is_mavi_yaka']) {
                    $changes['is_mavi_yaka'] = ['old' => $user->is_mavi_yaka ? 'Evet' : 'Hayır', 'new' => $centralUser['is_mavi_yaka'] ? 'Evet' : 'Hayır', 'new_val' => $centralUser['is_mavi_yaka']];
                }

                if ($user->department_id !== $newDeptId) {
                    $oldDept = $user->department_id ? ($localDepartmentNames[$user->department_id] ?? 'Yok') : 'Yok';
                    $newDept = $newDeptId ? $localDepartmentNames[$newDeptId] : 'Yok';
                    $changes['department_id'] = ['old' => $oldDept, 'new' => $newDept, 'new_id' => $newDeptId];
                }

                // Rol Karşılaştırması
                $expectedRoles = $this->resolveExpectedRoles($centralUser, $user, $deptManagerUserIds, $directorUserIds);
                $currentRoles = $user->roles->pluck('name')->toArray();
                sort($expectedRoles);
                sort($currentRoles);

                if ($currentRoles != $expectedRoles) {
                    $changes['roles'] = [
                        'old' => implode(', ', $currentRoles) ?: '(Rol Yok)',
                        'new' => implode(', ', $expectedRoles) ?: '(Rol Yok)',
                        'new_roles' => $expectedRoles
                    ];
                }

                if (!empty($changes)) {
                    $usersWithChanges[] = [
                        'user_id' => $user->id,
                        'name'    => $user->name,
                        'email'   => $user->email,
                        'changes' => $changes
                    ];
                }
            } else {
                // --- SİSTEME YENİ EKLENECEK KULLANICI ---
                $expectedRoles = $this->resolveExpectedRoles($centralUser, null, $deptManagerUserIds, $directorUserIds);

                $usersWithChanges[] = [
                    'user_id' => 'new_' . md5($centralUser['email']), // Vue tarafındaki unique key için
                    'name'    => $centralUser['name'],
                    'email'   => $centralUser['email'],
                    'changes' => [
                        'email'           => ['old' => 'Yok (Yeni)', 'new' => $centralUser['email']],
                        'tc_no'           => ['old' => 'Yok', 'new' => $centralUser['tc_no']],
                        'registration_no' => ['old' => 'Yok', 'new' => $centralUser['registration_no']],
                        'title'           => ['old' => 'Yok', 'new' => $centralUser['job_title']],
                        'roles'           => ['old' => '(Yok - Yeni)', 'new' => implode(', ', $expectedRoles) ?: 'Kullanıcı', 'new_roles' => $expectedRoles],
                        'is_customer'     => ['old' => 'Yok', 'new' => $centralUser['is_customer'] ? 'Evet' : 'Hayır', 'new_val' => $centralUser['is_customer']],
                        'is_mavi_yaka'    => ['old' => 'Yok', 'new' => $centralUser['is_mavi_yaka'] ? 'Evet' : 'Hayır', 'new_val' => $centralUser['is_mavi_yaka']],
                        'department_id'   => ['old' => 'Yok', 'new' => $newDeptId ? $localDepartmentNames[$newDeptId] : 'Yok', 'new_id' => $newDeptId]
                    ]
                ];
            }
        }

        return $usersWithChanges;
    }

    /**
     * MYS unvan ve niteliklerine göre kullanıcının sistemdeki 8 resmi role göre eşleşmesini sağlar.
     * Resmi Roller: Admin, Süreç Tasarımcısı, Direktör, Müdür, Amir, Kullanıcı, Müşteri, Mavi Yaka
     */
    public function resolveExpectedRoles(array $centralUser, ?User $existingUser = null, array $deptManagerUserIds = [], array $directorUserIds = []): array
    {
        $roles = [];

        // 1. İdari Özel Rolleri Koru (Admin, Süreç Tasarımcısı sistemden elle verilir, senkronizasyonda silinmez)
        if ($existingUser) {
            $currentRoles = $existingUser->roles->pluck('name')->toArray();
            if (in_array('Admin', $currentRoles, true)) {
                $roles[] = 'Admin';
            }
            if (in_array('Süreç Tasarımcısı', $currentRoles, true)) {
                $roles[] = 'Süreç Tasarımcısı';
            }
        }

        $userId = $existingUser?->id;
        $title = mb_strtoupper($centralUser['job_title'] ?? '', 'UTF-8');
        $isMaviYaka = !empty($centralUser['is_mavi_yaka']);
        $isCustomer = !empty($centralUser['is_customer']);

        // 2. Müşteri Rolü
        if ($isCustomer) {
            $roles[] = 'Müşteri';
            return array_values(array_unique($roles));
        }

        // 3. Direktör Rolü (MYS Direktörlüğü veya Unvanında Direktör/GMY/CEO geçenler)
        $isDirector = ($userId && in_array($userId, $directorUserIds, true))
            || str_contains($title, 'DİREKTÖR')
            || str_contains($title, 'GENEL MÜDÜR')
            || str_contains($title, 'GMY')
            || str_contains($title, 'CEO');

        if ($isDirector) {
            $roles[] = 'Direktör';
            return array_values(array_unique($roles));
        }

        // 4. Müdür Rolü (MYS Departman Müdürü veya Unvanında Müdür geçenler)
        $isMudur = ($userId && isset($deptManagerUserIds[$userId]) && $deptManagerUserIds[$userId] === 'manager')
            || (str_contains($title, 'MÜDÜR') && !str_contains($title, 'YARDIMCI') && !str_contains($title, 'YRD'));

        if ($isMudur) {
            $roles[] = 'Müdür';
            return array_values(array_unique($roles));
        }

        // 5. Amir Rolü (MYS Müdür Yrd. veya Unvanında Şef, Amir, Müdür Yrd, Sorumlu, Lider geçenler)
        $isAmir = ($userId && isset($deptManagerUserIds[$userId]) && $deptManagerUserIds[$userId] === 'assistant_manager')
            || str_contains($title, 'AMİR')
            || str_contains($title, 'ŞEF')
            || str_contains($title, 'MÜDÜR YARDIMCISI')
            || str_contains($title, 'MÜDÜR YRD')
            || str_contains($title, 'SORUMLU')
            || str_contains($title, 'LİDER')
            || str_contains($title, 'BAŞMÜHENDİS')
            || str_contains($title, 'SUPERVISOR');

        if ($isAmir) {
            $roles[] = 'Amir';
            return array_values(array_unique($roles));
        }

        // 6. Mavi Yaka Rolü
        if ($isMaviYaka) {
            $roles[] = 'Mavi Yaka';
            return array_values(array_unique($roles));
        }

        // 7. Kullanıcı Rolü (Yukarıdaki unvanlara girmeyen tüm şirket personeli: Uzman, Mühendis, Memur vb.)
        $roles[] = 'Kullanıcı';

        return array_values(array_unique($roles));
    }
}
