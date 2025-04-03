<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    public function index()
    {
        return Teacher::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'id' => 'required|string|max:10',
            'dep_id' => 'required|string|max:10',
            'firstname' => 'required|string|max:40',
            'lastname' => 'required|string|max:40',
            'mail' => 'required|email|max:100',
            'numof_choosed_stud' => 'required|integer',
        ]);

        return Teacher::create($request->all());
    }

    public function show($id)
    {
        $teacher = Teacher::find($id);
        if (!$teacher) {
            return response()->json(['message' => 'Teacher not found'], 404);
        }
        return response()->json($teacher);
    }


    public function update(Request $request, $id)
    {
        $teacher = Teacher::findOrFail($id);
        $teacher->update($request->all());

        return $teacher;
    }

    public function destroy($id)
    {
        $teacher = Teacher::findOrFail($id);
        $teacher->delete();

        return response()->json(['message' => 'Teacher deleted successfully']);
    }
}
