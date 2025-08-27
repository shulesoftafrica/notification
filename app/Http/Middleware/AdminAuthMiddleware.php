<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin Authentication Middleware
 * Validates admin session tokens for dashboard access
 */
class AdminAuthMiddleware
{
    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get token from various sources
        $token = $this->getTokenFromRequest($request);

        if (!$token) {
            return $this->unauthorizedResponse('No authentication token provided');
        }

        // Validate session
        $sessionKey = "admin_session:{$token}";
        $session = Cache::get($sessionKey);

        if (!$session) {
            return $this->unauthorizedResponse('Invalid or expired authentication token');
        }

        // Check IP if IP validation is enabled
        if (config('notification.admin.validate_ip', true)) {
            if ($session['ip'] !== $request->ip()) {
                \Log::warning('Admin session IP mismatch', [
                    'email' => $session['email'],
                    'session_ip' => $session['ip'],
                    'request_ip' => $request->ip(),
                    'token' => substr($token, 0, 8) . '...'
                ]);

                return $this->unauthorizedResponse('IP address mismatch');
            }
        }

        // Update last activity
        $session['last_activity'] = now()->toISOString();
        Cache::put($sessionKey, $session, 8 * 60 * 60); // Extend session

        // Add session info to request
        $request->attributes->set('admin_session', $session);
        $request->attributes->set('admin_email', $session['email']);

        return $next($request);
    }

    /**
     * Get token from request
     */
    private function getTokenFromRequest(Request $request): ?string
    {
        // Try Bearer token first
        $bearerToken = $request->bearerToken();
        if ($bearerToken) {
            return $bearerToken;
        }

        // Try Authorization header
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Token ')) {
            return substr($authHeader, 6);
        }

        // Try query parameter
        $queryToken = $request->query('token');
        if ($queryToken) {
            return $queryToken;
        }

        // Try POST body
        $bodyToken = $request->input('token');
        if ($bodyToken) {
            return $bodyToken;
        }

        // Try cookie
        $cookieToken = $request->cookie('admin_token');
        if ($cookieToken) {
            return $cookieToken;
        }

        return null;
    }

    /**
     * Return unauthorized response
     */
    private function unauthorizedResponse(string $message): Response
    {
        if (request()->expectsJson() || request()->is('api/*')) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => $message
            ], 401);
        }

        // For web requests, redirect to login
        return redirect()->route('admin.login')->with('error', $message);
    }
}
