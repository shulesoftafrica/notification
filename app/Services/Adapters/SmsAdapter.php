<?php

namespace App\Services\Adapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsAdapter implements ProviderAdapterInterface
{
    protected array $config;
    protected string $provider;

    public function __construct(array $config, string $provider = 'twilio')
    {
        $this->config = $config;
        $this->provider = $provider;
    }

    /**
     * Send SMS message
     */
    public function send(string $to, string $message, ?string $subject = null, array $metadata = []): ProviderResponse
    {
        $startTime = microtime(true);

        try {
            switch ($this->provider) {
                case 'twilio':
                    return $this->sendViaTwilio($to, $message, $metadata, $startTime);
                default:
                    return ProviderResponse::failure(
                        $this->provider,
                        "Unsupported SMS provider: {$this->provider}",
                        [],
                        $this->getResponseTime($startTime)
                    );
            }
        } catch (\Exception $e) {
            Log::error('SMS sending failed', [
                'provider' => $this->provider,
                'error' => $e->getMessage(),
                'to' => $to
            ]);

            return ProviderResponse::failure(
                $this->provider,
                $e->getMessage(),
                [],
                $this->getResponseTime($startTime)
            );
        }
    }

    /**
     * Send SMS via Twilio
     */
    protected function sendViaTwilio(string $to, string $message, array $metadata, float $startTime): ProviderResponse
    {
        $accountSid = $this->config['account_sid'];
        $authToken = $this->config['auth_token'];
        $fromNumber = $this->config['from_number'];

        $response = Http::withBasicAuth($accountSid, $authToken)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                'To' => $to,
                'From' => $fromNumber,
                'Body' => $message,
                'StatusCallback' => $metadata['webhook_url'] ?? null,
            ]);

        $responseTime = $this->getResponseTime($startTime);

        if ($response->successful()) {
            $data = $response->json();
            
            return ProviderResponse::success(
                'twilio',
                $data['sid'],
                [
                    'status' => $data['status'],
                    'direction' => $data['direction'],
                    'price' => $data['price'],
                    'price_unit' => $data['price_unit'],
                    'uri' => $data['uri']
                ],
                $data['price'] ? abs((float) $data['price']) : null,
                $responseTime
            );
        }

        $error = $response->json()['message'] ?? 'Unknown error';
        return ProviderResponse::failure('twilio', $error, [], $responseTime);
    }

    /**
     * Get provider name
     */
    public function getProviderName(): string
    {
        return $this->provider;
    }

    /**
     * Check if provider is healthy
     */
    public function isHealthy(): bool
    {
        try {
            switch ($this->provider) {
                case 'twilio':
                    return $this->checkTwilioHealth();
                default:
                    return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check Twilio health
     */
    protected function checkTwilioHealth(): bool
    {
        $accountSid = $this->config['account_sid'];
        $authToken = $this->config['auth_token'];

        $response = Http::withBasicAuth($accountSid, $authToken)
            ->timeout(10)
            ->get("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}.json");

        return $response->successful();
    }

    /**
     * Get provider capabilities
     */
    public function getCapabilities(): array
    {
        return [
            'unicode' => true,
            'concatenated_sms' => true,
            'delivery_receipts' => true,
            'two_way_sms' => true,
            'mms' => $this->provider === 'twilio',
            'short_codes' => true,
        ];
    }

    /**
     * Get provider configuration
     */
    public function getConfig(): array
    {
        // Return config without sensitive data
        return [
            'provider' => $this->provider,
            'from_number' => $this->config['from_number'] ?? null,
            'capabilities' => $this->getCapabilities(),
        ];
    }

    /**
     * Validate phone number format
     */
    public function validateRecipient(string $recipient): bool
    {
        // Basic international phone number validation
        return preg_match('/^\+[1-9]\d{10,14}$/', $recipient);
    }

    /**
     * Get maximum message length
     */
    public function getMaxMessageLength(): int
    {
        return 1600; // Standard concatenated SMS limit
    }

    /**
     * Get delivery status from provider
     */
    public function getDeliveryStatus(string $messageId): ?array
    {
        try {
            switch ($this->provider) {
                case 'twilio':
                    return $this->getTwilioDeliveryStatus($messageId);
                default:
                    return null;
            }
        } catch (\Exception $e) {
            Log::error('Failed to get delivery status', [
                'provider' => $this->provider,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get Twilio delivery status
     */
    protected function getTwilioDeliveryStatus(string $messageId): ?array
    {
        $accountSid = $this->config['account_sid'];
        $authToken = $this->config['auth_token'];

        $response = Http::withBasicAuth($accountSid, $authToken)
            ->get("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages/{$messageId}.json");

        if ($response->successful()) {
            $data = $response->json();
            return [
                'status' => $data['status'],
                'error_code' => $data['error_code'],
                'error_message' => $data['error_message'],
                'date_sent' => $data['date_sent'],
                'date_updated' => $data['date_updated'],
                'price' => $data['price'],
                'price_unit' => $data['price_unit'],
            ];
        }

        return null;
    }

    /**
     * Get estimated cost for message
     */
    public function getCost(string $to, string $message): ?float
    {
        // Rough cost estimation (actual costs vary by destination)
        $segments = ceil(strlen($message) / 160);
        
        // Estimate based on provider and destination
        switch ($this->provider) {
            case 'twilio':
                // US numbers: ~$0.0075 per segment
                // International: ~$0.05-0.30 per segment
                if (str_starts_with($to, '+1')) {
                    return $segments * 0.0075;
                } else {
                    return $segments * 0.05; // Conservative estimate
                }
            default:
                return null;
        }
    }

    /**
     * Calculate response time in milliseconds
     */
    protected function getResponseTime(float $startTime): int
    {
        return (int) round((microtime(true) - $startTime) * 1000);
    }
}
