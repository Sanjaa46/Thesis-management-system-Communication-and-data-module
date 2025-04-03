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
            'verify' => config('oauth.verify_ssl', true),
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
                    Log::error('No access token available for API request');
                    return null;
                }
                
                $accessToken = $clientTokenData['access_token'];
            } else {
                $accessToken = $tokenData['access_token'];
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
            } else {
                $options['json'] = $data;
            }
        }
        
        try {
            $response = $this->client->request(strtoupper($method), $url, $options);
            return json_decode($response->getBody(), true);
        } catch (GuzzleException $e) {
            Log::error('API request failed: ' . $e->getMessage());
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
}