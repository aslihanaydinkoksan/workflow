<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Directorate extends Model
{
    use SoftDeletes;

    protected $fillable = ['id', 'name', 'director_id', 'is_active'];

    public function director()
    {
        return $this->belongsTo(User::class, 'director_id');
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }
}
