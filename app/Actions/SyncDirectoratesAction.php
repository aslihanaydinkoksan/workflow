<?php

namespace App\Actions;

use App\Models\Directorate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncDirectoratesAction
{
    /**
     * MYS'den gelen direktörlükleri yerel veritabanına aktarır.
     */
    public function execute(array $centralDirectorates): array
    {
        // YEREL TEST KORUMASI: Sadece ilk 3 direktörlüğü al
        // if (app()->environment('local')) {
        //     $centralDirectorates = array_slice($centralDirectorates, 0, 3);
        // }

        $syncedCount = 0;

        // Kullanıcı eşleştirme işlemleri için tüm kullanıcıları RAM'e (Memory) alıyoruz.
        $localUsers = User::all();
        $usersByEmail = $localUsers->keyBy('email');
        $usersByTc = $localUsers->keyBy('tc_no')->filter(fn($u, $k) => !empty($k));
        $usersByName = $localUsers->mapWithKeys(fn($u) => [mb_strtolower($u->name) => $u]);

        // Eşleştirme fonksiyonunu (O(1) karmaşıklığı ile) bir Closure olarak tanımlıyoruz.
        $findUserId = function ($centralUser) use ($usersByEmail, $usersByTc, $usersByName) {
            if (!$centralUser) return null;

            if (!empty($centralUser['tc_no']) && $usersByTc->has($centralUser['tc_no'])) {
                return $usersByTc->get($centralUser['tc_no'])->id;
            }
            if (!empty($centralUser['email']) && $usersByEmail->has($centralUser['email'])) {
                return $usersByEmail->get($centralUser['email'])->id;
            }
            if (!empty($centralUser['name'])) {
                $nameLower = mb_strtolower($centralUser['name']);
                if ($usersByName->has($nameLower)) {
                    return $usersByName->get($nameLower)->id;
                }
            }
            return null;
        };

        // Veri bütünlüğü için Transaction başlatıyoruz
        DB::transaction(function () use ($centralDirectorates, &$syncedCount, $findUserId) {
            foreach ($centralDirectorates as $dir) {
                $directorUserId = null;

                // MYS'den 'director' objesi dolu gelmişse yereldeki ID'sini bul.
                if (!empty($dir['director'])) {
                    $directorUserId = $findUserId($dir['director']);
                }

                // İsme göre bul ve güncelle, yoksa yeni oluştur.
                Directorate::updateOrCreate(
                    ['name' => $dir['name']],
                    [
                        'director_id' => $directorUserId,
                        'is_active'   => $dir['is_active'] ?? true,
                    ]
                );
                $syncedCount++;
            }
        });

        Log::info("Direktörlük senkronizasyonu tamamlandı: {$syncedCount} kayıt işlendi.");

        return ['synced' => $syncedCount];
    }
}
