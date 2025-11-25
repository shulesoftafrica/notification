<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\BulkMessageRequest;
use App\Services\NotificationService;
use App\Jobs\DispatchMessage;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkMessageController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        $this->middleware('api.auth');
    }

    /**
     * Send bulk messages
     */
    public function send(BulkMessageRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $messages = $validated['messages'];
            $globalSettings = $validated['settings'] ?? [];

            Log::info('Processing bulk message request', [
                'message_count' => count($messages),
                'api_key' => $request->attributes->get('api_key')
            ]);

            $results = [];
            $messageIds = [];

            DB::beginTransaction();

            foreach ($messages as $index => $messageData) {
                // Merge global settings with individual message data
                $messageData = array_merge($globalSettings, $messageData);
                
                // Create message record
                $message = Message::create([
                    'type' => $messageData['type'],
                    'recipient' => $messageData['to'],
                    'subject' => $messageData['subject'] ?? null,
                    'message' => $messageData['message'],
                    'status' => 'pending',
                    'priority' => $messageData['priority'] ?? 'normal',
                    'scheduled_at' => $messageData['scheduled_at'] ?? null,
                    'metadata' => array_merge($messageData['metadata'] ?? [], [
                        'bulk_id' => $validated['bulk_id'] ?? null,
                        'bulk_index' => $index,
                        'bulk_total' => count($messages)
                    ]),
                    'tags' => array_merge($messageData['tags'] ?? [], ['bulk']),
                    'webhook_url' => $messageData['webhook_url'] ?? $globalSettings['webhook_url'] ?? null,
                    'api_key' => $request->attributes->get('api_key'),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                $messageIds[] = $message->id;

                // Dispatch job based on processing mode
                if (($validated['processing_mode'] ?? 'queue') === 'immediate') {
                    $result = $this->sendImmediately($messageData);
                    $message->update([
                        'status' => $result['success'] ? 'sent' : 'failed',
                        'provider' => $result['provider'] ?? null,
                        'external_id' => $result['message_id'] ?? null,
                        'sent_at' => $result['success'] ? now() : null,
                        'failed_at' => !$result['success'] ? now() : null,
                        'error_message' => $result['error'] ?? null,
                    ]);
                } else {
                    // Queue for background processing
                    $delay = $this->calculateDelay($index, $validated['rate_limit'] ?? null);
                    DispatchMessage::dispatch($messageData, $message->id, $messageData['priority'] ?? 'normal')
                        ->delay($delay);
                    $result = ['success' => true, 'queued' => true, 'message_id' => $message->id];
                }

                $results[] = [
                    'index' => $index,
                    'message_id' => $message->id,
                    'recipient' => $messageData['to'],
                    'type' => $messageData['type'],
                    'status' => $result['success'] ? 'queued' : 'failed',
                    'external_id' => $result['message_id'] ?? null,
                    'error' => $result['error'] ?? null
                ];
            }

            DB::commit();

            $successCount = collect($results)->where('status', '!=', 'failed')->count();
            $failedCount = count($results) - $successCount;

            Log::info('Bulk message processing completed', [
                'total' => count($results),
                'success' => $successCount,
                'failed' => $failedCount
            ]);

            return response()->json([
                'success' => true,
                'bulk_id' => $validated['bulk_id'] ?? null,
                'summary' => [
                    'total' => count($results),
                    'successful' => $successCount,
                    'failed' => $failedCount,
                    'processing_mode' => $validated['processing_mode'] ?? 'queue'
                ],
                'message_ids' => $messageIds,
                'results' => $results
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error('Bulk message processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Bulk message processing failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bulk message status
     */
    public function status(Request $request, $bulkId): JsonResponse
    {
        try {
            $messages = Message::where('metadata->bulk_id', $bulkId)
                ->where('api_key', $request->attributes->get('api_key'))
                ->get();

            if ($messages->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Bulk message not found'
                ], 404);
            }

            $summary = [
                'bulk_id' => $bulkId,
                'total' => $messages->count(),
                'pending' => $messages->where('status', 'pending')->count(),
                'queued' => $messages->where('status', 'queued')->count(),
                'sending' => $messages->where('status', 'sending')->count(),
                'sent' => $messages->where('status', 'sent')->count(),
                'delivered' => $messages->where('status', 'delivered')->count(),
                'failed' => $messages->where('status', 'failed')->count(),
                'cancelled' => $messages->where('status', 'cancelled')->count(),
                'success_rate' => $messages->count() > 0 ? 
                    round(($messages->whereIn('status', ['sent', 'delivered'])->count() / $messages->count()) * 100, 2) : 0,
                'created_at' => $messages->first()->created_at,
                'completed_at' => $messages->whereIn('status', ['sent', 'delivered', 'failed', 'cancelled'])->count() === $messages->count() ?
                    $messages->max('updated_at') : null
            ];

            $details = $messages->map(function ($message) {
                return [
                    'id' => $message->id,
                    'index' => $message->metadata['bulk_index'] ?? null,
                    'recipient' => $message->recipient,
                    'type' => $message->type,
                    'status' => $message->status,
                    'external_id' => $message->external_id,
                    'sent_at' => $message->sent_at,
                    'delivered_at' => $message->delivered_at,
                    'failed_at' => $message->failed_at,
                    'error_message' => $message->error_message
                ];
            });

            return response()->json([
                'success' => true,
                'summary' => $summary,
                'messages' => $details
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve bulk status',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel bulk message
     */
    public function cancel(Request $request, $bulkId): JsonResponse
    {
        try {
            $messages = Message::where('metadata->bulk_id', $bulkId)
                ->where('api_key', $request->attributes->get('api_key'))
                ->whereIn('status', ['pending', 'queued'])
                ->get();

            if ($messages->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'No pending messages found for this bulk ID'
                ], 404);
            }

            $cancelledCount = 0;
            foreach ($messages as $message) {
                $message->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'error_message' => 'Cancelled by user request'
                ]);
                $cancelledCount++;
            }

            Log::info('Bulk messages cancelled', [
                'bulk_id' => $bulkId,
                'cancelled_count' => $cancelledCount
            ]);

            return response()->json([
                'success' => true,
                'bulk_id' => $bulkId,
                'cancelled_count' => $cancelledCount,
                'message' => "Cancelled {$cancelledCount} pending messages"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to cancel bulk messages',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send message immediately (synchronous)
     */
    protected function sendImmediately(array $messageData): array
    {
        try {
            switch ($messageData['type']) {
                case 'sms':
                    return $this->notificationService->sendSms(
                        $messageData['to'],
                        $messageData['message'],
                        $messageData['metadata'] ?? [],
                        $messageData['provider'] ?? null
                    );

                case 'email':
                    return $this->notificationService->sendEmail(
                        $messageData['to'],
                        $messageData['subject'],
                        $messageData['message'],
                        $messageData['metadata'] ?? [],
                        $messageData['provider'] ?? null
                    );

                case 'whatsapp':
                    return $this->notificationService->sendWhatsApp(
                        $messageData['to'],
                        $messageData['message'],
                        $messageData['metadata'] ?? [],
                        $messageData['provider'] ?? null
                    );

                default:
                    throw new \InvalidArgumentException("Unsupported message type: {$messageData['type']}");
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Calculate delay for rate limiting
     */
    protected function calculateDelay(int $index, ?array $rateLimit): \DateTimeInterface
    {
        if (!$rateLimit) {
            return now();
        }

        $messagesPerMinute = $rateLimit['messages_per_minute'] ?? 60;
        $delaySeconds = ($index * 60) / $messagesPerMinute;
        
        return now()->addSeconds($delaySeconds);
    }
}
