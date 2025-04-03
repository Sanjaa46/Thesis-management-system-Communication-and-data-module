<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TopicRequest;
use App\Models\RequestsWithTopicAndStudent;

class TopicRequestController extends Controller
{
    /**
     * Store a new topic request.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'topic_id' => 'required|integer|exists:topics,id',
            'student_id' => 'required|string|exists:students,sisi_id',
            'note' => 'nullable|string',
            'selection_date' => 'required|date_format:Y-m-d H:i:s',
        ]);

        try {
            $topicRequest = TopicRequest::create([
                'topic_id' => $validated['topic_id'],
                'requested_by_id' => 1,
                'requested_by_type' => "student",
                'req_note' => $validated['note'],
                'is_selected' => false,
                'selected_at' => $validated['selection_date'],
            ]);

            return response()->json([
                'message' => 'Topic request saved successfully!',
                'data' => $topicRequest,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to save topic request.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

        /**
     * Store a new topic request.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storebyteacher(Request $request)
    {
        $validated = $request->validate([
            'topic_id' => 'required|integer|exists:topics,id',
            'teacher_id' => 'required|string|exists:teachers,id',
            'note' => 'nullable|string',
            'selection_date' => 'required|date_format:Y-m-d H:i:s',
        ]);

        try {
            $topicRequest = TopicRequest::create([
                'topic_id' => $validated['topic_id'],
                'requested_by_id' => "1",
                'requested_by_type' => "teacher",
                'req_note' => $validated['note'],
                'is_selected' => false,
                'selected_at' => $validated['selection_date'],
            ]);

            return response()->json([
                'message' => 'Topic request saved successfully!',
                'data' => $topicRequest,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to save topic request.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Display a list of topic requests with topics and students.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $data = RequestsWithTopicAndStudent::where('created_by_type', 'teacher')
                                                ->where('status', 'approved')
                                                ->get();

            return response()->json([
                'message' => 'Requests retrieved successfully!',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve requests.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


        /**
     * Display a list of topic requests with topics and students.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRequestedTopicByTeacher()
    {
        try {
            $data = RequestsWithTopicAndStudent::where('created_by_type', 'student')
                                                ->where('status', 'approved')
                                                ->get();

            return response()->json([
                'message' => 'Requests retrieved successfully!',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve requests.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Display a list of topic requests with topics and students.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConfirmedTopics()
    {
        try {
            $data = RequestsWithTopicAndStudent::where('created_by_type', 'teacher')
                                                ->where('status', 'confirmed')
                                                ->get();

            return response()->json([
                'message' => 'Requests retrieved successfully!',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve requests.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a list of topic requests with topics and students.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConfirmedTopicOnStudent()
    {
        try {
            $data = RequestsWithTopicAndStudent::where('created_by_type', 'teacher')
                                                ->where('status', 'confirmed')
                                                ->where('sisi_id', '20B1NUM0250')
                                                ->get();

            return response()->json([
                'message' => 'Requests retrieved successfully!',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve requests.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
