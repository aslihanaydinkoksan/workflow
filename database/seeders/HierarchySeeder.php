<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TreeType;
use App\Models\Node;
use App\Models\NodeClosure;
use App\Services\HierarchyManagementService;
use Illuminate\Support\Facades\Schema;

class HierarchySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(HierarchyManagementService $hierarchyService): void
    {
        Schema::disableForeignKeyConstraints();
        NodeClosure::truncate();
        Node::truncate();
        TreeType::truncate();
        Schema::enableForeignKeyConstraints();

        // 1. Ağaç Tipini Oluştur
        $treeType = TreeType::firstOrCreate(
            ['key' => 'factory_hierarchy'],
            [
                'display_name' => 'Fabrika Organizasyon Yapısı',
                'description'  => 'Fabrikanın ana organizasyon şemasını ve bölümlerini içerir.',
                'is_active'    => true,
            ]
        );

        // 2. Kök Düğüm: Merkez Fabrika (Ebeveyni Yok)
        $merkezFabrika = $hierarchyService->createNode([
            'tree_type_id' => $treeType->id,
            'label'        => 'Merkez Fabrika',
            'node_subtype' => 'fabrika',
        ]);

        // 3. Alt Düğüm 1: Üretim Bölümü
        $uretimBolumu = $hierarchyService->createNode([
            'tree_type_id' => $treeType->id,
            'label'        => 'Üretim Bölümü',
            'node_subtype' => 'bolum',
        ], $merkezFabrika->id);

        // 3.1. Alt Düğüm 1.1: Ünite 1
        $hierarchyService->createNode([
            'tree_type_id' => $treeType->id,
            'label'        => 'Ünite 1',
            'node_subtype' => 'unite',
        ], $uretimBolumu->id);

        // 3.2. Alt Düğüm 1.2: Ünite 2
        $hierarchyService->createNode([
            'tree_type_id' => $treeType->id,
            'label'        => 'Ünite 2',
            'node_subtype' => 'unite',
        ], $uretimBolumu->id);

        // 4. Alt Düğüm 2: Lojistik Bölümü
        $lojistikBolumu = $hierarchyService->createNode([
            'tree_type_id' => $treeType->id,
            'label'        => 'Lojistik Bölümü',
            'node_subtype' => 'bolum',
        ], $merkezFabrika->id);

        // 4.1. Alt Düğüm 2.1: Depo Yönetimi
        $hierarchyService->createNode([
            'tree_type_id' => $treeType->id,
            'label'        => 'Depo Yönetimi',
            'node_subtype' => 'unite',
        ], $lojistikBolumu->id);
    }
}
