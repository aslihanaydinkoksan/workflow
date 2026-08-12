<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['id', 'central_id', 'name', 'parent_id', 'directorate_id', 'is_active', 'manager_info', 'director_info', 'is_synced', 'manager_id', 'assistant_manager_id'];

    public function directorate()
    {
        return $this->belongsTo(Directorate::class)->withTrashed();
    }

    public function parent()
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function allManagers()
    {
        return $this->belongsToMany(User::class, 'department_managers')->withPivot('type');
    }

    public function managers()
    {
        return $this->belongsToMany(User::class, 'department_managers')->wherePivot('type', 'manager');
    }

    public function assistantManagers()
    {
        return $this->belongsToMany(User::class, 'department_managers')->wherePivot('type', 'assistant_manager');
    }
}
