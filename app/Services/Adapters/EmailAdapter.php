<?php

namespace App\Services\Adapters;

use App\Models\Message;
use App\Models\ProviderConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class EmailAdapter implements ProviderAdapterInterface
{
    public function send(Message $message, array $renderedContent, ProviderConfig $config): ProviderResponse
    {
        try {
            $provider = $config->provider;
            
            return match($provider) {
                'sendgrid' => $this->sendWithSendGrid($message, $renderedContent, $config),
                'mailgun' => $this->sendWithMailgun($message, $renderedContent, $config),
                'ses' => $this->sendWithSES($message, $renderedContent, $config),
                'resend' => $this->sendWithResend($message, $renderedContent, $config),
                default => throw new Exception("Unsupported email provider: {$provider}")
            };
        } catch (Exception $e) {
            Log::error('Email adapter error', [
                'message_id' => $message->message_id,
                'provider' => $config->provider,
                'error' => $e->getMessage()
            ]);
            return ProviderResponse::failure($e->getMessage());
        }
    }

    private function sendWithSendGrid(Message $message, array $renderedContent, ProviderConfig $config): ProviderResponse
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config->credentials['api_key'],
            'Content-Type' => 'application/json'
        ])->post('https://api.sendgrid.com/v3/mail/send', [
            'personalizations' => [[
                'to' => [['email' => $message->recipient['email']]]
            ]],
            'from' => [
                'email' => $config->settings['from_email'],
                'name' => $config->settings['from_name'] ?? null
            ],
            'subject' => $renderedContent['subject'],
            'content' => [
                ['type' => 'text/plain', 'value' => $renderedContent['content']],
                ...$renderedContent['html_content'] ? [['type' => 'text/html', 'value' => $renderedContent['html_content']]] : []
            ]
        ]);

        if ($response->successful()) {
            return ProviderResponse::success(
                $response->header('X-Message-Id') ?? uniqid(),
                0.01, // Estimated cost
                $response->json()
            );
        }

        return ProviderResponse::failure(
            'SendGrid API error: ' . $response->body(),
            $response->json()
        );
    }

    private function sendWithMailgun(Message $message, array $renderedContent, ProviderConfig $config): ProviderResponse
    {
        $domain = $config->credentials['domain'];
        $response = Http::withBasicAuth('api', $config->credentials['api_key'])
            ->asForm()
            ->post("https://api.mailgun.net/v3/{$domain}/messages", [
                'from' => $config->settings['from_email'],
                'to' => $message->recipient['email'],
                'subject' => $renderedContent['subject'],
                'text' => $renderedContent['content'],
                ...$renderedContent['html_content'] ? ['html' => $renderedContent['html_content']] : []
            ]);

        if ($response->successful()) {
            $data = $response->json();
            return ProviderResponse::success(
                $data['id'] ?? uniqid(),
                0.01,
                $data
            );
        }

        return ProviderResponse::failure(
            'Mailgun API error: ' . $response->body(),
            $response->json()
        );
    }

    private function sendWithSES(Message $message, array $renderedContent, ProviderConfig $config): ProviderResponse
    {
        // AWS SES implementation would go here
        // For now, return a mock response
        return ProviderResponse::success('ses-' . uniqid(), 0.01);
    }

    private function sendWithResend(Message $message, array $renderedContent, ProviderConfig $config): ProviderResponse
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config->credentials['api_key'],
            'Content-Type' => 'application/json'
        ])->post('https://api.resend.com/emails', [
            'from' => $config->settings['from_email'],
            'to' => [$message->recipient['email']],
            'subject' => $renderedContent['subject'],
            'text' => $renderedContent['content'],
            ...$renderedContent['html_content'] ? ['html' => $renderedContent['html_content']] : []
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return ProviderResponse::success(
                $data['id'] ?? uniqid(),
                0.01,
                $data
            );
        }

        return ProviderResponse::failure(
            'Resend API error: ' . $response->body(),
            $response->json()
        );
    }

    public function validateConfig(array $config): bool
    {
        $provider = $config['provider'] ?? null;
        
        return match($provider) {
            'sendgrid' => isset($config['credentials']['api_key']),
            'mailgun' => isset($config['credentials']['api_key'], $config['credentials']['domain']),
            'ses' => isset($config['credentials']['access_key'], $config['credentials']['secret_key']),
            'resend' => isset($config['credentials']['api_key']),
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
