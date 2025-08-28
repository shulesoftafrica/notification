<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;

class TestSmsIntegration extends Command
{
    protected $signature = 'test:sms {phone?} {message?}';
    protected $description = 'Test SMS integration with Beem/Termii';

    public function handle()
    {
        $this->info('=== Testing SMS Integration ===');
        $this->newLine();

        $phone = $this->argument('phone') ?: $this->ask('Enter phone number (e.g., +255712345678 for Tanzania, +2348012345678 for Nigeria)');
        $message = $this->argument('message') ?: $this->ask('Enter test message', 'Hello! This is a test from the notification service.');

        if (!$phone) {
            $this->error('Phone number is required');
            return Command::FAILURE;
        }

        $this->info("Testing SMS to: {$phone}");
        $this->info("Message: {$message}");
        $this->newLine();

        // Detect country from phone number
        $country = null;
        if (preg_match('/^\+255/', $phone)) {
            $country = 'TZ';
            $this->info('🇹🇿 Detected Tanzania number - should use Beem');
        } elseif (preg_match('/^\+234/', $phone)) {
            $country = 'NG';
            $this->info('🇳🇬 Detected Nigeria number - should use Termii');
        } else {
            $this->info('🌍 Unknown country - will use default provider');
        }

        $this->newLine();

        try {
            $notificationService = app(NotificationService::class);
            
            // Check which provider would be selected
            if ($country) {
                $selectedProvider = $notificationService->getProviderForCountry('sms', $country);
                $this->info("Selected provider: {$selectedProvider}");
                $this->newLine();
            }

            // Send the notification
            $this->info('📤 Sending SMS...');
            
            $result = $notificationService->send('sms', $phone, $message, 'Test SMS');
            
            $this->newLine();
            $this->info('✅ SMS sent successfully!');
            $this->line("Message ID: {$result['message_id']}");
            $this->line("Provider: {$result['provider']}");
            $this->line("Status: {$result['status']}");
            
            if (isset($result['provider_message_id'])) {
                $this->line("Provider Message ID: {$result['provider_message_id']}");
            }
            
            if (isset($result['send_time'])) {
                $this->line("Send Time: {$result['send_time']}ms");
            }

            $this->newLine();
            $this->comment('Check the notification_logs table to see the full details:');
            $this->line("SELECT * FROM notification_logs WHERE id = '{$result['message_id']}';");

        } catch (\Exception $e) {
            $this->error('❌ SMS sending failed!');
            $this->error("Error: {$e->getMessage()}");
            
            $this->newLine();
            $this->comment('Common issues:');
            $this->line('1. Invalid API credentials in .env file');
            $this->line('2. Provider service is down');
            $this->line('3. Invalid phone number format');
            $this->line('4. Insufficient balance in provider account');
            
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
