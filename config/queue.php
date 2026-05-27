<?php

return [
    'default'     => env('QUEUE_CONNECTION', 'database'),
    'connections' => [
        'sync'     => ['driver' => 'sync'],
        'database' => [
            'driver'         => 'database',
            'table'          => 'jobs',
            'queue'          => 'default',
            'retry_after'    => 180,
            'after_commit'   => false,
        ],
        'redis' => [
            'driver'      => 'redis',
            'connection'  => 'default',
            'queue'       => env('REDIS_QUEUE', 'default'),
            'retry_after' => 180,
            'block_for'   => null,
        ],
    ],
    'batching' => [
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table'    => 'job_batches',
    ],
    'failed' => [
        'driver'   => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table'    => 'failed_jobs',
    ],
];
