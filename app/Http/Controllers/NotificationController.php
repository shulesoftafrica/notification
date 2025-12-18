<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\SendMessageRequest;
use App\Http\Requests\SendBulkMessageRequest;
use App\Http\Resources\MessageResource;
use App\Services\NotificationService;
use App\Models\Message;
use App\Models\WaSenderSession;
use App\Models\SmsSession;
use App\Jobs\DispatchMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

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

            // Handle base64 encoded attachment
            $attachmentPath = null;
            $attachmentMetadata = null;


            if (!empty($validated['attachment'])) {
                try {
                    // Decode base64 data
                    $data = preg_replace('/^data:\w+\/\w+;base64,/', '', $validated['attachment']);

                    $fileContent = base64_decode($data, true);

                    if ($fileContent === false) {
                        throw new \Exception('Invalid base64 encoded attachment data');
                    }

                    // Generate unique filename
                    $extension = $this->getExtensionFromMimeType($validated['attachment_type']);
                    $filename = uniqid('attachment_', true) . '.' . $extension;

                    // Store file in storage/app/attachments
                    $attachmentPath = 'attachments/' . $filename;
                    Storage::disk('public')->put($attachmentPath, $fileContent);

                    // Save attachment metadata
                    $attachmentMetadata = [
                        'original_name' => $validated['attachment_name'],
                        'mime_type' => $validated['attachment_type'],
                        'size' => strlen($fileContent),
                        'extension' => $extension,
                    ];

                    Log::info('Attachment uploaded from base64', [
                        'path' => $attachmentPath,
                        'metadata' => $attachmentMetadata
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to process base64 attachment', [
                        'error' => $e->getMessage(),
                        'channel' => $validated['channel']
                    ]);

                    return response()->json([
                        'success' => false,
                        'error' => 'Failed to process attachment',
                        'message' => $e->getMessage()
                    ], 500);
                }
            }

            // Handle WaSender API key validation for WhatsApp/WaSender combination
            $wasenderApiKey = null;
            if ($this->isWasenderWhatsApp($validated)) {
                $wasenderApiKey = $this->getWasenderApiKey($validated['schema_name']);
                if (!$wasenderApiKey) {
                    return response()->json([
                        'success' => false,
                        'error' => 'WhatsApp session not found or API key unavailable',
                        'message' => 'No active WhatsApp session found for client: ' . $validated['schema_name'] . ' Please reconnect again or contact shulesoft support'
                    ], 400);
                }
            }

            // Handle SMS sender_name validation for SMS channel
            $smsSenderName = null;
            if ($validated['channel'] === 'sms') {
                $smsSenderName = $this->getSmsSenderName($validated['schema_name']);
                if ($smsSenderName === false) {
                    return response()->json([
                        'success' => false,
                        'error' => 'SMS session not found',
                        'message' => 'No SMS session found for client: ' . $validated['schema_name'] . '. Please contact shulesoft support'
                    ], 400);
                }
            }
            // check sms balance before dispatching the queue if channel is sms (quick sms);
            $dispatchMessage = true;
            if ($validated['channel'] === 'sms') {
                $smsBalance = $this->processBalance($validated['schema_name']);
                $initialBalance = $smsBalance['balance'];
                if ($initialBalance <= 0) { // 20
                    $dispatchMessage = false;
                }

                $smscount = $this->countMessage($validated['message']);
                $initialBalance -= $smscount;
                if ($initialBalance > 0) {
                    $dispatchMessage = true;
                } else {
                    $dispatchMessage = false;
                }
            }
            // Create message record
            $message = Message::create([
                'channel' => $validated['channel'],
                'recipient' => $validated['to'],
                'subject' => $validated['subject'] ?? null,
                'message' => $validated['message'],
                'status' => $dispatchMessage ? 'pending' : 'no_credit',
                'priority' => $validated['priority'] ?? 'normal',
                'scheduled_at' => $validated['scheduled_at'] ?? null,
                'metadata' => array_merge($validated['metadata'] ?? [], [
                    'schema_name' => $validated['schema_name'],
                    'wasender_api_key' => $wasenderApiKey, // Pass WaSender API key in metadata
                    'sms_sender_name' => $smsSenderName, // Pass SMS sender name in metadata
                ]),
                'tags' => $validated['tags'] ?? [],
                'webhook_url' => $validated['webhook_url'] ?? null,
                'schema_name' => $validated['schema_name'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'attachment' => $attachmentPath,
                'attachment_metadata' => $attachmentMetadata,
            ]);

            // Send the notification using unified service method
            if ($dispatchMessage) {
                $result = $this->notificationService->send([
                    'channel' => $validated['channel'],
                    'to' => $validated['to'],
                    'subject' => $validated['subject'] ?? null,
                    'message' => $validated['message'],
                    'template_id' => $validated['template_id'] ?? null,
                    'priority' => $validated['priority'] ?? 'normal',
                    'metadata' => array_merge($validated['metadata'] ?? [], [
                        'schema_name' => $validated['schema_name'],
                        'wasender_api_key' => $wasenderApiKey, // Pass WaSender API key in metadata
                        'sms_sender_name' => $smsSenderName, // Pass SMS sender name in metadata
                    ]),
                    'provider' => $validated['provider'] ?? null,
                    'sender_name' => $validated['sender_name'] ?? null,
                    'type' => $validated['type'] ?? null, // WhatsApp provider type
                    'webhook_url' => $validated['webhook_url'] ?? null,
                    'attachment' => $attachmentPath,
                    'attachment_metadata' => $attachmentMetadata,
                ]);

                // Update message with result
                $message->update([
                    'status' => $result['status'] ?? 'failed',
                    'provider' => $result['provider'] ?? null,
                    'external_id' => $result['message_id'] ?? null,
                    'sent_at' => ($result['status'] ?? 'failed') === 'sent' ? now() : null,
                    'failed_at' => ($result['status'] ?? 'failed') === 'failed' ? now() : null,
                    'error_message' => $result['error'] ?? null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message_id' => $message->id,
                'external_id' => isset($result['message_id']) ? $result['message_id'] : null,
                'status' => $message->status,
                'provider' => isset($result['provider']) ? $result['provider'] : null,
                'data' => new MessageResource($message)
            ], 201);
        } catch (\Exception $e) {
            // Update message status to failed if message exists
            if (isset($message)) {
                $message->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'error_message' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Failed to send notification',
                    'message' => $e->getMessage(),
                    'message_id' => $message->id,
                    'data' => new MessageResource($message)
                ], 500);
            }

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

            // Filter by schema name
            if ($schemaName = $request->input('schema_name')) {
                $query->where('schema_name', $schemaName);
            }

            // Filter by type/channel
            if ($request->has('channel')) {
                $query->where('channel', $request->channel);
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
            // $perPage = min($request->get('per_page', 20), 100);
            $messages = $query->get();

            return response()->json([
                'success' => true,
                'data' => MessageResource::collection($messages),
                // 'meta' => [
                //     'current_page' => $messages->currentPage(),
                //     'per_page' => $messages->perPage(),
                //     'total' => $messages->total(),
                //     'last_page' => $messages->lastPage(),
                // ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve notifications',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resend notifications by message IDs (bulk resend)
     */
    public function resend(Request $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'message_ids' => 'required|array|min:1',
                'message_ids.*' => 'required|integer|exists:messages,id',
                'schema_name' => 'required|string'
            ]);

            $messageIds = $request->input('message_ids');
            $schemaName = $request->input('schema_name');

            // Get all messages by IDs
            $messages = Message::whereIn('id', $messageIds)->get();

            if ($messages->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => 'No messages found',
                    'message' => 'No messages found with provided IDs'
                ], 404);
            }

            // Get channel from the first message
            $channel = $messages->first()->channel;

            $resendResults = [];
            $totalResent = 0;
            $totalSkipped = 0;

            // Check SMS balance if channel is SMS
            $dispatchMessage = true;
            $initialBalance = 0;

            if ($channel === 'sms') {
                $smsBalance = $this->processBalance($schemaName);
                $initialBalance = $smsBalance['balance'];
                
                if ($initialBalance <= 0) {
                    Log::warning('Insufficient SMS balance for resend', [
                        'schema_name' => $schemaName,
                        'balance' => $initialBalance,
                        'messages_count' => $messages->count()
                    ]);
                    $dispatchMessage = false;
                }
            }
            $scheduledAt = isset($request->scheduled_at) ? Carbon::parse($request->scheduled_at) : now();
            $rateLimit = $request->rate_limit ?? null;

            // Process each message
            foreach ($messages as $index => $message) {
                $shouldDispatch = $dispatchMessage;

                // For SMS, check balance for each message
                if ($channel === 'sms' && $dispatchMessage) {
                    $smscount = $this->countMessage($message->message);
                    $initialBalance -= $smscount;
                    
                    if ($initialBalance <= 0) {
                        $shouldDispatch = false;
                    }
                }

                // Reset message status
                $message->update([
                    'status' => $shouldDispatch ? 'pending' : 'no_credit',
                    'sent_at' => null,
                    'failed_at' => null,
                    'error_message' => null,
                ]);

                if ($shouldDispatch) {
                    $delay = 0;
                    if ($rateLimit && $index > 0) {
                        $delay = ($index / $rateLimit) * 60; // Convert to seconds
                    }
                    // Prepare job message data
                    $jobMessageData = [
                        'type' => $message->channel,
                        'channel' => $message->channel,
                        'to' => $message->recipient,
                        'subject' => $message->subject,
                        'message' => $message->message,
                        'metadata' => $message->metadata ?? [],
                        'provider' => $message->metadata['provider'] ?? null,
                        'sender_name' => $message->metadata['sender_name'] ?? null,
                        'whatsapp_type' => $message->metadata['type'] ?? null,
                        'webhook_url' => $message->webhook_url,
                        'attachment' => $message->attachment,
                        'attachment_metadata' => $message->attachment_metadata,
                    ];

                    // Dispatch the job to queue
                    DispatchMessage::dispatch(
                        $jobMessageData,
                        $message->id,
                        $message->priority ?? 'normal'
                    )->delay($scheduledAt->copy()->addSeconds($delay));

                    $totalResent++;
                } else {
                    $totalSkipped++;
                    
                    Log::info('Message resend skipped - no credit', [
                        'message_id' => $message->id,
                        'schema_name' => $schemaName,
                        'channel' => $channel
                    ]);
                }

                $resendResults[] = [
                    'message_id' => $message->id,
                    'status' => $shouldDispatch ? 'queued' : 'skipped_no_credit',
                    'channel' => $message->channel,
                    'recipient' => $message->recipient
                ];
            }

            DB::commit();

            Log::info('Bulk resend completed', [
                'total_messages' => count($messageIds),
                'resent' => $totalResent,
                'skipped' => $totalSkipped,
                'schema_name' => $schemaName,
                'channel' => $channel
            ]);

            return response()->json([
                'success' => true,
                'message' => $totalSkipped > 0 ? 'Some messages were skipped due to insufficient credit.' : 'All messages have been resent successfully.',
                'total_messages' => count($messageIds),
                'resent_count' => $totalResent,
                'skipped_count' => $totalSkipped,
                'results' => $resendResults
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to resend messages', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to resend messages',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete notifications by message IDs
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        Log::info('Bulk delete initiated', ['request' => $request->all()]);
        try {
            $request->validate([
                'message_ids' => 'required|array|min:1',
                'message_ids.*' => 'required|integer|exists:messages,id'
            ]);

            $messageIds = $request->input('message_ids');

            // Get messages before deletion for logging and attachment cleanup
            $messages = Message::whereIn('id', $messageIds)->get();

            // Delete attachments from storage if they exist
            foreach ($messages as $message) {
                if ($message->attachment) {
                    try {
                        Storage::disk('public_root')->delete($message->attachment);
                        Log::info('Attachment deleted', [
                            'message_id' => $message->id,
                            'attachment_path' => $message->attachment
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('Failed to delete attachment', [
                            'message_id' => $message->id,
                            'attachment_path' => $message->attachment,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            // Perform bulk delete
            $deletedCount = Message::whereIn('id', $messageIds)->delete();

            Log::info('Bulk delete completed', [
                'deleted_count' => $deletedCount,
                'message_ids' => $messageIds
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Messages deleted successfully',
                'deleted_count' => $deletedCount,
                'message_ids' => $messageIds
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to bulk delete messages', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to delete messages',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send bulk notifications (non-realtime)
     */
    public function sendBulk(SendBulkMessageRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();

            // Handle base64 encoded attachment (shared across all messages)
            $attachmentPath = null;
            $attachmentMetadata = null;

            if (!empty($validated['attachment'])) {
                try {
                    // Decode base64 data
                    $data = preg_replace('/^data:\w+\/\w+;base64,/', '', $validated['attachment']);

                    $fileContent = base64_decode($data, true);

                    if ($fileContent === false) {
                        throw new \Exception('Invalid base64 encoded attachment data');
                    }

                    // Generate unique filename
                    $extension = $this->getExtensionFromMimeType($validated['attachment_type']);
                    $filename = uniqid('attachment_', true) . '.' . $extension;
                    $attachmentPath = 'attachments/' . $filename;

                    Storage::disk('public_root')->put($attachmentPath, $fileContent);


                    // Save attachment metadata
                    $attachmentMetadata = [
                        'original_name' => $validated['attachment_name'],
                        'mime_type' => $validated['attachment_type'],
                        'size' => strlen($fileContent),
                        'extension' => $extension,
                    ];

                    Log::info('Attachment uploaded from base64 for bulk messages', [
                        'path' => $attachmentPath,
                        'metadata' => $attachmentMetadata
                    ]);
                } catch (\Exception $e) {
                    DB::rollBack();

                    Log::error('Failed to process base64 attachment for bulk messages', [
                        'error' => $e->getMessage(),
                        'channel' => $validated['channel']
                    ]);

                    return response()->json([
                        'success' => false,
                        'error' => 'Failed to process attachment',
                        'message' => $e->getMessage()
                    ], 500);
                }
            }

            // Handle WaSender API key validation for WhatsApp/WaSender combination
            $wasenderApiKey = null;
            if ($this->isWasenderWhatsApp($validated)) {
                $wasenderApiKey = $this->getWasenderApiKey($validated['schema_name']);
                if (!$wasenderApiKey) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'error' => 'WhatsApp session not found or API key unavailable',
                        'message' => 'No active WhatsApp session found for client: ' . $validated['schema_name'] . '. Please reconnect again or contact shulesoft support'
                    ], 400);
                }
            }

            // Handle SMS sender_name validation for SMS channel
            $smsSenderName = null;
            if ($validated['channel'] === 'sms') {
                $smsSenderName = $this->getSmsSenderName($validated['schema_name']);
                if ($smsSenderName === false) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'error' => 'SMS session not found',
                        'message' => 'No SMS session found for cleint: ' . $validated['schema_name'] . '. Please contact shulesoft support'
                    ], 400);
                }
            }

            $createdMessages = [];
            $scheduledAt = isset($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : now();
            $rateLimit = $validated['rate_limit'] ?? null;
            $priority = $validated['priority'] ?? 'normal';

            // check sms balance before dispatching the queue if channel is sms (quick sms);
            $dispatchMessage = true;
            if ($validated['channel'] === 'sms') {
                $smsBalance = $this->processBalance($validated['schema_name']);
                $initialBalance = $smsBalance['balance'];
                if ($initialBalance <= 0) { // 20
                    $dispatchMessage = false;
                }
            }

            // Create message records for each recipient
            $smscount = 0;
            foreach ($validated['messages'] as $index => $messageData) {
                if ($validated['channel'] === 'sms') {
                    $smscount = $this->countMessage($messageData['message']); // 3
                    $initialBalance -= $smscount;
                    if ($initialBalance > 0) {
                        $dispatchMessage = true;
                    } else {
                        $dispatchMessage = false;
                    }
                }
                // Create message record (similar to send() method)
                $message = Message::create([
                    'channel' => $validated['channel'],
                    'recipient' => $messageData['to'],
                    'subject' => $messageData['subject'] ?? null,
                    'message' => $messageData['message'],
                    'status' => $dispatchMessage ? 'pending' : 'no_credit',
                    'priority' => $priority,
                    'scheduled_at' => $scheduledAt,
                    'metadata' => array_merge(
                        $messageData['metadata'] ?? [],
                        $validated['metadata'] ?? [],
                        [
                            'schema_name' => $validated['schema_name'],
                            'wasender_api_key' => $wasenderApiKey, // Pass WaSender API key in metadata
                            'sms_sender_name' => $smsSenderName, // Pass SMS sender name in metadata
                        ]
                    ),
                    'tags' => $validated['tags'] ?? [],
                    'webhook_url' => $validated['webhook_url'] ?? null,
                    'schema_name' => $validated['schema_name'],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'attachment' => $attachmentPath,
                    'attachment_metadata' => $attachmentMetadata,
                ]);
                if ($dispatchMessage) {
                    // Calculate delay for rate limiting
                    $delay = 0;
                    if ($rateLimit && $index > 0) {
                        $delay = ($index / $rateLimit) * 60; // Convert to seconds
                    }

                    // Prepare message data for the job (similar to send() method)
                    $jobMessageData = [
                        'type' => $validated['channel'],
                        'channel' => $validated['channel'],
                        'to' => $messageData['to'],
                        'subject' => $messageData['subject'] ?? null,
                        'message' => $messageData['message'],
                        'metadata' => array_merge(
                            $messageData['metadata'] ?? [],
                            $validated['metadata'] ?? [],
                            [
                                'schema_name' => $validated['schema_name'],
                                'wasender_api_key' => $wasenderApiKey, // Pass WaSender API key in metadata
                                'sms_sender_name' => $smsSenderName, // Pass SMS sender name in metadata
                            ]
                        ),
                        'provider' => $validated['provider'] ?? null,
                        'sender_name' => $validated['sender_name'] ?? null,
                        'whatsapp_type' => $validated['type'] ?? null, // WhatsApp provider type
                        'webhook_url' => $validated['webhook_url'] ?? null,
                        'attachment' => $attachmentPath,
                        'attachment_metadata' => $attachmentMetadata,
                    ];

                    // Dispatch job to queue with delay (similar to how send() would queue it)

                    DispatchMessage::dispatch(
                        $jobMessageData,
                        $message->id,
                        $priority
                    )->delay($scheduledAt->copy()->addSeconds($delay));
                }

                $createdMessages[] = $message;
            }

            DB::commit();

            Log::info('Bulk messages created and queued', [
                'channel' => $validated['channel'],
                'total_count' => count($createdMessages),
                'priority' => $priority,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bulk messages queued successfully',
                'total_count' => count($createdMessages),
                'status' => 'pending',
                'scheduled_at' => $scheduledAt,
                'data' => [
                    'channel' => $validated['channel'],
                    'total_count' => count($createdMessages),
                    'priority' => $priority,
                    'scheduled_at' => $scheduledAt,
                    'message_ids' => array_map(fn($msg) => $msg->id, $createdMessages),
                ]
            ], 202);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create bulk messages', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to queue bulk messages',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file extension from MIME type
     */
    protected function getExtensionFromMimeType(string $mimeType): string
    {
        $mimeMap = [
            // Images
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',

            // Documents
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain' => 'txt',
            'text/csv' => 'csv',

            // Videos
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',

            // Audio
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
        ];

        return $mimeMap[$mimeType] ?? 'bin';
    }

    /**
     * Check if the request is for WhatsApp with WaSender provider
     */
    protected function isWasenderWhatsApp(array $validated): bool
    {
        return $validated['channel'] === 'whatsapp' &&
            (($validated['provider'] ?? null) === 'wasender' ||
                ($validated['type'] ?? null) === 'wasender');
    }

    /**
     * Get WaSender API key for the given schema
     */
    protected function getWasenderApiKey(string $schemaName): ?string
    {
        try {
            $session = WaSenderSession::where('schema_name', $schemaName)
                ->where('status', 'connected')
                ->whereNotNull('api_key')
                ->first();

            return $session?->api_key;
        } catch (\Exception $e) {
            Log::error('Failed to fetch WaSender API key', [
                'schema_name' => $schemaName,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get SMS sender name for the given schema
     * Returns string (sender name), null (use default "SHULESOFT"), or false (session not found)
     */
    protected function getSmsSenderName(string $schemaName): string|null|false
    {
        try {
            $session = SmsSession::where('schema_name', $schemaName)->first();

            if (!$session) {
                Log::error('SMS session not found', [
                    'schema_name' => $schemaName
                ]);
                return false; // No session found
            }

            // If sender_name is null, return null to use default "SHULESOFT"
            if ($session->sender_name === null) {
                return null;
            }

            // Return the configured sender name
            return $session->sender_name;
        } catch (\Exception $e) {
            Log::error('Failed to fetch SMS sender name', [
                'schema_name' => $schemaName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    public function getSmsBalance($schemaName): array
    {
        $balance = [
            'total_sms' => 0,
            'total_sms_sent' => 0
        ];
        $schema = DB::connection('shulesoft')->table('admin.sms_status as a')
            ->join('admin.clients as b', 'b.username', '=', 'a.schema_name')
            ->where('a.message_left', '>', 0)
            ->where('a.schema_name', $schemaName)
            ->whereIn('b.status', [1, 2])
            ->select('total_sms', 'total_sms_sent')
            ->first();
        if ($schema) {
            $balance['total_sms'] = $schema->total_sms;
            $balance['total_sms_sent'] = $schema->total_sms_sent;
        }
        return $balance;
    }
    public function getProcessBalance(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'schema_name' => 'required|string',
            ]);
            $result = $this->processBalance($request->schema_name);
            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to get SMS balance', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve SMS balance',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function processBalance($schemaName)
    {
        $result = [
            'total_sms' => 0,
            'total_sms_sent' => 0,
            'balance' => 0
        ];

        try {
            $sentMessages  = Message::where('schema_name', $schemaName)
                ->where('channel', 'sms')
                ->whereNotIn('status', ['pending', 'no_credit'])
                ->select('message')
                ->get();
            $totalSent = 0;
            if (!$sentMessages->isEmpty()) {
                foreach ($sentMessages as $message) {
                    $totalSent += $this->countMessage($message->message);
                }
            }

            $smsCount = $this->getSmsBalance($schemaName);
            $total_sms_sent = $totalSent + $smsCount['total_sms_sent'];
            $balance = $smsCount['total_sms'] - $total_sms_sent;
            $result = [
                'total_sms' => $smsCount['total_sms'],
                'total_sms_sent' => $total_sms_sent,
                'balance' => $balance
            ];
        } catch (\Exception $e) {
            Log::error('Failed to process SMS balance', [
                'schema_name' => $schemaName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $result;
    }

    function countMessage(?string $val): int
    {
        // Match PostgreSQL behavior: char_length()
        $length = mb_strlen($val ?? '', 'UTF-8');

        if ($length >= 0 && $length <= 160) {
            return 1;
        } elseif ($length > 160 && $length <= 306) {
            return 2;
        } elseif ($length > 306 && $length <= 459) {
            return 3;
        } elseif ($length > 459 && $length <= 612) {
            return 4;
        } elseif ($length >= 612 && $length <= 765) {
            return 5;
        } elseif ($length > 765 && $length <= 918) {
            return 6;
        } elseif ($length > 918 && $length <= 1071) {
            return 7;
        } elseif ($length > 1071 && $length <= 1224) {
            return 8;
        } elseif ($length > 1224 && $length <= 1377) {
            return 9;
        } elseif ($length > 1377 && $length <= 1530) {
            return 10;
        }

        return 0;
    }
}
