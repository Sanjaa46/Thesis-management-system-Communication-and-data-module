<?php

return [
    'settings' => [
        'auto_notifications_enabled' => env('AUTO_NOTIFICATIONS_ENABLED', true),
        'topic_approval_enabled' => env('TOPIC_APPROVAL_NOTIFICATIONS_ENABLED', true),
        'deadline_reminders_enabled' => env('DEADLINE_REMINDERS_ENABLED', true),
        'deadline_reminder_days' => [14, 7, 3, 1], // 2 weeks, 1 week, 3 days, 1 day before
        'evaluation_notifications_enabled' => env('EVALUATION_NOTIFICATIONS_ENABLED', true),
    ],
];