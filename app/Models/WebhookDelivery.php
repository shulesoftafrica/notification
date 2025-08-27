<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'delivery_id',
        'project_id',
        'webhook_url',
        'event',
        'payload',
        'attempt_number',
        'status',
        'response_status',
        'response_body',
        'delivered_at',
        'error_message',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'pending',
        'attempt_number' => 1,
    ];

    /**
     * Get the project that this webhook delivery belongs to
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    /**
     * Get the payload as an array
     */
    public function getPayloadAttribute($value): ?array
    {
        return $value ? json_decode($value, true) : null;
    }

    /**
     * Set the payload from an array
     */
    public function setPayloadAttribute($value): void
    {
        $this->attributes['payload'] = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * Check if delivery was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'delivered';
    }

    /**
     * Check if delivery failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if delivery is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Get delivery duration in milliseconds
     */
    public function getDeliveryDurationMs(): ?int
    {
        if (!$this->delivered_at) {
            return null;
        }

        return $this->created_at->diffInMilliseconds($this->delivered_at);
    }

    /**
     * Scope for successful deliveries
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'delivered');
    }

    /**
     * Scope for failed deliveries
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for pending deliveries
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for deliveries within time range
     */
    public function scopeWithinPeriod($query, Carbon $start, Carbon $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * Scope for specific event type
     */
    public function scopeForEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    /**
     * Get formatted response status for display
     */
    public function getFormattedResponseStatus(): string
    {
        if (!$this->response_status) {
            return 'No Response';
        }

        $status = $this->response_status;
        
        if ($status >= 200 && $status < 300) {
            return "✅ {$status}";
        } elseif ($status >= 400 && $status < 500) {
            return "⚠️ {$status}";
        } elseif ($status >= 500) {
            return "❌ {$status}";
        } else {
            return "❓ {$status}";
        }
    }

    /**
     * Get summary of delivery attempt
     */
    public function getSummary(): array
    {
        return [
            'delivery_id' => $this->delivery_id,
            'event' => $this->event,
            'status' => $this->status,
            'attempt_number' => $this->attempt_number,
            'response_status' => $this->response_status,
            'created_at' => $this->created_at->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'duration_ms' => $this->getDeliveryDurationMs(),
            'error_message' => $this->error_message,
        ];
    }
}
