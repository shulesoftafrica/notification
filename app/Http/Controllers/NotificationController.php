<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\MessageResource;
use App\Services\NotificationService;
use App\Models\Message;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        $this->middleware('api.auth');
    }

    /**
     * Send a notification
     */
    public function send(SendMessageRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            // Create message record
            $message = Message::create([
                'type' => $validated['type'],
                'recipient' => $validated['to'],
                'subject' => $validated['subject'] ?? null,
                'message' => $validated['message'],
                'status' => 'pending',
                'priority' => $validated['priority'] ?? 'normal',
                'scheduled_at' => $validated['scheduled_at'] ?? null,
                'metadata' => $validated['metadata'] ?? [],
                'tags' => $validated['tags'] ?? [],
                'webhook_url' => $validated['webhook_url'] ?? null,
                'api_key' => $validated['api_key'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Send the notification
            switch ($validated['type']) {
                case 'sms':
                    $result = $this->notificationService->sendSms(
                        $validated['to'],
                        $validated['message'],
                        $validated['metadata'] ?? [],
                        $validated['provider'] ?? null
                    );
                    break;

                case 'email':
                    $result = $this->notificationService->sendEmail(
                        $validated['to'],
                        $validated['subject'],
                        $validated['message'],
                        $validated['metadata'] ?? [],
                        $validated['provider'] ?? null
                    );
                    break;

                case 'whatsapp':
                    $result = $this->notificationService->sendWhatsApp(
                        $validated['to'],
                        $validated['message'],
                        $validated['metadata'] ?? [],
                        $validated['provider'] ?? null
                    );
                    break;

                default:
                    return response()->json([
                        'error' => 'Invalid notification type',
                        'message' => 'Supported types: sms, email, whatsapp'
                    ], 400);
            }

            // Update message with result
            $message->update([
                'status' => $result['success'] ? 'sent' : 'failed',
                'provider' => $result['provider'],
                'external_id' => $result['message_id'] ?? null,
                'sent_at' => $result['success'] ? now() : null,
                'failed_at' => !$result['success'] ? now() : null,
                'error_message' => $result['error'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message_id' => $message->id,
                'external_id' => $result['message_id'] ?? null,
                'status' => $message->status,
                'provider' => $result['provider'],
                'data' => new MessageResource($message)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to send notification',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification status
     */
    public function status($id): JsonResponse
    {
        try {
            $message = Message::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => new MessageResource($message)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Message not found',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * List notifications
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Message::query();

            // Filter by API key
            if ($apiKey = $request->attributes->get('api_key')) {
                $query->where('api_key', $apiKey);
            }

            // Filter by type
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by date range
            if ($request->has('from')) {
                $query->where('created_at', '>=', $request->from);
            }

            if ($request->has('to')) {
                $query->where('created_at', '<=', $request->to);
            }

            // Search by recipient
            if ($request->has('recipient')) {
                $query->where('recipient', 'LIKE', '%' . $request->recipient . '%');
            }

            // Order by creation date
            $query->orderBy('created_at', 'desc');

            // Paginate results
            $perPage = min($request->get('per_page', 20), 100);
            $messages = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => MessageResource::collection($messages->items()),
                'meta' => [
                    'current_page' => $messages->currentPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                    'last_page' => $messages->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve notifications',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
