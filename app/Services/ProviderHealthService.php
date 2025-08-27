<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProviderHealthService
{
    private const CIRCUIT_BREAKER_PREFIX = 'circuit_breaker:';
    private const HEALTH_CHECK_PREFIX = 'provider_health:';
    private const FAILURE_THRESHOLD = 5; // Number of failures before opening circuit
    private const SUCCESS_THRESHOLD = 3; // Number of successes to close circuit
    private const TIMEOUT_SECONDS = 60; // Circuit breaker timeout
    private const HEALTH_CHECK_INTERVAL = 300; // 5 minutes

    /**
     * Record a successful provider response
     */
    public function recordSuccess(string $providerId): void
    {
        $key = $this->getCircuitKey($providerId);
        $redis = Redis::connection();
        
        try {
            // Get current circuit state
            $circuit = $redis->hgetall($key);
            
            if (empty($circuit)) {
                // Initialize circuit breaker
                $this->initializeCircuit($providerId);
                return;
            }
            
            $state = $circuit['state'] ?? 'closed';
            $successCount = (int)($circuit['success_count'] ?? 0);
            $failureCount = (int)($circuit['failure_count'] ?? 0);
            
            if ($state === 'half_open') {
                // In half-open state, count successes
                $successCount++;
                
                if ($successCount >= self::SUCCESS_THRESHOLD) {
                    // Close the circuit
                    $this->closeCircuit($providerId);
                    Log::info("Circuit breaker closed for provider: {$providerId}");
                } else {
                    // Update success count
                    $redis->hset($key, 'success_count', $successCount);
                    $redis->hset($key, 'last_success', time());
                }
            } else {
                // In closed state, reset failure count and update success metrics
                $redis->hset($key, 'failure_count', 0);
                $redis->hset($key, 'success_count', $successCount + 1);
                $redis->hset($key, 'last_success', time());
            }
            
            // Update health metrics
            $this->updateHealthMetrics($providerId, true);
            
        } catch (\Exception $e) {
            Log::error('Error recording provider success', [
                'provider_id' => $providerId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Record a provider failure
     */
    public function recordFailure(string $providerId, string $error = null): void
    {
        $key = $this->getCircuitKey($providerId);
        $redis = Redis::connection();
        
        try {
            // Get current circuit state
            $circuit = $redis->hgetall($key);
            
            if (empty($circuit)) {
                // Initialize circuit breaker
                $this->initializeCircuit($providerId);
                $circuit = $redis->hgetall($key);
            }
            
            $state = $circuit['state'] ?? 'closed';
            $failureCount = (int)($circuit['failure_count'] ?? 0) + 1;
            
            // Update failure count and last failure time
            $redis->hset($key, 'failure_count', $failureCount);
            $redis->hset($key, 'last_failure', time());
            
            if ($error) {
                $redis->hset($key, 'last_error', $error);
            }
            
            // Check if we should open the circuit
            if ($state === 'closed' && $failureCount >= self::FAILURE_THRESHOLD) {
                $this->openCircuit($providerId);
                Log::warning("Circuit breaker opened for provider: {$providerId}", [
                    'failure_count' => $failureCount,
                    'last_error' => $error
                ]);
            } elseif ($state === 'half_open') {
                // Return to open state
                $this->openCircuit($providerId);
                Log::warning("Circuit breaker returned to open state for provider: {$providerId}");
            }
            
            // Update health metrics
            $this->updateHealthMetrics($providerId, false, $error);
            
        } catch (\Exception $e) {
            Log::error('Error recording provider failure', [
                'provider_id' => $providerId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Check if provider is available (circuit breaker check)
     */
    public function isProviderAvailable(string $providerId): bool
    {
        try {
            $key = $this->getCircuitKey($providerId);
            $redis = Redis::connection();
            
            $circuit = $redis->hgetall($key);
            
            if (empty($circuit)) {
                // Initialize and assume available
                $this->initializeCircuit($providerId);
                return true;
            }
            
            $state = $circuit['state'] ?? 'closed';
            $openedAt = (int)($circuit['opened_at'] ?? 0);
            
            switch ($state) {
                case 'closed':
                    return true;
                    
                case 'open':
                    // Check if timeout has passed
                    if (time() - $openedAt >= self::TIMEOUT_SECONDS) {
                        // Move to half-open state
                        $this->halfOpenCircuit($providerId);
                        return true;
                    }
                    return false;
                    
                case 'half_open':
                    return true;
                    
                default:
                    return true;
            }
            
        } catch (\Exception $e) {
            Log::error('Error checking provider availability', [
                'provider_id' => $providerId,
                'error' => $e->getMessage()
            ]);
            // Fail open - assume available if we can't check
            return true;
        }
    }
    
    /**
     * Get provider health status
     */
    public function getProviderHealth(string $providerId): array
    {
        $circuitKey = $this->getCircuitKey($providerId);
        $healthKey = $this->getHealthKey($providerId);
        $redis = Redis::connection();
        
        try {
            $circuit = $redis->hgetall($circuitKey);
            $health = $redis->hgetall($healthKey);
            
            return [
                'provider_id' => $providerId,
                'circuit_state' => $circuit['state'] ?? 'closed',
                'is_available' => $this->isProviderAvailable($providerId),
                'failure_count' => (int)($circuit['failure_count'] ?? 0),
                'success_count' => (int)($circuit['success_count'] ?? 0),
                'last_success' => $circuit['last_success'] ? Carbon::createFromTimestamp($circuit['last_success']) : null,
                'last_failure' => $circuit['last_failure'] ? Carbon::createFromTimestamp($circuit['last_failure']) : null,
                'last_error' => $circuit['last_error'] ?? null,
                'success_rate' => $this->calculateSuccessRate($health),
                'average_response_time' => (float)($health['avg_response_time'] ?? 0),
                'health_score' => $this->calculateHealthScore($circuit, $health),
                'last_health_check' => $health['last_check'] ? Carbon::createFromTimestamp($health['last_check']) : null
            ];
            
        } catch (\Exception $e) {
            Log::error('Error getting provider health', [
                'provider_id' => $providerId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'provider_id' => $providerId,
                'circuit_state' => 'unknown',
                'is_available' => false,
                'error' => 'Health check failed'
            ];
        }
    }
    
    /**
     * Get health status for all providers
     */
    public function getAllProviderHealth(): array
    {
        $redis = Redis::connection();
        
        try {
            // Get all circuit breaker keys
            $keys = $redis->keys(self::CIRCUIT_BREAKER_PREFIX . '*');
            $providers = [];
            
            foreach ($keys as $key) {
                $providerId = str_replace(self::CIRCUIT_BREAKER_PREFIX, '', $key);
                $providers[] = $this->getProviderHealth($providerId);
            }
            
            return $providers;
            
        } catch (\Exception $e) {
            Log::error('Error getting all provider health', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Perform health check on a provider
     */
    public function performHealthCheck(string $providerId, string $provider, string $channel): bool
    {
        $startTime = microtime(true);
        
        try {
            // Get a test adapter for the provider
            $adapter = $this->getTestAdapter($channel);
            $config = $this->getProviderTestConfig($providerId, $provider);
            
            if (!$config) {
                Log::warning("No test configuration found for provider: {$providerId}");
                return false;
            }
            
            // Perform a test request (like connectivity check)
            $result = $this->performConnectivityTest($adapter, $config);
            $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
            
            if ($result) {
                $this->recordSuccess($providerId);
                $this->updateResponseTime($providerId, $responseTime);
                return true;
            } else {
                $this->recordFailure($providerId, 'Health check failed');
                return false;
            }
            
        } catch (\Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;
            $this->recordFailure($providerId, $e->getMessage());
            $this->updateResponseTime($providerId, $responseTime);
            
            Log::error('Provider health check failed', [
                'provider_id' => $providerId,
                'error' => $e->getMessage(),
                'response_time' => $responseTime
            ]);
            
            return false;
        }
    }
    
    /**
     * Initialize circuit breaker for a provider
     */
    private function initializeCircuit(string $providerId): void
    {
        $key = $this->getCircuitKey($providerId);
        $redis = Redis::connection();
        
        $redis->hset($key, [
            'state' => 'closed',
            'failure_count' => 0,
            'success_count' => 0,
            'opened_at' => 0,
            'last_success' => time(),
            'last_failure' => 0,
            'last_error' => ''
        ]);
        
        $redis->expire($key, 86400); // Expire after 24 hours of inactivity
    }
    
    /**
     * Open the circuit breaker
     */
    private function openCircuit(string $providerId): void
    {
        $key = $this->getCircuitKey($providerId);
        $redis = Redis::connection();
        
        $redis->hset($key, [
            'state' => 'open',
            'opened_at' => time(),
            'success_count' => 0
        ]);
    }
    
    /**
     * Close the circuit breaker
     */
    private function closeCircuit(string $providerId): void
    {
        $key = $this->getCircuitKey($providerId);
        $redis = Redis::connection();
        
        $redis->hset($key, [
            'state' => 'closed',
            'failure_count' => 0,
            'success_count' => 0,
            'opened_at' => 0
        ]);
    }
    
    /**
     * Set circuit breaker to half-open state
     */
    private function halfOpenCircuit(string $providerId): void
    {
        $key = $this->getCircuitKey($providerId);
        $redis = Redis::connection();
        
        $redis->hset($key, [
            'state' => 'half_open',
            'success_count' => 0
        ]);
        
        Log::info("Circuit breaker moved to half-open state for provider: {$providerId}");
    }
    
    /**
     * Update health metrics
     */
    private function updateHealthMetrics(string $providerId, bool $success, string $error = null): void
    {
        $key = $this->getHealthKey($providerId);
        $redis = Redis::connection();
        
        $current = $redis->hgetall($key);
        $totalRequests = (int)($current['total_requests'] ?? 0) + 1;
        $successfulRequests = (int)($current['successful_requests'] ?? 0) + ($success ? 1 : 0);
        
        $redis->hset($key, [
            'total_requests' => $totalRequests,
            'successful_requests' => $successfulRequests,
            'last_check' => time()
        ]);
        
        if ($error) {
            $redis->hset($key, 'last_error', $error);
        }
        
        $redis->expire($key, 86400); // Expire after 24 hours
    }
    
    /**
     * Update response time metrics
     */
    private function updateResponseTime(string $providerId, float $responseTime): void
    {
        $key = $this->getHealthKey($providerId);
        $redis = Redis::connection();
        
        $current = $redis->hgetall($key);
        $totalTime = (float)($current['total_response_time'] ?? 0) + $responseTime;
        $requestCount = (int)($current['response_time_count'] ?? 0) + 1;
        $avgResponseTime = $totalTime / $requestCount;
        
        $redis->hset($key, [
            'total_response_time' => $totalTime,
            'response_time_count' => $requestCount,
            'avg_response_time' => $avgResponseTime,
            'last_response_time' => $responseTime
        ]);
    }
    
    /**
     * Calculate success rate from health metrics
     */
    private function calculateSuccessRate(array $health): float
    {
        $total = (int)($health['total_requests'] ?? 0);
        $successful = (int)($health['successful_requests'] ?? 0);
        
        if ($total === 0) {
            return 100.0; // No data, assume healthy
        }
        
        return ($successful / $total) * 100;
    }
    
    /**
     * Calculate overall health score
     */
    private function calculateHealthScore(array $circuit, array $health): float
    {
        $successRate = $this->calculateSuccessRate($health);
        $circuitState = $circuit['state'] ?? 'closed';
        
        // Base score from success rate
        $score = $successRate;
        
        // Penalize based on circuit state
        if ($circuitState === 'open') {
            $score = min($score, 25.0); // Max 25% if circuit is open
        } elseif ($circuitState === 'half_open') {
            $score = min($score, 75.0); // Max 75% if circuit is half-open
        }
        
        // Factor in recent failures
        $recentFailures = (int)($circuit['failure_count'] ?? 0);
        if ($recentFailures > 0) {
            $score = max(0, $score - ($recentFailures * 5)); // Subtract 5% per failure
        }
        
        return round($score, 2);
    }
    
    /**
     * Get circuit breaker Redis key
     */
    private function getCircuitKey(string $providerId): string
    {
        return self::CIRCUIT_BREAKER_PREFIX . $providerId;
    }
    
    /**
     * Get health metrics Redis key
     */
    private function getHealthKey(string $providerId): string
    {
        return self::HEALTH_CHECK_PREFIX . $providerId;
    }
    
    /**
     * Get test adapter for channel
     */
    private function getTestAdapter(string $channel)
    {
        return match($channel) {
            'email' => app(\App\Services\Adapters\EmailAdapter::class),
            'sms' => app(\App\Services\Adapters\SmsAdapter::class),
            'whatsapp' => app(\App\Services\Adapters\WhatsAppAdapter::class),
            default => throw new \Exception('Unsupported channel: ' . $channel)
        };
    }
    
    /**
     * Get provider test configuration
     */
    private function getProviderTestConfig(string $providerId, string $provider): ?\App\Models\ProviderConfig
    {
        return \App\Models\ProviderConfig::where('id', $providerId)
            ->where('provider', $provider)
            ->where('enabled', true)
            ->first();
    }
    
    /**
     * Perform connectivity test
     */
    private function performConnectivityTest($adapter, $config): bool
    {
        // For now, return true - in real implementation, this would
        // make actual test calls to provider APIs
        return true;
    }

    /**
     * Get provider health score (0-100)
     */
    public function getHealthScore(string $provider): int
    {
        try {
            $circuitKey = $this->getCircuitKey($provider);
            $healthKey = $this->getHealthKey($provider);
            $redis = Redis::connection();
            
            $circuit = $redis->hgetall($circuitKey);
            $health = $redis->hgetall($healthKey);
            
            return $this->calculateHealthScore($circuit, $health);
        } catch (\Exception $e) {
            // Fallback when Redis is not available
            Log::warning("Redis not available for health score, returning default: {$e->getMessage()}");
            return 100; // Default healthy score
        }
    }

    /**
     * Get circuit breaker state
     */
    public function getCircuitState(string $provider): string
    {
        try {
            $circuitKey = $this->getCircuitKey($provider);
            $redis = Redis::connection();
            
            $circuit = $redis->hgetall($circuitKey);
            return $circuit['state'] ?? 'closed';
        } catch (\Exception $e) {
            // Fallback when Redis is not available
            Log::warning("Redis not available for circuit state, returning default: {$e->getMessage()}");
            return 'closed'; // Default state
        }
    }

    /**
     * Reset provider health data
     */
    public function resetProvider(string $provider): void
    {
        try {
            $circuitKey = $this->getCircuitKey($provider);
            $healthKey = $this->getHealthKey($provider);
            $redis = Redis::connection();
            
            $redis->del($circuitKey);
            $redis->del($healthKey);
            
            Log::info("Reset health data for provider: {$provider}");
        } catch (\Exception $e) {
            Log::warning("Redis not available for reset operation: {$e->getMessage()}");
        }
    }
}
