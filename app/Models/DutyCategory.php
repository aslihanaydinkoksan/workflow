<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DutyCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'icon',
        'color',
        'description',
        'is_field_duty',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_field_duty' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
    }
}
