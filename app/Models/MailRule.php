<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'mail_event_id',
        'target_users',
        'target_roles',
        'target_departments',
        'allowed_days',
        'allowed_time_start',
        'allowed_time_end',
        'is_active',
    ];

    protected $casts = [
        'target_users' => 'array',
        'target_roles' => 'array',
        'target_departments' => 'array',
        'allowed_days' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Parent mail event.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(MailEvent::class, 'mail_event_id');
    }
}
