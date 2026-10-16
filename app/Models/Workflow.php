<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Workflow extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'category',
        'allowed_departments',
        'allowed_roles',
        'allowed_users',
        'valid_from',
        'valid_until',
        'form_template_id',
        'nodes',
        'edges',
        'status',
        'version',
        'parent_workflow_id',
        'is_latest',
        'version_notes',
        'created_by',
        'follow_up_enabled',
        'is_sample_workflow',
    ];

    protected $casts = [
        'nodes' => 'array',
        'edges' => 'array',
        'allowed_departments' => 'array',
        'allowed_roles' => 'array',
        'allowed_users' => 'array',
        'category' => 'array',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'follow_up_enabled' => 'boolean',
        'is_sample_workflow' => 'boolean',
        'is_latest' => 'boolean',
        'version' => 'integer',
    ];

    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class);
    }

    public function processInstances(): HasMany
    {
        return $this->hasMany(ProcessInstance::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function latestProcessInstance(): HasOne
    {
        return $this->hasOne(ProcessInstance::class)->latestOfMany();
    }

    public function parentWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'parent_workflow_id');
    }

    public function childVersions(): HasMany
    {
        return $this->hasMany(Workflow::class, 'parent_workflow_id')->orderBy('version', 'desc');
    }

    /**
     * Bu akışın en üst kök (v1) kaydını döner.
     */
    public function rootWorkflow(): Workflow
    {
        return $this->parent_workflow_id ? ($this->parentWorkflow ?? $this) : $this;
    }

    /**
     * Bu süreç ailesine ait tüm versiyonları döner (v1, v2, v3...).
     */
    public function allVersions()
    {
        $rootId = $this->parent_workflow_id ?: $this->id;

        return Workflow::where(function ($q) use ($rootId) {
            $q->where('id', $rootId)
              ->orWhere('parent_workflow_id', $rootId);
        })->orderBy('version', 'desc')->get();
    }

    /**
     * Mevcut akış üzerinden bir sonraki versiyonu (v+1) oluşturur ve eskisini arşivler.
     */
    public function createNextVersion(array $data, ?string $versionNotes = null, ?int $userId = null): Workflow
    {
        $rootId = $this->parent_workflow_id ?: $this->id;
        $maxVersion = (int) Workflow::where('id', $rootId)
            ->orWhere('parent_workflow_id', $rootId)
            ->max('version');

        $nextVersion = max(1, $maxVersion) + 1;

        // Eski versiyonları is_latest = false yap
        Workflow::where('id', $rootId)
            ->orWhere('parent_workflow_id', $rootId)
            ->update(['is_latest' => false]);

        $attributes = array_merge([
            'name'                => $this->name,
            'description'         => $this->description,
            'category'            => $this->category,
            'allowed_departments' => $this->allowed_departments,
            'allowed_roles'       => $this->allowed_roles,
            'allowed_users'       => $this->allowed_users,
            'form_template_id'    => $this->form_template_id,
            'valid_from'          => $this->valid_from,
            'valid_until'         => $this->valid_until,
            'follow_up_enabled'   => (bool) ($this->follow_up_enabled ?? false),
            'is_sample_workflow'  => (bool) ($this->is_sample_workflow ?? false),
            'status'              => 'active',
        ], $data, [
            'parent_workflow_id'  => $rootId,
            'version'             => $nextVersion,
            'is_latest'           => true,
            'version_notes'       => $versionNotes,
            'created_by'          => $userId ?: auth()->id() ?: $this->created_by,
        ]);

        return Workflow::create($attributes);
    }
}
