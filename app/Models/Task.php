<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;


    protected $fillable = [
        'process_instance_id',
        'node_id',
        'assigned_to',
        'assigned_role',
        'assigned_role_id',
        'completed_by',
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
        'is_revision',
        'latest_revision_note',
        'latest_revision_user',
    ];

    /**
     * Bu görevin bir revizyon/düzeltme görevi olup olmadığını tespit eder.
     */
    public function getIsRevisionAttribute(): bool
    {
        if ($this->status === 'revised') {
            return false;
        }

        $isStartNode = in_array((string) $this->node_id, ['1', 'node_start'], true);
        if (! $isStartNode) {
            $workflow = $this->processInstance?->workflow;
            if ($workflow && is_array($workflow->nodes)) {
                $node = collect($workflow->nodes)->firstWhere('id', (string) $this->node_id);
                $isStartNode = in_array($node['type'] ?? '', ['start', 'input'], true);
            }
        }

        if ($isStartNode) {
            $pi = $this->processInstance;
            if (! $pi) {
                return false;
            }

            return ($pi->data['isletme_karari'] ?? '') === 'Revizyon İstendi'
                || Task::where('process_instance_id', $pi->id)->where('status', 'revised')->exists();
        }

        return false;
    }

    /**
     * En son talep edilen revizyon notunu döndürür.
     */
    public function getLatestRevisionNoteAttribute(): ?string
    {
        if (! $this->is_revision) {
            return null;
        }

        $pi = $this->processInstance;
        if (! $pi) {
            return null;
        }

        $revisedTask = Task::where('process_instance_id', $pi->id)
            ->where('status', 'revised')
            ->whereNotNull('comment')
            ->latest('id')
            ->first();

        return $revisedTask?->comment
            ?? ($pi->data['isletme_notu'] ?? null);
    }

    /**
     * Revizyon isteyen amir/kullanıcı adını döndürür.
     */
    public function getLatestRevisionUserAttribute(): ?string
    {
        if (! $this->is_revision) {
            return null;
        }

        $pi = $this->processInstance;
        if (! $pi) {
            return null;
        }

        $revisedTask = Task::with('completedUser')
            ->where('process_instance_id', $pi->id)
            ->where('status', 'revised')
            ->latest('id')
            ->first();

        return $revisedTask?->completedUser?->name ?? null;
    }

    /**
     * Düğüm ID'sini (örn: node_1788788473573) akış şemasındaki gerçek etiket veya anlamlı Türkçe başlığa çevirir.
     */
    public function getTitleAttribute(): string
    {
        if ($this->is_revision) {
            return 'Talebi Revize Et ve Yeniden Gönder';
        }

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

    public function completedUser()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
