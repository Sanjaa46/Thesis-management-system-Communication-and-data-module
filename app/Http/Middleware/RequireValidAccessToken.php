<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RequireValidAccessToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if we have a token in session
        $tokenData = session(config('oauth.token_session_key'));
        
        if (!$tokenData || !isset($tokenData['access_token'])) {
            Log::warning('Attempted to access protected API route without valid token');
            
            // Always return JSON response for API routes
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Please login first to access this functionality'
            ], 401);
        }
        
        return $next($request);
    }
}