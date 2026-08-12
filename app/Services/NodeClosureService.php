<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Node;
use App\Models\NodeClosure;

class NodeClosureService
{
    /**
     * Yeni bir düğümü Closure tablosuna ekler. 
     * Ebeveyni (parent) varsa, atalarının (ancestors) derinliğini 1 artırarak kalıtım alır.
     */
    public function attachNode(Node $node, ?Node $parent = null): void
    {
        // 1. Düğümün kendisini işaret eden (self-reference) temel kayıt (Derinlik: 0)
        $closures = [
            [
                'ancestor_id'   => $node->id,
                'descendant_id' => $node->id,
                'depth'         => 0,
            ]
        ];

        // 2. Ebeveyn belirtilmişse, ebeveynin sahip olduğu tüm ataları (kendisinin üstleri dahil) buluyoruz.
        if ($parent) {
            $parentAncestors = NodeClosure::where('descendant_id', $parent->id)->get();

            foreach ($parentAncestors as $ancestor) {
                $closures[] = [
                    'ancestor_id'   => $ancestor->ancestor_id,
                    'descendant_id' => $node->id,
                    'depth'         => $ancestor->depth + 1, // Yeni eklenen düğüm, atalarından 1 derece daha uzakta
                ];
            }
        }

        // 3. Performans için tüm ilişkileri tek bir seferde (batch insert) veritabanına yazıyoruz.
        NodeClosure::insert($closures);
    }
}
