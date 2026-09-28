<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'mail_event_id',
        'subject',
        'body_content',
        'is_active',
    ];

    protected $casts = [
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
