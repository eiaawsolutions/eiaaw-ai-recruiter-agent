<?php

return [
    'infisical' => [
        'enabled'         => env('INFISICAL_RESOLVER_ENABLED', false),
        'site_url'        => env('INFISICAL_SITE_URL', 'https://app.infisical.com'),
        'client_id'       => env('INFISICAL_APP_CLIENT_ID'),
        'client_secret'   => env('INFISICAL_APP_CLIENT_SECRET'),
        'project_id'      => env('INFISICAL_PROJECT_ID'),
        'environment'     => env('INFISICAL_ENVIRONMENT', 'prod'),
        'cache_ttl'       => (int) env('INFISICAL_CACHE_TTL', 300),
        'request_timeout' => (int) env('INFISICAL_REQUEST_TIMEOUT', 5),
    ],

    'resolve' => [
        // Database
        'database.connections.pgsql.password',
        'database.redis.default.password',
        'database.redis.cache.password',

        // AI providers
        'services.anthropic.api_key',

        // Mailgun
        'services.mailgun.secret',
        'services.mailgun.webhook_signing_key',

        // Workforce handoff
        'services.workforce.api_key',
        'services.workforce.hmac_secret',

        // Calendar OAuth secrets
        'services.google.client_secret',
        'services.microsoft.client_secret',
    ],
];
