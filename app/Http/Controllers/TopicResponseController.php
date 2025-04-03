<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TopicResponse;
use App\Models\Topic; // Import Topic model

class TopicResponseController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'topic_id' => 'required|exists:topics,id',
            'supervisor_id' => 'nullable',
            'res' => 'required',
            'note' => 'nullable',
            'res_date' => 'required|date',
        ]);
        $response = TopicResponse::create($validated);

        $topic = Topic::findOrFail($validated['topic_id']);
        $topic->status = $validated['res'] === 'approved' ? 'approved' : 'refused';
        $topic->save();

        return response()->json([
            'message' => 'Response saved and topic status updated successfully!',
            'response' => $response,
            'topic' => $topic,
        ], 201);
    }
}



