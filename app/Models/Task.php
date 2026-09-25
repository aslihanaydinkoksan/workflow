<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'process_instance_id',
        'node_id',
        'assigned_to',
        'assigned_role',
        'assigned_role_id',
        'type',
        'status',
        'comment',
        'due_date',
        'completed_at',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'title',
    ];

    /**
     * Düğüm ID'sini (örn: node_1788788473573) akış şemasındaki gerçek etiket veya anlamlı Türkçe başlığa çevirir.
     */
    public function getTitleAttribute(): string
    {
        $workflow = $this->processInstance?->workflow;
        if ($workflow && is_array($workflow->nodes)) {
            $node = collect($workflow->nodes)->firstWhere('id', $this->node_id);
            if ($node) {
                $label = $node['data']['label']
                    ?? $node['data']['customName']
                    ?? $node['label']
                    ?? null;

                if (!empty($label) && !str_starts_with($label, 'node_')) {
                    return $label;
                }
            }
        }

        if ($this->type === 'approval') {
            return 'Onay & Karar Görevi';
        }
        if ($this->type === 'form') {
            return 'Form Doldurma Görevi';
        }
        if ($this->type === 'review') {
            return 'İnceleme & Değerlendirme';
        }
        if ($this->type === 'notification') {
            return 'Bilgilendirme Bildirimi';
        }

        return 'Görev #' . $this->id;
    }

    public function processInstance()
    {
        return $this->belongsTo(ProcessInstance::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
