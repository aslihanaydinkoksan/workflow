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
     */
    private function resolveDepartmentManager(array $nodeData): ?int
    {
        $departmentId = $nodeData['assignValue'] ?? null;
        if (!$departmentId) return null;

        $managerRel = DB::table('department_managers')
            ->where('department_id', $departmentId)
            ->where('type', 'manager')
            ->first();

        // Yönetici yoksa departmandaki ilk kişiyi yedek olarak ata
        if (!$managerRel) {
            $deptUser = User::where('department_id', $departmentId)->first();
            return $deptUser?->id;
        }

        return $managerRel->user_id;
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