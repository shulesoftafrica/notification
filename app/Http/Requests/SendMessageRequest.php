<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendMessageRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(['sms', 'email', 'whatsapp'])],
            'to' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:4096'],
            'subject' => ['required_if:type,email', 'string', 'max:255'],
            'priority' => ['sometimes', 'string', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'scheduled_at' => ['sometimes', 'date', 'after:now'],
            'template_id' => ['sometimes', 'string', 'max:100'],
            'template_data' => ['sometimes', 'array'],
            'metadata' => ['sometimes', 'array', 'max:10'],
            'metadata.*' => ['string', 'max:500'],
            'webhook_url' => ['sometimes', 'url', 'max:2048'],
            'tags' => ['sometimes', 'array', 'max:10'],
            'tags.*' => ['string', 'max:50'],
            'provider' => ['sometimes', 'string', Rule::in(['twilio', 'whatsapp', 'sendgrid', 'mailgun'])],
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Message type is required',
            'type.in' => 'Message type must be one of: sms, email, whatsapp',
            'to.required' => 'Recipient is required',
            'to.max' => 'Recipient must not exceed 255 characters',
            'message.required' => 'Message content is required',
            'message.max' => 'Message content must not exceed 4096 characters',
            'subject.required_if' => 'Subject is required for email messages',
            'subject.max' => 'Subject must not exceed 255 characters',
            'priority.in' => 'Priority must be one of: low, normal, high, urgent',
            'scheduled_at.date' => 'Scheduled time must be a valid date',
            'scheduled_at.after' => 'Scheduled time must be in the future',
            'template_id.max' => 'Template ID must not exceed 100 characters',
            'template_data.array' => 'Template data must be an array',
            'metadata.array' => 'Metadata must be an array',
            'metadata.max' => 'Maximum 10 metadata items allowed',
            'metadata.*.string' => 'Metadata values must be strings',
            'metadata.*.max' => 'Metadata values must not exceed 500 characters',
            'webhook_url.url' => 'Webhook URL must be a valid URL',
            'webhook_url.max' => 'Webhook URL must not exceed 2048 characters',
            'tags.array' => 'Tags must be an array',
            'tags.max' => 'Maximum 10 tags allowed',
            'tags.*.string' => 'Tag values must be strings',
            'tags.*.max' => 'Tag values must not exceed 50 characters',
            'provider.in' => 'Provider must be one of: twilio, whatsapp, sendgrid, mailgun',
        ];
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        // Normalize phone numbers for SMS and WhatsApp
        if (in_array($this->type, ['sms', 'whatsapp']) && $this->to) {
            $this->merge([
                'to' => $this->normalizePhoneNumber($this->to)
            ]);
        }

        // Normalize email addresses
        if ($this->type === 'email' && $this->to) {
            $this->merge([
                'to' => strtolower(trim($this->to))
            ]);
        }

        // Set default priority
        if (!$this->has('priority')) {
            $this->merge(['priority' => 'normal']);
        }

        // Clean up tags
        if ($this->has('tags') && is_array($this->tags)) {
            $this->merge([
                'tags' => array_values(array_unique(array_filter($this->tags)))
            ]);
        }
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Additional validation for phone numbers
            if (in_array($this->type, ['sms', 'whatsapp'])) {
                if (!$this->isValidPhoneNumber($this->to)) {
                    $validator->errors()->add('to', 'Invalid phone number format. Use international format (e.g., +1234567890)');
                }
            }

            // Additional validation for email addresses
            if ($this->type === 'email') {
                if (!filter_var($this->to, FILTER_VALIDATE_EMAIL)) {
                    $validator->errors()->add('to', 'Invalid email address format');
                }
            }

            // Validate template data if template_id is provided
            if ($this->template_id && !$this->template_data) {
                $validator->errors()->add('template_data', 'Template data is required when using a template');
            }

            // Validate message length for SMS
            if ($this->type === 'sms' && strlen($this->message) > 1600) {
                $validator->errors()->add('message', 'SMS message too long. Maximum 1600 characters allowed');
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
