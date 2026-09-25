<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\TreeType;
use App\Models\User;
use App\Models\Department;
use App\Models\Directorate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use App\Services\HierarchyManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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

        // İlişkisel Varlıkların Çekilmesi (Performans için 5 dakikalık Cache)
        $users = Cache::remember('hierarchy_users_active', 300, function () {
            return User::select('id', 'name', 'email')->where('is_active', true)->orderBy('name')->get();
        });
        $departments = Cache::remember('hierarchy_departments', 300, function () {
            return Department::select('id', 'name')->orderBy('name')->get();
        });
        $directorates = Cache::remember('hierarchy_directorates', 300, function () {
            return Directorate::select('id', 'name')->orderBy('name')->get();
        });

        return Inertia::render('Admin/Hierarchy/Test', [
            'nodes'        => $tree,
            'subtypes'     => $subtypes,
            'treeType'     => $treeType,
            'treeTypes'    => $treeTypes,
            'users'        => $users,
            'departments'  => $departments,
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

    /**
     * Yeni düğüm oluşturur. Tüm iş mantığı Service'e delege edilmiştir.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tree_type_id' => 'required|integer|exists:tree_types,id',
            'parent_id'    => 'nullable|integer|exists:nodes,id',
            'label'        => 'required|string|max:255',
            'node_subtype' => 'nullable|string|max:50',
            'metadata'     => 'nullable|array',
            'user_id'      => 'nullable|integer|exists:users,id',
        ]);

        try {
            $node = $this->hierarchyService->createNode(
                [
                    'tree_type_id' => $validated['tree_type_id'],
                    'label'        => $validated['label'],
                    'node_subtype' => $validated['node_subtype'] ?? null,
                    'metadata'     => $validated['metadata'] ?? [],
                    'user_id'      => $validated['user_id'] ?? null,
                    'is_active'    => true,
                ],
                $validated['parent_id'] ?? null
            );

            return response()->json(['success' => true, 'node' => $node]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Doğrulama hatası.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            report($e); // Hatayı logla ama kullanıcıya detay sızdırma
            return response()->json([
                'success' => false,
                'message' => 'Düğüm oluşturulurken bir hata oluştu.'
            ], 500);
        }
    }

    /**
     * Düğüm bilgilerini günceller.
     */
    public function update(Request $request, Node $node): JsonResponse
    {
        $validated = $request->validate([
            'label'        => 'required|string|max:255',
            'node_subtype' => 'nullable|string|max:50',
            'metadata'     => 'nullable|array',
            'user_id'      => 'nullable|integer|exists:users,id',
        ]);

        try {
            $updatedNode = $this->hierarchyService->updateNode($node, $validated);
            return response()->json(['success' => true, 'node' => $updatedNode]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Doğrulama hatası.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Düğüm güncellenirken bir hata oluştu.'
            ], 500);
        }
    }

    /**
     * Düğümü ve tüm alt düğümlerini siler.
     */
    public function destroy(Node $node): JsonResponse
    {
        try {
            $this->hierarchyService->deleteNode($node);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Düğüm silinirken bir hata oluştu.'
            ], 500);
        }
    }

    /**
     * Düğümü yeni bir ebeveynin altına taşır.
     */
    public function move(Request $request, Node $node): JsonResponse
    {
        $validated = $request->validate([
            'new_parent_id' => 'nullable|integer|exists:nodes,id',
        ]);

        try {
            $newParent = $validated['new_parent_id']
                ? Node::findOrFail($validated['new_parent_id'])
                : null;

            $this->hierarchyService->moveNode($node, $newParent);

            return response()->json(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Düğüm taşınırken bir hata oluştu.'
            ], 500);
        }
    }

    /**
     * Ağaç tipi JSON şemasını günceller.
     */
    public function updateSchema(Request $request, TreeType $treeType): JsonResponse
    {
        $validated = $request->validate([
            'schema'            => 'nullable|array',
            'schema.*.field'    => 'required|string',
            'schema.*.type'     => 'required|string|in:string,integer,boolean,date,number,text,textarea,select,multiselect',
            'schema.*.required' => 'required|boolean',
        ]);

        $treeType->update(['schema' => $validated['schema'] ?? []]);

        return response()->json(['success' => true]);
    }
}
