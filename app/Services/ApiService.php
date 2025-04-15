<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class ApiService
{
    protected $client;
    protected $oauthService;
    protected $baseUrl;

    public function __construct(OAuthService $oauthService)
    {
        $this->client = new Client([
            'verify' => false,
            'timeout' => 30,
        ]);
        
        $this->oauthService = $oauthService;
        $this->baseUrl = config('services.api.base_url', 'https://tree.num.edu.mn/gateway');
    }

    /**
     * Make an authenticated API request
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint
     * @param array $data Data to send with the request
     * @param string|null $accessToken Optional access token (if not provided, will use the one from session)
     * @return array|null Response data or null on failure
     */
    public function request($method, $endpoint, $data = [], $accessToken = null)
    {
        // If no access token is provided, try to get it from session
        if (!$accessToken) {
            $tokenData = session(config('oauth.token_session_key'));
            
            if (!$tokenData || !isset($tokenData['access_token'])) {
                // If no user token, try to use a client credentials token
                $clientTokenData = $this->oauthService->getClientCredentialsToken();
                
                if (!$clientTokenData || !isset($clientTokenData['access_token'])) {
                    Log::error('No access token available for API request to: ' . $endpoint);
                    return null;
                }
                
                $accessToken = $clientTokenData['access_token'];
                Log::info('Using client credentials token for API request', [
                    'endpoint' => $endpoint,
                    'method' => $method,
                ]);
            } else {
                $accessToken = $tokenData['access_token'];
                Log::info('Using user access token for API request', [
                    'endpoint' => $endpoint,
                    'method' => $method,
                ]);
            }
        }
        
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ],
        ];
        
        // Add data to the request
        if (!empty($data)) {
            if (strtoupper($method) === 'GET') {
                $options['query'] = $data;
                Log::debug('API request with query parameters', [
                    'endpoint' => $endpoint,
                    'method' => $method,
                    'param_count' => count($data),
                ]);
            } else {
                $options['json'] = $data;
                // Log data for non-GET requests without sensitive details
                $safeData = $this->sanitizeDataForLogging($data);
                Log::debug('API request with JSON body', [
                    'endpoint' => $endpoint,
                    'method' => $method,
                    'data' => $safeData,
                ]);
            }
        }
        
        try {
            $response = $this->client->request(strtoupper($method), $url, $options);
            $responseData = json_decode($response->getBody(), true);
            
            Log::info('API request successful', [
                'endpoint' => $endpoint,
                'method' => $method,
                'status' => $response->getStatusCode(),
                'response_size' => strlen($response->getBody()),
            ]);
            
            return $responseData;
        } catch (GuzzleException $e) {
            $this->logApiException($endpoint, $method, $e);
            return null;
        }
    }
    
    /**
     * Make a GET request
     *
     * @param string $endpoint API endpoint
     * @param array $data Query parameters
     * @param string|null $accessToken Optional access token
     * @return array|null Response data or null on failure
     */
    public function get($endpoint, $data = [], $accessToken = null)
    {
        return $this->request('GET', $endpoint, $data, $accessToken);
    }
    
    /**
     * Make a POST request
     *
     * @param string $endpoint API endpoint
     * @param array $data Request body
     * @param string|null $accessToken Optional access token
     * @return array|null Response data or null on failure
     */
    public function post($endpoint, $data = [], $accessToken = null)
    {
        return $this->request('POST', $endpoint, $data, $accessToken);
    }
    
    /**
     * Make a PUT request
     *
     * @param string $endpoint API endpoint
     * @param array $data Request body
     * @param string|null $accessToken Optional access token
     * @return array|null Response data or null on failure
     */
    public function put($endpoint, $data = [], $accessToken = null)
    {
        return $this->request('PUT', $endpoint, $data, $accessToken);
    }
    
    /**
     * Make a DELETE request
     *
     * @param string $endpoint API endpoint
     * @param array $data Request body
     * @param string|null $accessToken Optional access token
     * @return array|null Response data or null on failure
     */
    public function delete($endpoint, $data = [], $accessToken = null)
    {
        return $this->request('DELETE', $endpoint, $data, $accessToken);
    }
    
    /**
     * Log an API exception with safe contextual data
     *
     * @param string $endpoint
     * @param string $method
     * @param GuzzleException $e
     * @return void
     */
    protected function logApiException($endpoint, $method, GuzzleException $e)
    {
        $context = [
            'endpoint' => $endpoint,
            'method' => $method,
            'exception' => get_class($e),
            'code' => $e->getCode(),
        ];
        
        // Don't log the full exception message as it might contain sensitive info
        // Instead extract just the status code and reason
        if (method_exists($e, 'getResponse') && $e->getResponse()) {
            $response = $e->getResponse();
            $context['status_code'] = $response->getStatusCode();
            $context['reason'] = $response->getReasonPhrase();
            
            try {
                $body = (string) $response->getBody();
                $json = json_decode($body, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Include only safe fields from the response
                    $safeFields = ['error', 'error_description', 'message', 'code'];
                    foreach ($safeFields as $field) {
                        if (isset($json[$field])) {
                            $context['response_' . $field] = $json[$field];
                        }
                    }
                }
            } catch (\Exception $bodyEx) {
                // Do nothing if we can't read the body
            }
        }
        
        Log::error('API request failed: ' . $this->extractErrorMessage($e), $context);
    }
    
    /**
     * Extract a safe error message from an exception
     *
     * @param \Exception $e
     * @return string
     */
    protected function extractErrorMessage(\Exception $e)
    {
        $message = $e->getMessage();
        
        // Remove potential sensitive data from error messages
        $message = preg_replace('/Bearer\s+[a-zA-Z0-9\._\-]+/', 'Bearer [REDACTED]', $message);
        $message = preg_replace('/client_secret=[^&]+/', 'client_secret=[REDACTED]', $message);
        $message = preg_replace('/password=[^&]+/', 'password=[REDACTED]', $message);
        
        return $message;
    }
    
    /**
     * Sanitize data for logging to remove sensitive information
     *
     * @param array $data
     * @return array
     */
    protected function sanitizeDataForLogging($data)
    {
        if (!is_array($data)) {
            return $data;
        }
        
        $sensitiveKeys = [
            'password', 'secret', 'token', 'key', 'auth', 'pass', 'login',
            'ssn', 'social_security', 'credit_card', 'card_number', 'authorization',
            'cvv', 'pin', 'secret_question'
        ];
        
        $result = [];
        
        foreach ($data as $key => $value) {
            // Check if this key contains any sensitive words
            $isSensitive = false;
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (stripos($key, $sensitiveKey) !== false) {
                    $isSensitive = true;
                    break;
                }
            }
            
            if ($isSensitive) {
                $result[$key] = '[REDACTED]';
            } else if (is_array($value)) {
                $result[$key] = $this->sanitizeDataForLogging($value);
            } else {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }
}