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
            'verify' => config('oauth.verify_ssl', true),
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
     * @return string The authorization URL
     */
    // This method correctly generates an authorization URL with state parameter
    public function getAuthorizationUrl($state = null)
    {
        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
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
            $response = $this->client->post($this->tokenEndpoint, [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'redirect_uri' => $this->redirectUri,
                    'code' => $code,
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (GuzzleException $e) {
            Log::error('Failed to get access token: ' . $e->getMessage());
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
            $response = $this->client->get($this->resourceEndpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (GuzzleException $e) {
            Log::error('Failed to get user data: ' . $e->getMessage());
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
            $response = $this->client->post($this->tokenEndpoint, [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $refreshToken,
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (GuzzleException $e) {
            Log::error('Failed to refresh token: ' . $e->getMessage());
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
            return Cache::get($cacheKey);
        }
        
        try {
            $response = $this->client->post($this->tokenEndpoint, [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
            ]);

            $tokenData = json_decode($response->getBody(), true);
            
            // Cache the token for slightly less than its expiration time
            if (isset($tokenData['expires_in'])) {
                Cache::put($cacheKey, $tokenData, $tokenData['expires_in'] - 60);
            }
            
            return $tokenData;
        } catch (GuzzleException $e) {
            Log::error('Failed to get client credentials token: ' . $e->getMessage());
            return null;
        }
    }
}