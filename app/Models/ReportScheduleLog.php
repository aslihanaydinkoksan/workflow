<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportScheduleLog extends Model
{
    protected $fillable = [
        'schedule_id',
        'sent_at',
        'status',
        'recipients_count',
        'error_message',
        'execution_time_ms',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ReportSchedule::class, 'schedule_id');
    }
}
