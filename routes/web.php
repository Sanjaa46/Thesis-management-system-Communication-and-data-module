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






//Test Hub api
Route::get('/test-api-connection', function () {
    $client = new Client();
    
    try {
        // Retrieve your token from the OAuth service 
        $oauthService = app()->make(\App\Services\OAuthService::class);
        $tokenData = $oauthService->getClientCredentialsToken();
        
        if (!$tokenData || !isset($tokenData['access_token'])) {
            return "Failed to obtain access token. Check logs for details.";
        }
        
        $token = $tokenData['access_token'];
        
        // Make the GraphQL request
        $response = $client->post(config('hubapi.endpoint', 'https://tree.num.edu.mn/gateway'), [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'query' => '{ hr_GetDepartments(clientId: "' . config('oauth.client_id') . '") { id name } }'
            ],
        ]);
        
        // Display the response 
        $result = json_decode($response->getBody(), true);
        
        echo "<h1>API Connection Test</h1>";
        echo "<h2>Successfully connected to the API</h2>";
        echo "<h3>Response:</h3>";
        echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
        
        return "";
    } catch (\Exception $e) {
        echo "<h1>API Connection Test - Error</h1>";
        echo "<h3>Error Message:</h3>";
        echo "<pre>" . $e->getMessage() . "</pre>";
        
        if (method_exists($e, 'getResponse') && $e->getResponse()) {
            echo "<h3>Response Body:</h3>";
            echo "<pre>" . (string) $e->getResponse()->getBody() . "</pre>";
        } else {
            echo "<p>No response body available</p>";
        }
        
        return "";
    }
});


Route::get('/test-oauth', function () {
    $clientId = config('oauth.client_id');
    $clientSecret = config('oauth.client_secret');
    $tokenEndpoint = config('oauth.token_endpoint');
    
    echo "<h1>OAuth Configuration Test</h1>";
    echo "<p>Client ID: " . (empty($clientId) ? 'MISSING' : 'SET') . "</p>";
    echo "<p>Client Secret: " . (empty($clientSecret) ? 'MISSING' : 'SET') . "</p>";
    echo "<p>Token Endpoint: " . $tokenEndpoint . "</p>";
    
    $client = new \GuzzleHttp\Client([
        'verify' => false, // Only use this for testing if needed
    ]);
    
    try {
        echo "<h2>Attempting to get token...</h2>";
        
        $response = $client->post($tokenEndpoint, [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ],
        ]);
        
        $tokenData = json_decode($response->getBody(), true);
        
        echo "<h2>Success!</h2>";
        echo "<pre>";
        // Only show token type and expires_in, not the actual token for security
        echo "Token Type: " . ($tokenData['token_type'] ?? 'not set') . "\n";
        echo "Expires In: " . ($tokenData['expires_in'] ?? 'not set') . "\n";
        echo "Access Token: " . (isset($tokenData['access_token']) ? '[PRESENT]' : '[MISSING]') . "\n";
        echo "</pre>";
    } catch (\Exception $e) {
        echo "<h2>Error</h2>";
        echo "<p>" . $e->getMessage() . "</p>";
        
        if (method_exists($e, 'getResponse') && $e->getResponse()) {
            echo "<h3>Response:</h3>";
            echo "<pre>" . (string) $e->getResponse()->getBody() . "</pre>";
        }
    }
    
    return "";
});