<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;

class TestWhatsAppRouting extends Command
{
    protected $signature = 'test:whatsapp-routing {phone?} {message?} {type?}';
    protected $description = 'Test WhatsApp routing between Official WhatsApp and Wasender';

    public function handle()
    {
        $this->info('=== Testing WhatsApp Routing System ===');
        $this->newLine();

        $phone = $this->argument('phone') ?: $this->ask('Enter WhatsApp phone number (e.g., +255712345678)');
        $message = $this->argument('message') ?: $this->ask('Enter test message', 'Hello! This is a test WhatsApp message.');
        $type = $this->argument('type') ?: $this->choice('Select WhatsApp provider type:', ['official', 'wasender'], 0);

        if (!$phone) {
            $this->error('Phone number is required');
            return Command::FAILURE;
        }

        $this->info("Testing WhatsApp message:");
        $this->info("📱 Phone: {$phone}");
        $this->info("💬 Message: {$message}");
        $this->info("🔀 Type: {$type}");
        $this->newLine();

        try {
            $notificationService = app(NotificationService::class);

            $this->info('📤 Sending WhatsApp message...');
            $this->newLine();

            $result = $notificationService->send([
                'channel' => 'whatsapp',
                'to' => $phone,
                'message' => $message,
                'subject' => null,
                'type' => $type,
                'metadata' => [
                    'campaign' => 'whatsapp_routing_test',
                    'type' => $type
                ]
            ]);

            $this->info('✅ WhatsApp message sent successfully!');
            $this->line("Message ID: {$result['message_id']}");
            $this->line("Provider: {$result['provider']}");
            $this->line("Status: {$result['status']}");

            if ($result['provider'] === 'wasender') {
                $this->comment('📡 Sent via Wasender (Third-party WhatsApp API)');
            } else {
                $this->comment('🏢 Sent via Official WhatsApp Business API');
            }

            $this->newLine();
            $this->comment('Check the notification_logs table for full details:');
            $this->line("SELECT * FROM notification_logs WHERE id = '{$result['message_id']}';");

        } catch (\Exception $e) {
            $this->error('❌ WhatsApp message sending failed!');
            $this->error("Error: {$e->getMessage()}");

            $this->newLine();
            $this->comment('Common issues:');
            $this->line('1. Invalid WhatsApp API credentials in .env file');
            $this->line('2. WhatsApp provider service is down');
            $this->line('3. Invalid phone number format');
            $this->line('4. Missing Wasender configuration for wasender type');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}