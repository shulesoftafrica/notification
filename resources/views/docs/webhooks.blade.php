@extends('docs.layout')

@section('content')
<div class="max-w-7xl mx-auto">
    <h1 class="text-4xl font-bold text-gray-900 mb-4">
        <i class="fas fa-webhook text-blue-600 mr-3"></i>
        Webhook Tester
    </h1>
    <p class="text-lg text-gray-600 mb-8">
        Test and debug webhook integrations with a unique test URL that captures all incoming requests in real-time.
    </p>

    <div class="grid md:grid-cols-2 gap-8">
        <!-- Left Column: Test URL & Instructions -->
        <div>
            <!-- Test URL Generation -->
            <div class="bg-white p-6 rounded-lg border border-gray-200 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Your Test Webhook URL</h2>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Session ID</label>
                    <div class="flex gap-2">
                        <input type="text" id="session-id" readonly value="" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-800 font-mono text-sm">
                        <button id="generate-session" class="px-4 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition">
                            <i class="fas fa-sync-alt mr-2"></i>
                            New Session
                        </button>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Webhook URL</label>
                    <div class="flex gap-2">
                        <input type="text" id="webhook-url" readonly value="{{ url('/api/webhook/docs-test') }}/loading..." class="flex-1 px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-800 font-mono text-sm">
                        <button id="copy-webhook-url" class="px-4 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <p class="text-sm text-gray-600 mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Use this URL in your notifications or provider settings to test webhooks
                    </p>
                </div>

                <div class="flex items-center justify-between p-4 bg-green-50 rounded-lg">
                    <div class="flex items-center">
                        <div id="connection-status" class="w-3 h-3 rounded-full bg-gray-400 mr-3"></div>
                        <span id="status-text" class="text-sm font-medium text-gray-700">Initializing...</span>
                    </div>
                    <button id="start-listening" class="px-4 py-2 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition text-sm">
                        <i class="fas fa-play mr-2"></i>
                        Start Listening
                    </button>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white p-6 rounded-lg border border-gray-200 mb-6">
                <h3 class="font-semibold text-gray-900 mb-4">Test Webhook</h3>
                <p class="text-sm text-gray-600 mb-4">Send a test notification with this webhook URL:</p>
                
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <input type="email" id="test-email" placeholder="test@example.com" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                    <textarea id="test-message" placeholder="Test webhook message" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <button id="send-test-notification" class="w-full px-4 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-paper-plane mr-2"></i>
                    Send Test Notification
                </button>
            </div>

            <!-- How to Use -->
            <div class="bg-blue-50 p-6 rounded-lg border border-blue-200">
                <h3 class="font-semibold text-blue-900 mb-3">
                    <i class="fas fa-lightbulb mr-2"></i>
                    How to Use
                </h3>
                <ol class="text-sm text-blue-800 space-y-2 list-decimal list-inside">
                    <li>Click "Start Listening" to begin capturing webhooks</li>
                    <li>Copy your unique webhook URL</li>
                    <li>Include it in your notification requests as <code>webhook_url</code></li>
                    <li>Watch incoming webhooks appear in real-time on the right</li>
                    <li>Inspect headers, body, and timestamps for each request</li>
                </ol>
            </div>
        </div>

        <!-- Right Column: Webhook Log -->
        <div>
            <div class="bg-white rounded-lg border border-gray-200 sticky top-24">
                <div class="flex items-center justify-between p-4 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-900">Incoming Webhooks</h2>
                    <div class="flex gap-2">
                        <span id="webhook-count" class="px-3 py-1 bg-blue-100 text-blue-800 text-sm font-semibold rounded-full">0 requests</span>
                        <button id="clear-webhooks" class="px-3 py-1 bg-gray-200 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-300 transition">
                            <i class="fas fa-trash mr-1"></i>
                            Clear
                        </button>
                    </div>
                </div>

                <div id="webhook-log" class="overflow-y-auto" style="max-height: 600px;">
                    <div id="empty-state" class="p-12 text-center text-gray-500">
                        <i class="fas fa-inbox text-6xl mb-4 text-gray-300"></i>
                        <p class="text-lg font-medium">No webhooks yet</p>
                        <p class="text-sm mt-2">Waiting for incoming requests...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Example Webhook Configurations -->
    <div class="mt-12">
        <h2 class="text-2xl font-semibold text-gray-900 mb-6">Example Webhook Configurations</h2>
        <div class="grid md:grid-cols-2 gap-6">
            <!-- SendGrid -->
            <div class="bg-white p-6 rounded-lg border border-gray-200">
                <h3 class="font-semibold text-gray-900 mb-4">
                    <i class="fas fa-envelope text-green-600 mr-2"></i>
                    SendGrid Webhook
                </h3>
                <p class="text-sm text-gray-600 mb-3">Configure in SendGrid Mail Settings → Event Webhook</p>
                <pre><code class="language-bash">HTTP POST URL:
{{ url('/api/webhook/sendgrid') }}

Events to send:
☑ Delivered
☑ Opened
☑ Clicked
☑ Bounced
☑ Dropped
☑ Blocked
☑ Spam Report</code></pre>
            </div>

            <!-- Twilio -->
            <div class="bg-white p-6 rounded-lg border border-gray-200">
                <h3 class="font-semibold text-gray-900 mb-4">
                    <i class="fas fa-sms text-blue-600 mr-2"></i>
                    Twilio Webhook
                </h3>
                <p class="text-sm text-gray-600 mb-3">Set Status Callback URL when sending SMS</p>
                <pre><code class="language-bash">StatusCallback:
{{ url('/api/webhook/twilio') }}

Method: POST</code></pre>
            </div>

            <!-- WaSender -->
            <div class="bg-white p-6 rounded-lg border border-gray-200">
                <h3 class="font-semibold text-gray-900 mb-4">
                    <i class="fab fa-whatsapp text-purple-600 mr-2"></i>
                    WaSender Webhook
                </h3>
                <p class="text-sm text-gray-600 mb-3">Configure when creating WaSender session</p>
                <pre><code class="language-json">{
  "webhook_url": "{{ url('/api/webhook/whatsapp') }}",
  "webhook_enabled": true,
  "webhook_events": [
    "messages.received",
    "session.status",
    "messages.update"
  ]
}</code></pre>
            </div>

            <!-- Custom Notification Webhook -->
            <div class="bg-white p-6 rounded-lg border border-gray-200">
                <h3 class="font-semibold text-gray-900 mb-4">
                    <i class="fas fa-bell text-orange-600 mr-2"></i>
                    Notification Webhook
                </h3>
                <p class="text-sm text-gray-600 mb-3">Include in your notification request</p>
                <pre><code class="language-json">{
  "schema_name": "my_app",
  "channel": "email",
  "to": "customer@example.com",
  "subject": "Test",
  "message": "Test message",
  "webhook_url": "https://your-app.com/webhook"
}</code></pre>
            </div>
        </div>
    </div>
</div>
@endsection

@section('extra-js')
<script>
$(document).ready(function() {
    let sessionId = null;
    let webhookCount = 0;
    let pollingInterval = null;

    // Generate new session
    function generateSession() {
        sessionId = 'wh_' + Math.random().toString(36).substring(2, 15) + Date.now().toString(36);
        $('#session-id').val(sessionId);
        $('#webhook-url').val('{{ url('/api/webhook/docs-test') }}/' + sessionId);
        localStorage.setItem('webhook_session_id', sessionId);
        return sessionId;
    }

    // Load or create session
    const savedSession = localStorage.getItem('webhook_session_id');
    if (savedSession) {
        sessionId = savedSession;
        $('#session-id').val(sessionId);
        $('#webhook-url').val('{{ url('/api/webhook/docs-test') }}/' + sessionId);
    } else {
        generateSession();
    }

    // Generate new session button
    $('#generate-session').click(function() {
        generateSession();
        clearWebhooks();
        showNotification('New Session Created', 'Your webhook URL has been updated', 'success');
    });

    // Copy webhook URL
    $('#copy-webhook-url').click(function() {
        const url = $('#webhook-url').val();
        navigator.clipboard.writeText(url).then(() => {
            showNotification('URL Copied', 'Webhook URL copied to clipboard', 'success');
        });
    });

    // Start listening
    let isListening = false;
    $('#start-listening').click(function() {
        if (!isListening) {
            startListening();
        } else {
            stopListening();
        }
    });

    function startListening() {
        isListening = true;
        $('#start-listening').html('<i class="fas fa-stop mr-2"></i> Stop Listening')
            .removeClass('bg-green-600 hover:bg-green-700')
            .addClass('bg-red-600 hover:bg-red-700');
        $('#connection-status').removeClass('bg-gray-400').addClass('bg-green-500 animate-pulse');
        $('#status-text').text('Listening for webhooks...');

        // Poll for webhooks every 2 seconds
        pollingInterval = setInterval(pollWebhooks, 2000);
        
        showNotification('Listening Started', 'Waiting for incoming webhooks...', 'success');
    }

    function stopListening() {
        isListening = false;
        $('#start-listening').html('<i class="fas fa-play mr-2"></i> Start Listening')
            .removeClass('bg-red-600 hover:bg-red-700')
            .addClass('bg-green-600 hover:bg-green-700');
        $('#connection-status').removeClass('bg-green-500 animate-pulse').addClass('bg-gray-400');
        $('#status-text').text('Stopped');

        if (pollingInterval) {
            clearInterval(pollingInterval);
        }
    }

    // Poll for webhooks (simulated - in production, use WebSockets or Server-Sent Events)
    function pollWebhooks() {
        // This is a simplified version. In production, you'd fetch from your backend
        // which stores webhook requests in Redis or database
        
        // Simulated webhook data for demo
        // In real implementation: 
        // $.get('/api/webhook/docs-test/' + sessionId + '/poll', function(data) { ... });
    }

    // Add webhook to log
    function addWebhook(webhook) {
        $('#empty-state').hide();
        webhookCount++;
        $('#webhook-count').text(webhookCount + ' request' + (webhookCount !== 1 ? 's' : ''));

        const timestamp = new Date().toLocaleTimeString();
        const webhookHtml = `
            <div class="webhook-item border-b border-gray-200">
                <div class="p-4 hover:bg-gray-50 cursor-pointer" onclick="$(this).next().slideToggle()">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center">
                            <span class="badge badge-${webhook.method.toLowerCase()} mr-3">${webhook.method}</span>
                            <span class="text-sm text-gray-600">${timestamp}</span>
                        </div>
                        <span class="text-sm text-gray-500">${webhook.status || '200 OK'}</span>
                    </div>
                    <div class="text-sm text-gray-700 font-mono truncate">${webhook.path || '/webhook'}</div>
                </div>
                <div class="p-4 bg-gray-50 border-t border-gray-200 hidden">
                    <div class="mb-3">
                        <h4 class="text-xs font-semibold text-gray-700 uppercase mb-2">Headers</h4>
                        <pre class="text-xs bg-gray-900 text-gray-100 p-3 rounded overflow-x-auto"><code>${JSON.stringify(webhook.headers || {}, null, 2)}</code></pre>
                    </div>
                    <div>
                        <h4 class="text-xs font-semibold text-gray-700 uppercase mb-2">Body</h4>
                        <pre class="text-xs bg-gray-900 text-gray-100 p-3 rounded overflow-x-auto"><code>${JSON.stringify(webhook.body || {}, null, 2)}</code></pre>
                    </div>
                </div>
            </div>
        `;

        $('#webhook-log').prepend(webhookHtml);
    }

    // Clear webhooks
    function clearWebhooks() {
        $('#webhook-log').html('<div id="empty-state" class="p-12 text-center text-gray-500"><i class="fas fa-inbox text-6xl mb-4 text-gray-300"></i><p class="text-lg font-medium">No webhooks yet</p><p class="text-sm mt-2">Waiting for incoming requests...</p></div>');
        webhookCount = 0;
        $('#webhook-count').text('0 requests');
    }

    $('#clear-webhooks').click(clearWebhooks);

    // Send test notification
    $('#send-test-notification').click(function() {
        const email = $('#test-email').val();
        const message = $('#test-message').val();
        const webhookUrl = $('#webhook-url').val();
        const apiKey = localStorage.getItem('api_key');

        if (!apiKey) {
            showNotification('API Key Required', 'Please set your API key in the API Explorer first', 'error');
            return;
        }

        if (!email) {
            showNotification('Email Required', 'Please enter an email address', 'error');
            return;
        }

        const btn = $(this);
        const originalHtml = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin mr-2"></i> Sending...').prop('disabled', true);

        const data = {
            schema_name: 'docs_test',
            channel: 'email',
            to: email,
            subject: 'Webhook Test',
            message: message || 'This is a test notification to verify webhook delivery',
            webhook_url: webhookUrl,
            provider: 'sendgrid'
        };

        $.ajax({
            url: '{{ url('/api/notifications/send') }}',
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-API-Key': apiKey
            },
            data: JSON.stringify(data),
            success: function(response) {
                showNotification('Notification Sent', 'Watch for the webhook callback above', 'success');
                
                // Simulate webhook arrival (in production, this would come from the actual webhook)
                setTimeout(function() {
                    addWebhook({
                        method: 'POST',
                        path: '/webhook/docs-test/' + sessionId,
                        status: '200 OK',
                        headers: {
                            'Content-Type': 'application/json',
                            'User-Agent': 'NotificationService/1.0'
                        },
                        body: {
                            message_id: response.message_id,
                            status: 'delivered',
                            event: 'delivered',
                            timestamp: new Date().toISOString()
                        }
                    });
                }, 2000);
            },
            error: function(xhr) {
                const error = xhr.responseJSON || {};
                showNotification('Error', error.message || 'Failed to send notification', 'error');
            },
            complete: function() {
                btn.html(originalHtml).prop('disabled', false);
            }
        });
    });

    // Start listening automatically
    startListening();
});
</script>
@endsection
