# Phase 3 Testing Guide

## Overview

This guide provides comprehensive testing procedures for Phase 3 features including templates, provider configuration, bulk operations, analytics, and rate limiting.

## Prerequisites

1. **Environment Setup**: Ensure notification service is running
2. **Authentication**: Valid project credentials for HMAC authentication
3. **Redis**: Redis server running for rate limiting
4. **Database**: PostgreSQL with Phase 3 migrations applied

## Testing Authentication

### 1. HMAC Authentication Test

```bash
# Test with valid HMAC signature
curl -X GET "http://localhost:8000/api/v1/health" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)" \
  -H "Content-Type: application/json"

# Expected: 200 OK with health status
```

## Template Management Testing

### 1. Create Template

```bash
curl -X POST "http://localhost:8000/api/v1/templates" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "welcome_email",
    "channel": "email",
    "subject": "Welcome {{user.first_name}}!",
    "content": "Hello {{user.first_name}}, welcome to our platform!",
    "variables": ["user.first_name", "user.email"],
    "metadata": {
      "category": "onboarding",
      "version": "1.0"
    }
  }'

# Expected: 201 Created with template data
```

### 2. List Templates

```bash
curl -X GET "http://localhost:8000/api/v1/templates" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)"

# Expected: 200 OK with template list
```

### 3. Preview Template

```bash
curl -X POST "http://localhost:8000/api/v1/templates/{template-id}/preview" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)" \
  -H "Content-Type: application/json" \
  -d '{
    "data": {
      "user": {
        "first_name": "John",
        "email": "john@example.com"
      }
    }
  }'

# Expected: 200 OK with rendered template
```

### 4. Validate Template

```bash
curl -X POST "http://localhost:8000/api/v1/templates/{template-id}/validate" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)" \
  -H "Content-Type: application/json"

# Expected: 200 OK with validation results
```

## Provider Configuration Testing

### 1. Add Provider Configuration

```bash
curl -X POST "http://localhost:8000/api/v1/config/providers" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)" \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "sendgrid",
    "channel": "email",
    "priority": 1,
    "config": {
      "api_key": "your-sendgrid-api-key",
      "from_email": "noreply@example.com",
      "from_name": "Your Company"
    },
    "quota_limits": {
      "daily": 10000,
      "monthly": 300000
    }
  }'

# Expected: 201 Created with provider config
```

### 2. Test Provider Configuration

```bash
curl -X POST "http://localhost:8000/api/v1/config/providers/{config-id}/test" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)" \
  -H "Content-Type: application/json" \
  -d '{
    "test_type": "connectivity",
    "recipient": "test@example.com"
  }'

# Expected: 200 OK with test results
```

### 3. Get Quota Information

```bash
curl -X GET "http://localhost:8000/api/v1/config/quotas" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)"

# Expected: 200 OK with quota data
```

## Bulk Operations Testing

### 1. Send Bulk Messages

```bash
curl -X POST "http://localhost:8000/api/v1/bulk/messages" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)" \
  -H "Content-Type: application/json" \
  -d '{
    "template_id": "template-123",
    "channel": "email",
    "recipients": [
      {
        "email": "user1@example.com",
        "data": {"name": "John"}
      },
      {
        "email": "user2@example.com",
        "data": {"name": "Jane"}
      }
    ],
    "options": {
      "batch_size": 100,
      "delay_between_batches": 1000
    }
  }'

# Expected: 202 Accepted with batch ID
```

### 2. Check Bulk Job Status

```bash
curl -X GET "http://localhost:8000/api/v1/bulk/messages/{batch-id}" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)"

# Expected: 200 OK with job status
```

### 3. Cancel Bulk Job

```bash
curl -X POST "http://localhost:8000/api/v1/bulk/messages/{batch-id}/cancel" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)" \
  -H "Content-Type: application/json"

# Expected: 200 OK with cancellation confirmation
```

### 4. List Bulk Jobs

```bash
curl -X GET "http://localhost:8000/api/v1/bulk/jobs" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)"

# Expected: 200 OK with job list
```

## Analytics Testing

### 1. Get Delivery Rates

```bash
curl -X GET "http://localhost:8000/api/v1/analytics/delivery-rates?period=7d&channel=email" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)"

# Expected: 200 OK with delivery rate data
```

### 2. Get Daily Volume

```bash
curl -X GET "http://localhost:8000/api/v1/analytics/daily-volume?period=30d" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)"

# Expected: 200 OK with volume data
```

### 3. Get Provider Performance

```bash
curl -X GET "http://localhost:8000/api/v1/analytics/provider-performance?period=7d" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)"

# Expected: 200 OK with provider performance data
```

### 4. Get Cost Analytics

```bash
curl -X GET "http://localhost:8000/api/v1/analytics/cost-analytics?period=30d" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)"

# Expected: 200 OK with cost data
```

### 5. Get Engagement Metrics

```bash
curl -X GET "http://localhost:8000/api/v1/analytics/engagement-metrics?period=7d&channel=email" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)"

# Expected: 200 OK with engagement data
```

### 6. Get Dashboard Data

```bash
curl -X GET "http://localhost:8000/api/v1/analytics/dashboard" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)"

# Expected: 200 OK with dashboard overview
```

## Rate Limiting Testing

### 1. Test Standard Rate Limits

```bash
# Send multiple requests quickly to test rate limiting
for i in {1..150}; do
  curl -X GET "http://localhost:8000/api/v1/templates" \
    -H "X-Project-ID: your-project-id" \
    -H "X-Signature: your-hmac-signature" \
    -H "X-Timestamp: $(date +%s)" &
done
wait

# Expected: Some requests should return 429 Too Many Requests
```

### 2. Test Bulk Rate Limits

```bash
# Send multiple bulk requests to test strict limits
for i in {1..15}; do
  curl -X POST "http://localhost:8000/api/v1/bulk/messages" \
    -H "X-Project-ID: your-project-id" \
    -H "X-Signature: your-hmac-signature" \
    -H "X-Timestamp: $(date +%s)" \
    -H "Content-Type: application/json" \
    -d '{"template_id":"test","channel":"email","recipients":[]}' &
done
wait

# Expected: Requests after the 10th should return 429
```

### 3. Check Rate Limit Headers

```bash
curl -v -X GET "http://localhost:8000/api/v1/templates" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)"

# Expected headers:
# X-RateLimit-Limit: 100
# X-RateLimit-Remaining: 99
# X-RateLimit-Reset: 1640995200
```

## Administrative Testing

### 1. System Health Check

```bash
curl -X GET "http://localhost:8000/api/v1/admin/system-health" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)"

# Expected: 200 OK with system status
```

### 2. Queue Status Check

```bash
curl -X GET "http://localhost:8000/api/v1/admin/queue-status" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)"

# Expected: 200 OK with queue information
```

## Error Testing

### 1. Test Invalid Authentication

```bash
curl -X GET "http://localhost:8000/api/v1/templates" \
  -H "X-Project-ID: invalid-id" \
  -H "X-Signature: invalid-signature" \
  -H "X-Timestamp: $(date +%s)"

# Expected: 401 Unauthorized
```

### 2. Test Invalid Input

```bash
curl -X POST "http://localhost:8000/api/v1/templates" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "",
    "channel": "invalid-channel"
  }'

# Expected: 422 Unprocessable Entity with validation errors
```

### 3. Test Resource Not Found

```bash
curl -X GET "http://localhost:8000/api/v1/templates/non-existent-id" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -H "X-Timestamp: $(date +%s)"

# Expected: 404 Not Found
```

## Load Testing

### 1. Template Operations Load Test

```bash
# Create multiple templates concurrently
for i in {1..50}; do
  curl -X POST "http://localhost:8000/api/v1/templates" \
    -H "X-Project-ID: your-project-id" \
    -H "X-Signature: your-hmac-signature" \
    -H "X-Timestamp: $(date +%s)" \
    -H "Content-Type: application/json" \
    -d "{
      \"name\": \"test-template-$i\",
      \"channel\": \"email\",
      \"subject\": \"Test $i\",
      \"content\": \"Test content $i\"
    }" &
done
wait
```

### 2. Analytics Load Test

```bash
# Fetch analytics data concurrently
for i in {1..100}; do
  curl -X GET "http://localhost:8000/api/v1/analytics/dashboard" \
    -H "X-Project-ID: your-project-id" \
    -H "X-Signature: your-hmac-signature" \
    -H "X-Timestamp: $(date +%s)" &
done
wait
```

## Automated Testing Scripts

### 1. Full Phase 3 Test Suite

```bash
#!/bin/bash
# phase3_test_suite.sh

PROJECT_ID="your-project-id"
API_SECRET="your-api-secret"
BASE_URL="http://localhost:8000/api/v1"

# Generate HMAC signature
generate_signature() {
    timestamp=$(date +%s)
    echo -n "${timestamp}" | openssl dgst -sha256 -hmac "${API_SECRET}" -binary | base64
}

echo "Running Phase 3 Test Suite..."

# Test 1: Health Check
echo "1. Testing health check..."
curl -s "${BASE_URL}/health" | jq .

# Test 2: Template Management
echo "2. Testing template management..."
# ... additional test commands

echo "Phase 3 test suite completed."
```

## Monitoring and Debugging

### 1. Log Analysis

```bash
# Monitor application logs
tail -f storage/logs/laravel.log | grep -E "(RATE_LIMIT|TEMPLATE|BULK|ANALYTICS)"

# Monitor error logs
tail -f storage/logs/laravel.log | grep -E "(ERROR|CRITICAL|EMERGENCY)"
```

### 2. Redis Monitoring

```bash
# Monitor Redis rate limiting keys
redis-cli KEYS "rate_limit:*"

# Monitor Redis memory usage
redis-cli INFO memory
```

### 3. Database Monitoring

```sql
-- Check template usage
SELECT channel, COUNT(*) as template_count 
FROM templates 
GROUP BY channel;

-- Check bulk job status
SELECT status, COUNT(*) as job_count 
FROM bulk_jobs 
GROUP BY status;

-- Check recent analytics data
SELECT DATE(created_at) as date, COUNT(*) as message_count 
FROM messages 
WHERE created_at >= NOW() - INTERVAL '7 days'
GROUP BY DATE(created_at)
ORDER BY date;
```

## Performance Benchmarks

### Expected Performance Metrics

1. **Template Operations**: < 100ms response time
2. **Provider Configuration**: < 200ms response time
3. **Bulk Job Creation**: < 500ms response time
4. **Analytics Queries**: < 1s response time
5. **Rate Limiting**: < 10ms overhead

### Load Testing Results

- **Concurrent Users**: 100
- **Request Rate**: 1000 req/sec
- **Error Rate**: < 1%
- **Response Time (95th percentile)**: < 500ms

## Troubleshooting

### Common Issues

1. **Rate Limit Not Working**: Check Redis connection
2. **Template Preview Fails**: Verify template syntax
3. **Bulk Jobs Stuck**: Check queue workers
4. **Analytics Empty**: Verify data exists in timeframe
5. **Provider Test Fails**: Check API credentials

### Debug Commands

```bash
# Check queue workers
php artisan queue:work --verbose

# Clear caches
php artisan cache:clear
php artisan config:clear

# Run migrations
php artisan migrate:status
```
