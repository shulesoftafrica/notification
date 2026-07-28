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

                <!-- Parameter table -->
                <div class="overflow-x-auto mb-4">
                    <table class="w-full text-sm border border-gray-200 rounded">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left px-3 py-2 border-b font-semibold">Field</th>
                                <th class="text-left px-3 py-2 border-b font-semibold">Type</th>
                                <th class="text-left px-3 py-2 border-b font-semibold">Required</th>
                                <th class="text-left px-3 py-2 border-b font-semibold">Description</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr><td class="px-3 py-2"><code>schema_name</code></td><td class="px-3 py-2">string</td><td class="px-3 py-2 text-green-600 font-semibold">✅</td><td class="px-3 py-2">Tenant/schema identifier</td></tr>
                            <tr class="bg-gray-50"><td class="px-3 py-2"><code>channel</code></td><td class="px-3 py-2">string</td><td class="px-3 py-2 text-green-600 font-semibold">✅</td><td class="px-3 py-2">Must be <code>"whatsapp"</code></td></tr>
                            <tr><td class="px-3 py-2"><code>to</code></td><td class="px-3 py-2">string</td><td class="px-3 py-2 text-green-600 font-semibold">✅</td><td class="px-3 py-2">Recipient phone number in international format (e.g. <code>+255712345678</code>)</td></tr>
                            <tr class="bg-gray-50"><td class="px-3 py-2"><code>message</code></td><td class="px-3 py-2">string</td><td class="px-3 py-2 text-green-600 font-semibold">✅</td><td class="px-3 py-2">Message body (max 4096 chars). Used as caption when sending media.</td></tr>
                            <tr><td class="px-3 py-2"><code>type</code></td><td class="px-3 py-2">string</td><td class="px-3 py-2 text-gray-400">—</td><td class="px-3 py-2"><code>"wasender"</code> (unofficial, QR-based) or <code>"official"</code> (Meta WhatsApp API). Defaults to official if omitted.</td></tr>
                            <tr class="bg-gray-50"><td class="px-3 py-2"><code>priority</code></td><td class="px-3 py-2">string</td><td class="px-3 py-2 text-gray-400">—</td><td class="px-3 py-2"><code>low</code>, <code>normal</code> (default), <code>high</code>, <code>urgent</code></td></tr>
                            <tr><td class="px-3 py-2"><code>scheduled_at</code></td><td class="px-3 py-2">datetime</td><td class="px-3 py-2 text-gray-400">—</td><td class="px-3 py-2">ISO 8601 future timestamp to schedule delivery</td></tr>
                            <tr class="bg-gray-50"><td class="px-3 py-2"><code>metadata</code></td><td class="px-3 py-2">object</td><td class="px-3 py-2 text-gray-400">—</td><td class="px-3 py-2">Custom key-value data stored with the message (max 10 keys). Also supports <code>media_type</code> + <code>media_url</code> for URL-based media.</td></tr>
                            <tr><td class="px-3 py-2"><code>tags</code></td><td class="px-3 py-2">array</td><td class="px-3 py-2 text-gray-400">—</td><td class="px-3 py-2">String labels for the message (max 10, each max 50 chars)</td></tr>
                            <tr class="bg-gray-50"><td class="px-3 py-2"><code>webhook_url</code></td><td class="px-3 py-2">string</td><td class="px-3 py-2 text-gray-400">—</td><td class="px-3 py-2">URL to receive delivery status callbacks</td></tr>
                            <tr><td class="px-3 py-2"><code>attachment</code></td><td class="px-3 py-2">string</td><td class="px-3 py-2 text-gray-400">—</td><td class="px-3 py-2">Base64-encoded file content (with or without <code>data:mime/type;base64,</code> prefix, max 10 MB)</td></tr>
                            <tr class="bg-gray-50"><td class="px-3 py-2"><code>attachment_name</code></td><td class="px-3 py-2">string</td><td class="px-3 py-2 text-yellow-600 font-semibold">✅ if attachment</td><td class="px-3 py-2">Original filename (e.g. <code>invoice.pdf</code>)</td></tr>
                            <tr><td class="px-3 py-2"><code>attachment_type</code></td><td class="px-3 py-2">string</td><td class="px-3 py-2 text-yellow-600 font-semibold">✅ if attachment</td><td class="px-3 py-2">MIME type — see supported types below</td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Supported MIME types for WhatsApp -->
                <div class="mb-4 p-3 bg-purple-50 border border-purple-200 rounded text-sm">
                    <p class="font-semibold text-purple-800 mb-1"><i class="fas fa-paperclip mr-1"></i>Supported attachment MIME types for WhatsApp</p>
                    <ul class="text-purple-700 list-disc list-inside space-y-0.5">
                        <li><strong>Images:</strong> <code>image/jpeg</code>, <code>image/png</code>, <code>image/gif</code></li>
                        <li><strong>Documents:</strong> <code>application/pdf</code></li>
                        <li><strong>Video:</strong> <code>video/mp4</code>, <code>video/webm</code></li>
                        <li><strong>Audio:</strong> <code>audio/mpeg</code>, <code>audio/ogg</code>, <code>audio/wav</code></li>
                    </ul>
                </div>

                <!-- Tab navigation for examples -->
                <div class="mt-2">
                    <div class="flex flex-wrap gap-1 mb-3" id="wa-tabs">
                        <button onclick="waTab('text',this)" class="wa-tab-btn px-3 py-1 rounded text-xs font-medium transition bg-purple-600 text-white">Text</button>
                        <button onclick="waTab('image',this)" class="wa-tab-btn px-3 py-1 rounded text-xs font-medium transition bg-gray-100 text-gray-700 hover:bg-gray-200">📷 Image</button>
                        <button onclick="waTab('pdf',this)" class="wa-tab-btn px-3 py-1 rounded text-xs font-medium transition bg-gray-100 text-gray-700 hover:bg-gray-200">📄 PDF</button>
                        <button onclick="waTab('video',this)" class="wa-tab-btn px-3 py-1 rounded text-xs font-medium transition bg-gray-100 text-gray-700 hover:bg-gray-200">🎬 Video</button>
                        <button onclick="waTab('audio',this)" class="wa-tab-btn px-3 py-1 rounded text-xs font-medium transition bg-gray-100 text-gray-700 hover:bg-gray-200">🎵 Audio</button>
                        <button onclick="waTab('media_url',this)" class="wa-tab-btn px-3 py-1 rounded text-xs font-medium transition bg-gray-100 text-gray-700 hover:bg-gray-200">🔗 URL Media</button>
                    </div>

                    <!-- Text -->
                    <div id="wa-tab-text" class="wa-tab-panel">
                        <pre><code class="language-json">{
  "schema_name": "client_tenant_demo",
  "channel": "whatsapp",
  "to": "+255712345678",
  "message": "Hello! Your order has been confirmed.",
  "type": "wasender",
  "priority": "normal",
  "metadata": { "order_id": "12345" },
  "tags": ["order", "confirmation"],
  "webhook_url": "https://your-app.com/webhook"
}</code></pre>
                    </div>

                    <!-- Image -->
                    <div id="wa-tab-image" class="wa-tab-panel" style="display:none">
                        <pre><code class="language-json">{
  "schema_name": "client_tenant_demo",
  "channel": "whatsapp",
  "to": "+255712345678",
  "message": "Here is your receipt image.",
  "type": "wasender",
  "attachment": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQE...",
  "attachment_name": "receipt.jpg",
  "attachment_type": "image/jpeg"
}</code></pre>
                        <p class="text-xs text-gray-500 mt-1">Supported: <code>image/jpeg</code>, <code>image/png</code>, <code>image/gif</code>. The <code>message</code> is used as the image caption.</p>
                    </div>

                    <!-- PDF -->
                    <div id="wa-tab-pdf" class="wa-tab-panel" style="display:none">
                        <pre><code class="language-json">{
  "schema_name": "client_tenant_demo",
  "channel": "whatsapp",
  "to": "+255712345678",
  "message": "Please find your invoice attached.",
  "type": "wasender",
  "attachment": "data:application/pdf;base64,JVBERi0xLjQ...",
  "attachment_name": "invoice.pdf",
  "attachment_type": "application/pdf"
}</code></pre>
                        <p class="text-xs text-gray-500 mt-1">PDF files are sent as documents. The <code>attachment_name</code> is shown as the file name in WhatsApp.</p>
                    </div>

                    <!-- Video -->
                    <div id="wa-tab-video" class="wa-tab-panel" style="display:none">
                        <pre><code class="language-json">{
  "schema_name": "client_tenant_demo",
  "channel": "whatsapp",
  "to": "+255712345678",
  "message": "Watch this product demo.",
  "type": "wasender",
  "attachment": "data:video/mp4;base64,AAAAIGZ0eXBpc28...",
  "attachment_name": "demo.mp4",
  "attachment_type": "video/mp4"
}</code></pre>
                        <p class="text-xs text-gray-500 mt-1">Supported: <code>video/mp4</code>, <code>video/webm</code>. The <code>message</code> is used as the video caption.</p>
                    </div>

                    <!-- Audio -->
                    <div id="wa-tab-audio" class="wa-tab-panel" style="display:none">
                        <pre><code class="language-json">{
  "schema_name": "client_tenant_demo",
  "channel": "whatsapp",
  "to": "+255712345678",
  "message": "Voice note for your delivery update.",
  "type": "wasender",
  "attachment": "data:audio/mpeg;base64,SUQzBAAAAAAAI1RTU0U...",
  "attachment_name": "voice_note.mp3",
  "attachment_type": "audio/mpeg"
}</code></pre>
                        <p class="text-xs text-gray-500 mt-1">Supported: <code>audio/mpeg</code>, <code>audio/ogg</code>, <code>audio/wav</code>. No caption is added for audio files.</p>
                    </div>

                    <!-- URL-based media -->
                    <div id="wa-tab-media_url" class="wa-tab-panel" style="display:none">
                        <pre><code class="language-json">{
  "schema_name": "client_tenant_demo",
  "channel": "whatsapp",
  "to": "+255712345678",
  "message": "Check out this image!",
  "type": "official",
  "metadata": {
    "media_type": "image",
    "media_url": "https://example.com/promo-banner.jpg"
  }
}</code></pre>
                        <p class="text-xs text-gray-500 mt-1">Use <code>metadata.media_type</code> (<code>image</code>, <code>video</code>, <code>audio</code>, <code>document</code>) with <code>metadata.media_url</code> to reference publicly accessible media without uploading base64.</p>
                    </div>
                </div>
                <script>
                function waTab(name, btn) {
                    document.querySelectorAll('.wa-tab-panel').forEach(function(el){ el.style.display = 'none'; });
                    document.querySelectorAll('.wa-tab-btn').forEach(function(b){
                        b.classList.remove('bg-purple-600','text-white');
                        b.classList.add('bg-gray-100','text-gray-700');
                    });
                    document.getElementById('wa-tab-' + name).style.display = '';
                    btn.classList.remove('bg-gray-100','text-gray-700');
                    btn.classList.add('bg-purple-600','text-white');
                    if (window.Prism) Prism.highlightAllUnder(document.getElementById('wa-tab-' + name));
                }
                </script>

                <p class="text-sm text-gray-600 mt-3">
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
