<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class WebhookVerificationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();
        
        // Handle Twilio webhook verification
        if (str_contains($path, 'twilio')) {
            return $this->verifyTwilioWebhook($request, $next);
        }
        
        // Handle WhatsApp webhook verification
        if (str_contains($path, 'whatsapp')) {
            return $this->verifyWhatsAppWebhook($request, $next);
        }
        
        // Handle SendGrid webhook verification
        if (str_contains($path, 'sendgrid')) {
            return $this->verifySendGridWebhook($request, $next);
        }
        
        // Handle Mailgun webhook verification
        if (str_contains($path, 'mailgun')) {
            return $this->verifyMailgunWebhook($request, $next);
        }

        // For unspecified webhook endpoints, proceed without verification
        return $next($request);
    }

    /**
     * Verify Twilio webhook signature
     */
    protected function verifyTwilioWebhook(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Twilio-Signature');
        
        if (!$signature) {
            Log::warning('Twilio webhook received without signature');
            return response()->json(['error' => 'Missing signature'], 400);
        }

        $authToken = config('services.twilio.auth_token');
        $url = $request->fullUrl();
        $postVars = $request->all();

        // Build the expected signature
        $expectedSignature = base64_encode(hash_hmac('sha1', $url . http_build_query($postVars), $authToken, true));

        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('Twilio webhook signature verification failed', [
                'expected' => $expectedSignature,
                'received' => $signature,
                'url' => $url
            ]);
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        return $next($request);
    }

    /**
     * Verify WhatsApp webhook signature
     */
    protected function verifyWhatsAppWebhook(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Hub-Signature-256');
        
        if (!$signature) {
            Log::warning('WhatsApp webhook received without signature');
            return response()->json(['error' => 'Missing signature'], 400);
        }

        $appSecret = config('services.whatsapp.webhook_secret');
        $payload = $request->getContent();
        
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);

        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('WhatsApp webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        return $next($request);
    }

    /**
     * Verify SendGrid webhook signature
     */
    protected function verifySendGridWebhook(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Twilio-Email-Event-Webhook-Signature');
        $timestamp = $request->header('X-Twilio-Email-Event-Webhook-Timestamp');
        
        if (!$signature || !$timestamp) {
            Log::warning('SendGrid webhook received without required headers');
            return response()->json(['error' => 'Missing required headers'], 400);
        }

        $publicKey = config('services.sendgrid.webhook_public_key');
        $payload = $request->getContent();
        
        // Verify timestamp (should be within 10 minutes)
        $currentTime = time();
        if (abs($currentTime - $timestamp) > 600) {
            Log::warning('SendGrid webhook timestamp too old', ['timestamp' => $timestamp, 'current' => $currentTime]);
            return response()->json(['error' => 'Timestamp too old'], 400);
        }

        // For production, implement proper SendGrid signature verification
        // This requires the sendgrid/sendgrid package and proper public key verification
        
        return $next($request);
    }

    /**
     * Verify Mailgun webhook signature
     */
    protected function verifyMailgunWebhook(Request $request, Closure $next): Response
    {
        $signature = $request->input('signature');
        $timestamp = $request->input('timestamp');
        $token = $request->input('token');
        
        if (!$signature || !$timestamp || !$token) {
            Log::warning('Mailgun webhook received without required signature data');
            return response()->json(['error' => 'Missing signature data'], 400);
        }

        $signingKey = config('services.mailgun.webhook_signing_key');
        $expectedSignature = hash_hmac('sha256', $timestamp . $token, $signingKey);

        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('Mailgun webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        // Check timestamp (should be within 15 minutes)
        $currentTime = time();
        if (abs($currentTime - $timestamp) > 900) {
            Log::warning('Mailgun webhook timestamp too old', ['timestamp' => $timestamp, 'current' => $currentTime]);
            return response()->json(['error' => 'Timestamp too old'], 400);
        }

        return $next($request);
    }
}
