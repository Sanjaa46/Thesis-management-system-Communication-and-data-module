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
            'verify' => false,
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
     * @param string|null $accessToken Optional access token
     * @return array|null Response data or null on failure
     */
    public function executeQuery($query, $variables = [], $accessToken = null)
    {
        try {
            // If no access token provided, try to get a client credentials token
            if (!$accessToken) {
                $tokenData = $this->oauthService->getClientCredentialsToken();
                if (!$tokenData || !isset($tokenData['access_token'])) {
                    Log::error('No access token available - cannot execute GraphQL query');
                    return null;
                }
                $accessToken = $tokenData['access_token'];
            }
            
            Log::info('Executing GraphQL query', [
                'query_preview' => substr($this->sanitizeQuery($query), 0, 100) . '...',
                'variables' => array_keys($variables),
                'endpoint' => $this->baseUrl
            ]);
            
            $response = $this->client->post($this->baseUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'query' => $query,
                    'variables' => $variables
                ],
            ]);
            
            $result = json_decode($response->getBody(), true);
            
            Log::info('GraphQL response received', [
                'status_code' => $response->getStatusCode(),
                'has_data' => isset($result['data']),
                'has_errors' => isset($result['errors']),
            ]);
            
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