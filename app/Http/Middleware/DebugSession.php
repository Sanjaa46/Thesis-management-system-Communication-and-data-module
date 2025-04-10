<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DebugSession
{
    public function handle(Request $request, Closure $next)
    {
        // Log session information before processing the request
        Log::info('Request session info', [
            'path' => $request->path(),
            'session_id' => session()->getId(),
            'has_token' => session()->has(config('oauth.token_session_key')),
            'has_user' => session()->has('oauth_user'),
            'all_cookies' => $request->cookies->all(),
        ]);
        
        $response = $next($request);
        
        // Log session after processing
        Log::info('Response session info', [
            'session_id' => session()->getId(),
            'status' => $response->getStatusCode(),
        ]);
        
        return $response;
    }
}