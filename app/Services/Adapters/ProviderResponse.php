<?php

namespace App\Services\Adapters;

class ProviderResponse
{
    public bool $success;
    public ?string $messageId;
    public ?string $error;
    public array $metadata;
    public ?float $cost;
    public int $responseTimeMs;
    public string $provider;

    public function __construct(
        bool $success,
        string $provider,
        ?string $messageId = null,
        ?string $error = null,
        array $metadata = [],
        ?float $cost = null,
        int $responseTimeMs = 0
    ) {
        $this->success = $success;
        $this->provider = $provider;
        $this->messageId = $messageId;
        $this->error = $error;
        $this->metadata = $metadata;
        $this->cost = $cost;
        $this->responseTimeMs = $responseTimeMs;
    }

    /**
     * Create a successful response
     */
    public static function success(
        string $provider,
        string $messageId,
        array $metadata = [],
        ?float $cost = null,
        int $responseTimeMs = 0
    ): self {
        return new self(
            success: true,
            provider: $provider,
            messageId: $messageId,
            metadata: $metadata,
            cost: $cost,
            responseTimeMs: $responseTimeMs
        );
    }

    /**
     * Create a failed response
     */
    public static function failure(
        string $provider,
        string $error,
        array $metadata = [],
        int $responseTimeMs = 0
    ): self {
        return new self(
            success: false,
            provider: $provider,
            error: $error,
            metadata: $metadata,
            responseTimeMs: $responseTimeMs
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'provider' => $this->provider,
            'message_id' => $this->messageId,
            'error' => $this->error,
            'metadata' => $this->metadata,
            'cost' => $this->cost,
            'response_time_ms' => $this->responseTimeMs,
        ];
    }

    /**
     * Check if response indicates a temporary failure
     */
    public function isTemporaryFailure(): bool
    {
        if ($this->success) {
            return false;
        }

        $temporaryErrors = [
            'rate limit',
            'timeout',
            'service unavailable',
            '429',
            '503',
            '502',
            'connection',
            'network'
        ];

        $error = strtolower($this->error ?? '');
        
        foreach ($temporaryErrors as $tempError) {
            if (strpos($error, $tempError) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get human readable status
     */
    public function getStatus(): string
    {
        if ($this->success) {
            return 'sent';
        }

        if ($this->isTemporaryFailure()) {
            return 'temporary_failure';
        }

        return 'permanent_failure';
    }
}
