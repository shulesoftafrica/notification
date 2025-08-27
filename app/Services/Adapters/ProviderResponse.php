<?php

namespace App\Services\Adapters;

class ProviderResponse
{
    public function __construct(
        private bool $success,
        private ?string $providerMessageId = null,
        private ?string $error = null,
        private ?float $cost = null,
        private ?array $response = null
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getProviderMessageId(): ?string
    {
        return $this->providerMessageId;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getCost(): ?float
    {
        return $this->cost;
    }

    public function getResponse(): ?array
    {
        return $this->response;
    }

    public static function success(string $providerMessageId, ?float $cost = null, ?array $response = null): self
    {
        return new self(true, $providerMessageId, null, $cost, $response);
    }

    public static function failure(string $error, ?array $response = null): self
    {
        return new self(false, null, $error, null, $response);
    }
}
