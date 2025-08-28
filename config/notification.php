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
        'sendgrid' => [
            'driver' => 'sendgrid',
            'api_key' => env('SENDGRID_API_KEY', 'fake-sendgrid-key-for-testing'),
            'from_email' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
            'from_name' => env('MAIL_FROM_NAME', 'Example'),
            'channels' => ['email'],
            'priority' => 90,
            'enabled' => true,
        ],

        'resend' => [
            'driver' => 'resend',
            'api_key' => env('RESEND_API_KEY', 're_MnYpRxPt_JfBcjc4ji75DeFpVMHMkn2GM'),
            'from_email' => env('MAIL_FROM_ADDRESS', 'noreply@mail.shulesoft.co'),
            'from_name' => env('MAIL_FROM_NAME', 'Shulesoft'),
            'channels' => ['email'],
            'priority' => 95, // Higher priority than SendGrid
            'enabled' => true,
        ],

        'mailgun' => [
            'driver' => 'mailgun',
            'domain' => env('MAILGUN_DOMAIN', 'example.com'),
            'secret' => env('MAILGUN_SECRET', 'fake-mailgun-key-for-testing'),
            'from_email' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
            'from_name' => env('MAIL_FROM_NAME', 'Example'),
            'channels' => ['email'],
            'priority' => 80,
            'enabled' => true,
        ],

        'twilio' => [
            'driver' => 'twilio',
            'sid' => env('TWILIO_SID', 'fake-twilio-sid'),
            'token' => env('TWILIO_TOKEN', 'fake-twilio-token'),
            'from' => env('TWILIO_FROM', '+1234567890'),
            'channels' => ['sms'],
            'priority' => 90,
            'enabled' => true,
        ],

        'beem' => [
            'driver' => 'beem',
            'api_key' => env('BEEM_API_KEY', '5e0b7f1a911dd411'),
            'secret_key' => env('BEEM_SECRET_KEY', 'MDI2ZGVlMWExN2NlNzlkYzUyYWE2NTlhOGE0MjgyMDRmMjFlMDFjODkwYjU2NjA4OTY4NzZlY2Y3NGZjY2Y0Yw'),
            'sender_name' => env('BEEM_SENDER_NAME', 'SHULESOFT'),
            'channels' => ['sms'],
            'priority' => 95, // Higher priority for Tanzania
            'enabled' => true,
            'countries' => ['TZ', 'tz', 'tanzania'], // Tanzania routing
        ],

        'termii' => [
            'driver' => 'termii',
            'api_key' => env('TERMII_API_KEY', 'TLhpxNaEsEaaBWvANVDlrsrorFRwOheKowfouKSNLAvWBibmowWYDNBqqDBBxn'),
            'from' => env('TERMII_FROM', 'N-Alert'),
            'channel' => env('TERMII_CHANNEL', 'dnd'),
            'type' => 'plain',
            'channels' => ['sms'],
            'priority' => 95, // Higher priority for Nigeria
            'enabled' => true,
            'countries' => ['NG', 'ng', 'nigeria'], // Nigeria routing
        ],

        'whatsapp' => [
            'driver' => 'whatsapp_business',
            'phone_id' => env('WHATSAPP_BUSINESS_PHONE_ID', 'fake-phone-id'),
            'access_token' => env('WHATSAPP_ACCESS_TOKEN', 'fake-access-token'),
            'channels' => ['whatsapp'],
            'priority' => 90,
            'enabled' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Channel Configuration
    |--------------------------------------------------------------------------
    |
    | Define which providers are available for each channel.
    |
    */

    'channels' => [
        'email' => [
            'providers' => ['resend', 'sendgrid', 'mailgun'],
            'default' => 'resend',
        ],
        'sms' => [
            'providers' => ['beem', 'termii', 'twilio'],
            'default' => 'beem',
        ],
        'whatsapp' => [
            'providers' => ['whatsapp'],
            'default' => 'whatsapp',
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
