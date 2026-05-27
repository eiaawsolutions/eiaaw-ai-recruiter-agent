<?php

return [
    'anthropic' => [
        'api_key'   => env('ANTHROPIC_API_KEY'),
        'base_url'  => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'reasoning_model' => env('RECRUITER_MODEL_REASONING', 'claude-opus-4-7'),
        'draft_model'     => env('RECRUITER_MODEL_DRAFT', 'claude-haiku-4-5-20251001'),
        'timeout' => (int) env('ANTHROPIC_TIMEOUT', 120),
    ],

    'resend' => [
        // Outbound API key. In the EIAAW shared Infisical project this lives
        // as `RESEND_API` — RESEND_API_KEY is the fallback name used outside
        // Infisical-resolved environments.
        'key' => env('RESEND_API_KEY', env('RESEND_API')),

        // Resend webhook signing secret (svix-style). Found in Resend
        // Dashboard → Webhooks → your endpoint → Signing secret. Starts
        // with `whsec_`.
        'webhook_signing_secret' => env('RESEND_WEBHOOK_SIGNING_SECRET'),
    ],

    'workforce' => [
        'base_url'    => env('WORKFORCE_BASE_URL', 'https://ep.eiaawsolutions.com'),
        'api_key'     => env('WORKFORCE_API_KEY'),
        'hmac_secret' => env('WORKFORCE_HMAC_SECRET'),
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],

    'microsoft' => [
        'client_id'     => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
    ],

    'recruiter' => [
        'require_approval'           => (bool) env('RECRUITER_REQUIRE_APPROVAL', true),
        'max_sourced_per_job'        => (int)  env('RECRUITER_MAX_SOURCED_PER_JOB', 50),
        'min_verification_sources'   => (int)  env('RECRUITER_MIN_VERIFICATION_SOURCES', 1),
        'discard_low_confidence'     => (bool) env('RECRUITER_DISCARD_LOW_CONFIDENCE', true),
        'webhook_retry_max'          => (int)  env('RECRUITER_WEBHOOK_RETRY_MAX', 8),
        'webhook_signature_header'   => env('RECRUITER_WEBHOOK_SIGNATURE_HEADER', 'X-EIAAW-Signature'),
        'api_rate_limit_per_min'     => (int)  env('RECRUITER_API_RATE_LIMIT_PER_MIN', 120),
    ],
];
