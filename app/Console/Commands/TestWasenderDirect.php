<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestWasenderDirect extends Command
{
    protected $signature = 'test:wasender-direct {phone} {message}';
    protected $description = 'Test Wasender API directly with actual configuration';

    public function handle()
    {
        $phone = $this->argument('phone');
        $message = $this->argument('message');

        $this->info('=== Testing Wasender API Directly ===');
        $this->newLine();

        // Get actual configuration from env
        $apiUrl = env('WASENDER_API_URL');
        $apiKey = env('WASENDER_API_KEY');
        $deviceId = env('WASENDER_DEVICE_ID');

        $this->info("📱 Phone: {$phone}");
        $this->info("💬 Message: {$message}");
        $this->info("🔗 API URL: {$apiUrl}");
        $this->info("🔑 API Key: " . substr($apiKey, 0, 10) . "...");
        $this->info("📟 Device ID: {$deviceId}");
        $this->newLine();

        // Clean phone number
        $phoneNumber = preg_replace('/[^\d+]/', '', $phone);
        if (!str_starts_with($phoneNumber, '+')) {
            $phoneNumber = '+' . $phoneNumber;
        }

        $this->info("📞 Cleaned phone: {$phoneNumber}");
        $this->newLine();

        // Prepare payload
        $payload = [
            'to' => $phoneNumber,
            'text' => $message,
            'device_id' => $deviceId
        ];

        $this->info("📦 Payload:");
        $this->line(json_encode($payload, JSON_PRETTY_PRINT));
        $this->newLine();

        try {
            $this->info('🚀 Sending request to Wasender...');
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->timeout(30)->post($apiUrl, $payload);

            $this->newLine();
            $this->info("📊 Response Status: {$response->status()}");
            $this->info("📄 Response Headers:");
            foreach ($response->headers() as $key => $value) {
                $this->line("  {$key}: " . (is_array($value) ? implode(', ', $value) : $value));
            }
            
            $this->newLine();
            $this->info("📝 Response Body:");
            $responseBody = $response->body();
            
            if ($response->successful()) {
                $this->info("✅ SUCCESS!");
                $data = $response->json();
                $this->line(json_encode($data, JSON_PRETTY_PRINT));
                
                if (isset($data['message_id'])) {
                    $this->comment("Message ID: {$data['message_id']}");
                }
            } else {
                $this->error("❌ FAILED!");
                $this->error("Raw response: {$responseBody}");
                
                if ($response->json()) {
                    $this->line(json_encode($response->json(), JSON_PRETTY_PRINT));
                }
            }

        } catch (\Exception $e) {
            $this->error("💥 Exception occurred: " . $e->getMessage());
            $this->error("Exception trace: " . $e->getTraceAsString());
        }

        $this->newLine();
        $this->comment('If this works, then the issue is in the notification service routing.');
        $this->comment('If this fails, then there\'s an issue with the Wasender API configuration.');

        return Command::SUCCESS;
    }
}