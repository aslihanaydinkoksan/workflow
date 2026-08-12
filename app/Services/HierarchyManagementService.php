<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Node;
use App\Models\NodeClosure;
use App\Models\TreeType;
use Illuminate\Support\Facades\DB;

class HierarchyManagementService
{
    public function __construct(
        private readonly NodeClosureService $closureService,
        private readonly NodeValidationService $validationService
    ) {}

    public function createNode(array $data, ?int $parentId = null): Node
    {
        $treeType = TreeType::findOrFail($data['tree_type_id']);
        $this->validationService->validateMetadata($treeType, $data['metadata'] ?? []);

        return DB::transaction(function () use ($data, $parentId) {
            $node = new Node($data);
            $node->save();

            // 1) Düğümün kendine ait kapanış kaydı (depth 0)
            // ÇÖZÜM BURADA: updateOrCreate YERİNE DOĞRUDAN INSERT KULLANILIYOR
            DB::table('node_closures')->insert([
                'ancestor_id'   => $node->id,
                'descendant_id' => $node->id,
                'depth'         => 0,
            ]);

            // 2) Parent varsa, parent'ın tüm atalarını yeni düğüme bağla
            if ($parentId) {
                $ancestorRows = DB::table('node_closures')->where('descendant_id', $parentId)->get();

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

    public function updateNode(Node $node, array $data): Node
    {
        $this->validationService->validateMetadata($node->treeType, $data['metadata'] ?? []);
        $node->update($data);
        return $node;
    }

    public function deleteNode(Node $node): void
    {
        DB::transaction(function () use ($node) {
            $descendants = NodeClosure::where('ancestor_id', $node->id)
                ->where('descendant_id', '!=', $node->id)
                ->pluck('descendant_id');

            Node::whereIn('id', $descendants)->delete();
            $node->delete();
        });
    }

    public function moveNode(Node $node, ?Node $newParent): void
    {
        DB::transaction(function () use ($node, $newParent) {
            $subtreeIds = NodeClosure::where('ancestor_id', $node->id)
                ->pluck('descendant_id')
                ->toArray();

            if ($newParent && in_array($newParent->id, $subtreeIds)) {
                throw new \Exception("Bir düğüm kendi altındaki bir düğüme taşınamaz.");
            }

            DB::table('node_closures')
                ->whereIn('descendant_id', $subtreeIds)
                ->whereNotIn('ancestor_id', $subtreeIds)
                ->delete();

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
