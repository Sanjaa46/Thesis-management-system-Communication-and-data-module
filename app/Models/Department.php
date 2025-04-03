<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['id', 'name'];
    public $incrementing = false;
    protected $keyType = 'string';

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
