<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ThesisDeadline extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'deadline_date',
        'department_id',
        'program_id',
        'reminder_days',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'deadline_date' => 'datetime',
        'reminder_days' => 'array',
    ];

    /**
     * Get the department that this deadline belongs to.
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Calculate the next reminder date for this deadline.
     *
     * @return Carbon|null
     */
    public function getNextReminderDate()
    {
        $now = Carbon::now();
        $deadlineDate = Carbon::parse($this->deadline_date);
        
        if ($deadlineDate->isPast()) {
            return null;
        }

        $reminderDays = collect($this->reminder_days)->sort()->values();
        
        foreach ($reminderDays as $days) {
            $reminderDate = $deadlineDate->copy()->subDays($days);
            if ($reminderDate->isFuture() || $reminderDate->isToday()) {
                return $reminderDate;
            }
        }

        return null;
    }

    /**
     * Get the students who should receive this deadline notification.
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTargetStudents()
    {
        $query = Student::query();
        
        if ($this->department_id) {
            $query->where('dep_id', $this->department_id);
        }
        
        if ($this->program_id) {
            $query->where('program', $this->program_id);
        }

        return $query->get();
    }
}