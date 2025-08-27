<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by AuthenticateProject middleware
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'to' => 'required|array',
            'to.email' => 'required_without:to.phone|email',
            'to.phone' => 'required_without:to.email|string|regex:/^\+[1-9]\d{1,14}$/',
            'to.name' => 'sometimes|string|max:255',
            
            'channel' => 'required|in:email,sms,whatsapp',
            'template_id' => 'required|string|max:100',
            
            'variables' => 'sometimes|array',
            
            'options' => 'sometimes|array',
            'options.fallback_channels' => 'sometimes|array',
            'options.fallback_channels.*' => 'in:email,sms,whatsapp',
            'options.priority' => 'sometimes|in:low,normal,high,urgent',
            'options.scheduled_at' => 'sometimes|date|after:now',
            
            'metadata' => 'sometimes|array',
            'metadata.external_id' => 'sometimes|string|max:255',
            'metadata.campaign_id' => 'sometimes|string|max:255',
            'metadata.source' => 'sometimes|string|max:255',
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'to.required' => 'Recipient information is required',
            'to.email.required_without' => 'Either email or phone is required',
            'to.phone.required_without' => 'Either email or phone is required',
            'to.phone.regex' => 'Phone number must be in E.164 format (+1234567890)',
            'channel.required' => 'Channel is required',
            'channel.in' => 'Channel must be one of: email, sms, whatsapp',
            'template_id.required' => 'Template ID is required',
            'options.scheduled_at.after' => 'Scheduled time must be in the future',
            'options.priority.in' => 'Priority must be one of: low, normal, high, urgent',
        ];
    }

    /**
     * Custom validation logic
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate channel matches recipient
            $channel = $this->input('channel');
            $to = $this->input('to', []);

            if ($channel === 'email' && empty($to['email'])) {
                $validator->errors()->add('to.email', 'Email is required for email channel');
            }

            if (in_array($channel, ['sms', 'whatsapp']) && empty($to['phone'])) {
                $validator->errors()->add('to.phone', 'Phone number is required for SMS/WhatsApp channels');
            }

            // Validate fallback channels don't include primary channel
            $fallbackChannels = $this->input('options.fallback_channels', []);
            if (in_array($channel, $fallbackChannels)) {
                $validator->errors()->add('options.fallback_channels', 'Fallback channels cannot include the primary channel');
            }
        });
    }
}
