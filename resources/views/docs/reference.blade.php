@extends('docs.layout')

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">
            <i class="fas fa-book text-blue-600 mr-3"></i>
            API Reference
        </h1>
        <div class="flex items-center gap-4 text-sm text-gray-600">
            <span class="badge badge-success">Version 1.1</span>
            <span><i class="far fa-calendar mr-1"></i> Updated: March 26, 2026</span>
            <span><i class="fas fa-server mr-1"></i> Base URL: <code class="text-xs">{{ url('/api') }}</code></span>
        </div>
    </div>

    <div class="grid lg:grid-cols-4 gap-8">
        <!-- Sticky Table of Contents (Left Sidebar) -->
        <div class="lg:col-span-1">
            <div class="sticky top-24 bg-white rounded-lg border border-gray-200 p-4">
                <h3 class="font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-list text-blue-600 mr-2"></i>
                    Table of Contents
                </h3>
                <nav class="toc space-y-1 text-sm">
                    <a href="#authentication" class="toc-link block py-1 text-gray-600 hover:text-blue-600 transition">1. Authentication</a>
                    <a href="#health-check" class="toc-link block py-1 text-gray-600 hover:text-blue-600 transition">2. Health Check</a>
                    <a href="#notification-api" class="toc-link block py-1 text-gray-600 hover:text-blue-600 transition">3. Notification API</a>
                    <div class="ml-4 space-y-1">
                        <a href="#send-single-notification" class="toc-link block py-1 text-xs text-gray-500 hover:text-blue-600 transition">3.1 Send Single</a>
                        <a href="#send-bulk-notifications" class="toc-link block py-1 text-xs text-gray-500 hover:text-blue-600 transition">3.2 Send Bulk</a>
                        <a href="#resend-notifications" class="toc-link block py-1 text-xs text-gray-500 hover:text-blue-600 transition">3.3 Resend</a>
                        <a href="#get-notification-status" class="toc-link block py-1 text-xs text-gray-500 hover:text-blue-600 transition">3.4 Get Status</a>
                        <a href="#list-notifications" class="toc-link block py-1 text-xs text-gray-500 hover:text-blue-600 transition">3.5 List All</a>
                        <a href="#bulk-delete-notifications" class="toc-link block py-1 text-xs text-gray-500 hover:text-blue-600 transition">3.6 Bulk Delete</a>
                        <a href="#get-sms-balance" class="toc-link block py-1 text-xs text-gray-500 hover:text-blue-600 transition">3.7 SMS Balance</a>
                    </div>
                    <a href="#sms-session-api" class="toc-link block py-1 text-gray-600 hover:text-blue-600 transition">4. SMS Sessions</a>
                    <a href="#wasender-api" class="toc-link block py-1 text-gray-600 hover:text-blue-600 transition">5. WaSender Sessions</a>
                    <a href="#rate-limiting" class="toc-link block py-1 text-gray-600 hover:text-blue-600 transition">6. Rate Limiting</a>
                    <a href="#webhooks" class="toc-link block py-1 text-gray-600 hover:text-blue-600 transition">7. Webhooks</a>
                    <a href="#admin-auth" class="toc-link block py-1 text-gray-600 hover:text-blue-600 transition">8. Admin Auth</a>
                    <a href="#error-reference" class="toc-link block py-1 text-gray-600 hover:text-blue-600 transition">9. Error Reference</a>
                    <a href="#field-validation" class="toc-link block py-1 text-gray-600 hover:text-blue-600 transition">10. Field Validation</a>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-3">
            <div class="prose prose-blue max-w-none">
                <!-- 1. Authentication -->
                <section id="authentication" class="api-section mb-12">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-key text-blue-600 mr-3"></i>
                        1. Authentication
                    </h2>
                    <p class="text-gray-600 mb-4">
                        All endpoints (except Health Check) require an API key. The key must be <strong>at least 32 characters</strong> long.
                    </p>

                    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden mb-6">
                        <div class="bg-gray-50 px-4 py-2 border-b border-gray-200">
                            <h4 class="font-semibold text-sm text-gray-700">Supported Header Methods</h4>
                        </div>
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Header</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Example</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr>
                                    <td class="px-6 py-4 text-sm font-mono text-gray-800">X-API-Key</td>
                                    <td class="px-6 py-4 text-sm font-mono text-gray-600">X-API-Key: your_api_key_here</td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-sm font-mono text-gray-800">X-Api-Key</td>
                                    <td class="px-6 py-4 text-sm font-mono text-gray-600">X-Api-Key: your_api_key_here</td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-sm font-mono text-gray-800">X-AUTH-TOKEN</td>
                                    <td class="px-6 py-4 text-sm font-mono text-gray-600">X-AUTH-TOKEN: your_api_key_here</td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-sm font-mono text-gray-800">X-Auth-Token</td>
                                    <td class="px-6 py-4 text-sm font-mono text-gray-600">X-Auth-Token: your_api_key_here</td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-sm font-mono text-gray-800">Authorization</td>
                                    <td class="px-6 py-4 text-sm font-mono text-gray-600">Authorization: Bearer your_api_key_here</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                        <div class="flex">
                            <i class="fas fa-exclamation-triangle text-yellow-400 mr-3 mt-1"></i>
                            <div>
                                <p class="text-sm text-yellow-800">
                                    You may also pass the key as a query parameter <code>?api_key=your_api_key</code>, but this is <strong>not recommended for production</strong> due to security concerns.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-900 rounded-lg p-4 mb-6">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs text-gray-400">Authentication Error (401)</span>
                            <button class="copy-code-btn text-xs text-gray-400 hover:text-white">
                                <i class="fas fa-copy mr-1"></i> Copy
                            </button>
                        </div>
                        <pre><code class="language-json">{
  "success": false,
  "error": "Unauthorized",
  "message": "API key required. Please provide an API key in X-API-Key, Authorization, or X-Auth-Token header."
}</code></pre>
                    </div>
                </section>

                <!-- 2. Health Check -->
                <section id="health-check" class="api-section mb-12">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-heartbeat text-green-600 mr-3"></i>
                        2. Health Check
                    </h2>

                    <div class="endpoint-card bg-white rounded-lg border border-gray-200 p-6 mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center">
                                <span class="badge badge-get mr-3">GET</span>
                                <code class="text-lg font-mono text-gray-800">/api/health</code>
                            </div>
                            <span class="badge badge-success text-xs">No Auth Required</span>
                        </div>
                        <p class="text-gray-600 mb-4">
                            Returns the operational status of the service. Also available at <code>GET /api/up</code>.
                        </p>

                        <div class="bg-gray-900 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-gray-400">Response (200 — Healthy)</span>
                                <button class="copy-code-btn text-xs text-gray-400 hover:text-white">
                                    <i class="fas fa-copy mr-1"></i> Copy
                                </button>
                            </div>
                            <pre><code class="language-json">{
  "status": "healthy",
  "timestamp": "2026-03-26T10:00:00.000Z",
  "checks": {
    "database": true,
    "cache": true
  },
  "uptime": "5d 3h 12m"
}</code></pre>
                        </div>
                    </div>
                </section>

                <!-- 3. Notification API -->
                <section id="notification-api" class="api-section mb-12">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                        <i class="fas fa-bell text-blue-600 mr-3"></i>
                        3. Notification API
                    </h2>

                    <!-- 3.1 Send Single Notification -->
                    <div id="send-single-notification" class="endpoint-card bg-white rounded-lg border border-gray-200 p-6 mb-8">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <div class="flex items-center mb-2">
                                    <span class="badge badge-post mr-3">POST</span>
                                    <code class="text-lg font-mono text-gray-800">/api/notifications/send</code>
                                </div>
                                <p class="text-gray-600">Send a single notification via Email, SMS, or WhatsApp</p>
                            </div>
                            <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm" onclick="window.location.href='{{ route('docs.explorer') }}#operations-Notifications-post_api_notifications_send'">
                                <i class="fas fa-play mr-2"></i>
                                Try It
                            </button>
                        </div>

                        <div class="mb-4">
                            <h4 class="font-semibold text-gray-900 mb-3">Request Body</h4>
                            <div class="bg-gray-900 rounded-lg p-4">
                                <pre><code class="language-json">{
  "schema_name": "my_app",
  "channel": "email",
  "to": "user@example.com",
  "subject": "Welcome to Our Platform",
  "message": "Thank you for signing up!",
  "from": "noreply@myapp.com",
  "provider": "sendgrid",
  "webhook_url": "https://myapp.com/webhook/notification"
}</code></pre>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h4 class="font-semibold text-gray-900 mb-3">Parameters</h4>
                            <table class="min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg overflow-hidden">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Field</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Required</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-mono text-gray-800">schema_name</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">string</td>
                                        <td class="px-4 py-3 text-sm"><span class="badge badge-required">Yes</span></td>
                                        <td class="px-4 py-3 text-sm text-gray-600">Multi-tenant identifier (alphanumeric, underscore)</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-mono text-gray-800">channel</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">string</td>
                                        <td class="px-4 py-3 text-sm"><span class="badge badge-required">Yes</span></td>
                                        <td class="px-4 py-3 text-sm text-gray-600">One of: <code>email</code>, <code>sms</code>, <code>whatsapp</code></td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-mono text-gray-800">to</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">string</td>
                                        <td class="px-4 py-3 text-sm"><span class="badge badge-required">Yes</span></td>
                                        <td class="px-4 py-3 text-sm text-gray-600">Recipient (email address or phone number with country code)</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-mono text-gray-800">message</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">string</td>
                                        <td class="px-4 py-3 text-sm"><span class="badge badge-required">Yes</span></td>
                                        <td class="px-4 py-3 text-sm text-gray-600">Message content (supports plain text or HTML for email)</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-mono text-gray-800">subject</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">string</td>
                                        <td class="px-4 py-3 text-sm"><span class="badge badge-optional">No</span></td>
                                        <td class="px-4 py-3 text-sm text-gray-600">Email subject (required for email channel)</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-mono text-gray-800">provider</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">string</td>
                                        <td class="px-4 py-3 text-sm"><span class="badge badge-optional">No</span></td>
                                        <td class="px-4 py-3 text-sm text-gray-600">Force specific provider (e.g., sendgrid, twilio, beem)</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-mono text-gray-800">webhook_url</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">string</td>
                                        <td class="px-4 py-3 text-sm"><span class="badge badge-optional">No</span></td>
                                        <td class="px-4 py-3 text-sm text-gray-600">URL to receive delivery status callbacks</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-3">Success Response (200)</h4>
                            <div class="bg-gray-900 rounded-lg p-4">
                                <pre><code class="language-json">{
  "success": true,
  "message_id": "msg_1a2b3c4d5e6f",
  "channel": "email",
  "provider": "sendgrid",
  "status": "sent",
  "to": "user@example.com",
  "webhook_url": "https://myapp.com/webhook/notification"
}</code></pre>
                            </div>
                        </div>
                    </div>

                    <!-- 3.2 Send Bulk Notifications -->
                    <div id="send-bulk-notifications" class="endpoint-card bg-white rounded-lg border border-gray-200 p-6 mb-8">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <div class="flex items-center mb-2">
                                    <span class="badge badge-post mr-3">POST</span>
                                    <code class="text-lg font-mono text-gray-800">/api/notifications/bulk-send</code>
                                </div>
                                <p class="text-gray-600">Send multiple notifications in a single request (max 100)</p>
                            </div>
                            <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm" onclick="window.location.href='{{ route('docs.explorer') }}'">
                                <i class="fas fa-play mr-2"></i>
                                Try It
                            </button>
                        </div>

                        <div class="mb-4">
                            <h4 class="font-semibold text-gray-900 mb-3">Request Body</h4>
                            <div class="bg-gray-900 rounded-lg p-4">
                                <pre><code class="language-json">{
  "schema_name": "my_app",
  "notifications": [
    {
      "channel": "email",
      "to": "user1@example.com",
      "subject": "Welcome!",
      "message": "Thank you for joining."
    },
    {
      "channel": "sms",
      "to": "+1234567890",
      "message": "Your verification code is 123456"
    }
  ]
}</code></pre>
                            </div>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-3">Success Response (200)</h4>
                            <div class="bg-gray-900 rounded-lg p-4">
                                <pre><code class="language-json">{
  "success": true,
  "sent": 2,
  "failed": 0,
  "results": [
    {
      "message_id": "msg_abc123",
      "status": "sent",
      "to": "user1@example.com"
    },
    {
      "message_id": "msg_def456",
      "status": "sent",
      "to": "+1234567890"
    }
  ]
}</code></pre>
                            </div>
                        </div>
                    </div>

                    <!-- 3.3 Resend Notifications -->
                    <div id="resend-notifications" class="endpoint-card bg-white rounded-lg border border-gray-200 p-6 mb-8">
                        <div class="flex items-center mb-4">
                            <span class="badge badge-post mr-3">POST</span>
                            <code class="text-lg font-mono text-gray-800">/api/notifications/resend</code>
                        </div>
                        <p class="text-gray-600 mb-4">Resend a previously failed or bounced notification</p>

                        <div class="mb-4">
                            <h4 class="font-semibold text-gray-900 mb-3">Request Body</h4>
                            <div class="bg-gray-900 rounded-lg p-4">
                                <pre><code class="language-json">{
  "schema_name": "my_app",
  "message_id": "msg_abc123",
  "provider": "twilio"
}</code></pre>
                            </div>
                        </div>
                    </div>

                    <!-- 3.4 Get Notification Status -->
                    <div id="get-notification-status" class="endpoint-card bg-white rounded-lg border border-gray-200 p-6 mb-8">
                        <div class="flex items-center mb-4">
                            <span class="badge badge-get mr-3">GET</span>
                            <code class="text-lg font-mono text-gray-800">/api/notifications/{message_id}/status</code>
                        </div>
                        <p class="text-gray-600 mb-4">Retrieve the current status of a notification</p>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-3">Success Response (200)</h4>
                            <div class="bg-gray-900 rounded-lg p-4">
                                <pre><code class="language-json">{
  "success": true,
  "message_id": "msg_abc123",
  "status": "delivered",
  "channel": "email",
  "provider": "sendgrid",
  "to": "user@example.com",
  "sent_at": "2026-03-26T10:00:00Z",
  "delivered_at": "2026-03-26T10:00:15Z"
}</code></pre>
                            </div>
                        </div>
                    </div>

                    <!-- 3.5 List Notifications -->
                    <div id="list-notifications" class="endpoint-card bg-white rounded-lg border border-gray-200 p-6 mb-8">
                        <div class="flex items-center mb-4">
                            <span class="badge badge-get mr-3">GET</span>
                            <code class="text-lg font-mono text-gray-800">/api/notifications</code>
                        </div>
                        <p class="text-gray-600 mb-4">List all notifications with optional filters</p>

                        <div class="mb-4">
                            <h4 class="font-semibold text-gray-900 mb-3">Query Parameters</h4>
                            <table class="min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg overflow-hidden">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Parameter</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-mono text-gray-800">schema_name</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">Filter by tenant (required)</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-mono text-gray-800">channel</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">Filter by channel (email, sms, whatsapp)</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-mono text-gray-800">status</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">Filter by status (sent, delivered, failed, etc.)</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-mono text-gray-800">page</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">Page number (default: 1)</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-mono text-gray-800">per_page</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">Items per page (default: 20, max: 100)</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 3.6 Bulk Delete -->
                    <div id="bulk-delete-notifications" class="endpoint-card bg-white rounded-lg border border-gray-200 p-6 mb-8">
                        <div class="flex items-center mb-4">
                            <span class="badge badge-delete mr-3">DELETE</span>
                            <code class="text-lg font-mono text-gray-800">/api/notifications/bulk-delete</code>
                        </div>
                        <p class="text-gray-600">Delete multiple notifications by message IDs</p>
                    </div>

                    <!-- 3.7 SMS Balance -->
                    <div id="get-sms-balance" class="endpoint-card bg-white rounded-lg border border-gray-200 p-6 mb-8">
                        <div class="flex items-center mb-4">
                            <span class="badge badge-get mr-3">GET</span>
                            <code class="text-lg font-mono text-gray-800">/api/sms/balance</code>
                        </div>
                        <p class="text-gray-600 mb-4">Check SMS balance for configured providers</p>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-3">Success Response (200)</h4>
                            <div class="bg-gray-900 rounded-lg p-4">
                                <pre><code class="language-json">{
  "success": true,
  "balances": {
    "twilio": {
      "balance": "45.20",
      "currency": "USD"
    },
    "beem": {
      "balance": "1250.00",
      "currency": "TZS"
    }
  }
}</code></pre>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Quick Links to Other Sections -->
                <section class="mb-12">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">More Sections</h2>
                    <div class="grid md:grid-cols-2 gap-4">
                        <a href="#sms-session-api" class="block p-6 bg-white rounded-lg border border-gray-200 hover:border-blue-500 hover:shadow-lg transition">
                            <h3 class="font-semibold text-gray-900 mb-2">
                                <i class="fas fa-sms text-blue-600 mr-2"></i>
                                SMS Session Management
                            </h3>
                            <p class="text-sm text-gray-600">Create and manage SMS sender configurations for different providers</p>
                        </a>
                        <a href="#wasender-api" class="block p-6 bg-white rounded-lg border border-gray-200 hover:border-green-500 hover:shadow-lg transition">
                            <h3 class="font-semibold text-gray-900 mb-2">
                                <i class="fab fa-whatsapp text-green-600 mr-2"></i>
                                WaSender Sessions
                            </h3>
                            <p class="text-sm text-gray-600">Manage WhatsApp sessions, QR codes, and connection status</p>
                        </a>
                        <a href="#rate-limiting" class="block p-6 bg-white rounded-lg border border-gray-200 hover:border-yellow-500 hover:shadow-lg transition">
                            <h3 class="font-semibold text-gray-900 mb-2">
                                <i class="fas fa-tachometer-alt text-yellow-600 mr-2"></i>
                                Rate Limiting
                            </h3>
                            <p class="text-sm text-gray-600">Understand rate limits and throttling policies</p>
                        </a>
                        <a href="#error-reference" class="block p-6 bg-white rounded-lg border border-gray-200 hover:border-red-500 hover:shadow-lg transition">
                            <h3 class="font-semibold text-gray-900 mb-2">
                                <i class="fas fa-exclamation-triangle text-red-600 mr-2"></i>
                                Error Reference
                            </h3>
                            <p class="text-sm text-gray-600">Complete list of error codes and troubleshooting</p>
                        </a>
                    </div>
                </section>

                <!-- Note about full reference -->
                <div class="bg-blue-50 border-l-4 border-blue-400 p-6 rounded-lg">
                    <div class="flex">
                        <i class="fas fa-info-circle text-blue-400 mr-3 mt-1"></i>
                        <div>
                            <h4 class="font-semibold text-blue-900 mb-2">Complete API Reference</h4>
                            <p class="text-sm text-blue-800 mb-3">
                                This page shows the most commonly used endpoints. For the complete API reference including SMS Sessions, WaSender API, Webhooks, and Admin Authentication, visit the <strong>API Explorer</strong> or download the OpenAPI specification.
                            </p>
                            <div class="flex gap-3">
                                <a href="{{ route('docs.explorer') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-semibold">
                                    <i class="fas fa-play mr-2"></i>
                                    Open API Explorer
                                </a>
                                <a href="{{ route('docs.openapi') }}" class="px-4 py-2 bg-white text-blue-600 border border-blue-600 rounded-lg hover:bg-blue-50 transition text-sm font-semibold">
                                    <i class="fas fa-download mr-2"></i>
                                    Download OpenAPI Spec
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('extra-js')
<script>
$(document).ready(function() {
    // Smooth scroll for TOC links
    $('.toc-link').click(function(e) {
        e.preventDefault();
        const target = $(this).attr('href');
        $('html, body').animate({
            scrollTop: $(target).offset().top - 100
        }, 500);
    });

    // Highlight active section in TOC
    $(window).scroll(function() {
        const scrollPos = $(window).scrollTop() + 150;
        
        $('.api-section').each(function() {
            const section = $(this);
            const sectionTop = section.offset().top;
            const sectionBottom = sectionTop + section.height();
            const sectionId = section.attr('id');
            
            if (scrollPos >= sectionTop && scrollPos < sectionBottom) {
                $('.toc-link').removeClass('text-blue-600 font-semibold');
                $(`.toc-link[href="#${sectionId}"]`).addClass('text-blue-600 font-semibold');
            }
        });
    });

    // Copy code blocks
    $('.copy-code-btn').click(function() {
        const code = $(this).closest('.bg-gray-900').find('code').text();
        navigator.clipboard.writeText(code).then(() => {
            const btn = $(this);
            const originalHtml = btn.html();
            btn.html('<i class="fas fa-check mr-1"></i> Copied!');
            setTimeout(() => {
                btn.html(originalHtml);
            }, 2000);
        });
    });
});
</script>
@endsection
