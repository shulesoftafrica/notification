<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'message_id',
        'webhook_url',
        'event_type',
        'payload',
        'status',
        'response_status',
        'response_body',
        'response_headers',
        'attempt_count',
        'max_attempts',
        'next_retry_at',
        'delivered_at',
        'failed_at',
        'error_message',
        'signature',
        'metadata'
    ];

    protected $casts = [
        'payload' => 'array',
        'response_headers' => 'array',
        'metadata' => 'array',
        'next_retry_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * Statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_FAILED = 'failed';
    const STATUS_RETRYING = 'retrying';

    /**
     * Get the project that owns this webhook delivery
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the message that triggered this webhook
     */
    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Scope for pending deliveries
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for failed deliveries
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for deliveries ready for retry
     */
    public function scopeReadyForRetry($query)
    {
        return $query->where('status', self::STATUS_RETRYING)
                    ->where('next_retry_at', '<=', now())
                    ->where('attempt_count', '<', 'max_attempts');
    }

    /**
     * Mark as delivered
     */
    public function markAsDelivered(int $responseStatus, ?array $responseHeaders = null, ?string $responseBody = null): void
    {
        $this->update([
            'status' => self::STATUS_DELIVERED,
            'response_status' => $responseStatus,
            'response_headers' => $responseHeaders,
            'response_body' => $responseBody,
            'delivered_at' => now(),
            'error_message' => null
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorMessage, ?int $responseStatus = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'response_status' => $responseStatus,
            'failed_at' => now(),
            'error_message' => $errorMessage
        ]);
    }

    /**
     * Schedule retry
     */
    public function scheduleRetry(\DateTimeInterface $nextRetryAt): void
    {
        $this->update([
            'status' => self::STATUS_RETRYING,
            'attempt_count' => $this->attempt_count + 1,
            'next_retry_at' => $nextRetryAt
        ]);
    }

    /**
     * Check if delivery can be retried
     */
    public function canRetry(): bool
    {
        return $this->attempt_count < $this->max_attempts && 
               $this->status !== self::STATUS_DELIVERED;
    }

    /**
     * Get retry delay in seconds
     */
    public function getRetryDelay(): int
    {
        // Exponential backoff: 30s, 1m, 5m, 15m, 30m
        $delays = [30, 60, 300, 900, 1800];
        $index = min($this->attempt_count, count($delays) - 1);
        
        return $delays[$index];
    }

    /**
     * Generate webhook signature
     */
    public function generateSignature(string $secret): string
    {
        $payload = is_string($this->payload) ? $this->payload : json_encode($this->payload);
        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Verify webhook signature
     */
    public function verifySignature(string $providedSignature, string $secret): bool
    {
        $expectedSignature = $this->generateSignature($secret);
        return hash_equals($expectedSignature, $providedSignature);
    }
}
