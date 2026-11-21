<?php

require_once 'vendor/autoload.php';

// Initialize Laravel application
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\ProviderFailoverService;
use App\Services\ProviderHealthService;

// Clear all provider failure cache
echo "Clearing provider failure cache...\n";
Cache::flush();

// Force provider recovery for Resend
$failoverService = app(ProviderFailoverService::class);
$healthService = app(ProviderHealthService::class);

echo "Forcing Resend recovery...\n";
$failoverService->forceProviderRecovery('resend');

echo "Checking provider health...\n";
$resendHealth = $healthService->refreshProvider('resend');
echo "Resend health: " . json_encode($resendHealth, JSON_PRETTY_PRINT) . "\n";

$sendgridHealth = $healthService->refreshProvider('sendgrid');
echo "SendGrid health: " . json_encode($sendgridHealth, JSON_PRETTY_PRINT) . "\n";

echo "Testing provider selection for email channel...\n";
$selectedProvider = $failoverService->selectProvider('email');
echo "Selected provider: {$selectedProvider}\n";

// Get failover status
echo "Failover status:\n";
$status = $failoverService->getFailoverStatus();
foreach ($status as $provider => $stat) {
    if (in_array($provider, ['resend', 'sendgrid', 'mailgun'])) {
        echo "- {$provider}: available=" . ($stat['available'] ? 'yes' : 'no') . 
             ", failures=" . $stat['failure_count'] . "\n";
    }
}

echo "\nDone!\n";