<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ClientWebhookService
{
    protected $maxRetries = 3;
    protected $retryDelay = 5; // seconds
    protected $timeout = 30; // seconds

    /**
     * Send webhook notification to client
     */
    public function sendWebhook($url, $payload, $secret = null, $retryCount = 0)
    {
        try {
            $headers = [
                'Content-Type' => 'application/json',
                'User-Agent' => 'NotificationService/1.0',
                'X-Webhook-Timestamp' => now()->timestamp,
            ];

            // Add signature if secret is provided
            if ($secret) {
                $signature = $this->generateSignature($payload, $secret);
                $headers['X-Webhook-Signature'] = $signature;
            }

            $response = Http::timeout($this->timeout)
                ->withHeaders($headers)
                ->post($url, $payload);

            if ($response->successful()) {
                Log::info('Webhook sent successfully', [
                    'url' => $url,
                    'status' => $response->status(),
                    'response_time' => $response->transferStats?->getTransferTime(),
                ]);

                return [
                    'success' => true,
                    'status_code' => $response->status(),
                    'response' => $response->body(),
                ];
            } else {
                throw new \Exception('HTTP ' . $response->status() . ': ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Webhook failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'retry_count' => $retryCount,
            ]);

            // Retry logic
            if ($retryCount < $this->maxRetries) {
                sleep($this->retryDelay * ($retryCount + 1)); // Exponential backoff
                return $this->sendWebhook($url, $payload, $secret, $retryCount + 1);
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retry_count' => $retryCount,
            ];
        }
    }

    /**
     * Send notification status webhook
     */
    public function sendNotificationStatus($webhookUrl, $notificationId, $status, $metadata = [])
    {
        $payload = [
            'notification_id' => $notificationId,
            'status' => $status,
            'timestamp' => now()->toISOString(),
            'metadata' => $metadata,
        ];

        return $this->sendWebhook($webhookUrl, $payload);
    }

    /**
     * Send delivery confirmation webhook
     */
    public function sendDeliveryConfirmation($webhookUrl, $notificationId, $deliveredAt, $provider)
    {
        $payload = [
            'notification_id' => $notificationId,
            'event' => 'delivered',
            'delivered_at' => $deliveredAt,
            'provider' => $provider,
            'timestamp' => now()->toISOString(),
        ];

        return $this->sendWebhook($webhookUrl, $payload);
    }

    /**
     * Send failure notification webhook
     */
    public function sendFailureNotification($webhookUrl, $notificationId, $error, $provider)
    {
        $payload = [
            'notification_id' => $notificationId,
            'event' => 'failed',
            'error' => $error,
            'provider' => $provider,
            'timestamp' => now()->toISOString(),
        ];

        return $this->sendWebhook($webhookUrl, $payload);
    }

    /**
     * Generate webhook signature
     */
    protected function generateSignature($payload, $secret)
    {
        $body = is_array($payload) ? json_encode($payload) : $payload;
        return 'sha256=' . hash_hmac('sha256', $body, $secret);
    }

    /**
     * Verify webhook signature
     */
    public function verifySignature($payload, $signature, $secret)
    {
        $expectedSignature = $this->generateSignature($payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Batch send webhooks
     */
    public function batchSendWebhooks($webhooks)
    {
        $results = [];
        
        foreach ($webhooks as $webhook) {
            $url = $webhook['url'];
            $payload = $webhook['payload'];
            $secret = $webhook['secret'] ?? null;
            
            $results[] = [
                'url' => $url,
                'result' => $this->sendWebhook($url, $payload, $secret)
            ];
        }

        return $results;
    }

    /**
     * Test webhook endpoint
     */
    public function testWebhook($url, $secret = null)
    {
        $testPayload = [
            'test' => true,
            'message' => 'This is a test webhook from Notification Service',
            'timestamp' => now()->toISOString(),
        ];

        return $this->sendWebhook($url, $testPayload, $secret);
    }
}
