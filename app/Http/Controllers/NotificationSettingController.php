<?php

namespace App\Http\Controllers;

use App\Models\NotificationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class NotificationSettingController extends Controller
{
    public function index()
    {
        try {
            $settings = NotificationSetting::first();
            
            return response()->json([
                'success' => true,
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching notification settings', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notification settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'auto_notifications_enabled' => 'boolean',
            'topic_approval_enabled' => 'boolean',
            'deadline_reminders_enabled' => 'boolean',
            'deadline_reminder_days' => 'array',
            'evaluation_notifications_enabled' => 'boolean',
            'topic_approval_template_id' => 'nullable|exists:thesis_notification_templates,id',
            'deadline_reminder_template_id' => 'nullable|exists:thesis_notification_templates,id',
            'evaluation_template_id' => 'nullable|exists:thesis_notification_templates,id',
            'thesis_proposal_deadline' => 'nullable|date',
            'first_draft_deadline' => 'nullable|date',
            'final_submission_deadline' => 'nullable|date',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $settings = NotificationSetting::first();
            
            if (!$settings) {
                $settings = new NotificationSetting();
            }
            
            $settings->fill($request->all());
            $settings->save();
            
            // Update the config values
            config(['notification.settings.auto_notifications_enabled' => $settings->auto_notifications_enabled]);
            config(['notification.settings.topic_approval_enabled' => $settings->topic_approval_enabled]);
            config(['notification.settings.deadline_reminders_enabled' => $settings->deadline_reminders_enabled]);
            config(['notification.settings.deadline_reminder_days' => $settings->deadline_reminder_days]);
            config(['notification.settings.evaluation_notifications_enabled' => $settings->evaluation_notifications_enabled]);
            
            config(['thesis.deadlines.proposal' => $settings->thesis_proposal_deadline]);
            config(['thesis.deadlines.first_draft' => $settings->first_draft_deadline]);
            config(['thesis.deadlines.final_submission' => $settings->final_submission_deadline]);
            
            return response()->json([
                'success' => true,
                'message' => 'Notification settings updated successfully',
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating notification settings', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}