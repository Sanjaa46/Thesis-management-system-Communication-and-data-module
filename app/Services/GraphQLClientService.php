<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class GraphQLClientService
{
    protected $client;
    protected $baseUrl;
    protected $oauthService;

    public function __construct(OAuthService $oauthService)
    {
        $this->client = new Client([
            'verify' => config('oauth.verify_ssl', true),
            'timeout' => 30,
        ]);
        
        $this->oauthService = $oauthService;
        $this->baseUrl = config('hubapi.endpoint', 'https://tree.num.edu.mn/gateway');
    }

    /**
     * Execute a GraphQL query
     *
     * @param string $query The GraphQL query
     * @param array $variables Variables for the query
     * @param string|null $accessToken Optional access token (if not provided, will get token)
     * @return array|null Response data or null on failure
     */
    public function executeQuery($query, $variables = [], $accessToken = null)
    {
        try {
            // If no access token provided, get one
            if (!$accessToken) {
                Log::info('No access token provided, attempting to get one');
                $tokenData = $this->oauthService->getClientCredentialsToken();
                
                if (!$tokenData || !isset($tokenData['access_token'])) {
                    Log::error('Failed to obtain access token for GraphQL request');
                    return null;
                }
                
                $accessToken = $tokenData['access_token'];
                Log::info('Successfully obtained access token');
            }
            
            // Log the request (sanitized)
            Log::info('Executing GraphQL query', [
                'query' => $this->sanitizeQuery($query),
                'variables' => $variables,
                'endpoint' => $this->baseUrl
            ]);
            
            // Make the request
            $response = $this->client->post($this->baseUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'query' => $query,
                    'variables' => $variables
                ],
            ]);
            
            // Parse the response
            $result = json_decode($response->getBody(), true);
            
            // Log the response (basic info only)
            Log::info('GraphQL response received', [
                'status_code' => $response->getStatusCode(),
                'has_data' => isset($result['data']),
                'has_errors' => isset($result['errors']),
            ]);
            
            // Check for GraphQL errors
            if (isset($result['errors'])) {
                Log::error('GraphQL query returned errors', [
                    'errors' => $result['errors'],
                    'query' => $this->sanitizeQuery($query),
                ]);
                return null;
            }
            
            return $result['data'] ?? null;
        } catch (GuzzleException $e) {
            Log::error('GraphQL request failed: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'code' => $e->getCode(),
                'query' => $this->sanitizeQuery($query),
                'response' => $e->hasResponse() ? (string) $e->getResponse()->getBody() : 'No response body',
            ]);
            return null;
        }
    }
    
    /**
     * Sanitize GraphQL query for logging (removes extra whitespace)
     *
     * @param string $query
     * @return string
     */
    protected function sanitizeQuery($query)
    {
        // Remove comments
        $query = preg_replace('/\s*#.*$/m', '', $query);
        
        // Replace multiple spaces/newlines with a single space
        $query = preg_replace('/\s+/', ' ', $query);
        
        return trim($query);
    }
}