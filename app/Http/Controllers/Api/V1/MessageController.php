<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Services\NotificationService;
use App\Services\RateLimitService;

class MessageController extends Controller
{
    protected $notificationService;
    protected $rateLimitService;

    public function __construct(NotificationService $notificationService, RateLimitService $rateLimitService)
    {
        $this->notificationService = $notificationService;
        $this->rateLimitService = $rateLimitService;
    }

    /**
     * Send a single notification
     */
    public function send(Request $request)
    {
        // Rate limiting check
        $rateLimitCheck = $this->rateLimitService->checkApiLimit('send_message', $request->ip());
        if ($rateLimitCheck['limited']) {
            return response()->json([
                'success' => false,
                'error' => 'Rate limit exceeded',
                'rate_limit' => $rateLimitCheck['usage'],
            ], 429);
        }

        $validator = Validator::make($request->all(), [
            'channel' => 'required|string|in:email,sms,whatsapp',
            'to' => 'required|string',
            'message' => 'required|string',
            'subject' => 'required_if:channel,email|string|max:255',
            'provider' => 'nullable|string',
            'template_id' => 'nullable|string',
            'template_data' => 'nullable|array',
            'priority' => 'nullable|string|in:low,normal,high,urgent',
            'scheduled_at' => 'nullable|date|after:now',
            'webhook_url' => 'nullable|url',
            'tags' => 'nullable|array',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            // Record API request
            $this->rateLimitService->recordApiRequest('send_message', $request->ip());

            $result = $this->notificationService->send([
                'channel' => $request->channel,
                'to' => $request->to,
                'message' => $request->message,
                'subject' => $request->subject,
                'provider' => $request->provider,
                'template_id' => $request->template_id,
                'template_data' => $request->template_data ?? [],
                'priority' => $request->priority ?? 'normal',
                'scheduled_at' => $request->scheduled_at,
                'webhook_url' => $request->webhook_url,
                'tags' => $request->tags ?? [],
                'metadata' => $request->metadata ?? [],
                'client_ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message_id' => $result['message_id'],
                'status' => $result['status'],
                'provider' => $result['provider'],
                'estimated_delivery' => $result['estimated_delivery'] ?? null,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Message send failed', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to send message',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get message status
     */
    public function status($messageId)
    {
        try {
            $message = DB::table('notification_logs')
                ->where('id', $messageId)
                ->first();

            if (!$message) {
                return response()->json([
                    'success' => false,
                    'error' => 'Message not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message_id' => $message->id,
                'status' => $message->status,
                'channel' => $message->channel,
                'provider' => $message->provider,
                'to' => $message->to,
                'sent_at' => $message->created_at,
                'delivered_at' => $message->delivered_at,
                'error' => $message->error,
                'metadata' => json_decode($message->metadata, true),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve message status',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List messages with pagination
     */
    public function list(Request $request)
    {
        $request->validate([
            'channel' => 'nullable|string|in:email,sms,whatsapp',
            'status' => 'nullable|string|in:queued,sent,delivered,failed',
            'provider' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        try {
            $query = DB::table('notification_logs');

            // Apply filters
            if ($request->channel) {
                $query->where('channel', $request->channel);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            if ($request->provider) {
                $query->where('provider', $request->provider);
            }

            if ($request->start_date) {
                $query->where('created_at', '>=', $request->start_date);
            }

            if ($request->end_date) {
                $query->where('created_at', '<=', $request->end_date);
            }

            $perPage = $request->get('per_page', 20);
            $page = $request->get('page', 1);
            $offset = ($page - 1) * $perPage;

            $total = $query->count();
            $messages = $query->orderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($perPage)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $messages,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage),
                    'has_more' => $offset + $perPage < $total,
                ],
                'filters' => $request->only(['channel', 'status', 'provider', 'start_date', 'end_date']),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve messages',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a scheduled message
     */
    public function cancel($messageId)
    {
        try {
            $message = DB::table('notification_logs')
                ->where('id', $messageId)
                ->where('status', 'queued')
                ->first();

            if (!$message) {
                return response()->json([
                    'success' => false,
                    'error' => 'Message not found or cannot be cancelled',
                ], 404);
            }

            $updated = DB::table('notification_logs')
                ->where('id', $messageId)
                ->update([
                    'status' => 'cancelled',
                    'updated_at' => now(),
                ]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Message cancelled successfully',
                    'message_id' => $messageId,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to cancel message',
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to cancel message',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retry a failed message
     */
    public function retry($messageId)
    {
        try {
            $message = DB::table('notification_logs')
                ->where('id', $messageId)
                ->where('status', 'failed')
                ->first();

            if (!$message) {
                return response()->json([
                    'success' => false,
                    'error' => 'Message not found or cannot be retried',
                ], 404);
            }

            // Reset message status and retry
            DB::table('notification_logs')
                ->where('id', $messageId)
                ->update([
                    'status' => 'queued',
                    'retry_count' => DB::raw('retry_count + 1'),
                    'updated_at' => now(),
                ]);

            // Re-queue the message for processing
            $result = $this->notificationService->send([
                'channel' => $message->channel,
                'to' => $message->to,
                'message' => $message->message,
                'subject' => $message->subject,
                'provider' => $message->provider,
                'metadata' => json_decode($message->metadata, true),
                'retry' => true,
                'original_id' => $messageId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Message queued for retry',
                'message_id' => $messageId,
                'retry_status' => $result['status'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retry message',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get message delivery report
     */
    public function report($messageId)
    {
        try {
            $message = DB::table('notification_logs')
                ->where('id', $messageId)
                ->first();

            if (!$message) {
                return response()->json([
                    'success' => false,
                    'error' => 'Message not found',
                ], 404);
            }

            $events = DB::table('notification_events')
                ->where('message_id', $messageId)
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message_id' => $messageId,
                'message' => $message,
                'events' => $events,
                'timeline' => $this->buildTimeline($message, $events),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve message report',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Build message timeline
     */
    protected function buildTimeline($message, $events)
    {
        $timeline = [
            [
                'event' => 'created',
                'timestamp' => $message->created_at,
                'description' => 'Message created and queued',
            ],
        ];

        foreach ($events as $event) {
            $timeline[] = [
                'event' => $event->event,
                'timestamp' => $event->created_at,
                'description' => $event->description,
                'data' => json_decode($event->data, true),
            ];
        }

        return collect($timeline)->sortBy('timestamp')->values()->all();
    }
}
