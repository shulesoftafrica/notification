<?php

namespace App\Services\Adapters;

use App\Models\Message;
use App\Models\ProviderConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class SmsAdapter implements ProviderAdapterInterface
{
    public function send(Message $message, array $renderedContent, ProviderConfig $config): ProviderResponse
    {
        try {
            $provider = $config->provider;
            
            return match($provider) {
                'twilio' => $this->sendWithTwilio($message, $renderedContent, $config),
                'vonage' => $this->sendWithVonage($message, $renderedContent, $config),
                'plivo' => $this->sendWithPlivo($message, $renderedContent, $config),
                default => throw new Exception("Unsupported SMS provider: {$provider}")
            };
        } catch (Exception $e) {
            Log::error('SMS adapter error', [
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
                'From' => $config->settings['from_number'],
                'To' => $message->recipient['phone'],
                'Body' => $renderedContent['content']
            ]);

        if ($response->successful()) {
            $data = $response->json();
            return ProviderResponse::success(
                $data['sid'],
                0.05, // Estimated cost per SMS
                $data
            );
        }

        return ProviderResponse::failure(
            'Twilio API error: ' . $response->body(),
            $response->json()
        );
    }

    private function sendWithVonage(Message $message, array $renderedContent, ProviderConfig $config): ProviderResponse
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config->credentials['api_token']
        ])->post('https://api.nexmo.com/v1/messages', [
            'from' => $config->settings['from_number'],
            'to' => $message->recipient['phone'],
            'text' => $renderedContent['content'],
            'message_type' => 'text'
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return ProviderResponse::success(
                $data['message_uuid'] ?? uniqid(),
                0.05,
                $data
            );
        }

        return ProviderResponse::failure(
            'Vonage API error: ' . $response->body(),
            $response->json()
        );
    }

    private function sendWithPlivo(Message $message, array $renderedContent, ProviderConfig $config): ProviderResponse
    {
        $authId = $config->credentials['auth_id'];
        $authToken = $config->credentials['auth_token'];
        
        $response = Http::withBasicAuth($authId, $authToken)
            ->asJson()
            ->post("https://api.plivo.com/v1/Account/{$authId}/Message/", [
                'src' => $config->settings['from_number'],
                'dst' => $message->recipient['phone'],
                'text' => $renderedContent['content']
            ]);

        if ($response->successful()) {
            $data = $response->json();
            return ProviderResponse::success(
                $data['message_uuid'][0] ?? uniqid(),
                0.05,
                $data
            );
        }

        return ProviderResponse::failure(
            'Plivo API error: ' . $response->body(),
            $response->json()
        );
    }

    public function validateConfig(array $config): bool
    {
        $provider = $config['provider'] ?? null;
        
        return match($provider) {
            'twilio' => isset($config['credentials']['account_sid'], $config['credentials']['auth_token']),
            'vonage' => isset($config['credentials']['api_token']),
            'plivo' => isset($config['credentials']['auth_id'], $config['credentials']['auth_token']),
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
