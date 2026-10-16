<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcessInstance extends Model
{
    use HasFactory, SoftDeletes;


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

    public function followUps()
    {
        return $this->hasMany(FollowUp::class);
    }

    public function latestFollowUp()
    {
        return $this->hasOne(FollowUp::class)->latestOfMany();
    }

    public function terminRevisions()
    {
        return $this->hasMany(TerminRevision::class)->latest();
    }

    public function latestTerminRevision()
    {
        return $this->hasOne(TerminRevision::class)->latestOfMany();
    }

    public function fieldAudits()
    {
        return $this->hasMany(ProcessFieldAudit::class)->latest();
    }
}
