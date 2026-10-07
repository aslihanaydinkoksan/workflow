<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcessFieldAudit extends Model
{
    use HasFactory;

    protected $table = 'process_field_audits';

    protected $fillable = [
        'process_instance_id',
        'task_id',
        'user_id',
        'event_type',
        'field_key',
        'field_label',
        'old_value',
        'new_value',
        'comment',
    ];

    public function processInstance()
    {
        return $this->belongsTo(ProcessInstance::class, 'process_instance_id');
    }

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
