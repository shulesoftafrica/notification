<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

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

        // Extract Bearer token from Authorization header
        $token = $this->extractBearerToken($request);
        
        if (!$token) {
            return $this->unauthorizedResponse('Bearer token required. Please provide a Bearer token in the Authorization header.');
        }

        // Validate token against API_KEY from .env
        if (!$this->validateToken($token)) {
            Log::warning('Invalid API token attempt', [
                'token_prefix' => substr($token, 0, 8) . '...',
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'endpoint' => $request->path()
            ]);
            
            return $this->unauthorizedResponse('Invalid API token.');
        }

        // Log successful authentication
        Log::info('API request authenticated', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'endpoint' => $request->path(),
            'method' => $request->method()
        ]);

        // Store token info for controllers
        $request->attributes->set('api_token', $token);
        $request->attributes->set('authenticated', true);

        return $next($request);
    }

    /**
     * Extract Bearer token from Authorization header
     */
    protected function extractBearerToken(Request $request): ?string
    {
        $authorization = $request->header('Authorization');
        
        if (!$authorization) {
            return null;
        }

        // Check if it starts with 'Bearer '
        if (!str_starts_with($authorization, 'Bearer ') && !str_starts_with($authorization, 'bearer ')) {
            return null;
        }

        // Extract the token part
        $token = substr($authorization, 7); // Remove 'Bearer ' prefix
        
        return !empty($token) ? $token : null;
    }

    /**
     * Validate token against API_KEY from environment
     */
    protected function validateToken(string $token): bool
    {
        $validApiKey = config('app.api_key') ?? env('API_KEY');
        
        if (empty($validApiKey)) {
            Log::error('API_KEY not configured in environment variables');
            return false;
        }

        return hash_equals($validApiKey, $token);
    }

    /**
     * Return unauthorized response
     */
    protected function unauthorizedResponse(string $message): Response
    {
        return response()->json([
            'error' => 'Unauthorized',
            'message' => $message,
            'status_code' => 401
        ], 401, [
            'Content-Type' => 'application/json',
            'WWW-Authenticate' => 'Bearer'
        ]);
    }
}
