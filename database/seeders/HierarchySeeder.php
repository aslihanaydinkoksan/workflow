<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TreeType;
use App\Models\Node;

class HierarchySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        \App\Models\NodeClosure::truncate();
        Node::truncate();
        TreeType::truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
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
        $merkezFabrika = new Node([
            'tree_type_id' => $treeType->id,
            'key'          => 'hq_merkez_fabrika',
            'node_subtype' => 'fabrika',
            'label'        => 'Merkez Fabrika',
        ]);
        $merkezFabrika->save();

        // 3. Alt Düğüm 1: Üretim Bölümü
        $uretimBolumu = new Node([
            'tree_type_id' => $treeType->id,
            'key'          => 'dept_uretim',
            'node_subtype' => 'bolum',
            'label'        => 'Üretim Bölümü',
        ]);
        // Observer'ın Closure tablosunu doldurması için sanal özelliği atıyoruz
        $uretimBolumu->parent_node = $merkezFabrika;
        $uretimBolumu->save();
        unset($uretimBolumu->parent_node);

        // 3.1. Alt Düğüm 1.1: Ünite 1
        $unite1 = new Node([
            'tree_type_id' => $treeType->id,
            'key'          => 'unit_uretim_1',
            'node_subtype' => 'unite',
            'label'        => 'Ünite 1',
        ]);
        $unite1->parent_node = $uretimBolumu;
        $unite1->save();
        unset($uretimBolumu->parent_node);

        // 3.2. Alt Düğüm 1.2: Ünite 2
        $unite2 = new Node([
            'tree_type_id' => $treeType->id,
            'key'          => 'unit_uretim_2',
            'node_subtype' => 'unite',
            'label'        => 'Ünite 2',
        ]);
        $unite2->parent_node = $uretimBolumu;
        $unite2->save();
        unset($uretimBolumu->parent_node);

        // 4. Alt Düğüm 2: Lojistik Bölümü (Lojistik_personeli gibi raw stringler kullanılmadı)
        $lojistikBolumu = new Node([
            'tree_type_id' => $treeType->id,
            'key'          => 'dept_lojistik',
            'node_subtype' => 'bolum',
            'label'        => 'Lojistik Bölümü',
        ]);
        $lojistikBolumu->parent_node = $merkezFabrika;
        $lojistikBolumu->save();
        unset($uretimBolumu->parent_node);

        // 4.1. Alt Düğüm 2.1: Depo Yönetimi
        $depoYonetimi = new Node([
            'tree_type_id' => $treeType->id,
            'key'          => 'unit_depo',
            'node_subtype' => 'unite',
            'label'        => 'Depo Yönetimi',
        ]);
        $depoYonetimi->parent_node = $lojistikBolumu;
        $depoYonetimi->save();
        unset($uretimBolumu->parent_node);
    }
}
