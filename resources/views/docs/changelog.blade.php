@extends('docs.layout')

@section('content')
<div class="max-w-5xl mx-auto">
    <h1 class="text-4xl font-bold text-gray-900 mb-4">
        <i class="fas fa-history text-blue-600 mr-3"></i>
        API Changelog
    </h1>
    <p class="text-lg text-gray-600 mb-8">
        Track the evolution of our Notification API. See what's new, what's changed, and what's been deprecated.
    </p>

    <!-- Version Timeline -->
    <div class="space-y-8">
        <!-- Version 1.1 -->
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 flex items-center">
                        v1.1
                        <span class="ml-3 px-3 py-1 bg-green-100 text-green-800 text-sm font-semibold rounded-full">Latest</span>
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">
                        <i class="far fa-calendar mr-2"></i>
                        March 26, 2026
                    </p>
                </div>
            </div>

            <div class="space-y-6">
                <!-- New Features -->
                <div>
                    <h3 class="font-semibold text-gray-900 mb-3 flex items-center">
                        <i class="fas fa-plus-circle text-green-600 mr-2"></i>
                        New Features
                    </h3>
                    <ul class="space-y-2 ml-6">
                        <li class="text-gray-700">
                            ✨ <strong>WhatsApp Dual Routing</strong> — Support for both Official WhatsApp Business API and WaSender integration
                        </li>
                        <li class="text-gray-700">
                            ✨ <strong>Webhook Testing Dashboard</strong> — Real-time webhook inspector for debugging integrations
                        </li>
                        <li class="text-gray-700">
                            ✨ <strong>Bulk Operations</strong> — Send up to 100 notifications in a single request with <code>/api/notifications/bulk-send</code>
                        </li>
                        <li class="text-gray-700">
                            ✨ <strong>SMS Balance Check</strong> — New endpoint <code>GET /api/sms/balance</code> to check provider balances
                        </li>
                        <li class="text-gray-700">
                            ✨ <strong>Notification Resend</strong> — Retry failed notifications with <code>POST /api/notifications/resend</code>
                        </li>
                        <li class="text-gray-700">
                            ✨ <strong>Provider Override</strong> — Force specific provider per notification with <code>provider</code> field
                        </li>
                    </ul>
                </div>

                <!-- Improvements -->
                <div>
                    <h3 class="font-semibold text-gray-900 mb-3 flex items-center">
                        <i class="fas fa-arrow-up text-blue-600 mr-2"></i>
                        Improvements
                    </h3>
                    <ul class="space-y-2 ml-6">
                        <li class="text-gray-700">
                            🚀 <strong>Rate Limiting</strong> — Enhanced throttling with configurable limits per provider
                        </li>
                        <li class="text-gray-700">
                            🚀 <strong>Multi-tenant Isolation</strong> — Better schema-based data isolation for enterprise clients
                        </li>
                        <li class="text-gray-700">
                            🚀 <strong>Webhook Reliability</strong> — Automatic retry logic for failed webhook deliveries
                        </li>
                        <li class="text-gray-700">
                            🚀 <strong>Error Messages</strong> — More descriptive error responses with actionable suggestions
                        </li>
                        <li class="text-gray-700">
                            🚀 <strong>Query Performance</strong> — Optimized database indices for faster notification history queries
                        </li>
                    </ul>
                </div>

                <!-- Bug Fixes -->
                <div>
                    <h3 class="font-semibold text-gray-900 mb-3 flex items-center">
                        <i class="fas fa-bug text-orange-600 mr-2"></i>
                        Bug Fixes
                    </h3>
                    <ul class="space-y-2 ml-6">
                        <li class="text-gray-700">
                            🐛 Fixed rate limit counter not resetting properly for some SMS providers
                        </li>
                        <li class="text-gray-700">
                            🐛 Fixed webhook URL validation allowing invalid URLs
                        </li>
                        <li class="text-gray-700">
                            🐛 Fixed WaSender session QR code not refreshing on reconnect
                        </li>
                        <li class="text-gray-700">
                            🐛 Fixed notification status not updating after provider webhook callback
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Version 1.0 -->
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">v1.0</h2>
                    <p class="text-sm text-gray-600 mt-1">
                        <i class="far fa-calendar mr-2"></i>
                        January 15, 2026
                    </p>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Initial Release -->
                <div>
                    <h3 class="font-semibold text-gray-900 mb-3 flex items-center">
                        <i class="fas fa-rocket text-purple-600 mr-2"></i>
                        Initial Release
                    </h3>
                    <ul class="space-y-2 ml-6">
                        <li class="text-gray-700">
                            🎉 <strong>Email Support</strong> — SendGrid, Resend, and Mailgun integration
                        </li>
                        <li class="text-gray-700">
                            🎉 <strong>SMS Support</strong> — Twilio, Beem, and Termii integration
                        </li>
                        <li class="text-gray-700">
                            🎉 <strong>WhatsApp Support</strong> — Official WhatsApp Business API integration
                        </li>
                        <li class="text-gray-700">
                            🎉 <strong>Multi-tenant Architecture</strong> — Schema-based tenant isolation
                        </li>
                        <li class="text-gray-700">
                            🎉 <strong>Webhook Callbacks</strong> — Real-time delivery status updates
                        </li>
                        <li class="text-gray-700">
                            🎉 <strong>RESTful API</strong> — Clean, predictable API design
                        </li>
                        <li class="text-gray-700">
                            🎉 <strong>API Key Authentication</strong> — Simple and secure authentication
                        </li>
                        <li class="text-gray-700">
                            🎉 <strong>Session Management</strong> — SMS and WhatsApp session configuration
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Upcoming -->
        <div class="bg-gradient-to-r from-blue-50 to-purple-50 rounded-lg border-2 border-dashed border-blue-300 p-6">
            <div class="flex items-center mb-4">
                <i class="fas fa-calendar-alt text-blue-600 text-2xl mr-3"></i>
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Coming Soon</h2>
                    <p class="text-sm text-gray-600 mt-1">Planned features for future releases</p>
                </div>
            </div>

            <div class="space-y-3">
                <div class="flex items-start">
                    <i class="fas fa-circle text-blue-400 text-xs mr-3 mt-1"></i>
                    <div>
                        <h4 class="font-semibold text-gray-900">Scheduled Notifications</h4>
                        <p class="text-sm text-gray-600">Send notifications at a specific future time</p>
                    </div>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-circle text-blue-400 text-xs mr-3 mt-1"></i>
                    <div>
                        <h4 class="font-semibold text-gray-900">Template Management</h4>
                        <p class="text-sm text-gray-600">Create and manage reusable notification templates</p>
                    </div>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-circle text-blue-400 text-xs mr-3 mt-1"></i>
                    <div>
                        <h4 class="font-semibold text-gray-900">Analytics Dashboard</h4>
                        <p class="text-sm text-gray-600">Detailed analytics and delivery insights</p>
                    </div>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-circle text-blue-400 text-xs mr-3 mt-1"></i>
                    <div>
                        <h4 class="font-semibold text-gray-900">Multiple API Key Support</h4>
                        <p class="text-sm text-gray-600">Create and manage multiple API keys with different permissions</p>
                    </div>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-circle text-blue-400 text-xs mr-3 mt-1"></i>
                    <div>
                        <h4 class="font-semibold text-gray-900">Slack & Discord Integration</h4>
                        <p class="text-sm text-gray-600">Send notifications to team collaboration platforms</p>
                    </div>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-circle text-blue-400 text-xs mr-3 mt-1"></i>
                    <div>
                        <h4 class="font-semibold text-gray-900">GraphQL API</h4>
                        <p class="text-sm text-gray-600">Alternative GraphQL endpoint for advanced queries</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Subscribe to Updates -->
    <div class="mt-12 bg-white rounded-lg border border-gray-200 p-8 text-center">
        <h3 class="text-xl font-bold text-gray-900 mb-3">
            <i class="fas fa-bell text-blue-600 mr-2"></i>
            Stay Updated
        </h3>
        <p class="text-gray-600 mb-6">
            Subscribe to be notified about new features, improvements, and breaking changes.
        </p>
        <div class="flex justify-center gap-3 max-w-md mx-auto">
            <input type="email" placeholder="your@email.com" class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            <button class="px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition whitespace-nowrap">
                Subscribe
            </button>
        </div>
        <p class="text-xs text-gray-500 mt-3">
            We respect your privacy. Unsubscribe at any time.
        </p>
    </div>
</div>
@endsection
