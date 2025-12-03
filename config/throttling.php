<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Redis Throttling Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Redis-based rate limiting throughout the application.
    |
    */

    'default' => [
        'max_attempts' => env('THROTTLE_MAX_ATTEMPTS', 2),
        'decay_seconds' => env('THROTTLE_DECAY_SECONDS', 1),
    ],

    'notifications' => [
        'send' => [
            'max_attempts' => env('NOTIFICATION_SEND_MAX_ATTEMPTS', 2),
            'decay_seconds' => env('NOTIFICATION_SEND_DECAY_SECONDS', 1),
        ],
        'bulk_send' => [
            'max_attempts' => env('NOTIFICATION_BULK_SEND_MAX_ATTEMPTS', 1),
            'decay_seconds' => env('NOTIFICATION_BULK_SEND_DECAY_SECONDS', 2),
        ],
    ],

    'wasender' => [
        'max_attempts' => env('WASENDER_MAX_ATTEMPTS', 5),
        'decay_seconds' => env('WASENDER_DECAY_SECONDS', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Connection
    |--------------------------------------------------------------------------
    |
    | Redis connection to use for throttling. Uses default Redis connection
    | if not specified.
    |
    */
    'redis_connection' => env('THROTTLE_REDIS_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Key Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for throttling keys in Redis to avoid conflicts.
    |
    */
    'key_prefix' => env('THROTTLE_KEY_PREFIX', 'throttle'),
];