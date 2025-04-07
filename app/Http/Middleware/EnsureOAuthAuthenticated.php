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
        // Log the session state for debugging
        \Log::info('Middleware session check', [
            'session_id' => session()->getId(),
            'has_token' => session()->has(config('oauth.token_session_key')),
        ]);
        
        // Check if the user has OAuth tokens in the session
        $tokenData = session(config('oauth.token_session_key'));
        
        if (!$tokenData || !isset($tokenData['access_token'])) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }
            
            // Store the intended URL if it's a GET request
            if ($request->isMethod('get')) {
                session()->put('url.intended', $request->url());
            }
            
            // Redirect to the OAuth login route
            return redirect()->route('oauth.redirect');
        }
        
        return $next($request);
    }
}