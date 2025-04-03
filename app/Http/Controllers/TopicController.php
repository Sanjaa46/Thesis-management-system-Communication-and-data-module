<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use Illuminate\Http\Request;
use App\Models\ProposalForm;
use App\Models\Student;
use App\Models\TopicDetail;
use App\Models\TopicRequest;
use App\Models\TopicResponse;
use Auth;
use Log;

class TopicController extends Controller
{
    // Display a listing of topics
    public function index()
    {
        return Topic::with(['proposalForm', 'topicDetails', 'topicRequests', 'topicResponses'])->get();
    }

    //Хянагч багш нь оюутан болон багшийн дэвшүүлсэн сэдвийг авах функц
    public function getSubmittedTopicsByType($type)
    {
        if (!in_array($type, ['student', 'teacher'])) {
            return response()->json(['error' => 'Invalid type'], 400);
        }

        $topics = Topic::where('status', 'submitted')
            ->where('created_by_type', $type)
            ->get();

        return response()->json($topics);
    }

    //Багш дэвшүүлж батлагдсан сэдвийн жагсаалт авах функц
    public function getCheckedTopics()
    {
        $topics = Topic::where('status', 'approved')
            ->where('created_by_type', 'teacher')
            ->get();

        return response()->json($topics);
    }

    //Оюутан дэвшүүлж батлагдсан сэдвийн жагсаалт авах функц
    public function getCheckedTopicsByStud()
    {
        $topics = Topic::where('status', 'approved')
            ->where('created_by_type', 'student')
            ->get();

        return response()->json($topics);
    }

    //Багш болон Оюутан өөрийн дэвшүүлсэн сэдэв авах функц
    public function getTopicListProposedByUser(Request $request)
    {
        // Log::debug($request);
        $userType = $request->query('user_type'); // Get user type from query parameter

        $topics = Topic::whereIn('status', ['submitted', 'approved', 'refused'])
            ->where('created_by_type', $userType) // Filter by user type
            ->get();

        return response()->json($topics);
    }


    //Оюутан өөрийн ноорогт хадгалсан болон түтгэлзүүлсэн сэдвийн жагсаалт авах функц
    public function getDraftTopicsByStudent()
    {
        $topics = Topic::where('status', ['draft', 'refused'])
            ->where('created_by_type', 'student')
            ->get();

        return response()->json($topics);
    }

    //Багш өөрийн ноорогт хадгалсан болон түтгэлзүүлсэн сэдвийн жагсаалт авах функц
    public function getDraftTopicsByTeacher()
    {
        $topics = Topic::where('status', ['draft', 'refused'])
            ->where('created_by_type', 'teacher')
            ->get();

        return response()->json($topics);
    }


    //Оюутны дэвшүүлсэн сэдэв хадгалах функц
    public function storestudent(Request $request)
    {
        $validatedData = $request->validate([
            'form_id' => 'required|string|max:10',
            'fields' => 'array|required',
            'fields.*.field' => 'required',
            'fields.*.field2' => 'required',
            'fields.*.value' => 'required',
            'status' => 'required|string',
        ]);

        $topic = Topic::create([
            'form_id' => $validatedData['form_id'],
            'fields' => json_encode($validatedData['fields']),
            // 'program' => json_encode($validatedData['combinedFields']),
            'status' => $validatedData['status'],
            'created_at' => now(),
            'created_by_id' => '1',
            'created_by_type' => 'student',
        ]);

        return response()->json(['message' => 'Topic and TopicDetail saved successfully']);
    }

    //Багшийн дэвшүүлсэн сэдэв хадгалах функц
    public function storeteacher(Request $request)
    {
        $validatedData = $request->validate([
            'form_id' => 'required|string|max:10',
            'fields' => 'array|required',
            'fields.*.field' => 'required',
            'fields.*.field2' => 'required',
            'fields.*.value' => 'required',
            'program' => 'nullable',
            'status' => 'required|string',
        ]);

        $topic = Topic::create([
            'form_id' => $validatedData['form_id'],
            'fields' => json_encode($validatedData['fields']),
            'program' => json_encode($validatedData['program']),
            'status' => $validatedData['status'],
            'created_at' => now(),
            'created_by_id' => '1',
            'created_by_type' => 'teacher',
        ]);

        return response()->json(['message' => 'Topic and TopicDetail saved successfully']);
    }


    //save confirmed topic
    public function confirmTopic(Request $request)
    {
        $validatedData = $request->validate([
            'topic_id' => 'required|integer|exists:topics,id',
            'req_id' => 'required|integer|exists:topic_requests,id',
            'student_id' => 'required|integer|exists:students,id',
            'res_date' => 'nullable'
        ]);

        try {
            // Update Topic status
            $topic = Topic::findOrFail($validatedData['topic_id']);
            $topic->update([
                'status' => 'confirmed',
            ]);

            // Update Student is_choosed
            $student = Student::findOrFail($validatedData['student_id']);
            $student->update([
                'is_choosed' => true,
            ]);

            // Update TopicRequest is_selected
            $topicRequest = TopicRequest::findOrFail($validatedData['req_id']);
            $topicRequest->update([
                'is_selected' => true,
                'selected_date' => $validatedData['res_date'],
            ]);

            return response()->json(['message' => 'Topic confirmed successfully']);
        } catch (\Exception $e) {
            Log::error('Error confirming topic: ' . $e->getMessage());
            return response()->json(['message' => 'Error confirming topic'], 500);
        }
    }


    //Сонгогдсон сэдэв цуцлах функц
    public function declineTopic(Request $request)
    {
        $validatedData = $request->validate([
            // 'topic_id' => 'nullable',
            'topic_id' => 'required|integer|exists:topics,id',
            'req_id' => 'required|integer|exists:topic_requests,id',
            'student_id' => 'required|integer|exists:students,id',
            'res_date' => 'nullable'
        ]);

        try {
            // Update Topic status
            $topic = Topic::findOrFail($validatedData['topic_id']);
            $topic->update([
                'status' => 'approved',
            ]);

            // Update Student is_choosed
            $student = Student::findOrFail($validatedData['student_id']);
            $student->update([
                'is_choosed' => false,
            ]);

            // Update TopicRequest is_selected
            $topicRequest = TopicRequest::findOrFail($validatedData['req_id']);
            $topicRequest->update([
                'is_selected' => false,
                'selected_date' => $validatedData['res_date'],
            ]);

            return response()->json(['message' => 'Topic declined successfully']);
        } catch (\Exception $e) {
            Log::error('Error confirming topic: ' . $e->getMessage());
            return response()->json(['message' => 'Error confirming topic'], 500);
        }
    }


    // Display the specified topic
    public function show($id)
    {
        $topic = Topic::with(['proposalForm', 'topicDetails', 'topicRequests', 'topicResponses'])->findOrFail($id);

        return response()->json($topic);
    }


    /**
     * Display a listing of topics with status 'refused' or 'approved'.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRefusedOrApprovedTopics()
    {
        $topics = Topic::whereIn('status', ['refused', 'approved'])->get();

        return response()->json($topics);
    }


    // Update the specified topic in storage
    public function update(Request $request, $id)
    {
        $topic = Topic::findOrFail($id);

        $request->validate([
            'form_id' => 'nullable|string|max:10|exists:proposal_forms,id',
            'name_mongolian' => 'nullable|string|max:150',
            'name_english' => 'nullable|string|max:150',
            'description' => 'nullable|string|max:300',
            'program' => 'nullable|json',
            'status' => 'nullable|string|max:30',
            'created_at' => 'nullable|date',
            'created_by' => 'nullable|string|max:10',
        ]);

        $topic->update($request->all());

        return response()->json($topic);
    }
}
