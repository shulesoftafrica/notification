<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Message;

class UpdateDeliveryStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;
    public $backoff = [30, 60, 120];

    protected $messageId;
    protected $status;
    protected $externalId;
    protected $deliveryData;
    protected $webhookData;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $messageId,
        string $status,
        ?string $externalId = null,
        array $deliveryData = [],
        array $webhookData = []
    ) {
        $this->messageId = $messageId;
        $this->status = $status;
        $this->externalId = $externalId;
        $this->deliveryData = $deliveryData;
        $this->webhookData = $webhookData;
        
        // Use status-updates queue
        $this->onQueue('status-updates');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Updating delivery status', [
                'message_id' => $this->messageId,
                'status' => $this->status,
                'external_id' => $this->externalId
            ]);

            // Get the message
            $message = Message::find($this->messageId);
            
            if (!$message) {
                Log::warning('Message not found for status update', [
                    'message_id' => $this->messageId,
                    'status' => $this->status
                ]);
                return;
            }

            // Check if status is valid transition
            if (!$this->isValidStatusTransition($message->status, $this->status)) {
                Log::warning('Invalid status transition attempted', [
                    'message_id' => $this->messageId,
                    'current_status' => $message->status,
                    'new_status' => $this->status
                ]);
                return;
            }

            // Update message status
            $this->updateMessageStatus($message);

            // Trigger webhook if configured
            if ($message->webhook_url && $this->shouldTriggerWebhook($this->status)) {
                $this->dispatchWebhook($message);
            }

            Log::info('Delivery status updated successfully', [
                'message_id' => $this->messageId,
                'status' => $this->status,
                'previous_status' => $message->status
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update delivery status', [
                'message_id' => $this->messageId,
                'status' => $this->status,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update message status in database
     */
    protected function updateMessageStatus(Message $message): void
    {
        $updateData = [
            'status' => $this->status,
            'updated_at' => now()
        ];

        // Set external ID if provided
        if ($this->externalId && !$message->external_id) {
            $updateData['external_id'] = $this->externalId;
        }

        // Set timestamp based on status
        switch ($this->status) {
            case 'sent':
                $updateData['sent_at'] = now();
                break;
            case 'delivered':
                $updateData['delivered_at'] = now();
                $updateData['status'] ='sent';
                if (!$message->sent_at) {
                    $updateData['sent_at'] = now();
                }
                break;
            case 'failed':
                $updateData['failed_at'] = now();
                break;
            case 'cancelled':
                $updateData['cancelled_at'] = now();
                $updateData['status'] ='failed';
                break;
        }

        // Add delivery data if provided
        if (!empty($this->deliveryData)) {
            $updateData = array_merge($updateData, $this->sanitizeDeliveryData($this->deliveryData));
        }

        // Update cost information if provided
        if (isset($this->deliveryData['cost'])) {
            $updateData['cost_amount'] = $this->deliveryData['cost']['amount'] ?? null;
            $updateData['cost_currency'] = $this->deliveryData['cost']['currency'] ?? 'USD';
        }

        // Update duration if provided
        if (isset($this->deliveryData['duration_ms'])) {
            $updateData['duration_ms'] = $this->deliveryData['duration_ms'];
        }

        // Update error message if provided
        if (isset($this->deliveryData['error'])) {
            $updateData['error_message'] = $this->deliveryData['error'];
        }

        // Merge existing metadata with new data
        if (isset($this->deliveryData['metadata'])) {
            $existingMetadata = $message->metadata ?? [];
            $updateData['metadata'] = array_merge($existingMetadata, $this->deliveryData['metadata']);
        }

        Message::where('id', $this->messageId)->update($updateData);
    }

    /**
     * Check if status transition is valid
     */
    protected function isValidStatusTransition(string $currentStatus, string $newStatus): bool
    {
        $validTransitions = [
            'pending' => ['queued', 'sending', 'sent', 'failed', 'cancelled'],
            'queued' => ['sending', 'sent', 'failed', 'cancelled'],
            'sending' => ['sent', 'delivered', 'failed'],
            'sent' => ['delivered', 'failed'],
            'delivered' => [], // Delivered is final
            'failed' => ['queued', 'sending'], // Can retry failed messages
            'cancelled' => [] // Cancelled is final
        ];

        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }

    /**
     * Determine if webhook should be triggered for this status
     */
    protected function shouldTriggerWebhook(string $status): bool
    {
        $webhookStatuses = ['sent', 'delivered', 'failed'];
        return in_array($status, $webhookStatuses);
    }

    /**
     * Dispatch webhook for status update
     */
    protected function dispatchWebhook(Message $message): void
    {
        try {
            $webhookData = array_merge($this->webhookData, [
                'status_updated_at' => now()->toISOString(),
                'previous_status' => $message->status,
                'delivery_data' => $this->deliveryData
            ]);

            DeliverWebhook::dispatch($this->messageId, $this->status, $webhookData);

            Log::info('Webhook dispatched for status update', [
                'message_id' => $this->messageId,
                'status' => $this->status
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to dispatch webhook for status update', [
                'message_id' => $this->messageId,
                'status' => $this->status,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Sanitize delivery data for database storage
     */
    protected function sanitizeDeliveryData(array $data): array
    {
        $sanitized = [];
        
        // Allowed fields for direct update
        $allowedFields = [
            'provider_response',
            'delivery_receipt',
            'carrier_info',
            'country_code',
            'network_code'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = $data[$field];
            }
        }

        return $sanitized;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Update delivery status job failed permanently', [
            'message_id' => $this->messageId,
            'status' => $this->status,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage()
        ]);

        try {
            // Log the failed status update attempt
            Message::where('id', $this->messageId)->update([
                'metadata' => \DB::raw("JSON_SET(COALESCE(metadata, '{}'), '$.failed_status_updates', JSON_ARRAY_APPEND(COALESCE(JSON_EXTRACT(metadata, '$.failed_status_updates'), '[]'), '$', '{$this->status}'))"),
                'updated_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log failed status update', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['status-update', "message:{$this->messageId}", "status:{$this->status}"];
    }
}
