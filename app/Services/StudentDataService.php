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
        if (config('hubapi.cache.enabled', false) && Cache::has($cacheKey)) {
            Log::info('Returning cached student data', ['cache_key' => $cacheKey]);
            return Cache::get($cacheKey);
        }
        
        // GraphQL query to fetch students (based on the API.pdf requirements)
        $query = <<<'GRAPHQL'
        query GetStudentsEnrolledInThesis($clientId: String!, $departmentId: String!, $semesterId: String!, $courseCode: String!) {
          sisi_GetStudentsEnrolledInThesis(
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
            phone
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
            
            if (!$result || !isset($result['sisi_GetStudentsEnrolledInThesis'])) {
                // Fallback to a more generic student query if the specific one fails
                Log::warning('Specific thesis student query failed, trying generic student query');
                return $this->fetchStudentsGenericFromHubApi($departmentId);
            }
            
            $students = $result['sisi_GetStudentsEnrolledInThesis'];
            
            // Cache the results if enabled
            if (config('hubapi.cache.enabled', false)) {
                Cache::put($cacheKey, $students, config('hubapi.cache.ttl', 3600));
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
     * Fallback method to fetch students using a generic query
     * 
     * @param string $departmentId
     * @return array|null
     */
    private function fetchStudentsGenericFromHubApi($departmentId)
    {
        $query = <<<'GRAPHQL'
        query GetStudents($clientId: String!, $departmentId: String!) {
          sisi_GetStudents(
            clientId: $clientId
            departmentId: $departmentId
          ) {
            sisi_id
            firstname
            lastname
            student_email
            personal_email
            program_name
            program_id
            phone
            department_id
          }
        }
        GRAPHQL;
        
        $variables = [
            'clientId' => config('oauth.client_id'),
            'departmentId' => $departmentId
        ];
        
        try {
            $result = $this->graphqlClient->executeQuery($query, $variables);
            
            if (!$result || !isset($result['sisi_GetStudents'])) {
                Log::error('Failed to fetch students with generic query');
                return null;
            }
            
            return $result['sisi_GetStudents'];
        } catch (\Exception $e) {
            Log::error('Exception in generic student fetch: ' . $e->getMessage());
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
                        'phone' => $studentData['phone'],
                        'dep_id' => $studentData['department_id'],
                    ]);
                    
                    $stats['updated']++;
                } else {
                    // Create new student
                    Student::create([
                        'id' => $studentData['sisi_id'], // Using sisi_id as id
                        'sisi_id' => $studentData['sisi_id'],
                        'firstname' => $studentData['firstname'],
                        'lastname' => $studentData['lastname'],
                        'mail' => $studentData['student_email'] ?? $studentData['personal_email'],
                        'program' => $studentData['program_name'],
                        'phone' => $studentData['phone'],
                        'dep_id' => $studentData['department_id'],
                        'is_choosed' => false,
                        'proposed_number' => 0,
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