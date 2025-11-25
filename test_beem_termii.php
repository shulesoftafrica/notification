<?php

/**
 * Test script for Beem and Termii SMS providers
 * 
 * This script tests the newly integrated Beem (Tanzania) and Termii (Nigeria) SMS providers
 * to verify proper configuration, country routing, and API integration.
 */

require_once 'vendor/autoload.php';

use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

echo "=== Testing Beem and Termii SMS Integration ===\n\n";

// Initialize NotificationService
$notificationService = new NotificationService();

echo "1. Testing configuration loading...\n";
$config = config('notification');
$providers = $config['providers'] ?? [];

// Check Beem configuration
if (isset($providers['beem'])) {
    echo "✅ Beem configuration loaded\n";
    echo "   - Countries: " . implode(', ', $providers['beem']['countries']) . "\n";
    echo "   - Priority: " . $providers['beem']['priority'] . "\n";
} else {
    echo "❌ Beem configuration missing\n";
}

// Check Termii configuration
if (isset($providers['termii'])) {
    echo "✅ Termii configuration loaded\n";
    echo "   - Countries: " . implode(', ', $providers['termii']['countries']) . "\n";
    echo "   - Priority: " . $providers['termii']['priority'] . "\n";
} else {
    echo "❌ Termii configuration missing\n";
}

echo "\n2. Testing provider selection for different countries...\n";

// Test Tanzania number - should select Beem
echo "Testing Tanzania number (+255...):\n";
try {
    $providerForTZ = $notificationService->getProviderForCountry('sms', 'TZ');
    echo "   Selected provider: " . ($providerForTZ ?: 'none') . "\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// Test Nigeria number - should select Termii
echo "Testing Nigeria number (+234...):\n";
try {
    $providerForNG = $notificationService->getProviderForCountry('sms', 'NG');
    echo "   Selected provider: " . ($providerForNG ?: 'none') . "\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n3. Testing adapter creation...\n";

// Test Beem adapter
echo "Creating Beem adapter:\n";
try {
    $beemConfig = $providers['beem'] ?? [];
    $beemAdapter = new \App\Services\Adapters\SmsAdapter($beemConfig, 'beem');
    echo "✅ Beem adapter created successfully\n";
} catch (Exception $e) {
    echo "❌ Beem adapter error: " . $e->getMessage() . "\n";
}

// Test Termii adapter
echo "Creating Termii adapter:\n";
try {
    $termiiConfig = $providers['termii'] ?? [];
    $termiiAdapter = new \App\Services\Adapters\SmsAdapter($termiiConfig, 'termii');
    echo "✅ Termii adapter created successfully\n";
} catch (Exception $e) {
    echo "❌ Termii adapter error: " . $e->getMessage() . "\n";
}

echo "\n4. Testing health checks (requires API credentials)...\n";

// Test Beem health check
echo "Testing Beem health check:\n";
if (isset($beemAdapter)) {
    try {
        $beemHealth = $beemAdapter->checkHealth();
        echo "   Beem health: " . ($beemHealth ? "✅ Healthy" : "❌ Unhealthy") . "\n";
    } catch (Exception $e) {
        echo "   Beem health check error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   Skipped - adapter not created\n";
}

// Test Termii health check
echo "Testing Termii health check:\n";
if (isset($termiiAdapter)) {
    try {
        $termiiHealth = $termiiAdapter->checkHealth();
        echo "   Termii health: " . ($termiiHealth ? "✅ Healthy" : "❌ Unhealthy") . "\n";
    } catch (Exception $e) {
        echo "   Termii health check error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   Skipped - adapter not created\n";
}

echo "\n5. Configuration Summary:\n";
echo "SMS providers configured:\n";
foreach ($config['channels']['sms'] ?? [] as $provider) {
    $providerConfig = $providers[$provider] ?? null;
    if ($providerConfig) {
        echo "   - {$provider}: priority {$providerConfig['priority']}";
        if (isset($providerConfig['countries'])) {
            echo " (countries: " . implode(', ', $providerConfig['countries']) . ")";
        }
        echo "\n";
    }
}

echo "\n=== Integration Test Complete ===\n";
echo "\nNext steps:\n";
echo "1. Set up API credentials in .env file:\n";
echo "   BEEM_API_KEY=your_api_key\n";
echo "   BEEM_SECRET_KEY=your_secret_key\n";
echo "   TERMII_API_KEY=your_api_key\n";
echo "\n2. Test with real SMS sending:\n";
echo "   php artisan tinker\n";
echo "   >> \$service = app(\\App\\Services\\NotificationService::class);\n";
echo "   >> \$service->send('sms', '+255712345678', 'Test message', 'Test Subject');\n";
echo "\n3. Monitor logs for provider selection and sending:\n";
echo "   tail -f storage/logs/laravel.log\n";
