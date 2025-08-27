<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Notification Service</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <i class="fas fa-bell text-blue-600 text-2xl mr-3"></i>
                        <h1 class="text-xl font-semibold text-gray-900">Notification Service</h1>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-500" id="currentUser">Loading...</span>
                    <button onclick="logout()" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Status Indicators -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-heartbeat text-green-500 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">System Status</dt>
                                <dd class="text-lg font-medium text-gray-900" id="systemStatus">Checking...</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-envelope text-blue-500 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Messages Today</dt>
                                <dd class="text-lg font-medium text-gray-900" id="messagesToday">-</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-percentage text-green-500 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Success Rate</dt>
                                <dd class="text-lg font-medium text-gray-900" id="successRate">-</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-list text-orange-500 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Queue Depth</dt>
                                <dd class="text-lg font-medium text-gray-900" id="queueDepth">-</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Provider Health -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                    <i class="fas fa-server mr-2"></i>Provider Health
                </h3>
                <div id="providerHealth" class="space-y-3">
                    <div class="text-gray-500">Loading provider status...</div>
                </div>
            </div>
        </div>

        <!-- Recent Messages -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                    <i class="fas fa-clock mr-2"></i>Recent Messages
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Channel</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">To</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provider</th>
                            </tr>
                        </thead>
                        <tbody id="recentMessages" class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">Loading recent messages...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = window.location.origin;
        let dashboardData = {};

        // Authentication check
        function checkAuth() {
            const token = localStorage.getItem('admin_token');
            if (!token) {
                window.location.href = '/admin/login';
                return false;
            }
            return token;
        }

        // Logout function
        async function logout() {
            const token = checkAuth();
            if (token) {
                try {
                    await fetch(`${API_BASE}/api/admin/auth/logout`, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Content-Type': 'application/json'
                        }
                    });
                } catch (error) {
                    console.error('Logout error:', error);
                }
            }
            
            localStorage.removeItem('admin_token');
            document.cookie = 'admin_token=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
            window.location.href = '/admin/login';
        }

        // API call helper
        async function apiCall(endpoint) {
            const token = checkAuth();
            if (!token) return null;

            try {
                const response = await fetch(`${API_BASE}/api/admin${endpoint}`, {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                });

                if (response.status === 401) {
                    logout();
                    return null;
                }

                return await response.json();
            } catch (error) {
                console.error(`API call failed for ${endpoint}:`, error);
                return null;
            }
        }

        // Load current user info
        async function loadUserInfo() {
            const data = await apiCall('/auth/me');
            if (data) {
                document.getElementById('currentUser').textContent = data.email;
            }
        }

        // Load dashboard overview
        async function loadOverview() {
            const data = await apiCall('/dashboard/overview');
            if (data) {
                dashboardData.overview = data;
                updateOverviewDisplay(data);
            }
        }

        // Load provider health
        async function loadProviderHealth() {
            const data = await apiCall('/dashboard/provider-health');
            if (data) {
                updateProviderHealth(data);
            }
        }

        // Load recent messages
        async function loadRecentMessages() {
            const data = await apiCall('/dashboard/recent-messages');
            if (data && data.messages) {
                updateRecentMessages(data.messages);
            }
        }

        // Update overview display
        function updateOverviewDisplay(data) {
            const status = data.overall_health || 'unknown';
            document.getElementById('systemStatus').textContent = status.toUpperCase();
            document.getElementById('systemStatus').className = `text-lg font-medium ${getStatusColor(status)}`;

            document.getElementById('messagesToday').textContent = (data.messages_today || 0).toLocaleString();
            document.getElementById('successRate').textContent = `${(data.success_rate || 0).toFixed(1)}%`;
            document.getElementById('queueDepth').textContent = (data.queue_depth || 0).toLocaleString();
        }

        // Update provider health
        function updateProviderHealth(data) {
            const container = document.getElementById('providerHealth');
            if (!data.providers || Object.keys(data.providers).length === 0) {
                container.innerHTML = '<div class="text-gray-500">No provider data available</div>';
                return;
            }

            const html = Object.entries(data.providers).map(([provider, health]) => `
                <div class="flex items-center justify-between p-3 border rounded-lg">
                    <div class="flex items-center">
                        <span class="w-3 h-3 rounded-full ${getStatusDot(health.status)} mr-3"></span>
                        <span class="font-medium">${provider}</span>
                    </div>
                    <div class="text-sm text-gray-500">
                        ${health.status} - ${health.success_rate_percent || 0}% success
                    </div>
                </div>
            `).join('');

            container.innerHTML = html;
        }

        // Update recent messages
        function updateRecentMessages(messages) {
            const tbody = document.getElementById('recentMessages');
            
            if (!messages || messages.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No recent messages</td></tr>';
                return;
            }

            const html = messages.map(message => `
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${new Date(message.created_at).toLocaleString()}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            ${message.channel}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${message.recipient || 'N/A'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusBadge(message.status)}">
                            ${message.status}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${message.provider || 'N/A'}
                    </td>
                </tr>
            `).join('');

            tbody.innerHTML = html;
        }

        // Helper functions
        function getStatusColor(status) {
            switch (status?.toLowerCase()) {
                case 'healthy': return 'text-green-600';
                case 'degraded': return 'text-yellow-600';
                case 'unhealthy': return 'text-red-600';
                default: return 'text-gray-600';
            }
        }

        function getStatusDot(status) {
            switch (status?.toLowerCase()) {
                case 'healthy': return 'bg-green-500';
                case 'degraded': return 'bg-yellow-500';
                case 'unhealthy': return 'bg-red-500';
                default: return 'bg-gray-500';
            }
        }

        function getStatusBadge(status) {
            switch (status?.toLowerCase()) {
                case 'delivered': return 'bg-green-100 text-green-800';
                case 'sent': return 'bg-blue-100 text-blue-800';
                case 'failed': return 'bg-red-100 text-red-800';
                case 'pending': return 'bg-yellow-100 text-yellow-800';
                default: return 'bg-gray-100 text-gray-800';
            }
        }

        // Initialize dashboard
        async function initDashboard() {
            await loadUserInfo();
            await loadOverview();
            await loadProviderHealth();
            await loadRecentMessages();
        }

        // Auto-refresh dashboard
        function startAutoRefresh() {
            setInterval(async () => {
                await loadOverview();
                await loadProviderHealth();
                await loadRecentMessages();
            }, 30000); // Refresh every 30 seconds
        }

        // Initialize on page load
        window.addEventListener('load', function() {
            if (!checkAuth()) return;
            
            initDashboard();
            startAutoRefresh();
        });
    </script>
</body>
</html>
