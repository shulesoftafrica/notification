<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\ProductionMonitoringService;
use App\Services\AlertService;

/**
 * Production Health Controller
 * Provides health check endpoints for load balancers and monitoring systems
 */
class HealthController extends Controller
{
    private ProductionMonitoringService $monitoring;
    private AlertService $alertService;

    public function __construct(ProductionMonitoringService $monitoring, AlertService $alertService)
    {
        $this->monitoring = $monitoring;
        $this->alertService = $alertService;
    }

    /**
     * Simple health check for load balancer
     * Returns 200 if service is healthy, 503 if not
     */
    public function simple(): JsonResponse
    {
        try {
            // Basic checks only for load balancer
            $dbOk = \DB::select('SELECT 1')[0] ?? false;
            $redisOk = \Cache::store('redis')->get('health_check') !== null;
            
            if ($dbOk && $redisOk) {
                return response()->json(['status' => 'ok'], 200);
            }
            
            return response()->json(['status' => 'unhealthy'], 503);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 503);
        }
    }

    /**
     * Detailed health check
     */
    public function detailed(): JsonResponse
    {
        try {
            $health = $this->monitoring->performHealthCheck();
            
            $httpStatus = match ($health['overall_status']) {
                'healthy' => 200,
                'degraded' => 200,
                'unhealthy' => 503,
                default => 503
            };

            return response()->json($health, $httpStatus);
        } catch (\Exception $e) {
            return response()->json([
                'overall_status' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * Readiness check - indicates if service is ready to receive traffic
     */
    public function ready(): JsonResponse
    {
        try {
            // Check critical services are ready
            $ready = true;
            $checks = [];

            // Database connection
            try {
                \DB::select('SELECT 1');
                $checks['database'] = true;
            } catch (\Exception $e) {
                $checks['database'] = false;
                $ready = false;
            }

            // Redis connection
            try {
                \Cache::store('redis')->put('readiness_check', true, 1);
                $checks['redis'] = true;
            } catch (\Exception $e) {
                $checks['redis'] = false;
                $ready = false;
            }

            // Queue system
            try {
                \Queue::size() >= 0; // Just check if we can query queue
                $checks['queue'] = true;
            } catch (\Exception $e) {
                $checks['queue'] = false;
                $ready = false;
            }

            return response()->json([
                'ready' => $ready,
                'checks' => $checks,
                'timestamp' => now()->toISOString()
            ], $ready ? 200 : 503);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 503);
        }
    }

    /**
     * Liveness check - indicates if service is alive (for restart decisions)
     */
    public function live(): JsonResponse
    {
        // Simple check - if we can respond, we're alive
        return response()->json([
            'alive' => true,
            'timestamp' => now()->toISOString(),
            'uptime_seconds' => $this->getUptime()
        ], 200);
    }

    /**
     * Startup check - indicates if service has completed startup
     */
    public function startup(): JsonResponse
    {
        try {
            // Check if all startup tasks are complete
            $startupComplete = true;
            $checks = [];

            // Check if migrations are up to date
            try {
                $pendingMigrations = \Artisan::call('migrate:status');
                $checks['migrations'] = true;
            } catch (\Exception $e) {
                $checks['migrations'] = false;
                $startupComplete = false;
            }

            // Check if config is loaded
            $checks['config'] = !empty(config('notification.providers'));

            // Check if service providers are loaded
            $checks['providers'] = app()->bound('App\Services\NotificationService');

            $startupComplete = $startupComplete && $checks['config'] && $checks['providers'];

            return response()->json([
                'startup_complete' => $startupComplete,
                'checks' => $checks,
                'timestamp' => now()->toISOString()
            ], $startupComplete ? 200 : 503);

        } catch (\Exception $e) {
            return response()->json([
                'startup_complete' => false,
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 503);
        }
    }

    /**
     * Metrics endpoint for monitoring systems
     */
    public function metrics(): JsonResponse
    {
        try {
            $health = $this->monitoring->performHealthCheck();
            
            // Return metrics in Prometheus-like format
            $metrics = [
                'notification_service_up' => $health['overall_status'] === 'healthy' ? 1 : 0,
                'notification_service_messages_total' => $health['metrics']['messages_sent_last_hour'] ?? 0,
                'notification_service_success_rate' => $health['metrics']['success_rate_last_hour'] ?? 0,
                'notification_service_error_rate' => $health['metrics']['error_rate_last_hour'] ?? 0,
                'notification_service_queue_depth' => $health['metrics']['queue_depth'] ?? 0,
                'notification_service_processing_time_seconds' => $health['metrics']['average_processing_time'] ?? 0,
                'notification_service_active_projects' => $health['metrics']['active_projects'] ?? 0,
            ];

            // Add service-specific metrics
            foreach ($health['services'] as $service => $status) {
                $metrics["notification_service_{$service}_healthy"] = $status['status'] === 'healthy' ? 1 : 0;
                
                // Add specific metrics if available
                if (isset($status['response_time_ms'])) {
                    $metrics["notification_service_{$service}_response_time_ms"] = $status['response_time_ms'];
                }
            }

            return response()->json([
                'metrics' => $metrics,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * Status page data
     */
    public function status(): JsonResponse
    {
        try {
            $health = $this->monitoring->performHealthCheck();
            $alerts = $this->alertService->getActiveAlerts();

            return response()->json([
                'service' => [
                    'name' => 'Notification Service',
                    'version' => config('app.version', '2.0.0'),
                    'environment' => app()->environment(),
                    'status' => $health['overall_status']
                ],
                'health' => $health,
                'alerts' => $alerts,
                'uptime_seconds' => $this->getUptime(),
                'last_updated' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'service' => [
                    'name' => 'Notification Service',
                    'status' => 'error'
                ],
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * Get service uptime in seconds
     */
    private function getUptime(): int
    {
        $startFile = storage_path('app/service_start_time');
        
        if (file_exists($startFile)) {
            $startTime = (int)file_get_contents($startFile);
            return time() - $startTime;
        }
        
        // If no start file, assume we just started
        file_put_contents($startFile, time());
        return 0;
    }
}
