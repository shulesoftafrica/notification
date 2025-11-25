<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ProviderHealthService;
use App\Services\MetricsService;

class HealthController extends Controller
{
    protected $healthService;
    protected $metricsService;

    public function __construct(ProviderHealthService $healthService, MetricsService $metricsService)
    {
        $this->healthService = $healthService;
        $this->metricsService = $metricsService;
    }

    /**
     * Basic health check endpoint
     */
    public function check()
    {
        try {
            // Check database connection
            $dbHealth = $this->checkDatabase();
            
            // Check cache
            $cacheHealth = $this->checkCache();
            
            // Get overall health
            $overallHealth = $dbHealth && $cacheHealth;

            return response()->json([
                'status' => $overallHealth ? 'healthy' : 'unhealthy',
                'timestamp' => now()->toISOString(),
                'checks' => [
                    'database' => $dbHealth,
                    'cache' => $cacheHealth,
                ],
                'uptime' => $this->getUptime(),
            ], $overallHealth ? 200 : 503);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 503);
        }
    }

    /**
     * Detailed health check with all services
     */
    public function detailed()
    {
        try {
            $startTime = microtime(true);

            // Basic health checks
            $dbHealth = $this->checkDatabase();
            $cacheHealth = $this->checkCache();
            
            // Provider health checks
            $providerHealth = $this->healthService->checkAllProviders();
            
            // Get metrics summary
            $metrics = $this->metricsService->getMetricsSummary('1h');

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            $overallHealth = $dbHealth && $cacheHealth && 
                           collect($providerHealth)->where('healthy', true)->count() > 0;

            return response()->json([
                'status' => $overallHealth ? 'healthy' : 'unhealthy',
                'timestamp' => now()->toISOString(),
                'response_time_ms' => $responseTime,
                'checks' => [
                    'database' => $dbHealth,
                    'cache' => $cacheHealth,
                    'providers' => $providerHealth,
                ],
                'metrics' => $metrics,
                'uptime' => $this->getUptime(),
                'environment' => app()->environment(),
                'version' => config('app.version', '1.0.0'),
            ], $overallHealth ? 200 : 503);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 503);
        }
    }

    /**
     * Check database connectivity
     */
    protected function checkDatabase()
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check cache connectivity
     */
    protected function checkCache()
    {
        try {
            cache()->put('health_check', true, 10);
            $result = cache()->get('health_check');
            cache()->forget('health_check');
            return $result === true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get application uptime
     */
    protected function getUptime()
    {
        try {
            $uptimeFile = storage_path('framework/cache/uptime');
            
            if (!file_exists($uptimeFile)) {
                file_put_contents($uptimeFile, now()->timestamp);
            }
            
            $startTime = file_get_contents($uptimeFile);
            return now()->timestamp - $startTime;
            
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Provider-specific health check
     */
    public function provider($provider)
    {
        try {
            $health = $this->healthService->checkProvider($provider);
            
            return response()->json([
                'provider' => $provider,
                'health' => $health,
                'timestamp' => now()->toISOString(),
            ], $health['healthy'] ? 200 : 503);
            
        } catch (\Exception $e) {
            return response()->json([
                'provider' => $provider,
                'status' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Refresh provider health cache
     */
    public function refreshProvider($provider)
    {
        try {
            $health = $this->healthService->refreshProvider($provider);
            
            return response()->json([
                'provider' => $provider,
                'health' => $health,
                'refreshed_at' => now()->toISOString(),
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'provider' => $provider,
                'status' => 'error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get system metrics
     */
    public function metrics(Request $request)
    {
        $period = $request->get('period', '1h');
        
        try {
            $metrics = $this->metricsService->getMetricsSummary($period);
            
            return response()->json([
                'metrics' => $metrics,
                'period' => $period,
                'timestamp' => now()->toISOString(),
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }
}
