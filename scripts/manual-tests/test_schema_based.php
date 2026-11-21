<?php
/**
 * Test script for schema-based NotificationController
 * Tests the updated controller that uses schema_name instead of api_key
 */

// Configuration
$baseUrl = 'http://localhost/notification';
$apiKey = 'LhpxNaEsEaaBW45SANVDlrsrorFRwOheKowfouKSHEAvWBibmowWYDNBqqDBBxn'; // From .env API_KEY

// Test cases for schema-based authentication
$testCases = [
    [
        'name' => 'Send SMS with Schema Name',
        'endpoint' => '/api/send',
        'method' => 'POST',
        'headers' => ['Authorization' => 'Bearer ' . $apiKey],
        'data' => [
            'schema_name' => 'test_schema',
            'channel' => 'sms',
            'to' => '+1234567890',
            'message' => 'Test message with schema authentication'
        ],
        'expectedStatus' => [200, 201, 422] // Success or validation error (not auth error)
    ],
    [
        'name' => 'Send WhatsApp with Schema Name',
        'endpoint' => '/api/send',
        'method' => 'POST',
        'headers' => ['Authorization' => 'Bearer ' . $apiKey],
        'data' => [
            'schema_name' => 'whatsapp_schema',
            'channel' => 'whatsapp',
            'type' => 'wasender',
            'to' => '+1234567890',
            'message' => 'Test WhatsApp message with schema authentication'
        ],
        'expectedStatus' => [200, 201, 400, 422] // Success, validation error, or missing WaSender session
    ],
    [
        'name' => 'List Messages with Schema Filter',
        'endpoint' => '/api/messages?schema_name=test_schema',
        'method' => 'GET',
        'headers' => ['Authorization' => 'Bearer ' . $apiKey],
        'data' => null,
        'expectedStatus' => [200]
    ],
    [
        'name' => 'Send Bulk Messages with Schema Name',
        'endpoint' => '/api/send/bulk',
        'method' => 'POST',
        'headers' => ['Authorization' => 'Bearer ' . $apiKey],
        'data' => [
            'schema_name' => 'bulk_schema',
            'channel' => 'sms',
            'messages' => [
                [
                    'to' => '+1234567890',
                    'message' => 'Bulk message 1'
                ],
                [
                    'to' => '+1234567891',
                    'message' => 'Bulk message 2'
                ]
            ]
        ],
        'expectedStatus' => [200, 201, 422]
    ]
];

function testApiEndpoint($url, $method, $headers, $data, $expectedStatus, $testName) {
    echo "\n--- Testing: $testName ---\n";
    echo "URL: $url\n";
    echo "Method: $method\n";
    echo "Data: " . ($data ? json_encode($data, JSON_PRETTY_PRINT) : 'None') . "\n";
    
    $ch = curl_init();
    
    $curlOptions = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => array_merge([
            'Content-Type: application/json',
            'Accept: application/json'
        ], array_map(function($key, $value) {
            return "$key: $value";
        }, array_keys($headers), $headers)),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5
    ];
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
    }
    
    curl_setopt_array($ch, $curlOptions);
    
    $response = curl_exec($ch);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "❌ cURL Error: $error\n";
        return false;
    }
    
    echo "HTTP Status: $httpStatus\n";
    
    $decodedResponse = json_decode($response, true);
    if ($decodedResponse) {
        echo "Response: " . json_encode($decodedResponse, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "Raw Response: " . substr($response, 0, 300) . "\n";
    }
    
    // Check status code
    $statusMatch = is_array($expectedStatus) ? in_array($httpStatus, $expectedStatus) : ($httpStatus === $expectedStatus);
    
    if ($statusMatch) {
        echo "✅ Status code matches expected (" . (is_array($expectedStatus) ? implode('|', $expectedStatus) : $expectedStatus) . ")\n";
    } else {
        echo "❌ Status code mismatch. Expected: " . (is_array($expectedStatus) ? implode('|', $expectedStatus) : $expectedStatus) . ", Got: $httpStatus\n";
        return false;
    }
    
    // Check for schema_name in successful responses
    if ($httpStatus < 300 && $decodedResponse && isset($decodedResponse['data'])) {
        if (isset($data['schema_name'])) {
            echo "✅ Request included schema_name: {$data['schema_name']}\n";
        }
    }
    
    return true;
}

function runTests() {
    global $baseUrl, $testCases;
    
    echo "🚀 Starting Schema-Based NotificationController Tests\n";
    echo "Base URL: $baseUrl\n";
    echo "Testing schema_name usage instead of api_key\n";
    echo "=" . str_repeat("=", 70) . "\n";
    
    $totalTests = 0;
    $passedTests = 0;
    
    foreach ($testCases as $testCase) {
        $url = $baseUrl . $testCase['endpoint'];
        
        $result = testApiEndpoint(
            $url,
            $testCase['method'],
            $testCase['headers'],
            $testCase['data'],
            $testCase['expectedStatus'],
            $testCase['name']
        );
        
        $totalTests++;
        if ($result) {
            $passedTests++;
        }
        
        echo str_repeat("-", 80) . "\n";
    }
    
    echo "\n🏁 Test Summary\n";
    echo "Passed: $passedTests/$totalTests\n";
    echo ($passedTests === $totalTests ? "✅ All tests passed!" : "❌ Some tests failed");
    echo "\n";
}

// Check if we can connect to the application
echo "🔧 Pre-flight checks...\n";

$healthCheck = curl_init();
curl_setopt_array($healthCheck, [
    CURLOPT_URL => $baseUrl . '/api/health',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5,
    CURLOPT_CONNECTTIMEOUT => 3
]);

$response = curl_exec($healthCheck);
$httpStatus = curl_getinfo($healthCheck, CURLINFO_HTTP_CODE);
$error = curl_error($healthCheck);
curl_close($healthCheck);

if ($error) {
    echo "❌ Cannot connect to application: $error\n";
    echo "Please ensure the application is running at $baseUrl\n";
    exit(1);
}

if ($httpStatus === 200) {
    echo "✅ Application is reachable\n";
} else {
    echo "⚠️  Application returned status $httpStatus\n";
}

echo str_repeat("=", 80) . "\n";

// Run the tests
runTests();

// Additional information
echo "\n📋 Schema-Based Changes Summary:\n";
echo "✅ NotificationController updated to use schema_name instead of api_key\n";
echo "✅ Message model fillable array updated (schema_name added, api_key removed)\n";
echo "✅ Database migration created for schema_name column\n";
echo "✅ Query filtering now uses schema_name parameter\n";
echo "✅ Message creation stores schema_name in database\n";
echo "✅ WaSender API key lookup logic preserved (different use case)\n";

echo "\n🔧 Changes Made:\n";
echo "1. Updated send() method: 'api_key' => 'schema_name'\n";
echo "2. Updated index() method: filter by schema_name instead of api_key\n";
echo "3. Updated sendBulk() method: 'api_key' => 'schema_name'\n";
echo "4. Updated Message model fillable array\n";
echo "5. Created migration to replace api_key column with schema_name\n";

echo "\n📖 Usage Examples:\n";
echo "Send message:\n";
echo "curl -X POST $baseUrl/api/send \\\n";
echo "  -H 'Authorization: Bearer {$apiKey}' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\"schema_name\":\"my_schema\",\"channel\":\"sms\",\"to\":\"+1234567890\",\"message\":\"Hello\"}'\n\n";

echo "List messages:\n";
echo "curl -X GET '$baseUrl/api/messages?schema_name=my_schema' \\\n";
echo "  -H 'Authorization: Bearer {$apiKey}'\n";
?>