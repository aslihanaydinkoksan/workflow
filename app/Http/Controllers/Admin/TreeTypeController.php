<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TreeType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TreeTypeController extends Controller
{
    public function index(): Response
    {
        $treeTypes = TreeType::orderBy('id', 'desc')->get();

        return Inertia::render('Admin/TreeTypes/Index', [
            'treeTypes' => $treeTypes
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateTreeType($request);

        if (empty($validated['key'])) {
            $validated['key'] = Str::slug($validated['display_name']) . '_' . uniqid();
        }

        $treeType = TreeType::create(array_merge($validated, [
            'is_active' => $validated['is_active'] ?? true,
        ]));

        return response()->json(['success' => true, 'treeType' => $treeType]);
    }

    public function update(Request $request, TreeType $treeType): JsonResponse
    {
        $validated = $this->validateTreeType($request, $treeType->id);

        $treeType->update($validated);

        return response()->json(['success' => true, 'treeType' => $treeType]);
    }

    public function destroy(TreeType $treeType): JsonResponse
    {
        $treeType->delete();
        return response()->json(['success' => true]);
    }

    private function validateTreeType(Request $request, ?int $ignoreId = null): array
    {
        $keyRule = $ignoreId ? "nullable|string|unique:tree_types,key,{$ignoreId}" : "nullable|string|unique:tree_types,key";

        $validated = $request->validate([
            'key'               => $keyRule,
            'display_name'      => 'required|string|max:255',
            'description'       => 'nullable|string',
            'is_active'         => 'boolean',
            'schema'            => 'nullable|array',

            // İŞTE BÜYÜK SIR BURADA ÇÖZÜLDÜ! Vue veriyi 'name' olarak gönderiyor:
            'schema.*.name'     => 'required|string',
            'schema.*.label'    => 'required|string',
            'schema.*.type'     => 'required|string',
            'schema.*.required' => 'nullable|boolean',
            'schema.*.unit'     => 'nullable|string|max:50',
            'schema.*.options'  => 'nullable',
        ]);

        if (!empty($validated['schema'])) {
            foreach ($validated['schema'] as $index => &$item) {

                // Vue'dan gelen 'name' değerini, veritabanımızın (ve kural motorumuzun) beklediği 'field' ismine çeviriyoruz
                if (isset($item['name'])) {
                    $item['field'] = $item['name'];
                }

                // Virgüllü metinleri diziye (array) çeviriyoruz
                if (isset($item['options']) && is_string($item['options'])) {
                    $item['options'] = array_filter(array_map('trim', explode(',', $item['options'])));
                }

                $type = $item['type'] ?? '';
                $options = $item['options'] ?? [];

                if (in_array($type, ['select', 'multiselect', 'dropdown']) && empty($options)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "schema.{$index}.options" => "Açılır liste türündeki alanlar için en az bir seçenek (virgülle ayırarak) belirtmelisiniz."
                    ]);
                }
            }
        }

        return $validated;
    }
}
