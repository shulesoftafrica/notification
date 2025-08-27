<?php

namespace App\Http\Middleware;

use App\Services\RateLimitService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    private RateLimitService $rateLimitService;

    public function __construct(RateLimitService $rateLimitService)
    {
        $this->rateLimitService = $rateLimitService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $level = 'default'): Response
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        if (!$project) {
            // If no project authentication, skip rate limiting
            return $next($request);
        }

        try {
            // Build rate limit key based on level
            $rateLimitKey = $this->buildRateLimitKey($request, $project->project_id, $tenantId, $level);
            
            // Get appropriate rate limits
            $limits = $this->getRateLimits($project->project_id, $tenantId, $level, $request);
            
            // Check rate limits
            $rateLimitResults = $this->rateLimitService->checkRateLimit($rateLimitKey, $limits);
            
            // Find the most restrictive limit that's exceeded
            $exceededLimit = null;
            $earliestReset = null;
            
            foreach ($rateLimitResults as $window => $result) {
                if ($result['exceeded']) {
                    if ($exceededLimit === null || $result['reset_time'] < $earliestReset) {
                        $exceededLimit = $result;
                        $earliestReset = $result['reset_time'];
                    }
                }
            }
            
            // Add rate limit headers to response
            $response = $next($request);
            $this->addRateLimitHeaders($response, $rateLimitResults);
            
            // If rate limited, return 429 response
            if ($exceededLimit !== null) {
                Log::warning('Rate limit exceeded', [
                    'project_id' => $project->project_id,
                    'tenant_id' => $tenantId,
                    'rate_limit_key' => $rateLimitKey,
                    'exceeded_limit' => $exceededLimit,
                    'request_id' => $requestId
                ]);
                
                return response()->json([
                    'error' => [
                        'code' => 'RATE_LIMIT_EXCEEDED',
                        'message' => 'Rate limit exceeded. Too many requests.',
                        'details' => [
                            'limit' => $exceededLimit['limit'],
                            'reset_time' => $exceededLimit['reset_time'],
                            'retry_after' => $exceededLimit['reset_time'] - time()
                        ],
                        'trace_id' => $requestId
                    ]
                ], 429, $this->getRateLimitHeaders($rateLimitResults));
            }
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('Rate limiting error', [
                'project_id' => $project->project_id ?? null,
                'tenant_id' => $tenantId ?? null,
                'error' => $e->getMessage(),
                'request_id' => $requestId
            ]);
            
            // On rate limiting errors, allow the request to proceed
            return $next($request);
        }
    }

    /**
     * Build rate limit key based on level and request context
     */
    private function buildRateLimitKey(Request $request, string $projectId, string $tenantId, string $level): string
    {
        $baseKey = "rate_limit:{$projectId}";
        
        return match($level) {
            'global' => "rate_limit:global",
            'project' => "rate_limit:{$projectId}",
            'tenant' => "rate_limit:{$projectId}:{$tenantId}",
            'endpoint' => "rate_limit:{$projectId}:{$tenantId}:" . $request->path(),
            'ip' => "rate_limit:ip:" . $request->ip(),
            'channel' => "rate_limit:{$projectId}:{$tenantId}:" . ($request->input('channel') ?? 'unknown'),
            default => "rate_limit:{$projectId}:{$tenantId}"
        };
    }

    /**
     * Get rate limits based on level and context
     */
    private function getRateLimits(string $projectId, string $tenantId, string $level, Request $request): array
    {
        return match($level) {
            'global' => [
                'minute' => 10000,
                'hour' => 500000,
                'day' => 10000000
            ],
            'project' => $this->rateLimitService->getProjectRateLimits($projectId),
            'tenant' => $this->rateLimitService->getTenantRateLimits($projectId, $tenantId),
            'endpoint' => $this->getEndpointRateLimits($request->path()),
            'ip' => [
                'minute' => 100,
                'hour' => 1000,
                'day' => 10000
            ],
            'channel' => $this->rateLimitService->getChannelRateLimits($request->input('channel', 'default')),
            'bulk' => [
                'minute' => 10,
                'hour' => 100,
                'day' => 1000
            ],
            default => [
                'minute' => 100,
                'hour' => 5000,
                'day' => 100000
            ]
        };
    }

    /**
     * Get endpoint-specific rate limits
     */
    private function getEndpointRateLimits(string $path): array
    {
        // Different endpoints may have different limits
        if (str_contains($path, '/bulk/messages')) {
            return [
                'minute' => 10,
                'hour' => 100,
                'day' => 1000
            ];
        }
        
        if (str_contains($path, '/messages')) {
            return [
                'minute' => 100,
                'hour' => 5000,
                'day' => 50000
            ];
        }
        
        if (str_contains($path, '/templates')) {
            return [
                'minute' => 50,
                'hour' => 1000,
                'day' => 10000
            ];
        }
        
        if (str_contains($path, '/analytics')) {
            return [
                'minute' => 20,
                'hour' => 500,
                'day' => 5000
            ];
        }
        
        // Default limits
        return [
            'minute' => 60,
            'hour' => 1000,
            'day' => 10000
        ];
    }

    /**
     * Add rate limit headers to response
     */
    private function addRateLimitHeaders(Response $response, array $rateLimitResults): void
    {
        // Use the most restrictive limit for headers
        $mostRestrictive = null;
        
        foreach ($rateLimitResults as $window => $result) {
            if ($mostRestrictive === null || $result['remaining'] < $mostRestrictive['remaining']) {
                $mostRestrictive = $result;
            }
        }
        
        if ($mostRestrictive) {
            $response->headers->set('X-RateLimit-Limit', $mostRestrictive['limit']);
            $response->headers->set('X-RateLimit-Remaining', $mostRestrictive['remaining']);
            $response->headers->set('X-RateLimit-Reset', $mostRestrictive['reset_time']);
            
            if ($mostRestrictive['exceeded']) {
                $response->headers->set('Retry-After', $mostRestrictive['reset_time'] - time());
            }
        }
    }

    /**
     * Get rate limit headers for error response
     */
    private function getRateLimitHeaders(array $rateLimitResults): array
    {
        $headers = [];
        
        // Use the most restrictive limit for headers
        $mostRestrictive = null;
        
        foreach ($rateLimitResults as $window => $result) {
            if ($mostRestrictive === null || $result['remaining'] < $mostRestrictive['remaining']) {
                $mostRestrictive = $result;
            }
        }
        
        if ($mostRestrictive) {
            $headers['X-RateLimit-Limit'] = $mostRestrictive['limit'];
            $headers['X-RateLimit-Remaining'] = $mostRestrictive['remaining'];
            $headers['X-RateLimit-Reset'] = $mostRestrictive['reset_time'];
            
            if ($mostRestrictive['exceeded']) {
                $headers['Retry-After'] = $mostRestrictive['reset_time'] - time();
            }
        }
        
        return $headers;
    }
}
