<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rule;
use App\Models\Workflow;
use App\Models\TreeType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RuleController extends Controller
{
    /**
     * Yeni bir kural kaydeder (Mevcut veritabanı şemasına uygun).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workflow_id'    => 'required|integer|exists:workflows,id',
            'node_id'        => 'required|string|max:255',
            'name'           => 'required|string|max:255',
            'priority'       => 'required|integer|min:1',
            'condition_type' => 'required|string|in:all,any',
            'conditions'     => 'required|array', // JSON olarak parse edilecek
            'action'         => 'required|array', // JSON olarak parse edilecek
            'is_active'      => 'boolean',
        ]);

        $rule = Rule::create(array_merge($validated, [
            'is_active' => $validated['is_active'] ?? true,
        ]));

        return response()->json(['success' => true, 'rule' => $rule]);
    }
    /**
     * Mevcut bir kuralı günceller (Edit).
     */
    public function update(Request $request, Rule $rule): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'priority'       => 'required|integer|min:1',
            'condition_type' => 'required|string|in:all,any',
            'conditions'     => 'required|array',
            'action'         => 'required|array',
            'is_active'      => 'boolean',
        ]);

        $rule->update($validated);

        return response()->json(['success' => true, 'rule' => $rule]);
    }

    /**
     * Kuralı siler.
     */
    public function destroy(Rule $rule): JsonResponse
    {
        $rule->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Görsel kural sihirbazı için dinamik alanları (Metadata + Form) derler.
     * Vue tarafındaki 'Akıllı Girdi' (Smart Input) mimarisi için type ve options değerlerini normalize eder.
     */
    public function getAvailableFields(Workflow $workflow): JsonResponse
    {
        $fields = [];

        // 1. FORM ALANLARI MAPPING (form.*) -> 'id' kullanıyoruz
        $formSchema = $workflow->formTemplate->schema ?? [];
        foreach ($formSchema as $fieldDef) {
            $fieldName = $fieldDef['id'] ?? '';
            $rawType = strtolower($fieldDef['type'] ?? 'string');

            // Seçenekleri (Options) Array formatına çeviriyoruz
            $options = null;
            if (!empty($fieldDef['options'])) {
                $options = is_array($fieldDef['options'])
                    ? $fieldDef['options']
                    : array_map('trim', explode(',', (string) $fieldDef['options']));
            }

            // Girdi tipini Frontend'in anlayacağı evrensel tiplere dönüştürüyoruz
            $type = 'text';
            if (in_array($rawType, ['select', 'dropdown', 'radio', 'çoklu seçim', 'checkbox-group'])) {
                $type = 'select';
            } elseif (in_array($rawType, ['checkbox', 'boolean', 'bool', 'evet/hayır', 'switch'])) {
                $type = 'boolean';
            } elseif (in_array($rawType, ['number', 'integer', 'int', 'float'])) {
                $type = 'number';
            }

            $fields[] = [
                'value'   => 'form.' . $fieldName,
                'label'   => 'Form: ' . ($fieldDef['label'] ?? $fieldName),
                'type'    => $type,
                'options' => $options
            ];
        }

        // 2. METADATA ALANLARI MAPPING (actor.metadata.* ve target.metadata.*) -> 'field' kullanıyoruz
        $treeTypes = TreeType::whereNotNull('schema')->where('is_active', true)->get();

        foreach ($treeTypes as $treeType) {
            $schema = $treeType->schema ?? [];

            $isActor = str_contains(strtolower($treeType->key), 'personel')
                || str_contains(strtolower($treeType->display_name), 'personel');

            $prefix = $isActor ? 'actor.metadata.' : 'target.metadata.';
            $labelPrefix = $treeType->display_name . ' -> ';

            foreach ($schema as $col) {
                $metaField = $col['field'] ?? '';
                $rawType = strtolower($col['type'] ?? 'string');

                // Seçenekleri Array formatına çeviriyoruz
                $options = null;
                if (!empty($col['options'])) {
                    $options = is_array($col['options'])
                        ? $col['options']
                        : array_map('trim', explode(',', (string) $col['options']));
                }

                // Girdi tipini Frontend'in anlayacağı evrensel tiplere dönüştürüyoruz
                $type = 'text';
                if (in_array($rawType, ['select', 'dropdown', 'enum', 'radio'])) {
                    $type = 'select';
                } elseif (in_array($rawType, ['checkbox', 'boolean', 'bool'])) {
                    $type = 'boolean';
                } elseif (in_array($rawType, ['number', 'integer', 'int', 'float'])) {
                    $type = 'number';
                }

                $fields[] = [
                    'value'   => $prefix . $metaField,
                    'label'   => $labelPrefix . ($col['label'] ?? $metaField),
                    'type'    => $type,
                    'options' => $options
                ];
            }
        }

        return response()->json(['fields' => $fields]);
    }
    /**
     * Belirli bir iş akışı ve düğüm (node) için tanımlanmış kuralları listeler.
     */
    public function getRulesByNode(int $workflowId, string $nodeId): JsonResponse
    {
        $rules = Rule::where('workflow_id', $workflowId)
            ->where('node_id', $nodeId)
            ->orderBy('priority', 'asc') // Öncelik sırasına göre diz
            ->get();

        return response()->json(['rules' => $rules]);
    }
}
