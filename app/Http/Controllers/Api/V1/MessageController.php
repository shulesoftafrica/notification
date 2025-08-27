<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\MessageResource;
use App\Jobs\DispatchMessage;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    /**
     * Send a new message
     */
    public function store(SendMessageRequest $request): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        // Generate unique message ID
        $messageId = 'msg_' . Str::ulid();

        // Check for idempotency
        $idempotencyKey = $request->header('X-Idempotency-Key');
        if ($idempotencyKey) {
            $existingMessage = Message::where('project_id', $project->project_id)
                                    ->where('idempotency_key', $idempotencyKey)
                                    ->first();
            
            if ($existingMessage) {
                return response()->json([
                    'data' => new MessageResource($existingMessage),
                    'meta' => [
                        'idempotent' => true,
                        'trace_id' => $requestId
                    ]
                ], 200);
            }
        }

        // Create message record
        $message = Message::create([
            'message_id' => $messageId,
            'project_id' => $project->project_id,
            'tenant_id' => $tenantId,
            'idempotency_key' => $idempotencyKey,
            'external_id' => $request->input('metadata.external_id'),
            'recipient' => $request->input('to'),
            'channel' => $request->input('channel'),
            'template_id' => $request->input('template_id'),
            'variables' => $request->input('variables'),
            'options' => $request->input('options'),
            'metadata' => $request->input('metadata'),
            'priority' => $request->input('options.priority', 'normal'),
            'scheduled_at' => $request->input('options.scheduled_at'),
            'status' => 'queued'
        ]);

        // Dispatch to queue for processing
        if ($message->scheduled_at && $message->scheduled_at > now()) {
            // Schedule for later
            DispatchMessage::dispatch($message)->delay($message->scheduled_at);
        } else {
            // Process immediately based on priority
            $delay = match($message->priority) {
                'high' => 0,
                'normal' => 5,
                'low' => 30,
                default => 5
            };
            
            DispatchMessage::dispatch($message)->delay(now()->addSeconds($delay));
        }

        return response()->json([
            'data' => new MessageResource($message),
            'meta' => [
                'project_id' => $project->project_id,
                'tenant_id' => $tenantId,
                'channel' => $message->channel,
                'priority' => $message->priority,
                'trace_id' => $requestId
            ]
        ], 202);
    }

    /**
     * Get message status
     */
    public function show(Request $request, string $messageId): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $requestId = $request->input('request_id');

        $message = Message::where('message_id', $messageId)
                         ->where('project_id', $project->project_id)
                         ->first();

        if (!$message) {
            return response()->json([
                'error' => [
                    'code' => 'MESSAGE_NOT_FOUND',
                    'message' => 'Message not found or access denied',
                    'trace_id' => $requestId
                ]
            ], 404);
        }

        return response()->json([
            'data' => new MessageResource($message),
            'meta' => [
                'trace_id' => $requestId
            ]
        ]);
    }

    /**
     * List messages for project/tenant
     */
    public function index(Request $request): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        $query = Message::where('project_id', $project->project_id)
                       ->where('tenant_id', $tenantId);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('channel')) {
            $query->where('channel', $request->input('channel'));
        }

        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->input('from_date'));
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->input('to_date'));
        }

        $messages = $query->orderBy('created_at', 'desc')
                         ->paginate($request->input('per_page', 25));

        return response()->json([
            'data' => MessageResource::collection($messages),
            'meta' => [
                'pagination' => [
                    'total' => $messages->total(),
                    'per_page' => $messages->perPage(),
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                ],
                'trace_id' => $requestId
            ]
        ]);
    }
}
