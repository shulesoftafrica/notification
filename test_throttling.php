<?php

/**
 * Redis Throttling Test Script
 * 
 * This script tests the Redis throttling middleware by making rapid requests
 * to the notification endpoints to verify rate limiting works.
 * 
 * Run: php test_throttling.php
 */

echo "\n========================================\n";
echo "Redis Throttling Test\n";
echo "Testing 2 requests per second limit\n";
echo "========================================\n\n";

$baseUrl = 'http://localhost:8000/api';
$apiKey = 'test_api_key_12345'; // Replace with actual API key

// Test data for single notification
$testData = [
    'schema_name' => 'test_throttle_schema',
    'channel' => 'sms',
    'to' => '+255712345678',
    'message' => 'Throttling test message',
    'provider' => 'beem'
];

/**
 * Make HTTP request with rate limit headers tracking
 */
function makeThrottleTestRequest($url, $data, $apiKey, $requestNum) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    // Parse rate limit headers
    $rateLimit = null;
    $remaining = null;
    $reset = null;
    $retryAfter = null;
    
    if (preg_match('/X-RateLimit-Limit: (\d+)/i', $headers, $matches)) {
        $rateLimit = $matches[1];
    }
    if (preg_match('/X-RateLimit-Remaining: (\d+)/i', $headers, $matches)) {
        $remaining = $matches[1];
    }
    if (preg_match('/X-RateLimit-Reset: (\d+)/i', $headers, $matches)) {
        $reset = $matches[1];
    }
    if (preg_match('/Retry-After: (\d+)/i', $headers, $matches)) {
        $retryAfter = $matches[1];
    }
    
    return [
        'request_num' => $requestNum,
        'http_code' => $httpCode,
        'body' => json_decode($body, true),
        'rate_limit' => $rateLimit,
        'remaining' => $remaining,
        'reset' => $reset,
        'retry_after' => $retryAfter,
        'timestamp' => microtime(true)
    ];
}

// Test 1: Single notification endpoint
echo "Test 1: Single notification endpoint (/notifications/send)\n";
echo "Making 5 rapid requests to test 2/sec limit...\n\n";

$startTime = microtime(true);
$results = [];

for ($i = 1; $i <= 5; $i++) {
    echo "Request {$i}: ";
    $result = makeThrottleTestRequest($baseUrl . '/notifications/send', $testData, $apiKey, $i);
    $results[] = $result;
    
    $timeSinceStart = round($result['timestamp'] - $startTime, 3);
    
    if ($result['http_code'] === 200 || $result['http_code'] === 201) {
        echo "✓ SUCCESS (HTTP {$result['http_code']})";
    } elseif ($result['http_code'] === 429) {
        echo "⚠ RATE LIMITED (HTTP 429)";
    } else {
        echo "✗ ERROR (HTTP {$result['http_code']})";
    }
    
    echo " - Time: {$timeSinceStart}s";
    
    if ($result['rate_limit']) {
        echo " - Limit: {$result['rate_limit']}";
    }
    if ($result['remaining'] !== null) {
        echo " - Remaining: {$result['remaining']}";
    }
    if ($result['retry_after']) {
        echo " - Retry-After: {$result['retry_after']}s";
    }
    
    echo "\n";
    
    // Small delay to make requests rapid but observable
    usleep(100000); // 0.1 second
}

echo "\n" . str_repeat('-', 60) . "\n";
echo "Results Summary:\n";

$successCount = 0;
$rateLimitedCount = 0;
$errorCount = 0;

foreach ($results as $result) {
    if (in_array($result['http_code'], [200, 201])) {
        $successCount++;
    } elseif ($result['http_code'] === 429) {
        $rateLimitedCount++;
    } else {
        $errorCount++;
    }
}

echo "Successful requests: {$successCount}\n";
echo "Rate limited requests: {$rateLimitedCount}\n";
echo "Error requests: {$errorCount}\n";

// Test 2: Wait and retry
echo "\n" . str_repeat('=', 60) . "\n";
echo "Test 2: Waiting for rate limit reset...\n";
echo "Waiting 2 seconds then making another request...\n";

sleep(2);

echo "Making request after wait: ";
$retryResult = makeThrottleTestRequest($baseUrl . '/notifications/send', $testData, $apiKey, 'retry');

if (in_array($retryResult['http_code'], [200, 201])) {
    echo "✓ SUCCESS - Rate limit reset worked!\n";
} else {
    echo "✗ FAILED - HTTP {$retryResult['http_code']}\n";
}

// Test 3: Bulk endpoint
echo "\n" . str_repeat('=', 60) . "\n";
echo "Test 3: Bulk notification endpoint\n";

$bulkData = [
    'schema_name' => 'test_throttle_schema',
    'channel' => 'sms',
    'provider' => 'beem',
    'messages' => [
        ['to' => '+255712345678', 'message' => 'Bulk test 1'],
        ['to' => '+255712345679', 'message' => 'Bulk test 2']
    ]
];

echo "Making 3 rapid bulk requests...\n";

for ($i = 1; $i <= 3; $i++) {
    echo "Bulk Request {$i}: ";
    $result = makeThrottleTestRequest($baseUrl . '/notifications/bulk/send', $bulkData, $apiKey, $i);
    
    if (in_array($result['http_code'], [200, 201, 202])) {
        echo "✓ SUCCESS (HTTP {$result['http_code']})";
    } elseif ($result['http_code'] === 429) {
        echo "⚠ RATE LIMITED (HTTP 429)";
    } else {
        echo "✗ ERROR (HTTP {$result['http_code']})";
    }
    
    if ($result['remaining'] !== null) {
        echo " - Remaining: {$result['remaining']}";
    }
    
    echo "\n";
    usleep(100000); // 0.1 second delay
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "Throttling Test Complete\n";
echo "\nExpected behavior:\n";
echo "- First 2 requests per second should succeed\n";
echo "- Additional requests should return HTTP 429\n";
echo "- Rate limit headers should be present\n";
echo "- After waiting, requests should succeed again\n";
echo str_repeat('=', 60) . "\n\n";

echo "Note: If you see errors about missing schemas/sessions,\n";
echo "that's normal - we're testing throttling, not actual sending.\n\n";