<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Redis\Limiters\DurationLimiter;
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
        $key = static::keyFor($request, $request->path());

        try {
            // DurationLimiter checks and increments in one Lua script, so concurrent requests
            // can't all read "under limit" before any of them increments.
            $limiter = new DurationLimiter(
                Redis::connection(config('throttling.redis_connection', 'default')),
                $key,
                $maxAttempts,
                $decaySeconds
            );
            $allowed = $limiter->acquire();
        } catch (\Exception $e) {
            Log::error('Redis throttling error - allowing request', [
                'error' => $e->getMessage(),
                'key' => $this->sanitizeKeyForLog($key),
                'endpoint' => $request->path(),
            ]);

            // If Redis fails, allow the request to proceed
            return $next($request);
        }

        $retryAfter = max(1, (int) $limiter->decaysAt - time());

        if (!$allowed) {
            Log::warning('Redis rate limit exceeded', [
                'key' => $this->sanitizeKeyForLog($key),
                'limit' => $maxAttempts,
                'decay_seconds' => $decaySeconds,
                'ip' => $request->ip(),
                'endpoint' => $request->path(),
                'method' => $request->method()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Rate limit exceeded',
                'message' => "Too many requests. Limit: {$maxAttempts} requests per {$decaySeconds} second(s)",
                'retry_after' => $retryAfter,
                'limit' => $maxAttempts,
                'remaining' => 0,
                'reset_at' => (int) $limiter->decaysAt
            ], 429, [
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => (int) $limiter->decaysAt,
                'Retry-After' => $retryAfter
            ]);
        }

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', $limiter->remaining);
        $response->headers->set('X-RateLimit-Reset', (int) $limiter->decaysAt);

        return $response;
    }

    /**
     * Throttle key for a request and endpoint. Shared by ThrottlingController.
     *
     * All schools call through the same API token, so the school (schema_name) is part of
     * the key; otherwise every school would share one 2 req/s budget.
     */
    public static function keyFor(Request $request, string $endpoint): string
    {
        $prefix = config('throttling.key_prefix', 'throttle');

        $token = $request->attributes->get('api_token') ?? $request->header('Authorization');
        $identifier = $token ? 'api_key:' . md5($token) : 'ip:' . $request->ip();

        if ($schema = $request->input('schema_name')) {
            $identifier .= ':schema:' . md5((string) $schema);
        }

        // Include endpoint to have per-endpoint limits
        $endpointKey = str_replace('/', ':', trim($endpoint, '/'));

        return "{$prefix}:rl:{$endpointKey}:{$identifier}";
    }

    /**
     * Sanitize key for logging (remove sensitive data)
     */
    protected function sanitizeKeyForLog(string $key): string
    {
        return preg_replace('/[a-f0-9]{32}/', '***', $key);
    }
}
