<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NotificationTemplate;
use App\Models\Supervisor;

class NotificationTemplateSeeder extends Seeder
{
    public function run()
    {
        // Find a supervisor to set as creator
        $supervisor = Supervisor::first();
        if (!$supervisor) {
            $this->command->error('No supervisor found. Please create a supervisor first.');
            return;
        }

        // Topic Approval Template
        NotificationTemplate::create([
            'name' => 'Topic Approval',
            'subject' => 'Your Thesis Topic Has Been Approved',
            'body' => "Dear {{student_name}},\n\nWe're pleased to inform you that your thesis topic has been approved.\n\nTopic (Mongolian): {{topic_name_mon}}\nTopic (English): {{topic_name_eng}}\nApproval Date: {{approval_date}}\nSupervisor: {{supervisor_name}}\n\nNext steps:\n1. Schedule an initial meeting with your supervisor\n2. Prepare your research plan\n3. Begin your literature review\n\nRemember that the final thesis submission deadline is {{submission_deadline}}.\n\nBest regards,\nThesis Management Team",
            'event_type' => 'topic_approval',
            'created_by_id' => $supervisor->id,
            'is_active' => true
        ]);

        // Deadline Reminder Template
        NotificationTemplate::create([
            'name' => 'Deadline Reminder',
            'subject' => 'Upcoming Deadline: {{deadline_type}}',
            'body' => "Dear {{student_name}},\n\nThis is a reminder that an important thesis deadline is approaching:\n\nDeadline: {{deadline_type}}\nDue Date: {{due_date}}\nRemaining Time: {{days_remaining}} days\n\nRequirements:\n{{requirements}}\n\nPlease ensure you complete all necessary work before the deadline. \nLate submissions may affect your final grade.\n\nIf you have any questions, please contact your supervisor.\n\nBest regards,\nThesis Management Team",
            'event_type' => 'deadline_reminder',
            'created_by_id' => $supervisor->id,
            'is_active' => true
        ]);

        // Evaluation Notification Template
        NotificationTemplate::create([
            'name' => 'Thesis Evaluation',
            'subject' => 'Thesis Evaluation: {{evaluation_type}}',
            'body' => "Dear {{student_name}},\n\nYour supervisor has provided the following evaluation of your thesis work:\n\nEvaluation Type: {{evaluation_type}}\nScore/Grade: {{score}}\n\nFeedback:\n{{feedback}}\n\nAreas for improvement:\n{{improvement_areas}}\n\nNext steps:\n{{next_steps}}\n\nPlease review this feedback carefully and incorporate it into your ongoing work.\n\nBest regards,\nThesis Management Team",
            'event_type' => 'evaluation_notification',
            'created_by_id' => $supervisor->id,
            'is_active' => true
        ]);
    }
}