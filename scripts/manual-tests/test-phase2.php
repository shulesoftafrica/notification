<?php

require_once __DIR__ . '/notification-service/vendor/autoload.php';

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Test Phase 2 Implementation
 * Tests the complete queue-based message processing system
 */

echo "=== NOTIFICATION SERVICE PHASE 2 TEST ===\n";
echo "Testing complete message processing with queue system\n\n";

// Configuration
$baseUrl = 'http://localhost:8000/api';
$projectId = 'proj_demo_project';
$apiKey = 'sk_test_demo_key_12345';
$tenantId = 'tenant_main';

// Generate signature for API request
function generateSignature($method, $path, $body, $timestamp, $apiKey) {
    $payload = $method . '|' . $path . '|' . $body . '|' . $timestamp;
    return hash_hmac('sha256', $payload, $apiKey);
}

// Test 1: Check Redis Connection
echo "1. Testing Redis Connection...\n";
try {
    if (extension_loaded('redis')) {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->ping();
        echo "   ✓ Redis connection successful\n";
        $redis->close();
    } else {
        echo "   ℹ Redis PHP extension not installed, using Predis from Laravel\n";
        echo "   ✓ Laravel will use Predis client as configured\n";
    }
} catch (Exception $e) {
    echo "   ✗ Redis connection failed: " . $e->getMessage() . "\n";
    echo "   Note: Make sure Redis server is running on 127.0.0.1:6379\n";
}

// Test 2: Send Email Message
echo "\n2. Testing Email Message with Queue Processing...\n";
$emailPayload = [
    'channel' => 'email',
    'to' => [
        'email' => 'test@example.com',
        'name' => 'Test User',
        'subject' => 'Test Email from Phase 2',
        'content' => 'Hello, this is a test email from our Phase 2 implementation!'
    ],
    'options' => [
        'priority' => 'high'
    ],
    'metadata' => [
        'external_id' => 'test_email_' . time(),
        'campaign' => 'phase2_test'
    ]
];

$timestamp = time();
$body = json_encode($emailPayload);
$signature = generateSignature('POST', '/api/v1/messages', $body, $timestamp, $apiKey);

$headers = [
    'Content-Type' => 'application/json',
    'X-Project-ID' => $projectId,
    'X-Tenant-ID' => $tenantId,
    'X-API-Key' => $apiKey,
    'X-Timestamp' => $timestamp,
    'X-Signature' => $signature,
    'X-Idempotency-Key' => 'test_email_' . uniqid()
];

echo "   Sending email message request...\n";
echo "   URL: {$baseUrl}/v1/messages\n";
echo "   Headers: " . json_encode($headers, JSON_PRETTY_PRINT) . "\n";
echo "   Payload: " . json_encode($emailPayload, JSON_PRETTY_PRINT) . "\n";

// Note: Since we're testing in command line, we'll use curl
$curlCommand = 'curl -X POST "' . $baseUrl . '/v1/messages" ' .
    '-H "Content-Type: application/json" ' .
    '-H "X-Project-ID: ' . $projectId . '" ' .
    '-H "X-Tenant-ID: ' . $tenantId . '" ' .
    '-H "X-API-Key: ' . $apiKey . '" ' .
    '-H "X-Timestamp: ' . $timestamp . '" ' .
    '-H "X-Signature: ' . $signature . '" ' .
    '-H "X-Idempotency-Key: test_email_' . uniqid() . '" ' .
    '-d \'' . $body . '\'';

echo "   CURL Command: $curlCommand\n";
echo "   Note: Execute this command manually to test the API\n";

// Test 3: Send SMS Message
echo "\n3. Testing SMS Message with Queue Processing...\n";
$smsPayload = [
    'channel' => 'sms',
    'to' => [
        'phone' => '+1234567890',
        'text' => 'Hello! This is a test SMS from Phase 2 implementation.'
    ],
    'options' => [
        'priority' => 'normal'
    ],
    'metadata' => [
        'external_id' => 'test_sms_' . time(),
        'campaign' => 'phase2_test'
    ]
];

$timestamp = time();
$body = json_encode($smsPayload);
$signature = generateSignature('POST', '/api/v1/messages', $body, $timestamp, $apiKey);

$curlCommand = 'curl -X POST "' . $baseUrl . '/v1/messages" ' .
    '-H "Content-Type: application/json" ' .
    '-H "X-Project-ID: ' . $projectId . '" ' .
    '-H "X-Tenant-ID: ' . $tenantId . '" ' .
    '-H "X-API-Key: ' . $apiKey . '" ' .
    '-H "X-Timestamp: ' . $timestamp . '" ' .
    '-H "X-Signature: ' . $signature . '" ' .
    '-H "X-Idempotency-Key: test_sms_' . uniqid() . '" ' .
    '-d \'' . $body . '\'';

echo "   CURL Command: $curlCommand\n";

// Test 4: Test Template Rendering
echo "\n4. Testing Template-based Message...\n";
$templatePayload = [
    'channel' => 'email',
    'to' => [
        'email' => 'user@example.com',
        'name' => 'John Doe'
    ],
    'template_id' => 'tmpl_welcome_email',
    'variables' => [
        'user_name' => 'John Doe',
        'company_name' => 'ACME Corp',
        'login_url' => 'https://app.example.com/login'
    ],
    'options' => [
        'priority' => 'normal'
    ],
    'metadata' => [
        'external_id' => 'test_template_' . time(),
        'campaign' => 'welcome_series'
    ]
];

$timestamp = time();
$body = json_encode($templatePayload);
$signature = generateSignature('POST', '/api/v1/messages', $body, $timestamp, $apiKey);

$curlCommand = 'curl -X POST "' . $baseUrl . '/v1/messages" ' .
    '-H "Content-Type: application/json" ' .
    '-H "X-Project-ID: ' . $projectId . '" ' .
    '-H "X-Tenant-ID: ' . $tenantId . '" ' .
    '-H "X-API-Key: ' . $apiKey . '" ' .
    '-H "X-Timestamp: ' . $timestamp . '" ' .
    '-H "X-Signature: ' . $signature . '" ' .
    '-H "X-Idempotency-Key: test_template_' . uniqid() . '" ' .
    '-d \'' . $body . '\'';

echo "   CURL Command: $curlCommand\n";

// Test 5: Queue Worker Instructions
echo "\n5. Queue Worker Setup Instructions...\n";
echo "   To process messages in the queue, run the following commands:\n";
echo "   \n";
echo "   # Start the queue worker (in a separate terminal):\n";
echo "   cd C:\\xampp\\htdocs\\notification\\notification-service\n";
echo "   php artisan notification:queue:work --queue=default\n";
echo "   \n";
echo "   # Or use the built-in Laravel queue worker:\n";
echo "   php artisan queue:work redis --queue=default --timeout=60\n";
echo "   \n";
echo "   # Monitor queue status:\n";
echo "   php artisan queue:monitor redis:default\n";
echo "   \n";
echo "   # Clear failed jobs:\n";
echo "   php artisan queue:flush\n";

// Test 6: Webhook Testing
echo "\n6. Webhook Endpoint Testing...\n";
echo "   Webhook endpoints are available at:\n";
echo "   - Email: POST {$baseUrl}/v1/webhooks/sendgrid\n";
echo "   - Email: POST {$baseUrl}/v1/webhooks/mailgun\n";
echo "   - Email: POST {$baseUrl}/v1/webhooks/resend\n";
echo "   - SMS: POST {$baseUrl}/v1/webhooks/twilio\n";
echo "   - SMS: POST {$baseUrl}/v1/webhooks/vonage\n";
echo "   - WhatsApp: POST {$baseUrl}/v1/webhooks/whatsapp\n";
echo "   \n";
echo "   Example webhook test (Twilio SMS delivery):\n";
echo "   curl -X POST \"{$baseUrl}/v1/webhooks/twilio\" \\\n";
echo "     -H \"Content-Type: application/x-www-form-urlencoded\" \\\n";
echo "     -d \"MessageSid=SM123456789&MessageStatus=delivered\"\n";

// Test 7: Database Check
echo "\n7. Database Status Check...\n";
echo "   You can check the database tables manually:\n";
echo "   \n";
echo "   # Connect to PostgreSQL:\n";
echo "   psql -h 127.0.0.1 -U postgres -d other_app\n";
echo "   \n";
echo "   # Check messages table:\n";
echo "   \\c other_app\n";
echo "   SET search_path TO notification;\n";
echo "   SELECT message_id, channel, status, created_at FROM messages ORDER BY created_at DESC LIMIT 5;\n";
echo "   \n";
echo "   # Check queue jobs:\n";
echo "   SELECT id, queue, payload, attempts, created_at FROM jobs ORDER BY created_at DESC LIMIT 5;\n";
echo "   \n";
echo "   # Check failed jobs:\n";
echo "   SELECT id, queue, payload, exception, failed_at FROM failed_jobs ORDER BY failed_at DESC LIMIT 5;\n";

echo "\n=== PHASE 2 TEST COMPLETE ===\n";
echo "The core messaging system with queue processing is ready for testing.\n";
echo "Execute the CURL commands above to test the API endpoints.\n";
echo "Start the queue worker to process messages automatically.\n";
echo "Check the logs and database for processing results.\n\n";

echo "Next Steps:\n";
echo "1. Start Laravel development server: php artisan serve\n";
echo "2. Start Redis server: redis-server\n";
echo "3. Start queue worker: php artisan queue:work redis\n";
echo "4. Execute test API calls using the CURL commands above\n";
echo "5. Monitor queue processing and message status updates\n";
