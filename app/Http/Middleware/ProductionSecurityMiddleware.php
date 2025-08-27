<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Production Security Middleware
 * Implements comprehensive security measures for production deployment
 */
class ProductionSecurityMiddleware
{
    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. IP Whitelist for admin endpoints
        if ($this->isAdminEndpoint($request)) {
            $this->validateAdminAccess($request);
        }

        // 2. Request size validation
        $this->validateRequestSize($request);

        // 3. Security headers validation
        $this->validateSecurityHeaders($request);

        // 4. Content type validation
        $this->validateContentType($request);

        // 5. User agent validation (prevent bot abuse)
        $this->validateUserAgent($request);

        $response = $next($request);

        // Add security headers to response
        return $this->addSecurityHeaders($response);
    }

    /**
     * Check if request is for admin endpoint
     */
    private function isAdminEndpoint(Request $request): bool
    {
        return str_starts_with($request->path(), 'api/admin');
    }

    /**
     * Validate admin access based on IP whitelist
     */
    private function validateAdminAccess(Request $request): void
    {
        $allowedIps = config('notification.admin.allowed_ips', []);
        $clientIp = $request->ip();

        if (!empty($allowedIps) && !in_array($clientIp, $allowedIps)) {
            Log::warning('Unauthorized admin access attempt', [
                'ip' => $clientIp,
                'path' => $request->path(),
                'user_agent' => $request->userAgent()
            ]);

            abort(403, 'Access denied from this IP address');
        }
    }

    /**
     * Validate request size to prevent DoS attacks
     */
    private function validateRequestSize(Request $request): void
    {
        $maxSize = config('notification.security.max_request_size', 10 * 1024 * 1024); // 10MB
        $contentLength = $request->header('Content-Length', 0);

        if ($contentLength > $maxSize) {
            Log::warning('Request size exceeded limit', [
                'size' => $contentLength,
                'limit' => $maxSize,
                'ip' => $request->ip()
            ]);

            abort(413, 'Request entity too large');
        }
    }

    /**
     * Validate security headers
     */
    private function validateSecurityHeaders(Request $request): void
    {
        // Require HTTPS in production
        if (app()->environment('production') && !$request->secure()) {
            abort(426, 'HTTPS required');
        }

        // Validate required headers for API requests
        if ($request->is('api/*')) {
            $requiredHeaders = ['X-API-Key', 'X-Signature', 'X-Timestamp'];
            
            foreach ($requiredHeaders as $header) {
                if (!$request->hasHeader($header)) {
                    Log::warning('Missing required header', [
                        'header' => $header,
                        'ip' => $request->ip(),
                        'path' => $request->path()
                    ]);
                }
            }
        }
    }

    /**
     * Validate content type for API requests
     */
    private function validateContentType(Request $request): void
    {
        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH')) {
            $contentType = $request->header('Content-Type');
            
            if (!str_contains($contentType, 'application/json')) {
                abort(415, 'Unsupported media type. Expected application/json');
            }
        }
    }

    /**
     * Validate user agent to prevent bot abuse
     */
    private function validateUserAgent(Request $request): void
    {
        $userAgent = $request->userAgent();
        $blockedAgents = config('notification.security.blocked_user_agents', [
            'curl', 'wget', 'python-requests', 'bot', 'crawler', 'spider'
        ]);

        foreach ($blockedAgents as $blockedAgent) {
            if (stripos($userAgent, $blockedAgent) !== false) {
                Log::info('Blocked user agent', [
                    'user_agent' => $userAgent,
                    'ip' => $request->ip(),
                    'path' => $request->path()
                ]);

                abort(403, 'Access denied');
            }
        }
    }

    /**
     * Add security headers to response
     */
    private function addSecurityHeaders(Response $response): Response
    {
        $securityHeaders = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'; frame-ancestors 'none';",
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'X-Powered-By' => 'Notification Service v2.0'
        ];

        foreach ($securityHeaders as $header => $value) {
            $response->headers->set($header, $value);
        }

        return $response;
    }
}
