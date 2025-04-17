<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TokenAuthentication
{
    public function handle(Request $request, Closure $next)
    {
        // Check different token sources in order of priority
        
        // 1. From Authorization header
        $bearerToken = $request->bearerToken();
        if ($bearerToken) {
            // Validate the token and get the user data
            $userData = $this->validateToken($bearerToken);
            if ($userData) {
                // Attach the token and user data to the request
                $request->attributes->add([
                    'access_token' => $bearerToken,
                    'auth_user' => $userData
                ]);
                return $next($request);
            }
        }
        
        // 2. From session storage
        $tokenData = session(config('oauth.token_session_key'));
        if ($tokenData && isset($tokenData['access_token'])) {
            // Validate the token and get the user data
            $userData = $this->validateToken($tokenData['access_token']);
            if ($userData) {
                // Attach the token and user data to the request
                $request->attributes->add([
                    'access_token' => $tokenData['access_token'],
                    'auth_user' => $userData
                ]);
                return $next($request);
            }
        }
        
        // 3. From query parameters (not recommended for production)
        if ($request->has('access_token')) {
            // Validate the token and get the user data
            $userData = $this->validateToken($request->input('access_token'));
            if ($userData) {
                // Attach the token and user data to the request
                $request->attributes->add([
                    'access_token' => $request->input('access_token'),
                    'auth_user' => $userData
                ]);
                return $next($request);
            }
        }
        
        // No valid token found
        Log::warning('Attempted to access protected API route without valid token');
        
        return response()->json([
            'error' => 'Unauthorized',
            'message' => 'Authentication token is missing or invalid'
        ], 401);
    }

    /**
     * Validate the token and return the associated user data
     *
     * @param string $token
     * @return array|null User data or null if invalid
     */
    protected function validateToken($token)
    {
        try {
            // Use the OAuth service to validate the token
            $oauthService = app(\App\Services\OAuthService::class);
            $userData = $oauthService->getUserData($token);
            
            // If we got user data back, the token is valid
            if ($userData) {
                // Convert the array format if needed
                if (isset($userData[0]['Type'])) {
                    // Convert from [{Type: "key", Value: "value"}] format to associative array
                    $formattedUserData = [];
                    foreach ($userData as $item) {
                        if (isset($item['Type']) && isset($item['Value'])) {
                            $formattedUserData[$item['Type']] = $item['Value'];
                        }
                    }
                    return $formattedUserData;
                }
                
                // Return the user data as is
                return $userData;
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Token validation error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}