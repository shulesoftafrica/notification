<?php

namespace App\Http\Controllers;

use App\Models\SmsSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SmsSessionController extends Controller
{
    public function __construct()
    {
        $this->middleware('api.auth');
    }

    /**
     * List SMS sessions.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'schema_name' => 'nullable|string|max:255',
            'provider' => 'nullable|string|in:beem,termii,twilio',
            'status' => 'nullable|string|in:active,inactive',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|string|in:id,schema_name,sender_name,provider,status,created_at,updated_at',
            'sort_direction' => 'nullable|string|in:asc,desc',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $query = SmsSession::query();

            if ($request->filled('schema_name')) {
                $query->where('schema_name', $request->string('schema_name')->toString());
            }

            if ($request->filled('provider')) {
                $query->where('provider', $request->string('provider')->toString());
            }

            if ($request->filled('status')) {
                $query->where('status', $request->string('status')->toString());
            }

            if ($request->filled('search')) {
                $search = $request->string('search')->toString();
                $query->where(function ($builder) use ($search) {
                    $builder->where('schema_name', 'like', "%{$search}%")
                        ->orWhere('sender_name', 'like', "%{$search}%")
                        ->orWhere('provider', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            }

            $sortBy = $request->input('sort_by', 'created_at');
            $sortDirection = $request->input('sort_direction', 'desc');
            $perPage = (int) $request->input('per_page', 20);

            $sessions = $query
                ->orderBy($sortBy, $sortDirection)
                ->paginate($perPage)
                ->appends($request->query());

            return response()->json([
                'success' => true,
                'data' => $sessions->items(),
                'meta' => [
                    'current_page' => $sessions->currentPage(),
                    'per_page' => $sessions->perPage(),
                    'total' => $sessions->total(),
                    'last_page' => $sessions->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve SMS sessions', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve SMS sessions',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new SMS session.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'schema_name' => 'required|string|max:255',
            'sender_name' => 'nullable|string|max:255',
            'provider' => 'nullable|string|in:beem,termii,twilio',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $mappingValidation = $this->validateSchemaSenderMapping(
            $request->input('schema_name'),
            $request->input('sender_name')
        );

        if ($mappingValidation) {
            return $mappingValidation;
        }

        try {
            $session = SmsSession::create([
                'schema_name' => $request->input('schema_name'),
                'sender_name' => $request->input('sender_name'),
                'provider' => $request->input('provider', 'beem'),
                'status' => 1, // Default to active
            ]);

            Log::info('SMS session created', [
                'sms_session_id' => $session->id,
                'schema_name' => $session->schema_name,
                'provider' => $session->provider,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'SMS session created successfully',
                'data' => $session,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create SMS session', [
                'error' => $e->getMessage(),
                'payload' => $request->only(['schema_name', 'sender_name', 'provider', 'status']),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create SMS session',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a single SMS session.
     */
    public function show($id): JsonResponse
    {
        if (!is_numeric($id)) {
            return response()->json([
                'success' => false,
                'error' => 'SMS session not found',
            ], 404);
        }

        $id = (int) $id;

        try {
            $session = SmsSession::find($id);

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'error' => 'SMS session not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $session,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch SMS session', [
                'sms_session_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch SMS session',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an SMS session.
     */
    public function update(Request $request, $id): JsonResponse
    {
        if (!is_numeric($id)) {
            return response()->json([
                'success' => false,
                'error' => 'SMS session not found',
            ], 404);
        }

        $id = (int) $id;

        $validator = Validator::make($request->all(), [
            'schema_name' => 'sometimes|string|max:255',
            'sender_name' => 'nullable|string|max:255',
            'provider' => 'sometimes|string|in:beem,termii,twilio',
            'status' => 'sometimes|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $session = SmsSession::find($id);

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'error' => 'SMS session not found',
                ], 404);
            }

            $schemaName = $request->input('schema_name', $session->schema_name);
            $senderName = $request->has('sender_name')
                ? $request->input('sender_name')
                : $session->sender_name;

            $mappingValidation = $this->validateSchemaSenderMapping(
                $schemaName,
                $senderName,
                $session->id
            );

            if ($mappingValidation) {
                return $mappingValidation;
            }

            $session->update($request->only([
                'schema_name',
                'sender_name',
                'provider',
                'status',
            ]));

            Log::info('SMS session updated', [
                'sms_session_id' => $session->id,
                'schema_name' => $session->schema_name,
                'provider' => $session->provider,
                'status' => $session->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'SMS session updated successfully',
                'data' => $session->fresh(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update SMS session', [
                'sms_session_id' => $id,
                'error' => $e->getMessage(),
                'payload' => $request->only(['schema_name', 'sender_name', 'provider', 'status']),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to update SMS session',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an SMS session.
     */
    public function destroy($id): JsonResponse
    {
        if (!is_numeric($id)) {
            return response()->json([
                'success' => false,
                'error' => 'SMS session not found',
            ], 404);
        }

        $id = (int) $id;

        try {
            $session = SmsSession::find($id);

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'error' => 'SMS session not found',
                ], 404);
            }

            $sessionDetails = [
                'sms_session_id' => $session->id,
                'schema_name' => $session->schema_name,
                'provider' => $session->provider,
            ];

            $session->delete();

            Log::info('SMS session deleted', $sessionDetails);

            return response()->json([
                'success' => true,
                'message' => 'SMS session deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete SMS session', [
                'sms_session_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to delete SMS session',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function validateSchemaSenderMapping(string $schemaName, ?string $senderName, ?int $ignoreSessionId = null): ?JsonResponse
    {
        $query = SmsSession::query()->where('schema_name', $schemaName);

        if ($ignoreSessionId !== null) {
            $query->where('id', '!=', $ignoreSessionId);
        }

        $existingSession = $query->select(['id', 'sender_name'])->first();

        if (!$existingSession) {
            return null;
        }

        if ($existingSession->sender_name !== $senderName) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => [
                    'sender_name' => [
                        'Each schema_name must be associated with exactly one sender_name.',
                    ],
                ],
            ], 422);
        }

        return null;
    }
}