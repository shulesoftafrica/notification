<?php

namespace App\Services\Adapters;

interface ProviderAdapterInterface
{
    /**
     * Send a message using the provider
     *
     * @param string $to Recipient (phone number or email)
     * @param string $message Message content
     * @param string|null $subject Subject (for email)
     * @param array $metadata Additional metadata
     * @return ProviderResponse
     */
    public function send(string $to, string $message, ?string $subject = null, array $metadata = []): ProviderResponse;

    /**
     * Get the provider name
     *
     * @return string
     */
    public function getProviderName(): string;

    /**
     * Check if the provider is available/healthy
     *
     * @return bool
     */
    public function isHealthy(): bool;

    /**
     * Get provider capabilities
     *
     * @return array
     */
    public function getCapabilities(): array;

    /**
     * Get provider configuration
     *
     * @return array
     */
    public function getConfig(): array;

    /**
     * Validate recipient format
     *
     * @param string $recipient
     * @return bool
     */
    public function validateRecipient(string $recipient): bool;

    /**
     * Get maximum message length
     *
     * @return int
     */
    public function getMaxMessageLength(): int;

    /**
     * Get delivery status from provider
     *
     * @param string $messageId External message ID
     * @return array|null
     */
    public function getDeliveryStatus(string $messageId): ?array;

    /**
     * Get provider cost for message
     *
     * @param string $to Recipient
     * @param string $message Message content
     * @return float|null Cost in USD
     */
    public function getCost(string $to, string $message): ?float;
}
