<?php

namespace App\Services\Adapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppAdapter implements ProviderAdapterInterface
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Send WhatsApp message
     */
    public function send(string $to, string $message, ?string $subject = null, array $metadata = []): ProviderResponse
    {
        $startTime = microtime(true);

        try {
            // WhatsApp Business API via Meta/Facebook
            return $this->sendViaWhatsAppAPI($to, $message, $metadata, $startTime);
        } catch (\Exception $e) {
            Log::error('WhatsApp sending failed', [
                'error' => $e->getMessage(),
                'to' => $to
            ]);

            return ProviderResponse::failure(
                'whatsapp',
                $e->getMessage(),
                [],
                $this->getResponseTime($startTime)
            );
        }
    }

    /**
     * Send message via WhatsApp Business API
     */
    protected function sendViaWhatsAppAPI(string $to, string $message, array $metadata, float $startTime): ProviderResponse
    {
        $accessToken = $this->config['access_token'];
        $phoneNumberId = $this->config['phone_number_id'];
        $apiVersion = $this->config['api_version'] ?? 'v18.0';

        // Clean phone number (remove + and non-digits)
        $phoneNumber = preg_replace('/[^\d]/', '', $to);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phoneNumber,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message
            ]
        ];

        // Handle template messages if specified
        if (!empty($metadata['template_name'])) {
            $payload = $this->buildTemplateMessage($phoneNumber, $metadata);
        }

        // Handle media messages
        if (!empty($metadata['media_type']) && !empty($metadata['media_url'])) {
            $payload = $this->buildMediaMessage($phoneNumber, $message, $metadata);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json'
        ])->post("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages", $payload);

        $responseTime = $this->getResponseTime($startTime);

        if ($response->successful()) {
            $data = $response->json();
            
            return ProviderResponse::success(
                'whatsapp',
                $data['messages'][0]['id'],
                [
                    'contacts' => $data['contacts'],
                    'response_code' => $response->status()
                ],
                $this->getCost($phoneNumber, $message),
                $responseTime
            );
        }

        $error = $response->json()['error']['message'] ?? 'Unknown error';
        return ProviderResponse::failure('whatsapp', $error, [], $responseTime);
    }

    /**
     * Build template message payload
     */
    protected function buildTemplateMessage(string $phoneNumber, array $metadata): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phoneNumber,
            'type' => 'template',
            'template' => [
                'name' => $metadata['template_name'],
                'language' => [
                    'code' => $metadata['language_code'] ?? 'en_US'
                ]
            ]
        ];

        // Add template parameters if provided
        if (!empty($metadata['template_parameters'])) {
            $components = [];
            
            if (!empty($metadata['template_parameters']['header'])) {
                $components[] = [
                    'type' => 'header',
                    'parameters' => array_map(function($param) {
                        return ['type' => 'text', 'text' => $param];
                    }, $metadata['template_parameters']['header'])
                ];
            }

            if (!empty($metadata['template_parameters']['body'])) {
                $components[] = [
                    'type' => 'body',
                    'parameters' => array_map(function($param) {
                        return ['type' => 'text', 'text' => $param];
                    }, $metadata['template_parameters']['body'])
                ];
            }

            if (!empty($components)) {
                $payload['template']['components'] = $components;
            }
        }

        return $payload;
    }

    /**
     * Build media message payload
     */
    protected function buildMediaMessage(string $phoneNumber, string $caption, array $metadata): array
    {
        $mediaType = $metadata['media_type']; // image, document, audio, video
        $mediaUrl = $metadata['media_url'];

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phoneNumber,
            'type' => $mediaType,
            $mediaType => [
                'link' => $mediaUrl
            ]
        ];

        // Add caption for supported media types
        if (in_array($mediaType, ['image', 'document', 'video']) && !empty($caption)) {
            $payload[$mediaType]['caption'] = $caption;
        }

        // Add filename for documents
        if ($mediaType === 'document' && !empty($metadata['filename'])) {
            $payload[$mediaType]['filename'] = $metadata['filename'];
        }

        return $payload;
    }

    /**
     * Get provider name
     */
    public function getProviderName(): string
    {
        return 'whatsapp';
    }

    /**
     * Check if provider is healthy
     */
    public function isHealthy(): bool
    {
        try {
            $accessToken = $this->config['access_token'];
            $phoneNumberId = $this->config['phone_number_id'];
            $apiVersion = $this->config['api_version'] ?? 'v18.0';

            // Check phone number status
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken
            ])->timeout(10)->get("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}");

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get provider capabilities
     */
    public function getCapabilities(): array
    {
        return [
            'text' => true,
            'templates' => true,
            'media' => true,
            'images' => true,
            'documents' => true,
            'audio' => true,
            'video' => true,
            'location' => true,
            'contacts' => true,
            'interactive' => true,
            'delivery_receipts' => true,
            'read_receipts' => true,
        ];
    }

    /**
     * Get provider configuration
     */
    public function getConfig(): array
    {
        return [
            'provider' => 'whatsapp',
            'phone_number_id' => $this->config['phone_number_id'] ?? null,
            'business_account_id' => $this->config['business_account_id'] ?? null,
            'api_version' => $this->config['api_version'] ?? 'v18.0',
            'capabilities' => $this->getCapabilities(),
        ];
    }

    /**
     * Validate WhatsApp phone number
     */
    public function validateRecipient(string $recipient): bool
    {
        // Remove + and any non-digit characters
        $phoneNumber = preg_replace('/[^\d]/', '', $recipient);
        
        // Must be between 7 and 15 digits (international format)
        return preg_match('/^\d{7,15}$/', $phoneNumber);
    }

    /**
     * Get maximum message length
     */
    public function getMaxMessageLength(): int
    {
        return 4096; // WhatsApp text message limit
    }

    /**
     * Get delivery status from WhatsApp API
     */
    public function getDeliveryStatus(string $messageId): ?array
    {
        try {
            $accessToken = $this->config['access_token'];
            $apiVersion = $this->config['api_version'] ?? 'v18.0';

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken
            ])->get("https://graph.facebook.com/{$apiVersion}/{$messageId}");

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get WhatsApp delivery status', [
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get estimated cost for WhatsApp message
     */
    public function getCost(string $to, string $message): ?float
    {
        // WhatsApp Business API pricing varies by country and message type
        // This is a rough estimation for business-initiated conversations
        
        // Check if it's a template message (higher cost)
        $templateCost = 0.055; // Average template message cost
        $sessionCost = 0.005;  // Average conversation session cost
        
        // For simplicity, assume it's a session message
        // In real implementation, you'd check message type and destination country
        return $sessionCost;
    }

    /**
     * Get business profile information
     */
    public function getBusinessProfile(): ?array
    {
        try {
            $accessToken = $this->config['access_token'];
            $phoneNumberId = $this->config['phone_number_id'];
            $apiVersion = $this->config['api_version'] ?? 'v18.0';

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken
            ])->get("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/whatsapp_business_profile");

            if ($response->successful()) {
                return $response->json()['data'][0] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get WhatsApp business profile', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get message templates
     */
    public function getTemplates(): array
    {
        try {
            $accessToken = $this->config['access_token'];
            $businessAccountId = $this->config['business_account_id'];
            $apiVersion = $this->config['api_version'] ?? 'v18.0';

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken
            ])->get("https://graph.facebook.com/{$apiVersion}/{$businessAccountId}/message_templates");

            if ($response->successful()) {
                return $response->json()['data'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Failed to get WhatsApp templates', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Send interactive message (buttons, lists, etc.)
     */
    public function sendInteractiveMessage(string $to, array $interactive): ProviderResponse
    {
        $startTime = microtime(true);

        try {
            $accessToken = $this->config['access_token'];
            $phoneNumberId = $this->config['phone_number_id'];
            $apiVersion = $this->config['api_version'] ?? 'v18.0';

            $phoneNumber = preg_replace('/[^\d]/', '', $to);

            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $phoneNumber,
                'type' => 'interactive',
                'interactive' => $interactive
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json'
            ])->post("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages", $payload);

            $responseTime = $this->getResponseTime($startTime);

            if ($response->successful()) {
                $data = $response->json();
                
                return ProviderResponse::success(
                    'whatsapp',
                    $data['messages'][0]['id'],
                    [
                        'contacts' => $data['contacts'],
                        'response_code' => $response->status()
                    ],
                    $this->getCost($phoneNumber, 'interactive'),
                    $responseTime
                );
            }

            $error = $response->json()['error']['message'] ?? 'Unknown error';
            return ProviderResponse::failure('whatsapp', $error, [], $responseTime);

        } catch (\Exception $e) {
            return ProviderResponse::failure(
                'whatsapp',
                $e->getMessage(),
                [],
                $this->getResponseTime($startTime)
            );
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
