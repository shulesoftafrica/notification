<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchMessage;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BulkMessageController extends Controller
{
    /**
     * Send bulk messages
     */
    public function store(Request $request): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        $validator = Validator::make($request->all(), [
            'messages' => 'required|array|min:1|max:1000', // Max 1000 messages per batch
            'messages.*.to' => 'required|array',
            'messages.*.channel' => 'required|in:email,sms,whatsapp',
            'messages.*.template_id' => 'sometimes|string',
            'messages.*.variables' => 'sometimes|array',
            'messages.*.options' => 'sometimes|array',
            'messages.*.metadata' => 'sometimes|array',
            'batch_options' => 'sometimes|array',
            'batch_options.priority' => 'sometimes|in:low,normal,high',
            'batch_options.scheduled_at' => 'sometimes|date|after:now',
            'batch_options.delay_between_messages' => 'sometimes|integer|min:0|max:3600' // Max 1 hour delay
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors(),
                    'trace_id' => $requestId
                ]
            ], 422);
        }

        $batchId = 'batch_' . Str::ulid();
        $messages = $request->input('messages');
        $batchOptions = $request->input('batch_options', []);
        
        $createdMessages = [];
        $errors = [];
        
        DB::beginTransaction();
        
        try {
            foreach ($messages as $index => $messageData) {
                try {
                    // Generate unique message ID
                    $messageId = 'msg_' . Str::ulid();
                    
                    // Create idempotency key for bulk message
                    $idempotencyKey = $request->header('X-Idempotency-Key') . '_' . $index;
                    
                    // Check for existing message with same idempotency key
                    $existingMessage = Message::where('project_id', $project->project_id)
                                            ->where('idempotency_key', $idempotencyKey)
                                            ->first();
                    
                    if ($existingMessage) {
                        $createdMessages[] = $existingMessage;
                        continue;
                    }
                    
                    // Merge batch options with individual message options
                    $messageOptions = array_merge($batchOptions, $messageData['options'] ?? []);
                    
                    $message = Message::create([
                        'message_id' => $messageId,
                        'project_id' => $project->project_id,
                        'tenant_id' => $tenantId,
                        'idempotency_key' => $idempotencyKey,
                        'external_id' => $messageData['metadata']['external_id'] ?? null,
                        'recipient' => $messageData['to'],
                        'channel' => $messageData['channel'],
                        'template_id' => $messageData['template_id'] ?? null,
                        'variables' => $messageData['variables'] ?? null,
                        'options' => $messageOptions,
                        'metadata' => array_merge($messageData['metadata'] ?? [], [
                            'batch_id' => $batchId,
                            'batch_index' => $index
                        ]),
                        'priority' => $messageOptions['priority'] ?? 'normal',
                        'scheduled_at' => $messageOptions['scheduled_at'] ?? null,
                        'status' => 'queued'
                    ]);
                    
                    $createdMessages[] = $message;
                    
                } catch (\Exception $e) {
                    $errors["messages.{$index}"] = "Failed to create message: " . $e->getMessage();
                }
            }
            
            // If there are errors, rollback and return error response
            if (!empty($errors)) {
                DB::rollback();
                return response()->json([
                    'error' => [
                        'code' => 'BULK_MESSAGE_ERROR',
                        'message' => 'Some messages failed to create.',
                        'details' => $errors,
                        'trace_id' => $requestId
                    ]
                ], 422);
            }
            
            DB::commit();
            
            // Dispatch jobs for processing
            $delayBetweenMessages = $batchOptions['delay_between_messages'] ?? 0;
            $baseDelay = 0;
            
            foreach ($createdMessages as $index => $message) {
                $messageDelay = $baseDelay + ($index * $delayBetweenMessages);
                
                if ($message->scheduled_at && $message->scheduled_at > now()) {
                    // Schedule for later
                    DispatchMessage::dispatch($message)->delay($message->scheduled_at);
                } else {
                    // Process with staggered delay
                    DispatchMessage::dispatch($message)->delay(now()->addSeconds($messageDelay));
                }
            }
            
            return response()->json([
                'data' => [
                    'batch_id' => $batchId,
                    'messages_created' => count($createdMessages),
                    'message_ids' => array_column($createdMessages, 'message_id'),
                    'estimated_completion' => now()->addSeconds(count($createdMessages) * $delayBetweenMessages)->toISOString()
                ],
                'meta' => [
                    'project_id' => $project->project_id,
                    'tenant_id' => $tenantId,
                    'batch_options' => $batchOptions,
                    'trace_id' => $requestId
                ]
            ], 202);
            
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => [
                    'code' => 'BULK_MESSAGE_FAILED',
                    'message' => 'Failed to create bulk messages: ' . $e->getMessage(),
                    'trace_id' => $requestId
                ]
            ], 500);
        }
    }

    /**
     * Get bulk message batch status
     */
    public function getBatchStatus(Request $request, string $batchId): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        $messages = Message::where('project_id', $project->project_id)
                          ->where('tenant_id', $tenantId)
                          ->whereJsonContains('metadata->batch_id', $batchId)
                          ->get();

        if ($messages->isEmpty()) {
            return response()->json([
                'error' => [
                    'code' => 'BATCH_NOT_FOUND',
                    'message' => 'Batch not found or access denied',
                    'trace_id' => $requestId
                ]
            ], 404);
        }

        $statusCounts = $messages->groupBy('status')->map->count();
        $totalMessages = $messages->count();
        
        $completedStatuses = ['sent', 'delivered', 'read', 'failed'];
        $completedCount = $messages->whereIn('status', $completedStatuses)->count();
        
        $batchStatus = $completedCount === $totalMessages ? 'completed' : 'processing';
        if ($statusCounts->get('failed', 0) > 0 && $completedCount === $totalMessages) {
            $batchStatus = 'completed_with_failures';
        }

        return response()->json([
            'data' => [
                'batch_id' => $batchId,
                'status' => $batchStatus,
                'total_messages' => $totalMessages,
                'status_breakdown' => $statusCounts,
                'progress_percentage' => round(($completedCount / $totalMessages) * 100, 2),
                'created_at' => $messages->min('created_at'),
                'estimated_completion' => $batchStatus === 'completed' ? null : 
                    now()->addMinutes(($totalMessages - $completedCount) * 0.1)->toISOString()
            ],
            'meta' => [
                'trace_id' => $requestId
            ]
        ]);
    }

    /**
     * Get messages in a batch
     */
    public function getBatchMessages(Request $request, string $batchId): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        $query = Message::where('project_id', $project->project_id)
                       ->where('tenant_id', $tenantId)
                       ->whereJsonContains('metadata->batch_id', $batchId);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('channel')) {
            $query->where('channel', $request->input('channel'));
        }

        $messages = $query->orderBy('metadata->batch_index')
                         ->paginate($request->input('per_page', 100));

        return response()->json([
            'data' => $messages->items(),
            'meta' => [
                'batch_id' => $batchId,
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

    /**
     * Cancel a batch (cancel pending messages)
     */
    public function cancelBatch(Request $request, string $batchId): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        $pendingMessages = Message::where('project_id', $project->project_id)
                                 ->where('tenant_id', $tenantId)
                                 ->whereJsonContains('metadata->batch_id', $batchId)
                                 ->whereIn('status', ['queued', 'processing'])
                                 ->get();

        if ($pendingMessages->isEmpty()) {
            return response()->json([
                'error' => [
                    'code' => 'NO_PENDING_MESSAGES',
                    'message' => 'No pending messages found in this batch',
                    'trace_id' => $requestId
                ]
            ], 404);
        }

        $cancelledCount = 0;
        foreach ($pendingMessages as $message) {
            $message->update([
                'status' => 'cancelled',
                'failure_reason' => 'Batch cancelled by user',
                'failed_at' => now()
            ]);
            $cancelledCount++;
        }

        return response()->json([
            'data' => [
                'batch_id' => $batchId,
                'cancelled_messages' => $cancelledCount,
                'cancelled_at' => now()->toISOString()
            ],
            'meta' => [
                'trace_id' => $requestId
            ]
        ]);
    }
}
