<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{
    protected $roleService;
    
    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }
    
    /**
     * Get the authenticated user's role information
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserRole(Request $request)
    {
        // Get the token from the session
        $tokenData = session(config('oauth.token_session_key'));
        
        // Log the session status for debugging
        Log::info('Session data in role API', [
            'session_id' => session()->getId(),
            'has_token' => session()->has(config('oauth.token_session_key')),
        ]);
        
        if (!$tokenData || !isset($tokenData['access_token'])) {
            // Try to get token from Authorization header
            $authHeader = $request->header('Authorization');
            $accessToken = null;
            
            if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
                $accessToken = substr($authHeader, 7);
            }
            
            if (!$accessToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'No authentication token found'
                ], 401);
            }
        } else {
            $accessToken = $tokenData['access_token'];
        }
        
        try {
            // Get role information from the token
            $roleInfo = $this->roleService->getUserRoleInfo($accessToken);
            
            if (!$roleInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to get role information'
                ], 500);
            }
            
            return response()->json([
                'success' => true,
                'data' => $roleInfo
            ]);
        } catch (\Exception $e) {
            Log::error('Exception in user role API: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false, 
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }
}