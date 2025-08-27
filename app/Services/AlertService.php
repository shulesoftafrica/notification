<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Models\Project;

/**
 * Alert Management Service
 * Handles alerting and notifications for production issues
 */
class AlertService
{
    private const ALERT_COOLDOWN = 900; // 15 minutes
    private const ESCALATION_THRESHOLD = 3600; // 1 hour

    /**
     * Send critical alert
     */
    public function sendCriticalAlert(string $type, string $message, array $data = []): void
    {
        $alertKey = "alert:critical:{$type}";
        
        // Check cooldown to prevent spam
        if (Cache::has($alertKey)) {
            return;
        }

        // Set cooldown
        Cache::put($alertKey, true, self::ALERT_COOLDOWN);

        $alert = [
            'type' => $type,
            'severity' => 'critical',
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment()
        ];

        // Log the alert
        Log::critical('CRITICAL ALERT', $alert);

        // Send to multiple channels
        $this->sendSlackAlert($alert);
        $this->sendEmailAlert($alert);
        $this->sendWebhookAlert($alert);
        
        // Store in database for tracking
        $this->storeAlert($alert);
    }

    /**
     * Send warning alert
     */
    public function sendWarningAlert(string $type, string $message, array $data = []): void
    {
        $alertKey = "alert:warning:{$type}";
        
        // Check cooldown
        if (Cache::has($alertKey)) {
            return;
        }

        // Set cooldown (shorter for warnings)
        Cache::put($alertKey, true, 300); // 5 minutes

        $alert = [
            'type' => $type,
            'severity' => 'warning',
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment()
        ];

        Log::warning('WARNING ALERT', $alert);
        
        // Send to monitoring channels
        $this->sendSlackAlert($alert);
        $this->storeAlert($alert);
    }

    /**
     * Send Slack alert
     */
    private function sendSlackAlert(array $alert): void
    {
        $webhookUrl = config('notification.alerts.slack_webhook');
        
        if (empty($webhookUrl)) {
            return;
        }

        $color = $alert['severity'] === 'critical' ? 'danger' : 'warning';
        $emoji = $alert['severity'] === 'critical' ? '🚨' : '⚠️';

        $payload = [
            'text' => "{$emoji} {$alert['severity']} Alert: {$alert['message']}",
            'attachments' => [
                [
                    'color' => $color,
                    'fields' => [
                        [
                            'title' => 'Type',
                            'value' => $alert['type'],
                            'short' => true
                        ],
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

        try {
            Http::timeout(10)->post($webhookUrl, $payload);
        } catch (\Exception $e) {
            Log::error('Failed to send Slack alert', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send email alert
     */
    private function sendEmailAlert(array $alert): void
    {
        $alertEmails = config('notification.alerts.emails', []);
        
        if (empty($alertEmails)) {
            return;
        }

        try {
            Mail::raw(
                $this->formatEmailAlert($alert),
                function ($message) use ($alert, $alertEmails) {
                    $message->to($alertEmails)
                        ->subject("[{$alert['environment']}] {$alert['severity']} Alert: {$alert['type']}")
                        ->priority($alert['severity'] === 'critical' ? 1 : 3);
                }
            );
        } catch (\Exception $e) {
            Log::error('Failed to send email alert', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send webhook alert to external monitoring systems
     */
    private function sendWebhookAlert(array $alert): void
    {
        $webhookUrls = config('notification.alerts.webhooks', []);
        
        foreach ($webhookUrls as $url) {
            try {
                Http::timeout(10)->post($url, $alert);
            } catch (\Exception $e) {
                Log::error('Failed to send webhook alert', [
                    'url' => $url,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Store alert in database
     */
    private function storeAlert(array $alert): void
    {
        try {
            \DB::table('system_alerts')->insert([
                'type' => $alert['type'],
                'severity' => $alert['severity'],
                'message' => $alert['message'],
                'data' => json_encode($alert['data']),
                'environment' => $alert['environment'],
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store alert', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Format email alert message
     */
    private function formatEmailAlert(array $alert): string
    {
        $message = "NOTIFICATION SERVICE ALERT\n";
        $message .= str_repeat('=', 50) . "\n\n";
        $message .= "Severity: " . strtoupper($alert['severity']) . "\n";
        $message .= "Type: {$alert['type']}\n";
        $message .= "Environment: {$alert['environment']}\n";
        $message .= "Timestamp: {$alert['timestamp']}\n\n";
        $message .= "Message:\n{$alert['message']}\n\n";
        
        if (!empty($alert['data'])) {
            $message .= "Additional Data:\n";
            foreach ($alert['data'] as $key => $value) {
                $message .= "  {$key}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
            }
        }
        
        $message .= "\n" . str_repeat('=', 50) . "\n";
        $message .= "This is an automated alert from the Notification Service.\n";
        $message .= "Please investigate immediately if this is a critical alert.\n";
        
        return $message;
    }

    /**
     * Check for escalation needed
     */
    public function checkEscalation(): void
    {
        $criticalAlerts = \DB::table('system_alerts')
            ->where('severity', 'critical')
            ->where('created_at', '>=', now()->subHour())
            ->where('escalated', false)
            ->get();

        foreach ($criticalAlerts as $alert) {
            if (now()->diffInSeconds($alert->created_at) > self::ESCALATION_THRESHOLD) {
                $this->escalateAlert($alert);
            }
        }
    }

    /**
     * Escalate unresolved critical alerts
     */
    private function escalateAlert($alert): void
    {
        $escalationEmails = config('notification.alerts.escalation_emails', []);
        
        if (empty($escalationEmails)) {
            return;
        }

        try {
            Mail::raw(
                "ESCALATED ALERT: Critical issue unresolved for over 1 hour.\n\n" .
                "Original Alert: {$alert->message}\n" .
                "First Reported: {$alert->created_at}\n\n" .
                "Immediate action required!",
                function ($message) use ($alert, $escalationEmails) {
                    $message->to($escalationEmails)
                        ->subject("[ESCALATED] Critical Alert: {$alert->type}")
                        ->priority(1);
                }
            );

            // Mark as escalated
            \DB::table('system_alerts')
                ->where('id', $alert->id)
                ->update(['escalated' => true, 'escalated_at' => now()]);

        } catch (\Exception $e) {
            Log::error('Failed to escalate alert', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get active alerts summary
     */
    public function getActiveAlerts(): array
    {
        return \DB::table('system_alerts')
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('severity')
            ->map(function ($alerts) {
                return $alerts->take(10); // Limit to recent 10 per severity
            })
            ->toArray();
    }

    /**
     * Clear resolved alerts
     */
    public function clearAlert(string $type): void
    {
        Cache::forget("alert:critical:{$type}");
        Cache::forget("alert:warning:{$type}");
        
        Log::info('Alert cleared', ['type' => $type]);
    }
}
