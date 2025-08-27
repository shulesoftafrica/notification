<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ProductionMonitoringService;
use App\Services\AlertService;

/**
 * Production Health Monitor Command
 * Continuous monitoring for production environment
 */
class MonitorProductionHealth extends Command
{
    protected $signature = 'monitor:health {--continuous} {--alert-threshold=critical}';
    protected $description = 'Monitor production health and send alerts';

    private ProductionMonitoringService $monitoring;
    private AlertService $alertService;

    public function __construct(ProductionMonitoringService $monitoring, AlertService $alertService)
    {
        parent::__construct();
        $this->monitoring = $monitoring;
        $this->alertService = $alertService;
    }

    public function handle(): int
    {
        $this->info('Starting production health monitoring...');

        if ($this->option('continuous')) {
            $this->runContinuousMonitoring();
        } else {
            $this->runSingleCheck();
        }

        return Command::SUCCESS;
    }

    /**
     * Run continuous monitoring
     */
    private function runContinuousMonitoring(): void
    {
        $this->info('Running in continuous mode. Press Ctrl+C to stop.');

        while (true) {
            try {
                $this->performHealthCheck();
                sleep(30); // Check every 30 seconds
            } catch (\Exception $e) {
                $this->error("Monitoring error: {$e->getMessage()}");
                $this->alertService->sendCriticalAlert(
                    'monitoring_failure',
                    'Production monitoring script failed',
                    ['error' => $e->getMessage()]
                );
                sleep(60); // Wait longer on error
            }
        }
    }

    /**
     * Run single health check
     */
    private function runSingleCheck(): void
    {
        $this->performHealthCheck();
    }

    /**
     * Perform health check and handle alerts
     */
    private function performHealthCheck(): void
    {
        $health = $this->monitoring->performHealthCheck();
        $timestamp = now()->format('Y-m-d H:i:s');

        $this->line("[$timestamp] Overall Status: " . strtoupper($health['overall_status']));

        // Check each service
        foreach ($health['services'] as $service => $status) {
            $statusColor = $this->getStatusColor($status['status']);
            $this->line("  {$service}: <{$statusColor}>{$status['status']}</{$statusColor}>");
            
            // Send alerts for unhealthy services
            if ($status['status'] === 'unhealthy') {
                $this->alertService->sendCriticalAlert(
                    "service_{$service}_unhealthy",
                    "Service {$service} is unhealthy",
                    $status
                );
            } elseif ($status['status'] === 'degraded') {
                $this->alertService->sendWarningAlert(
                    "service_{$service}_degraded",
                    "Service {$service} is degraded",
                    $status
                );
            }
        }

        // Check metrics for alerts
        $this->checkMetricAlerts($health['metrics']);

        // Display active alerts
        if (!empty($health['alerts'])) {
            $this->warn("Active Alerts:");
            foreach ($health['alerts'] as $alert) {
                $this->line("  - {$alert['type']}: {$alert['message']}");
            }
        }

        // Log health status
        \Log::info('Health check completed', [
            'overall_status' => $health['overall_status'],
            'services' => array_map(fn($s) => $s['status'], $health['services']),
            'metrics' => $health['metrics'],
            'alerts_count' => count($health['alerts'])
        ]);
    }

    /**
     * Check metrics for alert conditions
     */
    private function checkMetricAlerts(array $metrics): void
    {
        // High error rate alert
        if ($metrics['error_rate_last_hour'] > 10) {
            $this->alertService->sendCriticalAlert(
                'high_error_rate',
                "Error rate is {$metrics['error_rate_last_hour']}% in the last hour",
                ['error_rate' => $metrics['error_rate_last_hour']]
            );
        } elseif ($metrics['error_rate_last_hour'] > 5) {
            $this->alertService->sendWarningAlert(
                'elevated_error_rate',
                "Error rate is {$metrics['error_rate_last_hour']}% in the last hour",
                ['error_rate' => $metrics['error_rate_last_hour']]
            );
        }

        // Queue backup alert
        if ($metrics['queue_depth'] > 10000) {
            $this->alertService->sendCriticalAlert(
                'queue_backup_critical',
                "Queue depth is critically high: {$metrics['queue_depth']} messages",
                ['queue_depth' => $metrics['queue_depth']]
            );
        } elseif ($metrics['queue_depth'] > 1000) {
            $this->alertService->sendWarningAlert(
                'queue_backup',
                "Queue depth is elevated: {$metrics['queue_depth']} messages",
                ['queue_depth' => $metrics['queue_depth']]
            );
        }

        // Low success rate alert
        if ($metrics['success_rate_last_hour'] < 80) {
            $this->alertService->sendCriticalAlert(
                'low_success_rate',
                "Success rate is low: {$metrics['success_rate_last_hour']}% in the last hour",
                ['success_rate' => $metrics['success_rate_last_hour']]
            );
        } elseif ($metrics['success_rate_last_hour'] < 95) {
            $this->alertService->sendWarningAlert(
                'degraded_success_rate',
                "Success rate is degraded: {$metrics['success_rate_last_hour']}% in the last hour",
                ['success_rate' => $metrics['success_rate_last_hour']]
            );
        }

        // High processing time alert
        if ($metrics['average_processing_time'] > 300) { // 5 minutes
            $this->alertService->sendWarningAlert(
                'slow_processing',
                "Average processing time is high: {$metrics['average_processing_time']} seconds",
                ['processing_time' => $metrics['average_processing_time']]
            );
        }
    }

    /**
     * Get color for status display
     */
    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'healthy' => 'info',
            'degraded' => 'comment',
            'unhealthy' => 'error',
            default => 'info'
        };
    }
}
