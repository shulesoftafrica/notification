<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings for provider health monitoring and failover
    |
    */
    'defaults' => [
        'circuit_breaker' => [
            'failure_threshold' => 5,
            'recovery_timeout' => 300, // 5 minutes
            'success_threshold' => 3,
        ],
        'health_check' => [
            'interval' => 60, // seconds
            'timeout' => 10, // seconds
        ],
        'rate_limiting' => [
            'enabled' => true,
            'requests_per_minute' => 1000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    |
    | Configuration for each notification provider including priority,
    | circuit breaker settings, and health check configurations
    |
    */
    'providers' => [
        'email' => [
            'sendgrid' => [
                'name' => 'SendGrid',
                'priority' => 1,
                'weight' => 100,
                'enabled' => env('SENDGRID_ENABLED', true),
                'circuit_breaker' => [
                    'failure_threshold' => 5,
                    'recovery_timeout' => 300,
                    'success_threshold' => 3,
                ],
                'health_check' => [
                    'url' => 'https://api.sendgrid.com/v3/user/profile',
                    'method' => 'GET',
                    'headers' => [
                        'Authorization' => 'Bearer ' . env('SENDGRID_API_KEY'),
                    ],
                    'timeout' => 10,
                    'interval' => 60,
                ],
                'rate_limiting' => [
                    'requests_per_minute' => 600,
                ],
            ],
            'mailgun' => [
                'name' => 'Mailgun',
                'priority' => 2,
                'weight' => 90,
                'enabled' => env('MAILGUN_ENABLED', true),
                'circuit_breaker' => [
                    'failure_threshold' => 5,
                    'recovery_timeout' => 300,
                    'success_threshold' => 3,
                ],
                'health_check' => [
                    'url' => 'https://api.mailgun.net/v3/domains',
                    'method' => 'GET',
                    'auth' => [
                        'username' => 'api',
                        'password' => env('MAILGUN_SECRET'),
                    ],
                    'timeout' => 10,
                    'interval' => 60,
                ],
                'rate_limiting' => [
                    'requests_per_minute' => 500,
                ],
            ],
            'resend' => [
                'name' => 'Resend',
                'priority' => 3,
                'weight' => 85,
                'enabled' => env('RESEND_ENABLED', true),
                'circuit_breaker' => [
                    'failure_threshold' => 5,
                    'recovery_timeout' => 300,
                    'success_threshold' => 3,
                ],
                'health_check' => [
                    'url' => 'https://api.resend.com/domains',
                    'method' => 'GET',
                    'headers' => [
                        'Authorization' => 'Bearer ' . env('RESEND_API_KEY'),
                    ],
                    'timeout' => 10,
                    'interval' => 60,
                ],
                'rate_limiting' => [
                    'requests_per_minute' => 400,
                ],
            ],
        ],
        'sms' => [
            'twilio' => [
                'name' => 'Twilio',
                'priority' => 1,
                'weight' => 100,
                'enabled' => env('TWILIO_ENABLED', true),
                'circuit_breaker' => [
                    'failure_threshold' => 5,
                    'recovery_timeout' => 300,
                    'success_threshold' => 3,
                ],
                'health_check' => [
                    'url' => 'https://api.twilio.com/2010-04-01/Accounts/' . env('TWILIO_ACCOUNT_SID') . '.json',
                    'method' => 'GET',
                    'auth' => [
                        'username' => env('TWILIO_ACCOUNT_SID'),
                        'password' => env('TWILIO_AUTH_TOKEN'),
                    ],
                    'timeout' => 10,
                    'interval' => 60,
                ],
                'rate_limiting' => [
                    'requests_per_minute' => 200,
                ],
            ],
            'vonage' => [
                'name' => 'Vonage',
                'priority' => 2,
                'weight' => 90,
                'enabled' => env('VONAGE_ENABLED', true),
                'circuit_breaker' => [
                    'failure_threshold' => 5,
                    'recovery_timeout' => 300,
                    'success_threshold' => 3,
                ],
                'health_check' => [
                    'url' => 'https://rest.nexmo.com/account/get-balance',
                    'method' => 'GET',
                    'query' => [
                        'api_key' => env('VONAGE_API_KEY'),
                        'api_secret' => env('VONAGE_API_SECRET'),
                    ],
                    'timeout' => 10,
                    'interval' => 60,
                ],
                'rate_limiting' => [
                    'requests_per_minute' => 150,
                ],
            ],
        ],
        'whatsapp' => [
            'meta' => [
                'name' => 'Meta WhatsApp Business',
                'priority' => 1,
                'weight' => 100,
                'enabled' => env('WHATSAPP_ENABLED', true),
                'circuit_breaker' => [
                    'failure_threshold' => 5,
                    'recovery_timeout' => 300,
                    'success_threshold' => 3,
                ],
                'health_check' => [
                    'url' => 'https://graph.facebook.com/v17.0/' . env('WHATSAPP_PHONE_NUMBER_ID'),
                    'method' => 'GET',
                    'headers' => [
                        'Authorization' => 'Bearer ' . env('WHATSAPP_ACCESS_TOKEN'),
                    ],
                    'timeout' => 10,
                    'interval' => 60,
                ],
                'rate_limiting' => [
                    'requests_per_minute' => 80,
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for API rate limiting at various levels
    |
    */
    'rate_limits' => [
        'global' => [
            'per_minute' => env('GLOBAL_RATE_LIMIT_MINUTE', 10000),
            'per_hour' => env('GLOBAL_RATE_LIMIT_HOUR', 500000),
        ],
        'default' => [
            'per_minute' => env('DEFAULT_RATE_LIMIT_MINUTE', 100),
            'per_hour' => env('DEFAULT_RATE_LIMIT_HOUR', 5000),
            'per_day' => env('DEFAULT_RATE_LIMIT_DAY', 100000),
        ],
        'unauthenticated' => [
            'per_minute' => 60,
            'per_hour' => 1000,
            'per_day' => 10000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for webhook processing and signature validation
    |
    */
    'webhooks' => [
        'max_attempts' => env('WEBHOOK_MAX_ATTEMPTS', 10),
        'timeout_seconds' => env('WEBHOOK_TIMEOUT', 30),
        'retry_delays' => [1, 2, 4, 8, 16, 30, 60, 120, 300, 600], // seconds
        'signature_validation' => [
            'sendgrid' => [
                'enabled' => true,
                'header' => 'X-Twilio-Email-Event-Webhook-Signature',
                'secret' => env('SENDGRID_WEBHOOK_SECRET'),
            ],
            'mailgun' => [
                'enabled' => true,
                'header' => 'X-Mailgun-Signature-256',
                'secret' => env('MAILGUN_WEBHOOK_SECRET'),
            ],
            'twilio' => [
                'enabled' => true,
                'header' => 'X-Twilio-Signature',
                'secret' => env('TWILIO_AUTH_TOKEN'),
            ],
            'vonage' => [
                'enabled' => false, // Vonage doesn't use signature validation
            ],
            'resend' => [
                'enabled' => true,
                'header' => 'Resend-Signature',
                'secret' => env('RESEND_WEBHOOK_SECRET'),
            ],
            'whatsapp' => [
                'enabled' => true,
                'header' => 'X-Hub-Signature-256',
                'secret' => env('WHATSAPP_WEBHOOK_SECRET'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Failover Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for automatic failover behavior
    |
    */
    'failover' => [
        'enabled' => env('NOTIFICATION_FAILOVER_ENABLED', true),
        'max_retries' => 3,
        'retry_delay' => 5, // seconds
        'health_score_threshold' => 70, // minimum score to be considered healthy
        'load_balancing' => [
            'algorithm' => 'weighted_round_robin', // weighted_round_robin, least_connections, random
            'consider_response_time' => true,
            'max_response_time' => 5000, // milliseconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for provider monitoring and alerting
    |
    */
    'monitoring' => [
        'metrics_retention' => 86400, // 24 hours in seconds
        'alert_thresholds' => [
            'error_rate' => 10, // percentage
            'response_time' => 3000, // milliseconds
            'success_rate' => 95, // percentage
        ],
        'dashboard' => [
            'enabled' => true,
            'refresh_interval' => 30, // seconds
        ],
        'health_check_cache_ttl' => env('HEALTH_CHECK_CACHE_TTL', 60),
        'metrics_retention_days' => env('METRICS_RETENTION_DAYS', 30),
        'alert_cooldown_seconds' => env('ALERT_COOLDOWN_SECONDS', 900),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Configuration
    |--------------------------------------------------------------------------
    */
    'alerts' => [
        'slack_webhook' => env('SLACK_WEBHOOK_URL'),
        'emails' => array_filter(explode(',', env('ALERT_EMAILS', ''))),
        'escalation_emails' => array_filter(explode(',', env('ESCALATION_EMAILS', ''))),
        'webhooks' => array_filter(explode(',', env('ALERT_WEBHOOKS', ''))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Configuration
    |--------------------------------------------------------------------------
    */
    'admin' => [
        'allowed_ips' => array_filter(explode(',', env('ADMIN_ALLOWED_IPS', ''))),
        'validate_ip' => env('ADMIN_VALIDATE_IP', true),
        'credentials' => [
            // Email => Hashed Password
            // Generate with: php artisan admin:generate-password YourPassword
            'admin@notification.local' => '$2y$12$uiAwf4ZIqtCWKyY4emaB2e8Ur1a3DVKB7NclyRqdq0NT6y8dxykCW', // MySecurePassword123
            'admin@yourcompany.com' => '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */
    'security' => [
        'max_request_size' => env('MAX_REQUEST_SIZE', 10 * 1024 * 1024), // 10MB
        'blocked_user_agents' => [
            'curl', 'wget', 'python-requests', 'bot', 'crawler', 'spider'
        ],
        'require_https' => env('REQUIRE_HTTPS', true),
    ],
];
