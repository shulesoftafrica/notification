<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Service - Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full space-y-8">
        <div>
            <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-blue-100">
                <i class="fas fa-bell text-blue-600 text-xl"></i>
            </div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Admin Dashboard
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Notification Service Administration
            </p>
        </div>
        
        <div class="bg-white py-8 px-6 shadow rounded-lg">
            <form id="loginForm" class="space-y-6">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">
                        Email Address
                    </label>
                    <div class="mt-1 relative">
                        <input id="email" name="email" type="email" required
                               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                               placeholder="admin@example.com">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">
                        Password
                    </label>
                    <div class="mt-1 relative">
                        <input id="password" name="password" type="password" required
                               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                               placeholder="Password">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                    </div>
                </div>

                <div id="errorMessage" class="hidden bg-red-50 border border-red-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700" id="errorText"></p>
                        </div>
                    </div>
                </div>

                <div>
                    <button type="submit" id="loginButton"
                            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <i class="fas fa-sign-in-alt text-blue-500 group-hover:text-blue-400"></i>
                        </span>
                        <span id="buttonText">Sign In</span>
                        <span id="loadingSpinner" class="hidden">
                            <i class="fas fa-spinner fa-spin ml-2"></i>
                        </span>
                    </button>
                </div>
            </form>
        </div>
        
        <div class="text-center">
            <p class="text-xs text-gray-500">
                Notification Service v2.0 - Admin Portal
            </p>
        </div>
    </div>

    <script>
        const API_BASE = window.location.origin;
        
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const button = document.getElementById('loginButton');
            const buttonText = document.getElementById('buttonText');
            const loadingSpinner = document.getElementById('loadingSpinner');
            const errorMessage = document.getElementById('errorMessage');
            
            // Show loading state
            button.disabled = true;
            buttonText.textContent = 'Signing In...';
            loadingSpinner.classList.remove('hidden');
            errorMessage.classList.add('hidden');
            
            try {
                const response = await fetch(`${API_BASE}/api/admin/auth/login`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ email, password })
                });
                
                const data = await response.json();
                
                if (response.ok && data.token) {
                    // Store token
                    localStorage.setItem('admin_token', data.token);
                    
                    // Set cookie for 8 hours
                    const expires = new Date(Date.now() + 8 * 60 * 60 * 1000);
                    document.cookie = `admin_token=${data.token}; expires=${expires.toUTCString()}; path=/; SameSite=Strict`;
                    
                    // Redirect to dashboard
                    window.location.href = '/admin/dashboard';
                } else {
                    // Show error
                    document.getElementById('errorText').textContent = data.error || 'Login failed';
                    errorMessage.classList.remove('hidden');
                }
            } catch (error) {
                console.error('Login error:', error);
                document.getElementById('errorText').textContent = 'Network error. Please try again.';
                errorMessage.classList.remove('hidden');
            } finally {
                // Reset button state
                button.disabled = false;
                buttonText.textContent = 'Sign In';
                loadingSpinner.classList.add('hidden');
            }
        });

        // Check if already logged in
        window.addEventListener('load', function() {
            const token = localStorage.getItem('admin_token');
            if (token) {
                // Verify token is still valid
                fetch(`${API_BASE}/api/admin/auth/me`, {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    if (response.ok) {
                        window.location.href = '/admin/dashboard';
                    } else {
                        // Remove invalid token
                        localStorage.removeItem('admin_token');
                        document.cookie = 'admin_token=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
                    }
                })
                .catch(error => console.log('Token validation failed:', error));
            }
        });
    </script>
</body>
</html>
