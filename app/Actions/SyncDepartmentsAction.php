<?php

namespace App\Actions;

use App\Models\Department;
use App\Models\Directorate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncDepartmentsAction
{
    /**
     * MYS'den gelen departmanları yerel veritabanına aktarır ve yöneticilerini (pivot) eşleştirir.
     */
    public function execute(array $centralDepartments): array
    {
        // YEREL TEST KORUMASI: Sadece ilk 5 departmanı al
        // if (app()->environment('local')) {
        //     $centralDepartments = array_slice($centralDepartments, 0, 5);
        // }

        $syncedCount = 0;

        // RAM Üzerine Alma (N+1 Önlemi)
        $localUsers = User::all();
        $usersByEmail = $localUsers->keyBy('email');
        $usersByTc = $localUsers->keyBy('tc_no')->filter(fn($u, $k) => !empty($k));
        $usersByName = $localUsers->mapWithKeys(fn($u) => [mb_strtolower($u->name) => $u]);

        $localDirectorates = Directorate::pluck('id', 'name')->mapWithKeys(function ($id, $name) {
            return [mb_strtolower($name) => $id];
        })->toArray();

        // Kullanıcı eşleştirme yardımcı fonksiyonu
        $findUserId = function ($centralUser) use ($usersByEmail, $usersByTc, $usersByName) {
            if (!$centralUser) return null;
            if (!empty($centralUser['tc_no']) && $usersByTc->has($centralUser['tc_no'])) return $usersByTc->get($centralUser['tc_no'])->id;
            if (!empty($centralUser['email']) && $usersByEmail->has($centralUser['email'])) return $usersByEmail->get($centralUser['email'])->id;
            if (!empty($centralUser['name'])) {
                $nameLower = mb_strtolower($centralUser['name']);
                if ($usersByName->has($nameLower)) return $usersByName->get($nameLower)->id;
            }
            return null;
        };

        DB::transaction(function () use ($centralDepartments, &$syncedCount, $findUserId, $localDirectorates) {
            foreach ($centralDepartments as $dept) {
                // 1. Müdür ve Müdür Yrd. İsimlerini Metin Olarak Hazırla (Fallback için)
                $mNames = array_map(function ($m) {
                    return is_array($m) ? ($m['name'] ?? '') : $m;
                }, $dept['managers'] ?? []);
                $aNames = array_map(function ($a) {
                    return is_array($a) ? ($a['name'] ?? '') : $a;
                }, $dept['assistant_managers'] ?? []);

                $managerData = [
                    'managers' => array_values(array_filter($mNames)),
                    'assistant_managers' => array_values(array_filter($aNames))
                ];
                $managerInfo = json_encode($managerData, JSON_UNESCAPED_UNICODE);

                // 2. Direktör bilgisini ayarla
                $directorData = $dept['director'] ?? null;
                $directorInfo = is_array($directorData) ? ($directorData['name'] ?? null) : $directorData;

                // 3. Direktörlük ID'sini bul
                // MYS'deki hiyerarşide departmanın içinde "directorate" veya "directorate_name" gibi bir key varsa ona bakabiliriz.
                // Şimdilik MYS'den geldiği varsayılan departmanın 'directorate' bilgisi varsa eşleştiriyoruz.
                $directorateId = null;
                if (!empty($dept['directorate']) && !empty($dept['directorate']['name'])) {
                    $dirNameLower = mb_strtolower($dept['directorate']['name']);
                    $directorateId = $localDirectorates[$dirNameLower] ?? null;
                }

                // 4. Departmanı Kaydet / Güncelle
                $department = Department::updateOrCreate(
                    ['name' => $dept['name']],
                    [
                        'central_id'     => $dept['id'],
                        'directorate_id' => $directorateId,
                        'manager_info'   => $managerInfo,
                        'director_info'  => $directorInfo,
                        'is_synced'      => true,
                        'is_active'      => true,
                    ]
                );

                // 5. Yöneticileri (Pivot Tablosuna) Ekle
                $syncData = [];
                foreach ($dept['managers'] ?? [] as $m) {
                    if ($id = $findUserId($m)) $syncData[$id] = ['type' => 'manager'];
                }
                foreach ($dept['assistant_managers'] ?? [] as $a) {
                    if ($id = $findUserId($a)) $syncData[$id] = ['type' => 'assistant_manager'];
                }

                if (!empty($syncData)) {
                    $department->allManagers()->sync($syncData);
                }

                $syncedCount++;
            }
        });

        Log::info("Departman senkronizasyonu tamamlandı: {$syncedCount} departman işlendi.");

        return ['synced' => $syncedCount];
    }
}
