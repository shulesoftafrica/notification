<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;
use App\Services\ProviderFailoverService;
use App\Models\Message;

class DispatchMessageWithFailover implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 180;
    public $tries = 5;
    public $backoff = [30, 60, 120, 300, 600]; // Progressive backoff

    protected $messageData;
    protected $messageId;
    protected $priority;
    protected $attemptedProviders = [];

    /**
     * Create a new job instance.
     */
    public function __construct(array $messageData, ?int $messageId = null, string $priority = 'normal')
    {
        $this->messageData = $messageData;
        $this->messageId = $messageId;
        $this->priority = $priority;
        
        // Set queue based on priority
        $this->onQueue($this->getQueueName($priority));
    }

    /**
     * Execute the job with failover capability.
     */
    public function handle(NotificationService $notificationService, ProviderFailoverService $failoverService): void
    {
        $startTime = microtime(true);
        
        try {
            Log::info('Processing message with failover', [
                'message_id' => $this->messageId,
                'type' => $this->messageData['type'],
                'attempt' => $this->attempts(),
                'attempted_providers' => $this->attemptedProviders
            ]);

            // Update message status
            if ($this->messageId) {
                $this->updateMessageStatus('sending');
            }

            // Attempt to send with failover
            $result = $this->attemptSendWithFailover($notificationService, $failoverService);

            // Update message with successful result
            if ($this->messageId) {
                $this->updateMessageWithResult($result, $startTime);
            }

            Log::info('Message sent successfully with failover', [
                'message_id' => $this->messageId,
                'provider' => $result['provider'],
                'failover_occurred' => $result['failover_occurred'] ?? false,
                'external_id' => $result['message_id'],
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ]);

        } catch (\Exception $e) {
            $this->handleFailoverFailure($e, $startTime, $failoverService);
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Attempt to send message with automatic failover
     */
    protected function attemptSendWithFailover(NotificationService $notificationService, ProviderFailoverService $failoverService): array
    {
        $type = $this->messageData['type'];
        $providers = $failoverService->getAvailableProviders($type);
        
        // Remove already attempted providers
        $providers = array_diff($providers, $this->attemptedProviders);
        
        if (empty($providers)) {
            throw new \Exception('No available providers for failover');
        }

        $lastError = null;

        foreach ($providers as $provider) {
            try {
                Log::info("Attempting to send via {$provider}", [
                    'message_id' => $this->messageId,
                    'type' => $type,
                    'provider' => $provider
                ]);

                // Check provider health before attempting
                if (!$failoverService->isProviderHealthy($provider)) {
                    Log::warning("Skipping unhealthy provider: {$provider}");
                    $this->attemptedProviders[] = $provider;
                    continue;
                }

                $result = $this->sendViaProvider($notificationService, $provider);
                
                // Mark successful provider
                $failoverService->recordSuccessfulSend($provider, $type);
                
                // Add failover information to result
                $result['failover_occurred'] = !empty($this->attemptedProviders);
                $result['attempted_providers'] = $this->attemptedProviders;
                $result['successful_provider'] = $provider;

                return $result;

            } catch (\Exception $e) {
                $this->attemptedProviders[] = $provider;
                $lastError = $e;
                
                Log::warning("Provider {$provider} failed", [
                    'message_id' => $this->messageId,
                    'error' => $e->getMessage()
                ]);

                // Record provider failure
                $failoverService->recordProviderFailure($provider, $type, $e->getMessage());
                
                // Continue to next provider
                continue;
            }
        }

        // If we get here, all providers failed
        throw new \Exception(
            'All providers failed. Last error: ' . ($lastError ? $lastError->getMessage() : 'Unknown error'),
            0,
            $lastError
        );
    }

    /**
     * Send message via specific provider
     */
    protected function sendViaProvider(NotificationService $notificationService, string $provider): array
    {
        $type = $this->messageData['type'];
        $recipient = $this->messageData['to'];
        $message = $this->messageData['message'];
        $metadata = $this->messageData['metadata'] ?? [];

        switch ($type) {
            case 'sms':
                return $notificationService->sendSms($recipient, $message, $metadata, $provider);

            case 'email':
                return $notificationService->sendEmail(
                    $recipient,
                    $this->messageData['subject'],
                    $message,
                    $metadata,
                    $provider
                );

            case 'whatsapp':
                return $notificationService->sendWhatsApp($recipient, $message, $metadata, $provider);

            default:
                throw new \InvalidArgumentException("Unsupported message type: {$type}");
        }
    }

    /**
     * Update message status
     */
    protected function updateMessageStatus(string $status): void
    {
        if (!$this->messageId) {
            return;
        }

        try {
            Message::where('id', $this->messageId)->update([
                'status' => $status,
                'updated_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to update message status', [
                'message_id' => $this->messageId,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update message with successful result
     */
    protected function updateMessageWithResult(array $result, float $startTime): void
    {
        if (!$this->messageId) {
            return;
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        try {
            $updateData = [
                'status' => 'sent',
                'provider' => $result['provider'],
                'external_id' => $result['message_id'],
                'duration_ms' => $duration,
                'sent_at' => now(),
                'retry_count' => $this->attempts() - 1,
                'metadata' => array_merge(
                    $this->messageData['metadata'] ?? [],
                    [
                        'failover_occurred' => $result['failover_occurred'] ?? false,
                        'attempted_providers' => $this->attemptedProviders,
                        'successful_provider' => $result['successful_provider']
                    ]
                ),
                'updated_at' => now()
            ];

            Message::where('id', $this->messageId)->update($updateData);

            // Dispatch webhook notification
            if (!empty($this->messageData['webhook_url'])) {
                DeliverWebhook::dispatch($this->messageId, 'sent', $result);
            }

        } catch (\Exception $e) {
            Log::error('Failed to update message with result', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle failover failure
     */
    protected function handleFailoverFailure(\Exception $e, float $startTime, ProviderFailoverService $failoverService): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        Log::error('Message failover attempt failed', [
            'message_id' => $this->messageId,
            'type' => $this->messageData['type'],
            'attempt' => $this->attempts(),
            'max_tries' => $this->tries,
            'attempted_providers' => $this->attemptedProviders,
            'error' => $e->getMessage(),
            'duration_ms' => $duration
        ]);

        if ($this->messageId) {
            try {
                $isFinalFailure = $this->attempts() >= $this->tries;
                
                Message::where('id', $this->messageId)->update([
                    'status' => $isFinalFailure ? 'failed' : 'pending',
                    'retry_count' => $this->attempts(),
                    'error_message' => $e->getMessage(),
                    'failed_at' => $isFinalFailure ? now() : null,
                    'duration_ms' => $duration,
                    'metadata' => array_merge(
                        $this->messageData['metadata'] ?? [],
                        [
                            'attempted_providers' => $this->attemptedProviders,
                            'final_failure' => $isFinalFailure
                        ]
                    ),
                    'updated_at' => now()
                ]);

                // Send webhook notification for final failure
                if ($isFinalFailure && !empty($this->messageData['webhook_url'])) {
                    DeliverWebhook::dispatch($this->messageId, 'failed', [
                        'error' => $e->getMessage(),
                        'attempts' => $this->attempts(),
                        'attempted_providers' => $this->attemptedProviders
                    ]);
                }

            } catch (\Exception $updateError) {
                Log::error('Failed to update message failure status', [
                    'message_id' => $this->messageId,
                    'error' => $updateError->getMessage()
                ]);
            }
        }
    }

    /**
     * Get queue name based on priority
     */
    protected function getQueueName(string $priority): string
    {
        return match ($priority) {
            'urgent' => 'notifications-urgent',
            'high' => 'notifications-high',
            'normal' => 'notifications-failover',
            'low' => 'notifications-low',
            default => 'notifications-failover'
        };
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Message failover job failed permanently', [
            'message_id' => $this->messageId,
            'type' => $this->messageData['type'],
            'attempts' => $this->attempts(),
            'attempted_providers' => $this->attemptedProviders,
            'error' => $exception->getMessage()
        ]);

        if ($this->messageId) {
            try {
                Message::where('id', $this->messageId)->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'error_message' => 'Failover job failed permanently: ' . $exception->getMessage(),
                    'metadata' => array_merge(
                        $this->messageData['metadata'] ?? [],
                        [
                            'attempted_providers' => $this->attemptedProviders,
                            'permanent_failure' => true
                        ]
                    ),
                    'updated_at' => now()
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to mark message as permanently failed', [
                    'message_id' => $this->messageId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
