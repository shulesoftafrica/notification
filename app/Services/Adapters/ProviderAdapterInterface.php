<?php

namespace App\Services\Adapters;

use App\Models\Message;
use App\Models\ProviderConfig;

interface ProviderAdapterInterface
{
    /**
     * Send message through the provider
     */
    public function send(Message $message, array $renderedContent, ProviderConfig $config): ProviderResponse;

    /**
     * Validate provider configuration
     */
    public function validateConfig(array $config): bool;

    /**
     * Get delivery status from provider
     */
    public function getDeliveryStatus(string $providerMessageId, ProviderConfig $config): ?string;
}
