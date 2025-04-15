<?php

return [
    /*
    |--------------------------------------------------------------------------
    | МУИС HUB API Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for connecting to the МУИС HUB API
    | and retrieving data for the thesis management system.
    |
    */

    // API endpoint for GraphQL queries
    'endpoint' => env('HUBAPI_ENDPOINT', 'https://tree.num.edu.mn/gateway'),

    // Default department ID to use if none specified
    'departments' => [
        'default' => env('HUBAPI_DEFAULT_DEPARTMENT', 'MCST'), // Default to МКУТ
    ],

    // Semester configuration
    'semester' => [
        'current' => env('HUBAPI_CURRENT_SEMESTER', '2025-1'), // Spring 2025
    ],

    // Course code for thesis
    'courses' => [
        'thesis_code' => env('HUBAPI_THESIS_COURSE', 'THES400'), // Бакалаврын судалгааны ажил
    ],

    // Caching options
    'cache' => [
        'enabled' => env('HUBAPI_CACHE_ENABLED', true),
        'ttl' => env('HUBAPI_CACHE_TTL', 3600), // Cache for 1 hour
    ],
];