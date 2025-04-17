<?php

namespace App\Http\Controllers;

use App\Models\ThesisDeadline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Services\NotificationService;

class ThesisDeadlineController extends Controller
{
    protected $notificationService;

    /**
     * Create a new controller instance.
     *
     * @param NotificationService $notificationService
     * @return void
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of thesis deadlines.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = ThesisDeadline::query();
            
            // Filter by department_id if provided
            if ($request->has('department_id')) {
                $query->where('department_id', $request->department_id);
            }
            
            // Filter by program_id if provided
            if ($request->has('program_id')) {
                $query->where('program_id', $request->program_id);
            }
            
            // Sort by deadline date (ascending by default)
            $query->orderBy('deadline_date', $request->input('sort', 'asc'));
            
            $deadlines = $query->get();
            
            return response()->json([
                'success' => true,
                'data' => $deadlines
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching thesis deadlines', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch thesis deadlines',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created thesis deadline.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'deadline_date' => 'required|date',
            'department_id' => 'nullable|string|exists:departments,id',
            'program_id' => 'nullable|string',
            'reminder_days' => 'required|array',
            'reminder_days.*' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $deadline = ThesisDeadline::create([
                'name' => $request->name,
                'description' => $request->description,
                'deadline_date' => $request->deadline_date,
                'department_id' => $request->department_id,
                'program_id' => $request->program_id,
                'reminder_days' => $request->reminder_days,
                'created_by' => $request->user()->id ?? 'system',
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Thesis deadline created successfully',
                'data' => $deadline
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating thesis deadline', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create thesis deadline',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified thesis deadline.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $deadline = ThesisDeadline::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $deadline
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching thesis deadline', [
                'deadline_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch thesis deadline',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified thesis deadline.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'deadline_date' => 'date',
            'department_id' => 'nullable|string|exists:departments,id',
            'program_id' => 'nullable|string',
            'reminder_days' => 'array',
            'reminder_days.*' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $deadline = ThesisDeadline::findOrFail($id);
            
            $deadline->update($request->only([
                'name', 'description', 'deadline_date', 'department_id', 'program_id', 'reminder_days'
            ]));
            
            return response()->json([
                'success' => true,
                'message' => 'Thesis deadline updated successfully',
                'data' => $deadline
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating thesis deadline', [
                'deadline_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update thesis deadline',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified thesis deadline.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $deadline = ThesisDeadline::findOrFail($id);
            $deadline->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Thesis deadline deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting thesis deadline', [
                'deadline_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete thesis deadline',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send reminder notifications for a specific deadline.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendReminders($id)
    {
        try {
            $deadline = ThesisDeadline::findOrFail($id);
            
            // Send notifications
            $results = $this->notificationService->sendDeadlineReminders($id);
            
            return response()->json([
                'success' => true,
                'message' => 'Deadline reminders sent successfully',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending deadline reminders', [
                'deadline_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send deadline reminders',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}