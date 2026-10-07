<?php

namespace App\Services;

use App\Models\ProcessFieldAudit;
use App\Models\ProcessInstance;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ProcessAuditService
{
    /**
     * İki veri dizisi arasındaki farkları hesaplar ve her bir değişen alan için
     * denetim izi (audit trail) kaydı oluşturur.
     */
    public function recordDiff(
        ProcessInstance $instance,
        array $oldData,
        array $newData,
        ?int $userId,
        string $eventType = 'revision',
        ?int $taskId = null,
        ?string $comment = null
    ): int {
        $schema = $instance->workflow?->formTemplate?->schema ?? [];
        $labelsMap = $this->extractFieldLabels($schema);

        $changedCount = 0;

        foreach ($newData as $key => $newVal) {
            // Dahili sistem meta alanlarını atla
            if (str_starts_with($key, '_')) {
                continue;
            }

            $oldVal = $oldData[$key] ?? null;

            if ($this->hasValueChanged($oldVal, $newVal)) {
                $fieldLabel = $labelsMap[$key] ?? $this->beautifyFieldKey($key);

                ProcessFieldAudit::create([
                    'process_instance_id' => $instance->id,
                    'task_id'             => $taskId,
                    'user_id'             => $userId,
                    'event_type'          => $eventType,
                    'field_key'           => $key,
                    'field_label'         => $fieldLabel,
                    'old_value'           => $this->serializeValue($oldVal),
                    'new_value'           => $this->serializeValue($newVal),
                    'comment'             => $comment,
                ]);

                $changedCount++;
            }
        }

        return $changedCount;
    }

    /**
     * Yönetici müdahale aksiyonunu audit günlüğüne kaydeder.
     */
    public function recordAdminIntervention(
        ProcessInstance $instance,
        string $eventType,
        ?int $userId,
        string $fieldLabel,
        ?string $oldValue,
        ?string $newValue,
        ?string $comment = null,
        ?int $taskId = null
    ): ProcessFieldAudit {
        return ProcessFieldAudit::create([
            'process_instance_id' => $instance->id,
            'task_id'             => $taskId,
            'user_id'             => $userId,
            'event_type'          => $eventType,
            'field_key'           => 'admin_action',
            'field_label'         => $fieldLabel,
            'old_value'           => $oldValue,
            'new_value'           => $newValue,
            'comment'             => $comment,
        ]);
    }

    /**
     * Süreç için tüm denetim kayıtlarını kullanıcı ve görev bilgileriyle getirir.
     */
    public function getAuditsForInstance(ProcessInstance $instance): array
    {
        $audits = ProcessFieldAudit::where('process_instance_id', $instance->id)
            ->with(['user', 'task'])
            ->orderByDesc('id')
            ->get();

        return $audits->map(function ($audit) {
            return [
                'id'          => $audit->id,
                'event_type'  => $audit->event_type,
                'field_key'   => $audit->field_key,
                'field_label' => $audit->field_label,
                'old_value'   => $audit->old_value,
                'new_value'   => $audit->new_value,
                'comment'     => $audit->comment,
                'created_at'  => $audit->created_at?->toIso8601String(),
                'user'        => $audit->user ? [
                    'id'   => $audit->user->id,
                    'name' => $audit->user->name,
                ] : null,
                'task'        => $audit->task ? [
                    'id'    => $audit->task->id,
                    'title' => $audit->task->title,
                ] : null,
            ];
        })->toArray();
    }

    /**
     * İki değer arasında anlamlı bir değişiklik olup olmadığını test eder.
     */
    private function hasValueChanged(mixed $oldVal, mixed $newVal): bool
    {
        // Null ve boş string eşit kabul edilebilir
        if (($oldVal === null || $oldVal === '') && ($newVal === null || $newVal === '')) {
            return false;
        }

        // Dizi/tablo karşılaştırması
        if (is_array($oldVal) || is_array($newVal)) {
            return json_encode($oldVal, JSON_UNESCAPED_UNICODE) !== json_encode($newVal, JSON_UNESCAPED_UNICODE);
        }

        // Sayısal değerler için tip toleranslı karşılaştırma (örn: "550" vs 550)
        if (is_numeric($oldVal) && is_numeric($newVal)) {
            return (float) $oldVal !== (float) $newVal;
        }

        return (string) $oldVal !== (string) $newVal;
    }

    /**
     * Değeri okunabilir bir stringe çevirir.
     */
    private function serializeValue(mixed $val): ?string
    {
        if ($val === null) {
            return null;
        }

        if (is_bool($val)) {
            return $val ? 'Evet' : 'Hayır';
        }

        if (is_array($val)) {
            return json_encode($val, JSON_UNESCAPED_UNICODE);
        }

        return (string) $val;
    }

    /**
     * Form şablonundan alan etiket haritasını oluşturur.
     */
    private function extractFieldLabels(array $schema): array
    {
        $map = [];

        $walker = function ($elements) use (&$walker, &$map) {
            if (!is_array($elements)) {
                return;
            }
            foreach ($elements as $el) {
                if (isset($el['id']) && !empty($el['label'])) {
                    $map[$el['id']] = $el['label'];
                }
                if (isset($el['elements']) && is_array($el['elements'])) {
                    $walker($el['elements']);
                }
            }
        };

        $walker($schema);

        return $map;
    }

    /**
     * Etiketi olmayan alan anahtarını temiz ve okunur bir başlığa dönüştürür.
     */
    private function beautifyFieldKey(string $key): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $key));
    }
}
