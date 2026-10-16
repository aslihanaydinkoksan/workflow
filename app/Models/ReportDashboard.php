<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportDashboard extends Model
{
    protected $fillable = [
        'title',
        'description',
        'is_default',
        'is_public',
        'layout_grid_json',
        'created_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_public' => 'boolean',
        'layout_grid_json' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function widgets(): BelongsToMany
    {
        return $this->belongsToMany(ReportWidget::class, 'report_dashboard_widgets', 'dashboard_id', 'widget_id')
            ->withPivot('order_index', 'width_cols')
            ->withTimestamps()
            ->orderBy('report_dashboard_widgets.order_index', 'asc');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ReportSchedule::class, 'dashboard_id');
    }
}
