<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = ['id', 'dep_id', 'firstname', 'lastname', 'program', 'mail', 'phone', 'is_choosed', 'proposed_number'];
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    public function department()
    {
        return $this->belongsTo(Department::class, 'dep_id');
    }

    public function topics()
    {
        return $this->morphMany(Topic::class, 'created_by');
    }
}