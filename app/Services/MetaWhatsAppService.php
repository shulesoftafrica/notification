<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Meta WhatsApp Business API Service
 *
 * Provides clean interface for sending messages via Meta's Official WhatsApp Business API.
 * Implements automatic fallback to WaSender on failures.
 *
 * @link https://developers.facebook.com/docs/whatsapp/cloud-api
 */
class MetaWhatsAppService
{
    protected string $baseUrl;
    protected string $phoneNumberId;
    protected string $accessToken;
    protected string $apiVersion;
    protected WaSenderService $waSenderService;
    protected bool $enableFallback;
    protected bool $logRequests;

    public function __construct(WaSenderService $waSenderService)
    {
        $this->baseUrl = rtrim(config('meta_whatsapp.base_url', 'https://graph.facebook.com'), '/');
        $this->phoneNumberId = config('meta_whatsapp.phone_number_id');
        $this->accessToken = config('meta_whatsapp.access_token');
        $this->apiVersion = config('meta_whatsapp.api_version', 'v24.0');
        $this->waSenderService = $waSenderService;
        $this->enableFallback = config('meta_whatsapp.settings.enable_fallback', true);
        $this->logRequests = config('meta_whatsapp.settings.log_requests', true);

        if (empty($this->accessToken)) {
            Log::warning('Meta WhatsApp access token not configured');
        }
    }

    /**
     * Send OTP template message
     *
     * @param string $phoneNumber Phone number in international format
     * @param string $otpCode OTP code (4-8 digits)
     * @param string|null $otpCode2 Optional second OTP parameter for button
     * @return array Response with success status and data
     */
    public function sendOtpTemplate(string $phoneNumber, string $otpCode, ?string $otpCode2 = null): array
    {
        $cleanPhone = $this->formatPhoneNumber($phoneNumber);
        $otpCode2 = $otpCode2 ?? $otpCode;

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $cleanPhone,
            'type' => 'template',
            'template' => [
                'name' => config('meta_whatsapp.otp_template_name', 'otp'),
                'language' => [
                    'code' => config('meta_whatsapp.otp_template_language', 'en'),
                ],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $otpCode,
                            ],
                        ],
                    ],
                    [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '0',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $otpCode2,
                            ],
                        ],
                    ],
                ],
            ],
        ];
        Log::info('send throgh this sendOtpTemplate method', $payload);
        return $this->sendWithFallback($cleanPhone, $payload, 'otp_verification', "Your OTP: $otpCode");
    }

    /**
     * Send text message
     */
    public function sendTextMessage(string $phoneNumber, string $message, bool $previewUrl = false): array
    {
        $cleanPhone = $this->formatPhoneNumber($phoneNumber);

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $cleanPhone,
            'type' => 'text',
            'text' => [
                'preview_url' => $previewUrl,
                'body' => $message,
            ],
        ];
        Log::info('send throgh sendTextMessage method', $payload);

        return $this->sendWithFallback($cleanPhone, $payload, 'text', $message);
    }

    /**
     * Send message with automatic fallback to WaSender
     */
    protected function sendWithFallback(string $phoneNumber, array $payload, string $messageType, string $fallbackMessage): array
    {
        try {
            $response = $this->makeApiRequest('messages', $payload);

            if ($response['success'] ?? false) {
                $this->logOperation('send_' . $messageType, [
                    'phone' => $phoneNumber,
                    'success' => true,
                    'message_id' => $response['data']['messages'][0]['id'] ?? null,
                ]);

                return $response;
            }

            if ($this->enableFallback) {
                return $this->fallbackToWaSender($phoneNumber, $fallbackMessage, $messageType, $response);
            }

            return $response;
        } catch (Exception $e) {
            Log::error("Error sending {$messageType} via Meta WhatsApp: " . $e->getMessage(), [
                'phone' => $phoneNumber,
                'exception' => $e,
            ]);
            $this->logOperation('send_' . $messageType . '_error', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);

            if ($this->enableFallback) {
                Log::info("Falling back to WaSender for {$messageType} due to error: " . $e->getMessage(), [
                    'phone' => $phoneNumber,
                ]);
                return $this->fallbackToWaSender($phoneNumber, $fallbackMessage, $messageType, [
                    'success' => false,
                    'error' => $e->getMessage(),
                ]);
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function fallbackToWaSender(string $phoneNumber, string $message, string $messageType, array $metaResponse): array
    {
        $this->logOperation('fallback_to_wasender', [
            'phone' => $phoneNumber,
            'message_type' => $messageType,
            'meta_error' => $metaResponse['error'] ?? 'Unknown error',
        ]);

        try {
            $wasenderResponse = $this->waSenderService->sendMessage($phoneNumber, $message);

            return [
                'success' => true,
                'via' => 'wasender',
                'fallback' => true,
                'meta_error' => $metaResponse['error'] ?? null,
                'wasender_response' => $wasenderResponse,
            ];
        } catch (Exception $e) {
            $this->logOperation('fallback_failed', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Both Meta and WaSender failed',
                'meta_error' => $metaResponse['error'] ?? null,
                'wasender_error' => $e->getMessage(),
            ];
        }
    }

    protected function makeApiRequest(string $endpoint, array $payload): array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/{$endpoint}";
        Log::info("Whatsapp url", ["url" => $url]);


        if ($this->logRequests) {
            Log::channel('single')->info('Meta WhatsApp API Request', [
                'url' => $url,
                'payload' => $payload,
            ]);
        }

        $response = Http::withToken($this->accessToken)
            ->timeout(config('meta_whatsapp.settings.timeout', 30))
            ->post($url, $payload);


        $result = $response->json() ?? [];

        if ($this->logRequests) {
            Log::channel('single')->info('Meta WhatsApp API Response', [
                'status' => $response->status(),
                'response' => $result,
            ]);
        }
        Log::info('Meta WhatsApp API Response', [
            'status' => $response->status(),
            'response' => $result,
            'success' => $response->successful(),
            'error' => isset($result['error']) ? $result['error'] : 'No error key in response',
        ]);

        if (!$response->successful() || isset($result['error'])) {
            $errorCode = $result['error']['code'] ?? $response->status();
            $errorMessage = $result['error']['message'] ?? 'Unknown error';

            $fallbackCodes = config('meta_whatsapp.fallback_error_codes', []);
            $shouldFallback = in_array($errorCode, $fallbackCodes);

            return [
                'success' => false,
                'error' => $errorMessage,
                'error_code' => $errorCode,
                'should_fallback' => $shouldFallback,
                'raw_response' => $result,
            ];
        }

        return [
            'success' => true,
            'data' => $result,
            'via' => 'meta',
        ];
    }

    /**
     * Format phone number to international format
     */
    protected function formatPhoneNumber(string $phoneNumber): string
    {
        $cleaned = preg_replace('/[^0-9+]/', '', $phoneNumber);

        if (str_starts_with($cleaned, '+')) {
            return $cleaned;
        }

        // Local Tanzanian format (0XXXXXXXXX, 10 digits) must be checked before the
        // generic "already has a country code" length check below — a local number
        // is also >= 10 digits, so that check alone would wrongly prepend '+' to the
        // leading 0 (e.g. "0714825469" -> "+0714825469" instead of "+255714825469").
        if (str_starts_with($cleaned, '0') && strlen($cleaned) === 10) {
            return '+255' . substr($cleaned, 1);
        }

        if (strlen($cleaned) >= 10) {
            return '+' . $cleaned;
        }

        return '+255' . $cleaned;
    }

    protected function logOperation(string $operation, array $data): void
    {
        if (!$this->logRequests) {
            return;
        }

        Log::channel('single')->info("Meta WhatsApp: {$operation}", $data);
    }

    public function isConfigured(): bool
    {
        return !empty($this->accessToken) &&
            !empty($this->phoneNumberId) &&
            !empty($this->baseUrl);
    }
}
