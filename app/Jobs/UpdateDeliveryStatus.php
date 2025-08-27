<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\Receipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class UpdateDeliveryStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $messageId,
        public string $status,
        public array $webhookData = [],
        public ?string $providerMessageId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Find the message
            $message = Message::where('message_id', $this->messageId)
                             ->orWhere('provider_message_id', $this->providerMessageId)
                             ->first();

            if (!$message) {
                Log::warning('Message not found for delivery status update', [
                    'message_id' => $this->messageId,
                    'provider_message_id' => $this->providerMessageId,
                    'status' => $this->status
                ]);
                return;
            }

            // Map provider status to our internal status
            $internalStatus = $this->mapProviderStatus($this->status);

            // Update message status if it's a valid transition
            if ($this->isValidStatusTransition($message->status, $internalStatus)) {
                $updateData = ['status' => $internalStatus];

                // Set timestamps based on status
                switch ($internalStatus) {
                    case 'delivered':
                        $updateData['delivered_at'] = now();
                        break;
                    case 'read':
                        $updateData['read_at'] = now();
                        if (!$message->delivered_at) {
                            $updateData['delivered_at'] = now();
                        }
                        break;
                    case 'failed':
                        $updateData['failed_at'] = now();
                        $updateData['failure_reason'] = $this->webhookData['error_message'] ?? 'Delivery failed';
                        break;
                }

                $message->update($updateData);

                Log::info('Message status updated', [
                    'message_id' => $message->message_id,
                    'old_status' => $message->status,
                    'new_status' => $internalStatus,
                    'provider_status' => $this->status
                ]);
            }

            // Create receipt record
            $this->createReceipt($message);

        } catch (Exception $e) {
            Log::error('Failed to update delivery status', [
                'message_id' => $this->messageId,
                'provider_message_id' => $this->providerMessageId,
                'status' => $this->status,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Map provider-specific status to internal status
     */
    private function mapProviderStatus(string $providerStatus): string
    {
        return match(strtolower($providerStatus)) {
            'delivered', 'delivered_to_handset', 'delivered_to_terminal' => 'delivered',
            'read', 'opened', 'clicked' => 'read',
            'failed', 'undelivered', 'rejected', 'bounced' => 'failed',
            'sent', 'accepted', 'queued' => 'sent',
            'processing', 'in_progress' => 'processing',
            default => $providerStatus
        };
    }

    /**
     * Check if status transition is valid
     */
    private function isValidStatusTransition(string $currentStatus, string $newStatus): bool
    {
        $validTransitions = [
            'queued' => ['processing', 'sent', 'failed'],
            'processing' => ['sent', 'failed'],
            'sent' => ['delivered', 'failed', 'read'],
            'delivered' => ['read', 'failed'],
            'failed' => [], // Terminal state
            'read' => [] // Terminal state
        ];

        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }

    /**
     * Create receipt record for the status update
     */
    private function createReceipt(Message $message): void
    {
        try {
            Receipt::create([
                'receipt_id' => 'rcpt_' . uniqid(),
                'message_id' => $message->message_id,
                'project_id' => $message->project_id,
                'tenant_id' => $message->tenant_id,
                'event_type' => $this->status,
                'event_data' => $this->webhookData,
                'provider_name' => $message->provider_name,
                'provider_message_id' => $message->provider_message_id,
                'occurred_at' => now()
            ]);
        } catch (Exception $e) {
            Log::error('Failed to create receipt', [
                'message_id' => $message->message_id,
                'event_type' => $this->status,
                'error' => $e->getMessage()
            ]);
            // Don't throw here as receipt creation is secondary
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        Log::error('UpdateDeliveryStatus job failed permanently', [
            'message_id' => $this->messageId,
            'provider_message_id' => $this->providerMessageId,
            'status' => $this->status,
            'error' => $exception->getMessage()
        ]);
    }
}
