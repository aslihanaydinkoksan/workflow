<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcessInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'current_node_id',
        'status',
        'started_by',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function starter()
    {
        return $this->belongsTo(User::class, 'started_by');
    }
}
