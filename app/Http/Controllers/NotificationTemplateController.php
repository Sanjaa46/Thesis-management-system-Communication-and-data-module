<?php

namespace App\Http\Controllers;

use App\Models\NotificationTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class NotificationTemplateController extends Controller
{
    public function index()
    {
        try {
            $templates = NotificationTemplate::where('is_active', true)
                ->orderBy('name')
                ->get();
                
            return response()->json([
                'success' => true,
                'data' => $templates
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching notification templates', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notification templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'event_type' => 'nullable|string',
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
            
            $template = NotificationTemplate::create([
                'name' => $request->input('name'),
                'subject' => $request->input('subject'),
                'body' => $request->input('body'),
                'event_type' => $request->input('event_type'),
                'created_by_id' => $user->id,
                'is_active' => true
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Template created successfully',
                'data' => $template
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating notification template', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create notification template',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function show($id)
    {
        try {
            $template = NotificationTemplate::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $template
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }
    
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'event_type' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $template = NotificationTemplate::findOrFail($id);
            
            $template->update([
                'name' => $request->input('name'),
                'subject' => $request->input('subject'),
                'body' => $request->input('body'),
                'event_type' => $request->input('event_type'),
                'is_active' => $request->input('is_active', $template->is_active)
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Template updated successfully',
                'data' => $template
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating notification template', [
                'template_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification template',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function destroy($id)
    {
        try {
            $template = NotificationTemplate::findOrFail($id);
            
            // Soft delete by marking as inactive
            $template->update(['is_active' => false]);
            
            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting notification template', [
                'template_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification template',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}