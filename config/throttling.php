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
    | API limitations and prevent 429 errors. The provider limit is the one
    | enforced for queued sends; it is keyed on the provider that will
    | actually be used (after failover/routing), not the one requested.
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
            'max_attempts' => env('BEEM_MAX_ATTEMPTS', 10), // Increased from 5 to 10
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
            // Wasender account protection allows only 1 message every 5 seconds.
            'max_attempts' => env('WASENDER_MAX_ATTEMPTS', 1),
            'decay_seconds' => env('WASENDER_DECAY_SECONDS', 5),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Channel-Specific Rate Limits
    |--------------------------------------------------------------------------
    |
    | Fallback limits, used only when the resolved provider has no entry in
    | the 'providers' list above.
    |
    */

    'channels' => [
        'email' => [
            'max_attempts' => env('EMAIL_CHANNEL_MAX_ATTEMPTS', 5),
            'decay_seconds' => env('EMAIL_CHANNEL_DECAY_SECONDS', 1),
        ],
        'sms' => [
            'max_attempts' => env('SMS_CHANNEL_MAX_ATTEMPTS', 10), // Increased from 5 to 10
            'decay_seconds' => env('SMS_CHANNEL_DECAY_SECONDS', 1),
        ],
        'whatsapp' => [
            'max_attempts' => env('WHATSAPP_CHANNEL_MAX_ATTEMPTS', 10),
            'decay_seconds' => env('WHATSAPP_CHANNEL_DECAY_SECONDS', 1),
        ],
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

    /*
    |--------------------------------------------------------------------------
    | Queue Behaviour
    |--------------------------------------------------------------------------
    |
    | release_jitter_seconds: random extra delay added when a throttled job is
    | released, so released jobs do not all wake up in the same second.
    |
    | retry_window_minutes: how long a DispatchMessage job may keep being
    | released/retried before it is failed. Throttle releases no longer
    | consume a fixed "tries" budget; only this window and maxExceptions apply.
    |
    */
    'release_jitter_seconds' => (int) env('THROTTLE_RELEASE_JITTER_SECONDS', 3),
    'retry_window_minutes' => (int) env('QUEUE_RETRY_WINDOW_MINUTES', 360),
];