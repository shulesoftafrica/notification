<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Message;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 5;
    public $backoff = [30, 60, 180, 600, 1800]; // Progressive backoff

    protected $messageId;
    protected $event;
    protected $data;
    protected $webhookUrl;

    /**
     * Create a new job instance.
     */
    public function __construct(int $messageId, string $event, array $data = [])
    {
        $this->messageId = $messageId;
        $this->event = $event;
        $this->data = $data;
        
        // Set to webhook queue
        $this->onQueue('webhooks');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        
        try {
            // Get message and webhook URL
            $message = Message::find($this->messageId);
            
            if (!$message) {
                Log::warning('Message not found for webhook delivery', ['message_id' => $this->messageId]);
                return;
            }

            $this->webhookUrl = $message->webhook_url;
            
            if (!$this->webhookUrl) {
                Log::warning('No webhook URL configured for message', ['message_id' => $this->messageId]);
                return;
            }

            Log::info('Delivering webhook', [
                'message_id' => $this->messageId,
                'event' => $this->event,
                'webhook_url' => $this->webhookUrl,
                'attempt' => $this->attempts()
            ]);

            // Prepare webhook payload
            $payload = $this->prepareWebhookPayload($message);

            // Deliver webhook
            $response = $this->deliverWebhook($payload);

            // Update webhook delivery status
            $this->updateWebhookStatus($message, true, $response, $startTime);

            Log::info('Webhook delivered successfully', [
                'message_id' => $this->messageId,
                'webhook_url' => $this->webhookUrl,
                'response_status' => $response['status'],
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ]);

        } catch (\Exception $e) {
            $this->handleWebhookFailure($e, $startTime);
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Prepare webhook payload
     */
    protected function prepareWebhookPayload(Message $message): array
    {
        $payload = [
            'event' => $this->event,
            'message_id' => $message->id,
            'external_id' => $message->external_id,
            'type' => $message->type,
            'recipient' => $message->recipient,
            'status' => $message->status,
            'provider' => $message->provider,
            'sent_at' => $message->sent_at?->toISOString(),
            'delivered_at' => $message->delivered_at?->toISOString(),
            'failed_at' => $message->failed_at?->toISOString(),
            'timestamp' => now()->toISOString(),
            'metadata' => $message->metadata ?? [],
        ];

        // Add event-specific data
        if (!empty($this->data)) {
            $payload['event_data'] = $this->data;
        }

        // Add error information for failed events
        if ($this->event === 'failed' && $message->error_message) {
            $payload['error'] = [
                'message' => $message->error_message,
                'retry_count' => $message->retry_count
            ];
        }

        // Add delivery information for delivered events
        if ($this->event === 'delivered') {
            $payload['delivery'] = [
                'delivered_at' => $message->delivered_at?->toISOString(),
                'duration_ms' => $message->duration_ms
            ];
        }

        // Generate signature for webhook verification
        $payload['signature'] = $this->generateWebhookSignature($payload);

        return $payload;
    }

    /**
     * Deliver webhook to endpoint
     */
    protected function deliverWebhook(array $payload): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'NotificationService/1.0',
            'X-Webhook-Event' => $this->event,
            'X-Message-ID' => $this->messageId,
            'X-Delivery-Attempt' => $this->attempts(),
            'X-Webhook-Signature' => $payload['signature'],
            'X-Timestamp' => now()->toISOString()
        ];

        $response = Http::withHeaders($headers)
            ->timeout($this->timeout)
            ->retry(2, 1000) // Retry 2 times with 1 second delay
            ->post($this->webhookUrl, $payload);

        if (!$response->successful()) {
            throw new \Exception(
                "Webhook delivery failed with status {$response->status()}: " . $response->body()
            );
        }

        return [
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body' => $response->body(),
            'response_time_ms' => $response->transferStats?->getTotalTime() * 1000 ?? null
        ];
    }

    /**
     * Generate webhook signature for verification
     */
    protected function generateWebhookSignature(array $payload): string
    {
        // Remove signature field before generating signature
        unset($payload['signature']);
        
        $data = json_encode($payload, JSON_SORT_KEYS | JSON_UNESCAPED_SLASHES);
        $secret = config('app.webhook_secret', config('app.key'));
        
        return 'sha256=' . hash_hmac('sha256', $data, $secret);
    }

    /**
     * Update webhook delivery status
     */
    protected function updateWebhookStatus(Message $message, bool $success, ?array $response = null, ?float $startTime = null): void
    {
        try {
            $updateData = [
                'webhook_delivered' => $success,
                'webhook_attempts' => $this->attempts(),
                'updated_at' => now()
            ];

            if ($response) {
                $updateData['webhook_response'] = [
                    'status' => $response['status'],
                    'delivered_at' => now()->toISOString(),
                    'response_time_ms' => $startTime ? round((microtime(true) - $startTime) * 1000, 2) : null
                ];
            }

            Message::where('id', $this->messageId)->update($updateData);

        } catch (\Exception $e) {
            Log::error('Failed to update webhook status', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle webhook delivery failure
     */
    protected function handleWebhookFailure(\Exception $e, float $startTime): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        Log::error('Webhook delivery failed', [
            'message_id' => $this->messageId,
            'webhook_url' => $this->webhookUrl,
            'event' => $this->event,
            'attempt' => $this->attempts(),
            'max_tries' => $this->tries,
            'error' => $e->getMessage(),
            'duration_ms' => $duration
        ]);

        try {
            $message = Message::find($this->messageId);
            if ($message) {
                $isFinalFailure = $this->attempts() >= $this->tries;
                
                $updateData = [
                    'webhook_delivered' => false,
                    'webhook_attempts' => $this->attempts(),
                    'webhook_error' => $e->getMessage(),
                    'updated_at' => now()
                ];

                if ($isFinalFailure) {
                    $updateData['webhook_failed_at'] = now();
                }

                Message::where('id', $this->messageId)->update($updateData);
            }

        } catch (\Exception $updateError) {
            Log::error('Failed to update webhook failure status', [
                'message_id' => $this->messageId,
                'error' => $updateError->getMessage()
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Webhook delivery job failed permanently', [
            'message_id' => $this->messageId,
            'webhook_url' => $this->webhookUrl,
            'event' => $this->event,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage()
        ]);

        try {
            Message::where('id', $this->messageId)->update([
                'webhook_delivered' => false,
                'webhook_failed_at' => now(),
                'webhook_error' => 'Webhook delivery failed permanently: ' . $exception->getMessage(),
                'updated_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark webhook as permanently failed', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return $this->backoff;
    }
}
