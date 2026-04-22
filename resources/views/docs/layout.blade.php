<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Notification Service API Documentation - Multi-channel notification service supporting Email, SMS, and WhatsApp">
    <title>{{ $title ?? 'API Documentation' }} | Notification Service</title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Prism.js for syntax highlighting -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-json.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-python.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-bash.min.js"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #3B82F6;
            --primary-dark: #2563EB;
            --secondary-color: #10B981;
            --dark-bg: #1F2937;
            --darker-bg: #111827;
            --light-gray: #F3F4F6;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: var(--darker-bg);
            color: white;
            overflow-y: auto;
            z-index: 40;
            transition: transform 0.3s;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #4B5563;
            border-radius: 3px;
        }

        .main-content {
            margin-left: 280px;
            min-height: 100vh;
        }

        .nav-item {
            transition: all 0.2s;
        }

        .nav-item:hover {
            background: rgba(59, 130, 246, 0.1);
            border-left: 3px solid var(--primary-color);
        }

        .nav-item.active {
            background: rgba(59, 130, 246, 0.2);
            border-left: 3px solid var(--primary-color);
        }

        .code-block {
            position: relative;
        }

        .copy-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 4px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
        }

        .copy-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .search-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 50;
            align-items: flex-start;
            justify-content: center;
            padding-top: 100px;
        }

        .search-modal.active {
            display: flex;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        /* HTTP Method Badges */
        .badge-get { background: #10B981; color: white; }
        .badge-post { background: #3B82F6; color: white; }
        .badge-put { background: #F59E0B; color: white; }
        .badge-patch { background: #8B5CF6; color: white; }
        .badge-delete { background: #EF4444; color: white; }
        
        /* Status Badges */
        .badge-success { background: #D1FAE5; color: #065F46; }
        .badge-warning { background: #FEF3C7; color: #92400E; }
        .badge-error { background: #FEE2E2; color: #991B1B; }
        .badge-info { background: #DBEAFE; color: #1E40AF; }
        
        /* Requirement Badges */
        .badge-required { background: #FEE2E2; color: #991B1B; }
        .badge-optional { background: #E5E7EB; color: #4B5563; }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.mobile-open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }
        }

        /* Markdown styling */
        .markdown-content h1 {
            font-size: 2.25rem;
            font-weight: 700;
            margin-top: 2rem;
            margin-bottom: 1rem;
            color: #111827;
        }

        .markdown-content h2 {
            font-size: 1.875rem;
            font-weight: 600;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
            color: #1F2937;
            border-bottom: 2px solid #E5E7EB;
            padding-bottom: 0.5rem;
        }

        .markdown-content h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-top: 1.25rem;
            margin-bottom: 0.5rem;
            color: #374151;
        }

        .markdown-content code {
            background: #F3F4F6;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.875em;
            color: #EF4444;
            font-family: 'Courier New', monospace;
        }

        .markdown-content pre code {
            background: transparent;
            padding: 0;
            color: inherit;
        }

        .markdown-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }

        .markdown-content th,
        .markdown-content td {
            border: 1px solid #E5E7EB;
            padding: 0.75rem;
            text-align: left;
        }

        .markdown-content th {
            background: #F9FAFB;
            font-weight: 600;
        }

        .markdown-content blockquote {
            border-left: 4px solid var(--primary-color);
            padding-left: 1rem;
            margin: 1rem 0;
            color: #6B7280;
            font-style: italic;
        }
    </style>

    @yield('extra-css')
</head>
<body class="bg-gray-50">
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="p-6 border-b border-gray-700">
            <h1 class="text-2xl font-bold text-white flex items-center">
                <i class="fas fa-bell mr-3 text-blue-400"></i>
                Notification API
            </h1>
            <p class="text-sm text-gray-400 mt-1">v1.1</p>
        </div>

        <nav class="p-4">
            <div class="space-y-1">
                <a href="{{ route('docs.index') }}" class="nav-item block px-4 py-2.5 rounded text-sm {{ request()->routeIs('docs.index') ? 'active' : '' }}">
                    <i class="fas fa-home mr-2"></i> Home
                </a>
                
                <a href="{{ route('docs.getting-started') }}" class="nav-item block px-4 py-2.5 rounded text-sm {{ request()->routeIs('docs.getting-started') ? 'active' : '' }}">
                    <i class="fas fa-rocket mr-2"></i> Getting Started
                </a>
                
                <a href="{{ route('docs.reference') }}" class="nav-item block px-4 py-2.5 rounded text-sm {{ request()->routeIs('docs.reference') ? 'active' : '' }}">
                    <i class="fas fa-book mr-2"></i> API Reference
                </a>
                
                <a href="{{ route('docs.explorer') }}" class="nav-item block px-4 py-2.5 rounded text-sm {{ request()->routeIs('docs.explorer') ? 'active' : '' }}">
                    <i class="fas fa-play-circle mr-2"></i> API Explorer
                </a>

                <a href="{{ route('docs.code-examples') }}" class="nav-item block px-4 py-2.5 rounded text-sm {{ request()->routeIs('docs.code-examples') ? 'active' : '' }}">
                    <i class="fas fa-code mr-2"></i> Code Examples
                </a>

                <a href="{{ route('docs.webhooks') }}" class="nav-item block px-4 py-2.5 rounded text-sm {{ request()->routeIs('docs.webhooks') ? 'active' : '' }}">
                    <i class="fas fa-webhook mr-2"></i> Webhook Tester
                </a>

                <div class="mt-6 mb-2 px-4 text-xs font-semibold text-gray-500 uppercase">
                    Guides
                </div>

                <a href="{{ route('docs.guides', 'authentication') }}" class="nav-item block px-4 py-2.5 rounded text-sm">
                    <i class="fas fa-key mr-2"></i> Authentication
                </a>

                <a href="{{ route('docs.guides', 'sending-notifications') }}" class="nav-item block px-4 py-2.5 rounded text-sm">
                    <i class="fas fa-paper-plane mr-2"></i> Sending Notifications
                </a>

                <a href="{{ route('docs.guides', 'bulk-operations') }}" class="nav-item block px-4 py-2.5 rounded text-sm">
                    <i class="fas fa-layer-group mr-2"></i> Bulk Operations
                </a>

                <a href="{{ route('docs.guides', 'error-handling') }}" class="nav-item block px-4 py-2.5 rounded text-sm">
                    <i class="fas fa-exclamation-triangle mr-2"></i> Error Handling
                </a>

                <a href="{{ route('docs.guides', 'rate-limits') }}" class="nav-item block px-4 py-2.5 rounded text-sm">
                    <i class="fas fa-tachometer-alt mr-2"></i> Rate Limits
                </a>

                <div class="mt-6 mb-2 px-4 text-xs font-semibold text-gray-500 uppercase">
                    Resources
                </div>

                <a href="{{ route('docs.changelog') }}" class="nav-item block px-4 py-2.5 rounded text-sm {{ request()->routeIs('docs.changelog') ? 'active' : '' }}">
                    <i class="fas fa-history mr-2"></i> Changelog
                </a>

                <a href="{{ route('docs.status') }}" class="nav-item block px-4 py-2.5 rounded text-sm {{ request()->routeIs('docs.status') ? 'active' : '' }}">
                    <i class="fas fa-heartbeat mr-2"></i> API Status
                </a>

                <a href="{{ route('docs.postman') }}" class="nav-item block px-4 py-2.5 rounded text-sm">
                    <i class="fas fa-download mr-2"></i> Postman Collection
                </a>

                <a href="{{ route('docs.openapi') }}" class="nav-item block px-4 py-2.5 rounded text-sm" target="_blank">
                    <i class="fas fa-file-code mr-2"></i> OpenAPI Spec
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
            <div class="flex items-center justify-between px-6 py-4">
                <div class="flex items-center space-x-4">
                    <button class="md:hidden text-gray-600" id="mobile-menu-btn">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    
                    <button class="hidden md:flex items-center space-x-2 px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200 transition" id="search-btn">
                        <i class="fas fa-search text-gray-500"></i>
                        <span class="text-sm text-gray-600">Search documentation...</span>
                        <kbd class="ml-8 px-2 py-1 text-xs bg-white border border-gray-300 rounded">⌘K</kbd>
                    </button>
                </div>

                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-600">Sandbox Mode:</span>
                        <label class="relative inline-block w-12 h-6">
                            <input type="checkbox" id="sandbox-toggle" class="sr-only peer">
                            <div class="w-12 h-6 bg-gray-300 rounded-full peer peer-checked:bg-green-500 transition cursor-pointer"></div>
                            <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition peer-checked:translate-x-6"></div>
                        </label>
                    </div>

                    <a href="https://github.com" target="_blank" class="text-gray-600 hover:text-gray-900">
                        <i class="fab fa-github text-xl"></i>
                    </a>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <div class="container mx-auto px-6 py-8">
            @yield('content')
        </div>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 mt-16">
            <div class="container mx-auto px-6 py-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-3">Documentation</h3>
                        <ul class="space-y-2 text-sm text-gray-600">
                            <li><a href="{{ route('docs.getting-started') }}" class="hover:text-blue-600">Getting Started</a></li>
                            <li><a href="{{ route('docs.reference') }}" class="hover:text-blue-600">API Reference</a></li>
                            <li><a href="{{ route('docs.guides') }}" class="hover:text-blue-600">Guides</a></li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-3">Tools</h3>
                        <ul class="space-y-2 text-sm text-gray-600">
                            <li><a href="{{ route('docs.explorer') }}" class="hover:text-blue-600">API Explorer</a></li>
                            <li><a href="{{ route('docs.webhooks') }}" class="hover:text-blue-600">Webhook Tester</a></li>
                            <li><a href="{{ route('docs.postman') }}" class="hover:text-blue-600">Postman Collection</a></li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-3">Resources</h3>
                        <ul class="space-y-2 text-sm text-gray-600">
                            <li><a href="{{ route('docs.status') }}" class="hover:text-blue-600">API Status</a></li>
                            <li><a href="{{ route('docs.changelog') }}" class="hover:text-blue-600">Changelog</a></li>
                            <li><a href="{{ route('docs.openapi') }}" class="hover:text-blue-600" target="_blank">OpenAPI Spec</a></li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-3">Support</h3>
                        <ul class="space-y-2 text-sm text-gray-600">
                            <li><a href="mailto:support@example.com" class="hover:text-blue-600">Contact Support</a></li>
                            <li><a href="#" class="hover:text-blue-600">Report Issue</a></li>
                        </ul>
                    </div>
                </div>
                <div class="mt-8 pt-8 border-t border-gray-200 text-center text-sm text-gray-600">
                    <p>&copy; {{ date('Y') }} Notification Service API. All rights reserved.</p>
                </div>
            </div>
        </footer>
    </main>

    <!-- Search Modal -->
    <div class="search-modal" id="search-modal">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-2xl mx-4">
            <div class="p-4 border-b">
                <input type="text" id="search-input" placeholder="Search documentation..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="p-4 max-h-96 overflow-y-auto" id="search-results">
                <p class="text-gray-500 text-center py-8">Type to search...</p>
            </div>
        </div>
    </div>

    <!-- Global JavaScript -->
    <script>
        $(document).ready(function() {
            // Mobile menu toggle
            $('#mobile-menu-btn').click(function() {
                $('#sidebar').toggleClass('mobile-open');
            });

            // Search modal toggle
            $('#search-btn').click(function() {
                $('#search-modal').addClass('active');
                $('#search-input').focus();
            });

            // Close search modal
            $('#search-modal').click(function(e) {
                if (e.target === this) {
                    $(this).removeClass('active');
                }
            });

            // Keyboard shortcut for search (Cmd+K or Ctrl+K)
            $(document).keydown(function(e) {
                if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                    e.preventDefault();
                    $('#search-modal').addClass('active');
                    $('#search-input').focus();
                }

                // ESC to close search
                if (e.key === 'Escape') {
                    $('#search-modal').removeClass('active');
                }
            });

            // Sandbox mode toggle
            $('#sandbox-toggle').change(function() {
                const isEnabled = $(this).is(':checked');
                localStorage.setItem('sandbox_mode', isEnabled);
                
                if (isEnabled) {
                    showNotification('Sandbox Mode Enabled', 'API calls will use mock data', 'success');
                } else {
                    showNotification('Sandbox Mode Disabled', 'API calls will use live data', 'info');
                }
            });

            // Load sandbox mode state
            const sandboxMode = localStorage.getItem('sandbox_mode') === 'true';
            $('#sandbox-toggle').prop('checked', sandboxMode);

            // Copy code to clipboard
            $(document).on('click', '.copy-btn', function() {
                const code = $(this).siblings('code').text();
                navigator.clipboard.writeText(code).then(() => {
                    const btn = $(this);
                    const originalText = btn.html();
                    btn.html('<i class="fas fa-check mr-1"></i> Copied!');
                    setTimeout(() => {
                        btn.html(originalText);
                    }, 2000);
                });
            });

            // Add copy buttons to code blocks
            $('pre').each(function() {
                $(this).addClass('code-block');
                $(this).prepend('<button class="copy-btn"><i class="fas fa-copy mr-1"></i> Copy</button>');
            });
        });

        // Show notification helper
        function showNotification(title, message, type = 'info') {
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-yellow-500',
                info: 'bg-blue-500'
            };

            const notification = $(`
                <div class="fixed bottom-4 right-4 ${colors[type]} text-white px-6 py-4 rounded-lg shadow-lg z-50 max-w-md animate-slide-in">
                    <div class="font-semibold">${title}</div>
                    <div class="text-sm mt-1">${message}</div>
                </div>
            `);

            $('body').append(notification);

            setTimeout(() => {
                notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }

        // API request helper with sandbox mode support
        function makeApiRequest(endpoint, options = {}) {
            const sandboxMode = localStorage.getItem('sandbox_mode') === 'true';
            
            if (sandboxMode) {
                // Return mock response
                return Promise.resolve(getMockResponse(endpoint, options.method || 'GET'));
            }

            // Make real API request
            return $.ajax({
                url: endpoint,
                ...options
            });
        }

        // Mock responses for sandbox mode
        function getMockResponse(endpoint, method) {
            return {
                success: true,
                message: 'Sandbox mode - Mock response',
                data: {
                    id: 999,
                    status: 'sent',
                    message: 'This is a sandbox response'
                }
            };
        }
    </script>

    @yield('extra-js')
</body>
</html>
