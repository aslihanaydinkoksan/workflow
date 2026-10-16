<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ReportWidget extends Model
{
    protected $fillable = [
        'title',
        'data_source',
        'chart_type',
        'dimension_field',
        'metric_field',
        'aggregation_type',
        'filters_json',
        'color_palette',
        'created_by',
        'is_active',
        'is_pinned_to_home',
    ];

    protected $casts = [
        'filters_json' => 'array',
        'is_active' => 'boolean',
        'is_pinned_to_home' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dashboards(): BelongsToMany
    {
        return $this->belongsToMany(ReportDashboard::class, 'report_dashboard_widgets', 'widget_id', 'dashboard_id')
            ->withPivot('order_index', 'width_cols')
            ->withTimestamps();
    }
}
