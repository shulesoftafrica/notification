# Test Notification Service API
Write-Host "=== Testing Notification Service API ===" -ForegroundColor Cyan

$headers = @{
    "X-API-Key" = "test123456789012345678901234567890"
    "Content-Type" = "application/json"
}

$body = @{
    channel = "whatsapp"
    to = "+255714825469"
    message = "Notification Service API Test"
    type = "wasender"
} | ConvertTo-Json

Write-Host "Testing notification service..." -ForegroundColor Yellow

try {
    $response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/notifications/send" -Method POST -Headers $headers -Body $body
    Write-Host "✅ Success!" -ForegroundColor Green
    Write-Host "Provider: $($response.provider)"
    Write-Host "Status: $($response.status)"
} catch {
    Write-Host "❌ Failed: $($_.Exception.Message)" -ForegroundColor Red
}