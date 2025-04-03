<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    use HasFactory;

    protected $fillable = ['id', 'dep_id', 'firstname', 'lastname', 'mail', 'numof_choosed_stud'];
    public $incrementing = false;
    protected $keyType = 'string';

    public function department()
    {
        return $this->belongsTo(Department::class, 'dep_id');
    }

    public function topics()
    {
        return $this->morphMany(Topic::class, 'created_by');
    }
}
