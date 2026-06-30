@extends('docs.layout')

@section('content')
<div class="max-w-5xl mx-auto">
    <h1 class="text-4xl font-bold text-gray-900 mb-4">
        <i class="fas fa-signal text-green-600 mr-3"></i>
        Service Status
    </h1>
    <p class="text-lg text-gray-600 mb-8">
        Real-time status of our notification providers and API infrastructure.
    </p>

    <!-- Overall Status -->
    <div class="bg-gradient-to-r from-green-50 to-green-100 rounded-lg border border-green-200 p-6 mb-8">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-4 h-4 bg-green-500 rounded-full animate-pulse mr-4"></div>
                <div>
                    <h2 class="text-2xl font-bold text-green-900">All Systems Operational</h2>
                    <p class="text-green-700 mt-1">Last checked: <span id="last-check-time">Just now</span></p>
                </div>
            </div>
            <button id="refresh-status" class="px-4 py-2 bg-white text-green-600 border border-green-300 rounded-lg hover:bg-green-50 transition font-semibold">
                <i class="fas fa-sync-alt mr-2"></i>
                Refresh
            </button>
        </div>
    </div>

    <!-- Provider Status Cards -->
    <div class="space-y-6">
        <!-- Email Providers -->
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-envelope text-blue-600 mr-3"></i>
                Email Providers
            </h3>
            <div class="space-y-3">
                <div class="provider-status flex items-center justify-between p-4 rounded-lg bg-gray-50 hover:bg-gray-100 transition">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-4"></div>
                        <div>
                            <h4 class="font-semibold text-gray-900">SendGrid</h4>
                            <p class="text-sm text-gray-600">Email delivery via SendGrid API</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="badge badge-success">Operational</span>
                        <p class="text-xs text-gray-500 mt-1">Uptime: 99.98%</p>
                    </div>
                </div>

                <div class="provider-status flex items-center justify-between p-4 rounded-lg bg-gray-50 hover:bg-gray-100 transition">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-4"></div>
                        <div>
                            <h4 class="font-semibold text-gray-900">Resend</h4>
                            <p class="text-sm text-gray-600">Modern email API for developers</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="badge badge-success">Operational</span>
                        <p class="text-xs text-gray-500 mt-1">Uptime: 99.95%</p>
                    </div>
                </div>

                <div class="provider-status flex items-center justify-between p-4 rounded-lg bg-gray-50 hover:bg-gray-100 transition">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-4"></div>
                        <div>
                            <h4 class="font-semibold text-gray-900">Mailgun</h4>
                            <p class="text-sm text-gray-600">Powerful email delivery service</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="badge badge-success">Operational</span>
                        <p class="text-xs text-gray-500 mt-1">Uptime: 99.92%</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- SMS Providers -->
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-sms text-purple-600 mr-3"></i>
                SMS Providers
            </h3>
            <div class="space-y-3">
                <div class="provider-status flex items-center justify-between p-4 rounded-lg bg-gray-50 hover:bg-gray-100 transition">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-4"></div>
                        <div>
                            <h4 class="font-semibold text-gray-900">Twilio</h4>
                            <p class="text-sm text-gray-600">Global SMS delivery network</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="badge badge-success">Operational</span>
                        <p class="text-xs text-gray-500 mt-1">Uptime: 99.99%</p>
                    </div>
                </div>

                <div class="provider-status flex items-center justify-between p-4 rounded-lg bg-gray-50 hover:bg-gray-100 transition">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-4"></div>
                        <div>
                            <h4 class="font-semibold text-gray-900">Beem Africa</h4>
                            <p class="text-sm text-gray-600">SMS delivery across Africa</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="badge badge-success">Operational</span>
                        <p class="text-xs text-gray-500 mt-1">Uptime: 99.85%</p>
                    </div>
                </div>

                <div class="provider-status flex items-center justify-between p-4 rounded-lg bg-gray-50 hover:bg-gray-100 transition">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-4"></div>
                        <div>
                            <h4 class="font-semibold text-gray-900">Termii</h4>
                            <p class="text-sm text-gray-600">African SMS and voice API</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="badge badge-success">Operational</span>
                        <p class="text-xs text-gray-500 mt-1">Uptime: 99.80%</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- WhatsApp Providers -->
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fab fa-whatsapp text-green-600 mr-3"></i>
                WhatsApp Providers
            </h3>
            <div class="space-y-3">
                <div class="provider-status flex items-center justify-between p-4 rounded-lg bg-gray-50 hover:bg-gray-100 transition">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-4"></div>
                        <div>
                            <h4 class="font-semibold text-gray-900">WhatsApp Business API</h4>
                            <p class="text-sm text-gray-600">Official WhatsApp messaging platform</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="badge badge-success">Operational</span>
                        <p class="text-xs text-gray-500 mt-1">Uptime: 99.97%</p>
                    </div>
                </div>

                <div class="provider-status flex items-center justify-between p-4 rounded-lg bg-gray-50 hover:bg-gray-100 transition">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-4"></div>
                        <div>
                            <h4 class="font-semibold text-gray-900">WaSender</h4>
                            <p class="text-sm text-gray-600">Alternative WhatsApp integration</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="badge badge-success">Operational</span>
                        <p class="text-xs text-gray-500 mt-1">Uptime: 99.75%</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Infrastructure -->
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-server text-gray-600 mr-3"></i>
                Infrastructure
            </h3>
            <div class="space-y-3">
                <div class="provider-status flex items-center justify-between p-4 rounded-lg bg-gray-50 hover:bg-gray-100 transition">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-4"></div>
                        <div>
                            <h4 class="font-semibold text-gray-900">API Gateway</h4>
                            <p class="text-sm text-gray-600">Main API endpoint</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="badge badge-success">Operational</span>
                        <p class="text-xs text-gray-500 mt-1">Response: 45ms</p>
                    </div>
                </div>

                <div class="provider-status flex items-center justify-between p-4 rounded-lg bg-gray-50 hover:bg-gray-100 transition">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-4"></div>
                        <div>
                            <h4 class="font-semibold text-gray-900">Database</h4>
                            <p class="text-sm text-gray-600">Primary data storage</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="badge badge-success">Operational</span>
                        <p class="text-xs text-gray-500 mt-1">Query: 12ms</p>
                    </div>
                </div>

                <div class="provider-status flex items-center justify-between p-4 rounded-lg bg-gray-50 hover:bg-gray-100 transition">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-4"></div>
                        <div>
                            <h4 class="font-semibold text-gray-900">Cache (Redis)</h4>
                            <p class="text-sm text-gray-600">High-speed data cache</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="badge badge-success">Operational</span>
                        <p class="text-xs text-gray-500 mt-1">Response: 3ms</p>
                    </div>
                </div>

                <div class="provider-status flex items-center justify-between p-4 rounded-lg bg-gray-50 hover:bg-gray-100 transition">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-4"></div>
                        <div>
                            <h4 class="font-semibold text-gray-900">Webhook Delivery</h4>
                            <p class="text-sm text-gray-600">Outbound webhook queue</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="badge badge-success">Operational</span>
                        <p class="text-xs text-gray-500 mt-1">Queue: 0 pending</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Metrics -->
    <div class="mt-8 grid md:grid-cols-3 gap-6">
        <div class="bg-white rounded-lg border border-gray-200 p-6 text-center">
            <i class="fas fa-clock text-blue-600 text-3xl mb-3"></i>
            <h4 class="font-semibold text-gray-900 mb-2">Average Response Time</h4>
            <p class="text-3xl font-bold text-blue-600">48ms</p>
            <p class="text-sm text-gray-600 mt-2">Across all endpoints</p>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 p-6 text-center">
            <i class="fas fa-check-circle text-green-600 text-3xl mb-3"></i>
            <h4 class="font-semibold text-gray-900 mb-2">Success Rate</h4>
            <p class="text-3xl font-bold text-green-600">99.94%</p>
            <p class="text-sm text-gray-600 mt-2">Last 30 days</p>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 p-6 text-center">
            <i class="fas fa-chart-line text-purple-600 text-3xl mb-3"></i>
            <h4 class="font-semibold text-gray-900 mb-2">Uptime</h4>
            <p class="text-3xl font-bold text-purple-600">99.98%</p>
            <p class="text-sm text-gray-600 mt-2">Last 90 days</p>
        </div>
    </div>

    <!-- Incident History -->
    <div class="mt-12">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">
            <i class="fas fa-history text-gray-600 mr-3"></i>
            Recent Incidents
        </h2>
        <div class="bg-white rounded-lg border border-gray-200 p-8 text-center text-gray-500">
            <i class="fas fa-check-circle text-green-500 text-5xl mb-4"></i>
            <p class="text-lg font-medium">No incidents in the last 30 days</p>
            <p class="text-sm mt-2">Our services have been running smoothly</p>
        </div>
    </div>

    <!-- Subscribe to Updates -->
    <div class="mt-12 bg-blue-50 rounded-lg border border-blue-200 p-8">
        <h3 class="text-xl font-bold text-gray-900 mb-3 flex items-center">
            <i class="fas fa-bell text-blue-600 mr-2"></i>
            Get Status Updates
        </h3>
        <p class="text-gray-600 mb-6">
            Subscribe to receive notifications about service incidents and scheduled maintenance.
        </p>
        <div class="flex gap-3 max-w-md">
            <input type="email" placeholder="your@email.com" class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            <button class="px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition whitespace-nowrap">
                Subscribe
            </button>
        </div>
    </div>
</div>
@endsection

@section('extra-js')
<script>
$(document).ready(function() {
    // Update timestamp
    function updateTimestamp() {
        const now = new Date();
        $('#last-check-time').text(now.toLocaleTimeString());
    }

    // Refresh status
    $('#refresh-status').click(function() {
        const btn = $(this);
        const icon = btn.find('i');
        
        // Animate refresh
        icon.addClass('fa-spin');
        btn.prop('disabled', true);
        
        // Simulate API call
        setTimeout(function() {
            updateTimestamp();
            icon.removeClass('fa-spin');
            btn.prop('disabled', false);
            
            // Show success notification
            showNotification('Status Updated', 'All services are operational', 'success');
        }, 1000);
    });

    // Auto-refresh every 60 seconds
    setInterval(function() {
        updateTimestamp();
    }, 60000);
});
</script>
@endsection
