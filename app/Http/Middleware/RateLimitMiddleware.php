<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\RateLimitService;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    protected $rateLimitService;

    public function __construct(RateLimitService $rateLimitService)
    {
        $this->rateLimitService = $rateLimitService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $endpoint = null, int $maxAttempts = null): Response
    {
        $endpoint = $endpoint ?? $this->getEndpoint($request);
        $identifier = $this->getIdentifier($request);
        $key = "api:{$endpoint}:{$identifier}";

        // Check rate limit
        $rateLimitCheck = $this->rateLimitService->checkApiLimit($endpoint, $identifier);
        
        if ($rateLimitCheck['limited']) {
            $headers = $this->rateLimitService->getRateLimitHeaders(
                $key,
                $rateLimitCheck['usage']['minute']['limit'] ?? 60,
                60
            );

            return response()->json([
                'success' => false,
                'error' => 'Rate limit exceeded',
                'message' => 'Too many requests. Please try again later.',
                'rate_limit' => $rateLimitCheck['usage'],
            ], 429)->withHeaders($headers);
        }

        // Record the request
        $this->rateLimitService->recordApiRequest($endpoint, $identifier);

        // Add rate limit headers to response
        $response = $next($request);
        
        $headers = $this->rateLimitService->getRateLimitHeaders(
            $key,
            $rateLimitCheck['usage']['minute']['limit'] ?? 60,
            60
        );

        foreach ($headers as $header => $value) {
            $response->headers->set($header, $value);
        }

        return $response;
    }

    /**
     * Get endpoint name from request
     */
    protected function getEndpoint(Request $request): string
    {
        $path = $request->path();
        $method = $request->method();
        
        // Normalize endpoint
        $endpoint = preg_replace('/\/\d+/', '/{id}', $path);
        
        return strtolower($method) . ':' . $endpoint;
    }

    /**
     * Get rate limit identifier
     */
    protected function getIdentifier(Request $request): string
    {
        // Use API key if available, otherwise IP address
        $apiKey = $request->header('X-API-Key') ?? $request->bearerToken();
        
        if ($apiKey) {
            return 'api_key:' . substr(md5($apiKey), 0, 8);
        }

        return 'ip:' . $request->ip();
    }
}
