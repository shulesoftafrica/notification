<?php

/**
 * WaSender API Client Test Script
 * 
 * This script tests creating a WhatsApp session via WaSender API
 * Run: php test_wasender_api.php
 */

// Configuration
$apiBaseUrl = 'http://localhost:8000/api/wasender';
$adminToken = 'your_admin_token_here'; // Replace with your actual admin token

echo "\n========================================\n";
echo "WaSender API Client Test\n";
echo "========================================\n\n";

/**
 * Make a cURL request to your local API
 */
function makeRequest($method, $url, $token, $data = null) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
    ]);
    
    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'response' => json_decode($response, true),
        'http_code' => $httpCode
    ];
}

// Test: Create a new session
echo "Test 1: Create New WhatsApp Session\n";
echo "------------------------------------\n";

$createData = [
    'schema_name' => 'client_tenant_1',
    'name' => 'My Business WhatsApp',
    'phone_number' => '+1234567890',
    'account_protection' => true,
    'log_messages' => true,
    'read_incoming_messages' => false,
    'webhook_url' => 'https://example.com/webhook',
    'webhook_enabled' => true,
    'webhook_events' => [
        'messages.received',
        'session.status',
        'messages.update'
    ]
];

echo "Sending request to: $apiBaseUrl/sessions/create\n";
echo "Request body:\n";
echo json_encode($createData, JSON_PRETTY_PRINT) . "\n\n";

$result = makeRequest('POST', $apiBaseUrl . '/sessions/create', $adminToken, $createData);

echo "HTTP Status: " . $result['http_code'] . "\n";
echo "Response:\n";
echo json_encode($result['response'], JSON_PRETTY_PRINT) . "\n\n";

if (isset($result['response']['success']) && $result['response']['success']) {
    echo "✓ Session created successfully!\n";
    $sessionId = $result['response']['data']['id'] ?? null;
    
    if ($sessionId) {
        echo "Local Database ID: $sessionId\n";
        echo "WaSender Session ID: " . ($result['response']['data']['wasender_session_id'] ?? 'N/A') . "\n\n";
        
        // Test: Get all sessions
        echo "Test 2: Get All Sessions\n";
        echo "-------------------------\n";
        $result = makeRequest('GET', $apiBaseUrl . '/sessions', $adminToken);
        echo "Response:\n";
        echo json_encode($result['response'], JSON_PRETTY_PRINT) . "\n\n";
        
        // Test: Get specific session
        echo "Test 3: Get Specific Session (ID: $sessionId)\n";
        echo "----------------------------------------------\n";
        $result = makeRequest('GET', $apiBaseUrl . "/sessions/$sessionId", $adminToken);
        echo "Response:\n";
        echo json_encode($result['response'], JSON_PRETTY_PRINT) . "\n\n";
    }
} else {
    echo "✗ Failed to create session\n";
    echo "Error: " . ($result['response']['error'] ?? 'Unknown error') . "\n\n";
}

echo "========================================\n";
echo "Test Complete\n";
echo "========================================\n\n";

