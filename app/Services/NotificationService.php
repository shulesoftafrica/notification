<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\ProviderFailoverService;
use App\Services\MetricsService;
use App\Services\AnalyticsService;
use App\Services\EmailFilterService;
use App\Services\Adapters\ProviderResponse;

class NotificationService
{
    protected $failoverService;
    protected $metricsService;
    protected $analyticsService;
    protected $emailFilter;

    public function __construct(
        ProviderFailoverService $failoverService,
        MetricsService $metricsService,
        AnalyticsService $analyticsService,
        EmailFilterService $emailFilter
    ) {
        $this->failoverService = $failoverService;
        $this->metricsService = $metricsService;
        $this->analyticsService = $analyticsService;
        $this->emailFilter = $emailFilter;
    }

    /**
     * Send notification
     *
     * Pass 'resolved_provider' (from resolveProvider()) to skip provider selection, so the
     * provider that was rate-limited is the one actually used.
     */
    public function send($data)
    {
        $logId = (string) Str::uuid();

        try {
            // Validate and prepare data
            $preparedData = $this->prepareNotificationData($data);

            // Last line of defence before a provider is contacted: never send
            // email to an address that fails the outbound recipient filter.
            if (($preparedData['channel'] ?? null) === 'email') {
                $check = $this->emailFilter->check($preparedData['to']);

                if (!$check['allowed']) {
                    return $this->blockEmailRecipient($logId, $preparedData, $check);
                }

                $preparedData['to'] = $check['email'];
            }

            // Store notification log
            $this->storeNotificationLog($logId, $preparedData);

            // Select provider
            $provider = $data['resolved_provider']
                ?? $this->selectProvider($preparedData['channel'], $preparedData['provider'], $preparedData);

            // Send notification
            $result = $this->sendNotification($provider, $preparedData);

            // Update log with result
            $this->updateNotificationLog($logId, $result, $provider);

            $success = (bool) ($result['success'] ?? false);

            // Track metrics (a provider rejection must not be counted/logged as sent)
            if ($success) {
                $this->metricsService->trackNotificationSent($provider, $preparedData['channel'], [
                    'send_time' => $result['send_time'] ?? 0,
                ]);
            } else {
                $this->metricsService->trackNotificationFailed($provider, $preparedData['channel'], $result['error'] ?? 'unknown', [
                    'send_time' => $result['send_time'] ?? 0,
                ]);
            }
            $statusCode = (int) ($result['metadata']['status_code'] ?? 0);
            $temporaryFailure = !$success && (
                $statusCode === 429 || $statusCode >= 500
                || ProviderResponse::errorIsTemporary($result['error'] ?? null)
            );

            // Feed the circuit breaker. Only transient provider errors count, so bad
            // recipient numbers etc. cannot mark a healthy provider as down.
            if ($success) {
                $this->failoverService->recordProviderSuccess($provider);
            } elseif ($temporaryFailure) {
                $this->failoverService->recordProviderFailure($provider, $result['error'] ?? 'unknown');
            }

            return [
                // Provider's own id (used to match delivery reports); falls back to the log id.
                'message_id' => $result['message_id'] ?? $logId,
                'provider_message_id' => $result['message_id'] ?? null,
                'log_id' => $logId,
                'status' => $success ? 'sent' : 'failed',
                'provider' => $provider,
                'error' => $success ? null : ($result['error'] ?? 'Unknown provider error'),
                'temporary_failure' => $temporaryFailure,
                'estimated_delivery' => $result['estimated_delivery'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Notification send failed', [
                'log_id' => $logId,
                'error' => $e->getMessage(),
                'data' => $this->redactForLog($data),
            ]);

            // Update log with error
            $this->updateNotificationLog($logId, [
                'success' => false,
                'error' => $e->getMessage(),
            ], $provider ?? null);

            throw $e;
        }
    }

    /**
     * Record a recipient rejected by the email filter and return a terminal
     * result. Deliberately not an exception: there is nothing to retry.
     */
    protected function blockEmailRecipient($messageId, array $preparedData, array $check)
    {
        Log::warning('Email recipient blocked by filter - not sent', [
            'message_id' => (string) $messageId,
            'to' => $check['email'],
            'domain' => $check['domain'],
            'code' => $check['code'],
            'reason' => $check['reason'],
            'schema_name' => $preparedData['metadata']['schema_name'] ?? null,
        ]);

        try {
            $logId = $this->storeNotificationLog($messageId, $preparedData);
            $this->updateNotificationLog($logId, [
                'status' => 'blocked',
                'error' => $check['reason'],
                'provider_response' => ['filter_code' => $check['code']],
            ], null);
        } catch (\Exception $e) {
            Log::error('Failed to log blocked email recipient', [
                'message_id' => (string) $messageId,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'message_id' => $messageId,
            'provider_message_id' => null,
            'log_id' => (string) $messageId,
            'status' => 'blocked',
            'provider' => null,
            'error' => $check['reason'],
            'temporary_failure' => false,
            'estimated_delivery' => null,
            'filter_code' => $check['code'],
        ];
    }

    /**
     * Resolve which provider will be used for this notification, without sending.
     */
    public function resolveProvider(array $data): string
    {
        $preparedData = $this->prepareNotificationData($data);

        return $this->selectProvider($preparedData['channel'], $preparedData['provider'], $preparedData);
    }

    /**
     * Strip secrets from payloads before they are written to logs.
     */
    protected function redactForLog(array $data): array
    {
        if (isset($data['metadata']['wasender_api_key'])) {
            $data['metadata']['wasender_api_key'] = '[redacted]';
        }

        return $data;
    }

    /**
     * Prepare notification data
     */
    protected function prepareNotificationData($data)
    {
        return [
            'channel' => $data['channel'],
            'to' => $data['to'],
            'message' => $data['message'],
            'subject' => $data['subject'] ?? null,
            'provider' => $data['provider'] ?? null,
            'template_id' => $data['template_id'] ?? null,
            'template_data' => $data['template_data'] ?? [],
            'priority' => $data['priority'] ?? 'normal',
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'webhook_url' => $data['webhook_url'] ?? null,
            'tags' => $data['tags'] ?? [],
            'attachment' => $data['attachment'] ?? null,
            'attachment_metadata' => $data['attachment_metadata'] ?? null,
            'metadata' => array_merge($data['metadata'] ?? [], [
                'client_ip' => $data['client_ip'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
                'retry' => $data['retry'] ?? false,
                'original_id' => $data['original_id'] ?? null,
                'sender_name' => $data['sender_name'] ?? null,
                'type' => $data['type'] ?? null, // WhatsApp provider type
                'attachment' => $data['attachment'] ?? null,
                'attachment_metadata' => $data['attachment_metadata'] ?? null,
            ]),
        ];
    }

    /**
     * Store notification log
     */
    protected function storeNotificationLog($messageId, $data)
    {
        return DB::table('notification_logs')->insertGetId([
            'id' => $messageId,
            'channel' => $data['channel'],
            'to' => $data['to'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'template_id' => $data['template_id'],
            'template_data' => json_encode($data['template_data']),
            'priority' => $data['priority'],
            'status' => 'queued',
            'scheduled_at' => $data['scheduled_at'],
            'client_webhook_url' => $data['webhook_url'],
            'tags' => json_encode($data['tags']),
            'metadata' => json_encode($data['metadata']),
            'retry_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Update notification log
     */
    protected function updateNotificationLog($logId, $result, $provider)
    {
        // $result is ProviderResponse::toArray() (+ send_time): success, message_id, error, ...
        $status = !empty($result['success']) ? 'sent' : 'failed';

        $updateData = [
            'status' => $status,
            'provider' => $provider,
            'provider_message_id' => $result['message_id'] ?? null,
            'provider_response' => json_encode($result),
            'error' => $result['error'] ?? null,
            'updated_at' => now(),
        ];

        if ($status === 'sent') {
            $updateData['sent_at'] = now();
        } else {
            $updateData['failed_at'] = now();
        }

        try {
            DB::table('notification_logs')
                ->where('id', $logId)
                ->update($updateData);
        } catch (\Exception $e) {
            // Audit logging must never break or duplicate a send.
            Log::warning('Failed to update notification log', [
                'log_id' => $logId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Select provider for notification
     */
    protected function selectProvider($channel, $preferredProvider = null, $data = [])
    {
        // Only honour a preferred provider that actually serves this channel
        // (e.g. never route an SMS through 'wasender').
        $supportedChannels = config("notification.providers.{$preferredProvider}.channels", []);

        if ($preferredProvider && in_array($channel, $supportedChannels, true)) {
            // Check if preferred provider is available
            if ($this->failoverService->isProviderAvailable($preferredProvider)) {
                return $preferredProvider;
            }
        }

        // Handle WhatsApp type-based routing
        if ($channel === 'whatsapp') {
            return $this->selectWhatsAppProvider($data);
        }

        // Use failover service to select best available provider
        return $this->failoverService->selectProvider($channel);
    }

    /**
     * Select WhatsApp provider based on type
     */
    protected function selectWhatsAppProvider($data = [])
    {
        // Get the type from data metadata
        $type = $data['metadata']['type'] ?? 'official'; // Default to official
        
        if ($type === 'wasender') {
            // Force wasender even if health check fails (for user choice)
            $wasenderConfig = config('notification.providers.wasender', []);
            if (!empty($wasenderConfig) && ($wasenderConfig['enabled'] ?? false)) {
                return 'wasender';
            }
        }
        
        // For official or fallback, try whatsapp first
        $whatsappConfig = config('notification.providers.whatsapp', []);
        if (!empty($whatsappConfig) && ($whatsappConfig['enabled'] ?? false)) {
            return 'whatsapp';
        }
        
        // Ultimate fallback - return any available provider
        return $this->failoverService->selectProvider('whatsapp');
    }

    /**
     * Send notification via provider
     */
    protected function sendNotification($provider, $data)
    {
        $startTime = microtime(true);
        
        try {
            $adapter = $this->getProviderAdapter($provider);
            $result = $adapter->send(
                $data['to'],
                $data['message'],
                $data['subject'],
                $data['metadata']
            );
            
            $sendTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
            
            return array_merge($result->toArray(), [
                'send_time' => $sendTime,
            ]);
            
        } catch (\Exception $e) {
            $sendTime = (microtime(true) - $startTime) * 1000;
            
            // Mark provider as failed
            $this->failoverService->recordProviderFailure($provider, $e->getMessage());
            
            throw $e;
        }
    }

    /**
     * Get provider adapter
     */
    protected function getProviderAdapter($provider)
    {
        $config = config("notification.providers.{$provider}", []);
        
        return match($provider) {
            'beem' => new \App\Services\Adapters\SmsAdapter($config, 'beem'),
            'termii' => new \App\Services\Adapters\SmsAdapter($config, 'termii'),
            'twilio' => new \App\Services\Adapters\SmsAdapter($config, 'twilio'),
            'whatsapp' => new \App\Services\Adapters\WhatsAppAdapter($config, 'whatsapp'),
            'wasender' => new \App\Services\Adapters\WhatsAppAdapter($config, 'wasender'),
            'resend' => new \App\Services\Adapters\EmailAdapter($config, 'resend'),
            'sendgrid' => new \App\Services\Adapters\EmailAdapter($config, 'sendgrid'),
            'mailgun' => new \App\Services\Adapters\EmailAdapter($config, 'mailgun'),
            default => throw new \Exception("Unsupported provider: {$provider}"),
        };
    }

    /**
     * Send bulk notifications
     */
    public function sendBulk($notifications)
    {
        $results = [];
        $batchId = Str::uuid();
        
        Log::info('Starting bulk notification send', [
            'batch_id' => $batchId,
            'count' => count($notifications),
        ]);
        
        foreach ($notifications as $notification) {
            try {
                $notification['metadata'] = array_merge($notification['metadata'] ?? [], [
                    'batch_id' => $batchId,
                ]);
                
                $result = $this->send($notification);
                $results[] = [
                    'success' => true,
                    'message_id' => $result['message_id'],
                    'status' => $result['status'],
                ];
                
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'notification' => $notification,
                ];
            }
        }
        
        Log::info('Bulk notification send completed', [
            'batch_id' => $batchId,
            'total' => count($notifications),
            'successful' => collect($results)->where('success', true)->count(),
            'failed' => collect($results)->where('success', false)->count(),
        ]);
        
        return [
            'batch_id' => $batchId,
            'total' => count($notifications),
            'results' => $results,
            'summary' => [
                'successful' => collect($results)->where('success', true)->count(),
                'failed' => collect($results)->where('success', false)->count(),
            ],
        ];
    }

    /**
     * Schedule notification
     */
    public function schedule($data, $scheduledAt)
    {
        $data['scheduled_at'] = $scheduledAt;
        
        // For now, just store it - in production you'd use a queue system
        return $this->send($data);
    }

    /**
     * Get notification status
     */
    public function getStatus($messageId)
    {
        return DB::table('notification_logs')
            ->where('id', $messageId)
            ->first();
    }

    /**
     * Cancel scheduled notification
     */
    public function cancel($messageId)
    {
        return DB::table('notification_logs')
            ->where('id', $messageId)
            ->where('status', 'queued')
            ->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);
    }

    /**
     * Get provider for specific country
     */
    public function getProviderForCountry($channel, $countryCode)
    {
        $channelConfig = config("notification.channels.{$channel}", []);
        $providers = $channelConfig['providers'] ?? [];
        $countryCode = strtolower($countryCode);
        
        // Find providers that support this country
        $availableProviders = [];
        
        foreach ($providers as $provider) {
            $providerConfig = config("notification.providers.{$provider}", []);
            $countries = array_map('strtolower', $providerConfig['countries'] ?? []);
            
            if (in_array($countryCode, $countries)) {
                $availableProviders[] = [
                    'provider' => $provider,
                    'priority' => $providerConfig['priority'] ?? 0
                ];
            }
        }
        
        // Sort by priority (highest first)
        usort($availableProviders, function($a, $b) {
            return $b['priority'] - $a['priority'];
        });
        
        // Return highest priority provider for this country
        return !empty($availableProviders) ? $availableProviders[0]['provider'] : null;
    }
}
