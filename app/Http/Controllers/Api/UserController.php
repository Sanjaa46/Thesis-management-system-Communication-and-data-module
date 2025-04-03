<?php

namespace App\Http\Controllers\Api;

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
        // Get user from OAuth token
        $tokenData = session(config('oauth.token_session_key'));
        
        if (!$tokenData || !isset($tokenData['access_token'])) {
            return response()->json(null, 401);
        }
        
        // Fetch the user data from the OAuth service
        $oauthService = app(\App\Services\OAuthService::class);
        $userData = $oauthService->getUserData($tokenData['access_token']);
        
        return response()->json($userData);
    }
}