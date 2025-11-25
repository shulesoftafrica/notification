# Queue System Testing Manual

## 📋 Overview

This manual provides comprehensive instructions for testing the queue functionality in the Laravel notification service. The system uses Laravel's built-in queue system to handle asynchronous notification processing.

## 🔧 Prerequisites

### 1. Environment Setup
Ensure your `.env` file has the correct queue configuration:
```env
QUEUE_CONNECTION=database
QUEUE_DRIVER=database
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 2. Database Setup
Make sure you have the jobs table created:
```bash
php artisan queue:table
php artisan migrate
```

### 3. Required Tables
Verify these tables exist in your database:
- `jobs` - Queue jobs storage
- `failed_jobs` - Failed job tracking
- `notification_logs` - Notification tracking

## 🚀 Starting Queue Workers

### Method 1: Basic Queue Worker
```bash
# Start a single queue worker
php artisan queue:work

# Start with specific queue name
php artisan queue:work --queue=notifications

# Start with timeout and sleep settings
php artisan queue:work --timeout=60 --sleep=3

# Start with memory limit
php artisan queue:work --memory=512
```

### Method 2: Queue Worker with Restart
```bash
# Restart workers gracefully after current job completion
php artisan queue:restart

# Start worker that auto-restarts on code changes
php artisan queue:work --timeout=60 --tries=3
```

### Method 3: Background Queue Worker (Windows)
```powershell
# Start queue worker in background
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd 'C:\xampp\htdocs\notification'; php artisan queue:work --timeout=60"
```

## 📤 Testing Queue Functionality

### Test 1: Basic SMS Queue Test

#### Step 1: Send SMS to Queue
```bash
# Using API
curl -X POST http://127.0.0.1:8000/api/notifications/send \
-H "Content-Type: application/json" \
-H "X-API-Key: test123456789012345678901234567890" \
-d '{
    "channel": "sms",
    "to": "+255712345678",
    "message": "Test queue message",
    "sender_name": "TESTBRAND",
    "priority": "normal",
    "metadata": {
        "campaign": "queue_test"
    }
}'
```

#### Step 2: Using PowerShell
```powershell
$headers = @{
    "X-API-Key" = "test123456789012345678901234567890"
    "Content-Type" = "application/json"
}

$body = @{
    channel = "sms"
    to = "+255712345678"
    message = "Test queue SMS"
    sender_name = "MYBRAND"
    priority = "normal"
    metadata = @{
        test = "queue_functionality"
    }
} | ConvertTo-Json

Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/notifications/send" -Method POST -Headers $headers -Body $body
```

### Test 2: Email Queue Test

```bash
curl -X POST http://127.0.0.1:8000/api/notifications/send \
-H "Content-Type: application/json" \
-H "X-API-Key: test123456789012345678901234567890" \
-d '{
    "channel": "email",
    "to": "test@example.com",
    "subject": "Queue Test Email",
    "message": "This email was sent through the queue system",
    "priority": "high"
}'
```

### Test 3: WhatsApp Routing Test

#### Official WhatsApp Business API
```bash
curl -X POST http://127.0.0.1:8000/api/notifications/send \
-H "Content-Type: application/json" \
-H "X-API-Key: test123456789012345678901234567890" \
-d '{
    "channel": "whatsapp",
    "to": "+255712345678",
    "message": "Test message via Official WhatsApp",
    "type": "official",
    "metadata": {
        "campaign": "official_test"
    }
}'
```

#### Wasender WhatsApp API
```bash
curl -X POST http://127.0.0.1:8000/api/notifications/send \
-H "Content-Type: application/json" \
-H "X-API-Key: test123456789012345678901234567890" \
-d '{
    "channel": "whatsapp",
    "to": "+255712345678",
    "message": "Test message via Wasender",
    "type": "wasender",
    "metadata": {
        "campaign": "wasender_test"
    }
}'
```

#### PowerShell WhatsApp Test
```powershell
$headers = @{
    "X-API-Key" = "test123456789012345678901234567890"
    "Content-Type" = "application/json"
}

# Test Official WhatsApp
$officialBody = @{
    channel = "whatsapp"
    to = "+255712345678"
    message = "Test via Official WhatsApp"
    type = "official"
} | ConvertTo-Json

Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/notifications/send" -Method POST -Headers $headers -Body $officialBody

# Test Wasender
$wasenderBody = @{
    channel = "whatsapp"
    to = "+255712345678"
    message = "Test via Wasender"
    type = "wasender"
} | ConvertTo-Json

Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/notifications/send" -Method POST -Headers $headers -Body $wasenderBody
```

### Test 4: Bulk Queue Testing

Create a test script for multiple notifications:

```php
<?php
// File: test_bulk_queue.php

require_once 'vendor/autoload.php';

use App\Services\NotificationService;

$service = app(NotificationService::class);

// Send 10 SMS notifications to queue
for ($i = 1; $i <= 10; $i++) {
    $result = $service->send(
        'sms',
        '+255712345678',
        "Bulk test message #{$i}",
        null,
        [
            'sender_name' => 'BULKTEST',
            'campaign' => 'bulk_queue_test',
            'batch_id' => 'BATCH_001'
        ]
    );
    
    echo "Queued message #{$i}: {$result['message_id']}\n";
    sleep(1);
}

echo "All 10 messages queued successfully!\n";
```

Run with:
```bash
php test_bulk_queue.php
```

## 📊 Monitoring Queue Status

### Method 1: Database Monitoring

#### Check Pending Jobs
```sql
-- View pending jobs
SELECT id, queue, payload, attempts, created_at 
FROM jobs 
ORDER BY created_at DESC 
LIMIT 10;

-- Count jobs by queue
SELECT queue, COUNT(*) as count 
FROM jobs 
GROUP BY queue;

-- View failed jobs
SELECT id, connection, queue, payload, exception, failed_at 
FROM failed_jobs 
ORDER BY failed_at DESC 
LIMIT 5;
```

#### Check Notification Status
```sql
-- View recent notifications
SELECT id, type, recipient, status, provider, created_at, updated_at 
FROM notification_logs 
ORDER BY created_at DESC 
LIMIT 10;

-- Count notifications by status
SELECT status, COUNT(*) as count 
FROM notification_logs 
GROUP BY status;

-- Check processing times
SELECT provider, AVG(duration_ms) as avg_duration_ms 
FROM notification_logs 
WHERE duration_ms IS NOT NULL 
GROUP BY provider;
```

### Method 2: Laravel Commands

```bash
# Check queue status
php artisan queue:monitor

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush

# Get queue statistics
php artisan queue:work --once --verbose
```

### Method 3: Real-time Monitoring Script

Create a monitoring script:

```php
<?php
// File: monitor_queue.php

while (true) {
    $pendingJobs = DB::table('jobs')->count();
    $failedJobs = DB::table('failed_jobs')->count();
    $recentNotifications = DB::table('notification_logs')
        ->where('created_at', '>', now()->subMinutes(5))
        ->count();
    
    echo "\n" . date('Y-m-d H:i:s') . " - Queue Status:\n";
    echo "Pending Jobs: {$pendingJobs}\n";
    echo "Failed Jobs: {$failedJobs}\n";
    echo "Notifications (last 5min): {$recentNotifications}\n";
    echo "----------------------------------------\n";
    
    sleep(10);
}
```

## 🧪 Testing Different Scenarios

### Scenario 1: High Priority Queue Test

```bash
# Send high priority notification
curl -X POST http://127.0.0.1:8000/api/notifications/send \
-H "Content-Type: application/json" \
-H "X-API-Key: test123456789012345678901234567890" \
-d '{
    "channel": "sms",
    "to": "+255712345678",
    "message": "URGENT: High priority message",
    "priority": "high",
    "sender_name": "ALERT"
}'
```

### Scenario 2: Failed Job Recovery Test

1. **Stop the queue worker**
2. **Send notifications** (they will queue but not process)
3. **Start the queue worker** (jobs should process)
4. **Simulate failure** by providing invalid API credentials
5. **Check failed_jobs table**
6. **Retry failed jobs**: `php artisan queue:retry all`

### Scenario 3: Provider Failover Test

```bash
# Test with multiple providers configured
curl -X POST http://127.0.0.1:8000/api/notifications/send \
-H "Content-Type: application/json" \
-H "X-API-Key: test123456789012345678901234567890" \
-d '{
    "channel": "sms",
    "to": "+255712345678",
    "message": "Provider failover test",
    "metadata": {
        "test_failover": true
    }
}'
```

## 🔍 Troubleshooting Queue Issues

### Common Issues and Solutions

#### 1. Jobs Not Processing
```bash
# Check if worker is running
ps aux | grep "queue:work"

# Check queue configuration
php artisan config:show queue

# Restart worker
php artisan queue:restart
php artisan queue:work
```

#### 2. Jobs Failing Repeatedly
```bash
# View failed job details
php artisan queue:failed

# Check specific failed job
php artisan queue:failed-show [job-id]

# Retry specific job
php artisan queue:retry [job-id]
```

#### 3. Memory Issues
```bash
# Start worker with higher memory limit
php artisan queue:work --memory=1024

# Monitor memory usage
php artisan queue:work --verbose
```

### Debug Mode Testing

Enable debug logging in `.env`:
```env
APP_DEBUG=true
LOG_LEVEL=debug
```

Then monitor logs:
```bash
# Watch logs in real-time
tail -f storage/logs/laravel.log

# Filter queue-related logs
tail -f storage/logs/laravel.log | grep -i queue
```

## 📈 Performance Testing

### Load Testing Script

```php
<?php
// File: load_test_queue.php

$startTime = microtime(true);
$successCount = 0;
$failCount = 0;

for ($i = 1; $i <= 100; $i++) {
    try {
        $service = app(NotificationService::class);
        $result = $service->send(
            'sms',
            '+255712345678',
            "Load test message #{$i}",
            null,
            ['sender_name' => 'LOADTEST']
        );
        $successCount++;
        echo "✓ Queued #{$i}\n";
    } catch (Exception $e) {
        $failCount++;
        echo "✗ Failed #{$i}: {$e->getMessage()}\n";
    }
}

$endTime = microtime(true);
$duration = $endTime - $startTime;

echo "\n=== Load Test Results ===\n";
echo "Total messages: 100\n";
echo "Successful: {$successCount}\n";
echo "Failed: {$failCount}\n";
echo "Duration: " . round($duration, 2) . " seconds\n";
echo "Rate: " . round(100 / $duration, 2) . " messages/second\n";
```

## ✅ Testing Checklist

### Basic Functionality
- [ ] Queue worker starts without errors
- [ ] SMS notifications are queued successfully
- [ ] Email notifications are queued successfully
- [ ] WhatsApp notifications are queued successfully (both Official and Wasender)
- [ ] Jobs are processed by the worker
- [ ] Notification status is updated correctly
- [ ] Custom sender names work properly
- [ ] WhatsApp routing works based on type parameter

### Error Handling
- [ ] Failed jobs are recorded in failed_jobs table
- [ ] Provider failover works when primary provider fails
- [ ] Retry mechanism works for failed jobs
- [ ] Error messages are logged properly

### Performance
- [ ] Multiple notifications can be queued quickly
- [ ] Queue processes jobs efficiently
- [ ] Memory usage remains stable during processing
- [ ] No memory leaks during long-running workers

### Monitoring
- [ ] Job counts are accurate in database
- [ ] Processing times are recorded
- [ ] Failed job details are captured
- [ ] Logs provide sufficient debugging information

## 🚨 Important Notes

1. **Always run queue workers in production**: Use process managers like Supervisor on Linux or Windows Service
2. **Monitor failed jobs regularly**: Set up alerts for failed job counts
3. **Use appropriate timeouts**: Prevent jobs from hanging indefinitely
4. **Test provider failover**: Ensure backup providers work when primary fails
5. **Monitor memory usage**: Restart workers periodically to prevent memory leaks

## 📞 Emergency Procedures

### If Queue is Stuck
```bash
# Stop all workers
php artisan queue:restart

# Clear all pending jobs (DANGER - only in emergencies)
php artisan queue:clear

# Restart worker
php artisan queue:work --timeout=60
```

### If Database is Full
```bash
# Clean up old completed jobs (if stored)
php artisan queue:prune-batches --hours=24

# Clean up old failed jobs
php artisan queue:flush
```

This manual should help you thoroughly test and monitor the queue functionality in your notification system. Always test in a development environment before applying changes to production!