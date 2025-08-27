<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'message_id' => $this->message_id,
            'status' => $this->status,
            'channel' => $this->channel,
            'priority' => $this->priority,
            'recipient' => $this->recipient,
            'template_id' => $this->template_id,
            'external_id' => $this->external_id,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'failed_at' => $this->failed_at?->toISOString(),
            'provider' => $this->provider,
            'cost' => [
                'amount' => (float) $this->cost,
                'currency' => $this->currency,
            ],
            'retry_count' => $this->retry_count,
            'error_message' => $this->when($this->status === 'failed', $this->error_message),
            
            // Include metadata for detailed view
            'variables' => $this->when(
                $request->routeIs('messages.show'),
                $this->variables
            ),
            'options' => $this->when(
                $request->routeIs('messages.show'),
                $this->options
            ),
            'metadata' => $this->when(
                $request->routeIs('messages.show'),
                $this->metadata
            ),
        ];
    }
}
