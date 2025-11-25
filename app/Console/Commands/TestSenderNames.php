<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;

class TestSenderNames extends Command
{
    protected $signature = 'test:sender-names';
    protected $description = 'Test SMS sender name functionality';

    public function handle()
    {
        $this->info('=== Testing SMS Sender Name Functionality ===');
        $this->newLine();

        $notificationService = app(NotificationService::class);

        // Test 1: Tanzania number with custom sender name
        $this->info('1. Testing Tanzania SMS with custom sender "MYCOMPANY"');
        try {
            $data1 = [
                'channel' => 'sms',
                'to' => '+255712345678',
                'message' => 'Hello from custom sender MYCOMPANY!',
                'sender_name' => 'MYCOMPANY',
                'priority' => 'normal'
            ];
            
            $result1 = $notificationService->send($data1);
            $this->info("✅ Success! Provider: {$result1['provider']}, Status: {$result1['status']}, Message ID: {$result1['message_id']}");
        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
        }

        $this->newLine();

        // Test 2: Nigeria number with custom sender name
        $this->info('2. Testing Nigeria SMS with custom sender "TESTCO"');
        try {
            $data2 = [
                'channel' => 'sms',
                'to' => '+2348012345678',
                'message' => 'Hello from custom sender TESTCO!',
                'sender_name' => 'TESTCO',
                'priority' => 'normal'
            ];
            
            $result2 = $notificationService->send($data2);
            $this->info("✅ Success! Provider: {$result2['provider']}, Status: {$result2['status']}, Message ID: {$result2['message_id']}");
        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
        }

        $this->newLine();

        // Test 3: Default sender name (no sender_name provided)
        $this->info('3. Testing SMS with default sender name');
        try {
            $data3 = [
                'channel' => 'sms',
                'to' => '+255712345678',
                'message' => 'Hello with default sender!',
                'priority' => 'normal'
            ];
            
            $result3 = $notificationService->send($data3);
            $this->info("✅ Success! Provider: {$result3['provider']}, Status: {$result3['status']}, Message ID: {$result3['message_id']}");
        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
        }

        $this->newLine();
        $this->info('=== Test Complete ===');
        
        $this->comment('To verify sender names were used correctly, check the notification_logs table:');
        $this->line('SELECT * FROM notification_logs ORDER BY created_at DESC LIMIT 3;');

        return Command::SUCCESS;
    }
}