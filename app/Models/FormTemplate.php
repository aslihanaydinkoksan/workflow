<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'schema',
        'is_active',
        'created_by',
        'category_id',
        'document_no',
        'publish_date',
        'revision_no',
        'revision_date',
        'page_no',
        'logo_width',
        'logo_height'
    ];

    protected $casts = [
        'schema' => 'array',
        'is_active' => 'boolean',
        'publish_date' => 'date',
        'revision_date' => 'date',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function category()
    {
        return $this->belongsTo(FormCategory::class);
    }

    public function revisions()
    {
        return $this->hasMany(FormTemplateRevision::class)->orderBy('revision_no', 'desc');
    }

    public function primaryWorkflows()
    {
        return $this->hasMany(Workflow::class, 'form_template_id');
    }
}
