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

    /*
    |--------------------------------------------------------------------------
    | Provider-Specific Rate Limits
    |--------------------------------------------------------------------------
    |
    | Configure different rate limits for each provider to respect their
    | API limitations and prevent 429 errors.
    |
    */

    'providers' => [
        'resend' => [
            'max_attempts' => env('RESEND_MAX_ATTEMPTS', 2),
            'decay_seconds' => env('RESEND_DECAY_SECONDS', 1),
        ],
        'sendgrid' => [
            'max_attempts' => env('SENDGRID_MAX_ATTEMPTS', 10),
            'decay_seconds' => env('SENDGRID_DECAY_SECONDS', 1),
        ],
        'mailgun' => [
            'max_attempts' => env('MAILGUN_MAX_ATTEMPTS', 100),
            'decay_seconds' => env('MAILGUN_DECAY_SECONDS', 1),
        ],
        'beem' => [
            'max_attempts' => env('BEEM_MAX_ATTEMPTS', 5),
            'decay_seconds' => env('BEEM_DECAY_SECONDS', 1),
        ],
        'termii' => [
            'max_attempts' => env('TERMII_MAX_ATTEMPTS', 10),
            'decay_seconds' => env('TERMII_DECAY_SECONDS', 1),
        ],
        'twilio' => [
            'max_attempts' => env('TWILIO_MAX_ATTEMPTS', 1),
            'decay_seconds' => env('TWILIO_DECAY_SECONDS', 1),
        ],
        'whatsapp' => [
            'max_attempts' => env('WHATSAPP_MAX_ATTEMPTS', 1000),
            'decay_seconds' => env('WHATSAPP_DECAY_SECONDS', 60),
        ],
        'wasender' => [
            'max_attempts' => env('WASENDER_MAX_ATTEMPTS', 5),
            'decay_seconds' => env('WASENDER_DECAY_SECONDS', 1),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Channel-Specific Rate Limits
    |--------------------------------------------------------------------------
    |
    | Configure different rate limits for each channel type.
    |
    */

    'channels' => [
        'email' => [
            'max_attempts' => env('EMAIL_CHANNEL_MAX_ATTEMPTS', 5),
            'decay_seconds' => env('EMAIL_CHANNEL_DECAY_SECONDS', 1),
        ],
        'sms' => [
            'max_attempts' => env('SMS_CHANNEL_MAX_ATTEMPTS', 5),
            'decay_seconds' => env('SMS_CHANNEL_DECAY_SECONDS', 1),
        ],
        'whatsapp' => [
            'max_attempts' => env('WHATSAPP_CHANNEL_MAX_ATTEMPTS', 10),
            'decay_seconds' => env('WHATSAPP_CHANNEL_DECAY_SECONDS', 1),
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