<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\OAuthService;

class CheckOAuthTokenExpiration
{
    protected $oauthService;

    public function __construct(OAuthService $oauthService)
    {
        $this->oauthService = $oauthService;
    }

    /**
     * Handle an incoming request and check if OAuth token is expired
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip if no OAuth session
        if (!session()->has(config('oauth.token_session_key'))) {
            return $next($request);
        }
        
        $tokenData = session(config('oauth.token_session_key'));
        
        // Check if token is expired
        if (isset($tokenData['expires_in']) && isset($tokenData['created_at'])) {
            $expiresAt = $tokenData['created_at'] + $tokenData['expires_in'];
            $bufferTime = 60; // Refresh token 60 seconds before it expires
            
            if (time() >= ($expiresAt - $bufferTime)) {
                // Try to refresh the token if we have a refresh token
                if (isset($tokenData['refresh_token'])) {
                    try {
                        $newTokenData = $this->oauthService->refreshToken($tokenData['refresh_token']);
                        
                        if ($newTokenData && isset($newTokenData['access_token'])) {
                            $newTokenData['created_at'] = time();
                            session([config('oauth.token_session_key') => $newTokenData]);
                            Log::info('OAuth token refreshed automatically by middleware');
                        } else {
                            // If refresh fails and token is already expired, redirect to login
                            if (time() >= $expiresAt) {
                                Log::notice('OAuth token expired and refresh failed');
                                
                                if ($request->expectsJson()) {
                                    return response()->json([
                                        'error' => 'authentication_required',
                                        'message' => 'Your session has expired. Please log in again.'
                                    ], 401);
                                }
                                
                                // Store intended URL for redirect after login
                                if ($request->isMethod('get')) {
                                    session()->put('url.intended', $request->url());
                                }
                                
                                return redirect()->route('oauth.redirect')
                                    ->with('error', 'Your session has expired. Please log in again.');
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Error refreshing token in middleware: ' . $e->getMessage());
                    }
                } else if (time() >= $expiresAt) {
                    // No refresh token and token is expired
                    session()->forget(config('oauth.token_session_key'));
                    
                    if ($request->expectsJson()) {
                        return response()->json([
                            'error' => 'authentication_required',
                            'message' => 'Your session has expired. Please log in again.'
                        ], 401);
                    }
                    
                    // Store intended URL for redirect after login
                    if ($request->isMethod('get')) {
                        session()->put('url.intended', $request->url());
                    }
                    
                    return redirect()->route('oauth.redirect')
                        ->with('error', 'Your session has expired. Please log in again.');
                }
            }
        }
        
        return $next($request);
    }
}