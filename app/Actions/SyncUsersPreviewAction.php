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
        $localUsers = User::all();
        $usersByEmail = $localUsers->keyBy('email');
        $usersByTc = $localUsers->keyBy('tc_no')->filter(fn($user, $key) => !empty($key));

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

                $usersWithChanges[] = [
                    'user_id' => 'new_' . md5($centralUser['email']), // Vue tarafındaki unique key için
                    'name'    => $centralUser['name'],
                    'email'   => $centralUser['email'],
                    'changes' => [
                        'email'           => ['old' => 'Yok (Yeni)', 'new' => $centralUser['email']],
                        'tc_no'           => ['old' => 'Yok', 'new' => $centralUser['tc_no']],
                        'registration_no' => ['old' => 'Yok', 'new' => $centralUser['registration_no']],
                        'title'           => ['old' => 'Yok', 'new' => $centralUser['job_title']],
                        'is_customer'     => ['old' => 'Yok', 'new' => $centralUser['is_customer'] ? 'Evet' : 'Hayır', 'new_val' => $centralUser['is_customer']],
                        'is_mavi_yaka'    => ['old' => 'Yok', 'new' => $centralUser['is_mavi_yaka'] ? 'Evet' : 'Hayır', 'new_val' => $centralUser['is_mavi_yaka']],
                        'department_id'   => ['old' => 'Yok', 'new' => $newDeptId ? $localDepartmentNames[$newDeptId] : 'Yok', 'new_id' => $newDeptId]
                    ]
                ];
            }
        }

        return $usersWithChanges;
    }
}
