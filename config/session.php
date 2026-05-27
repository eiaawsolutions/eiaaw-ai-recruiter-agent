<?php

return [
    'driver'          => env('SESSION_DRIVER', 'database'),
    'lifetime'        => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt'         => false,
    'files'           => storage_path('framework/sessions'),
    'connection'      => env('SESSION_CONNECTION'),
    'table'           => env('SESSION_TABLE', 'sessions'),
    'store'           => env('SESSION_STORE'),
    'lottery'         => [2, 100],
    'cookie'          => env('SESSION_COOKIE', 'recruiter_session'),
    'path'            => env('SESSION_PATH', '/'),
    'domain'          => env('SESSION_DOMAIN'),
    // Default ON in any non-local environment. The explicit env var still wins
    // when the operator needs to override (e.g. behind a TLS-terminating proxy
    // that strips the secure flag).
    'secure'          => env('SESSION_SECURE_COOKIE', env('APP_ENV', 'production') !== 'local'),
    'http_only'       => true,
    'same_site'       => 'lax',
];
