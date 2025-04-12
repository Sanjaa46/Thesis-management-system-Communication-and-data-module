<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class StudentDataService
{
    protected $graphqlClient;
    
    public function __construct(GraphQLClientService $graphqlClient)
    {
        $this->graphqlClient = $graphqlClient;
    }
    
    /**
     * Fetch students enrolled in thesis course from HUB API
     *
     * @param string $departmentId Department ID to filter by
     * @param string|null $semester Semester code (e.g., '2025-1')
     * @return array|null List of students or null on failure
     */
    public function fetchStudentsFromHubApi($departmentId = null, $semester = null)
    {
        // Use configured values if not provided
        $departmentId = $departmentId ?? config('hubapi.departments.default');
        $semester = $semester ?? config('hubapi.semester.current');
        $thesisCourse = config('hubapi.courses.thesis_code');
        
        // Cache key for this request
        $cacheKey = "hubapi_students_{$departmentId}_{$semester}_{$thesisCourse}";
        
        // Check cache if enabled
        if (config('hubapi.cache.enabled') && Cache::has($cacheKey)) {
            Log::info('Returning cached student data', ['cache_key' => $cacheKey]);
            return Cache::get($cacheKey);
        }
        
        // GraphQL query to fetch students
        $query = <<<'GRAPHQL'
        query sisi_GetStudentsInfo($clientId: String!, $departmentId: String!, $semesterId: String!, $courseCode: String!) {
          sisi_GetStudentsInfo(
            clientId: $clientId
            departmentId: $departmentId
            semesterId: $semesterId
            courseCode: $courseCode
          ) {
            sisi_id
            firstname
            lastname
            student_email
            personal_email
            program_name
            program_id
            phone_number
            department_id
          }
        }
        GRAPHQL;
        
        $variables = [
            'clientId' => config('oauth.client_id'),
            'departmentId' => $departmentId,
            'semesterId' => $semester,
            'courseCode' => $thesisCourse
        ];
        
        try {
            // Execute the GraphQL query
            $result = $this->graphqlClient->executeQuery($query, $variables);
            
            if (!$result || !isset($result['sisi_GetStudentsInfo'])) {
                Log::error('Failed to fetch students from HUB API', [
                    'department_id' => $departmentId,
                    'semester' => $semester,
                ]);
                return null;
            }
            
            $students = $result['sisi_GetStudentsInfo'];
            
            // Cache the results if enabled
            if (config('hubapi.cache.enabled')) {
                Cache::put($cacheKey, $students, config('hubapi.cache.ttl'));
            }
            
            Log::info('Successfully fetched students from HUB API', [
                'count' => count($students),
                'department_id' => $departmentId,
            ]);
            
            return $students;
        } catch (\Exception $e) {
            Log::error('Exception while fetching students: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return null;
        }
    }
    
    /**
     * Sync students from HUB API to the database
     *
     * @param string $departmentId Department ID to filter by
     * @param string|null $semester Semester code (e.g., '2025-1')
     * @return array Results of the sync operation
     */
    public function syncStudentsFromHubApi($departmentId = null, $semester = null)
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'total' => 0,
        ];
        
        // Fetch students from API
        $students = $this->fetchStudentsFromHubApi($departmentId, $semester);
        
        if (!$students) {
            return $stats;
        }
        
        $stats['total'] = count($students);
        
        foreach ($students as $studentData) {
            try {
                // Try to find existing student
                $student = Student::where('sisi_id', $studentData['sisi_id'])->first();
                
                if ($student) {
                    // Update existing student
                    $student->update([
                        'firstname' => $studentData['firstname'],
                        'lastname' => $studentData['lastname'],
                        'mail' => $studentData['student_email'] ?? $studentData['personal_email'],
                        'program' => $studentData['program_name'],
                        'phone' => $studentData['phone_number'],
                        'dep_id' => $studentData['department_id'],
                        // Add any other fields that need updating
                    ]);
                    
                    $stats['updated']++;
                } else {
                    // Create new student
                    Student::create([
                        'id' => $studentData['sisi_id'], // Note: 'id' is used since your model has it as fillable, not incrementing
                        'sisi_id' => $studentData['sisi_id'],
                        'firstname' => $studentData['firstname'],
                        'lastname' => $studentData['lastname'],
                        'mail' => $studentData['student_email'] ?? $studentData['personal_email'],
                        'program' => $studentData['program_name'],
                        'phone' => $studentData['phone_number'],
                        'dep_id' => $studentData['department_id'],
                        'is_choosed' => false,
                        'proposed_number' => 0,
                        // Add any other required fields
                    ]);
                    
                    $stats['created']++;
                }
            } catch (\Exception $e) {
                Log::error('Failed to sync student: ' . $e->getMessage(), [
                    'sisi_id' => $studentData['sisi_id'] ?? 'unknown',
                    'exception' => get_class($e),
                ]);
                
                $stats['failed']++;
            }
        }
        
        Log::info('Student sync completed', $stats);
        
        return $stats;
    }
}