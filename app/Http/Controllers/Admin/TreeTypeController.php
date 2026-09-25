<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TreeType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TreeTypeController extends Controller
{
    /**
     * Ağaç tiplerini bağlı düğüm sayılarıyla birlikte listeler.
     */
    public function index(): Response
    {
        $treeTypes = TreeType::withCount('nodes')->orderBy('id', 'desc')->get();

        return Inertia::render('Admin/TreeTypes/Index', [
            'treeTypes' => $treeTypes
        ]);
    }

    /**
     * Yeni ağaç tipi oluşturur.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateTreeType($request);

        if (empty($validated['key'])) {
            $validated['key'] = Str::slug($validated['display_name']) . '_' . Str::lower((string) Str::ulid());
        }

        $treeType = TreeType::create([
            'key'          => $validated['key'],
            'display_name' => $validated['display_name'],
            'description'  => $validated['description'] ?? null,
            'schema'       => $validated['schema'] ?? [],
            'is_active'    => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success'  => true,
            'message'  => 'Ağaç tipi başarıyla oluşturuldu.',
            'treeType' => $treeType->loadCount('nodes')
        ]);
    }

    /**
     * Mevcut ağaç tipini günceller.
     */
    public function update(Request $request, TreeType $treeType): JsonResponse
    {
        $validated = $this->validateTreeType($request, $treeType->id);

        // Key boş bırakılmışsa orijinal key korunur
        if (empty($validated['key'])) {
            $validated['key'] = $treeType->key;
        }

        $treeType->update([
            'key'          => $validated['key'],
            'display_name' => $validated['display_name'],
            'description'  => $validated['description'] ?? null,
            'schema'       => $validated['schema'] ?? [],
            'is_active'    => $validated['is_active'] ?? $treeType->is_active,
        ]);

        return response()->json([
            'success'  => true,
            'message'  => 'Ağaç tipi başarıyla güncellendi.',
            'treeType' => $treeType->loadCount('nodes')
        ]);
    }

    /**
     * Ağaç tipini siler. Bağlı düğümler varsa veri kaybını önlemek için engeller.
     */
    public function destroy(TreeType $treeType): JsonResponse
    {
        $nodesCount = $treeType->nodes()->count();
        if ($nodesCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Bu ağaç tipine bağlı {$nodesCount} adet organizasyon düğümü bulunmaktadır. Bağlı düğümleri silmeden bu ağaç tipini silemezsiniz."
            ], 422);
        }

        $treeType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ağaç tipi başarıyla silindi.'
        ]);
    }

    /**
     * Gelen TreeType ve JSON Schema verilerini doğrular ve normalize eder.
     */
    private function validateTreeType(Request $request, ?int $ignoreId = null): array
    {
        $keyRule = $ignoreId
            ? "nullable|string|max:100|regex:/^[a-z0-9_-]+$/|unique:tree_types,key,{$ignoreId}"
            : "nullable|string|max:100|regex:/^[a-z0-9_-]+$/|unique:tree_types,key";

        $validated = $request->validate([
            'key'               => $keyRule,
            'display_name'      => 'required|string|max:255',
            'description'       => 'nullable|string',
            'is_active'         => 'nullable|boolean',
            'schema'            => 'nullable|array',
            'schema.*.name'     => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_]+$/'],
            'schema.*.label'    => 'required|string|max:255',
            'schema.*.type'     => 'required|string|in:text,textarea,number,boolean,date,select,multiselect',
            'schema.*.required' => 'nullable|boolean',
            'schema.*.unit'     => 'nullable|string|max:50',
            'schema.*.options'  => 'nullable',
        ], [
            'schema.*.name.regex'    => 'Alan anahtarı (key) sadece harf, rakam ve alt tire içerebilir.',
            'schema.*.name.required' => 'Her alan için veritabanı anahtarı (key) zorunludur.',
            'schema.*.label.required'=> 'Her alan için görünen ad (label) zorunludur.',
            'key.regex'              => 'Sistem anahtarı (key) sadece küçük harf, rakam, tire ve alt tire içerebilir.',
        ]);

        if (!empty($validated['schema'])) {
            $seenNames = [];

            foreach ($validated['schema'] as $index => &$item) {
                $fieldName = strtolower(trim($item['name']));
                $item['name'] = $fieldName;
                $item['field'] = $fieldName;

                // Mükerrer alan adı kontrolü (Duplicate field name check)
                if (in_array($fieldName, $seenNames, true)) {
                    throw ValidationException::withMessages([
                        "schema.{$index}.name" => "'{$fieldName}' anahtarı şemada birden fazla kez kullanılamaz."
                    ]);
                }
                $seenNames[] = $fieldName;

                // Unit temizleme ve normalizasyon
                if (isset($item['unit'])) {
                    $trimmedUnit = trim((string) $item['unit']);
                    $item['unit'] = ($trimmedUnit === '' || strtolower($trimmedUnit) === 'null') ? null : $trimmedUnit;
                } else {
                    $item['unit'] = null;
                }

                // Seçenekleri temiz diziye dönüştürme
                if (isset($item['options']) && is_string($item['options'])) {
                    $item['options'] = array_values(array_filter(array_map('trim', explode(',', $item['options']))));
                } elseif (isset($item['options']) && is_array($item['options'])) {
                    $item['options'] = array_values(array_filter(array_map('trim', $item['options'])));
                } else {
                    $item['options'] = null;
                }

                $type = $item['type'] ?? '';
                $options = $item['options'] ?? [];

                if (in_array($type, ['select', 'multiselect'], true) && empty($options)) {
                    throw ValidationException::withMessages([
                        "schema.{$index}.options" => "'{$item['label']}' açılır liste alanı için en az bir seçenek belirtmelisiniz."
                    ]);
                }

                // Tip uyumsuz alanları temizle
                if (!in_array($type, ['select', 'multiselect'], true)) {
                    $item['options'] = null;
                }

                if ($type !== 'number') {
                    $item['unit'] = null;
                }

                $item['required'] = (bool) ($item['required'] ?? false);
            }
        }

        return $validated;
    }
}
