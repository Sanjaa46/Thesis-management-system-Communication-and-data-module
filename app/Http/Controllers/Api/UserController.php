<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Log;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Get the authenticated user's information
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(Request $request)
    {
        // Get the tokens from the session
        $tokenData = session(config('oauth.token_session_key'));
        
        // Log the session status for debugging
        \Log::info('Session data in user API', [
            'session_id' => session()->getId(),
            'has_token' => session()->has(config('oauth.token_session_key')),
        ]);
        Log::debug('Session data in user method', [
            'session_id' => session()->getId(),
            'all_session_data' => session()->all()
        ]);
        
        if (!$tokenData || !isset($tokenData['access_token'])) {
            return response()->json(['authenticated' => false], 401);
        }
        
        try {
            // Check if token is expired
            if (isset($tokenData['expires_in']) && isset($tokenData['created_at'])) {
                $expiresAt = $tokenData['created_at'] + $tokenData['expires_in'];
                
                if (time() >= $expiresAt) {
                    \Log::info('Token has expired, attempting to refresh');
                    
                    if (isset($tokenData['refresh_token'])) {
                        $oauthService = app(\App\Services\OAuthService::class);
                        $newTokenData = $oauthService->refreshToken($tokenData['refresh_token']);
                        
                        if ($newTokenData && isset($newTokenData['access_token'])) {
                            $newTokenData['created_at'] = time();
                            session([config('oauth.token_session_key') => $newTokenData]);
                            $tokenData = $newTokenData;
                            \Log::info('Token refreshed successfully');
                        } else {
                            \Log::error('Token refresh failed');
                            return response()->json(['authenticated' => false, 'error' => 'Token expired and refresh failed'], 401);
                        }
                    } else {
                        \Log::error('No refresh token available');
                        return response()->json(['authenticated' => false, 'error' => 'Token expired and no refresh token available'], 401);
                    }
                }
            }
            
            // Fetch the user data from the OAuth service
            $oauthService = app(\App\Services\OAuthService::class);
            $userData = $oauthService->getUserData($tokenData['access_token']);
            
            if (!$userData) {
                \Log::error('Failed to fetch user data from OAuth service');
                return response()->json(['authenticated' => false], 401);
            }
            
            // Format the user data for the frontend
            $formattedUser = [];
            foreach ($userData as $item) {
                $formattedUser[$item['Type']] = $item['Value'];
            }
            
            // For compatibility with your frontend's user role determination
            // You'll need to adjust this based on your specific requirements
            if (!isset($formattedUser['email'])) {
                // Create a synthetic email from username or other fields
                $username = $formattedUser['username'] ?? 'user';
                $formattedUser['email'] = $username . '@num.edu.mn';
            }
            
            return response()->json($formattedUser);
        } catch (\Exception $e) {
            \Log::error('Error in user API: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}