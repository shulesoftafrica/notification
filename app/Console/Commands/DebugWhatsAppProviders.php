<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ProviderFailoverService;

class DebugWhatsAppProviders extends Command
{
    protected $signature = 'debug:whatsapp-providers';
    protected $description = 'Debug WhatsApp provider availability';

    public function handle()
    {
        $this->info('=== Debugging WhatsApp Providers ===');
        $this->newLine();

        // Check configuration
        $this->info('1. Configuration Check:');
        
        $whatsappConfig = config('notification.providers.whatsapp', []);
        $wasenderConfig = config('notification.providers.wasender', []);
        $channelConfig = config('notification.channels.whatsapp', []);
        
        $this->line('WhatsApp config exists: ' . (empty($whatsappConfig) ? '❌' : '✅'));
        $this->line('Wasender config exists: ' . (empty($wasenderConfig) ? '❌' : '✅'));
        $this->line('Channel providers: ' . json_encode($channelConfig['providers'] ?? []));
        
        $this->newLine();
        
        // Check provider availability
        $this->info('2. Provider Availability:');
        
        $failoverService = app(ProviderFailoverService::class);
        
        $whatsappAvailable = $failoverService->isProviderAvailable('whatsapp');
        $wasenderAvailable = $failoverService->isProviderAvailable('wasender');
        
        $this->line('WhatsApp available: ' . ($whatsappAvailable ? '✅' : '❌'));
        $this->line('Wasender available: ' . ($wasenderAvailable ? '✅' : '❌'));
        
        $this->newLine();
        
        // Test provider selection
        $this->info('3. Provider Selection Test:');
        
        try {
            $selectedProvider = $failoverService->selectProvider('whatsapp');
            $this->line('Selected provider: ' . $selectedProvider);
        } catch (\Exception $e) {
            $this->error('Provider selection failed: ' . $e->getMessage());
        }
        
        $this->newLine();
        
        // Check individual configs
        $this->info('4. Detailed Configuration:');
        $this->line('WhatsApp config:');
        $this->line(json_encode($whatsappConfig, JSON_PRETTY_PRINT));
        $this->newLine();
        $this->line('Wasender config:');
        $this->line(json_encode($wasenderConfig, JSON_PRETTY_PRINT));
        
        return Command::SUCCESS;
    }
}