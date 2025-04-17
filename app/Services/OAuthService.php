<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OAuthService
{
    protected $client;
    protected $authorizationEndpoint;
    protected $tokenEndpoint;
    protected $resourceEndpoint;
    protected $clientId;
    protected $clientSecret;
    protected $redirectUri;
    protected $scopes;

    public function __construct()
    {
        $this->client = new Client([
            'verify' => config('oauth.verify_ssl', false),
            'timeout' => 30,
        ]);

        $this->authorizationEndpoint = config('oauth.authorization_endpoint', 'https://auth.num.edu.mn/oauth2/oauth/authorize');
        $this->tokenEndpoint = config('oauth.token_endpoint', 'https://auth.num.edu.mn/oauth2/oauth/token');
        $this->resourceEndpoint = config('oauth.resource_endpoint', 'https://auth.num.edu.mn/resource/me');
        $this->clientId = config('oauth.client_id');
        $this->clientSecret = config('oauth.client_secret');
        $this->redirectUri = config('oauth.redirect_uri');
        $this->scopes = config('oauth.scopes', '');
    }

    /**
     * Generate authorization URL for the OAuth flow
     *
     * @param string $state A random state parameter to prevent CSRF
     * @param string|null $overrideRedirectUri Override the default redirect URI
     * @return string The authorization URL
     */
    public function getAuthorizationUrl($state = null, $overrideRedirectUri = null)
    {
        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $overrideRedirectUri ?: $this->redirectUri,
        ];
        
        if (!empty($this->scopes)) {
            $params['scope'] = $this->scopes;
        }
        
        if ($state) {
            $params['state'] = $state;
        }
        
        return $this->authorizationEndpoint . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for an access token
     *
     * @param string $code The authorization code received
     * @return array|null The token response or null on failure
     */
    public function getAccessToken($code)
    {
        try {
            // Log attempt without sensitive data
            Log::info('Exchanging authorization code for access token');
            
            $response = $this->client->post($this->tokenEndpoint, [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'redirect_uri' => $this->redirectUri,
                    'code' => $code,
                ],
            ]);

            $tokenData = json_decode($response->getBody(), true);
            
            // Log success without exposing tokens
            Log::info('Successfully obtained access token', [
                'token_type' => $tokenData['token_type'] ?? 'unknown',
                'expires_in' => $tokenData['expires_in'] ?? 'unknown',
                'has_refresh_token' => isset($tokenData['refresh_token']),
            ]);
            
            return $tokenData;
        } catch (GuzzleException $e) {
            Log::error('Failed to get access token: ' . $e->getMessage(), [
                'code' => $e->getCode(),
                'response' => $e->hasResponse() ? (string) $e->getResponse()->getBody() : 'No response',
            ]);
            
            return null;
        }
    }

    /**
     * Fetch the user's data from the resource server
     *
     * @param string $accessToken The access token
     * @return array|null The user data or null on failure
     */
    public function getUserData($accessToken)
    {
        try {
            // Mask the token for logging
            $maskedToken = $this->maskString($accessToken);
            Log::info('Fetching user data using token', [
                'token' => $maskedToken,
            ]);
            
            $response = $this->client->get($this->resourceEndpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json',
                ],
            ]);

            $userData = json_decode($response->getBody(), true);
            
            // Log success without exposing user data
            Log::info('Successfully fetched user data', [
                'data_count' => is_array($userData) ? count($userData) : 'not_array',
                'data_sample' => is_array($userData) && !empty($userData) ? json_encode(array_slice($userData, 0, 1)) : 'empty',
            ]);
            
            return $userData;
        } catch (GuzzleException $e) {
            $this->logRequestException('Failed to get user data', $e);
            return null;
        }
    }

    /**
     * Refresh an expired access token
     *
     * @param string $refreshToken The refresh token
     * @return array|null The new token response or null on failure
     */
    public function refreshToken($refreshToken)
    {
        try {
            // Mask the token for logging
            $maskedToken = $this->maskString($refreshToken);
            Log::info('Refreshing access token', [
                'refresh_token' => $maskedToken,
            ]);
            
            $response = $this->client->post($this->tokenEndpoint, [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $refreshToken,
                ],
            ]);

            $tokenData = json_decode($response->getBody(), true);
            
            // Log success without exposing tokens
            Log::info('Successfully refreshed access token', [
                'token_type' => $tokenData['token_type'] ?? 'unknown',
                'expires_in' => $tokenData['expires_in'] ?? 'unknown',
                'has_refresh_token' => isset($tokenData['refresh_token']),
            ]);
            
            return $tokenData;
        } catch (GuzzleException $e) {
            $this->logRequestException('Failed to refresh token', $e);
            return null;
        }
    }

    /**
     * Use Client Credentials grant type to get an application-level access token
     * 
     * @return array|null The token response or null on failure
     */
    public function getClientCredentialsToken()
    {
        $cacheKey = 'oauth_client_credentials_token';
        
        // Check if we have a cached token
        if (Cache::has($cacheKey)) {
            Log::info('Using cached client credentials token');
            return Cache::get($cacheKey);
        }
        
        try {
            Log::info('Obtaining new client credentials token');
            
            $response = $this->client->post($this->tokenEndpoint, [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept' => 'application/json',
                ],
            ]);
    
            $tokenData = json_decode($response->getBody(), true);
            
            // Log success without exposing tokens
            Log::info('Successfully obtained client credentials token', [
                'token_type' => $tokenData['token_type'] ?? 'unknown',
                'expires_in' => $tokenData['expires_in'] ?? 'unknown',
            ]);
            
            // Cache the token for slightly less than its expiration time
            if (isset($tokenData['expires_in'])) {
                $cacheDuration = $tokenData['expires_in'] - config('oauth.token_refresh_buffer', 60);
                Cache::put($cacheKey, $tokenData, $cacheDuration);
            }
            
            return $tokenData;
        } catch (GuzzleException $e) {
            $this->logRequestException('Failed to get client credentials token', $e);
            return null;
        }
    }
    
    /**
     * Log an exception from HTTP requests without exposing sensitive data
     * 
     * @param string $message
     * @param GuzzleException $e
     * @return void
     */
    protected function logRequestException($message, GuzzleException $e)
    {
        $context = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
        ];
        
        // Add response info if available
        if (method_exists($e, 'getResponse') && $e->getResponse()) {
            $response = $e->getResponse();
            $context['status_code'] = $response->getStatusCode();
            $context['reason_phrase'] = $response->getReasonPhrase();
            
            // Try to get response body but don't include credentials
            try {
                $body = (string) $response->getBody();
                // Don't log the entire body in case it contains sensitive info
                $context['response_size'] = strlen($body);
                
                // Try to decode as JSON to log structured error info
                $json = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Include only safe fields
                    $safeFields = ['error', 'error_description', 'error_code', 'message'];
                    foreach ($safeFields as $field) {
                        if (isset($json[$field])) {
                            $context['response_' . $field] = $json[$field];
                        }
                    }
                }
            } catch (\Exception $bodyEx) {
                $context['body_error'] = $bodyEx->getMessage();
            }
        }
        
        Log::error($message, $context);
    }
    
    /**
     * Mask a string for safe logging (shows first 4 and last 4 chars only)
     * 
     * @param string $string The string to mask
     * @return string The masked string
     */
    protected function maskString($string)
    {
        if (empty($string)) {
            return '';
        }
        
        $length = strlen($string);
        
        if ($length <= 8) {
            return '****';
        }
        
        return substr($string, 0, 4) . str_repeat('*', $length - 8) . substr($string, -4);
    }
}