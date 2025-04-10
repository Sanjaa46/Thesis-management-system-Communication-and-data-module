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
// Modify app/Http/Middleware/EnsureOAuthAuthenticated.php to handle both session and header tokens:
    public function handle(Request $request, Closure $next)
    {
        // Debug the current route to see if login routes are getting middleware applied
        \Log::info('Current route:', [
            'route' => $request->route(),
            'path' => $request->path()
        ]);
        
        // Don't redirect if already on OAuth routes
        if ($request->routeIs('oauth.redirect') || $request->routeIs('oauth.callback')) {
            return $next($request);
        }
        
        // Check authentication logic here
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