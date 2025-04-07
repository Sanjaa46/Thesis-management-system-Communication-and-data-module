<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\OAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OAuthController extends Controller
{
    protected $oauthService;

    public function __construct(OAuthService $oauthService)
    {
        $this->oauthService = $oauthService;
    }

    /**
     * Redirect the user to the OAuth authorization page
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirectToProvider()
    {
        // Generate and store a random state to prevent CSRF attacks
        $state = Str::random(40);
        session(['oauth_state' => $state]);

        // Generate authorization URL and redirect
        $authUrl = $this->oauthService->getAuthorizationUrl($state);
        return redirect()->away($authUrl);
    }

    /**
     * Handle the callback from the OAuth provider
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleProviderCallback(Request $request)
    {
        // Check if there's an error or the user denied access
        if ($request->has('error')) {
            \Log::error('OAuth error: ' . $request->error);
            return redirect('http://localhost:4000/login')->with('error', 'Authentication failed: ' . $request->error_description);
        }
    
        // Exchange the authorization code for an access token
        $code = $request->code;
        $tokenData = $this->oauthService->getAccessToken($code);
    
        if (!$tokenData || !isset($tokenData['access_token'])) {
            \Log::error('Failed to obtain access token');
            return redirect('http://localhost:4000/login')->with('error', 'Failed to obtain access token');
        }
    
        // Add creation timestamp for expiration tracking
        $tokenData['created_at'] = time();
        
        // Store the tokens in the session
        session([config('oauth.token_session_key') => $tokenData]);
        
        // Debugging - Log session data
        \Log::info('Token stored in session', [
            'session_id' => session()->getId(),
            'has_token' => session()->has(config('oauth.token_session_key')),
            'token_type' => $tokenData['token_type'] ?? 'not_set',
        ]);
    
        // Get the user data
        $userData = $this->oauthService->getUserData($tokenData['access_token']);
    
        if (!$userData) {
            \Log::error('Failed to fetch user data');
            return redirect('http://localhost:4000/login')->with('error', 'Failed to fetch user data');
        }
    
        // Store the user data in the session as well
        session(['oauth_user' => $userData]);
        
        // Redirect to the frontend callback URL
        return redirect('http://localhost:4000/auth');
    }

    /**
     * Log the user out and clear OAuth session data
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout()
    {
        // Clear OAuth-related session data
        session()->forget([config('oauth.token_session_key'), 'oauth_user', 'oauth_state']);
        
        // Regular Laravel logout
        auth()->logout();
        
        return redirect()->route('home')->with('success', 'You have been logged out');
    }

    /**
     * Refresh the access token
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function refreshToken()
    {
        $tokenData = session(config('oauth.token_session_key'));
        
        // Check if we have a refresh token
        if (!$tokenData || !isset($tokenData['refresh_token'])) {
            return redirect()->route('oauth.redirect')->with('error', 'No refresh token available. Please login again.');
        }
        
        // Refresh the token
        $newTokenData = $this->oauthService->refreshToken($tokenData['refresh_token']);
        
        if (!$newTokenData || !isset($newTokenData['access_token'])) {
            return redirect()->route('oauth.redirect')->with('error', 'Failed to refresh the token. Please login again.');
        }
        
        // Store the new token data
        session([config('oauth.token_session_key') => $newTokenData]);
        
        // Redirect back to the previous page
        return redirect()->back()->with('success', 'Token refreshed successfully');
    }
}