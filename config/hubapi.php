<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HUB API Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for МУИС's HUB API integration.
    |
    */
    
    // HUB API endpoint
    'endpoint' => env('HUBAPI_ENDPOINT', 'https://tree.num.edu.mn/gateway'),
    
    // Cache configuration
    'cache' => [
        'enabled' => env('HUBAPI_CACHE_ENABLED', true),
        'ttl' => env('HUBAPI_CACHE_TTL', 3600), // Cache TTL in seconds
    ],
    
    // Batch size for data synchronization
    'batch_size' => env('HUBAPI_BATCH_SIZE', 100),
    
    // Department filters
    'departments' => [
        'default' => env('HUBAPI_DEFAULT_DEPARTMENT', 'MCST'), // Default department code
    ],
    
    // Course filters
    'courses' => [
        'thesis_code' => env('HUBAPI_THESIS_COURSE', 'THES400'), // Course code for thesis
    ],
    
    // Semester filters
    'semester' => [
        'current' => env('HUBAPI_CURRENT_SEMESTER', '2025-1'), // Current semester code
    ],
];