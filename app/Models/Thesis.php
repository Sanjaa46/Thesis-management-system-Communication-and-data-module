<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Thesis extends Model
{
    use HasFactory;

    protected $table = 'thesis';
    protected $fillable = ['supervisor_id', 'student_id', 'status', 'submitted_to_teacher_at','submitted_to_teacher_at', 'name_mongolian',
'name_english', 'description'];
    protected $casts = [
        'topic' => 'array', // Automatically converts JSON to an array
    ];
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(Supervisor::class, 'supervisor_id');
    }
    public function projects()
    {
        return $this->hxasMany(Project::class);
    }

}