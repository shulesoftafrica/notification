<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WaSenderSessionResource extends JsonResource
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
            'name' => $this->name,
            'phone_number' => $this->phone_number,
            'status' => $this->status,
            'account_protection' => $this->account_protection,
            'log_messages' => $this->log_messages,
            'read_incoming_messages' => $this->read_incoming_messages,
            'webhook_url' => $this->webhook_url,
            'webhook_enabled' => $this->webhook_enabled,
            'webhook_events' => $this->webhook_events,
            'api_key' => $this->when($request->user()?->is_admin ?? false, $this->api_key),
            'webhook_secret' => $this->when($request->user()?->is_admin ?? false, $this->webhook_secret),
            'wasender_session_id' => $this->wasender_session_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
