<?php

namespace App\Http\Controllers;

use App\Services\WebhookProcessorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class WebhookController extends Controller
{
    private WebhookProcessorService $webhookProcessor;

    public function __construct(WebhookProcessorService $webhookProcessor)
    {
        $this->webhookProcessor = $webhookProcessor;
    }

    /**
     * Handle Twilio webhook
     */
    public function twilio(Request $request): JsonResponse
    {
        $result = $this->webhookProcessor->processWebhook('twilio', $request);
        
        if ($result['success']) {
            return response()->json([
                'status' => 'success',
                'processed' => $result['processed'] ?? 0
            ]);
        }

        return response()->json([
            'status' => 'error',
            'error' => $result['error']
        ], 400);
    }

    /**
     * Handle SendGrid webhook
     */
    public function sendgrid(Request $request): JsonResponse
    {
        $result = $this->webhookProcessor->processWebhook('sendgrid', $request);
        
        if ($result['success']) {
            return response()->json([
                'status' => 'success',
                'processed' => $result['processed'] ?? 0,
                'errors' => $result['errors'] ?? []
            ]);
        }

        return response()->json([
            'status' => 'error',
            'error' => $result['error']
        ], 400);
    }

    /**
     * Handle Meta WhatsApp webhook
     */
    public function whatsapp(Request $request): JsonResponse
    {
        $result = $this->webhookProcessor->processWebhook('whatsapp', $request);
        
        if ($result['success']) {
            // WhatsApp verification challenge
            if (isset($result['challenge'])) {
                return response($result['challenge'], 200);
            }

            return response()->json([
                'status' => 'success',
                'processed' => $result['processed'] ?? 0
            ]);
        }

        return response()->json([
            'status' => 'error',
            'error' => $result['error']
        ], 400);
    }

    /**
     * Handle Mailgun webhook
     */
    public function mailgun(Request $request): JsonResponse
    {
        $result = $this->webhookProcessor->processWebhook('mailgun', $request);
        
        if ($result['success']) {
            return response()->json([
                'status' => 'success',
                'processed' => $result['processed'] ?? 0
            ]);
        }

        return response()->json([
            'status' => 'error',
            'error' => $result['error']
        ], 400);
    }

    /**
     * Handle Vonage SMS webhook
     */
    public function vonage(Request $request): JsonResponse
    {
        $result = $this->webhookProcessor->processWebhook('vonage', $request);
        
        if ($result['success']) {
            return response()->json([
                'status' => 'success',
                'processed' => $result['processed'] ?? 0
            ]);
        }

        return response()->json([
            'status' => 'error',
            'error' => $result['error']
        ], 400);
    }

    /**
     * Handle Resend webhook
     */
    public function resend(Request $request): JsonResponse
    {
        $result = $this->webhookProcessor->processWebhook('resend', $request);
        
        if ($result['success']) {
            return response()->json([
                'status' => 'success',
                'processed' => $result['processed'] ?? 0
            ]);
        }

        return response()->json([
            'status' => 'error',
            'error' => $result['error']
        ], 400);
    }
}
