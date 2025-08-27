<?php

namespace App\Services\Adapters;

use App\Models\Message;
use App\Models\ProviderConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class WhatsAppAdapter implements ProviderAdapterInterface
{
    public function send(Message $message, array $renderedContent, ProviderConfig $config): ProviderResponse
    {
        try {
            $provider = $config->provider;
            
            return match($provider) {
                'twilio' => $this->sendWithTwilio($message, $renderedContent, $config),
                'meta' => $this->sendWithMeta($message, $renderedContent, $config),
                '360dialog' => $this->sendWith360Dialog($message, $renderedContent, $config),
                default => throw new Exception("Unsupported WhatsApp provider: {$provider}")
            };
        } catch (Exception $e) {
            Log::error('WhatsApp adapter error', [
                'message_id' => $message->message_id,
                'provider' => $config->provider,
                'error' => $e->getMessage()
            ]);
            return ProviderResponse::failure($e->getMessage());
        }
    }

    private function sendWithTwilio(Message $message, array $renderedContent, ProviderConfig $config): ProviderResponse
    {
        $accountSid = $config->credentials['account_sid'];
        $authToken = $config->credentials['auth_token'];
        
        $response = Http::withBasicAuth($accountSid, $authToken)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                'From' => 'whatsapp:' . $config->settings['from_number'],
                'To' => 'whatsapp:' . $message->recipient['phone'],
                'Body' => $renderedContent['content']
            ]);

        if ($response->successful()) {
            $data = $response->json();
            return ProviderResponse::success(
                $data['sid'],
                0.10, // Estimated cost per WhatsApp message
                $data
            );
        }

        return ProviderResponse::failure(
            'Twilio WhatsApp API error: ' . $response->body(),
            $response->json()
        );
    }

    private function sendWithMeta(Message $message, array $renderedContent, ProviderConfig $config): ProviderResponse
    {
        $phoneNumberId = $config->credentials['phone_number_id'];
        $accessToken = $config->credentials['access_token'];
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json'
        ])->post("https://graph.facebook.com/v18.0/{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => $message->recipient['phone'],
            'type' => 'text',
            'text' => [
                'body' => $renderedContent['content']
            ]
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return ProviderResponse::success(
                $data['messages'][0]['id'] ?? uniqid(),
                0.10,
                $data
            );
        }

        return ProviderResponse::failure(
            'Meta WhatsApp API error: ' . $response->body(),
            $response->json()
        );
    }

    private function sendWith360Dialog(Message $message, array $renderedContent, ProviderConfig $config): ProviderResponse
    {
        $response = Http::withHeaders([
            'D360-API-KEY' => $config->credentials['api_key'],
            'Content-Type' => 'application/json'
        ])->post('https://waba.360dialog.io/v1/messages', [
            'to' => $message->recipient['phone'],
            'type' => 'text',
            'text' => [
                'body' => $renderedContent['content']
            ]
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return ProviderResponse::success(
                $data['messages'][0]['id'] ?? uniqid(),
                0.10,
                $data
            );
        }

        return ProviderResponse::failure(
            '360Dialog API error: ' . $response->body(),
            $response->json()
        );
    }

    public function validateConfig(array $config): bool
    {
        $provider = $config['provider'] ?? null;
        
        return match($provider) {
            'twilio' => isset($config['credentials']['account_sid'], $config['credentials']['auth_token']),
            'meta' => isset($config['credentials']['phone_number_id'], $config['credentials']['access_token']),
            '360dialog' => isset($config['credentials']['api_key']),
            default => false
        };
    }

    public function getDeliveryStatus(string $providerMessageId, ProviderConfig $config): ?string
    {
        // Implementation would vary by provider
        // For now, return null (status unknown)
        return null;
    }
}
