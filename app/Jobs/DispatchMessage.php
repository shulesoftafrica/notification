<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\ProviderConfig;
use App\Services\Adapters\EmailAdapter;
use App\Services\Adapters\SmsAdapter;
use App\Services\Adapters\WhatsAppAdapter;
use App\Services\TemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
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
    public function handle(): void
    {
        try {
            Log::info('Processing message', [
                'message_id' => $this->message->message_id,
                'channel' => $this->message->channel,
                'project_id' => $this->message->project_id,
                'tenant_id' => $this->message->tenant_id
            ]);

            // Update status to processing
            $this->message->update([
                'status' => 'processing',
                'attempt_count' => $this->message->attempt_count + 1,
                'last_attempted_at' => now()
            ]);

            // Get provider configuration
            $providerConfig = $this->getProviderConfig();
            if (!$providerConfig) {
                throw new Exception('No active provider configuration found for channel: ' . $this->message->channel);
            }

            // Render template if template_id is provided
            $renderedContent = $this->renderTemplate();

            // Get appropriate adapter
            $adapter = $this->getAdapter($this->message->channel, $providerConfig);

            // Send message through adapter
            $result = $adapter->send($this->message, $renderedContent, $providerConfig);

            if ($result->isSuccess()) {
                // Update message with success
                $this->message->update([
                    'status' => 'sent',
                    'provider_message_id' => $result->getProviderMessageId(),
                    'provider_name' => $providerConfig->provider,
                    'cost_amount' => $result->getCost(),
                    'cost_currency' => 'USD',
                    'sent_at' => now(),
                    'provider_response' => $result->getResponse()
                ]);

                Log::info('Message sent successfully', [
                    'message_id' => $this->message->message_id,
                    'provider_message_id' => $result->getProviderMessageId(),
                    'provider' => $providerConfig->provider
                ]);
            } else {
                throw new Exception('Provider failed to send message: ' . $result->getError());
            }

        } catch (Exception $e) {
            Log::error('Message dispatch failed', [
                'message_id' => $this->message->message_id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            // If we've exhausted all retries, mark as failed
            if ($this->attempts() >= $this->tries) {
                $this->message->update([
                    'status' => 'failed',
                    'failure_reason' => $e->getMessage(),
                    'failed_at' => now()
                ]);
                
                Log::error('Message permanently failed after retries', [
                    'message_id' => $this->message->message_id,
                    'attempts' => $this->attempts()
                ]);
            } else {
                // Update status back to queued for retry
                $this->message->update(['status' => 'queued']);
            }

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Get provider configuration for the message
     */
    private function getProviderConfig(): ?ProviderConfig
    {
        return ProviderConfig::where('project_id', $this->message->project_id)
            ->where('tenant_id', $this->message->tenant_id)
            ->where('channel', $this->message->channel)
            ->where('enabled', true)
            ->orderBy('priority')
            ->first();
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
     * Get appropriate adapter for channel
     */
    private function getAdapter(string $channel, ProviderConfig $config)
    {
        return match($channel) {
            'email' => app(EmailAdapter::class),
            'sms' => app(SmsAdapter::class),
            'whatsapp' => app(WhatsAppAdapter::class),
            default => throw new Exception('Unsupported channel: ' . $channel)
        };
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
            'failed_at' => now()
        ]);
    }
}
