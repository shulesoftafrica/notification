<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;
use App\Services\ProviderFailoverService;
use Illuminate\Support\Facades\Log;

class TestProviderFailover extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:provider-failover {type} {--to=} {--simulate-failure}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test provider failover mechanisms';

    protected $notificationService;
    protected $failoverService;

    public function __construct(NotificationService $notificationService, ProviderFailoverService $failoverService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
        $this->failoverService = $failoverService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->argument('type');
        $recipient = $this->option('to');
        $simulateFailure = $this->option('simulate-failure');

        if (!in_array($type, ['sms', 'email', 'whatsapp'])) {
            $this->error('Invalid notification type. Use: sms, email, or whatsapp');
            return 1;
        }

        if (!$recipient) {
            $recipient = $this->getDefaultRecipient($type);
        }

        $this->info("Testing {$type} provider failover...");
        $this->info("Recipient: {$recipient}");
        $this->newLine();

        try {
            // Show current provider configuration
            $this->displayProviderConfiguration($type);
            
            if ($simulateFailure) {
                $this->info('Simulating primary provider failure...');
                $this->simulateProviderFailure($type);
            }

            // Send test notification
            $result = $this->sendTestNotification($type, $recipient);
            
            $this->displayResult($result);
            
            // Show failover statistics
            $this->displayFailoverStats($type);
            
            return $result['success'] ? 0 : 1;

        } catch (\Exception $e) {
            $this->error('Test failed: ' . $e->getMessage());
            Log::error('Provider failover test failed', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return 1;
        }
    }

    protected function getDefaultRecipient(string $type): string
    {
        switch ($type) {
            case 'sms':
            case 'whatsapp':
                return '+1234567890'; // Test phone number
            case 'email':
                return 'test@example.com';
            default:
                return 'test@example.com';
        }
    }

    protected function displayProviderConfiguration(string $type): void
    {
        $this->info('Current Provider Configuration:');
        
        $providers = $this->failoverService->getProvidersForType($type);
        
        foreach ($providers as $index => $provider) {
            $status = $this->failoverService->isProviderHealthy($provider) ? 'HEALTHY' : 'UNHEALTHY';
            $color = $status === 'HEALTHY' ? 'green' : 'red';
            $priority = $index + 1;
            
            $this->line("  {$priority}. {$provider}: <fg={$color}>{$status}</>");
        }
        
        $this->newLine();
    }

    protected function simulateProviderFailure(string $type): void
    {
        $primaryProvider = $this->failoverService->getPrimaryProvider($type);
        $this->failoverService->markProviderAsUnhealthy($primaryProvider, 'Simulated failure for testing');
        
        $this->warn("Marked {$primaryProvider} as unhealthy for testing");
        $this->newLine();
    }

    protected function sendTestNotification(string $type, string $recipient): array
    {
        $message = "Test {$type} notification sent at " . now()->format('Y-m-d H:i:s');
        
        $this->info('Sending test notification...');
        
        $startTime = microtime(true);
        
        switch ($type) {
            case 'sms':
                $result = $this->notificationService->sendSms($recipient, $message);
                break;
            case 'email':
                $result = $this->notificationService->sendEmail($recipient, 'Test Email', $message);
                break;
            case 'whatsapp':
                $result = $this->notificationService->sendWhatsApp($recipient, $message);
                break;
            default:
                throw new \InvalidArgumentException('Invalid notification type');
        }
        
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        return array_merge($result, ['duration' => $duration]);
    }

    protected function displayResult(array $result): void
    {
        $this->newLine();
        
        if ($result['success']) {
            $this->info('✓ Test notification sent successfully!');
            $this->line("  Provider Used: {$result['provider']}");
            $this->line("  Message ID: {$result['message_id']}");
            $this->line("  Duration: {$result['duration']}ms");
            
            if (isset($result['failover_occurred']) && $result['failover_occurred']) {
                $this->warn("  Failover occurred from {$result['original_provider']} to {$result['provider']}");
            }
        } else {
            $this->error('✗ Test notification failed!');
            $this->line("  Error: {$result['error']}");
            
            if (isset($result['failed_providers'])) {
                $this->line("  Failed Providers: " . implode(', ', $result['failed_providers']));
            }
        }
        
        $this->newLine();
    }

    protected function displayFailoverStats(string $type): void
    {
        $this->info('Failover Statistics (Last 24 hours):');
        
        $stats = $this->failoverService->getFailoverStats($type, 24);
        
        $this->line("  Total Notifications: {$stats['total']}");
        $this->line("  Successful: {$stats['successful']}");
        $this->line("  Failed: {$stats['failed']}");
        $this->line("  Failovers: {$stats['failovers']}");
        $this->line("  Success Rate: {$stats['success_rate']}%");
        
        if (!empty($stats['provider_usage'])) {
            $this->newLine();
            $this->info('Provider Usage:');
            foreach ($stats['provider_usage'] as $provider => $count) {
                $this->line("  {$provider}: {$count} messages");
            }
        }
    }
}
