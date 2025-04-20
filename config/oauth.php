<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OAuth Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for OAuth 2.0 authentication.
    |
    */

    // OAuth Server Endpoints
    'authorization_endpoint' => env('OAUTH_AUTHORIZATION_ENDPOINT', 'https://auth.num.edu.mn/oauth2/oauth/authorize'),
    'token_endpoint' => env('OAUTH_TOKEN_ENDPOINT', 'https://auth.num.edu.mn/oauth2/oauth/token'),
    'resource_endpoint' => env('OAUTH_RESOURCE_ENDPOINT', 'https://auth.num.edu.mn/resource/me'),
    
    // OAuth Client Credentials
    'client_id' => env('OAUTH_CLIENT_ID', ''),
    'client_secret' => env('OAUTH_CLIENT_SECRET', ''),
    
    // Redirect URI after authorization
    'redirect_uri' => env('OAUTH_REDIRECT_URI', 'http://localhost:4000/auth'),
    
    // OAuth scopes (space-separated)
    'scopes' => env('OAUTH_SCOPES', ''),
    
    // Whether to verify SSL certificates
    'verify_ssl' => env('OAUTH_VERIFY_SSL', true),
    
    // Default grant type to use
    'default_grant_type' => env('OAUTH_DEFAULT_GRANT_TYPE', 'authorization_code'),
    
    // Session key for storing tokens
    'token_session_key' => 'oauth_tokens',
    
    // Frontend URL for redirects
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:4000'),
    
    // Frontend routes
    'frontend_routes' => [
        'login' => '/login',
        'auth' => '/auth',
        'home' => '/',
        'dashboard' => '/dashboard',
    ],
    
    // Token expiration buffer time in seconds
    // Refresh tokens this many seconds before they actually expire
    'token_refresh_buffer' => env('OAUTH_TOKEN_REFRESH_BUFFER', 60),






    'hub_api_endpoint' => env('HUB_API_ENDPOINT', 'http://localhost:8080/graphql'),
];