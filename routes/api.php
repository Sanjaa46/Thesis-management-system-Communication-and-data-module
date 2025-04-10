<?php

use App\Http\Controllers\ProposalFormController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TopicRequestController;
use App\Http\Controllers\Api\UserController;


Route::get('/proposalform', [ProposalFormController::class, 'index']);
Route::post('/proposalform', [ProposalFormController::class, 'update']);

//new
Route::apiResource('topics', TopicController::class);
Route::post('/topic/store', [TopicController::class, 'store']);


Route::get('/teacher/{id}', [TeacherController::class, 'show']);
Route::get('/department/{id}', [DepartmentController::class, 'show']);


Route::get('/topic_requests_teacher', [TopicRequestController::class, 'getRequestedTopicByTeacher']);

Route::middleware('oauth')->get('/user', [UserController::class, 'user']);


Route::middleware('oauth')->get('/token', function (Request $request) {
    $tokenData = session(config('oauth.token_session_key'));
    
    if (!$tokenData || !isset($tokenData['access_token'])) {
        return response()->json(['error' => 'No token available'], 401);
    }
    
    return response()->json([
        'access_token' => $tokenData['access_token'],
        'expires_in' => $tokenData['expires_in'],
    ]);
});

Route::get('/debug-token', function () {
    return [
        'has_token' => session()->has(config('oauth.token_session_key')),
        'session_id' => session()->getId(),
        'user_in_session' => session()->has('oauth_user')
    ];
});


Route::get('/test-session', function (Request $request) {
    // Set a test value in session
    session(['test_value' => 'This is a test']);
    session()->save();
    
    return response()->json([
        'message' => 'Session value set',
        'session_id' => session()->getId()
    ]);
});

Route::get('/check-session', function (Request $request) {
    return response()->json([
        'has_test_value' => session()->has('test_value'),
        'test_value' => session('test_value'),
        'session_id' => session()->getId(),
        'all_session_data' => session()->all()
    ]);
});


Route::get('/debug-full-token', function() {
    $tokenData = session(config('oauth.token_session_key'));
    
    // Return safe version (hide actual token value)
    return response()->json([
        'has_token' => !empty($tokenData),
        'token_type' => $tokenData['token_type'] ?? null,
        'expires_in' => $tokenData['expires_in'] ?? null,
        'created_at' => $tokenData['created_at'] ?? null,
        'session_id' => session()->getId(),
        'all_session_keys' => array_keys(session()->all())
    ]);
});

Route::get('/restore-session/{id}', function ($id) {
    // Start session with the given ID
    session()->setId($id);
    session()->start();
    
    return response()->json([
        'success' => true,
        'session_id' => session()->getId(),
        'has_token' => session()->has(config('oauth.token_session_key')),
        'user_in_session' => session()->has('oauth_user'),
    ]);
});



Route::get('/current-user', function (Request $request) {
    $tokenData = session(config('oauth.token_session_key'));
    $userData = session('oauth_user');
    
    if (!$tokenData || !isset($tokenData['access_token'])) {
        return response()->json([
            'authenticated' => false,
            'session_id' => session()->getId(),
            'has_token' => false
        ]);
    }
    
    // Format user data
    $formattedUser = [];
    if ($userData) {
        foreach ($userData as $item) {
            $formattedUser[$item['Type']] = $item['Value'];
        }
    }
    
    return response()->json([
        'authenticated' => true,
        'user' => $formattedUser,
        'session_id' => session()->getId()
    ]);
});



Route::get('/user', function (Request $request) {
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
        $oauthService = app(\App\Services\OAuthService::class);
        $userData = $oauthService->getUserData($token);
        
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
        \Log::error('Error in user API: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 500);
    }
});


Route::get('/set-test-cookie', function(Request $request) {
    $cookie = cookie('test_cookie', 'test_value', 60, '/', null, false, false, false, 'lax');
    return response()->json(['message' => 'Cookie set', 'session_id' => session()->getId()])->withCookie($cookie);
});

Route::get('/check-test-cookie', function(Request $request) {
    return response()->json([
        'has_cookie' => $request->hasCookie('test_cookie'),
        'cookie_value' => $request->cookie('test_cookie'),
        'session_id' => session()->getId()
    ]);
});


Route::get('/token-exchange', function (Request $request) {
    // Get the provided session ID
    $sessionId = $request->input('session_id');
    
    if ($sessionId) {
        // Try to resume the session
        session()->setId($sessionId);
        session()->start();
        
        Log::info('Session restored', [
            'provided_id' => $sessionId,
            'current_id' => session()->getId(),
            'has_token' => session()->has(config('oauth.token_session_key'))
        ]);
    }
    
    // Return current session status
    return response()->json([
        'session_id' => session()->getId(),
        'has_token' => session()->has(config('oauth.token_session_key')),
        'token_type' => session(config('oauth.token_session_key').'.token_type', null),
    ]);
});

Route::post('/exchange-token', function (Request $request) {
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
    ]);
});