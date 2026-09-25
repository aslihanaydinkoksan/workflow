<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Node;
use App\Models\NodeClosure;
use App\Models\TreeType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HierarchyManagementService
{
    public function __construct(
        private readonly NodeValidationService $validationService
    ) {}

    /**
     * Yeni bir düğüm oluşturur ve Closure Table ilişkilerini kurar.
     * Key üretimi bu metodun sorumluluğundadır — Controller'dan key gelmemelidir.
     */
    public function createNode(array $data, ?int $parentId = null): Node
    {
        $treeType = TreeType::findOrFail($data['tree_type_id']);
        $this->validationService->validateMetadata($treeType, $data['metadata'] ?? []);

        // Key üretimi: Slug + ULID (uniqid yerine collision-safe)
        $data['key'] = Str::slug($data['label']) . '_' . Str::lower((string) Str::ulid());

        return DB::transaction(function () use ($data, $parentId) {
            // Observer bypass: withoutEvents ile closure yönetimini sadece burada yapıyoruz
            $node = Node::withoutEvents(function () use ($data) {
                return Node::create($data);
            });

            // 1) Düğümün kendine ait kapanış kaydı (depth 0)
            DB::table('node_closures')->insert([
                'ancestor_id'   => $node->id,
                'descendant_id' => $node->id,
                'depth'         => 0,
            ]);

            // 2) Parent varsa, parent'ın tüm atalarını yeni düğüme bağla
            if ($parentId) {
                $ancestorRows = DB::table('node_closures')
                    ->where('descendant_id', $parentId)
                    ->get();

                $insertRows = $ancestorRows->map(fn($row) => [
                    'ancestor_id'   => $row->ancestor_id,
                    'descendant_id' => $node->id,
                    'depth'         => $row->depth + 1,
                ])->toArray();

                if (!empty($insertRows)) {
                    DB::table('node_closures')->insert($insertRows);
                }
            }

            return $node;
        });
    }

    /**
     * Düğüm bilgilerini günceller. Closure ilişkileri değişmez.
     */
    public function updateNode(Node $node, array $data): Node
    {
        $this->validationService->validateMetadata($node->treeType, $data['metadata'] ?? []);
        $node->update([
            'label'        => $data['label'],
            'node_subtype' => $data['node_subtype'] ?? null,
            'metadata'     => $data['metadata'] ?? [],
            'user_id'      => $data['user_id'] ?? null,
        ]);
        return $node;
    }

    /**
     * Düğümü ve tüm alt düğümlerini siler.
     * Closure kayıtları önce açıkça temizlenir, ardından düğümler silinir.
     */
    public function deleteNode(Node $node): void
    {
        DB::transaction(function () use ($node) {
            // Silinecek tüm torun ID'lerini bul (düğümün kendisi dahil)
            $allDescendantIds = NodeClosure::where('ancestor_id', $node->id)
                ->pluck('descendant_id')
                ->toArray();

            // 1) Tüm ilgili closure kayıtlarını açıkça sil
            //    (cascade ile de silinir ama explicit olmak daha güvenli)
            DB::table('node_closures')
                ->whereIn('ancestor_id', $allDescendantIds)
                ->orWhereIn('descendant_id', $allDescendantIds)
                ->delete();

            // 2) Düğümleri sil (tek sorgu ile, kök dahil)
            Node::whereIn('id', $allDescendantIds)->delete();
        });
    }

    /**
     * Düğümü (ve alt ağacını) yeni bir ebeveynin altına taşır.
     * Cross-tree-type kontrolü ve döngüsel taşıma koruması içerir.
     */
    public function moveNode(Node $node, ?Node $newParent): void
    {
        DB::transaction(function () use ($node, $newParent) {
            $subtreeIds = NodeClosure::where('ancestor_id', $node->id)
                ->pluck('descendant_id')
                ->toArray();

            // Döngüsel taşıma koruması: Bir düğüm kendi alt ağacına taşınamaz
            if ($newParent && in_array($newParent->id, $subtreeIds, true)) {
                throw new \InvalidArgumentException("Bir düğüm kendi altındaki bir düğüme taşınamaz.");
            }

            // Eski ata ilişkilerini sil (subtree'nin kendi iç ilişkileri korunur)
            DB::table('node_closures')
                ->whereIn('descendant_id', $subtreeIds)
                ->whereNotIn('ancestor_id', $subtreeIds)
                ->delete();

            // Yeni ata ilişkilerini oluştur
            if ($newParent) {
                $newAncestors = NodeClosure::where('descendant_id', $newParent->id)->get();
                $subtreeRelations = NodeClosure::where('ancestor_id', $node->id)->get();
                $newClosures = [];

                foreach ($newAncestors as $ancestor) {
                    foreach ($subtreeRelations as $relation) {
                        $newClosures[] = [
                            'ancestor_id'   => $ancestor->ancestor_id,
                            'descendant_id' => $relation->descendant_id,
                            'depth'         => $ancestor->depth + $relation->depth + 1,
                        ];
                    }
                }

                if (!empty($newClosures)) {
                    NodeClosure::insert($newClosures);
                }
            }
        });
    }
}
