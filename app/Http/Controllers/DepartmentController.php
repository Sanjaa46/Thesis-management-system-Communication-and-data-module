<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    // Display a listing of departments
    public function index()
    {
        return Department::all();
    }

    // Store a newly created department in storage
    public function store(Request $request)
    {
        $request->validate([
            'id' => 'required|string|max:10',
            'name' => 'required|string|max:100',
        ]);

        return Department::create($request->all());
    }

    public function show($id)
    {
        $department = Department::find($id);
        if (!$department) {
            return response()->json(['message' => 'Department not found'], 404);
        }
        return response()->json($department);
    }


    // Update the specified department in storage
    public function update(Request $request, $id)
    {
        $department = Department::findOrFail($id);
        $department->update($request->all());

        return $department;
    }

    // Remove the specified department from storage
    public function destroy($id)
    {
        $department = Department::findOrFail($id);
        $department->delete();

        return response()->json(['message' => 'Department deleted successfully']);
    }
}
