<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Node;
use App\Services\NodeClosureService;

class NodeObserver
{
    public function __construct(
        private readonly NodeClosureService $closureService
    ) {}

    /**
     * Düğüm veritabanına kaydedildikten HEMEN SONRA tetiklenir.
     */
    public function created(Node $node): void
    {
        // Not: nodes tablosunda parent_id olmadığı için, oluşturma işlemi sırasında 
        // ebeveyn modelini geçici (virtual) bir özellik olarak modelin içine enjekte edebiliriz.
        // Örnek Kullanım: 
        // $node = new Node([...]);
        // $node->parent_node = $parentNode; // DB'ye yansımaz, observer'da yakalanır
        // $node->save();

        $parent = $node->parent_node instanceof Node ? $node->parent_node : null;
        $this->closureService->attachNode($node, $parent);
    }
}
