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
}
