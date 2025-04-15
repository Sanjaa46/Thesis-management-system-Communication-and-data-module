<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GraphQLClientService;
use App\Services\DepartmentDataService;
use App\Services\TeacherDataService;
use App\Services\StudentDataService;
use Illuminate\Support\Facades\Log;

class GraphQLTestController extends Controller
{
    protected $graphqlClient;
    protected $departmentService;
    protected $teacherService;
    protected $studentService;
    
    public function __construct(
        GraphQLClientService $graphqlClient,
        DepartmentDataService $departmentService,
        TeacherDataService $teacherService,
        StudentDataService $studentService
    ) {
        $this->graphqlClient = $graphqlClient;
        $this->departmentService = $departmentService;
        $this->teacherService = $teacherService;
        $this->studentService = $studentService;
    }
    
    /**
     * Test basic GraphQL connection
     */
    public function testConnection()
    {
        try {
            // Simple query to test if we can connect to the GraphQL API
            $query = "{ __schema { queryType { name } } }";
            
            // Let the service get the token from session or client credentials
            $result = $this->graphqlClient->executeQuery($query);
            
            return response()->json([
                'success' => $result !== null,
                'message' => $result !== null ? 'Successfully connected to GraphQL API' : 'Connection failed',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('GraphQL connection test failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to connect to GraphQL API',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Test fetching departments
     */
    public function testDepartments()
    {
        try {
            $departments = $this->departmentService->fetchDepartmentsFromHubApi();
            
            return response()->json([
                'success' => true,
                'message' => 'Successfully fetched departments',
                'count' => $departments ? count($departments) : 0,
                'data' => $departments
            ]);
        } catch (\Exception $e) {
            Log::error('Department test failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch departments',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Test fetching teachers
     */
    public function testTeachers(Request $request)
    {
        try {
            $departmentId = $request->input('department_id');
            $teachers = $this->teacherService->fetchTeachersFromHubApi($departmentId);
            
            return response()->json([
                'success' => true,
                'message' => 'Successfully fetched teachers',
                'department_id' => $departmentId,
                'count' => $teachers ? count($teachers) : 0,
                'data' => $teachers
            ]);
        } catch (\Exception $e) {
            Log::error('Teacher test failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch teachers',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Test fetching students
     */
    public function testStudents(Request $request)
    {
        try {
            $departmentId = $request->input('department_id');
            $semester = $request->input('semester');
            
            $students = $this->studentService->fetchStudentsFromHubApi($departmentId, $semester);
            
            return response()->json([
                'success' => true,
                'message' => 'Successfully fetched students',
                'department_id' => $departmentId,
                'semester' => $semester,
                'count' => $students ? count($students) : 0,
                'data' => $students
            ]);
        } catch (\Exception $e) {
            Log::error('Student test failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch students',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}