<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ProviderHealthService;
use App\Services\AlertService;
use Illuminate\Support\Facades\Log;

class MonitorProviderHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:provider-health {--provider=} {--alert}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor notification provider health status';

    protected $providerHealthService;
    protected $alertService;

    public function __construct(ProviderHealthService $providerHealthService, AlertService $alertService)
    {
        parent::__construct();
        $this->providerHealthService = $providerHealthService;
        $this->alertService = $alertService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $specificProvider = $this->option('provider');
        $shouldAlert = $this->option('alert');

        $this->info('Starting provider health monitoring...');
        $this->newLine();

        $providers = $specificProvider ? [$specificProvider] : ['twilio', 'whatsapp', 'sendgrid', 'mailgun'];
        $healthResults = [];

        foreach ($providers as $provider) {
            $this->info("Checking {$provider} health...");
            
            try {
                $isHealthy = $this->providerHealthService->checkProviderHealth($provider);
                $status = $isHealthy ? 'HEALTHY' : 'UNHEALTHY';
                $color = $isHealthy ? 'green' : 'red';
                
                $this->line("  Status: <fg={$color}>{$status}</>");
                
                // Get detailed health metrics
                $metrics = $this->providerHealthService->getProviderMetrics($provider);
                if ($metrics) {
                    $this->line("  Response Time: {$metrics['response_time']}ms");
                    $this->line("  Success Rate: {$metrics['success_rate']}%");
                    $this->line("  Last Check: {$metrics['last_check']}");
                }

                $healthResults[$provider] = [
                    'healthy' => $isHealthy,
                    'metrics' => $metrics
                ];

                // Send alert if provider is unhealthy
                if (!$isHealthy && $shouldAlert) {
                    $this->alertService->sendProviderDownAlert($provider, $metrics);
                    $this->warn("  Alert sent for unhealthy provider!");
                }

            } catch (\Exception $e) {
                $this->error("  Error checking {$provider}: " . $e->getMessage());
                Log::error("Provider health check failed", [
                    'provider' => $provider,
                    'error' => $e->getMessage()
                ]);

                $healthResults[$provider] = [
                    'healthy' => false,
                    'error' => $e->getMessage()
                ];

                if ($shouldAlert) {
                    $this->alertService->sendProviderErrorAlert($provider, $e->getMessage());
                }
            }

            $this->newLine();
        }

        // Summary
        $this->info('Health Check Summary:');
        $healthyCount = collect($healthResults)->where('healthy', true)->count();
        $totalCount = count($healthResults);
        
        $this->line("Healthy Providers: {$healthyCount}/{$totalCount}");
        
        if ($healthyCount < $totalCount) {
            $this->warn('Some providers are experiencing issues!');
            return 1;
        }

        $this->info('All providers are healthy!');
        return 0;
    }
}
