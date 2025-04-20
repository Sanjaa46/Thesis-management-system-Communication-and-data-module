<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HubApiService
{
    protected $client;
    protected $endpoint;
    protected $clientId;
    protected $clientSecret;
    protected $accessToken;
    protected $tokenExpiry;

    public function __construct()
    {
        $this->client = new Client([
            'verify' => config('services.hub_api.verify_ssl', false),
            'timeout' => 30,
        ]);

        $this->endpoint = config('services.hub_api.endpoint', 'http://localhost:8000/graphql');
        $this->clientId = config('services.hub_api.client_id', 'thesis_management_system');
        $this->clientSecret = config('services.hub_api.client_secret', 'your_secret_here');
    }

    /**
     * Get access token for API requests
     *
     * @return string|null
     */
    protected function getAccessToken()
    {
        // Check if we have a cached token
        $cacheKey = 'hub_api_token';
        
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        // If not, get a new token
        try {
            $query = <<<'GRAPHQL'
mutation Login($input: LoginInput!) {
    login(input: $input) {
        access_token
        expires_in
    }
}
GRAPHQL;

            $variables = [
                'input' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret
                ]
            ];

            $response = $this->executeQuery($query, $variables, false);
            
            if (isset($response['data']['login']['access_token'])) {
                $token = $response['data']['login']['access_token'];
                $expiresIn = $response['data']['login']['expires_in'] ?? 3600;
                
                // Cache the token for slightly less than its expiration time
                Cache::put($cacheKey, $token, $expiresIn - 60);
                
                return $token;
            }
        } catch (\Exception $e) {
            Log::error('Failed to get HUB API access token: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Execute a GraphQL query
     *
     * @param string $query The GraphQL query/mutation
     * @param array $variables Variables for the query
     * @param bool $authenticate Whether to authenticate the request
     * @return array|null The response data or null on failure
     */
    protected function executeQuery($query, $variables = [], $authenticate = true)
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];
        
        if ($authenticate) {
            $token = $this->getAccessToken();
            if (!$token) {
                Log::error('No access token available for HUB API request');
                return null;
            }
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        
        try {
            $response = $this->client->post($this->endpoint, [
                'headers' => $headers,
                'json' => [
                    'query' => $query,
                    'variables' => $variables
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            // Check for GraphQL errors
            if (isset($data['errors'])) {
                $errorMessages = array_map(function ($error) {
                    return $error['message'];
                }, $data['errors']);
                
                Log::error('GraphQL errors: ' . implode(', ', $errorMessages), [
                    'query' => $query,
                    'variables' => $this->sanitizeVariables($variables)
                ]);
            }
            
            return $data;
        } catch (GuzzleException $e) {
            Log::error('HUB API request failed: ' . $e->getMessage(), [
                'query' => $query,
                'variables' => $this->sanitizeVariables($variables)
            ]);
            
            return null;
        }
    }

    /**
     * Sanitize variables for logging (remove sensitive data)
     *
     * @param array $variables
     * @return array
     */
    protected function sanitizeVariables($variables)
    {
        $sanitized = $variables;
        
        // Remove sensitive data like client_secret
        if (isset($sanitized['input']['client_secret'])) {
            $sanitized['input']['client_secret'] = '[REDACTED]';
        }
        
        return $sanitized;
    }

    /**
     * Get departments data
     *
     * @return array|null
     */
    public function getDepartments()
    {
        $query = <<<'GRAPHQL'
query GetDepartments($clientId: String!) {
    hr_GetDepartments(clientId: $clientId) {
        id
        name
        programs {
            id
            index
            name
        }
    }
}
GRAPHQL;

        $variables = [
            'clientId' => $this->clientId
        ];

        $response = $this->executeQuery($query, $variables);
        
        if ($response && isset($response['data']['hr_GetDepartments'])) {
            return $response['data']['hr_GetDepartments'];
        }
        
        return null;
    }

    /**
     * Get teachers data with optional department filter
     *
     * @param string|null $departmentId
     * @return array|null
     */
    public function getTeachers($departmentId = null)
    {
        $query = <<<'GRAPHQL'
query GetTeachers($clientId: String!, $departmentId: String) {
    hr_GetTeachers(clientId: $clientId, departmentId: $departmentId) {
        id
        department_id
        department_name
        first_name
        last_name
        email
        phone
        position
        academic_degree
    }
}
GRAPHQL;

        $variables = [
            'clientId' => $this->clientId,
            'departmentId' => $departmentId
        ];

        $response = $this->executeQuery($query, $variables);
        
        if ($response && isset($response['data']['hr_GetTeachers'])) {
            return $response['data']['hr_GetTeachers'];
        }
        
        return null;
    }

    /**
     * Get a student by public hash
     *
     * @param string $publicHash
     * @return array|null
     */
    public function getStudentInfo($publicHash)
    {
        $query = <<<'GRAPHQL'
query GetStudentInfo($publicHash: String!, $clientId: String!) {
    sisi_GetStudentInfo(publicHash: $publicHash, clientId: $clientId) {
        sisi_id
        first_name
        last_name
        student_email
        personal_email
        program_name
        program_id
        phone
        department_id
        has_selected_research
    }
}
GRAPHQL;

        $variables = [
            'publicHash' => $publicHash,
            'clientId' => $this->clientId
        ];

        $response = $this->executeQuery($query, $variables);
        
        if ($response && isset($response['data']['sisi_GetStudentInfo'])) {
            return $response['data']['sisi_GetStudentInfo'];
        }
        
        return null;
    }

    /**
     * Get students with pagination
     *
     * @param int $skip Number of records to skip
     * @param int $take Number of records to take
     * @return array|null
     */
    public function getStudentsInfo($skip = 0, $take = 50)
    {
        $query = <<<'GRAPHQL'
query GetStudentsInfo($clientId: String!, $skip: Int, $take: Int) {
    sisi_GetStudentsInfo(clientId: $clientId, skip: $skip, take: $take) {
        sisi_id
        first_name
        last_name
        student_email
        personal_email
        program_name
        program_id
        phone
        department_id
        has_selected_research
    }
}
GRAPHQL;

        $variables = [
            'clientId' => $this->clientId,
            'skip' => $skip,
            'take' => $take
        ];

        $response = $this->executeQuery($query, $variables);
        
        if ($response && isset($response['data']['sisi_GetStudentsInfo'])) {
            return $response['data']['sisi_GetStudentsInfo'];
        }
        
        return null;
    }

    /**
     * Get students enrolled in thesis course
     *
     * @param string $departmentId
     * @param string $semesterId
     * @param string $courseCode Default is "THES400" (Thesis course)
     * @return array|null
     */
    public function getStudentsEnrolledInThesis($departmentId, $semesterId, $courseCode = 'THES400')
    {
        $query = <<<'GRAPHQL'
query GetStudentsEnrolledInThesis($clientId: String!, $departmentId: String!, $semesterId: String!, $courseCode: String!) {
    sisi_GetStudentsEnrolledInThesis(
        clientId: $clientId,
        departmentId: $departmentId,
        semesterId: $semesterId,
        courseCode: $courseCode
    ) {
        sisi_id
        first_name
        last_name
        student_email
        personal_email
        program_name
        program_id
        phone
        department_id
        has_selected_research
    }
}
GRAPHQL;

        $variables = [
            'clientId' => $this->clientId,
            'departmentId' => $departmentId,
            'semesterId' => $semesterId,
            'courseCode' => $courseCode
        ];

        $response = $this->executeQuery($query, $variables);
        
        if ($response && isset($response['data']['sisi_GetStudentsEnrolledInThesis'])) {
            return $response['data']['sisi_GetStudentsEnrolledInThesis'];
        }
        
        return null;
    }
}