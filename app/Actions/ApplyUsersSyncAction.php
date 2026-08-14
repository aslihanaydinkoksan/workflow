<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ApplyUsersSyncAction
{
    /**
     * Arayüzden gelen onaylanmış değişiklikleri veritabanına uygular.
     * Veri bütünlüğü için DB::transaction kullanılmıştır.
     *
     * @param array $updates Vue'den gönderilen onaylanmış değişiklikler dizisi
     * @return array [addedCount, updatedCount]
     */
    public function execute(array $updates): array
    {
        $addedCount = 0;
        $updatedCount = 0;

        // Merkezi SSO kullanıldığı için şifre önemli değil, döngü dışında 1 kere oluşturuyoruz (Performans).
        $dummyPassword = bcrypt(Str::random(16));

        DB::transaction(function () use ($updates, &$addedCount, &$updatedCount, $dummyPassword) {
            foreach ($updates as $update) {
                $changes = $update['changes'];

                if (str_starts_with($update['user_id'], 'new_')) {
                    // YENİ KULLANICI EKLEME
                    User::create([
                        'name'            => $update['name'],
                        'email'           => $update['email'],
                        'password'        => $dummyPassword,
                        'tc_no'           => $changes['tc_no']['new'] ?? null,
                        'registration_no' => $changes['registration_no']['new'] ?? null,
                        'title'           => $changes['title']['new'] ?? null,
                        'is_customer'     => $changes['is_customer']['new_val'] ?? false,
                        'is_mavi_yaka'    => $changes['is_mavi_yaka']['new_val'] ?? false,
                        'department_id'   => $changes['department_id']['new_id'] ?? null,
                    ]);
                    $addedCount++;
                } else {
                    // MEVCUT KULLANICIYI GÜNCELLEME
                    $user = User::find($update['user_id']);

                    if ($user) {
                        $userUpdates = [];

                        if (array_key_exists('tc_no', $changes)) $userUpdates['tc_no'] = $changes['tc_no']['new'];
                        if (array_key_exists('registration_no', $changes)) $userUpdates['registration_no'] = $changes['registration_no']['new'];
                        if (array_key_exists('title', $changes)) $userUpdates['title'] = $changes['title']['new'];
                        if (array_key_exists('is_customer', $changes)) $userUpdates['is_customer'] = $changes['is_customer']['new_val'];
                        if (array_key_exists('is_mavi_yaka', $changes)) $userUpdates['is_mavi_yaka'] = $changes['is_mavi_yaka']['new_val'];
                        if (array_key_exists('department_id', $changes)) $userUpdates['department_id'] = $changes['department_id']['new_id'];

                        if (!empty($userUpdates)) {
                            $user->update($userUpdates);
                            $updatedCount++;
                        }
                    }
                }
            }
        });

        // Olası bir takibe karşı arkada sessizce logluyoruz
        Log::info("Kullanıcı senkronizasyonu tamamlandı: {$addedCount} eklendi, {$updatedCount} güncellendi.");

        return [
            'added'   => $addedCount,
            'updated' => $updatedCount
        ];
    }
}
