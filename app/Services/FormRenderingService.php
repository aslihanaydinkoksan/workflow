<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Node;
use Illuminate\Support\Arr;

/**
 * Dinamik form şablonlarını analiz ederek veri eşleştirmelerini yapar.
 */
class FormRenderingService
{
    /**
     * Form şemasını tarar ve 'data_bind' tanımlanmış alanları kullanıcının 
     * metadata içeriğiyle doldurarak prefilled_data array'i döndürür.
     */
    public function getPrefilledData(array $schema, int $userId): array
    {
        // 1. İşlemi başlatan kullanıcının hiyerarşideki kaydını bul
        $node = Node::where('user_id', $userId)->first();
        $prefilledData = [];

        if (!$node) {
            return $prefilledData;
        }

        // Hiyerarşi verisini kolay çözümleme (dot notation) için diziye dönüştür
        $actorContext = ['metadata' => $node->metadata ?? []];

        // 2. Şemayı döngüyle tara ve bağlanan alanları eşleştir
        foreach ($schema as $field) {
            if (!empty($field['data_bind'])) {
                $bindKey = $field['data_bind']; // Örn: 'actor.metadata.src_belgesi'

                // Başlangıç formlarında genellikle sadece 'actor' verisi mevcuttur
                if (str_starts_with($bindKey, 'actor.')) {
                    // 'actor.' prefix'ini atarak metadata içinde arama yap
                    $internalKey = substr($bindKey, 6);

                    $val = Arr::get($actorContext, $internalKey);

                    if ($val !== null) {
                        $prefilledData[$field['id']] = $val; // JSON key -> değere maplenir
                    }
                }
            }
        }

        return $prefilledData;
    }
}
