<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class GraphQLClientService
{
    protected $client;
    protected $baseUrl;
    protected $oauthService;
    protected $request;

    public function __construct(OAuthService $oauthService, Request $request)
    {
        $this->client = new Client([
            'verify' => config('oauth.verify_ssl', true),
            'timeout' => 30,
        ]);
        
        $this->oauthService = $oauthService;
        $this->baseUrl = config('hubapi.endpoint', 'https://tree.num.edu.mn/gateway');
        $this->request = $request;
    }

    /**
     * Get access token from various sources
     *
     * @param string|null $providedToken Optional directly provided token
     * @return string|null Access token or null if not available
     */
    protected function getAccessToken($providedToken = null)
    {
        // Priority 1: Use token provided directly to the method
        if ($providedToken) {
            return $providedToken;
        }
        
        // Priority 2: Check for Bearer token in Authorization header
        $bearerToken = $this->request->bearerToken();
        if ($bearerToken) {
            Log::info('Using token from Authorization header');
            return $bearerToken;
        }
        
        // Priority 3: Check the session
        $tokenData = session(config('oauth.token_session_key'));
        if ($tokenData && isset($tokenData['access_token'])) {
            Log::info('Using token from session');
            return $tokenData['access_token'];
        }
        
        // Last resort: Try to get a client credentials token (likely won't work if client isn't authorized)
        try {
            Log::info('Attempting to get client credentials token as last resort');
            $tokenData = $this->oauthService->getClientCredentialsToken();
            if ($tokenData && isset($tokenData['access_token'])) {
                return $tokenData['access_token'];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get client credentials token: ' . $e->getMessage());
        }
        
        Log::error('No access token available from any source');
        return null;
    }

    /**
     * Execute a GraphQL query
     *
     * @param string $query The GraphQL query
     * @param array $variables Variables for the query
     * @param string|null $accessToken Optional access token
     * @return array|null Response data or null on failure
     */
    public function executeQuery($query, $variables = [], $accessToken = null)
    {
        try {
            // Get token using our helper method
            $token = $this->getAccessToken($accessToken);
            
            if (!$token) {
                Log::error('No access token available - cannot execute GraphQL query');
                return null;
            }
            
            // Log the request (sanitized)
            Log::info('Executing GraphQL query', [
                'query_preview' => substr($this->sanitizeQuery($query), 0, 100) . '...',
                'variables' => array_keys($variables),
                'endpoint' => $this->baseUrl
            ]);
            
            // Make the request
            $response = $this->client->post($this->baseUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
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
                ]);
                return null;
            }
            
            return $result['data'] ?? null;
        } catch (GuzzleException $e) {
            Log::error('GraphQL request failed: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'code' => $e->getCode(),
                'response' => $e->hasResponse() ? (string) $e->getResponse()->getBody() : 'No response body',
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Unexpected error in GraphQL execution: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return null;
        }
    }
    
    /**
     * Execute a GraphQL mutation
     *
     * @param string $mutation The GraphQL mutation
     * @param array $variables Variables for the mutation
     * @param string|null $accessToken Optional access token
     * @return array|null Response data or null on failure
     */
    public function executeMutation($mutation, $variables = [], $accessToken = null)
    {
        // For mutations, we reuse the same execution logic
        return $this->executeQuery($mutation, $variables, $accessToken);
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