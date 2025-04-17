<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\NotificationTemplate;
use App\Models\Student;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendDeadlineReminders extends Command
{
    protected $signature = 'notifications:deadline-reminders';
    protected $description = 'Send deadline reminder notifications to students';

    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    public function handle()
    {
        $this->info('Sending deadline reminders...');
        
        // Get notification settings
        $settings = config('notification.settings', [
            'deadline_reminders_enabled' => true,
            'deadline_reminder_days' => [14, 7, 3, 1],
        ]);
        
        if (!$settings['deadline_reminders_enabled']) {
            $this->info('Deadline reminders are disabled.');
            return;
        }
        
        // Get deadlines
        $deadlines = [
            [
                'type' => 'Thesis Proposal Submission',
                'date' => config('thesis.deadlines.proposal', null),
                'requirements' => 'Submit your thesis proposal document outlining your research question, methodology, and initial literature review.'
            ],
            [
                'type' => 'First Draft Submission',
                'date' => config('thesis.deadlines.first_draft', null),
                'requirements' => 'Submit your first complete draft of the thesis including all chapters, diagrams, and references.'
            ],
            [
                'type' => 'Final Thesis Submission',
                'date' => config('thesis.deadlines.final_submission', null),
                'requirements' => 'Submit your final thesis document with all revisions complete and formatted according to the guidelines.'
            ],
        ];
        
        // Get notification template
        $template = NotificationTemplate::where('event_type', 'deadline_reminder')
            ->where('is_active', true)
            ->first();
            
        if (!$template) {
            $this->error('No active deadline reminder template found.');
            return;
        }
        
        // Get all students
        $students = Student::where('is_choosed', true)->get();
        
        if ($students->isEmpty()) {
            $this->info('No students found with confirmed topics.');
            return;
        }
        
        $today = Carbon::today();
        $remindersSent = 0;
        
        foreach ($deadlines as $deadline) {
            if (!$deadline['date']) {
                continue;
            }
            
            $deadlineDate = Carbon::parse($deadline['date']);
            
            // Skip if deadline has passed
            if ($deadlineDate->isPast()) {
                continue;
            }
            
            // Check each reminder day
            foreach ($settings['deadline_reminder_days'] as $days) {
                $reminderDate = $deadlineDate->copy()->subDays($days);
                
                // If today is the reminder date
                if ($reminderDate->isSameDay($today)) {
                    $this->info("Sending reminders for {$deadline['type']} ({$days} days before deadline)");
                    
                    // Send to each student
                    foreach ($students as $student) {
                        $data = [
                            'student_name' => $student->firstname,
                            'deadline_type' => $deadline['type'],
                            'due_date' => $deadlineDate->format('Y-m-d'),
                            'days_remaining' => $days,
                            'requirements' => $deadline['requirements'],
                        ];
                        
                        try {
                            $result = $this->notificationService->sendFromTemplate(
                                $template->id,
                                $student->id,
                                $student->mail,
                                $data
                            );
                            
                            if ($result['email_sent'] || $result['push_notification_id']) {
                                $remindersSent++;
                            }
                        } catch (\Exception $e) {
                            Log::error('Error sending deadline reminder', [
                                'student_id' => $student->id,
                                'deadline' => $deadline['type'],
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
            }
        }
        
        $this->info("Sent {$remindersSent} deadline reminders.");
    }
}