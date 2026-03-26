<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel',
        'recipient',
        'subject',
        'message',
        'status',
        'priority',
        'provider',
        'external_id',
        'scheduled_at',
        'sent_at',
        'delivered_at',
        'failed_at',
        'cancelled_at',
        'error_message',
        'retry_count',
        'metadata',
        'tags',
        'webhook_url',
        'webhook_delivered',
        'webhook_attempts',
        'webhook_error',
        'webhook_failed_at',
        'schema_name',
        'ip_address',
        'user_agent',
        'cost_amount',
        'cost_currency',
        'duration_ms',
        'attachment',
        'attachment_metadata',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'webhook_failed_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'deleted_at' => 'datetime',
        'metadata' => 'array',
        'tags' => 'array',
        'variables' => 'array',
        'options' => 'array',
        'attachment_metadata' => 'array',
        'webhook_delivered' => 'boolean',
        'cost_amount' => 'decimal:4',
        'cost' => 'decimal:4',
        // Note: recipient should NOT be cast as array/json since we store simple strings
    ];

    /**
     * Scope for filtering by status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering by type
     */
    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for filtering by API key
     */
    public function scopeApiKey($query, $apiKey)
    {
        return $query->where('api_key', $apiKey);
    }

    /**
     * Scope for recent messages
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', Carbon::now()->subHours($hours));
    }

    /**
     * Check if message is scheduled
     */
    public function isScheduled(): bool
    {
        return $this->scheduled_at && $this->scheduled_at->isFuture();
    }

    /**
     * Check if message is delivered
     */
    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    /**
     * Check if message has failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get formatted cost
     */
    public function getFormattedCostAttribute(): ?string
    {
        if (!$this->cost_amount) {
            return null;
        }

        return ($this->cost_currency ?? '$') . number_format($this->cost_amount, 4);
    }

    /**
     * Get delivery duration in human readable format
     */
    public function getFormattedDurationAttribute(): ?string
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
     * Get masked recipient for privacy
     */
    public function getMaskedRecipientAttribute(): string
    {
        $recipient = $this->recipient;
        
        if ($this->type === 'email') {
            $parts = explode('@', $recipient);
            if (count($parts) === 2) {
                $username = $parts[0];
                $domain = $parts[1];
                $maskedUsername = substr($username, 0, 2) . str_repeat('*', max(0, strlen($username) - 4)) . substr($username, -2);
                return $maskedUsername . '@' . $domain;
            }
        } elseif (in_array($this->type, ['sms', 'whatsapp'])) {
            if (strlen($recipient) > 6) {
                return substr($recipient, 0, 4) . str_repeat('*', strlen($recipient) - 7) . substr($recipient, -3);
            }
        }
        
        return $recipient;
    }
}
