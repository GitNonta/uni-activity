<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', 'http://127.0.0.1:8080,http://localhost:8080,http://192.168.1.222:8080,http://127.0.0.1:8000,http://localhost:8000'))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'X-Auth-Token', 'Origin', 'Authorization', 'X-Requested-With', 'Accept', 'X-CSRF-TOKEN', 'X-API-Key'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
