<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RedisThrottleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 2, int $decaySeconds = 1): Response
    {
        $key = $this->resolveRequestSignature($request);
        
        try {
            $redis = Redis::connection(config('throttling.redis_connection', 'default'));
            
            // Get current request count
            $currentAttempts = (int) $redis->get($key) ?: 0;
            
            // Check if rate limit exceeded
            if ($currentAttempts >= $maxAttempts) {
                Log::warning('Redis rate limit exceeded', [
                    'key' => $this->sanitizeKeyForLog($key),
                    'attempts' => $currentAttempts,
                    'limit' => $maxAttempts,
                    'decay_seconds' => $decaySeconds,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'endpoint' => $request->path(),
                    'method' => $request->method()
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Rate limit exceeded',
                    'message' => "Too many requests. Limit: {$maxAttempts} requests per {$decaySeconds} second(s)",
                    'retry_after' => $decaySeconds,
                    'limit' => $maxAttempts,
                    'remaining' => 0,
                    'reset_at' => now()->addSeconds($decaySeconds)->timestamp
                ], 429, [
                    'X-RateLimit-Limit' => $maxAttempts,
                    'X-RateLimit-Remaining' => 0,
                    'X-RateLimit-Reset' => now()->addSeconds($decaySeconds)->timestamp,
                    'Retry-After' => $decaySeconds
                ]);
            }
            
            // Increment request count atomically
            $pipeline = $redis->pipeline();
            $pipeline->incr($key);
            $pipeline->expire($key, $decaySeconds);
            $results = $pipeline->execute();
            
            $newCount = $results[0] ?? $currentAttempts + 1;
            
            // Add rate limit headers to response
            $response = $next($request);
            
            $remaining = max(0, $maxAttempts - $newCount);
            $resetTime = now()->addSeconds($decaySeconds)->timestamp;
            
            $response->headers->set('X-RateLimit-Limit', $maxAttempts);
            $response->headers->set('X-RateLimit-Remaining', $remaining);
            $response->headers->set('X-RateLimit-Reset', $resetTime);
            
            Log::info('Redis throttling - request allowed', [
                'key' => $this->sanitizeKeyForLog($key),
                'attempts' => $newCount,
                'limit' => $maxAttempts,
                'remaining' => $remaining,
                'endpoint' => $request->path()
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('Redis throttling error - allowing request', [
                'error' => $e->getMessage(),
                'key' => $this->sanitizeKeyForLog($key),
                'endpoint' => $request->path(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // If Redis fails, allow the request to proceed
            return $next($request);
        }
    }
    
    /**
     * Resolve request signature for rate limiting
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $prefix = config('throttling.key_prefix', 'throttle');
        
        // Use API key if available, otherwise use IP
        $apiKey = $request->attributes->get('api_key') ?? $request->header('Authorization');
        
        if ($apiKey) {
            $identifier = 'api_key:' . md5($apiKey);
        } else {
            $identifier = 'ip:' . $request->ip();
        }
        
        // Include endpoint to have per-endpoint limits
        $endpoint = str_replace('/', ':', trim($request->path(), '/'));
        
        return "{$prefix}:{$endpoint}:{$identifier}";
    }
    
    /**
     * Sanitize key for logging (remove sensitive data)
     */
    protected function sanitizeKeyForLog(string $key): string
    {
        // Replace API key hashes with placeholder for security
        return preg_replace('/:[a-f0-9]{32}$/', ':***', $key);
    }
}