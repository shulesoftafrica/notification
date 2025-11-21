<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;
use App\Models\Message;

class DispatchMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;
    public $backoff = [30, 60, 120]; // Exponential backoff in seconds

    protected $messageData;
    protected $messageId;
    protected $priority;

    /**
     * Create a new job instance.
     */
    public function __construct(array $messageData, ?int $messageId = null, string $priority = 'normal')
    {
        $this->messageData = $messageData;
        $this->messageId = $messageId;
        $this->priority = $priority;
        
        // Set queue priority
      //  $this->onQueue($this->getQueueName($priority));
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        $startTime = microtime(true);
        
        try {
            Log::info('Processing message dispatch job', [
                'message_id' => $this->messageId,
                'channel' => $this->messageData['channel'] ?? $this->messageData['type'],
                'priority' => $this->priority,
                'attempt' => $this->attempts()
            ]);

            // Update message status to sending if we have a message ID
            if ($this->messageId) {
                $this->updateMessageStatus('sending');
            }

            // Send the notification using unified service method (same as controller)
            $result = $notificationService->send([
                'channel' => $this->messageData['channel'] ?? $this->messageData['type'],
                'to' => $this->messageData['to'],
                'subject' => $this->messageData['subject'] ?? null,
                'message' => $this->messageData['message'],
                'template_id' => $this->messageData['template_id'] ?? null,
                'priority' => $this->priority,
                'metadata' => $this->messageData['metadata'] ?? [],
                'provider' => $this->messageData['provider'] ?? null,
                'sender_name' => $this->messageData['sender_name'] ?? null,
                'type' => $this->messageData['whatsapp_type'] ?? $this->messageData['type'] ?? null,
                'webhook_url' => $this->messageData['webhook_url'] ?? null,
            ]);

            // Update message with results
            if ($this->messageId) {
                $this->updateMessageWithResult($result, $startTime);
            }

            Log::info('Message dispatch completed successfully', [
                'message_id' => $this->messageId,
                'provider' => $result['provider'] ?? null,
                'external_id' => $result['message_id'] ?? null,
                'duration_ms' => (int) round((microtime(true) - $startTime) * 1000)
            ]);

        } catch (\Exception $e) {
            $this->handleDispatchFailure($e, $startTime);
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Update message status in database
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
     * Update message with dispatch result
     */
    protected function updateMessageWithResult(array $result, float $startTime): void
    {
        if (!$this->messageId) {
            return;
        }

        $duration = (int) round((microtime(true) - $startTime) * 1000);

        try {
            // Update message with result (same as controller logic)
            $updateData = [
                'status' => $result['status'] ?? 'failed',
                'provider' => $result['provider'] ?? null,
                'external_id' => $result['message_id'] ?? null,
                'sent_at' => ($result['status'] ?? 'failed') === 'sent' ? now() : null,
                'failed_at' => ($result['status'] ?? 'failed') === 'failed' ? now() : null,
                'error_message' => $result['error'] ?? null,
                'duration_ms' => $duration,
                'updated_at' => now()
            ];

            Message::where('id', $this->messageId)->update($updateData);

            // Dispatch webhook if configured
            if (($result['status'] ?? 'failed') === 'sent' && !empty($this->messageData['webhook_url'])) {
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
     * Handle dispatch failure
     */
    protected function handleDispatchFailure(\Exception $e, float $startTime): void
    {
        $duration = (int) round((microtime(true) - $startTime) * 1000);

        Log::error('Message dispatch failed', [
            'message_id' => $this->messageId,
            'channel' => $this->messageData['channel'] ?? $this->messageData['type'] ?? 'unknown',
            'attempt' => $this->attempts(),
            'max_tries' => $this->tries,
            'error' => $e->getMessage(),
            'duration_ms' => $duration
        ]);

        if ($this->messageId) {
            try {
                Message::where('id', $this->messageId)->update([
                    'status' => $this->attempts() >= $this->tries ? 'failed' : 'pending',
                    'retry_count' => $this->attempts(),
                    'error_message' => $e->getMessage(),
                    'failed_at' => $this->attempts() >= $this->tries ? now() : null,
                    'duration_ms' => $duration,
                    'updated_at' => now()
                ]);

                // Dispatch webhook for final failure
                if ($this->attempts() >= $this->tries && !empty($this->messageData['webhook_url'])) {
                    DeliverWebhook::dispatch($this->messageId, 'failed', [
                        'error' => $e->getMessage(),
                        'attempts' => $this->attempts()
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
            'normal' => 'notifications',
            'low' => 'notifications-low',
            default => 'notifications'
        };
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Message dispatch job failed permanently', [
            'message_id' => $this->messageId,
            'channel' => $this->messageData['channel'] ?? $this->messageData['type'] ?? 'unknown',
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage()
        ]);

        // Mark message as permanently failed
        if ($this->messageId) {
            try {
                Message::where('id', $this->messageId)->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'error_message' => 'Job failed permanently: ' . $exception->getMessage(),
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
