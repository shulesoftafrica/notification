<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

class AlertService
{
    protected $alertConfig;
    protected $cachePrefix = 'alerts:';

    public function __construct()
    {
        $this->alertConfig = config('notification.alerts', []);
    }

    /**
     * Send critical alert
     */
    public function sendCriticalAlert($title, $message, $context = [])
    {
        $this->sendAlert('critical', $title, $message, $context);
    }

    /**
     * Send warning alert
     */
    public function sendWarningAlert($title, $message, $context = [])
    {
        $this->sendAlert('warning', $title, $message, $context);
    }

    /**
     * Send info alert
     */
    public function sendInfoAlert($title, $message, $context = [])
    {
        $this->sendAlert('info', $title, $message, $context);
    }

    /**
     * Send alert based on level
     */
    protected function sendAlert($level, $title, $message, $context = [])
    {
        $alertKey = $this->generateAlertKey($level, $title, $message);
        
        // Check if we've already sent this alert recently (rate limiting)
        if ($this->isAlertRateLimited($alertKey)) {
            return;
        }

        $alert = [
            'level' => $level,
            'title' => $title,
            'message' => $message,
            'context' => $context,
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment(),
        ];

        // Log the alert
        Log::channel('alerts')->{$level}($title, $alert);

        // Send email alerts if configured
        if ($this->shouldSendEmailAlert($level)) {
            $this->sendEmailAlert($alert);
        }

        // Send Slack alerts if configured
        if ($this->shouldSendSlackAlert($level)) {
            $this->sendSlackAlert($alert);
        }

        // Mark alert as sent (rate limiting)
        $this->markAlertSent($alertKey);
    }

    /**
     * Send provider failure alert
     */
    public function sendProviderFailureAlert($provider, $error, $failureCount = 1)
    {
        $title = "Provider Failure: {$provider}";
        $message = "Provider {$provider} has failed {$failureCount} time(s). Error: {$error}";
        
        $context = [
            'provider' => $provider,
            'error' => $error,
            'failure_count' => $failureCount,
        ];

        $level = $failureCount >= 5 ? 'critical' : 'warning';
        $this->sendAlert($level, $title, $message, $context);
    }

    /**
     * Send high error rate alert
     */
    public function sendHighErrorRateAlert($errorRate, $threshold, $timeWindow)
    {
        $title = "High Error Rate Detected";
        $message = "Error rate of {$errorRate}% exceeds threshold of {$threshold}% in the last {$timeWindow} minutes";
        
        $context = [
            'error_rate' => $errorRate,
            'threshold' => $threshold,
            'time_window' => $timeWindow,
        ];

        $this->sendCriticalAlert($title, $message, $context);
    }

    /**
     * Send rate limit exceeded alert
     */
    public function sendRateLimitAlert($provider, $endpoint, $limit)
    {
        $title = "Rate Limit Exceeded: {$provider}";
        $message = "Rate limit of {$limit} requests exceeded for {$provider} {$endpoint}";
        
        $context = [
            'provider' => $provider,
            'endpoint' => $endpoint,
            'limit' => $limit,
        ];

        $this->sendWarningAlert($title, $message, $context);
    }

    /**
     * Send email alert
     */
    protected function sendEmailAlert($alert)
    {
        try {
            $recipients = $this->alertConfig['email']['recipients'] ?? [];
            
            if (empty($recipients)) {
                return;
            }

            foreach ($recipients as $recipient) {
                Mail::raw($this->formatEmailAlert($alert), function ($message) use ($recipient, $alert) {
                    $message->to($recipient)
                            ->subject("[{$alert['level']}] {$alert['title']} - Notification Service");
                });
            }
        } catch (\Exception $e) {
            Log::error('Failed to send email alert', [
                'alert' => $alert,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send Slack alert
     */
    protected function sendSlackAlert($alert)
    {
        try {
            $webhookUrl = $this->alertConfig['slack']['webhook_url'] ?? null;
            
            if (!$webhookUrl) {
                return;
            }

            $payload = [
                'text' => $this->formatSlackAlert($alert),
                'username' => 'Notification Service',
                'icon_emoji' => $this->getSlackEmoji($alert['level']),
                'attachments' => [
                    [
                        'color' => $this->getSlackColor($alert['level']),
                        'fields' => [
                            [
                                'title' => 'Environment',
                                'value' => $alert['environment'],
                                'short' => true
                            ],
                            [
                                'title' => 'Timestamp',
                                'value' => $alert['timestamp'],
                                'short' => true
                            ]
                        ]
                    ]
                ]
            ];

            Http::post($webhookUrl, $payload);
        } catch (\Exception $e) {
            Log::error('Failed to send Slack alert', [
                'alert' => $alert,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if alert should send email
     */
    protected function shouldSendEmailAlert($level)
    {
        $emailLevels = $this->alertConfig['email']['levels'] ?? ['critical', 'warning'];
        return in_array($level, $emailLevels);
    }

    /**
     * Check if alert should send to Slack
     */
    protected function shouldSendSlackAlert($level)
    {
        $slackLevels = $this->alertConfig['slack']['levels'] ?? ['critical'];
        return in_array($level, $slackLevels);
    }

    /**
     * Format alert for email
     */
    protected function formatEmailAlert($alert)
    {
        $content = "ALERT: {$alert['title']}\n\n";
        $content .= "Level: " . strtoupper($alert['level']) . "\n";
        $content .= "Message: {$alert['message']}\n";
        $content .= "Environment: {$alert['environment']}\n";
        $content .= "Timestamp: {$alert['timestamp']}\n\n";
        
        if (!empty($alert['context'])) {
            $content .= "Context:\n";
            foreach ($alert['context'] as $key => $value) {
                $content .= "  {$key}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
            }
        }

        return $content;
    }

    /**
     * Format alert for Slack
     */
    protected function formatSlackAlert($alert)
    {
        return "*{$alert['title']}*\n{$alert['message']}";
    }

    /**
     * Get Slack emoji for alert level
     */
    protected function getSlackEmoji($level)
    {
        return match($level) {
            'critical' => ':rotating_light:',
            'warning' => ':warning:',
            'info' => ':information_source:',
            default => ':bell:'
        };
    }

    /**
     * Get Slack color for alert level
     */
    protected function getSlackColor($level)
    {
        return match($level) {
            'critical' => 'danger',
            'warning' => 'warning',
            'info' => 'good',
            default => '#36a64f'
        };
    }

    /**
     * Generate unique alert key for rate limiting
     */
    protected function generateAlertKey($level, $title, $message)
    {
        return md5($level . $title . $message);
    }

    /**
     * Check if alert is rate limited
     */
    protected function isAlertRateLimited($alertKey)
    {
        $cacheKey = $this->cachePrefix . $alertKey;
        return Cache::has($cacheKey);
    }

    /**
     * Mark alert as sent for rate limiting
     */
    protected function markAlertSent($alertKey)
    {
        $cacheKey = $this->cachePrefix . $alertKey;
        $ttl = $this->alertConfig['rate_limit_minutes'] ?? 60; // 1 hour default
        Cache::put($cacheKey, true, now()->addMinutes($ttl));
    }
}
