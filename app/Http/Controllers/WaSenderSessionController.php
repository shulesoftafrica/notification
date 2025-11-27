<?php

namespace App\Http\Controllers;

use App\Models\WaSenderSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WaSenderSessionController extends Controller
{
    /**
     * Create a new WhatsApp session by sending request to WaSender API
     * and save the response to database
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createSession(Request $request): JsonResponse
    {
        // Validate incoming request
        $validator = Validator::make($request->all(), [
            'schema_name' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'account_protection' => 'nullable|boolean',
            'log_messages' => 'nullable|boolean',
            'read_incoming_messages' => 'nullable|boolean',
            'webhook_url' => 'nullable|url|max:500',
            'webhook_enabled' => 'nullable|boolean',
            'webhook_events' => 'nullable|array',
            'webhook_events.*' => 'string|in:messages.received,session.status,messages.update',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Prepare request body for WaSender API
            $requestBody = [
                'name' => $request->input('name'),
                'phone_number' => $request->input('phone_number'),
                'account_protection' => $request->input('account_protection', true),
                'log_messages' => $request->input('log_messages', true),
                'read_incoming_messages' => $request->input('read_incoming_messages', false),
                'webhook_url' => $request->input('webhook_url'),
                'webhook_enabled' => $request->input('webhook_enabled', false),
                'webhook_events' => $request->input('webhook_events', []),
            ];

            // Make cURL request to WaSender API
            $apiUrl = 'https://www.wasenderapi.com/api/whatsapp-sessions';
            $accessToken = config('services.wasender.access_token');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'Accept: application/json',
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // Handle cURL errors
            if ($curlError) {
                Log::error('WaSender API cURL Error', [
                    'error' => $curlError,
                    'url' => $apiUrl,
                ]);
                throw new Exception('Failed to connect to WaSender API: ' . $curlError);
            }

            // Decode response
            $apiResponse = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('WaSender API Invalid JSON', [
                    'response' => $response,
                    'json_error' => json_last_error_msg(),
                ]);
                throw new Exception('Invalid response from WaSender API');
            }

            // Check for API errors
            if ($httpCode >= 400) {
                Log::error('WaSender API Error', [
                    'http_code' => $httpCode,
                    'response' => $apiResponse,
                ]);
                throw new Exception(
                    $apiResponse['message'] ?? 'WaSender API returned error: ' . $httpCode,
                    $httpCode
                );
            }

            // Save response to database
            if (isset($apiResponse['success']) && $apiResponse['success'] && isset($apiResponse['data'])) {
                $sessionData = $apiResponse['data'];

                $savedSession = WaSenderSession::create([
                    'schema_name' => $request->input('schema_name'),
                    'wasender_session_id' => $sessionData['id'] ?? null,
                    'name' => $sessionData['name'],
                    'phone_number' => $sessionData['phone_number'],
                    'status' => $sessionData['status'] ?? 'disconnected',
                    'account_protection' => $sessionData['account_protection'] ?? true,
                    'log_messages' => $sessionData['log_messages'] ?? true,
                    'read_incoming_messages' => $sessionData['read_incoming_messages'] ?? false,
                    'webhook_url' => $sessionData['webhook_url'] ?? null,
                    'webhook_enabled' => $sessionData['webhook_enabled'] ?? false,
                    'webhook_events' => $sessionData['webhook_events'] ?? [],
                    'api_key' => $sessionData['api_key'] ?? null,
                    'webhook_secret' => $sessionData['webhook_secret'] ?? null,
                ]);

                Log::info('WaSender session created and saved', [
                    'local_id' => $savedSession->id,
                    'wasender_id' => $savedSession->wasender_session_id,
                    'schema_name' => $savedSession->schema_name,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'WhatsApp session created successfully',
                    'data' => $savedSession,
                    'api_response' => $apiResponse,
                ], 201);
            }

            // If response format is unexpected
            throw new Exception('Unexpected response format from WaSender API');

        } catch (Exception $e) {
            Log::error('Error creating WaSender session', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create WhatsApp session',
                'error' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Get all sessions from database
     *
     * @return JsonResponse
     */
    public function getSessions(): JsonResponse
    {
        try {
            $sessions = WaSenderSession::orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $sessions,
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching sessions', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sessions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a single session from database
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getSession(int $id): JsonResponse
    {
        try {
            $session = WaSenderSession::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $session,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Connect a WhatsApp session and get QR code
     *
     * @param int $id
     * @return JsonResponse
     */
    public function connectSession(int $id): JsonResponse
    {
        try {
            // Get session from database
            $session = WaSenderSession::findOrFail($id);

            if (!$session->wasender_session_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not created on WaSender API yet',
                ], 400);
            }

            // Make cURL request to WaSender API
            $apiUrl = 'https://www.wasenderapi.com/api/whatsapp-sessions/' . $session->wasender_session_id . '/connect';
            $accessToken = config('services.wasender.access_token');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'Accept: application/json',
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // Handle cURL errors
            if ($curlError) {
                Log::error('WaSender API cURL Error on connect', [
                    'error' => $curlError,
                    'url' => $apiUrl,
                    'session_id' => $id,
                ]);
                throw new Exception('Failed to connect to WaSender API: ' . $curlError);
            }

            // Decode response
            $apiResponse = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('WaSender API Invalid JSON on connect', [
                    'response' => $response,
                    'json_error' => json_last_error_msg(),
                    'session_id' => $id,
                ]);
                throw new Exception('Invalid response from WaSender API');
            }

            // Check for API errors
            if ($httpCode >= 400) {
                Log::error('WaSender API Error on connect', [
                    'http_code' => $httpCode,
                    'response' => $apiResponse,
                    'session_id' => $id,
                ]);
                throw new Exception(
                    $apiResponse['message'] ?? 'WaSender API returned error: ' . $httpCode,
                    $httpCode
                );
            }

            // Update session status if provided in response
            if (isset($apiResponse['success']) && $apiResponse['success'] && isset($apiResponse['data']['status'])) {
                $session->update([
                    'status' => $apiResponse['data']['status'],
                ]);

                Log::info('WaSender session connect initiated', [
                    'local_id' => $session->id,
                    'wasender_id' => $session->wasender_session_id,
                    'status' => $apiResponse['data']['status'],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Session connect request successful',
                'data' => [
                    'session' => $session->fresh(),
                    'qr_code' => $apiResponse['data']['qrCode'] ?? null,
                    'status' => $apiResponse['data']['status'] ?? null,
                ],
                'api_response' => $apiResponse,
            ]);

        } catch (Exception $e) {
            Log::error('Error connecting WaSender session', [
                'session_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to connect WhatsApp session',
                'error' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Check WhatsApp session status
     *
     * @param int $id
     * @return JsonResponse
     */
    public function checkStatus(int $id): JsonResponse
    {
        try {
            // Get session from database
            $session = WaSenderSession::findOrFail($id);

            if (!$session->api_key) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session does not have an API key yet',
                ], 400);
            }

            // Make cURL request to WaSender API
            $apiUrl = 'https://www.wasenderapi.com/api/status';
            $apiKey = $session->api_key;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $apiKey,
                'Accept: application/json',
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // Handle cURL errors
            if ($curlError) {
                Log::error('WaSender API cURL Error on status check', [
                    'error' => $curlError,
                    'url' => $apiUrl,
                    'session_id' => $id,
                ]);
                throw new Exception('Failed to connect to WaSender API: ' . $curlError);
            }

            // Decode response
            $apiResponse = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('WaSender API Invalid JSON on status check', [
                    'response' => $response,
                    'json_error' => json_last_error_msg(),
                    'session_id' => $id,
                ]);
                throw new Exception('Invalid response from WaSender API');
            }

            // Check for API errors
            if ($httpCode >= 400) {
                Log::error('WaSender API Error on status check', [
                    'http_code' => $httpCode,
                    'response' => $apiResponse,
                    'session_id' => $id,
                ]);
                throw new Exception(
                    $apiResponse['message'] ?? 'WaSender API returned error: ' . $httpCode,
                    $httpCode
                );
            }

            // Update session status in database if status is returned
            if (isset($apiResponse['status'])) {
                $session->update([
                    'status' => $apiResponse['status'],
                ]);

                Log::info('WaSender session status checked and updated', [
                    'local_id' => $session->id,
                    'wasender_id' => $session->wasender_session_id,
                    'status' => $apiResponse['status'],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Session status retrieved successfully',
                'data' => [
                    'session' => $session->fresh(),
                    'status' => $apiResponse['status'] ?? null,
                ],
                'api_response' => $apiResponse,
            ]);

        } catch (Exception $e) {
            Log::error('Error checking WaSender session status', [
                'session_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check session status',
                'error' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Update WhatsApp session
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateSession(Request $request, int $id): JsonResponse
    {
        // Validate incoming request
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'account_protection' => 'nullable|boolean',
            'log_messages' => 'nullable|boolean',
            'read_incoming_messages' => 'nullable|boolean',
            'webhook_url' => 'nullable|url|max:500',
            'webhook_enabled' => 'nullable|boolean',
            'webhook_events' => 'nullable|array',
            'webhook_events.*' => 'string|in:messages.received,session.status,messages.update',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Get session from database
            $session = WaSenderSession::findOrFail($id);

            if (!$session->wasender_session_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not created on WaSender API yet',
                ], 400);
            }

            // Prepare request body for WaSender API (only include fields that are provided)
            $requestBody = [];
            if ($request->has('name')) {
                $requestBody['name'] = $request->input('name');
            }
            if ($request->has('phone_number')) {
                $requestBody['phone_number'] = $request->input('phone_number');
            }
            if ($request->has('account_protection')) {
                $requestBody['account_protection'] = $request->input('account_protection');
            }
            if ($request->has('log_messages')) {
                $requestBody['log_messages'] = $request->input('log_messages');
            }
            if ($request->has('read_incoming_messages')) {
                $requestBody['read_incoming_messages'] = $request->input('read_incoming_messages');
            }
            if ($request->has('webhook_url')) {
                $requestBody['webhook_url'] = $request->input('webhook_url');
            }
            if ($request->has('webhook_enabled')) {
                $requestBody['webhook_enabled'] = $request->input('webhook_enabled');
            }
            if ($request->has('webhook_events')) {
                $requestBody['webhook_events'] = $request->input('webhook_events');
            }

            // Make cURL request to WaSender API
            $apiUrl = 'https://www.wasenderapi.com/api/whatsapp-sessions/' . $session->wasender_session_id;
            $accessToken = config('services.wasender.access_token');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'Accept: application/json',
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // Handle cURL errors
            if ($curlError) {
                Log::error('WaSender API cURL Error on update', [
                    'error' => $curlError,
                    'url' => $apiUrl,
                    'session_id' => $id,
                ]);
                throw new Exception('Failed to connect to WaSender API: ' . $curlError);
            }

            // Decode response
            $apiResponse = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('WaSender API Invalid JSON on update', [
                    'response' => $response,
                    'json_error' => json_last_error_msg(),
                    'session_id' => $id,
                ]);
                throw new Exception('Invalid response from WaSender API');
            }

            // Check for API errors
            if ($httpCode >= 400) {
                Log::error('WaSender API Error on update', [
                    'http_code' => $httpCode,
                    'response' => $apiResponse,
                    'session_id' => $id,
                ]);
                throw new Exception(
                    $apiResponse['message'] ?? 'WaSender API returned error: ' . $httpCode,
                    $httpCode
                );
            }

            // Update session in database with response data
            if (isset($apiResponse['success']) && $apiResponse['success'] && isset($apiResponse['data'])) {
                $sessionData = $apiResponse['data'];

                $session->update([
                    'name' => $sessionData['name'] ?? $session->name,
                    'phone_number' => $sessionData['phone_number'] ?? $session->phone_number,
                    'status' => $sessionData['status'] ?? $session->status,
                    'account_protection' => $sessionData['account_protection'] ?? $session->account_protection,
                    'log_messages' => $sessionData['log_messages'] ?? $session->log_messages,
                    'read_incoming_messages' => $sessionData['read_incoming_messages'] ?? $session->read_incoming_messages,
                    'webhook_url' => $sessionData['webhook_url'] ?? $session->webhook_url,
                    'webhook_enabled' => $sessionData['webhook_enabled'] ?? $session->webhook_enabled,
                    'webhook_events' => $sessionData['webhook_events'] ?? $session->webhook_events,
                    'api_key' => $sessionData['api_key'] ?? $session->api_key,
                    'webhook_secret' => $sessionData['webhook_secret'] ?? $session->webhook_secret,
                ]);

                Log::info('WaSender session updated', [
                    'local_id' => $session->id,
                    'wasender_id' => $session->wasender_session_id,
                    'schema_name' => $session->schema_name,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'WhatsApp session updated successfully',
                    'data' => $session->fresh(),
                    'api_response' => $apiResponse,
                ]);
            }

            // If response format is unexpected
            throw new Exception('Unexpected response format from WaSender API');

        } catch (Exception $e) {
            Log::error('Error updating WaSender session', [
                'session_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update WhatsApp session',
                'error' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Get QR code for WhatsApp session
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getQRCode(int $id): JsonResponse
    {
        try {
            // Get session from database
            $session = WaSenderSession::findOrFail($id);

            if (!$session->wasender_session_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not created on WaSender API yet',
                ], 400);
            }

            // Make cURL request to WaSender API
            $apiUrl = 'https://www.wasenderapi.com/api/whatsapp-sessions/' . $session->wasender_session_id . '/qrcode';
            $accessToken = config('services.wasender.access_token');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // Handle cURL errors
            if ($curlError) {
                Log::error('WaSender API cURL Error on QR code fetch', [
                    'error' => $curlError,
                    'url' => $apiUrl,
                    'session_id' => $id,
                ]);
                throw new Exception('Failed to connect to WaSender API: ' . $curlError);
            }

            // Decode response
            $apiResponse = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('WaSender API Invalid JSON on QR code fetch', [
                    'response' => $response,
                    'json_error' => json_last_error_msg(),
                    'session_id' => $id,
                ]);
                throw new Exception('Invalid response from WaSender API');
            }

            // Check for API errors
            if ($httpCode >= 400) {
                Log::error('WaSender API Error on QR code fetch', [
                    'http_code' => $httpCode,
                    'response' => $apiResponse,
                    'session_id' => $id,
                ]);
                throw new Exception(
                    $apiResponse['message'] ?? 'WaSender API returned error: ' . $httpCode,
                    $httpCode
                );
            }

            Log::info('WaSender QR code fetched', [
                'local_id' => $session->id,
                'wasender_id' => $session->wasender_session_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'QR code retrieved successfully',
                'data' => [
                    'session' => $session,
                    'qr_code' => $apiResponse['data']['qrCode'] ?? null,
                ],
                'api_response' => $apiResponse,
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching WaSender QR code', [
                'session_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch QR code',
                'error' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Delete WhatsApp session
     *
     * @param int $id
     * @return JsonResponse
     */
    public function deleteSession(int $id): JsonResponse
    {
        try {
            // Get session from database
            $session = WaSenderSession::findOrFail($id);

            if (!$session->wasender_session_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not created on WaSender API yet',
                ], 400);
            }

            // Make cURL request to WaSender API
            $apiUrl = 'https://www.wasenderapi.com/api/whatsapp-sessions/' . $session->wasender_session_id;
            $accessToken = config('services.wasender.access_token');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'Accept: application/json',
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // Handle cURL errors
            if ($curlError) {
                Log::error('WaSender API cURL Error on delete', [
                    'error' => $curlError,
                    'url' => $apiUrl,
                    'session_id' => $id,
                ]);
                throw new Exception('Failed to connect to WaSender API: ' . $curlError);
            }

            // Decode response (may be empty for successful deletion)
            $apiResponse = null;
            if (!empty($response)) {
                $apiResponse = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('WaSender API response not JSON on delete', [
                        'response' => $response,
                        'session_id' => $id,
                    ]);
                }
            }

            // Check for API errors
            if ($httpCode >= 400) {
                Log::error('WaSender API Error on delete', [
                    'http_code' => $httpCode,
                    'response' => $apiResponse,
                    'session_id' => $id,
                ]);
                throw new Exception(
                    $apiResponse['message'] ?? 'WaSender API returned error: ' . $httpCode,
                    $httpCode
                );
            }

            // Delete session from local database
            $wasenderSessionId = $session->wasender_session_id;
            $schemaName = $session->schema_name;
            $session->delete();

            Log::info('WaSender session deleted', [
                'local_id' => $id,
                'wasender_id' => $wasenderSessionId,
                'schema_name' => $schemaName,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'WhatsApp session deleted successfully',
                'data' => [
                    'deleted_local_id' => $id,
                    'deleted_wasender_id' => $wasenderSessionId,
                ],
                'api_response' => $apiResponse,
            ]);

        } catch (Exception $e) {
            Log::error('Error deleting WaSender session', [
                'session_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete WhatsApp session',
                'error' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }
}
