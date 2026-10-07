<?php

namespace App\Services;

use App\Models\User;
use App\Models\Department;
use App\Models\Directorate;
use Illuminate\Support\Facades\DB;

class HierarchyResolverService
{
    /**
     * Düğüm ayarlarına ve süreci başlatan kişiye göre hedef kullanıcı ID'sini çözer.
     * Geriye dönük uyumluluk için tek kullanıcı döndürür.
     */
    public function resolveTargetUser(array $nodeData, ?int $starterId): ?int
    {
        $users = $this->resolveTargetUsers($nodeData, $starterId);
        return $users[0] ?? null;
    }

    /**
     * Düğüm ayarlarına ve süreci başlatan kişiye göre hedef kullanıcı ID'leri dizisini çözer.
     * Çoklu onaylayanlar, departman kadroları veya doğrudan çoklu personel seçimleri için kullanılır.
     *
     * @return int[]
     */
    public function resolveTargetUsers(array $nodeData, ?int $starterId): array
    {
        $strategy = $nodeData['resolve_by'] ?? $nodeData['assignType'] ?? null;

        return match ($strategy) {
            'tree_relation' => $this->resolveByTreeRelationMulti($nodeData, $starterId),
            'hierarchy'     => $this->resolveLegacyManagerMulti($nodeData, $starterId),
            'department'    => $this->resolveDepartmentUsers($nodeData),
            'directorate'   => $this->resolveDirectorateDirectorMulti($nodeData),
            'user'          => $this->resolveDirectUsers($nodeData),
            'starter'       => $starterId ? [$starterId] : [],
            default         => [],
        };
    }

    /**
     * NodeClosure (Ancestor/Descendant) mantığıyla çalışan dinamik hiyerarşi çözümleyici taslağı.
     * JSON payload: {"resolve_by": "tree_relation", "tree_type": "role_hierarchy", "node_subtype": "şef", "scope": "requester_unit"}
     */
    private function resolveByTreeRelation(array $rule, ?int $starterId): ?int
    {
        if (!$starterId) return null;

        $treeType = $rule['tree_type'] ?? 'role_hierarchy';
        $targetSubtype = $rule['node_subtype'] ?? null;

        // 1. Süreci başlatanın belirtilen ağaç tipindeki (tree_type) başlangıç düğümünü (Node) bul.
        $starterNode = DB::table('nodes')
            ->where('user_id', $starterId)
            ->where('tree_type', $treeType)
            ->first();

        if (!$starterNode) return null;

        // 2. NodeClosure tablosu (ancestor/descendant) üzerinden yukarıya doğru (ancestors) tarama yap.
        // Hiyerarşik derinliğe (depth) göre en yakın amiri (asc) buluyoruz.
        $targetNode = DB::table('node_closures as nc')
            ->join('nodes as ancestor', 'nc.ancestor_id', '=', 'ancestor.id')
            ->where('nc.descendant_id', $starterNode->id)
            ->when($targetSubtype, function ($query) use ($targetSubtype) {
                // Sadece belirli bir role/unvana (örneğin "şef") sahip ata düğümlerini filtrele
                return $query->where('ancestor.node_subtype', $targetSubtype);
            })
            ->where('ancestor.id', '!=', $starterNode->id) // Kendisini dahil etme
            ->orderBy('nc.depth', 'asc') // En yakın ata
            ->first();

        return $targetNode ? $targetNode->user_id : null;
    }

    /**
     * Legacy "manager_1", "manager_2" çözümleyicisi.
     */
    private function resolveLegacyManager(array $nodeData, ?int $starterId): ?int
    {
        if (!$starterId) return null;

        $assignValue = $nodeData['assignValue'] ?? $nodeData['role'] ?? null;
        $starter = User::find($starterId);

        if (!$starter) return null;

        if ($assignValue === 'manager_1') {
            return $starter->manager_id;
        }

        if ($assignValue === 'manager_2') {
            $manager = User::find($starter->manager_id);
            return $manager?->manager_id;
        }

        return null;
    }

    /**
     * Departman yöneticisini bulur.
     * 1. Resmi departman müdürü (department_managers tablosu)
     * 2. Resmi müdür yardımcısı (department_managers tablosu)
     * 3. Departmandaki 'Müdür' rolüne sahip ilk aktif personel
     * 4. Departmandaki 'Amir' rolüne sahip ilk aktif personel (Şef, Sorumlu vb.)
     * 5. Unvanında Yönetici/Şef/Amir geçen beyaz yaka personel
     * 6. Departmandaki ilk beyaz yaka aktif personel
     * 7. Son Çare (Fallback): Departmandaki ilk aktif kullanıcı
     */
    /**
     * Departman yöneticisini bulur.
     * Seçilen yönetici kademesine (deptManagerType) veya doğrudan seçilen kişiye (assignedUserId) göre çözümler:
     * - specific_user: Belirtilen yetkili ID'si
     * - assistant_manager: Müdür Yardımcısı
     * - chief: İşletme Şefi / Birim Amiri
     * - manager: Departman Müdürü (Asil)
     */
    private function resolveDepartmentManager(array $nodeData): ?int
    {
        $departmentId = $nodeData['assignValue'] ?? null;
        if (!$departmentId) return null;

        $managerType = $nodeData['deptManagerType'] ?? 'manager';
        $assignedUserId = $nodeData['assignedUserId'] ?? null;

        // 1. Doğrudan Kişi Seçimi (specific_user)
        if ($managerType === 'specific_user' && !empty($assignedUserId)) {
            $user = User::where('id', $assignedUserId)->where('is_active', true)->first();
            if ($user) {
                return (int) $user->id;
            }
        }

        // 2. Müdür Yardımcısı (assistant_manager)
        if ($managerType === 'assistant_manager') {
            $assistantRel = DB::table('department_managers')
                ->where('department_id', $departmentId)
                ->whereIn('type', ['assistant_manager', 'assistant', 'yardimci'])
                ->first();

            if ($assistantRel) {
                return (int) $assistantRel->user_id;
            }

            $assistantUser = User::where('department_id', $departmentId)
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where('title', 'like', '%YARDIMCI%')
                      ->orWhere('title', 'like', '%MD. YRD%')
                      ->orWhere('title', 'like', '%MÜDÜR YRD%');
                })
                ->first();

            if ($assistantUser) {
                return (int) $assistantUser->id;
            }
        }

        // 3. İşletme Şefi / Birim Amiri (chief)
        if ($managerType === 'chief') {
            $chiefRel = DB::table('department_managers')
                ->where('department_id', $departmentId)
                ->whereIn('type', ['chief', 'supervisor', 'amir', 'sef', 'posta_amiri'])
                ->first();

            if ($chiefRel) {
                return (int) $chiefRel->user_id;
            }

            $chiefUser = User::where('department_id', $departmentId)
                ->where('is_active', true)
                ->whereHas('roles', fn($q) => $q->where('name', 'Amir'))
                ->first();

            if ($chiefUser) {
                return (int) $chiefUser->id;
            }

            $titleChief = User::where('department_id', $departmentId)
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where('title', 'like', '%ŞEF%')
                      ->orWhere('title', 'like', '%AMİR%')
                      ->orWhere('title', 'like', '%SORUMLU%')
                      ->orWhere('title', 'like', '%POSTABAŞI%');
                })
                ->first();

            if ($titleChief) {
                return (int) $titleChief->id;
            }
        }

        // 4. Departman Müdürü (manager veya varsayılan/fallback)
        $managerRel = DB::table('department_managers')
            ->where('department_id', $departmentId)
            ->where('type', 'manager')
            ->first();

        if ($managerRel) {
            return (int) $managerRel->user_id;
        }

        // 5. Departmandaki 'Müdür' rolüne sahip aktif personel
        $deptMudur = User::where('department_id', $departmentId)
            ->where('is_active', true)
            ->whereHas('roles', fn($q) => $q->where('name', 'Müdür'))
            ->first();

        if ($deptMudur) {
            return (int) $deptMudur->id;
        }

        // 6. Unvanında MÜDÜR geçen beyaz yaka personel
        $titleMudur = User::where('department_id', $departmentId)
            ->where('is_active', true)
            ->where('title', 'like', '%MÜDÜR%')
            ->first();

        if ($titleMudur) {
            return (int) $titleMudur->id;
        }

        // 7. Departmandaki 'Amir' rolüne sahip aktif personel
        $deptAmir = User::where('department_id', $departmentId)
            ->where('is_active', true)
            ->whereHas('roles', fn($q) => $q->where('name', 'Amir'))
            ->first();

        if ($deptAmir) {
            return (int) $deptAmir->id;
        }

        // 8. Unvanında Yönetici/Şef/Amir/Sorumlu geçen personel
        $titleManager = User::where('department_id', $departmentId)
            ->where('is_active', true)
            ->where('is_mavi_yaka', false)
            ->where(function ($q) {
                $q->where('title', 'like', '%ŞEF%')
                  ->orWhere('title', 'like', '%AMİR%')
                  ->orWhere('title', 'like', '%SORUMLU%');
            })
            ->first();

        if ($titleManager) {
            return (int) $titleManager->id;
        }

        // 9. Departmandaki ilk beyaz yaka personel
        $whiteCollar = User::where('department_id', $departmentId)
            ->where('is_active', true)
            ->where('is_mavi_yaka', false)
            ->first();

        if ($whiteCollar) {
            return (int) $whiteCollar->id;
        }

        // 10. Son Çare: Departmandaki ilk aktif kullanıcı
        $deptUser = User::where('department_id', $departmentId)
            ->where('is_active', true)
            ->first();

        return $deptUser?->id;
    }

    /**
     * Doğrudan seçilen kullanıcı(lar)ı çözer.
     * assignedUserIds (dizi) veya assignValue (tek/virgüllü/dizi) destekler.
     *
     * @return int[]
     */
    private function resolveDirectUsers(array $nodeData): array
    {
        // 1. Çoklu kullanıcı dizisi (assignedUserIds)
        if (!empty($nodeData['assignedUserIds']) && is_array($nodeData['assignedUserIds'])) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $nodeData['assignedUserIds']))));
            if (!empty($ids)) {
                return $ids;
            }
        }

        // 2. assignValue dizisi veya virgülle ayrılmış değerler
        $assignValue = $nodeData['assignValue'] ?? null;
        if (is_array($assignValue)) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $assignValue))));
            if (!empty($ids)) {
                return $ids;
            }
        } elseif (is_string($assignValue) && str_contains($assignValue, ',')) {
            $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $assignValue)))));
            if (!empty($ids)) {
                return $ids;
            }
        } elseif (is_numeric($assignValue) && (int) $assignValue > 0) {
            return [(int) $assignValue];
        }

        // 3. Tekil assignedUserId
        if (!empty($nodeData['assignedUserId']) && (int) $nodeData['assignedUserId'] > 0) {
            return [(int) $nodeData['assignedUserId']];
        }

        return [];
    }

    /**
     * Departman bazlı kullanıcı(lar)ı çözer.
     * Seçilen yönetici kademesine (deptManagerType) göre tekli veya çoklu kullanıcı listesi döndürür:
     * - all_managers: Departmandaki tüm müdür ve müdür yardımcıları (Çoklu)
     * - all_chiefs: Departmandaki tüm işletme şefleri / amirleri (Çoklu)
     * - specific_users: Departmandan seçilen çoklu personeller (Çoklu)
     * - specific_user, manager, assistant_manager, chief: Tekil yetkili
     *
     * @return int[]
     */
    private function resolveDepartmentUsers(array $nodeData): array
    {
        $departmentId = $nodeData['assignValue'] ?? null;
        if (!$departmentId) return [];

        $managerType = $nodeData['deptManagerType'] ?? 'manager';

        // 1. Departmandan Birden Fazla Kişi Seçimi (specific_users)
        if ($managerType === 'specific_users') {
            if (!empty($nodeData['assignedUserIds']) && is_array($nodeData['assignedUserIds'])) {
                $ids = array_values(array_unique(array_filter(array_map('intval', $nodeData['assignedUserIds']))));
                if (!empty($ids)) {
                    return $ids;
                }
            }
            if (!empty($nodeData['assignedUserId'])) {
                return [(int) $nodeData['assignedUserId']];
            }
        }

        // 2. Departmandaki Tüm Müdürler ve Müdür Yardımcıları (all_managers)
        if ($managerType === 'all_managers') {
            $dmUserIds = DB::table('department_managers')
                ->where('department_id', $departmentId)
                ->whereIn('type', ['manager', 'assistant_manager', 'assistant', 'yardimci'])
                ->pluck('user_id')
                ->toArray();

            $roleMudurIds = User::where('department_id', $departmentId)
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereHas('roles', fn($r) => $r->whereIn('name', ['Müdür', 'Müdür Yardımcısı']))
                      ->orWhere('title', 'like', '%MÜDÜR%')
                      ->orWhere('title', 'like', '%MD. YRD%');
                })
                ->pluck('id')
                ->toArray();

            $allManagers = array_values(array_unique(array_filter(array_map('intval', array_merge($dmUserIds, $roleMudurIds)))));
            if (!empty($allManagers)) {
                return $allManagers;
            }
        }

        // 3. Departmandaki Tüm İşletme Şefleri / Amirler (all_chiefs)
        if ($managerType === 'all_chiefs') {
            $chiefDmIds = DB::table('department_managers')
                ->where('department_id', $departmentId)
                ->whereIn('type', ['chief', 'supervisor', 'amir', 'sef', 'posta_amiri'])
                ->pluck('user_id')
                ->toArray();

            $chiefRoleIds = User::where('department_id', $departmentId)
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereHas('roles', fn($r) => $r->where('name', 'Amir'))
                      ->orWhere('title', 'like', '%ŞEF%')
                      ->orWhere('title', 'like', '%AMİR%')
                      ->orWhere('title', 'like', '%SORUMLU%')
                      ->orWhere('title', 'like', '%POSTABAŞI%');
                })
                ->pluck('id')
                ->toArray();

            $allChiefs = array_values(array_unique(array_filter(array_map('intval', array_merge($chiefDmIds, $chiefRoleIds)))));
            if (!empty($allChiefs)) {
                return $allChiefs;
            }
        }

        // 4. Tekil Çözümleme (manager, assistant_manager, chief, specific_user ve varsayılan)
        $single = $this->resolveDepartmentManager($nodeData);
        return $single ? [$single] : [];
    }

    private function resolveLegacyManagerMulti(array $nodeData, ?int $starterId): array
    {
        $id = $this->resolveLegacyManager($nodeData, $starterId);
        return $id ? [$id] : [];
    }

    /**
     * Direktörlük yöneticisini bulur.
     */
    private function resolveDirectorateDirector(array $nodeData): ?int
    {
        $directorateId = $nodeData['assignValue'] ?? null;
        if (!$directorateId) return null;

        $directorate = Directorate::find($directorateId);
        return $directorate?->director_id;
    }

    private function resolveDirectorateDirectorMulti(array $nodeData): array
    {
        $id = $this->resolveDirectorateDirector($nodeData);
        return $id ? [$id] : [];
    }

    private function resolveByTreeRelationMulti(array $nodeData, ?int $starterId): array
    {
        $id = $this->resolveByTreeRelation($nodeData, $starterId);
        return $id ? [$id] : [];
    }
}