<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MailEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * All templates associated with this event.
     */
    public function templates(): HasMany
    {
        return $this->hasMany(MailTemplate::class, 'mail_event_id');
    }

    /**
     * Active template for this event.
     */
    public function activeTemplate(): HasOne
    {
        return $this->hasOne(MailTemplate::class, 'mail_event_id')->latestOfMany();
    }

    /**
     * All rules associated with this event.
     */
    public function rules(): HasMany
    {
        return $this->hasMany(MailRule::class, 'mail_event_id');
    }

    /**
     * Active rule for this event.
     */
    public function activeRule(): HasOne
    {
        return $this->hasOne(MailRule::class, 'mail_event_id')->latestOfMany();
    }
}
