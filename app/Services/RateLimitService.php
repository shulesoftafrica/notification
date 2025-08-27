<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RateLimitService
{
    /**
     * Check if request is within rate limits
     */
    public function checkRateLimit(string $key, array $limits): array
    {
        $now = time();
        $results = [];

        foreach ($limits as $window => $limit) {
            $windowKey = "{$key}:{$window}";
            $windowSeconds = $this->getWindowSeconds($window);
            
            // Get current count from cache
            $currentCount = Cache::get($windowKey, 0);
            
            // Increment counter
            $newCount = $currentCount + 1;
            
            // Store with expiry
            Cache::put($windowKey, $newCount, $windowSeconds);
            
            $remaining = max(0, $limit - $newCount);
            $resetTime = $now + $windowSeconds;
            $currentCount = $newCount <= $limit;
            
            $results[$window] = [
                'limit' => $limit,
                'remaining' => $remaining,
                'reset_time' => $resetTime,
                'current_count' => $newCount,
                'allowed' => $allowed,
                'exceeded' => $newCount > $limit
            ];
            
            // If any limit is exceeded, log it
            if ($newCount > $limit) {
                Log::warning('Rate limit exceeded', [
                    'key' => $key,
                    'window' => $window,
                    'limit' => $limit,
                    'count' => $newCount
                ]);
            }
        }

        return $results;
    }

    /**
     * Check if any rate limit is exceeded
     */
    public function isRateLimited(string $key, array $limits): bool
    {
        $results = $this->checkRateLimit($key, $limits);
        
        foreach ($results as $result) {
            if ($result['exceeded']) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get rate limit information without incrementing
     */
    public function getRateLimitInfo(string $key, array $limits): array
    {
        $now = time();
        $results = [];

        foreach ($limits as $window => $limit) {
            $windowKey = "{$key}:{$window}";
            $windowSeconds = $this->getWindowSeconds($window);
            
            $currentCount = Cache::get($windowKey, 0);
            $remaining = max(0, $limit - $currentCount);
            $resetTime = $now + $windowSeconds;
            
            $results[$window] = [
                'limit' => $limit,
                'remaining' => $remaining,
                'reset_time' => $resetTime,
                'current_count' => $currentCount,
                'exceeded' => $currentCount >= $limit
            ];
        }

        return $results;
    }

    /**
     * Reset rate limit for a key
     */
    public function resetRateLimit(string $key, array $windows = null): bool
    {
        try {
            if ($windows === null) {
                // For file cache, we need to clear all related keys manually
                $allWindows = ['minute', 'hour', 'day'];
                foreach ($allWindows as $window) {
                    Cache::forget("{$key}:{$window}");
                }
            } else {
                // Reset specific windows
                foreach ($windows as $window) {
                    Cache::forget("{$key}:{$window}");
                }
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to reset rate limit', [
                'key' => $key,
                'windows' => $windows,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get quota usage for a project/tenant
     */
    public function getQuotaUsage(string $projectId, string $tenantId, string $channel = null): array
    {
        $baseKey = "quota:{$projectId}:{$tenantId}";
        
        if ($channel) {
            $baseKey .= ":{$channel}";
            $channels = [$channel];
        } else {
            $channels = ['email', 'sms', 'whatsapp'];
        }
        
        $usage = [];
        
        foreach ($channels as $ch) {
            $channelKey = $channel ? $baseKey : "{$baseKey}:{$ch}";
            
            $daily = Cache::get("{$channelKey}:daily", 0);
            $monthly = Cache::get("{$channelKey}:monthly", 0);
            
            $usage[$ch] = [
                'daily' => $daily,
                'monthly' => $monthly
            ];
        }

        return $usage;
    }

    /**
     * Increment quota usage
     */
    public function incrementQuota(string $projectId, string $tenantId, string $channel): void
    {
        $baseKey = "quota:{$projectId}:{$tenantId}:{$channel}";
        
        // Increment daily counter
        $dailyKey = "{$baseKey}:daily";
        $dailyCount = Cache::get($dailyKey, 0) + 1;
        Cache::put($dailyKey, $dailyCount, 86400); // 24 hours
        
        // Increment monthly counter  
        $monthlyKey = "{$baseKey}:monthly";
        $monthlyCount = Cache::get($monthlyKey, 0) + 1;
        Cache::put($monthlyKey, $monthlyCount, 2592000); // 30 days
    }

    /**
     * Convert window string to seconds
     */
    private function getWindowSeconds(string $window): int
    {
        return match($window) {
            'minute' => 60,
            'hour' => 3600,
            'day' => 86400,
            'month' => 2592000, // 30 days
            default => (int) $window // Allow custom seconds
        };
    }

    /**
     * Get project-specific rate limits
     */
    public function getProjectRateLimits(string $projectId): array
    {
        // This could be fetched from database based on project subscription
        // For now, return default limits
        return [
            'minute' => 100,
            'hour' => 5000,
            'day' => 100000
        ];
    }

    /**
     * Get tenant-specific rate limits
     */
    public function getTenantRateLimits(string $projectId, string $tenantId): array
    {
        // This could be fetched from database based on tenant configuration
        // For now, return default limits
        return [
            'minute' => 50,
            'hour' => 2500,
            'day' => 50000
        ];
    }

    /**
     * Get channel-specific rate limits
     */
    public function getChannelRateLimits(string $channel): array
    {
        return match($channel) {
            'email' => [
                'minute' => 100,
                'hour' => 5000,
                'day' => 100000
            ],
            'sms' => [
                'minute' => 50,
                'hour' => 2000,
                'day' => 20000
            ],
            'whatsapp' => [
                'minute' => 20,
                'hour' => 1000,
                'day' => 10000
            ],
            default => [
                'minute' => 10,
                'hour' => 500,
                'day' => 5000
            ]
        };
    }

    /**
     * Check global service rate limits
     */
    public function checkGlobalLimits(): bool
    {
        $globalLimits = [
            'minute' => 10000,
            'hour' => 500000,
            'day' => 10000000
        ];

        $key = 'global:service';
        $results = $this->checkRateLimit($key, $globalLimits);

        foreach ($results as $window => $result) {
            if (!$result['allowed']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Hit global service rate limits
     */
    public function hitGlobalLimits(): void
    {
        // This is handled by checkGlobalLimits() which increments counters
        // No additional action needed as the check already increments
    }

    /**
     * Get rate limits for a project and tenant
     */
    public function getRateLimits(string $projectId, ?string $tenantId = null): array
    {
        $projectLimits = $this->getProjectRateLimits($projectId);
        
        if ($tenantId) {
            $tenantLimits = $this->getTenantRateLimits($projectId, $tenantId);
            // Merge limits, tenant limits override project limits
            return array_merge($projectLimits, $tenantLimits);
        }

        return $projectLimits;
    }

    /**
     * Check if a request is allowed within rate limits
     */
    public function isAllowed(string $identifier, array $limits): bool
    {
        $results = $this->checkRateLimit($identifier, $limits);

        foreach ($results as $window => $result) {
            if (!$result['allowed']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Hit/increment rate limit counters
     */
    public function hit(string $identifier, array $limits): void
    {
        // The checkRateLimit method already increments counters
        // This method is for consistency with the middleware expectations
        $this->checkRateLimit($identifier, $limits);
    }

    /**
     * Get remaining requests for rate limits
     */
    public function getRemainingRequests(string $identifier, array $limits): array
    {
        $results = $this->checkRateLimit($identifier, $limits);
        $remaining = [];

        foreach ($results as $window => $result) {
            $remaining[$window] = $result['remaining'];
        }

        return $remaining;
    }

    /**
     * Get reset times for rate limits
     */
    public function getResetTimes(string $identifier): array
    {
        $windows = ['minute', 'hour', 'day'];
        $resetTimes = [];
        $now = time();

        foreach ($windows as $window) {
            $windowSeconds = $this->getWindowSeconds($window);
            $resetTimes[$window] = $now + $windowSeconds;
        }

        return $resetTimes;
    }
}
