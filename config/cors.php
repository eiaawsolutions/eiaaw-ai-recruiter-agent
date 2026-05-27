<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_filter(explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),
    'allowed_origins_patterns' => array_filter(explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', ''))),
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-EIAAW-Signature'],
    'max_age' => 0,
    'supports_credentials' => true,
];
