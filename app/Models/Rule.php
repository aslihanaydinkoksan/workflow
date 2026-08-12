<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Rule extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'node_id',
        'name',
        'priority',
        'condition_type',
        'conditions',
        'action',
        'is_active',
    ];

    /**
     * JSON (longtext) sütunların otomatik Array objesine dönüştürülmesi
     * is_active alanının boolean olarak ele alınması
     */
    protected $casts = [
        'conditions'     => 'array',
        'action'         => 'array',
        'is_active'      => 'boolean',
        'priority'       => 'integer',
        'workflow_id'    => 'integer',
    ];

    /**
     * Belirli bir iş akışı ve düğüm (node) için aktif kuralları öncelik sırasına göre getirir.
     */
    public function scopeForNode(Builder $query, int $workflowId, string $nodeId): Builder
    {
        return $query
            ->where('workflow_id', $workflowId)
            ->where('node_id', $nodeId)
            ->where('is_active', true)
            ->orderBy('priority', 'asc'); // 1 en yüksek öncelik
    }
}
