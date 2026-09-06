<?php

$allowedOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => trim($origin),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'https://www.rakez.com.sa,https://rakez.com.sa'))
)));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    // Local browser development may use any localhost / 127.0.0.1 port.
    // Production origins must be explicit in CORS_ALLOWED_ORIGINS.
    'allowed_origins_patterns' => env('APP_ENV', 'production') === 'local' ? [
        '/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/',
    ] : [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition', 'Content-Length', 'Content-Type'],

    'max_age' => (int) env('CORS_MAX_AGE', 600),

    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),
];
