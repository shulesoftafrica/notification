<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;
use App\Services\EmailFilterService;
use App\Models\Message;
use App\Traits\RedisThrottlesQueues;

class DispatchMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, RedisThrottlesQueues;

    // Must stay below the redis queue 'retry_after' (config/queue.php), or a running
    // job is handed to a second worker and the message is sent twice.
    public $timeout = 120;

    // Throttle releases are bounded by retryUntil() instead of a tries count, so a busy
    // provider can't burn through the attempt budget. Real errors are capped here.
    public $maxExceptions = 5;

    public $backoff = [30, 60, 120, 300, 600];

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
     * Give up after the retry window, counted from when the job is due (not dispatched),
     * so messages scheduled far ahead don't expire before their first run.
     */
    public function retryUntil(): \DateTimeInterface
    {
        $due = match (true) {
            $this->delay instanceof \DateTimeInterface => \Illuminate\Support\Carbon::instance($this->delay),
            $this->delay instanceof \DateInterval => now()->add($this->delay),
            is_numeric($this->delay) => now()->addSeconds((int) $this->delay),
            default => now(),
        };

        return $due->addMinutes((int) config('throttling.retry_window_minutes', 360));
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        $startTime = microtime(true);
        $channel = $this->messageData['channel'] ?? $this->messageData['type'];
        $claimed = false;

        try {
            // Drop email to filtered recipients before throttling or sending.
            // This is terminal - no exception, so the job is not retried.
            if ($channel === 'email') {
                $check = app(EmailFilterService::class)->check($this->messageData['to'] ?? null);

                if (!$check['allowed']) {
                    Log::warning('Message dispatch skipped - blocked email recipient', [
                        'message_id' => $this->messageId,
                        'to' => $check['email'],
                        'code' => $check['code'],
                        'reason' => $check['reason'],
                    ]);

                    if ($this->messageId) {
                        Message::where('id', $this->messageId)->update([
                            'status' => 'blocked',
                            'error_message' => $check['reason'],
                            'updated_at' => now(),
                        ]);
                    }

                    return;
                }
            }

            $sendData = $this->buildSendData($channel);

            // Throttle on the provider that will actually be used (after routing/failover).
            $provider = $notificationService->resolveProvider($sendData);

            if ($this->handleQueueThrottling($provider, $channel)) {
                return; // Released with delay; message status stays pending/queued.
            }

            // Atomically claim the message so a duplicate delivery of this job can't send twice.
            if ($this->messageId) {
                if (!$this->claimMessage()) {
                    return;
                }
                $claimed = true;
            }

            Log::debug('Processing message dispatch job', [
                'message_id' => $this->messageId,
                'channel' => $channel,
                'provider' => $provider,
                'priority' => $this->priority,
                'attempt' => $this->attempts(),
            ]);

            $result = $notificationService->send($sendData + ['resolved_provider' => $provider]);

            if ($result['status'] === 'sent') {
                $this->updateMessageWithResult($result, $startTime);

                Log::info('Message dispatch completed successfully', [
                    'message_id' => $this->messageId,
                    'provider' => $result['provider'],
                    'external_id' => $result['provider_message_id'] ?? null,
                    'duration_ms' => (int) round((microtime(true) - $startTime) * 1000)
                ]);

                return;
            }

            if ($result['temporary_failure']) {
                // Retry later with backoff (rate limit, 5xx, network error).
                throw new \RuntimeException("Temporary provider failure ({$result['provider']}): {$result['error']}");
            }

            // Permanent failure (invalid number, bad credentials, ...): retrying won't help.
            $this->fail(new \RuntimeException("Provider rejected message ({$result['provider']}): {$result['error']}"));

        } catch (\Throwable $e) {
            $this->handleDispatchFailure($e, $startTime, $claimed);
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Payload for NotificationService::send()
     */
    protected function buildSendData(string $channel): array
    {
        return [
            'channel' => $channel,
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
            'attachment' => $this->messageData['attachment'] ?? null,
            'attachment_metadata' => $this->messageData['attachment_metadata'] ?? null,
        ];
    }

    /**
     * Move the message to 'sending' only if nobody else has it. A 'sending' row older than
     * the job timeout is a worker that died mid-job and may be taken over.
     */
    protected function claimMessage(): bool
    {
        $staleBefore = now()->subSeconds($this->timeout + 30);

        $claimed = Message::where('id', $this->messageId)
            ->where(function ($query) use ($staleBefore) {
                $query->whereIn('status', ['pending', 'queued'])
                    ->orWhere(function ($query) use ($staleBefore) {
                        $query->where('status', 'sending')->where('updated_at', '<', $staleBefore);
                    });
            })
            ->update(['status' => 'sending', 'updated_at' => now()]);

        if ($claimed) {
            return true;
        }

        Log::warning('Message dispatch skipped - message not claimable', [
            'message_id' => $this->messageId,
            'current_status' => Message::where('id', $this->messageId)->value('status'),
            'attempt' => $this->attempts(),
        ]);

        return false;
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
            Message::where('id', $this->messageId)->update([
                'status' => 'sent',
                'provider' => $result['provider'] ?? null,
                'external_id' => $result['message_id'] ?? null,
                'sent_at' => now(),
                'failed_at' => null,
                'error_message' => null,
                'retry_count' => max(0, $this->attempts() - 1),
                'duration_ms' => $duration,
                'updated_at' => now()
            ]);

            // Dispatch webhook if configured
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
     * Record a failed attempt. Final failure (and its webhook) is handled in failed().
     */
    protected function handleDispatchFailure(\Throwable $e, float $startTime, bool $claimed): void
    {
        $duration = (int) round((microtime(true) - $startTime) * 1000);

        Log::error('Message dispatch failed', [
            'message_id' => $this->messageId,
            'channel' => $this->messageData['channel'] ?? $this->messageData['type'] ?? 'unknown',
            'attempt' => $this->attempts(),
            'error' => $e->getMessage(),
            'error_type' => get_class($e),
            'duration_ms' => $duration
        ]);

        if (!$this->messageId || !$claimed) {
            return;
        }

        try {
            // Hand the message back so the retry can claim it again.
            Message::where('id', $this->messageId)->where('status', 'sending')->update([
                'status' => 'queued',
                'retry_count' => $this->attempts(),
                'error_message' => $e->getMessage(),
                'duration_ms' => $duration,
                'updated_at' => now()
            ]);
        } catch (\Exception $updateError) {
            Log::error('Failed to update message failure status', [
                'message_id' => $this->messageId,
                'error' => $updateError->getMessage()
            ]);
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
     * Handle a job failure (permanent provider rejection, retry window exceeded,
     * too many exceptions, or timeout).
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Message dispatch job failed permanently', [
            'message_id' => $this->messageId,
            'channel' => $this->messageData['channel'] ?? $this->messageData['type'] ?? 'unknown',
            'error' => $exception->getMessage()
        ]);

        if (!$this->messageId) {
            return;
        }

        try {
            // Never downgrade a message that did go out.
            $updated = Message::where('id', $this->messageId)
                ->whereNotIn('status', ['sent', 'delivered'])
                ->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'error_message' => $exception->getMessage(),
                    'updated_at' => now()
                ]);

            if ($updated && !empty($this->messageData['webhook_url'])) {
                DeliverWebhook::dispatch($this->messageId, 'failed', [
                    'error' => $exception->getMessage(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to mark message as permanently failed', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
