<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiLoggingMiddleware
{
    /**
     * Handle an incoming request and log API activity
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $requestId = uniqid('req_');
        
        // Log incoming request
        $this->logRequest($request, $requestId);
        
        // Process the request
        $response = $next($request);
        
        // Calculate response time
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        
        // Log response
        $this->logResponse($request, $response, $requestId, $responseTime);
        
        return $response;
    }

    /**
     * Log incoming request details
     */
    protected function logRequest(Request $request, string $requestId): void
    {
        $logData = [
            'request_id' => $requestId,
            'timestamp' => now()->toISOString(),
            'method' => $request->getMethod(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $this->sanitizeHeaders($request->headers->all()),
            'query_params' => $request->query->all(),
            'body' => $this->sanitizeBody($request->all()),
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
        ];

        Log::channel('api')->info('API_REQUEST', $logData);
    }

    /**
     * Log response details
     */
    protected function logResponse(Request $request, Response $response, string $requestId, float $responseTime): void
    {
        $logData = [
            'request_id' => $requestId,
            'timestamp' => now()->toISOString(),
            'method' => $request->getMethod(),
            'path' => $request->path(),
            'status_code' => $response->getStatusCode(),
            'response_time_ms' => $responseTime,
            'content_type' => $response->headers->get('Content-Type'),
            'content_length' => strlen($response->getContent()),
        ];

        // Add response body for debugging (only for non-2xx status codes or if debug is enabled)
        if ($response->getStatusCode() >= 400 || config('app.debug')) {
            $content = $response->getContent();
            if ($this->isJson($content)) {
                $logData['response_body'] = json_decode($content, true);
            } else {
                $logData['response_body'] = substr($content, 0, 1000); // Truncate large responses
            }
        }

        $logLevel = $this->getLogLevel($response->getStatusCode());
        Log::channel('api')->log($logLevel, 'API_RESPONSE', $logData);
    }

    /**
     * Sanitize headers to remove sensitive information
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'x-api-key',
            'cookie',
            'x-csrf-token',
            'x-signature',
        ];

        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = ['*** REDACTED ***'];
            }
        }

        return $headers;
    }

    /**
     * Sanitize request body to remove sensitive information
     */
    protected function sanitizeBody(array $body): array
    {
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'token',
            'secret',
            'api_key',
            'auth_token',
            'access_token',
            'refresh_token',
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($body[$field])) {
                $body[$field] = '*** REDACTED ***';
            }
        }

        return $body;
    }

    /**
     * Determine if content is JSON
     */
    protected function isJson(string $content): bool
    {
        json_decode($content);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Get appropriate log level based on status code
     */
    protected function getLogLevel(int $statusCode): string
    {
        if ($statusCode >= 500) {
            return 'error';
        } elseif ($statusCode >= 400) {
            return 'warning';
        } else {
            return 'info';
        }
    }
}
