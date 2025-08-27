<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\Adapters\EmailAdapter;
use App\Services\Adapters\SmsAdapter;
use App\Services\Adapters\WhatsAppAdapter;
use App\Services\TemplateRenderer;
use App\Services\ProviderFailoverService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class DispatchMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300;
    public $backoff = [30, 60, 120]; // Exponential backoff in seconds

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Message $message
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ProviderFailoverService $failoverService): void
    {
        try {
            Log::info('Processing message with failover', [
                'message_id' => $this->message->message_id,
                'channel' => $this->message->channel,
                'project_id' => $this->message->project_id,
                'tenant_id' => $this->message->tenant_id,
                'attempt' => $this->attempts()
            ]);

            // Update status to processing
            $this->message->update([
                'status' => 'processing',
                'attempt_count' => $this->message->attempt_count + 1,
                'last_attempted_at' => now()
            ]);

            // Render template if template_id is provided
            $renderedContent = $this->renderTemplate();

            // Send with automatic failover
            $result = $failoverService->sendWithFailover(
                $this->message,
                $renderedContent,
                3 // Max 3 provider attempts per job attempt
            );

            if ($result['success']) {
                // Message sent successfully
                $this->handleSuccess($result);
            } else {
                // All providers failed
                $this->handleFailure($result);
            }

        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Handle successful message delivery
     */
    private function handleSuccess(array $result): void
    {
        $provider = $result['provider'];
        $response = $result['response'];
        
        $this->message->update([
            'status' => 'sent',
            'provider_message_id' => $response->getProviderMessageId(),
            'provider_name' => $provider->provider,
            'provider_id' => $provider->id,
            'cost_amount' => $response->getCost(),
            'cost_currency' => 'USD',
            'sent_at' => now(),
            'provider_response' => $response->getResponse(),
            'metadata' => array_merge($this->message->metadata ?? [], [
                'provider_attempts' => $result['attempts'],
                'final_provider' => [
                    'id' => $provider->id,
                    'name' => $provider->provider,
                    'response_time' => $response->getResponseTime()
                ],
                'job_attempts' => $this->attempts()
            ])
        ]);

        Log::info('Message sent successfully with failover', [
            'message_id' => $this->message->message_id,
            'provider_id' => $provider->id,
            'provider_name' => $provider->provider,
            'provider_message_id' => $response->getProviderMessageId(),
            'job_attempts' => $this->attempts(),
            'provider_attempts' => count($result['attempts'])
        ]);
    }

    /**
     * Handle message delivery failure
     */
    private function handleFailure(array $result): void
    {
        $lastAttempt = end($result['attempts']);
        $errorMessage = $result['error'] ?? 'All providers failed';
        
        if ($lastAttempt) {
            $errorMessage = $lastAttempt['error'] ?? $errorMessage;
        }

        if ($this->attempts() >= $this->tries) {
            // Final failure - mark as permanently failed
            $this->message->update([
                'status' => 'failed',
                'failure_reason' => $errorMessage,
                'failed_at' => now(),
                'metadata' => array_merge($this->message->metadata ?? [], [
                    'provider_attempts' => $result['attempts'],
                    'final_error' => $errorMessage,
                    'job_attempts' => $this->attempts()
                ])
            ]);

            Log::error('Message permanently failed after all retries', [
                'message_id' => $this->message->message_id,
                'error' => $errorMessage,
                'total_job_attempts' => $this->attempts(),
                'provider_attempts' => $result['attempts']
            ]);
        } else {
            // Temporary failure - will be retried
            $this->message->update([
                'status' => 'queued',
                'metadata' => array_merge($this->message->metadata ?? [], [
                    'last_error' => $errorMessage,
                    'provider_attempts' => $result['attempts'],
                    'job_attempts' => $this->attempts()
                ])
            ]);

            Log::warning('Message failed, will retry job', [
                'message_id' => $this->message->message_id,
                'error' => $errorMessage,
                'current_attempt' => $this->attempts(),
                'max_attempts' => $this->tries
            ]);

            // Throw exception to trigger job retry
            throw new Exception($errorMessage);
        }
    }

    /**
     * Handle job exception
     */
    private function handleException(Exception $e): void
    {
        Log::error('Message dispatch exception', [
            'message_id' => $this->message->message_id,
            'error' => $e->getMessage(),
            'attempts' => $this->attempts()
        ]);

        if ($this->attempts() >= $this->tries) {
            // Final failure
            $this->message->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
                'failed_at' => now(),
                'metadata' => array_merge($this->message->metadata ?? [], [
                    'job_attempts' => $this->attempts(),
                    'exception' => $e->getMessage()
                ])
            ]);

            Log::error('Message permanently failed after job retries', [
                'message_id' => $this->message->message_id,
                'attempts' => $this->attempts(),
                'error' => $e->getMessage()
            ]);
        } else {
            // Update status back to queued for retry
            $this->message->update(['status' => 'queued']);
        }

        // Re-throw to trigger retry mechanism
        throw $e;
    }

    /**
     * Render template if template_id is provided
     */
    private function renderTemplate(): array
    {
        if (!$this->message->template_id) {
            // Use content directly from message
            return [
                'subject' => $this->message->recipient['subject'] ?? '',
                'content' => $this->message->recipient['content'] ?? $this->message->recipient['text'] ?? '',
                'html_content' => $this->message->recipient['html'] ?? null
            ];
        }

        // Use template renderer service
        $renderer = app(TemplateRenderer::class);
        return $renderer->render(
            $this->message->template_id,
            $this->message->project_id,
            $this->message->tenant_id,
            $this->message->variables ?? []
        );
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        Log::error('DispatchMessage job failed permanently', [
            'message_id' => $this->message->message_id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        $this->message->update([
            'status' => 'failed',
            'failure_reason' => $exception->getMessage(),
            'failed_at' => now(),
            'metadata' => array_merge($this->message->metadata ?? [], [
                'job_failed' => true,
                'job_attempts' => $this->attempts()
            ])
        ]);
    }
}
