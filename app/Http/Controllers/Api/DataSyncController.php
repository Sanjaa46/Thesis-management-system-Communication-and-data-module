<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\DepartmentDataService;
use App\Services\TeacherDataService;
use App\Services\StudentDataService;
use Illuminate\Support\Facades\Log;

class DataSyncController extends Controller
{
    protected $departmentService;
    protected $teacherService;
    protected $studentService;
    
    public function __construct(
        DepartmentDataService $departmentService,
        TeacherDataService $teacherService,
        StudentDataService $studentService
    ) {
        $this->departmentService = $departmentService;
        $this->teacherService = $teacherService;
        $this->studentService = $studentService;
    }
    
    /**
     * Synchronize departments from HUB API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncDepartments()
    {
        Log::info('Starting department sync via API');
        
        try {
            $result = $this->departmentService->syncDepartmentsFromHubApi();
            
            Log::info('Department sync completed via API', $result);
            
            return response()->json([
                'success' => true,
                'message' => 'Departments synchronized successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Department sync API error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to synchronize departments',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Synchronize teachers from HUB API
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncTeachers(Request $request)
    {
        Log::info('Starting teacher sync via API');
        
        try {
            $departmentId = $request->input('department_id');
            
            $result = $this->teacherService->syncTeachersFromHubApi($departmentId);
            
            Log::info('Teacher sync completed via API', $result);
            
            return response()->json([
                'success' => true,
                'message' => 'Teachers synchronized successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Teacher sync API error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to synchronize teachers',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Synchronize students from HUB API
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncStudents(Request $request)
    {
        Log::info('Starting student sync via API');
        
        try {
            $departmentId = $request->input('department_id');
            $semester = $request->input('semester');
            
            $result = $this->studentService->syncStudentsFromHubApi($departmentId, $semester);
            
            Log::info('Student sync completed via API', $result);
            
            return response()->json([
                'success' => true,
                'message' => 'Students synchronized successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Student sync API error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to synchronize students',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Synchronize all entities from HUB API
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncAll(Request $request)
    {
        Log::info('Starting full sync via API');
        
        $results = [];
        $success = true;
        
        try {
            // Sync departments
            $departmentResult = $this->departmentService->syncDepartmentsFromHubApi();
            $results['departments'] = $departmentResult;
            
            // Sync teachers
            $departmentId = $request->input('department_id');
            $teacherResult = $this->teacherService->syncTeachersFromHubApi($departmentId);
            $results['teachers'] = $teacherResult;
            
            // Sync students
            $semester = $request->input('semester');
            $studentResult = $this->studentService->syncStudentsFromHubApi($departmentId, $semester);
            $results['students'] = $studentResult;
            
            Log::info('Full sync completed via API', $results);
            
            return response()->json([
                'success' => true,
                'message' => 'All entities synchronized successfully',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Full sync API error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'results_so_far' => $results
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to synchronize all entities',
                'error' => $e->getMessage(),
                'partial_results' => $results
            ], 500);
        }
    }
}