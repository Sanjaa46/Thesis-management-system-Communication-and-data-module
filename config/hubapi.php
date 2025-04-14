<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HUB API Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for МУИС's HUB API integration.
    |
    */

    // HUB API Endpoint
    'endpoint' => env('HUBAPI_ENDPOINT', 'https://tree.num.edu.mn/gateway'),
    
    // Department specific settings
    'departments' => [
        'default' => env('HUBAPI_DEFAULT_DEPARTMENT', 'MCST'), // Default department ID to use
    ],
    
    // Course specific settings
    'courses' => [
        'thesis_code' => env('HUBAPI_THESIS_COURSE', 'THES400'), // Thesis course code
    ],
    
    // Semester settings
    'semester' => [
        'current' => env('HUBAPI_CURRENT_SEMESTER', '2025-1'), // Current semester (format: YYYY-N)
    ],
    
    // Caching settings
    'cache' => [
        'enabled' => env('HUBAPI_CACHE_ENABLED', true),
        'ttl' => env('HUBAPI_CACHE_TTL', 3600), // Cache time-to-live in seconds
    ],
];