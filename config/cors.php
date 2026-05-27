<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))))),
    'allowed_origins_patterns' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', ''))))),
    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-API-Key',
        'X-CSRF-TOKEN',
        'X-Requested-With',
        'X-XSRF-TOKEN',
    ],
    'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-EIAAW-Signature'],
    'max_age' => 600,
    'supports_credentials' => true,
];
