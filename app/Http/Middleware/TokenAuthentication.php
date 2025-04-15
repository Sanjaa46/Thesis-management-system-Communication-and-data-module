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
            // Attach the token to the request for controllers to use
            $request->attributes->add(['access_token' => $bearerToken]);
            return $next($request);
        }
        
        // 2. From session storage
        $tokenData = session(config('oauth.token_session_key'));
        if ($tokenData && isset($tokenData['access_token'])) {
            $request->attributes->add(['access_token' => $tokenData['access_token']]);
            return $next($request);
        }
        
        // 3. From query parameters (not recommended for production)
        if ($request->has('access_token')) {
            $request->attributes->add(['access_token' => $request->input('access_token')]);
            return $next($request);
        }
        
        // No token found
        Log::warning('Attempted to access protected API route without valid token');
        
        return response()->json([
            'error' => 'Unauthorized',
            'message' => 'Authentication token is missing'
        ], 401);
    }
}