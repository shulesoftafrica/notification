<?php

namespace App\Traits;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

trait RedisThrottlesQueues
{
    /**
     * Check if the job should be throttled
     */
    protected function shouldThrottle(string $provider, string $channel, int $maxAttempts = 2, int $decaySeconds = 1): bool
    {
        $key = $this->getThrottleKey($provider, $channel);
        
        try {
            $redis = Redis::connection(config('throttling.redis_connection', 'default'));
            $currentAttempts = (int) $redis->get($key) ?: 0;
            
            return $currentAttempts >= $maxAttempts;
        } catch (\Exception $e) {
            Log::error('Redis throttle check failed, allowing job to proceed', [
                'provider' => $provider,
                'channel' => $channel,
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            
            // If Redis fails, allow the job to proceed
            return false;
        }
    }

    /**
     * Hit the throttle (increment counter)
     */
    protected function hitThrottle(string $provider, string $channel, int $decaySeconds = 1): int
    {
        $key = $this->getThrottleKey($provider, $channel);
        
        try {
            $redis = Redis::connection(config('throttling.redis_connection', 'default'));
            
            // Use Redis pipeline for atomic operations
            $pipeline = $redis->pipeline();
            $pipeline->incr($key);
            $pipeline->expire($key, $decaySeconds);
            $results = $pipeline->execute();
            
            $newCount = $results[0] ?? 1;
            
            Log::debug('Redis throttle hit', [
                'provider' => $provider,
                'channel' => $channel,
                'key' => $key,
                'count' => $newCount,
                'decay_seconds' => $decaySeconds
            ]);
            
            return $newCount;
        } catch (\Exception $e) {
            Log::error('Redis throttle hit failed', [
                'provider' => $provider,
                'channel' => $channel,
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            
            // Return 1 to simulate successful hit
            return 1;
        }
    }

    /**
     * Get remaining attempts for this throttle
     */
    protected function getRemainingAttempts(string $provider, string $channel, int $maxAttempts = 2): int
    {
        $key = $this->getThrottleKey($provider, $channel);
        
        try {
            $redis = Redis::connection(config('throttling.redis_connection', 'default'));
            $currentAttempts = (int) $redis->get($key) ?: 0;
            
            return max(0, $maxAttempts - $currentAttempts);
        } catch (\Exception $e) {
            Log::error('Redis throttle remaining check failed', [
                'provider' => $provider,
                'channel' => $channel,
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            
            // Return max attempts if Redis fails
            return $maxAttempts;
        }
    }

    /**
     * Get time until throttle resets
     */
    protected function getThrottleResetTime(string $provider, string $channel): int
    {
        $key = $this->getThrottleKey($provider, $channel);
        
        try {
            $redis = Redis::connection(config('throttling.redis_connection', 'default'));
            $ttl = $redis->ttl($key);
            
            // TTL returns -1 if key exists but has no expiry, -2 if key doesn't exist
            return $ttl > 0 ? $ttl : 0;
        } catch (\Exception $e) {
            Log::error('Redis throttle TTL check failed', [
                'provider' => $provider,
                'channel' => $channel,
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            
            return 0;
        }
    }

    /**
     * Generate throttle key for provider and channel
     */
    protected function getThrottleKey(string $provider, string $channel): string
    {
        $prefix = config('throttling.key_prefix', 'throttle');
        
        return "{$prefix}:queue:{$channel}:{$provider}";
    }

    /**
     * Get provider rate limits from configuration
     */
    protected function getProviderRateLimit(string $provider, string $channel): array
    {
        $defaultLimits = [
            'max_attempts' => 2,
            'decay_seconds' => 1
        ];

        // Check provider-specific configuration
        $providerLimits = config("throttling.providers.{$provider}", []);
        $channelLimits = config("throttling.channels.{$channel}", []);
        
        // Merge configurations with precedence: provider > channel > default
        return array_merge(
            $defaultLimits,
            $channelLimits,
            $providerLimits
        );
    }

    /**
     * Calculate delay for job when throttled
     */
    protected function calculateThrottleDelay(string $provider, string $channel): int
    {
        $resetTime = $this->getThrottleResetTime($provider, $channel);
        $limits = $this->getProviderRateLimit($provider, $channel);
        
        // Add a small buffer to the reset time to avoid race conditions
        $buffer = 1; // 1 second buffer
        
        return max($resetTime + $buffer, $limits['decay_seconds']);
    }

    /**
     * Check and handle throttling for queue jobs
     * Returns true if job should be released (delayed), false if it can proceed
     */
    protected function handleQueueThrottling(string $provider, string $channel): bool
    {
        $limits = $this->getProviderRateLimit($provider, $channel);
        $maxAttempts = $limits['max_attempts'];
        $decaySeconds = $limits['decay_seconds'];

        if ($this->shouldThrottle($provider, $channel, $maxAttempts, $decaySeconds)) {
            $delay = $this->calculateThrottleDelay($provider, $channel);
            $remaining = $this->getRemainingAttempts($provider, $channel, $maxAttempts);
            
            Log::info('Queue job throttled - releasing with delay', [
                'provider' => $provider,
                'channel' => $channel,
                'delay_seconds' => $delay,
                'remaining_attempts' => $remaining,
                'max_attempts' => $maxAttempts,
                'message_id' => $this->messageId ?? 'unknown'
            ]);

            // Release the job with delay
            $this->release($delay);
            
            return true; // Job was released
        }

        // Hit the throttle counter since we're proceeding
        $this->hitThrottle($provider, $channel, $decaySeconds);
        
        return false; // Job can proceed
    }
}