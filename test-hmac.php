<?php

/**
 * HMAC Authentication Test Script
 * 
 * This script demonstrates how to generate HMAC signatures for API requests
 * and provides test functions for the notification service.
 */

class NotificationServiceTester
{
    private $projectId;
    private $secretKey;
    private $baseUrl;

    public function __construct($projectId, $secretKey, $baseUrl = 'http://127.0.0.1:8000/api')
    {
        $this->projectId = $projectId;
        $this->secretKey = $secretKey;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Generate HMAC signature for authentication
     */
    public function generateSignature($method, $uri, $body = '', $timestamp = null)
    {
        $timestamp = $timestamp ?: time();
        
        // Create the payload to sign
        $payload = $method . '|' . $uri . '|' . $body . '|' . $timestamp;
        
        // Generate HMAC signature
        $signature = hash_hmac('sha256', $payload, $this->secretKey);
        
        return [
            'timestamp' => $timestamp,
            'signature' => $signature,
            'auth_header' => "Bearer {$this->projectId}:{$timestamp}:{$signature}"
        ];
    }

    /**
     * Make authenticated API request
     */
    public function makeRequest($method, $endpoint, $data = null)
    {
        $url = $this->baseUrl . $endpoint;
        $body = $data ? json_encode($data) : '';
        
        // Generate authentication
        $auth = $this->generateSignature($method, $endpoint, $body);
        
        // Prepare cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: ' . $auth['auth_header']
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return [
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $error,
            'auth_header' => $auth['auth_header']
        ];
    }

    /**
     * Test health endpoint (no authentication)
     */
    public function testHealth()
    {
        echo "🏥 Testing health endpoint...\n";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/health',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "Status Code: {$httpCode}\n";
        echo "Response: {$response}\n\n";
        
        return $httpCode === 200;
    }

    /**
     * Test sending a message
     */
    public function testSendMessage()
    {
        echo "📧 Testing send message endpoint...\n";
        
        $messageData = [
            'tenant_id' => 'tenant-demo', // Use the tenant from test data
            'channel' => 'email',
            'recipient' => [
                'email' => 'test@example.com',
                'name' => 'Test User'
            ],
            'message' => [
                'subject' => 'Test Message from API',
                'content' => 'This is a test message sent via the notification service API.',
                'template_id' => 'welcome-email' // Use the template from test data
            ],
            'metadata' => [
                'test' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
        
        $result = $this->makeRequest('POST', '/v1/messages', $messageData);
        
        echo "Status Code: {$result['http_code']}\n";
        echo "Auth Header: {$result['auth_header']}\n";
        echo "Response: {$result['response']}\n";
        
        if ($result['error']) {
            echo "cURL Error: {$result['error']}\n";
        }
        
        echo "\n";
        
        return $result['http_code'] === 201;
    }

    /**
     * Test listing messages
     */
    public function testListMessages()
    {
        echo "📋 Testing list messages endpoint...\n";
        
        $result = $this->makeRequest('GET', '/v1/messages?limit=10');
        
        echo "Status Code: {$result['http_code']}\n";
        echo "Response: {$result['response']}\n\n";
        
        return $result['http_code'] === 200;
    }

    /**
     * Run all tests
     */
    public function runAllTests()
    {
        echo "🚀 Starting Notification Service API Tests\n";
        echo "==========================================\n\n";
        
        $healthOk = $this->testHealth();
        $sendOk = $this->testSendMessage();
        $listOk = $this->testListMessages();
        
        echo "📊 Test Results:\n";
        echo "Health Check: " . ($healthOk ? "✅ PASS" : "❌ FAIL") . "\n";
        echo "Send Message: " . ($sendOk ? "✅ PASS" : "❌ FAIL") . "\n";
        echo "List Messages: " . ($listOk ? "✅ PASS" : "❌ FAIL") . "\n\n";
        
        if ($healthOk && $sendOk && $listOk) {
            echo "🎉 All tests passed! Your notification service is working correctly.\n";
        } else {
            echo "⚠️  Some tests failed. Check your setup and authentication.\n";
        }
    }
}

// Example usage:
if (php_sapi_name() === 'cli') {
    echo "Notification Service HMAC Tester\n";
    echo "=================================\n\n";
    
    // Test configuration - update these values
    $projectId = '550e8400-e29b-41d4-a716-446655440000'; // UUID from database
    $secretKey = 'test-secret-key-for-hmac'; // Secret from database
    
    echo "✅ TEST DATA READY:\n";
    echo "Project ID: {$projectId}\n";
    echo "Secret Key: {$secretKey}\n";
    echo "Database: PostgreSQL (Schema: notification)\n";
    echo "Test data has been loaded into the database.\n\n";
    
    $tester = new NotificationServiceTester($projectId, $secretKey);
    
    // Run the tests
    $tester->runAllTests();
}
