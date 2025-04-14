<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TokenTestController extends Controller
{
    /**
     * Test if a valid token is present
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function testToken(Request $request)
    {
        $bearerToken = $request->bearerToken();
        $sessionToken = session()->has(config('oauth.token_session_key')) ? 
            session(config('oauth.token_session_key'))['access_token'] : null;
        
        return response()->json([
            'has_bearer_token' => !empty($bearerToken),
            'has_session_token' => !empty($sessionToken),
            'token_sources' => [
                'authorization_header' => !empty($bearerToken) ? 'Present' : 'Not present',
                'session' => !empty($sessionToken) ? 'Present' : 'Not present',
            ]
        ]);
    }
}