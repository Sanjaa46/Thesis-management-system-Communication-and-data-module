<?php

$allowedOrigins = explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:4000'));

return [
    'paths' => ['api/*', 'oauth/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];