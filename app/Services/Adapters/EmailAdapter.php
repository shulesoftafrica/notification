<?php

namespace App\Services\Adapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmailAdapter implements ProviderAdapterInterface
{
    protected array $config;
    protected string $provider;

    public function __construct(array $config, string $provider = 'sendgrid')
    {
        $this->config = $config;
        $this->provider = $provider;
    }

    /**
     * Send email message
     */
    public function send(string $to, string $message, ?string $subject = null, array $metadata = []): ProviderResponse
    {
        $startTime = microtime(true);

        try {
            // Extract attachment info from metadata
            $attachment = $metadata['attachment'] ?? null;
            $attachmentMetadata = $metadata['attachment_metadata'] ?? null;
            
            switch ($this->provider) {
                case 'resend':
                    return $this->sendViaResend($to, $message, $subject, $metadata, $startTime, $attachment, $attachmentMetadata);
                case 'sendgrid':
                    return $this->sendViaSendGrid($to, $message, $subject, $metadata, $startTime, $attachment, $attachmentMetadata);
                case 'mailgun':
                    return $this->sendViaMailgun($to, $message, $subject, $metadata, $startTime, $attachment, $attachmentMetadata);
                default:
                    return ProviderResponse::failure(
                        $this->provider,
                        "Unsupported email provider: {$this->provider}",
                        [],
                        $this->getResponseTime($startTime)
                    );
            }
        } catch (\Exception $e) {
            Log::error('Email sending failed', [
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
     * Send email via Resend
     */
    protected function sendViaResend(string $to, string $message, ?string $subject, array $metadata, float $startTime, ?string $attachment = null, ?array $attachmentMetadata = null): ProviderResponse
    {
        $apiKey = $this->config['api_key'];
        $fromEmail = $this->config['from_email'];
        $fromName = 'Shulesoft';

        $payload = [
            'from' => 'Shulesoft <noreply@mail.shulesoft.co>',
                     'to' => [$to],
            'subject' => $subject ?? 'Notification',
            'html' => $message,
        ];
        // Add tags from metadata if available
        if (!empty($metadata['tags'])) {
            $payload['tags'] = array_slice($metadata['tags'], 0, 10); // Resend allows max 10 tags
        }

        // Add reply-to if specified
        if (!empty($metadata['reply_to'])) {
            $payload['reply_to'] = [$metadata['reply_to']];
        }

        // Add attachment if provided
        if ($attachment && $attachmentMetadata) {
            try {
                $filePath = storage_path('app/public' . $attachment);
                // dd($filePath);
                if (!file_exists($filePath)) {
                    throw new \Exception("Attachment file not found: {$attachment}");
                }
                
                $attachmentContent = base64_encode(file_get_contents($filePath));
                
                $payload['attachments'] = [[
                    'filename' => $attachmentMetadata['original_name'] ?? 'attachment',
                    'content' => $attachmentContent,
                ]];
                
                Log::info('Adding attachment to Resend email', [
                    'filename' => $attachmentMetadata['original_name'] ?? 'attachment',
                    'size' => $attachmentMetadata['size'] ?? 0
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to attach file to Resend email', [
                    'error' => $e->getMessage(),
                    'attachment' => $attachment
                ]);
                throw $e;
            }
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json'
        ])->post('https://api.resend.com/emails', $payload);

        $responseTime = $this->getResponseTime($startTime);

        if ($response->successful()) {
            $responseData = $response->json();
            
            return ProviderResponse::success(
                $this->provider,
                $responseData['id'] ?? null,
                [],
                $responseTime
            );
        } else {
            $errorData = $response->json();
            $errorMessage = $errorData['message'] ?? 'Unknown error occurred';
            
            Log::error('Resend API error', [
                'status' => $response->status(),
                'error' => $errorMessage,
                'to' => $to,
                'response' => $errorData
            ]);

            return ProviderResponse::failure(
                $this->provider,
                $errorMessage,
                ['status_code' => $response->status()],
                $responseTime
            );
        }
    }

    /**
     * Send email via SendGrid
     */
    protected function sendViaSendGrid(string $to, string $message, ?string $subject, array $metadata, float $startTime, ?string $attachment = null, ?array $attachmentMetadata = null): ProviderResponse
    {
        $apiKey = $this->config['api_key'];
        $fromEmail = $this->config['from_email'];
        $fromName = $this->config['from_name'] ?? 'Notification Service';

        $payload = [
            'personalizations' => [
                [
                    'to' => [['email' => $to]],
                    'subject' => $subject ?? 'Notification'
                ]
            ],
            'from' => [
                'email' => $fromEmail,
                'name' => $fromName
            ],
            'content' => [
                [
                    'type' => 'text/html',
                    'value' => $message
                ]
            ]
        ];

        // Add tracking and metadata
        if (!empty($metadata['webhook_url'])) {
            $payload['tracking_settings'] = [
                'click_tracking' => ['enable' => true],
                'open_tracking' => ['enable' => true]
            ];
        }

        // Add attachment if provided
        if ($attachment && $attachmentMetadata) {
            try {
                $filePath =  storage_path('app/public' . $attachment);
               
                
                if (!file_exists($filePath)) {
                    throw new \Exception("Attachment file not found: {$attachment}");
                }
                
                $attachmentContent = base64_encode(file_get_contents($filePath));
                
                $payload['attachments'] = [[
                    'content' => $attachmentContent,
                    'type' => $attachmentMetadata['mime_type'] ?? 'application/octet-stream',
                    'filename' => $attachmentMetadata['original_name'] ?? 'attachment',
                    'disposition' => 'attachment',
                ]];
                
                Log::info('Adding attachment to SendGrid email', [
                    'filename' => $attachmentMetadata['original_name'] ?? 'attachment',
                    'size' => $attachmentMetadata['size'] ?? 0
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to attach file to SendGrid email', [
                    'error' => $e->getMessage(),
                    'attachment' => $attachment
                ]);
                throw $e;
            }
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json'
        ])->post('https://api.sendgrid.com/v3/mail/send', $payload);

        $responseTime = $this->getResponseTime($startTime);

        if ($response->successful()) {
            // SendGrid returns 202 for successful requests
            $messageId = $response->header('X-Message-Id') ?? 'sendgrid_' . uniqid();
            
            return ProviderResponse::success(
                'sendgrid',
                $messageId,
                [
                    'response_code' => $response->status(),
                    'headers' => $response->headers()
                ],
                null, // SendGrid doesn't provide cost in response
                $responseTime
            );
        }

        $error = $response->json()['errors'][0]['message'] ?? 'Unknown error';
        return ProviderResponse::failure('sendgrid', $error, [], $responseTime);
    }

    /**
     * Send email via Mailgun
     */
    protected function sendViaMailgun(string $to, string $message, ?string $subject, array $metadata, float $startTime, ?string $attachment = null, ?array $attachmentMetadata = null): ProviderResponse
    {
        $apiKey = $this->config['api_key'];
        $domain = $this->config['domain'];
        $fromEmail = $this->config['from_email'];
        $fromName = $this->config['from_name'] ?? 'Notification Service';

        $payload = [
            'from' => "{$fromName} <{$fromEmail}>",
            'to' => $to,
            'subject' => $subject ?? 'Notification',
            'html' => $message
        ];

        // Add tracking
        if (!empty($metadata['webhook_url'])) {
            $payload['o:tracking'] = 'yes';
            $payload['o:tracking-clicks'] = 'yes';
            $payload['o:tracking-opens'] = 'yes';
        }

        // Handle attachment - Mailgun uses multipart/form-data
        $request = Http::withBasicAuth('api', $apiKey);
        
        if ($attachment && $attachmentMetadata) {
            try {
                $filePath =  storage_path('app/public' . $attachment);
                
                if (!file_exists($filePath)) {
                    throw new \Exception("Attachment file not found: {$attachment}");
                }
                
                $request = $request->attach(
                    'attachment',
                    file_get_contents($filePath),
                    $attachmentMetadata['original_name'] ?? 'attachment'
                );
                
                Log::info('Adding attachment to Mailgun email', [
                    'filename' => $attachmentMetadata['original_name'] ?? 'attachment',
                    'size' => $attachmentMetadata['size'] ?? 0
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to attach file to Mailgun email', [
                    'error' => $e->getMessage(),
                    'attachment' => $attachment
                ]);
                throw $e;
            }
        }

        $response = $request->asForm()->post("https://api.mailgun.net/v3/{$domain}/messages", $payload);

        $responseTime = $this->getResponseTime($startTime);

        if ($response->successful()) {
            $data = $response->json();
            
            return ProviderResponse::success(
                'mailgun',
                $data['id'],
                [
                    'message' => $data['message'],
                    'response_code' => $response->status()
                ],
                null, // Mailgun doesn't provide cost in response
                $responseTime
            );
        }

        $error = $response->json()['message'] ?? 'Unknown error';
        return ProviderResponse::failure('mailgun', $error, [], $responseTime);
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
                case 'resend':
                    return $this->checkResendHealth();
                case 'sendgrid':
                    return $this->checkSendGridHealth();
                case 'mailgun':
                    return $this->checkMailgunHealth();
                default:
                    return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check Resend health
     */
    protected function checkResendHealth(): bool
    {
        $apiKey = $this->config['api_key'];

        // Resend doesn't have a dedicated health endpoint, so we check API keys endpoint
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey
        ])->get('https://api.resend.com/api-keys');

        return $response->successful();
    }

    /**
     * Check SendGrid health
     */
    protected function checkSendGridHealth(): bool
    {
        $apiKey = $this->config['api_key'];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey
        ])->get('https://api.sendgrid.com/v3/user/profile');

        return $response->successful();
    }

    /**
     * Check Mailgun health
     */
    protected function checkMailgunHealth(): bool
    {
        $apiKey = $this->config['api_key'];
        $domain = $this->config['domain'];

        $response = Http::withBasicAuth('api', $apiKey)
            ->get("https://api.mailgun.net/v3/{$domain}");

        return $response->successful();
    }

    /**
     * Get provider capabilities
     */
    public function getCapabilities(): array
    {
        return [
            'html' => true,
            'attachments' => true,
            'templates' => true,
            'tracking' => true,
            'analytics' => true,
            'suppression_lists' => true,
            'bounce_handling' => true,
            'unsubscribe_handling' => true,
        ];
    }

    /**
     * Get provider configuration
     */
    public function getConfig(): array
    {
        return [
            'provider' => $this->provider,
            'from_email' => $this->config['from_email'] ?? null,
            'from_name' => $this->config['from_name'] ?? null,
            'domain' => $this->config['domain'] ?? null,
            'capabilities' => $this->getCapabilities(),
        ];
    }

    /**
     * Validate email address
     */
    public function validateRecipient(string $recipient): bool
    {
        return filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Get maximum message length
     */
    public function getMaxMessageLength(): int
    {
        return 102400; // 100KB for email content
    }

    /**
     * Get delivery status from provider
     */
    public function getDeliveryStatus(string $messageId): ?array
    {
        try {
            switch ($this->provider) {
                case 'sendgrid':
                    return $this->getSendGridDeliveryStatus($messageId);
                case 'mailgun':
                    return $this->getMailgunDeliveryStatus($messageId);
                default:
                    return null;
            }
        } catch (\Exception $e) {
            Log::error('Failed to get email delivery status', [
                'provider' => $this->provider,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get SendGrid delivery status
     */
    protected function getSendGridDeliveryStatus(string $messageId): ?array
    {
        $apiKey = $this->config['api_key'];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey
        ])->get('https://api.sendgrid.com/v3/messages', [
            'query' => "msg_id=\"{$messageId}\""
        ]);

        if ($response->successful()) {
            $data = $response->json();
            if (!empty($data['messages'])) {
                $message = $data['messages'][0];
                return [
                    'status' => $message['status'],
                    'events' => $message['events'] ?? [],
                    'last_event_time' => $message['last_event_time'] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * Get Mailgun delivery status
     */
    protected function getMailgunDeliveryStatus(string $messageId): ?array
    {
        $apiKey = $this->config['api_key'];
        $domain = $this->config['domain'];

        $response = Http::withBasicAuth('api', $apiKey)
            ->get("https://api.mailgun.net/v3/{$domain}/events", [
                'message-id' => $messageId
            ]);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'events' => $data['items'] ?? [],
                'total_count' => $data['paging']['total'] ?? 0,
            ];
        }

        return null;
    }

    /**
     * Get estimated cost for email
     */
    public function getCost(string $to, string $message): ?float
    {
        // Rough cost estimation
        switch ($this->provider) {
            case 'resend':
                // Resend: $0.001 per email (first 3,000 free monthly)
                return 0.001;
            case 'sendgrid':
                // SendGrid: ~$0.0006 per email for most plans
                return 0.0006;
            case 'mailgun':
                // Mailgun: ~$0.0008 per email
                return 0.0008;
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
