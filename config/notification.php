<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Notification Provider
    |--------------------------------------------------------------------------
    |
    | This option defines the default notification provider that will be used
    | when no specific provider is defined for a notification.
    |
    */

    'default_provider' => env('NOTIFICATION_DEFAULT_PROVIDER', 'email'),

    /*
    |--------------------------------------------------------------------------
    | Notification Providers
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the notification providers for your application
    | along with their configuration.
    |
    */

    'providers' => [
        'email' => [
            'driver' => 'mail',
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Example'),
            ],
        ],

        'sms' => [
            'driver' => 'twilio',
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],

        'whatsapp' => [
            'driver' => 'whatsapp_business',
            'phone_id' => env('WHATSAPP_BUSINESS_PHONE_ID'),
            'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        ],

        'slack' => [
            'driver' => 'slack',
            'webhook_url' => env('SLACK_WEBHOOK_URL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for notifications to prevent abuse.
    |
    */

    'rate_limiting' => [
        'enabled' => env('NOTIFICATION_RATE_LIMIT_ENABLED', true),
        'limits' => [
            'email' => [
                'minute' => 100,
                'hour' => 5000,
                'day' => 100000,
            ],
            'sms' => [
                'minute' => 50,
                'hour' => 2000,
                'day' => 20000,
            ],
            'whatsapp' => [
                'minute' => 20,
                'hour' => 1000,
                'day' => 10000,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure whether notifications should be queued for background processing.
    |
    */

    'queue' => [
        'enabled' => env('NOTIFICATION_QUEUE_ENABLED', true),
        'connection' => env('QUEUE_CONNECTION', 'database'),
        'queue' => 'notifications',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configure webhook endpoints for delivery status updates.
    |
    */

    'webhooks' => [
        'verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'endpoints' => [
            'whatsapp' => '/webhooks/whatsapp',
            'twilio' => '/webhooks/twilio',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Alerts
    |--------------------------------------------------------------------------
    |
    | Configure monitoring and alerting for the notification service.
    |
    */

    'monitoring' => [
        'enabled' => env('PRODUCTION_MONITORING_ENABLED', true),
        'alerts' => [
            'enabled' => env('PRODUCTION_ALERTS_ENABLED', true),
            'channels' => ['slack', 'email'],
            'thresholds' => [
                'error_rate' => 0.05, // 5%
                'response_time' => 5000, // 5 seconds
            ],
        ],
    ],

];
