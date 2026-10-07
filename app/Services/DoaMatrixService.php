<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProcessInstance;
use Illuminate\Support\Facades\Log;

class DoaMatrixService
{
    /**
     * Kurumsal DoA (Delegation of Authority) Tutar Eşikleri (EUR/TRY bazlı)
     */
    public const THRESHOLDS = [
        'first_manager' => 1000.0,   // İlk Amir Onay Eşiği (<= 1.000 EUR)
        'department'    => 5000.0,   // Departman Müdürü Onay Eşiği (<= 5.000 EUR)
        'gmy'           => 25000.0,  // GMY Onay Eşiği (<= 25.000 EUR)
        'ceo'           => 100000.0, // CEO / Yönetim Kurulu Eşiği (> 25.000 EUR)
    ];

    /**
     * Süreç verisinden parasal harcama / avans tutarını ayıklar.
     */
    public function extractMonetaryAmount(ProcessInstance $instance): ?float
    {
        $data = (array) ($instance->data ?? []);

        $amountKeys = [
            'talep_edilen_avans',
            'genel_toplam_masraf',
            'belgeli_harcama_toplam',
            'tutar',
            'fatura_tutari',
            'butce_tutari',
        ];

        foreach ($amountKeys as $key) {
            if (!empty($data[$key])) {
                $val = $data[$key];
                if (is_numeric($val)) {
                    return (float) $val;
                }
                if (is_string($val)) {
                    if (preg_match('/(\d+(?:[.,]\d+)*)/', $val, $matches)) {
                        $rawNumber = $matches[1];
                        
                        // Hem nokta hem virgül varsa
                        if (str_contains($rawNumber, '.') && str_contains($rawNumber, ',')) {
                            if (strrpos($rawNumber, ',') > strrpos($rawNumber, '.')) {
                                // 1.500,50 (TR)
                                $rawNumber = str_replace('.', '', $rawNumber);
                                $rawNumber = str_replace(',', '.', $rawNumber);
                            } else {
                                // 1,500.50 (US)
                                $rawNumber = str_replace(',', '', $rawNumber);
                            }
                        } elseif (str_contains($rawNumber, '.')) {
                            // Sadece nokta var: Eğer noktadan sonra tam 3 basamak varsa (15.000 veya 1.000), binlik ayıracıdır
                            if (preg_match('/^\d{1,3}\.\d{3}$/', $rawNumber)) {
                                $rawNumber = str_replace('.', '', $rawNumber);
                            }
                        } elseif (str_contains($rawNumber, ',')) {
                            // Sadece virgül var
                            if (preg_match('/^\d{1,3},\d{3}$/', $rawNumber)) {
                                $rawNumber = str_replace(',', '', $rawNumber);
                            } else {
                                $rawNumber = str_replace(',', '.', $rawNumber);
                            }
                        }

                        if (is_numeric($rawNumber)) {
                            return (float) $rawNumber;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Verilen adımın DoA finansal onay eşiklerine göre otomatik geçilip geçilmeyeceğini değerlendirir.
     *
     * @return array{bypass: bool, reason: ?string, amount: ?float, threshold: ?float, tier: ?string}
     */
    public function evaluateNodeDoa(ProcessInstance $instance, array $node): array
    {
        $nodeData = $node['data'] ?? [];
        $nodeLabel = mb_strtolower(($nodeData['customName'] ?? '') . ' ' . ($nodeData['label'] ?? '') . ' ' . ($node['id'] ?? ''), 'UTF-8');
        $taskType = $nodeData['taskType'] ?? $node['type'] ?? 'approval';

        if (!in_array($taskType, ['approval', 'review'], true)) {
            return ['bypass' => false, 'reason' => null, 'amount' => null, 'threshold' => null, 'tier' => null];
        }

        $amount = $this->extractMonetaryAmount($instance);
        if ($amount === null) {
            return ['bypass' => false, 'reason' => null, 'amount' => null, 'threshold' => null, 'tier' => null];
        }

        // Düğüm açıkça bir DoA eşiği tanımlamış mı?
        $customThreshold = $nodeData['doaThreshold'] ?? $nodeData['doa_threshold'] ?? null;
        if ($customThreshold !== null && is_numeric($customThreshold)) {
            $threshold = (float) $customThreshold;
            if ($amount <= $threshold) {
                return [
                    'bypass' => true,
                    'reason' => "Talep tutarı (" . number_format($amount, 2, ',', '.') . " EUR), tanımlı onay eşiğinin (" . number_format($threshold, 2, ',', '.') . " EUR) altında kaldığı için DoA kuralı gereğince onay adımı otomatik geçilmiştir.",
                    'amount' => $amount,
                    'threshold' => $threshold,
                    'tier' => 'custom',
                ];
            }
            return ['bypass' => false, 'reason' => null, 'amount' => $amount, 'threshold' => $threshold, 'tier' => 'custom'];
        }

        // GMY / CEO Onay adımları otomatik tespiti
        $isGmyStep = str_contains($nodeLabel, 'gmy') || str_contains($nodeLabel, 'genel müdür yardımcısı');
        $isCeoStep = !$isGmyStep && (str_contains($nodeLabel, 'ceo') || str_contains($nodeLabel, 'genel müdür onayı'));

        if ($isGmyStep && $amount < self::THRESHOLDS['department']) {
            // Tutar 5.000 EUR'un altındaysa GMY onayı aranmaz
            return [
                'bypass' => true,
                'reason' => "Talep tutarı (" . number_format($amount, 2, ',', '.') . " EUR), GMY onay eşiğinin (" . number_format(self::THRESHOLDS['department'], 2, ',', '.') . " EUR) altında kaldığı için DoA Finansal Yetki Matrisi gereğince bu adım otomatik geçilmiştir.",
                'amount' => $amount,
                'threshold' => self::THRESHOLDS['department'],
                'tier' => 'gmy',
            ];
        }

        if ($isCeoStep && $amount < self::THRESHOLDS['gmy']) {
            // Tutar 25.000 EUR'un altındaysa CEO onayı aranmaz
            return [
                'bypass' => true,
                'reason' => "Talep tutarı (" . number_format($amount, 2, ',', '.') . " EUR), CEO onay eşiğinin (" . number_format(self::THRESHOLDS['gmy'], 2, ',', '.') . " EUR) altında kaldığı için DoA Finansal Yetki Matrisi gereğince bu adım otomatik geçilmiştir.",
                'amount' => $amount,
                'threshold' => self::THRESHOLDS['gmy'],
                'tier' => 'ceo',
            ];
        }

        return ['bypass' => false, 'reason' => null, 'amount' => $amount, 'threshold' => null, 'tier' => null];
    }
}
