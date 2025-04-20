<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['id', 'name', 'programs'];
    public $incrementing = false;
    protected $keyType = 'string';
    
    // Cast programs to array
    protected $casts = [
        'programs' => 'array',
    ];

    public function teachers()
    {
        return $this->hasMany(Teacher::class, 'dep_id');
    }

    public function supervisors()
    {
        return $this->hasMany(Supervisor::class, 'dep_id');
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'dep_id');
    }

    public function proposalForms()
    {
        return $this->hasMany(ProposalForm::class, 'dep_id');
    }
}