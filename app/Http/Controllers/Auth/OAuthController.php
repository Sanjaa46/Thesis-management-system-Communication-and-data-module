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
            
            // Store state in cookie rather than session
            $cookie = cookie('oauth_state', $state, 5, '/', null, false, false);
            
            // Build the authorization URL
            $params = [
                'client_id' => config('oauth.client_id'),
                'response_type' => 'code',
                'redirect_uri' => config('oauth.redirect_uri', 'http://localhost:4000/auth'),
                'state' => $state
            ];
            
            $authUrl = 'https://auth.num.edu.mn/oauth2/oauth/authorize?' . http_build_query($params);
            
            // Log the redirect for debugging
            \Log::info('Redirecting to OAuth provider', [
                'auth_url' => preg_replace('/client_secret=[^&]+/', 'client_secret=REDACTED', $authUrl),
            ]);
            
            // Redirect to the authorization server
            return redirect()->away($authUrl)->withCookie($cookie);
        } catch (\Exception $e) {
            \Log::error('Failed to redirect to OAuth provider: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return redirect()->route('home')->with('error', 
                'Authentication service is currently unavailable. Please try again later or contact support.');
        }

        dd([
            'client_id' => config('oauth.client_id'),
            'redirect_uri' => config('oauth.redirect_uri'),
            'configured' => !empty(config('oauth.client_id')) && !empty(config('oauth.redirect_uri'))
        ]);
    }

    /**
     * Handle the callback from the OAuth provider
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleProviderCallback(Request $request)
    {
        // Add more detailed logging
        Log::info('OAuth callback received', [
            'has_error' => $request->has('error'),
            'has_code' => $request->has('code'),
            'has_state' => $request->has('state'),
            'full_url' => $request->fullUrl(),
            'all_params' => $request->all(),
        ]);

        // Check if there's an error or the user denied access
        if ($request->has('error')) {
            $errorCode = $request->error;
            $errorDescription = $request->error_description ?? 'Unknown error';
            
            Log::error('OAuth callback error', [
                'error' => $errorCode,
                'description' => $errorDescription,
            ]);
            
            $userMessage = $this->getHumanReadableError($errorCode, $errorDescription);
            return redirect(config('oauth.frontend_url', 'http://localhost:4000') . '/login?error=' . urlencode($userMessage));
        }
        
        // Validate state parameter to prevent CSRF attacks
        $storedState = $request->cookie('oauth_state');
        $returnedState = $request->state;
        
        Log::info('OAuth state validation', [
            'has_stored_state' => !empty($storedState),
            'returned_state' => $returnedState,
            'states_match' => ($returnedState === $storedState),
        ]);
        
        if (empty($storedState) || $returnedState !== $storedState) {
            Log::warning('OAuth state mismatch', [
                'has_stored_state' => !empty($storedState),
                'states_match' => ($returnedState === $storedState),
            ]);
            
            return redirect(config('oauth.frontend_url', 'http://localhost:4000') . '/login?error=' . urlencode('Invalid authentication state. Please try again.'));
        }
        
        // Exchange the authorization code for an access token
        $code = $request->code;
        
        if (empty($code)) {
            Log::error('OAuth callback missing authorization code');
            return redirect(config('oauth.frontend_url', 'http://localhost:4000') . '/login?error=' . urlencode('Missing authorization code. Please try again.'));
        }
        
        try {
            $tokenData = $this->oauthService->getAccessToken($code);
        
            if (!$tokenData || !isset($tokenData['access_token'])) {
                Log::error('Failed to obtain access token', [
                    'token_data_exists' => !empty($tokenData),
                    'has_access_token' => isset($tokenData['access_token']),
                ]);
                return redirect(config('oauth.frontend_url') . '/login?error=token_failure');
            }
        
            // Add creation timestamp for expiration tracking
            $tokenData['created_at'] = time();
            
            // Get user data 
            $userData = $this->oauthService->getUserData($tokenData['access_token']);
            
            if (!$userData) {
                Log::error('Failed to get user data with access token');
                return redirect(config('oauth.frontend_url') . '/login?error=no_user_data');
            }
            
            // Format user data
            $formattedUser = [];
            foreach ($userData as $item) {
                $formattedUser[$item['Type']] = $item['Value'];
            }
            
            // Create a temporary token to verify the user
            $tempToken = bin2hex(random_bytes(32));
            
            // Store in cache for 5 minutes
            \Cache::put('oauth_temp_token:'.$tempToken, [
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'expires_in' => $tokenData['expires_in'] ?? 3600,
                'created_at' => $tokenData['created_at'],
                'user_data' => $formattedUser
            ], 300);
            
            // Clear the state cookie
            $clearCookie = cookie()->forget('oauth_state');
            
            // Log the redirect URL
            $redirectUrl = config('oauth.frontend_url') . '/auth?token=' . $tempToken;
            Log::info('Redirecting after successful auth', [
                'redirect_url' => $redirectUrl,
                'temp_token' => $tempToken,
                'frontend_url' => config('oauth.frontend_url'),
            ]);
            
            // Redirect with the temporary token
            return redirect($redirectUrl)->withCookie($clearCookie);
        } catch (\Exception $e) {
            Log::error('Exception during OAuth callback: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect(config('oauth.frontend_url') . '/login?error=callback_exception');
        }
    }

    /**
     * Exchange a temporary token for authentication data
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function exchangeToken(Request $request)
    {
        $tempToken = $request->input('token');
        
        if (!$tempToken) {
            return response()->json(['error' => 'No token provided'], 400);
        }
        
        $cacheKey = 'oauth_temp_token:'.$tempToken;
        $data = \Cache::get($cacheKey);
        
        if (!$data) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }
        
        // Remove from cache to prevent reuse
        \Cache::forget($cacheKey);
        
        // Return the necessary data
        return response()->json([
            'user' => $data['user_data'],
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in' => $data['expires_in'] ?? 3600,
            'token_time' => $data['created_at']
        ]);
    }

/**
 * Exchange authorization code for tokens
 *
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function exchangeCodeForToken(Request $request)
{
    Log::info('Token exchange request received', [
        'has_code' => $request->has('code'),
        'has_state' => $request->has('state'),
    ]);

    $code = $request->input('code');
    
    if (!$code) {
        return response()->json(['error' => 'No authorization code provided'], 400);
    }
    
    try {
        // Exchange the code for access token
        $tokenData = $this->oauthService->getAccessToken($code);
        
        if (!$tokenData || !isset($tokenData['access_token'])) {
            Log::error('Failed to obtain access token', [
                'token_data' => $tokenData ? 'exists but no access_token' : 'null',
            ]);
            
            return response()->json(['error' => 'Failed to obtain access token'], 401);
        }
        
        // Add creation timestamp for expiration tracking
        $tokenData['created_at'] = time();
        $tokenData['token_time'] = time();
        
        // Get user data to include in the response
        $userData = $this->oauthService->getUserData($tokenData['access_token']);
        
        // Format user data for the response
        $formattedUser = [];
        if ($userData) {
            foreach ($userData as $item) {
                $formattedUser[$item['Type']] = $item['Value'];
            }
        }
        
        // Log success
        Log::info('Token exchange successful', [
            'has_user_data' => !empty($formattedUser),
            'token_type' => $tokenData['token_type'] ?? 'unknown',
        ]);
        
        return response()->json([
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'expires_in' => $tokenData['expires_in'] ?? 3600,
            'token_time' => $tokenData['created_at'],
            'user' => $formattedUser
        ]);
    } catch (\Exception $e) {
        Log::error('Token exchange error: ' . $e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return response()->json(['error' => 'Failed to exchange code for token: ' . $e->getMessage()], 500);
    }
}

    /**
     * Refresh the access token
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refreshToken(Request $request)
    {
        $refreshToken = $request->input('refresh_token');
        
        if (!$refreshToken) {
            return response()->json(['error' => 'No refresh token provided'], 400);
        }
        
        try {
            // Refresh the token
            $newTokenData = $this->oauthService->refreshToken($refreshToken);
            
            if (!$newTokenData || !isset($newTokenData['access_token'])) {
                Log::error('Token refresh failed', [
                    'response' => $newTokenData ? 'empty_token' : 'null_response',
                ]);
                
                return response()->json(['error' => 'Failed to refresh token'], 401);
            }
            
            // Add the creation timestamp 
            $newTokenData['created_at'] = time();
            
            return response()->json([
                'access_token' => $newTokenData['access_token'],
                'refresh_token' => $newTokenData['refresh_token'] ?? null,
                'expires_in' => $newTokenData['expires_in'] ?? 3600,
                'token_time' => $newTokenData['created_at']
            ]);
        } catch (\Exception $e) {
            Log::error('Exception during token refresh: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json(['error' => 'An error occurred while refreshing your token'], 500);
        }
    }
    
    /**
     * Get user data using the access token
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserData(Request $request)
    {
        $token = null;
        
        // Check for token in Authorization header
        if ($request->hasHeader('Authorization')) {
            $authHeader = $request->header('Authorization');
            if (strpos($authHeader, 'Bearer ') === 0) {
                $token = substr($authHeader, 7);
            }
        }
        
        if (!$token) {
            return response()->json(['error' => 'No token provided'], 401);
        }
        
        try {
            // Fetch user data using the token
            $userData = $this->oauthService->getUserData($token);
            
            if (!$userData) {
                return response()->json(['error' => 'Invalid token or user not found'], 401);
            }
            
            // Format user data
            $formattedUser = [];
            foreach ($userData as $item) {
                $formattedUser[$item['Type']] = $item['Value'];
            }
            
            return response()->json($formattedUser);
        } catch (\Exception $e) {
            Log::error('Error in user API: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
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