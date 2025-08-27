<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProviderConfig;

/**
 * Production Monitoring Service
 * Comprehensive monitoring and alerting for production environment
 */
class ProductionMonitoringService
{
    private const ALERT_CACHE_TTL = 300; // 5 minutes
    private const METRIC_RETENTION_DAYS = 30;

    /**
     * Perform comprehensive health check
     */
    public function performHealthCheck(): array
    {
        $healthStatus = [
            'overall_status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'services' => [],
            'metrics' => [],
            'alerts' => []
        ];

        // Check database connectivity
        $healthStatus['services']['database'] = $this->checkDatabaseHealth();
        
        // Check Redis connectivity
        $healthStatus['services']['redis'] = $this->checkRedisHealth();
        
        // Check queue health
        $healthStatus['services']['queues'] = $this->checkQueueHealth();
        
        // Check provider health
        $healthStatus['services']['providers'] = $this->checkProviderHealth();
        
        // Check disk space
        $healthStatus['services']['storage'] = $this->checkStorageHealth();
        
        // Check memory usage
        $healthStatus['services']['memory'] = $this->checkMemoryHealth();

        // Calculate overall status
        $healthStatus['overall_status'] = $this->calculateOverallHealth($healthStatus['services']);
        
        // Get critical metrics
        $healthStatus['metrics'] = $this->getCriticalMetrics();
        
        // Check for active alerts
        $healthStatus['alerts'] = $this->getActiveAlerts();

        return $healthStatus;
    }

    /**
     * Check database connectivity and performance
     */
    private function checkDatabaseHealth(): array
    {
        try {
            $start = microtime(true);
            $result = DB::select('SELECT 1 as test');
            $responseTime = (microtime(true) - $start) * 1000;

            $connectionCount = DB::select("SHOW STATUS LIKE 'Threads_connected'")[0]->Value ?? 0;
            $maxConnections = DB::select("SHOW VARIABLES LIKE 'max_connections'")[0]->Value ?? 0;

            $status = 'healthy';
            if ($responseTime > 1000) $status = 'degraded';
            if ($responseTime > 5000) $status = 'unhealthy';

            return [
                'status' => $status,
                'response_time_ms' => round($responseTime, 2),
                'connections' => [
                    'current' => (int)$connectionCount,
                    'max' => (int)$maxConnections,
                    'utilization_percent' => round(($connectionCount / $maxConnections) * 100, 2)
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Database health check failed', ['error' => $e->getMessage()]);
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check Redis connectivity and performance
     */
    private function checkRedisHealth(): array
    {
        try {
            $start = microtime(true);
            Redis::ping();
            $responseTime = (microtime(true) - $start) * 1000;

            $info = Redis::info();
            $memoryUsed = $info['used_memory'] ?? 0;
            $memoryMax = $info['maxmemory'] ?? 0;

            $status = 'healthy';
            if ($responseTime > 100) $status = 'degraded';
            if ($responseTime > 500) $status = 'unhealthy';

            return [
                'status' => $status,
                'response_time_ms' => round($responseTime, 2),
                'memory' => [
                    'used_bytes' => $memoryUsed,
                    'max_bytes' => $memoryMax,
                    'utilization_percent' => $memoryMax > 0 ? round(($memoryUsed / $memoryMax) * 100, 2) : 0
                ],
                'connected_clients' => $info['connected_clients'] ?? 0
            ];
        } catch (\Exception $e) {
            Log::error('Redis health check failed', ['error' => $e->getMessage()]);
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check queue health and processing rates
     */
    private function checkQueueHealth(): array
    {
        try {
            $queueSizes = [
                'default' => Redis::llen('queues:default'),
                'webhooks' => Redis::llen('queues:webhooks'),
                'notifications' => Redis::llen('queues:notifications'),
                'failed' => Redis::zcard('queues:failed')
            ];

            $totalPending = array_sum(array_slice($queueSizes, 0, 3));
            $failedJobs = $queueSizes['failed'];

            $status = 'healthy';
            if ($totalPending > 1000) $status = 'degraded';
            if ($totalPending > 10000 || $failedJobs > 100) $status = 'unhealthy';

            return [
                'status' => $status,
                'queue_sizes' => $queueSizes,
                'total_pending' => $totalPending,
                'processing_rate' => $this->getQueueProcessingRate()
            ];
        } catch (\Exception $e) {
            Log::error('Queue health check failed', ['error' => $e->getMessage()]);
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check provider health and availability
     */
    private function checkProviderHealth(): array
    {
        $providers = ProviderConfig::where('is_active', true)->get();
        $providerHealth = [];

        foreach ($providers as $provider) {
            try {
                $health = $this->checkIndividualProvider($provider);
                $providerHealth[$provider->provider_name] = $health;
            } catch (\Exception $e) {
                $providerHealth[$provider->provider_name] = [
                    'status' => 'unhealthy',
                    'error' => $e->getMessage()
                ];
            }
        }

        $healthyCount = count(array_filter($providerHealth, fn($p) => $p['status'] === 'healthy'));
        $totalCount = count($providerHealth);

        $overallStatus = 'healthy';
        if ($healthyCount < $totalCount) $overallStatus = 'degraded';
        if ($healthyCount === 0) $overallStatus = 'unhealthy';

        return [
            'status' => $overallStatus,
            'providers' => $providerHealth,
            'healthy_count' => $healthyCount,
            'total_count' => $totalCount
        ];
    }

    /**
     * Check individual provider health
     */
    private function checkIndividualProvider(ProviderConfig $provider): array
    {
        // Get recent success rate
        $recentMessages = Message::where('provider_config_id', $provider->id)
            ->where('created_at', '>=', now()->subHours(1))
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered,
                AVG(CASE WHEN delivered_at IS NOT NULL THEN 
                    TIMESTAMPDIFF(SECOND, created_at, delivered_at) 
                ELSE NULL END) as avg_delivery_time
            ')
            ->first();

        $successRate = $recentMessages->total > 0 
            ? ($recentMessages->delivered / $recentMessages->total) * 100 
            : 100;

        $status = 'healthy';
        if ($successRate < 95) $status = 'degraded';
        if ($successRate < 80) $status = 'unhealthy';

        return [
            'status' => $status,
            'success_rate_percent' => round($successRate, 2),
            'total_messages_last_hour' => $recentMessages->total,
            'average_delivery_time_seconds' => round($recentMessages->avg_delivery_time ?? 0, 2)
        ];
    }

    /**
     * Check storage health
     */
    private function checkStorageHealth(): array
    {
        $totalSpace = disk_total_space('/');
        $freeSpace = disk_free_space('/');
        $usedSpace = $totalSpace - $freeSpace;
        $usagePercent = ($usedSpace / $totalSpace) * 100;

        $status = 'healthy';
        if ($usagePercent > 80) $status = 'degraded';
        if ($usagePercent > 90) $status = 'unhealthy';

        return [
            'status' => $status,
            'total_bytes' => $totalSpace,
            'used_bytes' => $usedSpace,
            'free_bytes' => $freeSpace,
            'usage_percent' => round($usagePercent, 2)
        ];
    }

    /**
     * Check memory health
     */
    private function checkMemoryHealth(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));

        $usagePercent = ($memoryUsage / $memoryLimit) * 100;

        $status = 'healthy';
        if ($usagePercent > 80) $status = 'degraded';
        if ($usagePercent > 90) $status = 'unhealthy';

        return [
            'status' => $status,
            'current_bytes' => $memoryUsage,
            'peak_bytes' => $memoryPeak,
            'limit_bytes' => $memoryLimit,
            'usage_percent' => round($usagePercent, 2)
        ];
    }

    /**
     * Calculate overall health status
     */
    private function calculateOverallHealth(array $services): string
    {
        $statuses = array_column($services, 'status');
        
        if (in_array('unhealthy', $statuses)) {
            return 'unhealthy';
        }
        
        if (in_array('degraded', $statuses)) {
            return 'degraded';
        }
        
        return 'healthy';
    }

    /**
     * Get critical metrics for monitoring
     */
    private function getCriticalMetrics(): array
    {
        return [
            'messages_sent_last_hour' => $this->getMessagesLastHour(),
            'success_rate_last_hour' => $this->getSuccessRateLastHour(),
            'average_processing_time' => $this->getAverageProcessingTime(),
            'active_projects' => Project::where('is_active', true)->count(),
            'queue_depth' => $this->getTotalQueueDepth(),
            'error_rate_last_hour' => $this->getErrorRateLastHour()
        ];
    }

    /**
     * Get active alerts
     */
    private function getActiveAlerts(): array
    {
        $alerts = [];

        // Check for high error rates
        $errorRate = $this->getErrorRateLastHour();
        if ($errorRate > 5) {
            $alerts[] = [
                'type' => 'high_error_rate',
                'severity' => $errorRate > 10 ? 'critical' : 'warning',
                'message' => "Error rate is {$errorRate}% in the last hour",
                'value' => $errorRate,
                'threshold' => 5
            ];
        }

        // Check for queue backup
        $queueDepth = $this->getTotalQueueDepth();
        if ($queueDepth > 1000) {
            $alerts[] = [
                'type' => 'queue_backup',
                'severity' => $queueDepth > 10000 ? 'critical' : 'warning',
                'message' => "Queue depth is {$queueDepth} messages",
                'value' => $queueDepth,
                'threshold' => 1000
            ];
        }

        return $alerts;
    }

    /**
     * Helper methods for metrics
     */
    private function getMessagesLastHour(): int
    {
        return Message::where('created_at', '>=', now()->subHour())->count();
    }

    private function getSuccessRateLastHour(): float
    {
        $total = Message::where('created_at', '>=', now()->subHour())->count();
        $delivered = Message::where('created_at', '>=', now()->subHour())
            ->where('status', 'delivered')->count();
        
        return $total > 0 ? round(($delivered / $total) * 100, 2) : 100;
    }

    private function getAverageProcessingTime(): float
    {
        $avg = Message::where('created_at', '>=', now()->subHour())
            ->whereNotNull('delivered_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, delivered_at)) as avg_time')
            ->value('avg_time');
        
        return round($avg ?? 0, 2);
    }

    private function getTotalQueueDepth(): int
    {
        return Redis::llen('queues:default') + 
               Redis::llen('queues:webhooks') + 
               Redis::llen('queues:notifications');
    }

    private function getErrorRateLastHour(): float
    {
        $total = Message::where('created_at', '>=', now()->subHour())->count();
        $failed = Message::where('created_at', '>=', now()->subHour())
            ->where('status', 'failed')->count();
        
        return $total > 0 ? round(($failed / $total) * 100, 2) : 0;
    }

    private function getQueueProcessingRate(): array
    {
        // Get processing rate from metrics stored in Redis
        $rates = [];
        $queues = ['default', 'webhooks', 'notifications'];
        
        foreach ($queues as $queue) {
            $processed = Redis::get("metrics:queue:{$queue}:processed_last_minute") ?? 0;
            $rates[$queue] = (int)$processed;
        }
        
        return $rates;
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $lastChar = strtolower($limit[strlen($limit) - 1]);
        $value = (int)substr($limit, 0, -1);
        
        switch ($lastChar) {
            case 'g': return $value * 1024 * 1024 * 1024;
            case 'm': return $value * 1024 * 1024;
            case 'k': return $value * 1024;
            default: return (int)$limit;
        }
    }
}
