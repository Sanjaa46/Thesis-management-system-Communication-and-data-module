<?php

namespace App\Http\Controllers;

use App\Services\HubApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DataSyncController extends Controller
{
    protected $hubApiService;
    
    public function __construct(HubApiService $hubApiService)
    {
        $this->hubApiService = $hubApiService;
    }
    
    /**
     * Sync departments data from HUB API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncDepartments()
    {
        try {
            // Get departments from HUB API
            $departments = $this->hubApiService->getDepartments();
            
            if (!$departments) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch departments from HUB API'
                ], 500);
            }
            
            // Process each department
            $stats = [
                'total' => count($departments),
                'created' => 0,
                'updated' => 0,
                'failed' => 0
            ];
            
            foreach ($departments as $deptData) {
                try {
                    // Find or create the department - note that your model uses string IDs
                    $department = \App\Models\Department::where('id', $deptData['id'])->first();
                    
                    if (!$department) {
                        // When creating a new department, use DB directly to avoid timestamp issues
                        $isNew = true;
                        
                        // Format programs data
                        $programsData = [];
                        if (isset($deptData['programs']) && is_array($deptData['programs'])) {
                            foreach ($deptData['programs'] as $program) {
                                $programsData[] = [
                                    'id' => $program['id'],
                                    'name' => $program['name'],
                                    'index' => $program['index']
                                ];
                            }
                        }
                        
                        // Insert using query builder to avoid timestamps
                        \Illuminate\Support\Facades\DB::table('departments')->insert([
                            'id' => $deptData['id'],
                            'name' => $deptData['name'],
                            'programs' => json_encode($programsData)
                        ]);
                        
                        $stats['created']++;
                    } else {
                        $isNew = false;
                        
                        // Format programs data
                        $programsData = [];
                        if (isset($deptData['programs']) && is_array($deptData['programs'])) {
                            foreach ($deptData['programs'] as $program) {
                                $programsData[] = [
                                    'id' => $program['id'],
                                    'name' => $program['name'],
                                    'index' => $program['index']
                                ];
                            }
                        }
                        
                        // Update using query builder to avoid timestamps
                        \Illuminate\Support\Facades\DB::table('departments')
                            ->where('id', $deptData['id'])
                            ->update([
                                'name' => $deptData['name'],
                                'programs' => json_encode($programsData)
                            ]);
                        
                        $stats['updated']++;
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to process department: ' . $e->getMessage(), [
                        'department_id' => $deptData['id'] ?? 'unknown',
                        'department_name' => $deptData['name'] ?? 'unknown',
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    $stats['failed']++;
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Departments synchronized successfully',
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Department sync error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to synchronize departments',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Sync teachers data from HUB API
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncTeachers(Request $request)
    {
        try {
            $departmentId = $request->input('department_id');
            
            Log::info('Starting teacher sync', [
                'department_id' => $departmentId
            ]);
            
            // First, let's log the actual columns in the teachers table
            $columns = \Illuminate\Support\Facades\Schema::getColumnListing('teachers');
            Log::info('Teachers table columns', ['columns' => $columns]);
            
            // Get teachers from HUB API
            $teachers = $this->hubApiService->getTeachers($departmentId);
            
            if (!$teachers) {
                Log::error('No teachers returned from HUB API');
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch teachers from HUB API'
                ], 500);
            }
            
            Log::info('Retrieved teachers from API', [
                'count' => count($teachers),
                'first_teacher' => $teachers[0] ?? 'no teachers'
            ]);
            
            // Process each teacher
            $stats = [
                'total' => count($teachers),
                'created' => 0,
                'updated' => 0,
                'failed' => 0
            ];
            
            foreach ($teachers as $teacherData) {
                try {
                    // Verify we have required fields
                    if (empty($teacherData['id'])) {
                        Log::warning('Teacher missing ID', ['data' => $teacherData]);
                        $stats['failed']++;
                        continue;
                    }
                    
                    // Log the current teacher we're processing
                    Log::info('Processing teacher', [
                        'id' => $teacherData['id'],
                        'name' => ($teacherData['first_name'] ?? '') . ' ' . ($teacherData['last_name'] ?? '')
                    ]);
                    
                    // Use the original ID from the API for the teacher
                    // This is important since the ID column is required and non-null
                    $teacherId = $teacherData['id'];
                    
                    // Check if a teacher with this ID already exists
                    $existingTeacher = \Illuminate\Support\Facades\DB::table('teachers')
                        ->where('id', $teacherId)
                        ->first();
                    
                    if (!$existingTeacher) {
                        // Create a record with only the fields that exist in the table
                        // INCLUDING the ID field which is required
                        $teacherRecord = [
                            'id' => $teacherId,
                            'dep_id' => $teacherData['department_id'],
                            'firstname' => $teacherData['first_name'],
                            'lastname' => $teacherData['last_name'],
                            'mail' => $teacherData['email'],
                            'numof_choosed_stud' => 0
                        ];
                        
                        // Conditionally add the phone field ONLY if it exists in the table
                        if (in_array('phone', $columns)) {
                            $teacherRecord['phone'] = $teacherData['phone'] ?? '';
                        }
                        
                        Log::info('Inserting new teacher', $teacherRecord);
                        
                        // Insert using query builder
                        $inserted = \Illuminate\Support\Facades\DB::table('teachers')->insert($teacherRecord);
                        
                        Log::info('Teacher insert result', ['success' => $inserted]);
                        
                        if ($inserted) {
                            $stats['created']++;
                        } else {
                            $stats['failed']++;
                        }
                    } else {
                        // Update record with only the fields that exist in the table
                        // Do NOT update the ID field
                        $teacherRecord = [
                            'dep_id' => $teacherData['department_id'],
                            'firstname' => $teacherData['first_name'],
                            'lastname' => $teacherData['last_name'],
                            'mail' => $teacherData['email']
                        ];
                        
                        // Conditionally add the phone field ONLY if it exists in the table
                        if (in_array('phone', $columns)) {
                            $teacherRecord['phone'] = $teacherData['phone'] ?? '';
                        }
                        
                        Log::info('Updating existing teacher', array_merge(['id' => $teacherId], $teacherRecord));
                        
                        // Update using query builder - find by ID
                        $updated = \Illuminate\Support\Facades\DB::table('teachers')
                            ->where('id', $teacherId)
                            ->update($teacherRecord);
                        
                        Log::info('Teacher update result', ['rows_affected' => $updated]);
                        
                        if ($updated) {
                            $stats['updated']++;
                        } else {
                            // Not counting as failure if no rows were affected (data unchanged)
                            $stats['updated']++;
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to process teacher: ' . $e->getMessage(), [
                        'teacher_id' => $teacherData['id'] ?? 'unknown',
                        'teacher_name' => ($teacherData['first_name'] ?? '') . ' ' . ($teacherData['last_name'] ?? ''),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    $stats['failed']++;
                }
            }
            
            Log::info('Teachers sync completed', $stats);
            
            return response()->json([
                'success' => true,
                'message' => 'Teachers synchronized successfully',
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Teacher sync error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to synchronize teachers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync students data from HUB API
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncStudents(Request $request)
    {
        try {
            $departmentId = $request->input('department_id', '1');
            $semesterId = $request->input('semester', 'S2025SPRING');
            
            Log::info('Starting student sync', [
                'department_id' => $departmentId,
                'semester' => $semesterId
            ]);
            
            // Get students from HUB API
            $students = $this->hubApiService->getStudentsInfo();
            
            if (!$students) {
                Log::error('No students returned from HUB API');
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch students from HUB API'
                ], 500);
            }
            
            Log::info('Retrieved students from API', [
                'count' => count($students),
                'first_student' => $students[0] ?? 'no students'
            ]);
            
            // Filter by department if needed
            if ($departmentId) {
                $filtered = array_filter($students, function ($student) use ($departmentId) {
                    return $student['department_id'] == $departmentId;
                });
                $students = array_values($filtered);
                
                Log::info('Filtered students by department', [
                    'original_count' => count($students),
                    'filtered_count' => count($filtered)
                ]);
            }
            
            // Process each student
            $stats = [
                'total' => count($students),
                'created' => 0,
                'updated' => 0,
                'failed' => 0
            ];
            
            foreach ($students as $studentData) {
                try {
                    // Verify we have required fields
                    if (empty($studentData['sisi_id'])) {
                        Log::warning('Student missing sisi_id', ['data' => $studentData]);
                        $stats['failed']++;
                        continue;
                    }
                    
                    // Log the current student we're processing
                    Log::info('Processing student', [
                        'sisi_id' => $studentData['sisi_id'],
                        'name' => ($studentData['first_name'] ?? '') . ' ' . ($studentData['last_name'] ?? '')
                    ]);
                    
                    // Instead of using string IDs, we'll auto-generate numeric IDs
                    // First check if this sisi_id already exists in the database
                    $existingStudent = \Illuminate\Support\Facades\DB::table('students')
                        ->where('sisi_id', $studentData['sisi_id'])
                        ->first();
                    
                    if (!$existingStudent) {
                        // Prepare data for insert - let the database auto-generate the ID
                        $studentRecord = [
                            // Do NOT set 'id' here - let the database auto-generate it
                            'sisi_id' => $studentData['sisi_id'],
                            'firstname' => $studentData['first_name'],
                            'lastname' => $studentData['last_name'],
                            'mail' => $studentData['student_email'],
                            'phone' => $studentData['phone'] ?? '',
                            'dep_id' => $studentData['department_id'],
                            'program' => $studentData['program_name'],
                            'is_choosed' => $studentData['has_selected_research'] ?? false,
                            'proposed_number' => 0 // Default value
                        ];
                        
                        Log::info('Inserting new student', $studentRecord);
                        
                        // Insert using query builder
                        $inserted = \Illuminate\Support\Facades\DB::table('students')->insert($studentRecord);
                        
                        Log::info('Student insert result', ['success' => $inserted]);
                        
                        if ($inserted) {
                            $stats['created']++;
                        } else {
                            $stats['failed']++;
                        }
                    } else {
                        // Prepare data for update
                        $studentRecord = [
                            'firstname' => $studentData['first_name'],
                            'lastname' => $studentData['last_name'],
                            'mail' => $studentData['student_email'],
                            'phone' => $studentData['phone'] ?? '',
                            'dep_id' => $studentData['department_id'],
                            'program' => $studentData['program_name'],
                            'is_choosed' => $studentData['has_selected_research'] ?? false
                        ];
                        
                        Log::info('Updating existing student', array_merge(['sisi_id' => $studentData['sisi_id']], $studentRecord));
                        
                        // Update using query builder - update by sisi_id, not id
                        $updated = \Illuminate\Support\Facades\DB::table('students')
                            ->where('sisi_id', $studentData['sisi_id'])
                            ->update($studentRecord);
                        
                        Log::info('Student update result', ['rows_affected' => $updated]);
                        
                        if ($updated) {
                            $stats['updated']++;
                        } else {
                            // Not counting as failure if no rows were affected (data unchanged)
                            $stats['updated']++;
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to process student: ' . $e->getMessage(), [
                        'student_id' => $studentData['sisi_id'] ?? 'unknown',
                        'student_name' => ($studentData['first_name'] ?? '') . ' ' . ($studentData['last_name'] ?? ''),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    $stats['failed']++;
                }
            }
            
            Log::info('Students sync completed', $stats);
            
            return response()->json([
                'success' => true,
                'message' => 'Students synchronized successfully',
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Student sync error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to synchronize students',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Sync all entities from HUB API
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncAll(Request $request)
    {
        $results = [];
        $success = true;
        
        try {
            // Sync departments
            $departmentsResponse = $this->syncDepartments();
            $departmentsData = json_decode($departmentsResponse->getContent(), true);
            $results['departments'] = $departmentsData;
            
            if (!$departmentsData['success']) {
                $success = false;
            }
            
            // Sync teachers
            $teachersResponse = $this->syncTeachers($request);
            $teachersData = json_decode($teachersResponse->getContent(), true);
            $results['teachers'] = $teachersData;
            
            if (!$teachersData['success']) {
                $success = false;
            }
            
            // Sync students
            $studentsResponse = $this->syncStudents($request);
            $studentsData = json_decode($studentsResponse->getContent(), true);
            $results['students'] = $studentsData;
            
            if (!$studentsData['success']) {
                $success = false;
            }
            
            return response()->json([
                'success' => $success,
                'message' => $success ? 'All entities synchronized successfully' : 'Some synchronizations failed',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Full sync error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
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