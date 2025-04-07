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

Route::middleware('auth:sanctum')->get('/user', [UserController::class, 'user']);


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
