<?php

namespace App\Console\Commands;

use App\Services\ProviderHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorProviderHealth extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notification:monitor-health 
                            {--reset : Reset all provider health scores}
                            {--check : Perform health checks for all providers}
                            {--provider= : Check specific provider only}';

    /**
     * The console command description.
     */
    protected $description = 'Monitor and manage notification provider health';

    /**
     * Execute the console command.
     */
    public function handle(ProviderHealthService $healthService): int
    {
        if ($this->option('reset')) {
            return $this->resetHealth($healthService);
        }

        if ($this->option('check')) {
            return $this->performHealthChecks($healthService);
        }

        return $this->showHealthStatus($healthService);
    }

    /**
     * Reset all provider health scores
     */
    private function resetHealth(ProviderHealthService $healthService): int
    {
        $this->info('Resetting provider health scores...');
        
        $allProviders = collect(config('notification.providers', []))
            ->flatMap(fn($providers) => array_keys($providers))
            ->unique();

        foreach ($allProviders as $provider) {
            $healthService->resetProvider($provider);
            $this->line("  ✅ Reset {$provider}");
        }

        $this->info('All provider health scores have been reset.');
        return 0;
    }

    /**
     * Perform health checks for all providers
     */
    private function performHealthChecks(ProviderHealthService $healthService): int
    {
        $provider = $this->option('provider');
        
        if ($provider) {
            return $this->checkSingleProvider($healthService, $provider);
        }

        $this->info('Performing health checks for all providers...');
        
        $allProviders = collect(config('notification.providers', []))
            ->flatMap(fn($providers, $type) => 
                collect($providers)->mapWithKeys(fn($config, $provider) => 
                    [$provider => array_merge($config, ['type' => $type])]
                )
            );

        $results = [];
        
        foreach ($allProviders as $providerId => $config) {
            $this->line("\nChecking {$config['name']} ({$providerId})...");
            
            try {
                $result = $healthService->performHealthCheck($providerId);
                $results[$providerId] = $result;
                
                if ($result['healthy']) {
                    $this->info("  ✅ Healthy (Response time: {$result['response_time']}ms)");
                } else {
                    $this->warn("  ❌ Unhealthy: {$result['error']}");
                }
            } catch (\Exception $e) {
                $this->error("  💥 Error: {$e->getMessage()}");
                $results[$providerId] = [
                    'healthy' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->showHealthSummary($results);
        return 0;
    }

    /**
     * Check a single provider
     */
    private function checkSingleProvider(ProviderHealthService $healthService, string $provider): int
    {
        $this->info("Checking provider: {$provider}");
        
        try {
            $result = $healthService->performHealthCheck($provider);
            
            if ($result['healthy']) {
                $this->info("✅ Provider {$provider} is healthy");
                $this->line("Response time: {$result['response_time']}ms");
            } else {
                $this->warn("❌ Provider {$provider} is unhealthy");
                $this->line("Error: {$result['error']}");
            }
            
            $score = $healthService->getHealthScore($provider);
            $this->line("Health score: {$score}/100");
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Error checking provider {$provider}: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Show current health status for all providers
     */
    private function showHealthStatus(ProviderHealthService $healthService): int
    {
        $this->info('Provider Health Status Dashboard');
        $this->line(str_repeat('=', 50));

        $allProviders = collect(config('notification.providers', []))
            ->flatMap(fn($providers, $type) => 
                collect($providers)->mapWithKeys(fn($config, $provider) => 
                    [$provider => array_merge($config, ['type' => $type])]
                )
            );

        foreach (['email', 'sms', 'whatsapp'] as $type) {
            $typeProviders = $allProviders->where('type', $type);
            
            if ($typeProviders->isEmpty()) {
                continue;
            }

            $this->info("\n" . strtoupper($type) . " Providers:");
            
            foreach ($typeProviders as $providerId => $config) {
                $isAvailable = $healthService->isProviderAvailable($providerId);
                $healthScore = $healthService->getHealthScore($providerId);
                $circuitState = $healthService->getCircuitState($providerId);
                
                $status = $isAvailable ? '🟢' : '🔴';
                $circuit = match($circuitState) {
                    'closed' => '🔒 Closed',
                    'open' => '🔓 Open',
                    'half_open' => '🔄 Half-Open',
                    default => '❓ Unknown'
                };
                
                $this->line(sprintf(
                    "  %s %-20s Priority: %d | Score: %3d/100 | Circuit: %s",
                    $status,
                    $config['name'],
                    $config['priority'],
                    $healthScore,
                    $circuit
                ));
            }
        }

        $this->line("\n" . str_repeat('=', 50));
        $this->info('Use --check to perform health checks');
        $this->info('Use --reset to reset all health scores');
        
        return 0;
    }

    /**
     * Show health check summary
     */
    private function showHealthSummary(array $results): void
    {
        $healthy = collect($results)->where('healthy', true)->count();
        $total = count($results);
        $unhealthy = $total - $healthy;

        $this->line("\n" . str_repeat('=', 50));
        $this->info("Health Check Summary:");
        $this->line("  Total providers: {$total}");
        $this->line("  Healthy: {$healthy}");
        
        if ($unhealthy > 0) {
            $this->warn("  Unhealthy: {$unhealthy}");
        }

        $successRate = $total > 0 ? round(($healthy / $total) * 100, 1) : 0;
        $this->line("  Success rate: {$successRate}%");
    }
}
