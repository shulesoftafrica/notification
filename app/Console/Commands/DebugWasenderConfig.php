<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DebugWasenderConfig extends Command
{
    protected $signature = 'debug:wasender-config';
    protected $description = 'Debug Wasender configuration';

    public function handle()
    {
        $this->info('=== Debugging Wasender Configuration ===');
        $this->newLine();

        // Check environment variables
        $this->info('1. Environment Variables:');
        $this->line('WASENDER_API_URL: ' . (env('WASENDER_API_URL') ?: 'NOT SET'));
        $this->line('WASENDER_API_KEY: ' . (env('WASENDER_API_KEY') ?: 'NOT SET'));
        $this->line('WASENDER_DEVICE_ID: ' . (env('WASENDER_DEVICE_ID') ?: 'NOT SET'));
        $this->newLine();

        // Check config
        $this->info('2. Configuration Array:');
        $config = config('notification.providers.wasender', []);
        $this->line(json_encode($config, JSON_PRETTY_PRINT));
        $this->newLine();

        // Check specific keys
        $this->info('3. Configuration Keys Check:');
        $this->line('api_url exists: ' . (isset($config['api_url']) ? '✅ YES' : '❌ NO'));
        $this->line('api_key exists: ' . (isset($config['api_key']) ? '✅ YES' : '❌ NO'));
        $this->line('device_id exists: ' . (isset($config['device_id']) ? '✅ YES' : '❌ NO'));
        $this->newLine();

        // Test adapter creation
        $this->info('4. Testing Adapter Creation:');
        try {
            $adapter = new \App\Services\Adapters\WhatsAppAdapter($config, 'wasender');
            $this->info('✅ Adapter created successfully');
        } catch (\Exception $e) {
            $this->error('❌ Adapter creation failed: ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}