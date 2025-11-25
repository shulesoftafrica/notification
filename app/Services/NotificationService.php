<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\ProviderFailoverService;
use App\Services\MetricsService;
use App\Services\AnalyticsService;

class NotificationService
{
    protected $failoverService;
    protected $metricsService;
    protected $analyticsService;

    public function __construct(
        ProviderFailoverService $failoverService,
        MetricsService $metricsService,
        AnalyticsService $analyticsService
    ) {
        $this->failoverService = $failoverService;
        $this->metricsService = $metricsService;
        $this->analyticsService = $analyticsService;
    }

    /**
     * Send notification
     */
    public function send($data)
    {
        $messageId = Str::uuid();
      
        try {
            // Validate and prepare data
            $preparedData = $this->prepareNotificationData($data);
            
            // Store notification log
            $logId = $this->storeNotificationLog($messageId, $preparedData);
            
            // Select provider
            $provider = $this->selectProvider($preparedData['channel'], $preparedData['provider'], $preparedData);
            
            // Send notification
            $result = $this->sendNotification($provider, $preparedData);
            
            // Update log with result  
            $this->updateNotificationLog($logId, $result, $provider);
            
            // Track metrics
            $this->metricsService->trackNotificationSent($provider, $preparedData['channel'], [
                'send_time' => $result['send_time'] ?? 0,
            ]);
            
            return [
                'message_id' => $messageId,
                'status' => $result['success'] ? 'sent' : 'failed',
                'provider' => $provider,
                'estimated_delivery' => $result['estimated_delivery'] ?? null,
            ];        } catch (\Exception $e) {
            Log::error('Notification send failed', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            
            // Update log with error
            if (isset($logId)) {
                $this->updateNotificationLog($logId, [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ], $provider ?? null);
            }
            
            throw $e;
        }
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
        // Handle both array and ProviderResponse formats
        if (is_array($result)) {
            $status = $result['status'] ?? 'failed';
            $messageId = $result['provider_message_id'] ?? null;
            $error = $result['error'] ?? null;
            $providerResponse = $result['provider_response'] ?? [];
        } else {
            // Convert ProviderResponse success to status
            $status = isset($result['success']) && $result['success'] ? 'sent' : 'failed';
            $messageId = $result['message_id'] ?? null;
            $error = $result['error'] ?? null;
            $providerResponse = $result;
        }

        $updateData = [
            'status' => $status,
            'provider' => $provider,
            'provider_message_id' => $messageId,
            'provider_response' => json_encode($providerResponse),
            'error' => $error,
            'updated_at' => now(),
        ];

        if ($status === 'sent') {
            $updateData['sent_at'] = now();
        } elseif ($status === 'delivered') {
            $updateData['delivered_at'] = now();
        } elseif ($status === 'failed') {
            $updateData['failed_at'] = now();
        }

        DB::table('notification_logs')
            ->where('id', $logId)
            ->update($updateData);
    }

    /**
     * Select provider for notification
     */
    protected function selectProvider($channel, $preferredProvider = null, $data = [])
    {
        if ($preferredProvider) {
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
