<?php

$allowedOrigins = explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:4000'));

return [
    'paths' => ['api/*', 'oauth/*', 'sanctum/csrf-cookie', '*'],  // Include all paths
    'allowed_origins' => ['http://localhost:4000'],
    'allowed_methods' => ['*'],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];