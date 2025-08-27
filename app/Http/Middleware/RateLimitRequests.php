<?php

namespace App\Http\Middleware;

use App\Services\RateLimitService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class RateLimitRequests
{
    private RateLimitService $rateLimitService;

    public function __construct(RateLimitService $rateLimitService)
    {
        $this->rateLimitService = $rateLimitService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $type = 'api'): SymfonyResponse
    {
        // Skip rate limiting for health checks and webhooks
        if ($this->shouldSkipRateLimit($request)) {
            return $next($request);
        }

        // Check global service limits first
        if (!$this->rateLimitService->checkGlobalLimits()) {
            return $this->buildRateLimitResponse('Global rate limit exceeded');
        }

        // Get project and tenant from request
        $project = $request->get('authenticated_project');
        $tenantId = $request->header('X-Tenant-ID');

        if (!$project) {
            // For unauthenticated requests, use IP-based limiting
            return $this->handleIpRateLimit($request, $next);
        }

        // Check project-specific rate limits
        $limits = $this->rateLimitService->getRateLimits($project->project_id, $tenantId);
        $identifier = $this->buildIdentifier($project->project_id, $tenantId, $type);

        if (!$this->rateLimitService->isAllowed($identifier, $limits)) {
            return $this->buildRateLimitResponse(
                'Rate limit exceeded',
                $this->rateLimitService->getRemainingRequests($identifier, $limits),
                $this->rateLimitService->getResetTimes($identifier)
            );
        }

        // Process the request
        $response = $next($request);

        // Increment counters after successful request
        $this->rateLimitService->hit($identifier, $limits);
        $this->rateLimitService->hitGlobalLimits();

        // Add rate limit headers to response
        $this->addRateLimitHeaders($response, $identifier, $limits);

        return $response;
    }

    /**
     * Handle IP-based rate limiting for unauthenticated requests
     */
    private function handleIpRateLimit(Request $request, Closure $next): SymfonyResponse
    {
        $ip = $request->ip();
        $identifier = "ip:{$ip}";
        $limits = [
            'minute' => 60,   // 60 requests per minute
            'hour' => 1000,   // 1000 requests per hour
            'day' => 10000,   // 10000 requests per day
        ];

        if (!$this->rateLimitService->isAllowed($identifier, $limits)) {
            return $this->buildRateLimitResponse('IP rate limit exceeded');
        }

        $response = $next($request);
        $this->rateLimitService->hit($identifier, $limits);

        return $response;
    }

    /**
     * Build rate limit identifier
     */
    private function buildIdentifier(string $projectId, ?string $tenantId, string $type): string
    {
        $identifier = "project:{$projectId}";
        
        if ($tenantId) {
            $identifier .= ":tenant:{$tenantId}";
        }
        
        if ($type !== 'api') {
            $identifier .= ":type:{$type}";
        }

        return $identifier;
    }

    /**
     * Check if rate limiting should be skipped
     */
    private function shouldSkipRateLimit(Request $request): bool
    {
        $skipPaths = [
            '/health',
            '/metrics',
            '/v1/receipts',  // Provider webhooks
        ];

        $path = $request->getPathInfo();
        
        foreach ($skipPaths as $skipPath) {
            if (str_starts_with($path, $skipPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build rate limit exceeded response
     */
    private function buildRateLimitResponse(
        string $message,
        array $remaining = [],
        array $resetTimes = []
    ): Response {
        $data = [
            'error' => [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'message' => $message,
                'details' => [
                    'retry_after' => $this->getRetryAfter($resetTimes),
                ],
            ]
        ];

        if (!empty($remaining)) {
            $data['error']['details']['remaining'] = $remaining;
        }

        if (!empty($resetTimes)) {
            $data['error']['details']['reset_times'] = $resetTimes;
        }

        $response = response()->json($data, 429);

        // Add standard rate limit headers
        if (!empty($resetTimes)) {
            $response->header('Retry-After', $this->getRetryAfter($resetTimes));
            $response->header('X-RateLimit-Reset-Minute', $resetTimes['minute'] ?? time() + 60);
        }

        return $response;
    }

    /**
     * Add rate limit headers to successful response
     */
    private function addRateLimitHeaders(SymfonyResponse $response, string $identifier, array $limits): void
    {
        $remaining = $this->rateLimitService->getRemainingRequests($identifier, $limits);
        $resetTimes = $this->rateLimitService->getResetTimes($identifier);

        // Add headers
        $response->headers->set('X-RateLimit-Limit-Minute', $limits['minute'] ?? 0);
        $response->headers->set('X-RateLimit-Limit-Hour', $limits['hour'] ?? 0);
        $response->headers->set('X-RateLimit-Limit-Day', $limits['day'] ?? 0);

        $response->headers->set('X-RateLimit-Remaining-Minute', $remaining['minute'] ?? 0);
        $response->headers->set('X-RateLimit-Remaining-Hour', $remaining['hour'] ?? 0);
        $response->headers->set('X-RateLimit-Remaining-Day', $remaining['day'] ?? 0);

        if (!empty($resetTimes)) {
            $response->headers->set('X-RateLimit-Reset-Minute', $resetTimes['minute'] ?? time() + 60);
            $response->headers->set('X-RateLimit-Reset-Hour', $resetTimes['hour'] ?? time() + 3600);
            $response->headers->set('X-RateLimit-Reset-Day', $resetTimes['day'] ?? time() + 86400);
        }
    }

    /**
     * Get retry after seconds
     */
    private function getRetryAfter(array $resetTimes): int
    {
        if (empty($resetTimes)) {
            return 60; // Default 1 minute
        }

        // Return the earliest reset time
        $now = time();
        $soonestReset = min(array_values($resetTimes));
        
        return max(1, $soonestReset - $now);
    }
}
