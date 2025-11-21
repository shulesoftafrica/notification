<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class SendBulkMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', 'string', Rule::in(['sms', 'email', 'whatsapp'])],
            'messages' => ['required', 'array', 'min:1', 'max:1000'],
            'messages.*.to' => ['required', 'string', 'max:255'],
            'messages.*.message' => ['required', 'string', 'max:4096'],
            'messages.*.subject' => ['required_if:channel,email', 'string', 'max:255'],
            'messages.*.metadata' => ['sometimes', 'array', 'max:10'],
            'messages.*.metadata.*' => ['string', 'max:500'],
            'priority' => ['sometimes', 'string', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'scheduled_at' => ['sometimes', 'date', 'after:now'],
            'batch_size' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'rate_limit' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'webhook_url' => ['sometimes', 'url', 'max:2048'],
            'metadata' => ['sometimes', 'array', 'max:10'],
            'metadata.*' => ['string', 'max:500'],
            'provider' => ['sometimes', 'string', Rule::in(['twilio', 'whatsapp', 'sendgrid', 'mailgun', 'resend', 'beem', 'termii'])],
            'sender_name' => ['sometimes', 'string', 'max:50'],
            'type' => ['sometimes', 'string', Rule::in(['official', 'wasender'])], // WhatsApp provider type
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'channel.required' => 'Message channel is required',
            'channel.in' => 'Message channel must be one of: sms, email, whatsapp',
            'messages.required' => 'Messages array is required',
            'messages.array' => 'Messages must be an array',
            'messages.min' => 'At least one message is required',
            'messages.max' => 'Maximum 1000 messages allowed per bulk request',
            'messages.*.to.required' => 'Recipient is required for each message',
            'messages.*.to.max' => 'Recipient must not exceed 255 characters',
            'messages.*.message.required' => 'Message content is required',
            'messages.*.message.max' => 'Message content must not exceed 4096 characters',
            'messages.*.subject.required_if' => 'Subject is required for email messages',
            'messages.*.subject.max' => 'Subject must not exceed 255 characters',
            'messages.*.metadata.array' => 'Metadata must be an array',
            'messages.*.metadata.max' => 'Maximum 10 metadata items allowed per message',
            'messages.*.metadata.*.string' => 'Metadata values must be strings',
            'messages.*.metadata.*.max' => 'Metadata values must not exceed 500 characters',
            'priority.in' => 'Priority must be one of: low, normal, high, urgent',
            'scheduled_at.date' => 'Scheduled time must be a valid date',
            'scheduled_at.after' => 'Scheduled time must be in the future',
            'batch_size.integer' => 'Batch size must be an integer',
            'batch_size.min' => 'Batch size must be at least 1',
            'batch_size.max' => 'Batch size must not exceed 100',
            'rate_limit.integer' => 'Rate limit must be an integer',
            'rate_limit.min' => 'Rate limit must be at least 1 message per minute',
            'rate_limit.max' => 'Rate limit must not exceed 1000 messages per minute',
            'webhook_url.url' => 'Webhook URL must be a valid URL',
            'webhook_url.max' => 'Webhook URL must not exceed 2048 characters',
            'metadata.array' => 'Metadata must be an array',
            'metadata.max' => 'Maximum 10 metadata items allowed',
            'metadata.*.string' => 'Metadata values must be strings',
            'metadata.*.max' => 'Metadata values must not exceed 500 characters',
            'sender_name.string' => 'Sender name must be a string',
            'sender_name.max' => 'Sender name must not exceed 50 characters',
            'provider.in' => 'Provider must be one of: twilio, whatsapp, sendgrid, mailgun, resend, beem, termii',
        ];
    }

    /**
     * Handle a failed validation attempt - FORCE JSON RESPONSE
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'code' => 'VALIDATION_ERROR'
            ], 422)
        );
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        // Set default priority
        if (!$this->has('priority')) {
            $this->merge(['priority' => 'normal']);
        }

        // Set default batch size
        if (!$this->has('batch_size')) {
            $this->merge(['batch_size' => 10]);
        }

        // Normalize messages
        if ($this->has('messages') && is_array($this->messages)) {
            $normalizedMessages = [];
            
            foreach ($this->messages as $message) {
                $normalized = $message;
                
                // Normalize phone numbers for SMS and WhatsApp
                if (in_array($this->channel, ['sms', 'whatsapp']) && isset($message['to'])) {
                    $normalized['to'] = $this->normalizePhoneNumber($message['to']);
                }

                // Normalize email addresses
                if ($this->channel === 'email' && isset($message['to'])) {
                    $normalized['to'] = strtolower(trim($message['to']));
                }

                $normalizedMessages[] = $normalized;
            }
            
            $this->merge(['messages' => $normalizedMessages]);
        }
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate each message
            foreach ($this->messages ?? [] as $index => $message) {
                $to = $message['to'] ?? '';
                
                // Additional validation for phone numbers
                if (in_array($this->channel, ['sms', 'whatsapp'])) {
                    if (!$this->isValidPhoneNumber($to)) {
                        $validator->errors()->add(
                            "messages.{$index}.to",
                            'Invalid phone number format. Use international format (e.g., +1234567890)'
                        );
                    }
                }

                // Additional validation for email addresses
                if ($this->channel === 'email') {
                    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                        $validator->errors()->add(
                            "messages.{$index}.to",
                            'Invalid email address format'
                        );
                    }
                }

                // Validate message length for SMS
                if ($this->channel === 'sms' && isset($message['message']) && strlen($message['message']) > 1600) {
                    $validator->errors()->add(
                        "messages.{$index}.message",
                        'SMS message too long. Maximum 1600 characters allowed'
                    );
                }
            }

            // Check for duplicate recipients (warning, not error)
            $recipients = array_column($this->messages ?? [], 'to');
            $duplicates = array_diff_assoc($recipients, array_unique($recipients));
            if (!empty($duplicates)) {
                // Log warning but don't fail validation
                Log::warning('Duplicate recipients in bulk message request', [
                    'duplicates' => array_values(array_unique($duplicates)),
                    'api_key' => $this->attributes->get('api_key')
                ]);
            }
        });
    }

    /**
     * Normalize phone number to international format
     */
    protected function normalizePhoneNumber(string $phoneNumber): string
    {
        // Remove all non-digit characters except +
        $cleaned = preg_replace('/[^\d+]/', '', $phoneNumber);
        
        // If it doesn't start with +, assume it's a US number
        if (!str_starts_with($cleaned, '+')) {
            $cleaned = '+1' . $cleaned;
        }
        
        return $cleaned;
    }

    /**
     * Validate phone number format
     */
    protected function isValidPhoneNumber(string $phoneNumber): bool
    {
        // Basic international phone number validation
        return preg_match('/^\+[1-9]\d{10,14}$/', $phoneNumber);
    }

    /**
     * Get validated data with additional processing
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        
        if ($key !== null) {
            return $validated;
        }

        // Add computed fields
        $validated['api_key'] = $this->attributes->get('api_key');
        $validated['ip_address'] = $this->ip();
        $validated['user_agent'] = $this->userAgent();
        $validated['timestamp'] = now()->toISOString();
        
        return $validated;
    }
}
