<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportSchedule extends Model
{
    protected $fillable = [
        'title',
        'dashboard_id',
        'widget_id',
        'frequency_type',
        'cron_expression',
        'dispatch_time',
        'day_of_week',
        'day_of_month',
        'recipients_json',
        'include_pdf',
        'include_excel',
        'include_html_body',
        'filter_override_json',
        'is_active',
        'last_sent_at',
        'created_by',
    ];

    protected $casts = [
        'recipients_json' => 'array',
        'filter_override_json' => 'array',
        'include_pdf' => 'boolean',
        'include_excel' => 'boolean',
        'include_html_body' => 'boolean',
        'is_active' => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(ReportDashboard::class, 'dashboard_id');
    }

    public function widget(): BelongsTo
    {
        return $this->belongsTo(ReportWidget::class, 'widget_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ReportScheduleLog::class, 'schedule_id')->orderBy('sent_at', 'desc');
    }
}
