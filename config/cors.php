<?php

return [
    'paths' => ['api/*', 'oauth/*', 'sanctum/csrf-cookie', '*'],
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:4000')],
    'allowed_methods' => ['*'],
    'allowed_headers' => [
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'Origin',
        'Accept',
    ],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];

// return [
//     /*
//     |--------------------------------------------------------------------------
//     | Cross-Origin Resource Sharing (CORS) Configuration
//     |--------------------------------------------------------------------------
//     |
//     | Here you may configure your settings for cross-origin resource sharing
//     | or "CORS". This determines what cross-origin operations may execute
//     | in web browsers. You are free to adjust these settings as needed.
//     |
//     */

//     'paths' => ['api/*', 'oauth/*', 'sanctum/csrf-cookie', '*'],
    
//     'allowed_origins' => [env('CORS_ALLOWED_ORIGINS', 'http://localhost:4000')],
    
//     'allowed_origins_patterns' => [],
    
//     'allowed_methods' => ['*'],
    
//     'allowed_headers' => [
//         'X-CSRF-TOKEN',
//         'Content-Type',
//         'X-Requested-With',
//         'Authorization',
//         'Origin',
//         'Accept',
//     ],
    
//     'exposed_headers' => [],
    
//     'max_age' => 0,
    
//     // Set to false for token-based authentication
//     'supports_credentials' => false,
// ];