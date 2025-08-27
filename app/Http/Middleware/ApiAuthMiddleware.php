<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip authentication for health check endpoints
        if ($request->is('health') || $request->is('health/*')) {
            return $next($request);
        }

        // Check for API key in various headers
        $apiKey = $this->extractApiKey($request);
        
        if (!$apiKey) {
            return $this->unauthorizedResponse('API key required. Please provide an API key in X-API-Key, Authorization, or X-Auth-Token header.');
        }

        // Validate API key format
        if (!$this->isValidApiKeyFormat($apiKey)) {
            return $this->unauthorizedResponse('Invalid API key format. API key must be at least 32 characters long.');
        }

        // Store API key for use in controllers
        $request->attributes->set('api_key', $apiKey);
        $request->headers->set('X-API-Key', $apiKey);

        // Add API key to response headers for debugging (in non-production)
        $response = $next($request);
        
        if (!app()->environment('production')) {
            $response->headers->set('X-Debug-API-Key-Length', strlen($apiKey));
            $response->headers->set('X-Debug-API-Key-Format', 'valid');
        }

        return $response;
    }

    /**
     * Extract API key from request headers
     */
    protected function extractApiKey(Request $request): ?string
    {
        // Try different header variations
        $headers = [
            'X-API-Key',
            'X-Api-Key', 
            'X-AUTH-TOKEN',
            'X-Auth-Token',
            'Authorization'
        ];

        foreach ($headers as $header) {
            $value = $request->header($header);
            if ($value) {
                // Remove Bearer prefix if present
                return str_replace(['Bearer ', 'bearer '], '', $value);
            }
        }

        // Also check query parameter as fallback (not recommended for production)
        return $request->query('api_key');
    }

    /**
     * Validate API key format
     */
    protected function isValidApiKeyFormat(string $apiKey): bool
    {
        // Basic validation: at least 32 characters, alphanumeric with allowed special chars
        if (strlen($apiKey) < 32) {
            return false;
        }

        // Allow alphanumeric characters, hyphens, underscores, and dots
        return preg_match('/^[a-zA-Z0-9\-_.]+$/', $apiKey);
    }

    /**
     * Return unauthorized response
     */
    protected function unauthorizedResponse(string $message): Response
    {
        return response()->json([
            'error' => 'Unauthorized',
            'message' => $message,
            'code' => 401,
            'timestamp' => now()->toISOString()
        ], 401);
    }
}
