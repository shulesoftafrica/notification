$headers = @{
    "X-API-Key" = "test123456789012345678901234567890"
    "Content-Type" = "application/json"
}

Write-Host "=== Testing SMS with Custom Sender Name ===" -ForegroundColor Cyan
Write-Host ""

# Test 1: SMS to Tanzania (Beem) with custom sender name
Write-Host "1. Testing Tanzania SMS with custom sender 'MYCOMPANY':" -ForegroundColor Yellow
$body1 = @{
    channel = "sms"
    to = "+255712345678"
    message = "Hello from custom sender MYCOMPANY!"
    sender_name = "MYCOMPANY"
} | ConvertTo-Json

try {
    $response1 = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/notifications/send" -Method POST -Headers $headers -Body $body1
    Write-Host "✅ Success!" -ForegroundColor Green
    Write-Host "Provider: $($response1.provider)" -ForegroundColor Green
    Write-Host "Status: $($response1.status)" -ForegroundColor Green
    Write-Host "Message ID: $($response1.message_id)" -ForegroundColor Green
} catch {
    Write-Host "❌ Error: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Test 2: SMS to Nigeria (Termii) with custom sender name
Write-Host "2. Testing Nigeria SMS with custom sender 'TESTCO':" -ForegroundColor Yellow
$body2 = @{
    channel = "sms"
    to = "+2348012345678"
    message = "Hello from custom sender TESTCO!"
    sender_name = "TESTCO"
} | ConvertTo-Json

try {
    $response2 = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/notifications/send" -Method POST -Headers $headers -Body $body2
    Write-Host "✅ Success!" -ForegroundColor Green
    Write-Host "Provider: $($response2.provider)" -ForegroundColor Green
    Write-Host "Status: $($response2.status)" -ForegroundColor Green
    Write-Host "Message ID: $($response2.message_id)" -ForegroundColor Green
} catch {
    Write-Host "❌ Error: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Test 3: SMS with default sender (no sender_name provided)
Write-Host "3. Testing SMS with default sender name:" -ForegroundColor Yellow
$body3 = @{
    channel = "sms"
    to = "+255712345678"
    message = "Hello with default sender name!"
} | ConvertTo-Json

try {
    $response3 = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/notifications/send" -Method POST -Headers $headers -Body $body3
    Write-Host "✅ Success!" -ForegroundColor Green
    Write-Host "Provider: $($response3.provider)" -ForegroundColor Green
    Write-Host "Status: $($response3.status)" -ForegroundColor Green
    Write-Host "Message ID: $($response3.message_id)" -ForegroundColor Green
} catch {
    Write-Host "❌ Error: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "=== Test Complete ===" -ForegroundColor Cyan