<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Services\ProviderFailoverService;
use App\Services\ProviderHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestProviderFailover extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notification:test-failover 
                            {type : The notification type (email, sms, whatsapp)}
                            {--provider= : Specific provider to test}
                            {--force-failure : Force a provider failure for testing}';

    /**
     * The console command description.
     */
    protected $description = 'Test notification provider failover and health monitoring';

    /**
     * Execute the console command.
     */
    public function handle(
        ProviderFailoverService $failoverService,
        ProviderHealthService $healthService
    ): int {
        $type = $this->argument('type');
        $provider = $this->option('provider');
        $forceFailure = $this->option('force-failure');

        $this->info("Testing {$type} provider failover...");

        if ($forceFailure && $provider) {
            $this->info("Forcing failure for provider: {$provider}");
            $healthService->recordFailure($provider, 'Forced failure for testing');
            $this->warn("Provider {$provider} has been marked as failed");
        }

        // Test getting the best provider
        $testMessage = $this->createTestMessage($type);
        $bestProvider = $failoverService->getBestProvider($testMessage);
        
        if ($bestProvider) {
            $this->info("Best available provider: {$bestProvider->provider}");
            
            // Show provider health scores
            $this->showProviderHealth($healthService, $type);
            
            // Test sending with failover
            $this->testSending($failoverService, $type);
        } else {
            $this->error("No healthy providers available for {$type}");
            return 1;
        }

        return 0;
    }

    /**
     * Show provider health information
     */
    private function showProviderHealth(ProviderHealthService $healthService, string $type): void
    {
        $this->info("\nProvider Health Status:");
        
        $providers = config("notification.providers.{$type}", []);
        
        foreach ($providers as $providerId => $config) {
            $isAvailable = $healthService->isProviderAvailable($providerId);
            $healthScore = $healthService->getHealthScore($providerId);
            
            $status = $isAvailable ? '🟢 Available' : '🔴 Unavailable';
            $this->line("  {$config['name']} ({$providerId}): {$status} (Score: {$healthScore}/100)");
        }
    }

    /**
     * Test sending with failover
     */
    private function testSending(ProviderFailoverService $failoverService, string $type): void
    {
        $this->info("\nTesting provider selection and scoring...");
        
        $testMessage = $this->createTestMessage($type);
        
        try {
            $bestProvider = $failoverService->getBestProvider($testMessage);
            
            if ($bestProvider) {
                $this->info("✅ Provider selection successful!");
                $this->line("Selected provider: {$bestProvider->provider}");
                $this->line("Provider ID: {$bestProvider->id}");
                $this->line("Priority: {$bestProvider->priority}");
                $this->line("Weight: {$bestProvider->weight}");
            } else {
                $this->error("❌ No provider selected");
            }
            
        } catch (\Exception $e) {
            $this->error("Error during provider selection: {$e->getMessage()}");
        }
    }

    /**
     * Get test data for the notification type
     */
    private function getTestData(string $type): array
    {
        return match($type) {
            'email' => [
                'to' => 'test@example.com',
                'subject' => 'Failover Test Email',
                'body' => 'This is a test email for failover testing.',
            ],
            'sms' => [
                'to' => '+1234567890',
                'message' => 'This is a test SMS for failover testing.',
            ],
            'whatsapp' => [
                'to' => '+1234567890',
                'message' => 'This is a test WhatsApp message for failover testing.',
            ],
            default => []
        };
    }

    /**
     * Create a test message object for testing
     */
    private function createTestMessage(string $type): Message
    {
        $testData = $this->getTestData($type);
        
        // Create a temporary message object for testing
        $message = new Message();
        $message->message_id = 'test_' . uniqid();
        $message->project_id = 'test_project';
        $message->tenant_id = 'test_tenant';
        $message->channel = $type;
        $message->to = $testData['to'] ?? '';
        $message->subject = $testData['subject'] ?? '';
        $message->body = $testData['message'] ?? $testData['body'] ?? '';
        $message->status = 'pending';
        
        return $message;
    }
}
