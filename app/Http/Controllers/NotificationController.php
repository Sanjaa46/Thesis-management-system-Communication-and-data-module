<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\Notification;

class NotificationController extends Controller
{
    protected $notificationService;

    /**
     * Create a new controller instance.
     *
     * @param \App\Services\NotificationService $notificationService
     * @return void
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Store a new notification to be sent.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'recipients' => 'required|array',
            'recipients.*.id' => 'required|string',
            'recipients.*.email' => 'required|email',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'schedule' => 'nullable|date',
            'url' => 'nullable|string|url',
            'send_email' => 'boolean',
            'send_push' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $title = $request->input('title');
        $content = $request->input('content');
        $schedule = $request->input('schedule');
        $url = $request->input('url');
        $sendEmail = $request->input('send_email', true);
        $sendPush = $request->input('send_push', true);

        $results = [];

        foreach ($request->input('recipients') as $recipient) {
            $userId = $recipient['id'];
            $email = $recipient['email'];
            
            try {
                if ($sendEmail && $sendPush) {
                    // Send both email and push notification
                    $result = $this->notificationService->sendCombinedNotification(
                        $userId,
                        $email,
                        $title,
                        $content,
                        $schedule,
                        $url
                    );

                    $results[$userId] = $result;
                } elseif ($sendEmail) {
                    // Send only email notification
                    $emailSent = $this->notificationService->sendEmailNotification(
                        $email,
                        $title,
                        $content,
                        ['url' => $url]
                    );

                    $results[$userId] = ['email_sent' => $emailSent];
                } elseif ($sendPush) {
                    // Send only push notification
                    $notificationId = $this->notificationService->storePushNotification(
                        $userId,
                        $title,
                        $content,
                        $schedule,
                        $url
                    );

                    $results[$userId] = ['push_notification_id' => $notificationId];
                }
            } catch (\Exception $e) {
                Log::error('Error sending notification', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                
                $results[$userId] = [
                    'error' => 'Failed to send notification',
                    'message' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Notifications processed',
            'results' => $results
        ]);
    }

    /**
     * Subscribe a user to push notifications.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function subscribe(Request $request)
    {
        // Log the entire request for debugging
        Log::info('Push subscription request received', [
            'request_data' => $request->all(),
            'auth_user' => $request->user()
        ]);

        $validator = Validator::make($request->all(), [
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get user ID from request or fallback to authenticated user
            $userId = $request->input('user_id');
            
            if (!$userId) {
                // Get the authenticated user
                $user = $request->user();
                
                if (!$user) {
                    return response()->json([
                        'success' => false,
                        'message' => 'User not authenticated and no user_id provided'
                    ], 401);
                }
                
                $userId = $user->sisi_id ?? $user->id;
            }

            // Log which user ID we're using
            Log::info('Saving push subscription for user', [
                'user_id' => $userId
            ]);

            // Store subscription information
            $subscription = \App\Models\PushSubscription::create([
                'user_id' => $userId,
                'endpoint' => $request->input('endpoint'),
                'p256dh' => $request->input('keys.p256dh'),
                'auth' => $request->input('keys.auth'),
                'expires_at' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Successfully subscribed to push notifications',
                'subscription_id' => $subscription->id
            ]);
        } catch (\Exception $e) {
            Log::error('Error subscribing to push notifications', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to subscribe to push notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unsubscribe a user from push notifications.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unsubscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'endpoint' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get the authenticated user
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Find and delete the subscription
            $deleted = \App\Models\PushSubscription::where('user_id', $user->id)
                ->where('endpoint', $request->input('endpoint'))
                ->delete();

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Successfully unsubscribed from push notifications'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription not found'
                ], 404);
            }
        } catch (\Exception $e) {
            Log::error('Error unsubscribing from push notifications', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to unsubscribe from push notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unread notifications for the authenticated user.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUnread(Request $request)
    {
        try {
            // Check for token in request attributes (set by middleware)
            $authUser = $request->attributes->get('auth_user');
            
            if (!$authUser) {
                Log::warning('getUnread called without auth user', [
                    'has_token' => (bool)$request->attributes->get('access_token'),
                    'headers' => $request->headers->all()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            // Get the user ID - different formats possible from OAuth
            $userId = $authUser['uid'] ?? $authUser['username'] ?? null;
            
            if (!$userId) {
                Log::error('Unable to determine user ID from auth data', [
                    'auth_data_keys' => array_keys($authUser),
                    'has_uid' => isset($authUser['uid']),
                    'has_username' => isset($authUser['username'])
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to determine user ID'
                ], 400);
            }
            
            // Log what user ID we're using
            Log::info('Getting unread notifications for user', ['user_id' => $userId]);

            // Get unread notifications - use the fully qualified namespace here
            $notifications = \App\Models\Notification::where('user_id', $userId)
                ->where('is_read', false)
                ->where('sent', true)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'count' => $notifications->count(),
                'notifications' => $notifications
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting unread notifications', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get unread notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark a notification as read.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead(Request $request, $id)
    {
        try {
            // Get the authenticated user
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Find the notification
            $notification = \App\Models\Notification::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            // Mark as read
            $notification->update([
                'is_read' => true,
                'read_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking notification as read', [
                'notification_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send a notification using a template to multiple recipients
     */
    public function sendTemplateNotification(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'template_id' => 'required|exists:thesis_notification_templates,id',
            'recipients' => 'required|array',
            'recipients.*.id' => 'required|string',
            'recipients.*.email' => 'required|email',
            'data' => 'nullable|array',
            'schedule' => 'nullable|date',
            'url' => 'nullable|string|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $templateId = $request->input('template_id');
            $recipients = $request->input('recipients');
            $data = $request->input('data', []);
            $schedule = $request->input('schedule');
            $url = $request->input('url');
            
            $results = $this->notificationService->sendBatchFromTemplate(
                $templateId,
                $recipients,
                $data,
                $schedule,
                $url
            );

            return response()->json([
                'success' => true,
                'message' => 'Notifications processed',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending template notification', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}