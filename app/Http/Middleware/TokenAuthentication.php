<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\OAuthService;
use Illuminate\Support\Facades\Log;

class TokenAuthentication
{
    protected $oauthService;

    public function __construct(OAuthService $oauthService)
    {
        $this->oauthService = $oauthService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check for Authorization header
        if (!$request->hasHeader('Authorization')) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication token is missing'
            ], 401);
        }

        $authHeader = $request->header('Authorization');
        
        // Ensure it's a Bearer token
        if (strpos($authHeader, 'Bearer ') !== 0) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid authentication format'
            ], 401);
        }

        // Extract the token
        $token = substr($authHeader, 7);
        
        if (empty($token)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication token is empty'
            ], 401);
        }

        try {
            // Verify the token by getting user data
            $userData = $this->oauthService->getUserData($token);
            
            if (!$userData) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Invalid or expired token'
                ], 401);
            }

            // Add user data to the request for controllers to use
            $request->attributes->add(['user_data' => $userData]);
            
            return $next($request);
        } catch (\Exception $e) {
            Log::error('Token validation failed: ' . $e->getMessage(), [
                'exception' => get_class($e),
            ]);
            
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication failed'
            ], 401);
        }
    }
}