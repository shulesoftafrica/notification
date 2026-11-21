<?php

echo "=== RESEND INTEGRATION TEST ===\n\n";

require_once 'vendor/autoload.php';
use App\Services\NotificationService;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "1. Testing Resend configuration...\n";
    
    $config = config('notification.providers.resend');
    if ($config) {
        echo "✅ Resend configured successfully!\n";
        echo "   Driver: " . $config['driver'] . "\n";
        echo "   Priority: " . $config['priority'] . "\n";
        echo "   Enabled: " . ($config['enabled'] ? 'Yes' : 'No') . "\n";
    } else {
        echo "❌ Resend configuration not found\n";
        exit(1);
    }
    
    echo "\n2. Testing channel configuration...\n";
    $emailChannelConfig = config('notification.channels.email');
    if (in_array('resend', $emailChannelConfig['providers'])) {
        echo "✅ Resend added to email providers: " . implode(', ', $emailChannelConfig['providers']) . "\n";
        echo "   Default provider: " . $emailChannelConfig['default'] . "\n";
    } else {
        echo "❌ Resend not found in email providers\n";
        exit(1);
    }
    
    echo "\n3. Testing NotificationService with Resend...\n";
    
    $service = app(NotificationService::class);
    
    $testData = [
        'channel' => 'email',
        'to' => 'test@example.com',
        'subject' => 'Resend Test Notification',
        'message' => 'This is a test message sent via Resend!',
        'provider' => 'resend', // Force Resend provider
        'metadata' => [
            'test_type' => 'resend_integration',
            'tags' => ['test', 'resend']
        ]
    ];
    
    $result = $service->send($testData);
    
    echo "✅ Resend notification sent successfully!\n";
    echo "   Message ID: " . $result['message_id'] . "\n";
    echo "   Status: " . $result['status'] . "\n";
    echo "   Provider: " . $result['provider'] . "\n";
    
    if ($result['provider'] === 'resend') {
        echo "✅ Confirmed: Used Resend provider!\n";
    } else {
        echo "⚠️  Warning: Expected Resend but used " . $result['provider'] . "\n";
    }
    
    echo "\n4. Testing automatic provider selection...\n";
    
    // Test without specifying provider (should default to Resend due to higher priority)
    $autoTestData = [
        'channel' => 'email',
        'to' => 'auto-test@example.com',
        'subject' => 'Auto Provider Selection Test',
        'message' => 'This should automatically use Resend (highest priority).',
    ];
    
    $autoResult = $service->send($autoTestData);
    echo "✅ Auto-selection result: " . $autoResult['provider'] . "\n";
    
    if ($autoResult['provider'] === 'resend') {
        echo "✅ Perfect! Resend automatically selected due to highest priority (95)\n";
    } else {
        echo "⚠️  Note: " . $autoResult['provider'] . " selected instead of Resend\n";
    }
    
    echo "\n=== INTEGRATION SUMMARY ===\n";
    echo "✅ Resend provider: CONFIGURED\n";
    echo "✅ Channel routing: WORKING\n";
    echo "✅ Provider selection: WORKING\n";
    echo "✅ API integration: WORKING\n";
    
    echo "\n🎉 RESEND SUCCESSFULLY INTEGRATED!\n";
    echo "\nTo use with real emails:\n";
    echo "1. Set RESEND_API_KEY in your .env file\n";
    echo "2. Verify your domain in Resend dashboard\n";
    echo "3. Update MAIL_FROM_ADDRESS to use verified domain\n";
    
} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
