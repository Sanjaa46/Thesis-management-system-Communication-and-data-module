<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class SendScheduledNotificationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send scheduled notifications that are due';

    /**
     * The notification service instance.
     *
     * @var \App\Services\NotificationService
     */
    protected $notificationService;

    /**
     * Create a new command instance.
     *
     * @param \App\Services\NotificationService $notificationService
     * @return void
     */
    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Sending scheduled notifications...');

        // Get notifications that are scheduled for now or earlier and not sent yet
        $notifications = Notification::where('scheduled_at', '<=', now())
            ->where('sent', false)
            ->get();

        $this->info(sprintf('Found %d notifications to send', $notifications->count()));

        $successCount = 0;
        $failCount = 0;

        foreach ($notifications as $notification) {
            $this->info(sprintf('Sending notification #%d to user %s', $notification->id, $notification->user_id));

            try {
                $result = $this->notificationService->sendPushNotification($notification->id);

                if ($result) {
                    $successCount++;
                    $this->info(sprintf('Successfully sent notification #%d', $notification->id));
                } else {
                    $failCount++;
                    $this->error(sprintf('Failed to send notification #%d', $notification->id));
                }
            } catch (\Exception $e) {
                $failCount++;
                Log::error('Error sending scheduled notification', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage()
                ]);
                $this->error(sprintf('Error sending notification #%d: %s', $notification->id, $e->getMessage()));
            }
        }

        $this->info(sprintf('Completed sending notifications: %d success, %d failures', $successCount, $failCount));

        return 0;
    }
}