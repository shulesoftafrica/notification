<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ProductionMonitoringService
{
    protected $metricsService;
    protected $alertService;
    protected $healthService;
    protected $isMonitoring = false;

    public function __construct(
        MetricsService $metricsService,
        AlertService $alertService,
        ProviderHealthService $healthService
    ) {
        $this->metricsService = $metricsService;
        $this->alertService = $alertService;
        $this->healthService = $healthService;
    }

    /**
     * Start monitoring services
     */
    public function startMonitoring()
    {
        if ($this->isMonitoring) {
            return;
        }

        $this->isMonitoring = true;

        Log::info('Production monitoring started');

        // Schedule monitoring tasks (in real implementation, use Laravel Scheduler)
        $this->scheduleHealthChecks();
        $this->scheduleErrorRateMonitoring();
        $this->scheduleRateLimitMonitoring();
    }

    /**
     * Schedule health checks
     */
    protected function scheduleHealthChecks()
    {
        // This would be implemented as a scheduled job
        Log::debug('Health check monitoring scheduled');
    }

    /**
     * Schedule error rate monitoring
     */
    protected function scheduleErrorRateMonitoring()
    {
        // This would monitor error rates and send alerts
        Log::debug('Error rate monitoring scheduled');
    }

    /**
     * Schedule rate limit monitoring
     */
    protected function scheduleRateLimitMonitoring()
    {
        // This would monitor rate limits and send alerts
        Log::debug('Rate limit monitoring scheduled');
    }

    /**
     * Check overall system health
     */
    public function performHealthCheck()
    {
        try {
            $health = $this->healthService->getOverallHealth();
            
            if (!$health['healthy']) {
                $this->alertService->sendCriticalAlert(
                    'System Health Check Failed',
                    "System health at {$health['health_percentage']}%. {$health['healthy_providers']}/{$health['total_providers']} providers healthy.",
                    $health
                );
            }

            return $health;
        } catch (\Exception $e) {
            $this->alertService->sendCriticalAlert(
                'Health Check Error',
                'Failed to perform system health check: ' . $e->getMessage(),
                ['error' => $e->getMessage()]
            );

            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Monitor error rates
     */
    public function checkErrorRates()
    {
        try {
            $metrics = $this->metricsService->getMetricsSummary('1h');
            
            $totalSent = $metrics['notifications']['sent'] ?? 0;
            $totalFailed = $metrics['notifications']['failed'] ?? 0;
            
            if ($totalSent > 0) {
                $errorRate = ($totalFailed / $totalSent) * 100;
                $threshold = config('notification.monitoring.error_rate_threshold', 5);
                
                if ($errorRate > $threshold) {
                    $this->alertService->sendHighErrorRateAlert($errorRate, $threshold, 60);
                }
            }

            return [
                'error_rate' => $errorRate ?? 0,
                'threshold' => $threshold ?? 5,
                'total_sent' => $totalSent,
                'total_failed' => $totalFailed,
            ];
        } catch (\Exception $e) {
            Log::error('Error rate monitoring failed', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Stop monitoring
     */
    public function stopMonitoring()
    {
        $this->isMonitoring = false;
        Log::info('Production monitoring stopped');
    }

    /**
     * Get monitoring status
     */
    public function getMonitoringStatus()
    {
        return [
            'monitoring' => $this->isMonitoring,
            'last_health_check' => Cache::get('monitoring:last_health_check'),
            'last_error_check' => Cache::get('monitoring:last_error_check'),
        ];
    }
}
