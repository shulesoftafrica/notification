<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class WaSenderService
{
    private string $baseUrl;
    private string $accessToken;

    public function __construct()
    {
        $this->baseUrl = config('services.wasender.base_url', 'https://www.wasenderapi.com/api');
        $this->accessToken = config('services.wasender.access_token');
    }

    /**
     * Create a new WhatsApp session
     *
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function createSession(array $data): array
    {
        $url = $this->baseUrl . '/whatsapp-sessions';
        
        $payload = [
            'name' => $data['name'],
            'phone_number' => $data['phone_number'],
            'account_protection' => $data['account_protection'] ?? true,
            'log_messages' => $data['log_messages'] ?? true,
            'read_incoming_messages' => $data['read_incoming_messages'] ?? false,
            'webhook_url' => $data['webhook_url'] ?? null,
            'webhook_enabled' => $data['webhook_enabled'] ?? false,
            'webhook_events' => $data['webhook_events'] ?? [],
        ];

        return $this->makeRequest('POST', $url, $payload);
    }

    /**
     * Get a WhatsApp session by ID
     *
     * @param string $sessionId
     * @return array
     * @throws Exception
     */
    public function getSession(string $sessionId): array
    {
        $url = $this->baseUrl . '/whatsapp-sessions/' . $sessionId;
        return $this->makeRequest('GET', $url);
    }

    /**
     * Update a WhatsApp session
     *
     * @param string $sessionId
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function updateSession(string $sessionId, array $data): array
    {
        $url = $this->baseUrl . '/whatsapp-sessions/' . $sessionId;
        return $this->makeRequest('PUT', $url, $data);
    }

    /**
     * Delete a WhatsApp session
     *
     * @param string $sessionId
     * @return array
     * @throws Exception
     */
    public function deleteSession(string $sessionId): array
    {
        $url = $this->baseUrl . '/whatsapp-sessions/' . $sessionId;
        return $this->makeRequest('DELETE', $url);
    }

    /**
     * List all WhatsApp sessions
     *
     * @return array
     * @throws Exception
     */
    public function listSessions(): array
    {
        $url = $this->baseUrl . '/whatsapp-sessions';
        return $this->makeRequest('GET', $url);
    }

    /**
     * Make a cURL request to WaSender API
     *
     * @param string $method
     * @param string $url
     * @param array|null $data
     * @return array
     * @throws Exception
     */
    private function makeRequest(string $method, string $url, ?array $data = null): array
    {
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        // Set method and data
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'GET':
            default:
                // GET is default
                break;
        }

        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Handle cURL errors
        if ($error) {
            Log::error('WaSender API cURL Error', [
                'error' => $error,
                'url' => $url,
                'method' => $method,
            ]);
            throw new Exception('cURL Error: ' . $error);
        }

        // Decode response
        $result = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('WaSender API Invalid JSON Response', [
                'response' => $response,
                'url' => $url,
                'method' => $method,
            ]);
            throw new Exception('Invalid JSON response from WaSender API');
        }

        // Handle HTTP errors
        if ($httpCode >= 400) {
            Log::error('WaSender API HTTP Error', [
                'http_code' => $httpCode,
                'response' => $result,
                'url' => $url,
                'method' => $method,
            ]);
            throw new Exception(
                $result['message'] ?? 'WaSender API Error: HTTP ' . $httpCode,
                $httpCode
            );
        }

        return $result;
    }

    /**
     * Send a WhatsApp message via session
     *
     * @param string $sessionId
     * @param array $messageData
     * @return array
     * @throws Exception
     */
    public function sendMessage(string $sessionId, array $messageData): array
    {
        $url = $this->baseUrl . '/whatsapp-sessions/' . $sessionId . '/messages';
        return $this->makeRequest('POST', $url, $messageData);
    }

    /**
     * Get session QR code (for initial connection)
     *
     * @param string $sessionId
     * @return array
     * @throws Exception
     */
    public function getQRCode(string $sessionId): array
    {
        $url = $this->baseUrl . '/whatsapp-sessions/' . $sessionId . '/qr';
        return $this->makeRequest('GET', $url);
    }

    /**
     * Disconnect a WhatsApp session
     *
     * @param string $sessionId
     * @return array
     * @throws Exception
     */
    public function disconnectSession(string $sessionId): array
    {
        $url = $this->baseUrl . '/whatsapp-sessions/' . $sessionId . '/disconnect';
        return $this->makeRequest('POST', $url);
    }
}
