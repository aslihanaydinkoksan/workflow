<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerminRevision extends Model
{
    protected $fillable = [
        'process_instance_id',
        'requested_by',
        'previous_termin',
        'new_termin',
        'new_target_date',
        'reason_category',
        'reason_note',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'new_target_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public const CATEGORIES = [
        'raw_material' => [
            'label' => 'Hammadde / Malzeme Tedarik Gecikmesi',
            'icon' => '📦',
            'badge_color' => 'bg-amber-100 text-amber-800 border-amber-300',
        ],
        'machine_breakdown' => [
            'label' => 'Ekstrüzyon / Makine Hattı Arızası & Bakım',
            'icon' => '⚙️',
            'badge_color' => 'bg-red-100 text-red-800 border-red-300',
        ],
        'mold_change' => [
            'label' => 'Kalıp Değişimi & Ayar Süresi',
            'icon' => '🔧',
            'badge_color' => 'bg-blue-100 text-blue-800 border-blue-300',
        ],
        'quality_retest' => [
            'label' => 'Laboratuvar / Kalite Test Tekrarı',
            'icon' => '🔬',
            'badge_color' => 'bg-purple-100 text-purple-800 border-purple-300',
        ],
        'customer_change' => [
            'label' => 'Müşteri Talebi / Spesifikasyon Değişikliği',
            'icon' => '🤝',
            'badge_color' => 'bg-indigo-100 text-indigo-800 border-indigo-300',
        ],
        'other' => [
            'label' => 'Diğer Operasyonel Gerekçe',
            'icon' => '📝',
            'badge_color' => 'bg-gray-100 text-gray-800 border-gray-300',
        ],
    ];

    public function processInstance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->reason_category]['label'] ?? $this->reason_category;
    }

    public function getCategoryIconAttribute(): string
    {
        return self::CATEGORIES[$this->reason_category]['icon'] ?? '📝';
    }
}
