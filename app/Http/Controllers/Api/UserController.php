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
        // Instead of redirecting, just check if we have a valid session
        $tokenData = session(config('oauth.token_session_key'));
        
        if (!$tokenData || !isset($tokenData['access_token'])) {
            return response()->json(['authenticated' => false], 401);
        }
        
        try {
            // Fetch the user data from the OAuth service
            $oauthService = app(\App\Services\OAuthService::class);
            $userData = $oauthService->getUserData($tokenData['access_token']);
            
            if (!$userData) {
                return response()->json(['authenticated' => false], 401);
            }
            
            return response()->json($userData);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}