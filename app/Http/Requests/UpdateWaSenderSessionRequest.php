<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWaSenderSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'account_protection' => ['nullable', 'boolean'],
            'log_messages' => ['nullable', 'boolean'],
            'read_incoming_messages' => ['nullable', 'boolean'],
            'webhook_url' => ['nullable', 'url', 'max:500'],
            'webhook_enabled' => ['nullable', 'boolean'],
            'webhook_events' => ['nullable', 'array'],
            'webhook_events.*' => ['string', 'in:messages.received,session.status,messages.update'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'webhook_url.url' => 'The webhook URL must be a valid URL.',
            'webhook_events.*.in' => 'Invalid webhook event type. Allowed values are: messages.received, session.status, messages.update.',
        ];
    }
}
