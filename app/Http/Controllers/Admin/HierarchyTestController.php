<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\TreeType;
use App\Models\User;
use App\Models\Department;
use App\Models\Directorate;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Services\HierarchyManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HierarchyTestController extends Controller
{
    public function __construct(
        private readonly HierarchyManagementService $hierarchyService
    ) {}

    public function index(Request $request): Response
    {
        $treeTypes = TreeType::where('is_active', true)->get();
        $currentTypeId = $request->query('type_id', $treeTypes->first()?->id);
        $treeType = $treeTypes->where('id', $currentTypeId)->first();

        $tree = [];
        $subtypes = collect();

        if ($treeType) {
            $subtypes = Node::where('tree_type_id', $treeType->id)
                ->whereNotNull('node_subtype')
                ->distinct()
                ->pluck('node_subtype');

            $rootNodes = Node::where('tree_type_id', $treeType->id)
                ->where('is_active', true)
                ->whereNotIn('id', function ($query) {
                    $query->select('descendant_id')
                        ->from('node_closures')
                        ->whereRaw('ancestor_id != descendant_id');
                })
                ->get();

            $rootIds = $rootNodes->pluck('id');

            if ($rootIds->isNotEmpty()) {
                $allTreeIds = DB::table('node_closures')
                    ->whereIn('ancestor_id', $rootIds)
                    ->pluck('descendant_id')
                    ->unique();

                $allNodes = Node::whereIn('id', $allTreeIds)
                    ->where('is_active', true)
                    ->get()
                    ->keyBy('id');

                $edges = DB::table('node_closures')
                    ->whereIn('ancestor_id', $allTreeIds)
                    ->where('depth', 1)
                    ->get();

                $childrenMap = [];
                foreach ($edges as $edge) {
                    if (isset($allNodes[$edge->descendant_id]) && isset($allNodes[$edge->ancestor_id])) {
                        $childrenMap[$edge->ancestor_id][] = $edge->descendant_id;
                    }
                }

                /** @var Node $rootNode */
                foreach ($rootNodes as $rootNode) {
                    if (isset($allNodes[$rootNode->id])) {
                        /** @var Node $targetRoot */
                        $targetRoot = $allNodes[$rootNode->id];
                        $tree[] = $this->buildTree($targetRoot, $allNodes, $childrenMap);
                    }
                }
            }
        }

        // GÖREV 1: Tüm İlişkisel Varlıkların Çekilmesi
        $users = User::select('id', 'name', 'email')->where('is_active', true)->get();
        $departments = Department::select('id', 'name')->get();
        $directorates = Directorate::select('id', 'name')->get();

        return Inertia::render('Admin/Hierarchy/Test', [
            'nodes'        => $tree,
            'subtypes'     => $subtypes,
            'treeType'     => $treeType,
            'treeTypes'    => $treeTypes,
            'users'        => $users,
            'departments' => $departments,
            'directorates' => $directorates
        ]);
    }

    private function buildTree(Node $node, Collection $allNodes, array $childrenMap): array
    {
        $children = [];

        if (isset($childrenMap[$node->id])) {
            foreach ($childrenMap[$node->id] as $childId) {
                if (isset($allNodes[$childId])) {
                    /** @var Node $childNode */
                    $childNode = $allNodes[$childId];
                    $children[] = $this->buildTree($childNode, $allNodes, $childrenMap);
                }
            }
        }

        return [
            'node'     => $node->toArray(),
            'children' => $children
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tree_type_id' => 'required|integer|exists:tree_types,id',
            'parent_id'    => 'nullable|integer|exists:nodes,id',
            'label'        => 'required|string|max:255',
            'node_subtype' => 'nullable|string|max:50',
            'metadata'     => 'nullable|array',
            'user_id'      => 'nullable|integer|exists:users,id', // Eklenen Alan
        ]);

        $validated['key'] = Str::slug($validated['label']) . '_' . uniqid();

        DB::beginTransaction();
        try {
            // GÖREV 3: Kayıt anında user_id dahil ediliyor (Departman/Direktörlük ID'leri Vue'dan metadata içinde gelecek)
            $node = Node::create([
                'tree_type_id' => $validated['tree_type_id'],
                'key'          => $validated['key'],
                'label'        => $validated['label'],
                'node_subtype' => $validated['node_subtype'] ?? null,
                'metadata'     => $validated['metadata'] ?? [],
                'user_id'      => $validated['user_id'] ?? null,
                'is_active'    => true,
            ]);

            DB::table('node_closures')->insert([
                'ancestor_id'   => $node->id,
                'descendant_id' => $node->id,
                'depth'         => 0,
            ]);

            if (!empty($validated['parent_id'])) {
                $ancestors = DB::table('node_closures')
                    ->where('descendant_id', $validated['parent_id'])
                    ->get();

                $closures = [];
                foreach ($ancestors as $ancestor) {
                    $closures[] = [
                        'ancestor_id'   => $ancestor->ancestor_id,
                        'descendant_id' => $node->id,
                        'depth'         => $ancestor->depth + 1,
                    ];
                }

                DB::table('node_closures')->insert($closures);
            }

            DB::commit();
            return response()->json(['success' => true, 'node' => $node]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, Node $node): JsonResponse
    {
        $validated = $request->validate([
            'label'        => 'required|string|max:255',
            'node_subtype' => 'nullable|string|max:50',
            'metadata'     => 'nullable|array',
            'user_id'      => 'nullable|integer|exists:users,id', // Eklenen Alan
        ]);

        $updatedNode = $this->hierarchyService->updateNode($node, $validated);

        return response()->json(['success' => true, 'node' => $updatedNode]);
    }

    public function destroy(Node $node): JsonResponse
    {
        $this->hierarchyService->deleteNode($node);
        return response()->json(['success' => true]);
    }

    public function move(Request $request, Node $node): JsonResponse
    {
        $validated = $request->validate([
            'new_parent_id' => 'nullable|integer|exists:nodes,id',
        ]);

        try {
            $newParent = $validated['new_parent_id'] ? Node::findOrFail($validated['new_parent_id']) : null;
            $this->hierarchyService->moveNode($node, $newParent);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function updateSchema(Request $request, TreeType $treeType): JsonResponse
    {
        $validated = $request->validate([
            'schema'            => 'nullable|array',
            'schema.*.field'    => 'required|string',
            'schema.*.type'     => 'required|string|in:string,integer,boolean,date',
            'schema.*.required' => 'required|boolean',
        ]);

        $treeType->update(['schema' => $validated['schema'] ?? []]);

        return response()->json(['success' => true]);
    }
}
