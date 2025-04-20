<?php

use Illuminate\Support\Facades\Route;
use GuzzleHttp\Client;

use App\Models\ProposalForm;
use App\Http\Controllers\ProposalFormController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\SupervisorController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\TopicDetailController;
use App\Http\Controllers\TopicRequestController;
use App\Http\Controllers\TopicResponseController;
use App\Http\Controllers\Auth\OAuthController;

use App\Services\HubApiService;
use App\Http\Controllers\DataSyncController;

//department
Route::get('/proposalform', [ProposalFormController::class, 'index']);
Route::post('/proposalform', [ProposalFormController::class, 'update']);
Route::get('/students/all', [StudentController::class, 'index']);

//student
Route::post('/topic/store', [TopicController::class, 'storestudent']);
Route::post('/topic-requests', [TopicRequestController::class, 'store']);
Route::get('/topics/draftstudent', [TopicController::class, 'getDraftTopicsByStudent']);
Route::get('/topics/draftteacher', [TopicController::class, 'getDraftTopicsByTeacher']);
Route::get('/topic_confirmed', [TopicRequestController::class, 'getConfirmedTopicOnStudent']);
Route::get('/topics/checkedtopics', [TopicController::class, 'getCheckedTopics']);
Route::get('/teacher/{id}', [TeacherController::class, 'show']);
Route::get('/department/{id}', [DepartmentController::class, 'show']);
Route::get('/topic_requests_teacher', [TopicRequestController::class, 'getRequestedTopicByTeacher']);

//supervisor
Route::get('/topics/submittedby/{type}', [TopicController::class, 'getSubmittedTopicsByType']);
Route::post('/topic-response', [TopicResponseController::class, 'store']);
Route::get('/topics/reviewedtopicList', [TopicController::class, 'getRefusedOrApprovedTopics']);


//teacher
Route::post('/topic/storeteacher', [TopicController::class, 'storeteacher']);
Route::post('/topic-requestsbyteacher', [TopicRequestController::class, 'storebyteacher']);
Route::get('/api/department', [DepartmentController::class, 'index']);
Route::get('/topic_requests', [TopicRequestController::class, 'index']);
Route::post('/topic_confirm', [TopicController::class, 'confirmTopic']);
Route::post('/topic_decline', [TopicController::class, 'declineTopic']);
Route::get('/topics_confirmed', [TopicRequestController::class, 'getConfirmedTopics']);
Route::get('/topics/checkedtopicsbystud', [TopicController::class, 'getCheckedTopicsByStud']);

//default
Route::get('/topics/topiclistproposedbyuser', [TopicController::class, 'getTopicListProposedByUser']);




// OAuth Authentication Routes
Route::get('/oauth/redirect', [OAuthController::class, 'redirectToProvider'])->name('oauth.redirect');
Route::get('/auth', [OAuthController::class, 'handleProviderCallback'])->name('oauth.callback');
Route::post('/api/oauth/exchange-token', [OAuthController::class, 'exchangeToken']);
Route::post('/api/oauth/refresh-token', [OAuthController::class, 'refreshToken']);
Route::get('/api/user', [OAuthController::class, 'getUserData']);

// Example of protected route using OAuth middleware
Route::middleware(['oauth'])->group(function () {
    Route::get('/protected-page', function () {
        $userData = session('oauth_user');
        return view('protected-page', ['userData' => $userData]);
    })->name('protected-page');
});







// GraphQL testing routes (require login)
Route::middleware(['require.token'])->prefix('graphql-test')->group(function () {
    Route::get('/connection', [App\Http\Controllers\GraphQLTestController::class, 'testConnection']);
    Route::get('/departments', [App\Http\Controllers\GraphQLTestController::class, 'testDepartments']);
    Route::get('/teachers', [App\Http\Controllers\GraphQLTestController::class, 'testTeachers']);
    Route::get('/students', [App\Http\Controllers\GraphQLTestController::class, 'testStudents']);
});




Route::get('/test-push', function () {
    // Get authenticated user or first student
    $user = auth()->user() ?? \App\Models\Student::first();
    
    // Get the user ID
    $userId = $user->sisi_id ?? $user->id;
    
    // Log which user we're using
    \Illuminate\Support\Facades\Log::info('Testing push notification for user', [
        'user' => $user,
        'user_id_used' => $userId
    ]);
    
    // Check if the user has a subscription
    $subscription = \App\Models\PushSubscription::where('user_id', $userId)->first();
    
    if (!$subscription) {
        return "No subscription found for user ID: $userId. Please subscribe first.";
    }
    
    $notificationService = app(\App\Services\NotificationService::class);
    
    $result = $notificationService->storePushNotification(
        $userId,
        'Test Notification',
        'This is a test notification from your thesis management system',
        null,
        url('/')
    );
    
    if ($result) {
        return "Notification queued with ID: $result. Check your browser notifications!";
    } else {
        return "Failed to create notification. Check logs for details.";
    }
});


Route::get('/subscribe-push', function () {
    return view('subscribe-push');
});








Route::get('/test-email', function() {
    $notificationService = app(\App\Services\NotificationService::class);
    
    $result = $notificationService->sendEmailNotification(
        '21b1num0435@stud.num.edu.mn',  // Replace with your test email
        'Test Email Notification',
        'This is a test email notification from your thesis management system.',
        ['url' => 'http://localhost:4000']
    );
    
    return [
        'success' => $result,
        'message' => $result ? 'Email sent successfully' : 'Failed to send email'
    ];
});











Route::get('/test-hub-api', function (HubApiService $hubApiService) {
    // Test departments
    $departments = $hubApiService->getDepartments();
    dump('Departments:', $departments);
    
    // Test teachers
    $teachers = $hubApiService->getTeachers();
    dump('Teachers:', $teachers);
    
    // Test students
    $students = $hubApiService->getStudentsInfo();
    dump('Students:', $students);
    
    return 'Check the dumped data above';
});








Route::get('/sync/departments', [DataSyncController::class, 'syncDepartments']);
Route::get('/sync/teachers', [DataSyncController::class, 'syncTeachers']);
Route::get('/sync/students', [DataSyncController::class, 'syncStudents']);
Route::get('/sync/all', [DataSyncController::class, 'syncAll']);