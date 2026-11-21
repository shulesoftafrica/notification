<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;
use App\Services\Adapters\SmsAdapter;

class TestBeemTermiiIntegration extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:beem-termii';

    /**
     * The console command description.
     */
    protected $description = 'Test Beem and Termii SMS provider integration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Testing Beem and Termii SMS Integration ===');
        $this->newLine();

        // Test 1: Configuration loading
        $this->info('1. Testing configuration loading...');
        $config = config('notification');
        $providers = $config['providers'] ?? [];

        // Check Beem configuration
        if (isset($providers['beem'])) {
            $this->info('✅ Beem configuration loaded');
            $this->line('   - Countries: ' . implode(', ', $providers['beem']['countries']));
            $this->line('   - Priority: ' . $providers['beem']['priority']);
        } else {
            $this->error('❌ Beem configuration missing');
        }

        // Check Termii configuration
        if (isset($providers['termii'])) {
            $this->info('✅ Termii configuration loaded');
            $this->line('   - Countries: ' . implode(', ', $providers['termii']['countries']));
            $this->line('   - Priority: ' . $providers['termii']['priority']);
        } else {
            $this->error('❌ Termii configuration missing');
        }

        $this->newLine();

        // Test 2: Provider selection
        $this->info('2. Testing provider selection for different countries...');
        
        $notificationService = app(NotificationService::class);

        // Test Tanzania number - should select Beem
        $this->line('Testing Tanzania number (+255...):');
        try {
            $providerForTZ = $notificationService->getProviderForCountry('sms', 'TZ');
            $this->line('   Selected provider: ' . ($providerForTZ ?: 'none'));
        } catch (\Exception $e) {
            $this->error('   Error: ' . $e->getMessage());
        }

        // Test Nigeria number - should select Termii
        $this->line('Testing Nigeria number (+234...):');
        try {
            $providerForNG = $notificationService->getProviderForCountry('sms', 'NG');
            $this->line('   Selected provider: ' . ($providerForNG ?: 'none'));
        } catch (\Exception $e) {
            $this->error('   Error: ' . $e->getMessage());
        }

        $this->newLine();

        // Test 3: Adapter creation
        $this->info('3. Testing adapter creation...');

        // Test Beem adapter
        $this->line('Creating Beem adapter:');
        try {
            $beemConfig = $providers['beem'] ?? [];
            $beemAdapter = new SmsAdapter($beemConfig, 'beem');
            $this->info('✅ Beem adapter created successfully');
        } catch (\Exception $e) {
            $this->error('❌ Beem adapter error: ' . $e->getMessage());
        }

        // Test Termii adapter
        $this->line('Creating Termii adapter:');
        try {
            $termiiConfig = $providers['termii'] ?? [];
            $termiiAdapter = new SmsAdapter($termiiConfig, 'termii');
            $this->info('✅ Termii adapter created successfully');
        } catch (\Exception $e) {
            $this->error('❌ Termii adapter error: ' . $e->getMessage());
        }

        $this->newLine();

        // Test 4: Health checks (if credentials available)
        $this->info('4. Testing health checks (requires API credentials)...');

        // Test Beem health check
        $this->line('Testing Beem health check:');
        if (isset($beemAdapter)) {
            try {
                $beemHealth = $beemAdapter->isHealthy();
                $this->line('   Beem health: ' . ($beemHealth ? "✅ Healthy" : "❌ Unhealthy"));
            } catch (\Exception $e) {
                $this->line('   Beem health check error: ' . $e->getMessage());
            }
        } else {
            $this->line('   Skipped - adapter not created');
        }

        // Test Termii health check
        $this->line('Testing Termii health check:');
        if (isset($termiiAdapter)) {
            try {
                $termiiHealth = $termiiAdapter->isHealthy();
                $this->line('   Termii health: ' . ($termiiHealth ? "✅ Healthy" : "❌ Unhealthy"));
            } catch (\Exception $e) {
                $this->line('   Termii health check error: ' . $e->getMessage());
            }
        } else {
            $this->line('   Skipped - adapter not created');
        }

        $this->newLine();

        // Test 5: Configuration summary
        $this->info('5. Configuration Summary:');
        $this->line('SMS providers configured:');
        foreach ($config['channels']['sms']['providers'] ?? [] as $provider) {
            $providerConfig = $providers[$provider] ?? null;
            if ($providerConfig) {
                $line = "   - {$provider}: priority {$providerConfig['priority']}";
                if (isset($providerConfig['countries'])) {
                    $line .= " (countries: " . implode(', ', $providerConfig['countries']) . ")";
                }
                $this->line($line);
            }
        }

        $this->newLine();
        $this->info('=== Integration Test Complete ===');
        $this->newLine();
        
        $this->comment('Next steps:');
        $this->line('1. Set up API credentials in .env file:');
        $this->line('   BEEM_API_KEY=your_api_key');
        $this->line('   BEEM_SECRET_KEY=your_secret_key');
        $this->line('   TERMII_API_KEY=your_api_key');
        $this->newLine();
        $this->line('2. Test with real SMS sending:');
        $this->line('   php artisan tinker');
        $this->line('   >> $service = app(\\App\\Services\\NotificationService::class);');
        $this->line('   >> $service->send(\'sms\', \'+255712345678\', \'Test message\', \'Test Subject\');');
        $this->newLine();
        $this->line('3. Monitor logs for provider selection and sending:');
        $this->line('   tail -f storage/logs/laravel.log');

        return Command::SUCCESS;
    }
}
