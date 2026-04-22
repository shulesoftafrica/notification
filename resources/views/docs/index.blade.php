@extends('docs.layout')

@section('content')
<div class="max-w-6xl mx-auto">
    <!-- Hero Section -->
    <div class="text-center mb-16">
        <div class="inline-flex items-center justify-center w-20 h-20 bg-blue-100 rounded-full mb-6">
            <i class="fas fa-bell text-4xl text-blue-600"></i>
        </div>
        <h1 class="text-5xl font-bold text-gray-900 mb-4">
            Notification Service API
        </h1>
        <p class="text-xl text-gray-600 mb-8 max-w-3xl mx-auto">
            Multi-channel notification platform supporting Email, SMS, and WhatsApp. 
            Send notifications at scale with powerful features and simple integration.
        </p>
        <div class="flex flex-wrap justify-center gap-4">
            <a href="{{ route('docs.getting-started') }}" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition shadow-lg">
                <i class="fas fa-rocket mr-2"></i>
                Get Started
            </a>
            <a href="{{ route('docs.explorer') }}" class="inline-flex items-center px-6 py-3 bg-white text-blue-600 font-semibold rounded-lg hover:bg-gray-50 transition border-2 border-blue-600">
                <i class="fas fa-play-circle mr-2"></i>
                Try API Explorer
            </a>
            <a href="{{ route('docs.postman') }}" class="inline-flex items-center px-6 py-3 bg-white text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition border border-gray-300">
                <i class="fas fa-download mr-2"></i>
                Postman Collection
            </a>
        </div>
    </div>

    <!-- Features Grid -->
    <div class="grid md:grid-cols-3 gap-8 mb-16">
        <div class="bg-white p-8 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                <i class="fas fa-envelope text-2xl text-green-600"></i>
            </div>
            <h3 class="text-xl font-semibold mb-3 text-gray-900">Email</h3>
            <p class="text-gray-600 mb-4">Send emails via Resend, SendGrid, or Mailgun with automatic failover and attachment support.</p>
            <ul class="text-sm text-gray-500 space-y-2">
                <li><i class="fas fa-check text-green-500 mr-2"></i> Multiple providers</li>
                <li><i class="fas fa-check text-green-500 mr-2"></i> Template support</li>
                <li><i class="fas fa-check text-green-500 mr-2"></i> Attachments</li>
            </ul>
        </div>

        <div class="bg-white p-8 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                <i class="fas fa-sms text-2xl text-blue-600"></i>
            </div>
            <h3 class="text-xl font-semibold mb-3 text-gray-900">SMS</h3>
            <p class="text-gray-600 mb-4">Deliver SMS messages through Beem, Termii, or Twilio with country-based routing.</p>
            <ul class="text-sm text-gray-500 space-y-2">
                <li><i class="fas fa-check text-green-500 mr-2"></i> Global coverage</li>
                <li><i class="fas fa-check text-green-500 mr-2"></i> Custom sender names</li>
                <li><i class="fas fa-check text-green-500 mr-2"></i> Balance tracking</li>
            </ul>
        </div>

        <div class="bg-white p-8 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                <i class="fab fa-whatsapp text-2xl text-purple-600"></i>
            </div>
            <h3 class="text-xl font-semibold mb-3 text-gray-900">WhatsApp</h3>
            <p class="text-gray-600 mb-4">Send WhatsApp messages via official API or WaSender with QR code authentication.</p>
            <ul class="text-sm text-gray-500 space-y-2">
                <li><i class="fas fa-check text-green-500 mr-2"></i> Official & unofficial</li>
                <li><i class="fas fa-check text-green-500 mr-2"></i> Session management</li>
                <li><i class="fas fa-check text-green-500 mr-2"></i> Webhook support</li>
            </ul>
        </div>
    </div>

    <!-- Key Features -->
    <div class="bg-gradient-to-br from-blue-50 to-purple-50 rounded-2xl p-10 mb-16">
        <h2 class="text-3xl font-bold text-center text-gray-900 mb-10">
            Powerful Features Built-In
        </h2>
        <div class="grid md:grid-cols-2 gap-6">
            <div class="flex items-start space-x-4">
                <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-bolt text-white"></i>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-1">Bulk Operations</h4>
                    <p class="text-sm text-gray-600">Send up to 1,000 messages in a single request with intelligent queuing</p>
                </div>
            </div>

            <div class="flex items-start space-x-4">
                <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-clock text-white"></i>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-1">Scheduled Delivery</h4>
                    <p class="text-sm text-gray-600">Schedule notifications for future delivery with precise timing</p>
                </div>
            </div>

            <div class="flex items-start space-x-4">
                <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-shield-alt text-white"></i>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-1">Provider Failover</h4>
                    <p class="text-sm text-gray-600">Automatic failover to backup providers ensures high deliverability</p>
                </div>
            </div>

            <div class="flex items-start space-x-4">
                <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-tachometer-alt text-white"></i>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-1">Rate Limiting</h4>
                    <p class="text-sm text-gray-600">Built-in rate limiting per provider with Redis-backed throttling</p>
                </div>
            </div>

            <div class="flex items-start space-x-4">
                <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-database text-white"></i>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-1">Multi-Tenancy</h4>
                    <p class="text-sm text-gray-600">Schema-based isolation for multiple clients in a single deployment</p>
                </div>
            </div>

            <div class="flex items-start space-x-4">
                <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-webhook text-white"></i>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-1">Webhooks</h4>
                    <p class="text-sm text-gray-600">Real-time delivery status callbacks to your application</p>
                </div>
            </div>

            <div class="flex items-start space-x-4">
                <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-tags text-white"></i>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-1">Metadata & Tags</h4>
                    <p class="text-sm text-gray-600">Attach custom metadata and tags for filtering and tracking</p>
                </div>
            </div>

            <div class="flex items-start space-x-4">
                <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-chart-line text-white"></i>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-1">Analytics</h4>
                    <p class="text-sm text-gray-600">Track delivery rates, provider performance, and usage metrics</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Start Code -->
    <div class="mb-16">
        <h2 class="text-3xl font-bold text-gray-900 mb-6 text-center">Quick Start Example</h2>
        <div class="bg-gray-900 rounded-xl overflow-hidden shadow-xl">
            <div class="flex items-center justify-between px-6 py-3 bg-gray-800 border-b border-gray-700">
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 rounded-full bg-red-500"></div>
                    <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                    <div class="w-3 h-3 rounded-full bg-green-500"></div>
                </div>
                <span class="text-gray-400 text-sm">Send Email Notification</span>
            </div>
            <div class="p-6">
                <pre><code class="language-bash">curl -X POST {{ url('/api/notifications/send') }} \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your_api_key_here" \
  -d '{
    "schema_name": "my_app",
    "channel": "email",
    "to": "customer@example.com",
    "subject": "Welcome to Our Service!",
    "message": "Thank you for signing up!",
    "provider": "sendgrid",
    "priority": "high"
  }'</code></pre>
            </div>
        </div>
    </div>

    <!-- Popular Endpoints -->
    <div class="mb-16">
        <h2 class="text-3xl font-bold text-gray-900 mb-6">Popular Endpoints</h2>
        <div class="space-y-3">
            <a href="{{ route('docs.reference') }}#send-single-notification" class="block bg-white p-5 rounded-lg border border-gray-200 hover:border-blue-500 hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <span class="badge badge-post">POST</span>
                        <code class="text-sm text-gray-700">/api/notifications/send</code>
                        <span class="text-gray-600">Send single notification</span>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400"></i>
                </div>
            </a>

            <a href="{{ route('docs.reference') }}#send-bulk-notifications" class="block bg-white p-5 rounded-lg border border-gray-200 hover:border-blue-500 hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <span class="badge badge-post">POST</span>
                        <code class="text-sm text-gray-700">/api/notifications/bulk/send</code>
                        <span class="text-gray-600">Send bulk notifications</span>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400"></i>
                </div>
            </a>

            <a href="{{ route('docs.reference') }}#get-notification-status" class="block bg-white p-5 rounded-lg border border-gray-200 hover:border-blue-500 hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <span class="badge badge-get">GET</span>
                        <code class="text-sm text-gray-700">/api/notifications/{id}</code>
                        <span class="text-gray-600">Get notification status</span>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400"></i>
                </div>
            </a>

            <a href="{{ route('docs.reference') }}#list-notifications" class="block bg-white p-5 rounded-lg border border-gray-200 hover:border-blue-500 hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <span class="badge badge-get">GET</span>
                        <code class="text-sm text-gray-700">/api/notifications</code>
                        <span class="text-gray-600">List all notifications</span>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400"></i>
                </div>
            </a>

            <a href="{{ route('docs.reference') }}#create-wasender-session" class="block bg-white p-5 rounded-lg border border-gray-200 hover:border-blue-500 hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <span class="badge badge-post">POST</span>
                        <code class="text-sm text-gray-700">/api/wasender/sessions/create</code>
                        <span class="text-gray-600">Create WhatsApp session</span>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400"></i>
                </div>
            </a>
        </div>
    </div>

    <!-- Call to Action -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl p-12 text-center text-white">
        <h2 class="text-3xl font-bold mb-4">Ready to Start Sending Notifications?</h2>
        <p class="text-lg mb-8 opacity-90">Get your API key and start integrating in minutes</p>
        <div class="flex flex-wrap justify-center gap-4">
            <a href="{{ route('docs.getting-started') }}" class="inline-flex items-center px-8 py-3 bg-white text-blue-600 font-semibold rounded-lg hover:bg-gray-100 transition shadow-lg">
                Read the Guide
                <i class="fas fa-arrow-right ml-2"></i>
            </a>
            <a href="{{ route('docs.explorer') }}" class="inline-flex items-center px-8 py-3 bg-transparent text-white font-semibold rounded-lg border-2 border-white hover:bg-white hover:text-blue-600 transition">
                Try it Live
                <i class="fas fa-play ml-2"></i>
            </a>
        </div>
    </div>
</div>
@endsection
