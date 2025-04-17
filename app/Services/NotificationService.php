<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\NotificationEmail;
use App\Models\NotificationTemplate;
use App\Models\ThesisDeadline;
use App\Models\Student;
use App\Models\Topic;
use Exception;
use Carbon\Carbon;

class NotificationService
{
    /**
     * Send an email notification.
     *
     * @param string $recipient Recipient email address
     * @param string $title Notification title
     * @param string $content Notification content
     * @param array $additionalData Additional data to include
     * @return bool Whether the email was sent successfully
     */
    public function sendEmailNotification($recipient, $title, $content, $additionalData = [])
    {
        try {
            Mail::to($recipient)->send(new NotificationEmail($title, $content, $additionalData));
            
            Log::info('Email notification sent', [
                'recipient' => $recipient,
                'title' => $title
            ]);
            
            return true;
        } catch (Exception $e) {
            Log::error('Failed to send email notification', [
                'recipient' => $recipient,
                'title' => $title,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Store a push notification for later delivery.
     *
     * @param string $userId User ID to receive the notification
     * @param string $title Notification title
     * @param string $content Notification content
     * @param string|null $scheduleTime When to send the notification (null for immediate)
     * @param string|null $url URL to redirect to when clicked
     * @param string $notificationType Type of notification
     * @param string|null $relatedId ID of the related entity
     * @return int|bool The notification ID if successful, false otherwise
     */
    public function storePushNotification($userId, $title, $content, $scheduleTime = null, $url = null, $notificationType = 'topic_approval', $relatedId = null)
    {
        try {
            // Convert schedule time string to proper datetime format if provided
            $schedule = $scheduleTime ? new Carbon($scheduleTime) : null;
            
            // Create notification record
            $notification = \App\Models\Notification::create([
                'user_id' => $userId,
                'notification_type' => $notificationType,
                'related_id' => $relatedId,
                'title' => $title,
                'content' => $content,
                'scheduled_at' => $schedule ? $schedule->format('Y-m-d H:i:s') : null,
                'url' => $url,
                'is_read' => false,
                'sent' => false
            ]);
            
            Log::info('Push notification stored', [
                'notification_id' => $notification->id,
                'user_id' => $userId,
                'scheduled_at' => $schedule ? $schedule->format('Y-m-d H:i:s') : 'immediate'
            ]);
            
            // If no schedule time, mark for immediate sending
            if (!$scheduleTime) {
                // Call the push notification method directly or queue it
                $this->sendPushNotification($notification->id);
            }
            
            return $notification->id;
        } catch (Exception $e) {
            Log::error('Failed to store push notification', [
                'user_id' => $userId,
                'title' => $title,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Send a push notification.
     *
     * @param int $notificationId The ID of the notification to send
     * @return bool Whether the notification was sent successfully
     */
    public function sendPushNotification($notificationId)
    {
        try {
            $notification = \App\Models\Notification::findOrFail($notificationId);
            
            // If already sent, don't send again
            if ($notification->sent) {
                return true;
            }
            
            // Get the subscriber's push subscription
            $subscription = \App\Models\PushSubscription::where('user_id', $notification->user_id)
                ->latest()
                ->first();
            
            if (!$subscription) {
                Log::warning('No push subscription found for user', [
                    'user_id' => $notification->user_id,
                    'notification_id' => $notificationId
                ]);
                
                return false;
            }
            
            // Prepare notification payload
            $payload = [
                'notification' => [
                    'title' => $notification->title,
                    'body' => $notification->content,
                    'icon' => '/icons/logo.png', // Path to your notification icon
                    'badge' => '/icons/badge.png', // Path to your badge icon
                    'data' => [
                        'url' => $notification->url ?? url('/')
                    ],
                    'actions' => [
                        [
                            'action' => 'view',
                            'title' => 'View'
                        ]
                    ]
                ]
            ];
            
            // Send notification via Web Push
            $webPush = new \Minishlink\WebPush\WebPush([
                'VAPID' => [
                    'subject' => config('app.url'),
                    'publicKey' => config('services.webpush.public_key'),
                    'privateKey' => config('services.webpush.private_key')
                ]
            ]);
            
            $webPush->queueNotification(
                \Minishlink\WebPush\Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'keys' => [
                        'p256dh' => $subscription->p256dh,
                        'auth' => $subscription->auth
                    ]
                ]),
                json_encode($payload)
            );
            
            $reports = $webPush->flush();
            
            // Check if the notification was sent successfully
            $success = false;
            foreach ($reports as $report) {
                if ($report->isSuccess()) {
                    $success = true;
                    break;
                }
            }
            
            if ($success) {
                // Update notification as sent
                $notification->update([
                    'sent' => true,
                    'sent_at' => now()
                ]);
                
                Log::info('Push notification sent', [
                    'notification_id' => $notificationId,
                    'user_id' => $notification->user_id
                ]);
                
                return true;
            } else {
                Log::warning('Push notification could not be delivered', [
                    'notification_id' => $notificationId,
                    'user_id' => $notification->user_id
                ]);
                
                return false;
            }
        } catch (Exception $e) {
            Log::error('Failed to send push notification', [
                'notification_id' => $notificationId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Send both email and push notifications.
     * 
     * @param string $userId User ID
     * @param string $email User email
     * @param string $title Notification title
     * @param string $content Notification content
     * @param string|null $scheduleTime When to send the notification
     * @param string|null $url URL to redirect to when clicked
     * @param string $notificationType Type of notification
     * @param string|null $relatedId ID of the related entity
     * @return array Results of email and push notification attempts
     */
    public function sendCombinedNotification($userId, $email, $title, $content, $scheduleTime = null, $url = null, $notificationType = 'topic_approval', $relatedId = null)
    {
        $emailResult = $this->sendEmailNotification($email, $title, $content, ['url' => $url]);
        $pushResult = $this->storePushNotification($userId, $title, $content, $scheduleTime, $url, $notificationType, $relatedId);
        
        return [
            'email_sent' => $emailResult,
            'push_notification_id' => $pushResult,
        ];
    }

    /**
     * Send notification using a template.
     *
     * @param string $userId User ID
     * @param string $email User email
     * @param int $templateId Template ID
     * @param array $variables Template variables
     * @param string|null $scheduleTime Schedule time
     * @param string|null $url URL to include
     * @param string|null $relatedId Related entity ID
     * @return array Results of notification sending
     */
    public function sendTemplatedNotification($userId, $email, $templateId, $variables = [], $scheduleTime = null, $url = null, $relatedId = null)
    {
        try {
            $template = NotificationTemplate::findOrFail($templateId);
            
            // Process the template with variables
            $processed = $template->processTemplate($variables);
            
            return $this->sendCombinedNotification(
                $userId,
                $email,
                $processed['subject'],
                $processed['content'],
                $scheduleTime,
                $url,
                $template->notification_type,
                $relatedId
            );
        } catch (Exception $e) {
            Log::error('Failed to send templated notification', [
                'template_id' => $templateId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'email_sent' => false,
                'push_notification_id' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send a topic approval notification.
     *
     * @param int $topicId The topic ID
     * @param int $studentId The student ID
     * @param string|null $customMessage Custom message from supervisor
     * @return array Notification results
     */
    public function sendTopicApprovalNotification($topicId, $studentId, $customMessage = null)
    {
        try {
            // Get the topic
            $topic = Topic::findOrFail($topicId);
            
            // Get the student
            $student = Student::findOrFail($studentId);
            
            // Get topic name from fields
            $topicName = '';
            $fields = is_string($topic->fields) ? json_decode($topic->fields, true) : $topic->fields;
            
            foreach ($fields as $field) {
                if (isset($field['field']) && $field['field'] === 'name_mongolian') {
                    $topicName = $field['value'] ?? '';
                    break;
                }
            }
            
            // Get the approval template
            $template = NotificationTemplate::where('notification_type', 'topic_approval')
                ->where('is_default', true)
                ->first();
            
            if (!$template) {
                // Create a default template if none exists
                $template = NotificationTemplate::create([
                    'name' => 'Default Topic Approval',
                    'subject' => 'Дипломын ажлын сэдэв батлагдсан тухай',
                    'content' => "Сайн байна уу, {student_name},\n\nТаны \"{topic_name}\" сэдэв амжилттай батлагдлаа.\n\n{custom_message}\n\nАнхааралтай байна уу.\nДипломын ажлын удирдлагын систем",
                    'notification_type' => 'topic_approval',
                    'is_default' => true,
                    'created_by' => 'system',
                ]);
            }
            
            // Prepare variables
            $variables = [
                'student_name' => $student->firstname,
                'topic_name' => $topicName,
                'custom_message' => $customMessage ?? '',
            ];
            
            // Send the notification
            return $this->sendTemplatedNotification(
                $studentId,
                $student->mail,
                $template->id,
                $variables,
                null, // Send immediately
                url('/confirmedtopic'), // URL to confirmed topic page
                $topicId // Related entity ID
            );
        } catch (Exception $e) {
            Log::error('Failed to send topic approval notification', [
                'topic_id' => $topicId,
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'email_sent' => false,
                'push_notification_id' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send deadline reminder notifications.
     *
     * @param int $deadlineId The deadline ID
     * @return array Array of notification results
     */
    public function sendDeadlineReminders($deadlineId)
    {
        try {
            // Get the deadline
            $deadline = ThesisDeadline::findOrFail($deadlineId);
            
            // Get the deadline template
            $template = NotificationTemplate::where('notification_type', 'deadline_reminder')
                ->where('is_default', true)
                ->first();
            
            if (!$template) {
                // Create a default template if none exists
                $template = NotificationTemplate::create([
                    'name' => 'Default Deadline Reminder',
                    'subject' => 'Хугацааны сануулга: {deadline_name}',
                    'content' => "Сайн байна уу, {student_name},\n\n{deadline_name} хугацаа дуусахад {days_remaining} өдөр үлдлээ. Хугацаа: {deadline_date}.\n\n{deadline_description}\n\nАнхааралтай байна уу.\nДипломын ажлын удирдлагын систем",
                    'notification_type' => 'deadline_reminder',
                    'is_default' => true,
                    'created_by' => 'system',
                ]);
            }
            
            // Get target students
            $students = $deadline->getTargetStudents();
            
            $results = [];
            
            // Calculate days remaining
            $now = Carbon::now();
            $deadlineDate = Carbon::parse($deadline->deadline_date);
            $daysRemaining = $now->diffInDays($deadlineDate);
            
            foreach ($students as $student) {
                // Prepare variables
                $variables = [
                    'student_name' => $student->firstname,
                    'deadline_name' => $deadline->name,
                    'deadline_date' => $deadlineDate->format('Y-m-d H:i'),
                    'days_remaining' => $daysRemaining,
                    'deadline_description' => $deadline->description ?? '',
                ];
                
                // Send the notification
                $result = $this->sendTemplatedNotification(
                    $student->id,
                    $student->mail,
                    $template->id,
                    $variables,
                    null, // Send immediately
                    url('/'), // URL to home page
                    $deadlineId // Related entity ID
                );
                
                $results[$student->id] = $result;
            }
            
            return $results;
        } catch (Exception $e) {
            Log::error('Failed to send deadline reminders', [
                'deadline_id' => $deadlineId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send evaluation notification.
     *
     * @param int $evaluationId The evaluation ID
     * @param int $studentId The student ID
     * @param array $evaluationData Evaluation data
     * @return array Notification results
     */
    public function sendEvaluationNotification($evaluationId, $studentId, $evaluationData)
    {
        try {
            // Get the student
            $student = Student::findOrFail($studentId);
            
            // Get the evaluation template
            $template = NotificationTemplate::where('notification_type', 'evaluation')
                ->where('is_default', true)
                ->first();
            
            if (!$template) {
                // Create a default template if none exists
                $template = NotificationTemplate::create([
                    'name' => 'Default Evaluation Notification',
                    'subject' => 'Үнэлгээний мэдэгдэл: {evaluation_type}',
                    'content' => "Сайн байна уу, {student_name},\n\nТаны дипломын ажил {evaluation_type} үнэлгээ хийгдлээ.\n\nҮнэлгээ: {score}\n\nСайжруулах зүйлс: {improvements}\n\n{feedback}\n\nАнхааралтай байна уу.\nДипломын ажлын удирдлагын систем",
                    'notification_type' => 'evaluation',
                    'is_default' => true,
                    'created_by' => 'system',
                ]);
            }
            
            // Prepare variables
            $variables = [
                'student_name' => $student->firstname,
                'evaluation_type' => $evaluationData['type'] ?? 'Урьдчилсан',
                'score' => $evaluationData['score'] ?? 'N/A',
                'improvements' => $evaluationData['improvements'] ?? 'N/A',
                'feedback' => $evaluationData['feedback'] ?? '',
            ];
            
            // Send the notification
            return $this->sendTemplatedNotification(
                $studentId,
                $student->mail,
                $template->id,
                $variables,
                null, // Send immediately
                url('/'), // URL to home page
                $evaluationId // Related entity ID
            );
        } catch (Exception $e) {
            Log::error('Failed to send evaluation notification', [
                'evaluation_id' => $evaluationId,
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'email_sent' => false,
                'push_notification_id' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send a notification using a template
     *
     * @param int $templateId Template ID
     * @param string $userId User ID
     * @param string $email User email
     * @param array $data Template variable data
     * @param string|null $scheduleTime When to send the notification
     * @param string|null $url URL to redirect to when clicked
     * @return array Results of the notification sending
     */
    public function sendFromTemplate($templateId, $userId, $email, $data = [], $scheduleTime = null, $url = null)
    {
        try {
            $template = \App\Models\NotificationTemplate::findOrFail($templateId);
            
            // Replace placeholders with actual data
            $subject = $this->replacePlaceholders($template->subject, $data);
            $content = $this->replacePlaceholders($template->body, $data);
            
            // Use the URL from data if provided, otherwise use the passed URL
            $finalUrl = $data['url'] ?? $url;
            
            // Send the notification using existing methods
            return $this->sendCombinedNotification(
                $userId,
                $email,
                $subject,
                $content,
                $scheduleTime,
                $finalUrl
            );
        } catch (\Exception $e) {
            Log::error('Failed to send notification from template', [
                'template_id' => $templateId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'email_sent' => false,
                'push_notification_id' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send a batch of notifications to multiple recipients
     *
     * @param int $templateId Template ID
     * @param array $recipients Array of user IDs and emails
     * @param array $data Template variable data
     * @param string|null $scheduleTime When to send the notification
     * @param string|null $url URL to redirect to when clicked
     * @return array Results of notification sending
     */
    public function sendBatchFromTemplate($templateId, $recipients, $data = [], $scheduleTime = null, $url = null)
    {
        $results = [];
        
        foreach ($recipients as $recipient) {
            // Prepare recipient-specific data
            $recipientData = $data;
            
            // Add recipient-specific data if available
            if (isset($recipient['data']) && is_array($recipient['data'])) {
                $recipientData = array_merge($recipientData, $recipient['data']);
            }
            
            // Send notification to this recipient
            $results[$recipient['id']] = $this->sendFromTemplate(
                $templateId,
                $recipient['id'],
                $recipient['email'],
                $recipientData,
                $scheduleTime,
                $url
            );
        }
        
        return $results;
    }

    /**
     * Replace placeholders in a text with actual values
     *
     * @param string $text Text with placeholders
     * @param array $data Data for replacements
     * @return string Text with replaced placeholders
     */
    private function replacePlaceholders($text, $data)
    {
        foreach ($data as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }
        return $text;
    }
}