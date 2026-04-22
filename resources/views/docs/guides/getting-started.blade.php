@extends('docs.layout')

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-4xl font-bold text-gray-900 mb-4">
        <i class="fas fa-rocket text-blue-600 mr-3"></i>
        Getting Started
    </h1>
    <p class="text-lg text-gray-600 mb-8">
        Get up and running with the Notification Service API in minutes.
    </p>

    <!-- Step 1: Get API Key -->
    <div class="mb-12">
        <div class="flex items-center mb-4">
            <span class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center text-lg font-bold mr-4">1</span>
            <h2 class="text-2xl font-semibold text-gray-900">Get Your API Key</h2>
        </div>
        <div class="ml-14 bg-white p-6 rounded-lg border border-gray-200">
            <p class="text-gray-700 mb-4">
                Contact your account manager or administrator to receive your API key. The key must be at least 32 characters long.
            </p>
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-r">
                <div class="flex">
                    <i class="fas fa-exclamation-triangle text-yellow-600 mt-1 mr-3"></i>
                    <div>
                        <p class="font-semibold text-yellow-800">Keep your API key secure!</p>
                        <p class="text-sm text-yellow-700 mt-1">Never commit your API key to version control or expose it in client-side code.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 2: Authentication -->
    <div class="mb-12">
        <div class="flex items-center mb-4">
            <span class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center text-lg font-bold mr-4">2</span>
            <h2 class="text-2xl font-semibold text-gray-900">Authenticate Your Requests</h2>
        </div>
        <div class="ml-14 bg-white p-6 rounded-lg border border-gray-200">
            <p class="text-gray-700 mb-4">
                Include your API key in the request header using one of these methods:
            </p>
            <div class="space-y-4">
                <div>
                    <p class="font-medium text-gray-900 mb-2">Option 1: X-API-Key header (Recommended)</p>
                    <pre><code class="language-bash">curl -H "X-API-Key: your_api_key_here" {{ url('/api/notifications/send') }}</code></pre>
                </div>
                <div>
                    <p class="font-medium text-gray-900 mb-2">Option 2: Authorization Bearer</p>
                    <pre><code class="language-bash">curl -H "Authorization: Bearer your_api_key_here" {{ url('/api/notifications/send') }}</code></pre>
                </div>
                <div>
                    <p class="font-medium text-gray-900 mb-2">Option 3: X-Auth-Token header</p>
                    <pre><code class="language-bash">curl -H "X-Auth-Token: your_api_key_here" {{ url('/api/notifications/send') }}</code></pre>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 3: First Request -->
    <div class="mb-12">
        <div class="flex items-center mb-4">
            <span class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center text-lg font-bold mr-4">3</span>
            <h2 class="text-2xl font-semibold text-gray-900">Make Your First Request</h2>
        </div>
        <div class="ml-14 bg-white p-6 rounded-lg border border-gray-200">
            <p class="text-gray-700 mb-4">
                Send a test email notification:
            </p>
            <pre><code class="language-bash">curl -X POST {{ url('/api/notifications/send') }} \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your_api_key_here" \
  -d '{
    "schema_name": "your_schema_name",
    "channel": "email",
    "to": "test@example.com",
    "subject": "Test Notification",
    "message": "Hello! This is a test notification.",
    "provider": "sendgrid",
    "priority": "normal"
  }'</code></pre>

            <div class="mt-4 bg-green-50 border-l-4 border-green-400 p-4 rounded-r">
                <p class="font-semibold text-green-800 mb-2">Expected Response (201 Created):</p>
                <pre><code class="language-json">{
  "success": true,
  "message_id": 123,
  "external_id": "sg_abc123",
  "status": "sent",
  "provider": "sendgrid",
  "data": {
    "id": 123,
    "channel": "email",
    "recipient": "test@example.com",
    "status": "sent",
    "sent_at": "2026-04-04T12:00:00Z"
  }
}</code></pre>
            </div>
        </div>
    </div>

    <!-- Step 4: Understanding Channels -->
    <div class="mb-12">
        <div class="flex items-center mb-4">
            <span class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center text-lg font-bold mr-4">4</span>
            <h2 class="text-2xl font-semibold text-gray-900">Choose Your Channel</h2>
        </div>
        <div class="ml-14 space-y-4">
            <!-- Email -->
            <div class="bg-white p-6 rounded-lg border border-gray-200">
                <div class="flex items-center mb-3">
                    <i class="fas fa-envelope text-green-600 text-2xl mr-3"></i>
                    <h3 class="text-xl font-semibold">Email</h3>
                </div>
                <p class="text-gray-700 mb-3">Providers: Resend (priority), SendGrid, Mailgun</p>
                <pre><code class="language-json">{
  "channel": "email",
  "to": "user@example.com",
  "subject": "Your subject here",
  "message": "Email body content",
  "provider": "sendgrid"
}</code></pre>
            </div>

            <!-- SMS -->
            <div class="bg-white p-6 rounded-lg border border-gray-200">
                <div class="flex items-center mb-3">
                    <i class="fas fa-sms text-blue-600 text-2xl mr-3"></i>
                    <h3 class="text-xl font-semibold">SMS</h3>
                </div>
                <p class="text-gray-700 mb-3">Providers: Beem (Tanzania), Termii (Nigeria), Twilio (Global)</p>
                <pre><code class="language-json">{
  "channel": "sms",
  "to": "+255712345678",
  "message": "Your SMS message here",
  "provider": "beem"
}</code></pre>
                <p class="text-sm text-gray-600 mt-2">
                    <i class="fas fa-info-circle mr-1"></i>
                    SMS requires an active SMS session for your schema. See <a href="{{ route('docs.reference') }}#sms-session-management" class="text-blue-600 hover:underline">SMS Session Management</a>.
                </p>
            </div>

            <!-- WhatsApp -->
            <div class="bg-white p-6 rounded-lg border border-gray-200">
                <div class="flex items-center mb-3">
                    <i class="fab fa-whatsapp text-purple-600 text-2xl mr-3"></i>
                    <h3 class="text-xl font-semibold">WhatsApp</h3>
                </div>
                <p class="text-gray-700 mb-3">Options: Official API or WaSender (unofficial)</p>
                <pre><code class="language-json">{
  "channel": "whatsapp",
  "to": "+255712345678",
  "message": "Your WhatsApp message here",
  "type": "wasender"
}</code></pre>
                <p class="text-sm text-gray-600 mt-2">
                    <i class="fas fa-info-circle mr-1"></i>
                    WaSender requires session setup with QR code authentication. See <a href="{{ route('docs.reference') }}#wasender-session-management" class="text-blue-600 hover:underline">WaSender Guide</a>.
                </p>
            </div>
        </div>
    </div>

    <!-- Step 5: Next Steps -->
    <div class="mb-12">
        <div class="flex items-center mb-4">
            <span class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center text-lg font-bold mr-4">5</span>
            <h2 class="text-2xl font-semibold text-gray-900">Next Steps</h2>
        </div>
        <div class="ml-14 grid md:grid-cols-2 gap-4">
            <a href="{{ route('docs.reference') }}" class="block bg-white p-6 rounded-lg border border-gray-200 hover:border-blue-500 hover:shadow-md transition">
                <i class="fas fa-book text-blue-600 text-2xl mb-3"></i>
                <h3 class="font-semibold text-gray-900 mb-2">API Reference</h3>
                <p class="text-sm text-gray-600">Complete documentation of all endpoints and parameters</p>
            </a>

            <a href="{{ route('docs.code-examples') }}" class="block bg-white p-6 rounded-lg border border-gray-200 hover:border-blue-500 hover:shadow-md transition">
                <i class="fas fa-code text-blue-600 text-2xl mb-3"></i>
                <h3 class="font-semibold text-gray-900 mb-2">Code Examples</h3>
                <p class="text-sm text-gray-600">Ready-to-use code snippets in multiple languages</p>
            </a>

            <a href="{{ route('docs.explorer') }}" class="block bg-white p-6 rounded-lg border border-gray-200 hover:border-blue-500 hover:shadow-md transition">
                <i class="fas fa-play-circle text-blue-600 text-2xl mb-3"></i>
                <h3 class="font-semibold text-gray-900 mb-2">API Explorer</h3>
                <p class="text-sm text-gray-600">Test endpoints interactively in your browser</p>
            </a>

            <a href="{{ route('docs.webhooks') }}" class="block bg-white p-6 rounded-lg border border-gray-200 hover:border-blue-500 hover:shadow-md transition">
                <i class="fas fa-webhook text-blue-600 text-2xl mb-3"></i>
                <h3 class="font-semibold text-gray-900 mb-2">Webhook Tester</h3>
                <p class="text-sm text-gray-600">Test and debug webhook integrations</p>
            </a>
        </div>
    </div>

    <!-- Common Scenarios -->
    <div class="bg-gradient-to-br from-blue-50 to-purple-50 rounded-xl p-8">
        <h2 class="text-2xl font-semibold text-gray-900 mb-6">Common Scenarios</h2>
        <div class="space-y-4">
            <details class="bg-white p-4 rounded-lg border border-gray-200">
                <summary class="font-semibold text-gray-900 cursor-pointer">How do I send to multiple recipients?</summary>
                <p class="mt-3 text-gray-700">
                    Use the bulk send endpoint: <code>POST /api/notifications/bulk/send</code>. You can send up to 1,000 messages in a single request. 
                    See <a href="{{ route('docs.reference') }}#send-bulk-notifications" class="text-blue-600 hover:underline">Bulk Operations</a> for details.
                </p>
            </details>

            <details class="bg-white p-4 rounded-lg border border-gray-200">
                <summary class="font-semibold text-gray-900 cursor-pointer">How do I schedule notifications for later?</summary>
                <p class="mt-3 text-gray-700">
                    Include the <code>scheduled_at</code> parameter with an ISO 8601 timestamp. Example: <code>"scheduled_at": "2026-04-10T09:00:00Z"</code>
                </p>
            </details>

            <details class="bg-white p-4 rounded-lg border border-gray-200">
                <summary class="font-semibold text-gray-900 cursor-pointer">How do I attach files to emails?</summary>
                <p class="mt-3 text-gray-700">
                    Include <code>attachment</code> (base64-encoded content), <code>attachment_name</code>, and <code>attachment_type</code> in your request. 
                    See <a href="{{ route('docs.reference') }}#attachment-processing" class="text-blue-600 hover:underline">Attachment Guide</a>.
                </p>
            </details>

            <details class="bg-white p-4 rounded-lg border border-gray-200">
                <summary class="font-semibold text-gray-900 cursor-pointer">What are rate limits?</summary>
                <p class="mt-3 text-gray-700">
                    Default limits: 2 requests/second for single notifications, 1 request/2 seconds for bulk operations. 
                    See <a href="{{ route('docs.guides', 'rate-limits') }}" class="text-blue-600 hover:underline">Rate Limits Guide</a>.
                </p>
            </details>

            <details class="bg-white p-4 rounded-lg border border-gray-200">
                <summary class="font-semibold text-gray-900 cursor-pointer">How do I receive delivery status updates?</summary>
                <p class="mt-3 text-gray-700">
                    Include a <code>webhook_url</code> in your request to receive HTTP callbacks when delivery status changes.
                </p>
            </details>
        </div>
    </div>

    <!-- Help Section -->
    <div class="mt-12 bg-blue-600 text-white rounded-xl p-8 text-center">
        <h2 class="text-2xl font-bold mb-3">Need Help?</h2>
        <p class="mb-6">Our support team is here to assist you</p>
        <div class="flex justify-center gap-4">
            <a href="mailto:support@example.com" class="inline-flex items-center px-6 py-3 bg-white text-blue-600 font-semibold rounded-lg hover:bg-gray-100 transition">
                <i class="fas fa-envelope mr-2"></i>
                Contact Support
            </a>
            <a href="{{ route('docs.reference') }}" class="inline-flex items-center px-6 py-3 bg-transparent border-2 border-white text-white font-semibold rounded-lg hover:bg-white hover:text-blue-600 transition">
                <i class="fas fa-book mr-2"></i>
                View Full Docs
            </a>
        </div>
    </div>
</div>
@endsection
