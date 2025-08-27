<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RateLimitService
{
    protected $cachePrefix = 'rate_limit:';
    protected $defaultLimits;

    public function __construct()
    {
        $this->defaultLimits = config('notification.rate_limits', [
            'per_minute' => 60,
            'per_hour' => 1000,
            'per_day' => 10000,
        ]);
    }

    /**
     * Check if action is rate limited
     */
    public function isRateLimited($key, $maxAttempts = null, $window = 60)
    {
        $maxAttempts = $maxAttempts ?? $this->defaultLimits['per_minute'];
        $cacheKey = $this->cachePrefix . $key . ':' . $window;

        $attempts = Cache::get($cacheKey, 0);
        return $attempts >= $maxAttempts;
    }

    /**
     * Increment rate limit counter
     */
    public function hit($key, $window = 60)
    {
        $cacheKey = $this->cachePrefix . $key . ':' . $window;
        
        $attempts = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, $attempts + 1, now()->addSeconds($window));

        return $attempts + 1;
    }

    /**
     * Get current attempt count
     */
    public function attempts($key, $window = 60)
    {
        $cacheKey = $this->cachePrefix . $key . ':' . $window;
        return Cache::get($cacheKey, 0);
    }

    /**
     * Get remaining attempts
     */
    public function remaining($key, $maxAttempts = null, $window = 60)
    {
        $maxAttempts = $maxAttempts ?? $this->defaultLimits['per_minute'];
        $attempts = $this->attempts($key, $window);
        return max(0, $maxAttempts - $attempts);
    }

    /**
     * Get time until reset
     */
    public function availableIn($key, $window = 60)
    {
        $cacheKey = $this->cachePrefix . $key . ':' . $window;
        
        if (!Cache::has($cacheKey)) {
            return 0;
        }

        // Get the cache expiration time
        $expiration = Cache::get($cacheKey . ':expiry', now()->addSeconds($window));
        return max(0, $expiration->diffInSeconds(now()));
    }

    /**
     * Clear rate limit for key
     */
    public function clear($key, $window = 60)
    {
        $cacheKey = $this->cachePrefix . $key . ':' . $window;
        Cache::forget($cacheKey);
        Cache::forget($cacheKey . ':expiry');
    }

    /**
     * Check provider-specific rate limits
     */
    public function checkProviderLimit($provider, $action = 'send')
    {
        $providerLimits = config("notification.providers.{$provider}.rate_limits", $this->defaultLimits);
        
        $checks = [
            'per_minute' => $this->isRateLimited("{$provider}:{$action}:minute", $providerLimits['per_minute'] ?? 60, 60),
            'per_hour' => $this->isRateLimited("{$provider}:{$action}:hour", $providerLimits['per_hour'] ?? 1000, 3600),
            'per_day' => $this->isRateLimited("{$provider}:{$action}:day", $providerLimits['per_day'] ?? 10000, 86400),
        ];

        return [
            'limited' => in_array(true, $checks),
            'limits' => $checks,
            'provider' => $provider,
            'action' => $action,
        ];
    }

    /**
     * Record provider action
     */
    public function recordProviderAction($provider, $action = 'send')
    {
        $this->hit("{$provider}:{$action}:minute", 60);
        $this->hit("{$provider}:{$action}:hour", 3600);
        $this->hit("{$provider}:{$action}:day", 86400);

        Log::debug('Provider action recorded', [
            'provider' => $provider,
            'action' => $action,
            'minute_count' => $this->attempts("{$provider}:{$action}:minute", 60),
            'hour_count' => $this->attempts("{$provider}:{$action}:hour", 3600),
            'day_count' => $this->attempts("{$provider}:{$action}:day", 86400),
        ]);
    }

    /**
     * Get provider usage statistics
     */
    public function getProviderUsage($provider, $action = 'send')
    {
        $providerLimits = config("notification.providers.{$provider}.rate_limits", $this->defaultLimits);

        return [
            'minute' => [
                'used' => $this->attempts("{$provider}:{$action}:minute", 60),
                'limit' => $providerLimits['per_minute'] ?? 60,
                'remaining' => $this->remaining("{$provider}:{$action}:minute", $providerLimits['per_minute'] ?? 60, 60),
                'reset_in' => $this->availableIn("{$provider}:{$action}:minute", 60),
            ],
            'hour' => [
                'used' => $this->attempts("{$provider}:{$action}:hour", 3600),
                'limit' => $providerLimits['per_hour'] ?? 1000,
                'remaining' => $this->remaining("{$provider}:{$action}:hour", $providerLimits['per_hour'] ?? 1000, 3600),
                'reset_in' => $this->availableIn("{$provider}:{$action}:hour", 3600),
            ],
            'day' => [
                'used' => $this->attempts("{$provider}:{$action}:day", 86400),
                'limit' => $providerLimits['per_day'] ?? 10000,
                'remaining' => $this->remaining("{$provider}:{$action}:day", $providerLimits['per_day'] ?? 10000, 86400),
                'reset_in' => $this->availableIn("{$provider}:{$action}:day", 86400),
            ],
        ];
    }

    /**
     * Check API rate limits
     */
    public function checkApiLimit($endpoint, $identifier = null)
    {
        $identifier = $identifier ?? request()->ip();
        $key = "api:{$endpoint}:{$identifier}";

        $limits = config('notification.api_rate_limits', [
            'per_minute' => 60,
            'per_hour' => 1000,
        ]);

        return [
            'limited' => $this->isRateLimited($key, $limits['per_minute'], 60),
            'usage' => [
                'minute' => [
                    'used' => $this->attempts($key, 60),
                    'limit' => $limits['per_minute'],
                    'remaining' => $this->remaining($key, $limits['per_minute'], 60),
                ],
                'hour' => [
                    'used' => $this->attempts($key . ':hour', 3600),
                    'limit' => $limits['per_hour'],
                    'remaining' => $this->remaining($key . ':hour', $limits['per_hour'], 3600),
                ],
            ],
        ];
    }

    /**
     * Record API request
     */
    public function recordApiRequest($endpoint, $identifier = null)
    {
        $identifier = $identifier ?? request()->ip();
        $key = "api:{$endpoint}:{$identifier}";

        $this->hit($key, 60); // Per minute
        $this->hit($key . ':hour', 3600); // Per hour

        return $this->checkApiLimit($endpoint, $identifier);
    }

    /**
     * Check bulk operation limits
     */
    public function checkBulkLimit($operation, $count, $identifier = null)
    {
        $identifier = $identifier ?? request()->ip();
        $key = "bulk:{$operation}:{$identifier}";

        $bulkLimits = config('notification.bulk_limits', [
            'max_batch_size' => 1000,
            'max_daily_bulk' => 10000,
        ]);

        $dailyUsage = $this->attempts($key . ':daily', 86400);
        $wouldExceedDaily = ($dailyUsage + $count) > $bulkLimits['max_daily_bulk'];
        $exceedsBatchSize = $count > $bulkLimits['max_batch_size'];

        return [
            'allowed' => !$wouldExceedDaily && !$exceedsBatchSize,
            'exceeds_batch_size' => $exceedsBatchSize,
            'would_exceed_daily' => $wouldExceedDaily,
            'daily_usage' => $dailyUsage,
            'daily_limit' => $bulkLimits['max_daily_bulk'],
            'batch_limit' => $bulkLimits['max_batch_size'],
        ];
    }

    /**
     * Record bulk operation
     */
    public function recordBulkOperation($operation, $count, $identifier = null)
    {
        $identifier = $identifier ?? request()->ip();
        $key = "bulk:{$operation}:{$identifier}";

        // Record daily usage
        $currentDaily = $this->attempts($key . ':daily', 86400);
        Cache::put($this->cachePrefix . $key . ':daily:86400', $currentDaily + $count, now()->addDay());

        Log::info('Bulk operation recorded', [
            'operation' => $operation,
            'count' => $count,
            'identifier' => $identifier,
            'daily_total' => $currentDaily + $count,
        ]);
    }

    /**
     * Get rate limit headers for API response
     */
    public function getRateLimitHeaders($key, $maxAttempts, $window = 60)
    {
        $attempts = $this->attempts($key, $window);
        $remaining = max(0, $maxAttempts - $attempts);
        $resetTime = now()->addSeconds($window)->timestamp;

        return [
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Reset' => $resetTime,
        ];
    }

    /**
     * Clean up expired rate limit entries
     */
    public function cleanup()
    {
        // This is automatically handled by cache TTL, but we can implement
        // additional cleanup logic here if needed
        Log::info('Rate limit cleanup completed');
    }
}
