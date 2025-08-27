<?php

namespace App\Services;

use App\Models\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Carbon\Carbon;

class WebhookProcessorService
{
    /**
     * Process webhook from provider
     */
    public function processWebhook(string $provider, Request $request): array
    {
        try {
            Log::info('Processing webhook', [
                'provider' => $provider,
                'headers' => $request->headers->all(),
                'payload' => $request->all()
            ]);

            // Validate webhook signature if required
            if (!$this->validateWebhookSignature($provider, $request)) {
                Log::warning('Invalid webhook signature', ['provider' => $provider]);
                return [
                    'success' => false,
                    'error' => 'Invalid signature'
                ];
            }

            // Process based on provider
            return match($provider) {
                'sendgrid' => $this->processSendGridWebhook($request),
                'mailgun' => $this->processMailgunWebhook($request),
                'resend' => $this->processResendWebhook($request),
                'twilio' => $this->processTwilioWebhook($request),
                'vonage' => $this->processVonageWebhook($request),
                'whatsapp' => $this->processWhatsAppWebhook($request),
                default => [
                    'success' => false,
                    'error' => 'Unsupported provider: ' . $provider
                ]
            };

        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process SendGrid webhook
     */
    private function processSendGridWebhook(Request $request): array
    {
        $events = $request->json()->all();
        $processed = 0;
        $errors = [];

        foreach ($events as $event) {
            try {
                $messageId = $event['unique_arg_message_id'] ?? null;
                if (!$messageId) {
                    continue;
                }

                $status = $this->mapSendGridStatus($event['event']);
                $timestamp = isset($event['timestamp']) ? Carbon::createFromTimestamp($event['timestamp']) : now();

                $this->updateMessageStatus($messageId, $status, [
                    'provider_event' => $event['event'],
                    'provider_timestamp' => $timestamp,
                    'provider_data' => $event,
                    'ip' => $event['ip'] ?? null,
                    'user_agent' => $event['useragent'] ?? null,
                    'reason' => $event['reason'] ?? null
                ]);

                $processed++;

            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
                Log::error('SendGrid webhook event error', [
                    'event' => $event,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'success' => true,
            'processed' => $processed,
            'errors' => $errors
        ];
    }

    /**
     * Process Mailgun webhook
     */
    private function processMailgunWebhook(Request $request): array
    {
        $eventData = $request->input('event-data', []);
        
        try {
            $messageId = $eventData['user-variables']['message_id'] ?? null;
            if (!$messageId) {
                return ['success' => false, 'error' => 'No message ID found'];
            }

            $status = $this->mapMailgunStatus($eventData['event']);
            $timestamp = isset($eventData['timestamp']) ? Carbon::createFromTimestamp($eventData['timestamp']) : now();

            $this->updateMessageStatus($messageId, $status, [
                'provider_event' => $eventData['event'],
                'provider_timestamp' => $timestamp,
                'provider_data' => $eventData,
                'recipient' => $eventData['recipient'] ?? null,
                'reason' => $eventData['reason'] ?? null,
                'severity' => $eventData['severity'] ?? null
            ]);

            return ['success' => true, 'processed' => 1];

        } catch (\Exception $e) {
            Log::error('Mailgun webhook error', [
                'event_data' => $eventData,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process Resend webhook
     */
    private function processResendWebhook(Request $request): array
    {
        $payload = $request->json()->all();
        
        try {
            $messageId = $payload['data']['tags']['message_id'] ?? null;
            if (!$messageId) {
                return ['success' => false, 'error' => 'No message ID found'];
            }

            $status = $this->mapResendStatus($payload['type']);
            $timestamp = isset($payload['created_at']) ? Carbon::parse($payload['created_at']) : now();

            $this->updateMessageStatus($messageId, $status, [
                'provider_event' => $payload['type'],
                'provider_timestamp' => $timestamp,
                'provider_data' => $payload['data'],
                'email_id' => $payload['data']['email_id'] ?? null
            ]);

            return ['success' => true, 'processed' => 1];

        } catch (\Exception $e) {
            Log::error('Resend webhook error', [
                'payload' => $payload,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process Twilio webhook
     */
    private function processTwilioWebhook(Request $request): array
    {
        try {
            $messageSid = $request->input('MessageSid');
            $status = $request->input('MessageStatus');
            
            // Find message by provider_message_id
            $message = Message::where('provider_message_id', $messageSid)->first();
            if (!$message) {
                return ['success' => false, 'error' => 'Message not found'];
            }

            $mappedStatus = $this->mapTwilioStatus($status);
            
            $this->updateMessageStatus($message->message_id, $mappedStatus, [
                'provider_event' => $status,
                'provider_timestamp' => now(),
                'provider_data' => $request->all(),
                'error_code' => $request->input('ErrorCode'),
                'error_message' => $request->input('ErrorMessage')
            ]);

            return ['success' => true, 'processed' => 1];

        } catch (\Exception $e) {
            Log::error('Twilio webhook error', [
                'payload' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process Vonage webhook
     */
    private function processVonageWebhook(Request $request): array
    {
        try {
            $messageId = $request->input('messageId');
            $status = $request->input('status');
            
            // Find message by provider_message_id
            $message = Message::where('provider_message_id', $messageId)->first();
            if (!$message) {
                return ['success' => false, 'error' => 'Message not found'];
            }

            $mappedStatus = $this->mapVonageStatus($status);
            
            $this->updateMessageStatus($message->message_id, $mappedStatus, [
                'provider_event' => $status,
                'provider_timestamp' => now(),
                'provider_data' => $request->all(),
                'error_text' => $request->input('err-txt')
            ]);

            return ['success' => true, 'processed' => 1];

        } catch (\Exception $e) {
            Log::error('Vonage webhook error', [
                'payload' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process WhatsApp webhook
     */
    private function processWhatsAppWebhook(Request $request): array
    {
        // Handle WhatsApp Business API webhooks
        $payload = $request->json()->all();
        
        try {
            if ($request->isMethod('GET')) {
                // Webhook verification
                $challenge = $request->input('hub_challenge');
                $verifyToken = $request->input('hub_verify_token');
                
                if ($verifyToken === config('services.whatsapp.verify_token')) {
                    return ['challenge' => $challenge];
                }
                
                return ['success' => false, 'error' => 'Invalid verify token'];
            }

            // Process status updates
            if (isset($payload['entry'][0]['changes'][0]['value']['statuses'])) {
                $statuses = $payload['entry'][0]['changes'][0]['value']['statuses'];
                $processed = 0;
                
                foreach ($statuses as $status) {
                    $providerId = $status['id'];
                    $message = Message::where('provider_message_id', $providerId)->first();
                    
                    if ($message) {
                        $mappedStatus = $this->mapWhatsAppStatus($status['status']);
                        
                        $this->updateMessageStatus($message->message_id, $mappedStatus, [
                            'provider_event' => $status['status'],
                            'provider_timestamp' => isset($status['timestamp']) ? Carbon::createFromTimestamp($status['timestamp']) : now(),
                            'provider_data' => $status
                        ]);
                        
                        $processed++;
                    }
                }
                
                return ['success' => true, 'processed' => $processed];
            }

            return ['success' => true, 'processed' => 0];

        } catch (\Exception $e) {
            Log::error('WhatsApp webhook error', [
                'payload' => $payload,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update message status from webhook
     */
    private function updateMessageStatus(string $messageId, string $status, array $metadata = []): void
    {
        $message = Message::where('message_id', $messageId)->first();
        
        if (!$message) {
            Log::warning('Message not found for webhook update', ['message_id' => $messageId]);
            return;
        }

        $updates = ['status' => $status];
        
        // Set delivery timestamp for specific statuses
        if ($status === 'delivered' && !$message->delivered_at) {
            $updates['delivered_at'] = $metadata['provider_timestamp'] ?? now();
        } elseif ($status === 'failed' && !$message->failed_at) {
            $updates['failed_at'] = $metadata['provider_timestamp'] ?? now();
            $updates['failure_reason'] = $metadata['reason'] ?? 'Provider reported failure';
        }

        // Update metadata
        $existingMetadata = $message->metadata ?? [];
        $updates['metadata'] = array_merge($existingMetadata, [
            'webhook_updates' => array_merge(
                $existingMetadata['webhook_updates'] ?? [],
                [
                    [
                        'timestamp' => now(),
                        'status' => $status,
                        'provider_data' => $metadata
                    ]
                ]
            )
        ]);

        $message->update($updates);

        Log::info('Message status updated from webhook', [
            'message_id' => $messageId,
            'old_status' => $message->status,
            'new_status' => $status,
            'provider_event' => $metadata['provider_event'] ?? null
        ]);
    }

    /**
     * Validate webhook signature
     */
    private function validateWebhookSignature(string $provider, Request $request): bool
    {
        // Implementation depends on provider-specific signature validation
        return match($provider) {
            'sendgrid' => $this->validateSendGridSignature($request),
            'mailgun' => $this->validateMailgunSignature($request),
            'resend' => $this->validateResendSignature($request),
            'twilio' => $this->validateTwilioSignature($request),
            'vonage' => true, // Vonage doesn't use signatures by default
            'whatsapp' => $this->validateWhatsAppSignature($request),
            default => false
        };
    }

    // Status mapping methods
    private function mapSendGridStatus(string $event): string
    {
        return match($event) {
            'delivered' => 'delivered',
            'bounce', 'blocked', 'dropped' => 'failed',
            'deferred' => 'pending',
            'processed' => 'sent',
            'open' => 'opened',
            'click' => 'clicked',
            'unsubscribe' => 'unsubscribed',
            'spamreport' => 'spam',
            default => 'unknown'
        };
    }

    private function mapMailgunStatus(string $event): string
    {
        return match($event) {
            'delivered' => 'delivered',
            'failed', 'permanent_fail' => 'failed',
            'temporary_fail' => 'pending',
            'accepted' => 'sent',
            'opened' => 'opened',
            'clicked' => 'clicked',
            'unsubscribed' => 'unsubscribed',
            'complained' => 'spam',
            default => 'unknown'
        };
    }

    private function mapResendStatus(string $type): string
    {
        return match($type) {
            'email.delivered' => 'delivered',
            'email.bounced' => 'failed',
            'email.complained' => 'spam',
            'email.sent' => 'sent',
            'email.opened' => 'opened',
            'email.clicked' => 'clicked',
            default => 'unknown'
        };
    }

    private function mapTwilioStatus(string $status): string
    {
        return match($status) {
            'delivered' => 'delivered',
            'failed', 'undelivered' => 'failed',
            'sent' => 'sent',
            'queued', 'accepted' => 'pending',
            'read' => 'read',
            default => 'unknown'
        };
    }

    private function mapVonageStatus(string $status): string
    {
        return match($status) {
            'delivered' => 'delivered',
            'failed', 'rejected' => 'failed',
            'buffered' => 'pending',
            'accepted' => 'sent',
            default => 'unknown'
        };
    }

    private function mapWhatsAppStatus(string $status): string
    {
        return match($status) {
            'delivered' => 'delivered',
            'failed' => 'failed',
            'sent' => 'sent',
            'read' => 'read',
            default => 'unknown'
        };
    }

    // Signature validation methods (simplified - implement based on provider docs)
    private function validateSendGridSignature(Request $request): bool
    {
        // Implement SendGrid signature validation
        return true;
    }

    private function validateMailgunSignature(Request $request): bool
    {
        // Implement Mailgun signature validation
        return true;
    }

    private function validateResendSignature(Request $request): bool
    {
        // Implement Resend signature validation
        return true;
    }

    private function validateTwilioSignature(Request $request): bool
    {
        // Implement Twilio signature validation
        return true;
    }

    private function validateWhatsAppSignature(Request $request): bool
    {
        // Implement WhatsApp signature validation
        return true;
    }
}
