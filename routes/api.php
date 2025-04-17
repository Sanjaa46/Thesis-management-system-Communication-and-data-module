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
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationTemplateController;
use App\Http\Controllers\NotificationSettingController;

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

// Notification routes accessible with token auth (not middleware group)
Route::middleware('auth.api.token')->group(function () {
    Route::get('/notifications/unread', [NotificationController::class, 'getUnread']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/subscribe', [NotificationController::class, 'subscribe']);
    Route::post('/notifications/unsubscribe', [NotificationController::class, 'unsubscribe']);
});

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

    // Other notification routes
    Route::post('/notifications', [NotificationController::class, 'store']);
    Route::post('/notifications/template', [NotificationController::class, 'sendTemplateNotification']);
    
    // Template management routes
    Route::get('/notification-templates', [NotificationTemplateController::class, 'index']);
    Route::post('/notification-templates', [NotificationTemplateController::class, 'store']);
    Route::get('/notification-templates/{id}', [NotificationTemplateController::class, 'show']);
    Route::put('/notification-templates/{id}', [NotificationTemplateController::class, 'update']);
    Route::delete('/notification-templates/{id}', [NotificationTemplateController::class, 'destroy']);
    Route::get('/notification-settings', [NotificationSettingController::class, 'index']);
    Route::post('/notification-settings', [NotificationSettingController::class, 'update']);
});