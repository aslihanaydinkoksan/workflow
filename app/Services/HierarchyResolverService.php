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
     */
    public function resolveTargetUser(array $nodeData, ?int $starterId): ?int
    {
        // Öncelik dinamik hiyerarşi motorunda (resolve_by), yoksa geriye dönük uyumluluk (assignType)
        $strategy = $nodeData['resolve_by'] ?? $nodeData['assignType'] ?? null;

        return match ($strategy) {
            'tree_relation' => $this->resolveByTreeRelation($nodeData, $starterId),
            'hierarchy'     => $this->resolveLegacyManager($nodeData, $starterId),
            'department'    => $this->resolveDepartmentManager($nodeData),
            'directorate'   => $this->resolveDirectorateDirector($nodeData),
            default         => null,
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
    private function resolveDepartmentManager(array $nodeData): ?int
    {
        $departmentId = $nodeData['assignValue'] ?? null;
        if (!$departmentId) return null;

        // 1. Resmi departman yöneticisi (Müdür)
        $managerRel = DB::table('department_managers')
            ->where('department_id', $departmentId)
            ->where('type', 'manager')
            ->first();

        if ($managerRel) {
            return (int) $managerRel->user_id;
        }

        // 2. Resmi müdür yardımcısı
        $assistantRel = DB::table('department_managers')
            ->where('department_id', $departmentId)
            ->where('type', 'assistant_manager')
            ->first();

        if ($assistantRel) {
            return (int) $assistantRel->user_id;
        }

        // 3. Departmandaki 'Müdür' rolüne sahip aktif personel
        $deptMudur = User::where('department_id', $departmentId)
            ->where('is_active', true)
            ->whereHas('roles', fn($q) => $q->where('name', 'Müdür'))
            ->first();

        if ($deptMudur) {
            return $deptMudur->id;
        }

        // 4. Departmandaki 'Amir' rolüne sahip aktif personel (Şef, Amir vb.)
        $deptAmir = User::where('department_id', $departmentId)
            ->where('is_active', true)
            ->whereHas('roles', fn($q) => $q->where('name', 'Amir'))
            ->first();

        if ($deptAmir) {
            return $deptAmir->id;
        }

        // 5. Unvanında Yönetici/Şef/Amir/Müdür geçen beyaz yaka personel
        $titleManager = User::where('department_id', $departmentId)
            ->where('is_active', true)
            ->where('is_mavi_yaka', false)
            ->where(function ($q) {
                $q->where('title', 'like', '%MÜDÜR%')
                  ->orWhere('title', 'like', '%ŞEF%')
                  ->orWhere('title', 'like', '%AMİR%')
                  ->orWhere('title', 'like', '%SORUMLU%');
            })
            ->first();

        if ($titleManager) {
            return $titleManager->id;
        }

        // 6. Departmandaki ilk beyaz yaka personel
        $whiteCollar = User::where('department_id', $departmentId)
            ->where('is_active', true)
            ->where('is_mavi_yaka', false)
            ->first();

        if ($whiteCollar) {
            return $whiteCollar->id;
        }

        // 7. Son Çare: Departmandaki ilk aktif kullanıcı
        $deptUser = User::where('department_id', $departmentId)
            ->where('is_active', true)
            ->first();

        return $deptUser?->id;
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
}