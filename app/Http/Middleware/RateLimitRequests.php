<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

class RateLimitRequests extends ThrottleRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  int|string  $maxAttempts
     * @param  float|int  $decayMinutes
     * @param  string  $prefix
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = '')
    {
        // Custom rate limiting logic for the notification service
        $key = $this->resolveRequestSignature($request);
        
        // Apply different limits based on endpoint type
        if ($request->is('api/notifications/*')) {
            $maxAttempts = 1000; // Higher limit for notification endpoints
            $decayMinutes = 1;
        } elseif ($request->is('api/webhooks/*')) {
            $maxAttempts = 10000; // Very high limit for webhooks
            $decayMinutes = 1;
        } elseif ($request->is('admin/*')) {
            $maxAttempts = 100; // Moderate limit for admin endpoints
            $decayMinutes = 1;
        } elseif ($request->is('health/*')) {
            $maxAttempts = 300; // High limit for health checks
            $decayMinutes = 1;
        }

        return parent::handle($request, $next, $maxAttempts, $decayMinutes, $prefix);
    }

    /**
     * Resolve the request signature for rate limiting.
     */
    protected function resolveRequestSignature($request)
    {
        // Use API key if available for more accurate rate limiting
        $apiKey = $request->header('X-API-Key') ?? $request->header('Authorization');
        
        if ($apiKey) {
            // Rate limit by API key for authenticated requests
            $apiKey = str_replace('Bearer ', '', $apiKey);
            return sha1($apiKey . '|' . $request->server('SERVER_NAME'));
        }

        // Fall back to IP-based rate limiting
        return parent::resolveRequestSignature($request);
    }

    /**
     * Create a 'too many attempts' response.
     */
    protected function buildResponse($key, $maxAttempts)
    {
        $response = response()->json([
            'error' => 'Too Many Requests',
            'message' => 'Rate limit exceeded. Please try again later.',
            'retry_after' => $this->getTimeUntilNextRetry($key),
            'limit' => $maxAttempts,
            'remaining' => 0
        ], 429);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            0,
            $this->getTimeUntilNextRetry($key)
        );
    }

    /**
     * Add rate limit headers to the response.
     */
    protected function addHeaders(Response $response, $maxAttempts, $remainingAttempts, $retryAfter = null)
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remainingAttempts),
        ]);

        if (!is_null($retryAfter)) {
            $response->headers->add([
                'Retry-After' => $retryAfter,
                'X-RateLimit-Reset' => $this->availableAt($retryAfter),
            ]);
        }

        return $response;
    }
}
