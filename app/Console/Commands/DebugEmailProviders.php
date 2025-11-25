<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DebugEmailProviders extends Command
{
    protected $signature = 'debug:email-providers';
    protected $description = 'Debug email provider configuration';

    public function handle()
    {
        $this->info('=== Email Provider Debug ===');
        $this->newLine();

        // Check configuration
        $emailConfig = config('notification.channels.email', []);
        $providers = config('notification.providers', []);

        $this->info('1. Email Channel Configuration:');
        $this->line(json_encode($emailConfig, JSON_PRETTY_PRINT));
        $this->newLine();

        $this->info('2. Available Email Providers:');
        foreach ($emailConfig['providers'] ?? [] as $provider) {
            $providerConfig = $providers[$provider] ?? null;
            if ($providerConfig) {
                $this->line("✅ {$provider}:");
                $this->line("   Priority: {$providerConfig['priority']}");
                $this->line("   Enabled: " . ($providerConfig['enabled'] ? 'Yes' : 'No'));
                if ($provider === 'resend') {
                    $apiKey = $providerConfig['api_key'] ?? 'Not set';
                    $this->line("   API Key: " . (strlen($apiKey) > 10 ? substr($apiKey, 0, 10) . '...' : $apiKey));
                }
            } else {
                $this->line("❌ {$provider}: Configuration missing");
            }
            $this->newLine();
        }

        // Test provider selection
        $this->info('3. Testing Provider Selection:');
        try {
            $failoverService = app(\App\Services\ProviderFailoverService::class);
            $selectedProvider = $failoverService->selectProvider('email');
            $this->line("Selected provider: {$selectedProvider}");
        } catch (\Exception $e) {
            $this->error("Provider selection failed: {$e->getMessage()}");
        }

        return Command::SUCCESS;
    }
}