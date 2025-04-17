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
            // Validate the token and get the user
            $user = $this->validateToken($bearerToken);
            if ($user) {
                // Attach the token and user to the request
                $request->attributes->add([
                    'access_token' => $bearerToken,
                    'auth_user' => $user
                ]);
                return $next($request);
            }
        }
        
        // 2. From session storage
        $tokenData = session(config('oauth.token_session_key'));
        if ($tokenData && isset($tokenData['access_token'])) {
            // Validate the token and get the user
            $user = $this->validateToken($tokenData['access_token']);
            if ($user) {
                // Attach the token and user to the request
                $request->attributes->add([
                    'access_token' => $tokenData['access_token'],
                    'auth_user' => $user
                ]);
                return $next($request);
            }
        }
        
        // 3. From query parameters (not recommended for production)
        if ($request->has('access_token')) {
            // Validate the token and get the user
            $user = $this->validateToken($request->input('access_token'));
            if ($user) {
                // Attach the token and user to the request
                $request->attributes->add([
                    'access_token' => $request->input('access_token'),
                    'auth_user' => $user
                ]);
                return $next($request);
            }
        }
        
        // No token found
        Log::warning('Attempted to access protected API route without valid token');
        
        return response()->json([
            'error' => 'Unauthorized',
            'message' => 'Authentication token is missing or invalid'
        ], 401);
    }

    /**
     * Validate the token and return the associated user
     *
     * @param string $token
     * @return mixed User object or null if invalid
     */
    protected function validateToken($token)
    {
        try {
            // Option 1: If using Laravel's built-in token validation
            // $user = \App\Models\User::where('api_token', $token)->first();
            
            // Option 2: If using your OAuth service
            $oauthService = app(\App\Services\OAuthService::class);
            $userData = $oauthService->getUserData($token);
            
            if ($userData) {
                // Find the user based on the data from the OAuth service
                // This depends on how your user data is structured
                // For example:
                $user = \App\Models\User::where('email', $userData['email'] ?? '')
                    ->orWhere('id', $userData['uid'] ?? '')
                    ->first();
                    
                return $user;
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Token validation error: ' . $e->getMessage());
            return null;
        }
    }
}