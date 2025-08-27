<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Project;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Carbon\Carbon;

class ClientWebhookService
{
    /**
     * Send webhook notification to client
     */
    public function sendWebhook(
        string $projectId,
        string $event,
        array $data,
        int $attemptNumber = 1
    ): bool {
        try {
            $project = Project::where('project_id', $projectId)->first();
            
            if (!$project || !$project->webhook_url) {
                Log::info('No webhook URL configured for project', [
                    'project_id' => $projectId,
                    'event' => $event
                ]);
                return true; // Not an error if no webhook configured
            }

            $payload = $this->buildWebhookPayload($event, $data);
            $signature = $this->generateSignature($payload, $project->webhook_secret);
            
            $deliveryId = $this->createDeliveryRecord($project, $event, $payload, $attemptNumber);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => 'sha256=' . $signature,
                'X-Webhook-Timestamp' => time(),
                'X-Webhook-Event' => $event,
                'X-Webhook-Delivery' => $deliveryId,
                'User-Agent' => 'NotificationService/1.0',
            ])
            ->timeout(30)
            ->retry(3, 1000) // Retry 3 times with 1 second delay
            ->post($project->webhook_url, $payload);

            $success = $response->successful();
            
            $this->updateDeliveryRecord($deliveryId, [
                'status' => $success ? 'delivered' : 'failed',
                'response_status' => $response->status(),
                'response_body' => $response->body(),
                'delivered_at' => $success ? now() : null,
                'error_message' => $success ? null : $this->getErrorMessage($response),
            ]);

            if ($success) {
                Log::info('Webhook delivered successfully', [
                    'project_id' => $projectId,
                    'event' => $event,
                    'delivery_id' => $deliveryId,
                    'attempt' => $attemptNumber,
                    'response_status' => $response->status(),
                ]);
                return true;
            } else {
                Log::warning('Webhook delivery failed', [
                    'project_id' => $projectId,
                    'event' => $event,
                    'delivery_id' => $deliveryId,
                    'attempt' => $attemptNumber,
                    'response_status' => $response->status(),
                    'error' => $this->getErrorMessage($response),
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('Webhook delivery exception', [
                'project_id' => $projectId,
                'event' => $event,
                'attempt' => $attemptNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if (isset($deliveryId)) {
                $this->updateDeliveryRecord($deliveryId, [
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            return false;
        }
    }

    /**
     * Queue webhook for delivery with retry logic
     */
    public function queueWebhook(
        string $projectId,
        string $event,
        array $data,
        int $delaySeconds = 0
    ): void {
        $job = new \App\Jobs\DeliverWebhook($projectId, $event, $data, 1);
        
        if ($delaySeconds > 0) {
            Queue::later(now()->addSeconds($delaySeconds), $job);
        } else {
            Queue::push($job);
        }
    }

    /**
     * Handle webhook retry with exponential backoff
     */
    public function retryWebhook(
        string $projectId,
        string $event,
        array $data,
        int $attemptNumber
    ): void {
        $maxAttempts = config('notification.webhooks.max_attempts', 10);
        
        if ($attemptNumber > $maxAttempts) {
            Log::error('Webhook max retries exceeded', [
                'project_id' => $projectId,
                'event' => $event,
                'attempts' => $attemptNumber,
            ]);
            return;
        }

        // Exponential backoff: 1s, 2s, 4s, 8s, 16s, 30s, 1m, 2m, 5m, 10m
        $delays = [1, 2, 4, 8, 16, 30, 60, 120, 300, 600];
        $delay = $delays[$attemptNumber - 1] ?? 600; // Max 10 minutes

        Log::info('Scheduling webhook retry', [
            'project_id' => $projectId,
            'event' => $event,
            'attempt' => $attemptNumber + 1,
            'delay_seconds' => $delay,
        ]);

        $job = new \App\Jobs\DeliverWebhook($projectId, $event, $data, $attemptNumber + 1);
        Queue::later(now()->addSeconds($delay), $job);
    }

    /**
     * Build webhook payload
     */
    private function buildWebhookPayload(string $event, array $data): array
    {
        return [
            'event' => $event,
            'timestamp' => now()->toISOString(),
            'data' => $data,
            'webhook_id' => 'wh_' . uniqid(),
        ];
    }

    /**
     * Generate HMAC signature for webhook
     */
    private function generateSignature(array $payload, ?string $secret): string
    {
        if (!$secret) {
            return '';
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        return hash_hmac('sha256', $body, $secret);
    }

    /**
     * Create webhook delivery record
     */
    private function createDeliveryRecord(
        Project $project,
        string $event,
        array $payload,
        int $attemptNumber
    ): string {
        try {
            $delivery = WebhookDelivery::create([
                'delivery_id' => 'whd_' . uniqid(),
                'project_id' => $project->project_id,
                'webhook_url' => $project->webhook_url,
                'event' => $event,
                'payload' => json_encode($payload),
                'attempt_number' => $attemptNumber,
                'status' => 'pending',
                'created_at' => now(),
            ]);

            return $delivery->delivery_id;
        } catch (\Exception $e) {
            Log::error('Failed to create webhook delivery record', [
                'project_id' => $project->project_id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
            return 'whd_error_' . uniqid();
        }
    }

    /**
     * Update webhook delivery record
     */
    private function updateDeliveryRecord(string $deliveryId, array $updates): void
    {
        try {
            WebhookDelivery::where('delivery_id', $deliveryId)->update($updates);
        } catch (\Exception $e) {
            Log::error('Failed to update webhook delivery record', [
                'delivery_id' => $deliveryId,
                'updates' => $updates,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get error message from HTTP response
     */
    private function getErrorMessage($response): string
    {
        if (!$response) {
            return 'No response received';
        }

        $status = $response->status();
        $body = $response->body();

        if ($status >= 500) {
            return "Server error (HTTP {$status})";
        } elseif ($status >= 400) {
            return "Client error (HTTP {$status}): " . substr($body, 0, 200);
        } else {
            return "Unexpected response (HTTP {$status})";
        }
    }

    /**
     * Send message status update webhook
     */
    public function sendMessageStatusWebhook(Message $message): void
    {
        $data = [
            'message_id' => $message->message_id,
            'external_id' => $message->external_id,
            'status' => $message->status,
            'channel' => $message->channel,
            'to' => $message->to,
            'delivered_at' => $message->delivered_at?->toISOString(),
            'failed_at' => $message->failed_at?->toISOString(),
            'failure_reason' => $message->failure_reason,
            'provider' => $message->provider,
            'provider_message_id' => $message->provider_message_id,
            'cost' => [
                'amount' => $message->cost_amount,
                'currency' => $message->cost_currency ?? 'USD',
            ],
            'metadata' => $message->metadata ? json_decode($message->metadata, true) : null,
        ];

        $event = "message.{$message->status}";
        
        $this->queueWebhook($message->project_id, $event, $data);
    }

    /**
     * Send template operation webhook
     */
    public function sendTemplateWebhook(string $projectId, string $event, array $templateData): void
    {
        $this->queueWebhook($projectId, "template.{$event}", $templateData);
    }

    /**
     * Send rate limit webhook
     */
    public function sendRateLimitWebhook(string $projectId, array $limitData): void
    {
        $this->queueWebhook($projectId, 'rate_limit.exceeded', $limitData);
    }

    /**
     * Test webhook configuration
     */
    public function testWebhook(string $projectId): bool
    {
        $testData = [
            'test' => true,
            'timestamp' => now()->toISOString(),
            'message' => 'This is a test webhook delivery',
        ];

        return $this->sendWebhook($projectId, 'webhook.test', $testData);
    }

    /**
     * Get webhook delivery statistics
     */
    public function getDeliveryStats(string $projectId, Carbon $since = null): array
    {
        $since = $since ?? now()->subDays(7);
        
        $query = WebhookDelivery::where('project_id', $projectId)
            ->where('created_at', '>=', $since);

        $total = $query->count();
        $successful = $query->where('status', 'delivered')->count();
        $failed = $query->where('status', 'failed')->count();
        $pending = $query->where('status', 'pending')->count();

        $successRate = $total > 0 ? round(($successful / $total) * 100, 2) : 0;

        return [
            'total_deliveries' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'pending' => $pending,
            'success_rate' => $successRate,
            'period' => [
                'since' => $since->toISOString(),
                'until' => now()->toISOString(),
            ],
        ];
    }
}
