<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\AnalyticsService;
use App\Services\MetricsService;
use App\Services\ClientWebhookService;

class WebhookProcessorService
{
    protected $analyticsService;
    protected $metricsService;
    protected $clientWebhookService;

    public function __construct(
        AnalyticsService $analyticsService,
        MetricsService $metricsService,
        ClientWebhookService $clientWebhookService
    ) {
        $this->analyticsService = $analyticsService;
        $this->metricsService = $metricsService;
        $this->clientWebhookService = $clientWebhookService;
    }

    /**
     * Process Twilio webhook
     */
    public function processTwilioWebhook($payload)
    {
        $messageStatus = $payload['MessageStatus'] ?? null;
        $messageSid = $payload['MessageSid'] ?? null;

        if (!$messageSid) {
            return false;
        }

        // Update message status in database
        $updated = DB::table('notification_logs')
            ->where('provider_message_id', $messageSid)
            ->update([
                'status' => $this->mapTwilioStatus($messageStatus),
                'provider_response' => json_encode($payload),
                'updated_at' => now(),
            ]);

        // Track metrics
        if (in_array($messageStatus, ['delivered', 'sent'])) {
            $this->metricsService->trackNotificationDelivered('twilio', 'sms');
        } elseif (in_array($messageStatus, ['failed', 'undelivered'])) {
            $this->metricsService->trackNotificationFailed('twilio', 'sms', $messageStatus);
        }

        // Send client webhook if configured
        $this->sendClientWebhook($messageSid, $messageStatus, $payload);

        return $updated > 0;
    }

    /**
     * Process WhatsApp webhook
     */
    public function processWhatsAppWebhook($payload)
    {
        $entry = $payload['entry'][0] ?? null;
        if (!$entry) return false;

        $changes = $entry['changes'][0] ?? null;
        if (!$changes) return false;

        $value = $changes['value'] ?? null;
        if (!$value) return false;

        // Process status updates
        if (isset($value['statuses'])) {
            foreach ($value['statuses'] as $status) {
                $this->processWhatsAppStatus($status);
            }
        }

        // Process incoming messages (if needed)
        if (isset($value['messages'])) {
            foreach ($value['messages'] as $message) {
                $this->processWhatsAppIncomingMessage($message);
            }
        }

        return true;
    }

    /**
     * Process WhatsApp status update
     */
    protected function processWhatsAppStatus($status)
    {
        $messageId = $status['id'] ?? null;
        $statusValue = $status['status'] ?? null;

        if (!$messageId) return false;

        // Update message status
        $updated = DB::table('notification_logs')
            ->where('provider_message_id', $messageId)
            ->update([
                'status' => $this->mapWhatsAppStatus($statusValue),
                'provider_response' => json_encode($status),
                'updated_at' => now(),
            ]);

        // Track metrics
        if ($statusValue === 'delivered') {
            $this->metricsService->trackNotificationDelivered('whatsapp', 'whatsapp');
        } elseif ($statusValue === 'failed') {
            $this->metricsService->trackNotificationFailed('whatsapp', 'whatsapp', 'delivery_failed');
        }

        // Send client webhook
        $this->sendClientWebhook($messageId, $statusValue, $status);

        return $updated > 0;
    }

    /**
     * Process SendGrid webhook
     */
    public function processSendGridWebhook($payload)
    {
        if (!is_array($payload)) {
            $payload = json_decode($payload, true);
        }

        foreach ($payload as $event) {
            $this->processSendGridEvent($event);
        }

        return true;
    }

    /**
     * Process individual SendGrid event
     */
    protected function processSendGridEvent($event)
    {
        $messageId = $event['sg_message_id'] ?? null;
        $eventType = $event['event'] ?? null;

        if (!$messageId) return false;

        // Update message status
        $updated = DB::table('notification_logs')
            ->where('provider_message_id', $messageId)
            ->update([
                'status' => $this->mapSendGridStatus($eventType),
                'provider_response' => json_encode($event),
                'updated_at' => now(),
            ]);

        // Track metrics
        if ($eventType === 'delivered') {
            $this->metricsService->trackNotificationDelivered('sendgrid', 'email');
        } elseif (in_array($eventType, ['bounce', 'dropped'])) {
            $this->metricsService->trackNotificationFailed('sendgrid', 'email', $eventType);
        }

        // Send client webhook
        $this->sendClientWebhook($messageId, $eventType, $event);

        return $updated > 0;
    }

    /**
     * Process Mailgun webhook
     */
    public function processMailgunWebhook($payload)
    {
        $eventData = $payload['event-data'] ?? null;
        if (!$eventData) return false;

        $messageId = $eventData['message']['headers']['message-id'] ?? null;
        $eventType = $eventData['event'] ?? null;

        if (!$messageId) return false;

        // Update message status
        $updated = DB::table('notification_logs')
            ->where('provider_message_id', $messageId)
            ->update([
                'status' => $this->mapMailgunStatus($eventType),
                'provider_response' => json_encode($eventData),
                'updated_at' => now(),
            ]);

        // Track metrics
        if ($eventType === 'delivered') {
            $this->metricsService->trackNotificationDelivered('mailgun', 'email');
        } elseif (in_array($eventType, ['failed', 'rejected'])) {
            $this->metricsService->trackNotificationFailed('mailgun', 'email', $eventType);
        }

        // Send client webhook
        $this->sendClientWebhook($messageId, $eventType, $eventData);

        return $updated > 0;
    }

    /**
     * Process generic webhook
     */
    public function processGenericWebhook($provider, $payload)
    {
        // Store raw webhook data
        DB::table('webhook_logs')->insert([
            'provider' => $provider,
            'payload' => json_encode($payload),
            'processed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info("Generic webhook stored for {$provider}", [
            'provider' => $provider,
            'payload' => $payload,
        ]);

        return true;
    }

    /**
     * Send client webhook notification
     */
    protected function sendClientWebhook($messageId, $status, $payload)
    {
        try {
            // Get notification record with client webhook URL
            $notification = DB::table('notification_logs')
                ->where('provider_message_id', $messageId)
                ->first();

            if (!$notification || !$notification->client_webhook_url) {
                return;
            }

            $this->clientWebhookService->sendNotificationStatus(
                $notification->client_webhook_url,
                $notification->id,
                $status,
                $payload
            );

        } catch (\Exception $e) {
            Log::error('Failed to send client webhook', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Map Twilio status to internal status
     */
    protected function mapTwilioStatus($status)
    {
        return match($status) {
            'sent' => 'sent',
            'delivered' => 'delivered',
            'failed', 'undelivered' => 'failed',
            'queued' => 'queued',
            default => 'unknown',
        };
    }

    /**
     * Map WhatsApp status to internal status
     */
    protected function mapWhatsAppStatus($status)
    {
        return match($status) {
            'sent' => 'sent',
            'delivered' => 'delivered',
            'read' => 'read',
            'failed' => 'failed',
            default => 'unknown',
        };
    }

    /**
     * Map SendGrid status to internal status
     */
    protected function mapSendGridStatus($event)
    {
        return match($event) {
            'processed' => 'sent',
            'delivered' => 'delivered',
            'bounce', 'dropped' => 'failed',
            'deferred' => 'queued',
            'open' => 'opened',
            'click' => 'clicked',
            default => 'unknown',
        };
    }

    /**
     * Map Mailgun status to internal status
     */
    protected function mapMailgunStatus($event)
    {
        return match($event) {
            'accepted' => 'sent',
            'delivered' => 'delivered',
            'failed', 'rejected' => 'failed',
            'opened' => 'opened',
            'clicked' => 'clicked',
            default => 'unknown',
        };
    }

    /**
     * Process WhatsApp incoming message
     */
    protected function processWhatsAppIncomingMessage($message)
    {
        // Store incoming message for processing
        DB::table('incoming_messages')->insert([
            'provider' => 'whatsapp',
            'provider_message_id' => $message['id'],
            'from' => $message['from'],
            'type' => $message['type'],
            'content' => json_encode($message),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('Incoming WhatsApp message stored', [
            'message_id' => $message['id'],
            'from' => $message['from'],
            'type' => $message['type'],
        ]);
    }
}
