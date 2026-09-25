<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUp extends Model
{
    use HasFactory;

    protected $fillable = [
        'process_instance_id',
        'follow_up_type',
        'title',
        'prompt',
        'sub_form_id',
        'status',
        'scheduled_at',
        'last_reminded_at',
        'reminder_count',
        'assigned_to',
        'response_status',
        'order_number',
        'non_conversion_reason',
        'customer_feedback',
        'responded_at',
        'responded_by',
        'sap_sales_order_id',
        'sap_sync_status',
        'sap_synced_at',
        'sap_payload',
        'sap_response',
        'metadata',
    ];

    protected $casts = [
        'scheduled_at'     => 'datetime',
        'last_reminded_at' => 'datetime',
        'responded_at'     => 'datetime',
        'sap_synced_at'    => 'datetime',
        'sap_payload'      => 'array',
        'sap_response'     => 'array',
        'metadata'         => 'array',
        'reminder_count'   => 'integer',
        'sub_form_id'      => 'integer',
    ];

    public function processInstance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class);
    }

    public function subForm(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'sub_form_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function respondedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConverted($query)
    {
        return $query->where('status', 'converted');
    }

    public function scopeNotConverted($query)
    {
        return $query->where('status', 'not_converted');
    }

    public function scopeDueForReminder($query)
    {
        return $query->where('status', 'pending')
                     ->where('scheduled_at', '<=', now());
    }
}
