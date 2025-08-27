<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'message_id',
        'project_id',
        'tenant_id',
        'idempotency_key',
        'external_id',
        'recipient',
        'channel',
        'template_id',
        'variables',
        'options',
        'metadata',
        'status',
        'priority',
        'scheduled_at',
        'sent_at',
        'delivered_at',
        'failed_at',
        'provider',
        'provider_message_id',
        'cost',
        'currency',
        'error_message',
        'retry_count',
        'next_retry_at',
    ];

    protected $casts = [
        'recipient' => 'array',
        'variables' => 'array',
        'options' => 'array',
        'metadata' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'cost' => 'decimal:6',
        'retry_count' => 'integer',
    ];

    /**
     * Relationships
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class, 'message_id', 'message_id');
    }

    /**
     * Scopes
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['queued', 'processing']);
    }

    public function scopeReadyForSending($query)
    {
        return $query->where('status', 'queued')
                    ->where(function ($q) {
                        $q->whereNull('scheduled_at')
                          ->orWhere('scheduled_at', '<=', now());
                    });
    }

    /**
     * Status checking methods
     */
    public function isPending(): bool
    {
        return in_array($this->status, ['queued', 'processing']);
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function canRetry(): bool
    {
        return $this->isFailed() && $this->retry_count < 3;
    }

    /**
     * Mark message as sent
     */
    public function markAsSent(string $provider, string $providerMessageId, float $cost = 0): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'provider' => $provider,
            'provider_message_id' => $providerMessageId,
            'cost' => $cost,
        ]);
    }

    /**
     * Mark message as delivered
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mark message as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
            'next_retry_at' => $this->calculateNextRetry(),
        ]);
    }

    /**
     * Calculate next retry time using exponential backoff
     */
    private function calculateNextRetry(): ?\Carbon\Carbon
    {
        if (!$this->canRetry()) {
            return null;
        }

        $delays = [60, 300, 900]; // 1 min, 5 min, 15 min
        $delay = $delays[$this->retry_count] ?? 900;
        
        return now()->addSeconds($delay);
    }
}
