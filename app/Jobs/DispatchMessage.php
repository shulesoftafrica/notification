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
        $this->onQueue($this->getQueueName($priority));
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
                'type' => $this->messageData['type'],
                'priority' => $this->priority,
                'attempt' => $this->attempts()
            ]);

            // Update message status to sending if we have a message ID
            if ($this->messageId) {
                $this->updateMessageStatus('sending');
            }

            // Dispatch the message
            $result = $this->dispatchMessage($notificationService);

            // Update message with results
            if ($this->messageId) {
                $this->updateMessageWithResult($result, $startTime);
            }

            Log::info('Message dispatch completed successfully', [
                'message_id' => $this->messageId,
                'provider' => $result['provider'],
                'external_id' => $result['message_id'] ?? null,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ]);

        } catch (\Exception $e) {
            $this->handleDispatchFailure($e, $startTime);
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Dispatch the message using the notification service
     */
    protected function dispatchMessage(NotificationService $notificationService): array
    {
        $type = $this->messageData['type'];
        $recipient = $this->messageData['to'];
        $message = $this->messageData['message'];

        switch ($type) {
            case 'sms':
                return $notificationService->sendSms(
                    $recipient,
                    $message,
                    $this->messageData['metadata'] ?? [],
                    $this->messageData['provider'] ?? null
                );

            case 'email':
                return $notificationService->sendEmail(
                    $recipient,
                    $this->messageData['subject'],
                    $message,
                    $this->messageData['metadata'] ?? [],
                    $this->messageData['provider'] ?? null
                );

            case 'whatsapp':
                return $notificationService->sendWhatsApp(
                    $recipient,
                    $message,
                    $this->messageData['metadata'] ?? [],
                    $this->messageData['provider'] ?? null
                );

            default:
                throw new \InvalidArgumentException("Unsupported message type: {$type}");
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

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        try {
            $updateData = [
                'status' => $result['success'] ? 'sent' : 'failed',
                'provider' => $result['provider'] ?? null,
                'external_id' => $result['message_id'] ?? null,
                'duration_ms' => $duration,
                'sent_at' => $result['success'] ? now() : null,
                'failed_at' => !$result['success'] ? now() : null,
                'error_message' => $result['error'] ?? null,
                'updated_at' => now()
            ];

            Message::where('id', $this->messageId)->update($updateData);

            // Dispatch webhook if configured
            if ($result['success'] && !empty($this->messageData['webhook_url'])) {
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
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        Log::error('Message dispatch failed', [
            'message_id' => $this->messageId,
            'type' => $this->messageData['type'],
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
            'type' => $this->messageData['type'],
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
