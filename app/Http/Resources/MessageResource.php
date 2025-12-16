<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel,
            'recipient' => $this->formatRecipient(),
            'subject' => $this->when($this->type === 'email', $this->subject),
            'message' =>$this->message,
            'schema_name'=>$this->schema_name,
            'status' => $this->status,
            'provider' => $this->provider,
            'priority' => $this->priority,
            'scheduled_at' => $this->when($this->scheduled_at, function () {
                return $this->scheduled_at?->toISOString();
            }),
            'sent_at' => $this->when($this->sent_at, function () {
                return $this->sent_at?->toISOString();
            }),
            'delivered_at' => $this->when($this->delivered_at, function () {
                return $this->delivered_at?->toISOString();
            }),
            'failed_at' => $this->when($this->failed_at, function () {
                return $this->failed_at?->toISOString();
            }),
            'external_id' => $this->external_id,
            'error_message' => $this->when($this->status === 'failed', $this->error_message),
            'retry_count' => $this->retry_count,
            'metadata' => $this->when($this->metadata, $this->metadata),
            'tags' => $this->when($this->tags, $this->tags),
            'webhook_url' => $this->when($request->user()?->isAdmin(), $this->webhook_url),
            'webhook_delivered' => $this->when($this->webhook_url, $this->webhook_delivered),
            'webhook_attempts' => $this->when($this->webhook_url, $this->webhook_attempts),
            'cost' => $this->when($request->user()?->isAdmin(), $this->formatCost()),
            'duration_ms' => $this->duration_ms,
            'created_at' => date('Y-m-d H:i:s', strtotime($this->created_at)),
            'updated_at' => date('Y-m-d H:i:s', strtotime($this->updated_at)),
            
            // Computed fields
            'is_scheduled' => $this->scheduled_at && $this->scheduled_at->isFuture(),
            'is_delivered' => $this->status === 'delivered',
            'is_failed' => $this->status === 'failed',
            'delivery_status' => $this->getDeliveryStatus(),
            'formatted_duration' => $this->getFormattedDuration(),
        ];
    }

    /**
     * Format recipient based on type and privacy settings
     */
    protected function formatRecipient(): string
    {
        $recipient = $this->recipient;
        
        // For privacy, mask parts of phone numbers and emails for non-admin users
        if (!request()->user()?->isAdmin()) {
            if ($this->type === 'email') {
                $parts = explode('@', $recipient);
                if (count($parts) === 2) {
                    $username = $parts[0];
                    $domain = $parts[1];
                    $maskedUsername = substr($username, 0, 2) . str_repeat('*', max(0, strlen($username) - 4)) . substr($username, -2);
                    return $maskedUsername . '@' . $domain;
                }
            } elseif (in_array($this->type, ['sms', 'whatsapp'])) {
                // Mask middle digits of phone number
                if (strlen($recipient) > 6) {
                    return substr($recipient, 0, 4) . str_repeat('*', strlen($recipient) - 7) . substr($recipient, -3);
                }
            }
        }
        
        return $recipient;
    }

    /**
     * Format cost for display
     */
    protected function formatCost(): ?array
    {
        if (!$this->cost_amount) {
            return null;
        }

        return [
            'amount' => number_format($this->cost_amount, 4),
            'currency' => $this->cost_currency ?? 'USD',
            'formatted' => ($this->cost_currency ?? '$') . number_format($this->cost_amount, 4)
        ];
    }

    /**
     * Get delivery status with additional context
     */
    protected function getDeliveryStatus(): array
    {
        $status = [
            'code' => $this->status,
            'label' => ucfirst($this->status),
            'color' => $this->getStatusColor(),
            'description' => $this->getStatusDescription()
        ];

        if ($this->status === 'failed' && $this->retry_count > 0) {
            $status['retries'] = $this->retry_count;
            $status['can_retry'] = $this->retry_count < 3;
        }

        return $status;
    }

    /**
     * Get status color for UI display
     */
    protected function getStatusColor(): string
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'queued' => 'blue',
            'sending' => 'orange',
            'sent' => 'green',
            'delivered' => 'green',
            'failed' => 'red',
            'cancelled' => 'gray',
            default => 'gray'
        };
    }

    /**
     * Get human-readable status description
     */
    protected function getStatusDescription(): string
    {
        return match ($this->status) {
            'pending' => 'Message is pending processing',
            'queued' => 'Message is queued for sending',
            'sending' => 'Message is being sent',
            'sent' => 'Message has been sent to provider',
            'delivered' => 'Message has been delivered to recipient',
            'failed' => 'Message delivery failed',
            'cancelled' => 'Message was cancelled before sending',
            default => 'Unknown status'
        };
    }

    /**
     * Get formatted duration string
     */
    protected function getFormattedDuration(): ?string
    {
        if (!$this->duration_ms) {
            return null;
        }

        if ($this->duration_ms < 1000) {
            return $this->duration_ms . 'ms';
        } elseif ($this->duration_ms < 60000) {
            return round($this->duration_ms / 1000, 2) . 's';
        } else {
            $minutes = floor($this->duration_ms / 60000);
            $seconds = round(($this->duration_ms % 60000) / 1000, 1);
            return $minutes . 'm ' . $seconds . 's';
        }
    }

    /**
     * Get additional data for collection responses
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'timestamp' => now()->toISOString(),
            ]
        ];
    }

    /**
     * Customize the response when this resource is used in a collection
     */
    public static function collection($resource)
    {
        return parent::collection($resource)->additional([
            'meta' => [
                'total_count' => $resource->count(),
                'version' => '1.0',
                'timestamp' => now()->toISOString(),
            ]
        ]);
    }
}
