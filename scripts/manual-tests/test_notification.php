<?php

require_once 'vendor/autoload.php';

use App\Services\NotificationService;

// Create Laravel application context
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $service = app(NotificationService::class);
    
    $result = $service->send([
        'channel' => 'email',
        'to' => 'test@example.com',
        'subject' => 'Test Subject',
        'message' => 'Test message content'
    ]);
    
    echo "Success:\n";
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
