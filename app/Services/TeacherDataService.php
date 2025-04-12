<?php

namespace App\Services;

use App\Models\Teacher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TeacherDataService
{
    protected $graphqlClient;
    
    public function __construct(GraphQLClientService $graphqlClient)
    {
        $this->graphqlClient = $graphqlClient;
    }
    
    /**
     * Fetch teachers from HUB API
     *
     * @param string $departmentId Department ID to filter by
     * @return array|null List of teachers or null on failure
     */
    public function fetchTeachersFromHubApi($departmentId = null)
    {
        // Use configured values if not provided
        $departmentId = $departmentId ?? config('hubapi.departments.default');
        
        // Cache key for this request
        $cacheKey = "hubapi_teachers_{$departmentId}";
        
        // Check cache if enabled
        if (config('hubapi.cache.enabled') && Cache::has($cacheKey)) {
            Log::info('Returning cached teacher data', ['cache_key' => $cacheKey]);
            return Cache::get($cacheKey);
        }
        
        // GraphQL query to fetch teachers
        $query = <<<'GRAPHQL'
        query hr_GetTeachers($clientId: String!, $departmentId: String!) {
          hr_GetTeachers(
            clientId: $clientId
            departmentId: $departmentId
          ) {
            id
            dep_id
            department_name
            firstname
            lastname
            email
            phone
            position
            academic_degree
          }
        }
        GRAPHQL;
        
        $variables = [
            'clientId' => config('oauth.client_id'),
            'departmentId' => $departmentId
        ];
        
        try {
            // Execute the GraphQL query
            $result = $this->graphqlClient->executeQuery($query, $variables);
            
            if (!$result || !isset($result['hr_GetTeachers'])) {
                Log::error('Failed to fetch teachers from HUB API', [
                    'department_id' => $departmentId,
                ]);
                return null;
            }
            
            $teachers = $result['hr_GetTeachers'];
            
            // Cache the results if enabled
            if (config('hubapi.cache.enabled')) {
                Cache::put($cacheKey, $teachers, config('hubapi.cache.ttl'));
            }
            
            Log::info('Successfully fetched teachers from HUB API', [
                'count' => count($teachers),
                'department_id' => $departmentId,
            ]);
            
            return $teachers;
        } catch (\Exception $e) {
            Log::error('Exception while fetching teachers: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return null;
        }
    }
    
    /**
     * Sync teachers from HUB API to the database
     *
     * @param string $departmentId Department ID to filter by
     * @return array Results of the sync operation
     */
    public function syncTeachersFromHubApi($departmentId = null)
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'total' => 0,
        ];
        
        // Fetch teachers from API
        $teachers = $this->fetchTeachersFromHubApi($departmentId);
        
        if (!$teachers) {
            return $stats;
        }
        
        $stats['total'] = count($teachers);
        
        foreach ($teachers as $teacherData) {
            try {
                // Try to find existing teacher
                $teacher = Teacher::where('id', $teacherData['id'])->first();
                
                if ($teacher) {
                    // Update existing teacher
                    $teacher->update([
                        'dep_id' => $teacherData['dep_id'],
                        'firstname' => $teacherData['firstname'],
                        'lastname' => $teacherData['lastname'],
                        'mail' => $teacherData['email'],
                        'phone' => $teacherData['phone'],
                        'numof_choosed_stud' => $teacher->numof_choosed_stud ?? 0,
                        // Add any other fields that need updating
                    ]);
                    
                    $stats['updated']++;
                } else {
                    // Create new teacher
                    Teacher::create([
                        'id' => $teacherData['id'],
                        'dep_id' => $teacherData['dep_id'],
                        'firstname' => $teacherData['firstname'],
                        'lastname' => $teacherData['lastname'],
                        'mail' => $teacherData['email'],
                        'phone' => $teacherData['phone'],
                        'numof_choosed_stud' => 0,
                        // Add any other required fields
                    ]);
                    
                    $stats['created']++;
                }
            } catch (\Exception $e) {
                Log::error('Failed to sync teacher: ' . $e->getMessage(), [
                    'id' => $teacherData['id'] ?? 'unknown',
                    'exception' => get_class($e),
                ]);
                
                $stats['failed']++;
            }
        }
        
        Log::info('Teacher sync completed', $stats);
        
        return $stats;
    }
}