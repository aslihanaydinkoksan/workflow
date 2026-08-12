<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormTemplateRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_template_id',
        'schema',
        'revision_no',
        'revision_date',
        'created_by'
    ];

    protected $casts = [
        'schema' => 'array',
        'revision_date' => 'date',
    ];

    public function formTemplate()
    {
        return $this->belongsTo(FormTemplate::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
