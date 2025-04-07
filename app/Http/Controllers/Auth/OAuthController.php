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
        try {
            // Generate and store a random state to prevent CSRF attacks
            $state = Str::random(40);
            session(['oauth_state' => $state]);

            // Generate authorization URL and redirect
            $authUrl = $this->oauthService->getAuthorizationUrl($state);
            
            Log::info('Redirecting to OAuth provider', [
                'redirect_url' => preg_replace('/client_secret=[^&]+/', 'client_secret=REDACTED', $authUrl),
            ]);
            
            return redirect()->away($authUrl);
        } catch (\Exception $e) {
            Log::error('Failed to redirect to OAuth provider: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return redirect()->route('home')->with('error', 
                'Authentication service is currently unavailable. Please try again later or contact support.');
        }
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
            $errorCode = $request->error;
            $errorDescription = $request->error_description ?? 'Unknown error';
            
            Log::error('OAuth callback error', [
                'error' => $errorCode,
                'description' => $errorDescription,
            ]);
            
            $userMessage = $this->getHumanReadableError($errorCode, $errorDescription);
            return redirect(config('oauth.frontend_url', 'http://localhost:4000') . '/login')
                   ->with('error', $userMessage);
        }
        
        // Validate state parameter to prevent CSRF attacks
        $storedState = session('oauth_state');
        $returnedState = $request->state;
        
        if (empty($storedState) || $returnedState !== $storedState) {
            Log::warning('OAuth state mismatch', [
                'has_stored_state' => !empty($storedState),
                'states_match' => $returnedState === $storedState,
            ]);
            
            return redirect(config('oauth.frontend_url', 'http://localhost:4000') . '/login')
                   ->with('error', 'Invalid authentication state. Please try again.');
        }
        
        // Exchange the authorization code for an access token
        $code = $request->code;
        
        if (empty($code)) {
            Log::error('OAuth callback missing authorization code');
            return redirect(config('oauth.frontend_url', 'http://localhost:4000') . '/login')
                   ->with('error', 'Missing authorization code. Please try again.');
        }
        
        try {
            $tokenData = $this->oauthService->getAccessToken($code);
        
            if (!$tokenData || !isset($tokenData['access_token'])) {
                Log::error('Failed to obtain access token', [
                    'response' => $tokenData ? 'empty_token' : 'null_response',
                ]);
                
                return redirect(config('oauth.frontend_url', 'http://localhost:4000') . '/login')
                       ->with('error', 'Failed to obtain access token. Please try again or contact support.');
            }
        
            // Add creation timestamp for expiration tracking
            $tokenData['created_at'] = time();
            
            // Store the tokens in the session
            session([config('oauth.token_session_key') => $tokenData]);
            
            // Debugging - Log session data (without exposing tokens)
            Log::info('Token stored in session', [
                'session_id' => session()->getId(),
                'has_token' => session()->has(config('oauth.token_session_key')),
                'token_type' => $tokenData['token_type'] ?? 'not_set',
                'expires_in' => $tokenData['expires_in'] ?? 'unknown',
            ]);
        
            // Get the user data
            $userData = $this->oauthService->getUserData($tokenData['access_token']);
        
            if (!$userData) {
                Log::error('Failed to fetch user data');
                return redirect(config('oauth.frontend_url', 'http://localhost:4000') . '/login')
                       ->with('error', 'Successfully authenticated but failed to fetch your user profile. Please try again.');
            }
        
            // Store the user data in the session as well
            session(['oauth_user' => $userData]);
            
            // Clear the state param since we don't need it anymore
            session()->forget('oauth_state');
            
            // Redirect to the frontend callback URL
            $redirectUrl = session('url.intended', config('oauth.frontend_url', 'http://localhost:4000') . '/auth');
            session()->forget('url.intended');
            
            Log::info('OAuth authentication completed successfully', [
                'redirect_to' => $redirectUrl
            ]);
            
            return redirect($redirectUrl);
        } catch (\Exception $e) {
            Log::error('Exception during OAuth callback processing: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect(config('oauth.frontend_url', 'http://localhost:4000') . '/login')
                   ->with('error', 'An error occurred during authentication. Please try again or contact support.');
        }
    }

    /**
     * Log the user out and clear OAuth session data
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout()
    {
        try {
            // Clear OAuth-related session data
            session()->forget([config('oauth.token_session_key'), 'oauth_user', 'oauth_state']);
            
            // Regular Laravel logout
            auth()->logout();
            
            Log::info('User logged out successfully');
            
            return redirect()->to(config('oauth.frontend_url', 'http://localhost:4000'))
                   ->with('success', 'You have been logged out successfully');
        } catch (\Exception $e) {
            Log::error('Error during logout: ' . $e->getMessage());
            return redirect()->to(config('oauth.frontend_url', 'http://localhost:4000'))
                   ->with('error', 'An error occurred during logout, but your session has been cleared.');
        }
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
            Log::warning('Refresh token attempt with no refresh token available');
            return redirect()->route('oauth.redirect')
                   ->with('error', 'No refresh token available. Please login again.');
        }
        
        try {
            // Refresh the token
            $newTokenData = $this->oauthService->refreshToken($tokenData['refresh_token']);
            
            if (!$newTokenData || !isset($newTokenData['access_token'])) {
                Log::error('Token refresh failed', [
                    'response' => $newTokenData ? 'empty_token' : 'null_response',
                ]);
                
                return redirect()->route('oauth.redirect')
                       ->with('error', 'Failed to refresh the token. Please login again.');
            }
            
            // Add the creation timestamp 
            $newTokenData['created_at'] = time();
            
            // Store the new token data
            session([config('oauth.token_session_key') => $newTokenData]);
            
            Log::info('Token refreshed successfully');
            
            // Redirect back to the previous page or intended URL
            $redirectTo = session('url.intended', url()->previous());
            session()->forget('url.intended');
            
            return redirect($redirectTo)->with('success', 'Your session has been refreshed.');
        } catch (\Exception $e) {
            Log::error('Exception during token refresh: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return redirect()->route('oauth.redirect')
                   ->with('error', 'An error occurred while refreshing your session. Please login again.');
        }
    }
    
    /**
     * Get a human-readable error message from OAuth error codes
     *
     * @param string $errorCode
     * @param string $errorDescription
     * @return string
     */
    protected function getHumanReadableError($errorCode, $errorDescription)
    {
        $messages = [
            'invalid_request' => 'The authentication request was invalid or malformed.',
            'unauthorized_client' => 'This application is not authorized to request authentication.',
            'access_denied' => 'You declined the authentication request.',
            'unsupported_response_type' => 'The authentication server does not support this type of request.',
            'invalid_scope' => 'The requested permissions were invalid or malformed.',
            'server_error' => 'The authentication server encountered an error.',
            'temporarily_unavailable' => 'The authentication service is temporarily unavailable.'
        ];
        
        return $messages[$errorCode] ?? "Authentication failed: $errorDescription";
    }
}