<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StudentController extends Controller
{
    /**
     * Display a listing of students.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $students = Student::all();
            return response()->json($students);
        } catch (\Exception $e) {
            Log::error('Error fetching students: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch students'], 500);
        }
    }

    /**
     * Store a newly created student in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|string|max:20',
                'sisi_id' => 'required|string|max:20',
                'dep_id' => 'required|string|max:10',
                'firstname' => 'required|string|max:100',
                'lastname' => 'required|string|max:100',
                'program' => 'required|string|max:255',
                'mail' => 'required|email|max:100',
                'phone' => 'nullable|string|max:20',
                'is_choosed' => 'boolean',
                'proposed_number' => 'integer'
            ]);

            $student = Student::create($validated);
            return response()->json($student, 201);
        } catch (\Exception $e) {
            Log::error('Error creating student: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create student'], 500);
        }
    }

    /**
     * Display the specified student.
     *
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $student = Student::findOrFail($id);
            return response()->json($student);
        } catch (\Exception $e) {
            Log::error('Error fetching student: ' . $e->getMessage());
            return response()->json(['error' => 'Student not found'], 404);
        }
    }

    /**
     * Update the specified student in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            $student = Student::findOrFail($id);
            
            $validated = $request->validate([
                'dep_id' => 'string|max:10',
                'firstname' => 'string|max:100',
                'lastname' => 'string|max:100',
                'program' => 'string|max:255',
                'mail' => 'email|max:100',
                'phone' => 'nullable|string|max:20',
                'is_choosed' => 'boolean',
                'proposed_number' => 'integer'
            ]);

            $student->update($validated);
            return response()->json($student);
        } catch (\Exception $e) {
            Log::error('Error updating student: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update student'], 500);
        }
    }

    /**
     * Remove the specified student from storage.
     *
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $student = Student::findOrFail($id);
            $student->delete();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error('Error deleting student: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete student'], 500);
        }
    }

    /**
     * Get students who have chosen a thesis topic.
     *
     * @return \Illuminate\Http\Response
     */
    public function getStudentsWithTopic()
    {
        try {
            $students = Student::where('is_choosed', true)->get();
            return response()->json($students);
        } catch (\Exception $e) {
            Log::error('Error fetching students with topic: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch students'], 500);
        }
    }

    /**
     * Get students who have not chosen a thesis topic.
     *
     * @return \Illuminate\Http\Response
     */
    public function getStudentsWithoutTopic()
    {
        try {
            $students = Student::where('is_choosed', false)->get();
            return response()->json($students);
        } catch (\Exception $e) {
            Log::error('Error fetching students without topic: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch students'], 500);
        }
    }
}