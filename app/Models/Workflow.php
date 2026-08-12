<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Workflow extends Model
{
    use HasFactory;

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
        'created_by',
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
    ];

    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class);
    }

    public function processInstances(): HasMany
    {
        return $this->hasMany(ProcessInstance::class);
    }

    public function latestProcessInstance(): HasOne
    {
        return $this->hasOne(ProcessInstance::class)->latestOfMany();
    }
}
