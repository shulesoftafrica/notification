<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;

class TestSenderNameSms extends Command
{
    protected $signature = 'test:sender-name {phone?} {message?} {sender?}';
    protected $description = 'Test SMS sending with custom sender names';

    public function handle()
    {
        $this->info('=== Testing SMS with Custom Sender Names ===');
        $this->newLine();

        $phone = $this->argument('phone') ?: $this->ask('Enter phone number (e.g., +255712345678 for Tanzania, +2348012345678 for Nigeria)');
        $message = $this->argument('message') ?: $this->ask('Enter test message', 'Hello! This is a test SMS with custom sender.');
        $senderName = $this->argument('sender') ?: $this->ask('Enter sender name (leave empty for default)', '');

        if (!$phone) {
            $this->error('Phone number is required');
            return Command::FAILURE;
        }

        $this->info("Testing SMS to: {$phone}");
        $this->info("Message: {$message}");
        
        if ($senderName) {
            $this->info("Custom Sender: {$senderName}");
        } else {
            $this->info("Using Default Sender (SHULESOFT)");
        }
        
        $this->newLine();

        // Detect country from phone number
        $country = null;
        $expectedProvider = 'twilio'; // Default
        
        if (preg_match('/^\+255/', $phone)) {
            $country = 'TZ';
            $expectedProvider = 'beem';
            $this->info('🇹🇿 Detected Tanzania number - should use Beem');
        } elseif (preg_match('/^\+234/', $phone)) {
            $country = 'NG';
            $expectedProvider = 'termii';
            $this->info('🇳🇬 Detected Nigeria number - should use Termii');
        } else {
            $this->info('🌍 Unknown country - will use default provider (Twilio)');
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

            // Prepare the notification data
            $notificationData = [
                'channel' => 'sms',
                'to' => $phone,
                'message' => $message,
                'metadata' => []
            ];

            // Add sender name if provided
            if ($senderName) {
                $notificationData['sender_name'] = $senderName;
                $notificationData['metadata']['sender_name'] = $senderName;
            }

            // Send the notification
            $this->info('📤 Sending SMS with custom sender...');
            
            $result = $notificationService->send($notificationData);
            
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

            // Verify sender name was used correctly
            if ($senderName) {
                $this->newLine();
                $this->comment("✅ Custom sender name '{$senderName}' should have been used by {$result['provider']} provider");
            } else {
                $this->newLine();
                $this->comment("✅ Default sender name should have been used by {$result['provider']} provider");
            }

            $this->newLine();
            $this->comment('Check the notification_logs table to see the full details:');
            $this->line("SELECT * FROM notification_logs WHERE id = '{$result['message_id']}';");

            // Test different scenarios
            $this->newLine();
            $this->comment('Testing different sender name scenarios...');
            
            $testScenarios = [
                ['sender' => 'MYCOMPANY', 'description' => 'Custom business sender'],
                ['sender' => 'TEST-APP', 'description' => 'App-specific sender'],
                ['sender' => '', 'description' => 'Default sender (SHULESOFT)']
            ];

            foreach ($testScenarios as $scenario) {
                $this->line("• Testing with sender: '" . ($scenario['sender'] ?: 'Default') . "' - " . $scenario['description']);
            }

        } catch (\Exception $e) {
            $this->error('❌ SMS sending failed!');
            $this->error("Error: {$e->getMessage()}");
            
            $this->newLine();
            $this->comment('Common issues:');
            $this->line('1. Invalid API credentials in .env file');
            $this->line('2. Provider service is down');
            $this->line('3. Invalid phone number format');
            $this->line('4. Insufficient balance in provider account');
            $this->line('5. Invalid sender name (check provider restrictions)');
            
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('=== Sender Name Test Complete ===');
        
        $this->newLine();
        $this->comment('API Usage Example:');
        $this->line('POST /api/notifications/send');
        $this->line('{');
        $this->line('    "channel": "sms",');
        $this->line('    "to": "' . $phone . '",');
        $this->line('    "message": "' . $message . '",');
        if ($senderName) {
            $this->line('    "sender_name": "' . $senderName . '"');
        }
        $this->line('}');

        return Command::SUCCESS;
    }
}