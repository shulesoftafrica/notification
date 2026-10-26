<?php

namespace App\Services;

use Illuminate\Redis\Limiters\DurationLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Provider send-rate limiter shared by queued jobs and the synchronous send endpoint.
 *
 * Uses Laravel's DurationLimiter, which checks and increments in a single Lua script,
 * so concurrent workers cannot all read "under limit" and overshoot the provider limit.
 */
class QueueThrottleService
{
    /**
     * Try to take one send slot for the provider.
     *
     * @return array{allowed: bool, retry_after: int}
     */
    public function attempt(string $provider, string $channel): array
    {
        $limits = $this->limitsFor($provider, $channel);

        try {
            $limiter = new DurationLimiter(
                Redis::connection(config('throttling.redis_connection', 'default')),
                $this->key($provider, $channel),
                $limits['max_attempts'],
                $limits['decay_seconds']
            );

            if ($limiter->acquire()) {
                return ['allowed' => true, 'retry_after' => 0];
            }

            return [
                'allowed' => false,
                'retry_after' => max(1, (int) $limiter->decaysAt - time()),
            ];
        } catch (\Throwable $e) {
            // The queue itself runs on Redis, so this only happens on a transient error.
            Log::error('Provider throttle check failed, allowing send', [
                'provider' => $provider,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return ['allowed' => true, 'retry_after' => 0];
        }
    }

    /**
     * Delay to use when releasing a throttled job, with jitter so released
     * jobs do not all wake up in the same second and collide again.
     */
    public function releaseDelay(int $retryAfter): int
    {
        $jitter = max(0, (int) config('throttling.release_jitter_seconds', 3));

        return $retryAfter + ($jitter > 0 ? random_int(0, $jitter) : 0);
    }

    /**
     * Dispatch-time delay (seconds) that spreads a batch across the provider's rate,
     * so a bulk send is not dumped on the queue all at once.
     */
    public function pacedDelay(int $index, string $provider, string $channel): int
    {
        $limits = $this->limitsFor($provider, $channel);

        return intdiv($index, max(1, $limits['max_attempts'])) * $limits['decay_seconds'];
    }

    /**
     * Provider limit if configured, otherwise the channel fallback.
     *
     * @return array{max_attempts: int, decay_seconds: int}
     */
    public function limitsFor(string $provider, string $channel): array
    {
        $limits = config("throttling.providers.{$provider}")
            ?? config("throttling.channels.{$channel}")
            ?? ['max_attempts' => 2, 'decay_seconds' => 1];

        return [
            'max_attempts' => max(1, (int) $limits['max_attempts']),
            'decay_seconds' => max(1, (int) $limits['decay_seconds']),
        ];
    }

    public function key(string $provider, string $channel): string
    {
        $prefix = config('throttling.key_prefix', 'throttle');

        return "{$prefix}:send-rl:{$channel}:{$provider}";
    }
}
