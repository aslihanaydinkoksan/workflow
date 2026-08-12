<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Rule;
use Illuminate\Support\Arr;

/**
 * Sınıf Sorumluluğu: İş süreçlerindeki dinamik kuralları, genişletilmiş hiyerarşik bağlam (Context)
 * verilerini (Form, Aktör Metadata, Hedef Ekipman vb.) doğrultusunda analiz eder ve yönlendirme kararı üretir.
 */
class RulesEngine
{
    /**
     * İlgili iş akışı düğümüne ait kuralları öncelik sırasına göre değerlendirir.
     * * @param int $workflowId İş akışı ID'si
     * @param string $nodeId İş akışı üzerindeki grafik düğüm ID'si (örn: "node_1")
     * @param array $context Genişletilmiş bağlam dizisi (form, actor, target)
     * @return RuleAction|null Eşleşen kuralın aksiyonu veya null
     */
    public function evaluateNodeRules(int $workflowId, string $nodeId, array $context): ?RuleAction
    {
        // 1. İlgili akış adımı için tanımlanmış tüm aktif kuralları öncelik (priority) sırasına göre getirir
        $rules = Rule::forNode($workflowId, $nodeId)->orderBy('priority', 'asc')->get();

        // 2. Her bir kuralı genişletilmiş bağlam (context) üzerinden değerlendirir
        foreach ($rules as $rule) {
            if ($this->evaluateRule($rule, $context)) {
                // Öncelik sırasına göre eşleşen ilk kuralın aksiyonunu döner (Early return)
                return new RuleAction($rule->action);
            }
        }

        // Koşullarla eşleşen herhangi bir kural bulunamadıysa süreç standart akışında ilerler
        return null;
    }

    /**
     * Kuralın içerdiği tüm koşul gruplarını kural tipine (AND/OR) göre işler.
     */
    private function evaluateRule(Rule $rule, array $context): bool
    {
        $conditions = collect($rule->conditions);

        if ($conditions->isEmpty()) {
            return false;
        }

        $type = $rule->condition_type; // 'all' (AND) veya 'any' (OR)

        // AND (all) Mantığı: Tüm koşulların sağlanması zorunludur
        if ($type === 'all') {
            return $conditions->every(fn($condition) => $this->evaluateCondition($condition, $context));
        }

        // OR (any) Mantığı: Koşullardan en az birinin sağlanması yeterlidir
        if ($type === 'any') {
            return $conditions->contains(fn($condition) => $this->evaluateCondition($condition, $context));
        }

        return false;
    }

    /**
     * Tekil bir koşul satırını genişletilmiş bağlam üzerinde doğrular.
     * Arr::get() metodu sayesinde 'actor.metadata.src_belgesi' gibi derin JSON alanlarını doğrudan çözer.
     */
    private function evaluateCondition(array $condition, array $context): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? '==';
        $expectedValue = $condition['value'] ?? null;

        // Bağlam hiyerarşisinden (form.*, actor.*, target.*) dot notation ile veriyi güvenle çekiyoruz
        $actualValue = Arr::get($context, $field);

        \Illuminate\Support\Facades\Log::info('--- KURAL MOTORU TESTİ ---', [
            'Aranan_Alan' => $field,
            'Sistemden_Gelen_Gercek_Deger' => $actualValue,
            'Kural_Sihirbazindaki_Beklenen_Deger' => $expectedValue,
            'Gercek_Deger_Tipi' => gettype($actualValue),
            'Beklenen_Deger_Tipi' => gettype($expectedValue),
        ]);

        return match ($operator) {
            '=='       => $actualValue == $expectedValue,
            '==='      => $actualValue === $expectedValue,
            '!='       => $actualValue != $expectedValue,
            '>'        => $actualValue > $expectedValue,
            '>='       => $actualValue >= $expectedValue,
            '<'        => $actualValue < $expectedValue,
            '<='       => $actualValue <= $expectedValue,
            'contains' => is_string($actualValue) && str_contains($actualValue, (string) $expectedValue),
            'in'       => is_array($expectedValue) && in_array($actualValue, $expectedValue),
            'not_in'   => is_array($expectedValue) && !in_array($actualValue, $expectedValue),
            // YENİ EKLENEN ÇOK AMAÇLI FORM KONTROLLERİ
            'is_empty'     => empty($actualValue),
            'is_not_empty' => !empty($actualValue),
            'is_null'      => is_null($actualValue),
            'is_not_null'  => !is_null($actualValue),
            default    => false,
        };
    }
}
