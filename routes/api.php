<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProposalFormController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TopicRequestController;
use App\Http\Controllers\TopicResponseController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Api\DataSyncController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\GraphQLTestController;

// OAuth token exchange endpoint
Route::post('/oauth/exchange-token', [OAuthController::class, 'exchangeToken']);

// OAuth refresh token endpoint
Route::post('/oauth/refresh-token', [OAuthController::class, 'refreshToken']);

// User data endpoint (protected by bearer token)
Route::get('/user', [OAuthController::class, 'getUserData']);

// Public API routes
Route::prefix('public')->group(function () {
    // Add any routes here that should be accessible without authentication
});

Route::post('/oauth/token', [OAuthController::class, 'exchangeCodeForToken']);

// Protected API routes
Route::middleware('auth.api.token')->group(function () {
    Route::get('/user/role', [RoleController::class, 'getUserRole']);

    // Data Sync Routes
    Route::prefix('sync')->group(function () {
        Route::post('/departments', [DataSyncController::class, 'syncDepartments']);
        Route::post('/teachers', [DataSyncController::class, 'syncTeachers']);
        Route::post('/students', [DataSyncController::class, 'syncStudents']);
        Route::post('/all', [DataSyncController::class, 'syncAll']);
    });

    // GraphQL Testing Routes
    Route::prefix('graphql-test')->group(function () {
        Route::get('/connection', [GraphQLTestController::class, 'testConnection']);
        Route::get('/departments', [GraphQLTestController::class, 'testDepartments']);
        Route::get('/teachers', [GraphQLTestController::class, 'testTeachers']);
        Route::get('/students', [GraphQLTestController::class, 'testStudents']);
    });

    Route::get('/proposalform', [ProposalFormController::class, 'index']);
    Route::post('/proposalform', [ProposalFormController::class, 'update']);
    
    // Topic related routes
    Route::post('/topic/store', [TopicController::class, 'store']);
    
    
    // Other protected routes
    Route::get('/teacher/{id}', [TeacherController::class, 'show']);
    Route::get('/department/{id}', [DepartmentController::class, 'show']);
    Route::get('/topic_requests_teacher', [TopicRequestController::class, 'getRequestedTopicByTeacher']);
    
    // Student routes
    Route::post('/topic/storestudent', [TopicController::class, 'storestudent']);
    Route::post('/topic-requests', [TopicRequestController::class, 'store']);
    Route::get('/topics/draftstudent', [TopicController::class, 'getDraftTopicsByStudent']);
    Route::get('/topics/draftteacher', [TopicController::class, 'getDraftTopicsByTeacher']);
    Route::get('/topic_confirmed', [TopicRequestController::class, 'getConfirmedTopicOnStudent']);
    
    // Supervisor routes
    Route::get('/topics/submittedby/{type}', [TopicController::class, 'getSubmittedTopicsByType']);
    Route::post('/topic-response', [TopicResponseController::class, 'store']);
    Route::get('/topics/reviewedtopicList', [TopicController::class, 'getRefusedOrApprovedTopics']);
    
    // Teacher routes
    Route::post('/topic/storeteacher', [TopicController::class, 'storeteacher']);
    Route::post('/topic-requestsbyteacher', [TopicRequestController::class, 'storebyteacher']);
    Route::get('/api/department', [DepartmentController::class, 'index']);
    Route::get('/topic_requests', [TopicRequestController::class, 'index']);
    Route::post('/topic_confirm', [TopicController::class, 'confirmTopic']);
    Route::post('/topic_decline', [TopicController::class, 'declineTopic']);
    Route::get('/topics_confirmed', [TopicRequestController::class, 'getConfirmedTopics']);
    Route::get('/topics/checkedtopicsbystud', [TopicController::class, 'getCheckedTopicsByStud']);
    Route::get('/topics/checkedtopics', [TopicController::class, 'getCheckedTopics']);
    
    // Default routes
    Route::get('/topics/topiclistproposedbyuser', [TopicController::class, 'getTopicListProposedByUser']);
    Route::apiResource('topics', TopicController::class);
    
    // Students API
    Route::get('/students/all', [StudentController::class, 'index']);
});

Route::middleware('auth.api.token')->group(function () {
    // Send notifications
    Route::post('/notifications', [App\Http\Controllers\NotificationController::class, 'store']);
    
    // Push notification subscription management
    Route::post('/notifications/subscribe', [App\Http\Controllers\NotificationController::class, 'subscribe']);
    Route::post('/notifications/unsubscribe', [App\Http\Controllers\NotificationController::class, 'unsubscribe']);
    
    // Get unread notifications
    Route::get('/notifications/unread', [App\Http\Controllers\NotificationController::class, 'getUnread']);
    
    // Mark notification as read
    Route::post('/notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead']);
});



// Test push notification endpoint for authenticated users
Route::middleware('auth.api.token')->post('/notifications/test', function (Request $request) {
    $user = $request->user();
    
    // If user is not found, return error
    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'User not authenticated'
        ], 401);
    }
    
    // Log the test request
    Log::info('Frontend notification test requested', [
        'user' => $user->sisi_id ?? $user->id
    ]);
    
    $notificationService = app(\App\Services\NotificationService::class);
    
    // Get parameters from request or use defaults
    $title = $request->input('title', 'Test Notification');
    $content = $request->input('content', 'This is a test notification from your thesis management system');
    $url = $request->input('url', url('/'));
    
    // Create the notification
    $result = $notificationService->sendCombinedNotification(
        $user->sisi_id ?? $user->id, // User ID
        $user->nummail ?? $user->mail ?? $user->email, // User email
        $title,
        $content,
        null, // No schedule (immediate)
        $url
    );
    
    return response()->json([
        'success' => true,
        'message' => 'Test notification sent',
        'result' => $result
    ]);
});


// Token testing route
Route::get('/test-token', [App\Http\Controllers\TokenTestController::class, 'testToken']);

// HUB API proxy endpoint
Route::post('/hub-proxy', [App\Http\Controllers\HubProxyController::class, 'proxyRequest'])->middleware('auth.api.token');