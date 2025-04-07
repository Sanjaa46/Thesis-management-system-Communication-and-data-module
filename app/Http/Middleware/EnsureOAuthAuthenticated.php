<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EnsureOAuthAuthenticated
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
        // Check if the user has OAuth tokens in the session
        $tokenData = session(config('oauth.token_session_key'));
        
        if (!$tokenData || !isset($tokenData['access_token'])) {
            // Store the intended URL if it's a GET request
            if ($request->isMethod('get')) {
                session()->put('url.intended', $request->url());
            }
            
            // Redirect to the OAuth login route
            return redirect()->route('oauth.redirect')
                ->with('error', 'Please login to access this resource');
        }
        
        // Check if the token is expired
        if (isset($tokenData['expires_in']) && isset($tokenData['created_at'])) {
            $expiresAt = $tokenData['created_at'] + $tokenData['expires_in'];
            
            if (time() >= $expiresAt && isset($tokenData['refresh_token'])) {
                Log::info('OAuth token expired, redirecting to refresh');
                return redirect()->route('oauth.refresh');
            }
        }
        
        return $next($request);
    }
}