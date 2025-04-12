<?php

namespace App\Services;

use App\Models\Department;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DepartmentDataService
{
    protected $graphqlClient;
    
    public function __construct(GraphQLClientService $graphqlClient)
    {
        $this->graphqlClient = $graphqlClient;
    }
    
    /**
     * Fetch departments from HUB API
     *
     * @return array|null List of departments or null on failure
     */
    public function fetchDepartmentsFromHubApi()
    {
        // Cache key for this request
        $cacheKey = "hubapi_departments";
        
        // Check cache if enabled
        if (config('hubapi.cache.enabled') && Cache::has($cacheKey)) {
            Log::info('Returning cached department data', ['cache_key' => $cacheKey]);
            return Cache::get($cacheKey);
        }
        
        // GraphQL query to fetch departments
        $query = <<<'GRAPHQL'
        query hr_GetDepartments($clientId: String!) {
          hr_GetDepartments(
            clientId: $clientId
          ) {
            id
            name
            programs {
              program_id
              program_index
              program_name
            }
          }
        }
        GRAPHQL;
        
        $variables = [
            'clientId' => config('oauth.client_id')
        ];
        
        try {
            // Execute the GraphQL query
            $result = $this->graphqlClient->executeQuery($query, $variables);
            
            if (!$result || !isset($result['hr_GetDepartments'])) {
                Log::error('Failed to fetch departments from HUB API');
                return null;
            }
            
            $departments = $result['hr_GetDepartments'];
            
            // Cache the results if enabled
            if (config('hubapi.cache.enabled')) {
                Cache::put($cacheKey, $departments, config('hubapi.cache.ttl'));
            }
            
            Log::info('Successfully fetched departments from HUB API', [
                'count' => count($departments)
            ]);
            
            return $departments;
        } catch (\Exception $e) {
            Log::error('Exception while fetching departments: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return null;
        }
    }
    
    /**
     * Sync departments from HUB API to the database
     *
     * @return array Results of the sync operation
     */
    public function syncDepartmentsFromHubApi()
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'total' => 0,
        ];
        
        // Fetch departments from API
        $departments = $this->fetchDepartmentsFromHubApi();
        
        if (!$departments) {
            return $stats;
        }
        
        $stats['total'] = count($departments);
        
        foreach ($departments as $departmentData) {
            try {
                // Try to find existing department
                $department = Department::where('id', $departmentData['id'])->first();
                
                // Encode programs JSON
                $programs = $departmentData['programs'] ?? [];
                $programsJson = json_encode($programs);
                
                if ($department) {
                    // Update existing department
                    $department->update([
                        'name' => $departmentData['name'],
                        'programs' => $programsJson,
                    ]);
                    
                    $stats['updated']++;
                } else {
                    // Create new department
                    Department::create([
                        'id' => $departmentData['id'],
                        'name' => $departmentData['name'],
                        'programs' => $programsJson,
                    ]);
                    
                    $stats['created']++;
                }
            } catch (\Exception $e) {
                Log::error('Failed to sync department: ' . $e->getMessage(), [
                    'id' => $departmentData['id'] ?? 'unknown',
                    'exception' => get_class($e),
                ]);
                
                $stats['failed']++;
            }
        }
        
        Log::info('Department sync completed', $stats);
        
        return $stats;
    }
}