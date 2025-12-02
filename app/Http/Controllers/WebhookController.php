<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\WebhookProcessorService;

class WebhookController extends Controller
{
    protected $webhookProcessor;

    public function __construct(WebhookProcessorService $webhookProcessor)
    {
        $this->webhookProcessor = $webhookProcessor;
    }

    /**
     * Handle Twilio webhooks
     */
    public function twilio(Request $request)
    {
        try {
            Log::info('Twilio webhook received', $request->all());

            $result = $this->webhookProcessor->processTwilioWebhook($request->all());

            return response()->json(['status' => 'received', 'processed' => $result]);
        } catch (\Exception $e) {
            Log::error('Twilio webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle WhatsApp webhooks
     */
    public function whatsapp(Request $request)
    {
        try {
            // Verify webhook for WhatsApp
            if ($request->has('hub_mode') && $request->get('hub_mode') === 'subscribe') {
                $verifyToken = config('notification.providers.whatsapp.verify_token');

                if ($request->get('hub_verify_token') === $verifyToken) {
                    return response($request->get('hub_challenge'));
                } else {
                    return response('Forbidden', 403);
                }
            }

            Log::info('WhatsApp webhook received', $request->all());

            $result = $this->webhookProcessor->processWhatsAppWebhook($request->all());

            return response()->json(['status' => 'received', 'processed' => $result]);
        } catch (\Exception $e) {
            Log::error('WhatsApp webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle WhatsApp webhooks with logging and forwarding
     */
    public function whatsappwebhook(Request $request)
    {
        try {
            // Log all incoming requests
            Log::info('WhatsApp webhook received (whatsappwebhook)', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'headers' => $request->headers->all(),
                'body' => $request->all(),
                'raw_body' => $request->getContent(),
                'timestamp' => now()->toISOString(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Forward all requests to the specified endpoint
            try {
                $webhookUrl = 'https://safarichat.africa/api/wasender/webhook/33991';
                $data = $request->all();

                $ch = curl_init($webhookUrl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);
                curl_close($ch);

                  Log::info('WhatsApp webhook forwarded successfully', [
                    'forward_url' => $webhookUrl,
                    'forward_response' => $response
                ]);
            } catch (\Exception $forwardError) {
                Log::error('Failed to forward WhatsApp webhook', [
                    'forward_url' => $webhookUrl,
                    'error' => $forwardError->getMessage()
                ]);
            }

            // Verify webhook for WhatsApp if it's a verification request
            if ($request->has('hub_mode') && $request->get('hub_mode') === 'subscribe') {
                $verifyToken = config('notification.providers.whatsapp.verify_token');

                if ($request->get('hub_verify_token') === $verifyToken) {
                    Log::info('WhatsApp webhook verification successful');
                    return response($request->get('hub_challenge'));
                } else {
                    Log::warning('WhatsApp webhook verification failed - invalid token');
                    return response('Forbidden', 403);
                }
            }

            // Process the webhook using existing processor
            $result = $this->webhookProcessor->processWhatsAppWebhook($request->all());

            return response()->json(['status' => 'received', 'processed' => $result]);
        } catch (\Exception $e) {
            Log::error('WhatsApp webhook processing failed (whatsappwebhook)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle SendGrid webhooks
     */
    public function sendgrid(Request $request)
    {
        try {
            Log::info('SendGrid webhook received', $request->all());

            $result = $this->webhookProcessor->processSendGridWebhook($request->all());

            return response()->json(['status' => 'received', 'processed' => $result]);
        } catch (\Exception $e) {
            Log::error('SendGrid webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle Mailgun webhooks
     */
    public function mailgun(Request $request)
    {
        try {
            Log::info('Mailgun webhook received', $request->all());

            $result = $this->webhookProcessor->processMailgunWebhook($request->all());

            return response()->json(['status' => 'received', 'processed' => $result]);
        } catch (\Exception $e) {
            Log::error('Mailgun webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Generic webhook handler
     */
    public function generic(Request $request, $provider)
    {
        try {
            Log::info("Generic webhook received for {$provider}", $request->all());

            $result = $this->webhookProcessor->processGenericWebhook($provider, $request->all());

            return response()->json(['status' => 'received', 'processed' => $result]);
        } catch (\Exception $e) {
            Log::error("Generic webhook processing failed for {$provider}", [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Test webhook endpoint
     */
    public function test(Request $request)
    {
        return response()->json([
            'status' => 'received',
            'message' => 'Test webhook received successfully',
            'timestamp' => now()->toISOString(),
            'payload' => $request->all(),
        ]);
    }
}
