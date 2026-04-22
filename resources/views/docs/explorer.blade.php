@extends('docs.layout')

@section('extra-css')
<style>
    .swagger-ui {
        font-family: inherit !important;
    }
</style>
@endsection

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="mb-8">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">
            <i class="fas fa-play-circle text-blue-600 mr-3"></i>
            API Explorer
        </h1>
        <p class="text-lg text-gray-600">
            Interactive API testing with Swagger UI. Test endpoints directly from your browser with your API key.
        </p>
    </div>

    <!-- API Key Configuration -->
    <div class="bg-blue-50 border-l-4 border-blue-600 p-6 mb-8 rounded-r-lg">
        <div class="flex items-start">
            <i class="fas fa-key text-blue-600 text-2xl mr-4 mt-1"></i>
            <div class="flex-1">
                <h3 class="font-semibold text-blue-900 mb-3">Configure Your API Key</h3>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">API Key (minimum 32 characters)</label>
                    <div class="flex gap-2">
                        <input type="text" id="api-key-input" placeholder="Enter your API key here..." class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button id="save-api-key" class="px-6 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition">
                            <i class="fas fa-save mr-2"></i>
                            Save
                        </button>
                        <button id="clear-api-key" class="px-6 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition">
                            <i class="fas fa-times mr-2"></i>
                            Clear
                        </button>
                    </div>
                    <p class="text-sm text-gray-600 mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Your API key is stored locally in your browser and never sent to our servers.
                        <span id="api-key-status" class="ml-2 font-semibold"></span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Swagger UI Container -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div id="swagger-ui"></div>
    </div>

    <!-- Help Section -->
    <div class="mt-8 bg-gray-50 border border-gray-200 rounded-lg p-6">
        <h3 class="font-semibold text-gray-900 mb-4flex items-center">
            <i class="fas fa-question-circle text-gray-600 mr-2"></i>
            How to Use the API Explorer
        </h3>
        <div class="grid md:grid-cols-3 gap-6 text-sm">
            <div>
                <div class="flex items-center mb-2">
                    <span class="w-6 h-6 bg-blue-600 text-white rounded-full flex items-center justify-center text-xs font-bold mr-2">1</span>
                    <span class="font-semibold">Enter API Key</span>
                </div>
                <p class="text-gray-600 ml-8">Enter your API key above and click "Save" to authorize all requests.</p>
            </div>
            <div>
                <div class="flex items-center mb-2">
                    <span class="w-6 h-6 bg-blue-600 text-white rounded-full flex items-center justify-center text-xs font-bold mr-2">2</span>
                    <span class="font-semibold">Select Endpoint</span>
                </div>
                <p class="text-gray-600 ml-8">Click on any endpoint below to expand it and see parameters.</p>
            </div>
            <div>
                <div class="flex items-center mb-2">
                    <span class="w-6 h-6 bg-blue-600 text-white rounded-full flex items-center justify-center text-xs font-bold mr-2">3</span>
                    <span class="font-semibold">Try It Out</span>
                </div>
                <p class="text-gray-600 ml-8">Click "Try it out", fill in parameters, and execute to see live responses.</p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('extra-js')
<!-- Swagger UI -->
<link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.10.0/swagger-ui.css" />
<script src="https://unpkg.com/swagger-ui-dist@5.10.0/swagger-ui-bundle.js"></script>
<script src="https://unpkg.com/swagger-ui-dist@5.10.0/swagger-ui-standalone-preset.js"></script>

<script>
$(document).ready(function() {
    // Load saved API key
    const savedApiKey = localStorage.getItem('api_key');
    if (savedApiKey) {
        $('#api-key-input').val(savedApiKey);
        updateApiKeyStatus('Saved');
    }

    // Save API key
    $('#save-api-key').click(function() {
        const apiKey = $('#api-key-input').val().trim();
        
        if (apiKey.length < 32) {
            showNotification('Invalid API Key', 'API key must be at least 32 characters long', 'error');
            return;
        }

        localStorage.setItem('api_key', apiKey);
        updateApiKeyStatus('Saved');
        showNotification('API Key Saved', 'Your API key has been saved successfully', 'success');
        
        // Reinitialize Swagger UI with new API key
        initSwaggerUI();
    });

    // Clear API key
    $('#clear-api-key').click(function() {
        localStorage.removeItem('api_key');
        $('#api-key-input').val('');
        updateApiKeyStatus('Not Set');
        showNotification('API Key Cleared', 'Your API key has been removed', 'info');
    });

    function updateApiKeyStatus(status) {
        const statuses = {
            'Saved': '<span class="text-green-600"><i class="fas fa-check-circle mr-1"></i>API Key Saved</span>',
            'Not Set': '<span class="text-red-600"><i class="fas fa-exclamation-circle mr-1"></i>API Key Not Set</span>'
        };
        $('#api-key-status').html(statuses[status] || '');
    }

    // Initialize Swagger UI
    function initSwaggerUI() {
        const apiKey = localStorage.getItem('api_key') || '';

        const ui = SwaggerUIBundle({
            url: "{{ route('docs.openapi') }}",
            dom_id: '#swagger-ui',
            deepLinking: true,
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIStandalonePreset
            ],
            plugins: [
                SwaggerUIBundle.plugins.DownloadUrl
            ],
            layout: "StandaloneLayout",
            requestInterceptor: (request) => {
                // Add API key to all requests
                if (apiKey) {
                    request.headers['X-API-Key'] = apiKey;
                }

                // Add sandbox mode header if enabled
                const sandboxMode = localStorage.getItem('sandbox_mode') === 'true';
                if (sandboxMode) {
                    request.headers['X-Sandbox-Mode'] = 'true';
                }

                return request;
            }
        });

        window.ui = ui;
    }

    // Initialize on page load
    initSwaggerUI();
});
</script>
@endsection
