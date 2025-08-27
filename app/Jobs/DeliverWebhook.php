<?php

namespace App\Jobs;

use App\Services\ClientWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1; // We handle retries manually with exponential backoff
    public $timeout = 60; // 60 seconds timeout
    public $backoff = 0; // No automatic backoff

    private string $projectId;
    private string $event;
    private array $data;
    private int $attemptNumber;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $projectId,
        string $event,
        array $data,
        int $attemptNumber = 1
    ) {
        $this->projectId = $projectId;
        $this->event = $event;
        $this->data = $data;
        $this->attemptNumber = $attemptNumber;
        
        // Set queue based on priority
        $this->onQueue($this->getQueueName($event));
    }

    /**
     * Execute the job.
     */
    public function handle(ClientWebhookService $webhookService): void
    {
        Log::info('Processing webhook delivery', [
            'project_id' => $this->projectId,
            'event' => $this->event,
            'attempt' => $this->attemptNumber,
            'job_id' => $this->job->getJobId(),
        ]);

        try {
            $success = $webhookService->sendWebhook(
                $this->projectId,
                $this->event,
                $this->data,
                $this->attemptNumber
            );

            if (!$success) {
                // Schedule retry if not successful
                $webhookService->retryWebhook(
                    $this->projectId,
                    $this->event,
                    $this->data,
                    $this->attemptNumber
                );
            }

        } catch (\Exception $e) {
            Log::error('Webhook delivery job failed', [
                'project_id' => $this->projectId,
                'event' => $this->event,
                'attempt' => $this->attemptNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Schedule retry on exception
            $webhookService->retryWebhook(
                $this->projectId,
                $this->event,
                $this->data,
                $this->attemptNumber
            );
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Webhook delivery job failed permanently', [
            'project_id' => $this->projectId,
            'event' => $this->event,
            'attempt' => $this->attemptNumber,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Get queue name based on event type
     */
    private function getQueueName(string $event): string
    {
        // High priority events
        $highPriorityEvents = [
            'message.failed',
            'message.delivered',
            'rate_limit.exceeded',
        ];

        // Low priority events
        $lowPriorityEvents = [
            'webhook.test',
            'template.created',
            'template.updated',
        ];

        if (in_array($event, $highPriorityEvents)) {
            return 'webhooks-high';
        } elseif (in_array($event, $lowPriorityEvents)) {
            return 'webhooks-low';
        } else {
            return 'webhooks-default';
        }
    }

    /**
     * Get the tags that should be applied to the job.
     */
    public function tags(): array
    {
        return [
            'webhook',
            'project:' . $this->projectId,
            'event:' . $this->event,
            'attempt:' . $this->attemptNumber,
        ];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(30); // Give up after 30 minutes
    }
}
