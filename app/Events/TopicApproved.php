<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TopicApproved
{
    use Dispatchable, SerializesModels;
    
    public $topic;
    public $student;
    public $supervisor;
    
    public function __construct($topic, $student, $supervisor)
    {
        $this->topic = $topic;
        $this->student = $student;
        $this->supervisor = $supervisor;
    }
}

// Create the corresponding listener
// app/Listeners/SendTopicApprovalNotification.php
namespace App\Listeners;

use App\Events\TopicApproved;
use App\Models\NotificationTemplate;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class SendTopicApprovalNotification
{
    protected $notificationService;
    
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    
    public function handle(TopicApproved $event)
    {
        try {
            // Find the appropriate template
            $template = NotificationTemplate::where('event_type', 'topic_approval')
                ->where('is_active', true)
                ->first();
                
            if (!$template) {
                Log::warning('Topic approval template not found');
                return;
            }
            
            // Get topic data
            $fields = is_array($event->topic->fields) 
                ? $event->topic->fields 
                : json_decode($event->topic->fields, true);
                
            $topicNameMongolian = '';
            $topicNameEnglish = '';
            
            foreach ($fields as $field) {
                if ($field['field'] === 'name_mongolian') $topicNameMongolian = $field['value'];
                if ($field['field'] === 'name_english') $topicNameEnglish = $field['value'];
            }
            
            // Prepare notification data
            $data = [
                'student_name' => $event->student->firstname,
                'topic_name_mon' => $topicNameMongolian,
                'topic_name_eng' => $topicNameEnglish,
                'supervisor_name' => $event->supervisor->lastname . ' ' . $event->supervisor->firstname,
                'approval_date' => now()->format('Y-m-d'),
                'submission_deadline' => config('thesis.deadlines.final_submission', 'TBD'),
                'url' => url('/confirmedtopic')
            ];
            
            // Send the notification
            $result = $this->notificationService->sendFromTemplate(
                $template->id,
                $event->student->id,
                $event->student->mail,
                $data
            );
            
            Log::info('Topic approval notification sent', [
                'student_id' => $event->student->id,
                'topic_id' => $event->topic->id,
                'result' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send topic approval notification', [
                'error' => $e->getMessage(),
                'topic_id' => $event->topic->id ?? 'unknown',
                'student_id' => $event->student->id ?? 'unknown',
            ]);
        }
    }
}