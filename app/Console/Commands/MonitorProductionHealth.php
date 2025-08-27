<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ProductionMonitoringService;
use App\Services\AlertService;
use Illuminate\Support\Facades\Log;

class MonitorProductionHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:production {--alert} {--detailed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor overall production health and performance';

    protected $monitoringService;
    protected $alertService;

    public function __construct(ProductionMonitoringService $monitoringService, AlertService $alertService)
    {
        parent::__construct();
        $this->monitoringService = $monitoringService;
        $this->alertService = $alertService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $shouldAlert = $this->option('alert');
        $detailed = $this->option('detailed');

        $this->info('Starting production health monitoring...');
        $this->newLine();

        try {
            // Get comprehensive health status
            $health = $this->monitoringService->getSystemHealth();
            $this->displayHealthStatus($health, $detailed);

            // Check for critical issues
            $criticalIssues = $this->identifyCriticalIssues($health);
            
            if (!empty($criticalIssues)) {
                $this->displayCriticalIssues($criticalIssues);
                
                if ($shouldAlert) {
                    $this->alertService->sendCriticalSystemAlert($criticalIssues);
                    $this->warn('Critical alerts sent!');
                }
                
                return 1; // Exit with error code
            }

            // Check for warnings
            $warnings = $this->identifyWarnings($health);
            if (!empty($warnings)) {
                $this->displayWarnings($warnings);
                
                if ($shouldAlert) {
                    $this->alertService->sendSystemWarningAlert($warnings);
                    $this->info('Warning alerts sent.');
                }
            }

            $this->info('Production system is healthy!');
            return 0;

        } catch (\Exception $e) {
            $this->error('Failed to check production health: ' . $e->getMessage());
            Log::error('Production health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($shouldAlert) {
                $this->alertService->sendSystemErrorAlert($e->getMessage());
            }

            return 1;
        }
    }

    protected function displayHealthStatus(array $health, bool $detailed): void
    {
        $this->info('System Health Overview:');
        
        // Database status
        $dbStatus = $health['database']['status'] ?? 'unknown';
        $dbColor = $dbStatus === 'healthy' ? 'green' : 'red';
        $this->line("  Database: <fg={$dbColor}>" . strtoupper($dbStatus) . "</>");
        
        // Cache status
        $cacheStatus = $health['cache']['status'] ?? 'unknown';
        $cacheColor = $cacheStatus === 'healthy' ? 'green' : 'red';
        $this->line("  Cache: <fg={$cacheColor}>" . strtoupper($cacheStatus) . "</>");
        
        // Queue status
        $queueStatus = $health['queue']['status'] ?? 'unknown';
        $queueColor = $queueStatus === 'healthy' ? 'green' : 'red';
        $this->line("  Queue: <fg={$queueColor}>" . strtoupper($queueStatus) . "</>");

        if ($detailed) {
            $this->newLine();
            $this->info('Detailed Metrics:');
            
            // Performance metrics
            if (isset($health['performance'])) {
                $this->line("  Average Response Time: {$health['performance']['avg_response_time']}ms");
                $this->line("  Memory Usage: {$health['performance']['memory_usage']}MB");
                $this->line("  CPU Usage: {$health['performance']['cpu_usage']}%");
            }
            
            // Notification metrics
            if (isset($health['notifications'])) {
                $this->line("  Messages Sent (24h): {$health['notifications']['sent_24h']}");
                $this->line("  Success Rate (24h): {$health['notifications']['success_rate_24h']}%");
                $this->line("  Failed Messages (24h): {$health['notifications']['failed_24h']}");
            }
        }
        
        $this->newLine();
    }

    protected function identifyCriticalIssues(array $health): array
    {
        $issues = [];
        
        // Database issues
        if (($health['database']['status'] ?? '') !== 'healthy') {
            $issues[] = 'Database connection failed';
        }
        
        // High failure rate
        if (($health['notifications']['success_rate_24h'] ?? 100) < 50) {
            $issues[] = 'Critical: Notification success rate below 50%';
        }
        
        // Memory issues
        if (($health['performance']['memory_usage'] ?? 0) > 1024) {
            $issues[] = 'Critical: High memory usage (>1GB)';
        }
        
        return $issues;
    }

    protected function identifyWarnings(array $health): array
    {
        $warnings = [];
        
        // Cache issues
        if (($health['cache']['status'] ?? '') !== 'healthy') {
            $warnings[] = 'Cache connection issues detected';
        }
        
        // Performance warnings
        if (($health['performance']['avg_response_time'] ?? 0) > 5000) {
            $warnings[] = 'High average response time (>5s)';
        }
        
        // Success rate warnings
        if (($health['notifications']['success_rate_24h'] ?? 100) < 85) {
            $warnings[] = 'Notification success rate below 85%';
        }
        
        return $warnings;
    }

    protected function displayCriticalIssues(array $issues): void
    {
        $this->error('CRITICAL ISSUES DETECTED:');
        foreach ($issues as $issue) {
            $this->line("  • <fg=red>{$issue}</>");
        }
        $this->newLine();
    }

    protected function displayWarnings(array $warnings): void
    {
        $this->warn('Warnings:');
        foreach ($warnings as $warning) {
            $this->line("  • <fg=yellow>{$warning}</>");
        }
        $this->newLine();
    }
}
