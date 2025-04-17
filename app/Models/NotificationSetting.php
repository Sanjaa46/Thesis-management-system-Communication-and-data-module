<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'auto_notifications_enabled',
        'topic_approval_enabled',
        'deadline_reminders_enabled',
        'deadline_reminder_days',
        'evaluation_notifications_enabled',
        'topic_approval_template_id',
        'deadline_reminder_template_id',
        'evaluation_template_id',
        'thesis_proposal_deadline',
        'first_draft_deadline',
        'final_submission_deadline',
    ];
    
    protected $casts = [
        'auto_notifications_enabled' => 'boolean',
        'topic_approval_enabled' => 'boolean',
        'deadline_reminders_enabled' => 'boolean',
        'deadline_reminder_days' => 'array',
        'evaluation_notifications_enabled' => 'boolean',
        'thesis_proposal_deadline' => 'date',
        'first_draft_deadline' => 'date',
        'final_submission_deadline' => 'date',
    ];
    
    public function topicApprovalTemplate()
    {
        return $this->belongsTo(NotificationTemplate::class, 'topic_approval_template_id');
    }
    
    public function deadlineReminderTemplate()
    {
        return $this->belongsTo(NotificationTemplate::class, 'deadline_reminder_template_id');
    }
    
    public function evaluationTemplate()
    {
        return $this->belongsTo(NotificationTemplate::class, 'evaluation_template_id');
    }
}