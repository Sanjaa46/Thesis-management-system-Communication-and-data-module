<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\NotificationEmail;
use Exception;

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
     * @return int|bool The notification ID if successful, false otherwise
     */
    public function storePushNotification($userId, $title, $content, $scheduleTime = null, $url = null)
    {
        try {
            // Convert schedule time string to proper datetime format if provided
            $schedule = $scheduleTime ? new \DateTime($scheduleTime) : null;
            
            // Create notification record
            $notification = \App\Models\Notification::create([
                'user_id' => $userId,
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
     * @return array Results of email and push notification attempts
     */
    public function sendCombinedNotification($userId, $email, $title, $content, $scheduleTime = null, $url = null)
    {
        $emailResult = $this->sendEmailNotification($email, $title, $content, ['url' => $url]);
        $pushResult = $this->storePushNotification($userId, $title, $content, $scheduleTime, $url);
        
        return [
            'email_sent' => $emailResult,
            'push_notification_id' => $pushResult,
        ];
    }
}