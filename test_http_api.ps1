#!/usr/bin/env pwsh

Write-Host "=== HTTP API TEST ===" -ForegroundColor Green

# Start the Laravel server in the background
$serverJob = Start-Job -ScriptBlock { 
    Set-Location "C:\xampp\htdocs\notification"
    php artisan serve --host=127.0.0.1 --port=8001 
}

Write-Host "Starting Laravel server..." -ForegroundColor Yellow
Start-Sleep -Seconds 3

try {
    # Test API endpoint
    $headers = @{
        "X-API-Key" = "test123456789012345678901234567890"
        "Content-Type" = "application/json"
    }
    
    $body = @{
        channel = "email"
        to = "test@example.com"
        subject = "API Test"
        message = "This is a test from the HTTP API"
    } | ConvertTo-Json
    
    Write-Host "Sending API request..." -ForegroundColor Yellow
    
    $response = Invoke-RestMethod -Uri "http://127.0.0.1:8001/api/notifications/send" -Method POST -Headers $headers -Body $body -TimeoutSec 10
    
    Write-Host "✅ HTTP API SUCCESS!" -ForegroundColor Green
    Write-Host "Response:" -ForegroundColor Cyan
    $response | ConvertTo-Json -Depth 5 | Write-Host
    
} catch {
    Write-Host "❌ HTTP API Error: $($_.Exception.Message)" -ForegroundColor Red
} finally {
    # Stop the server
    Write-Host "Stopping server..." -ForegroundColor Yellow
    Stop-Job $serverJob -Force
    Remove-Job $serverJob -Force
}

Write-Host "`n🎯 Testing complete!" -ForegroundColor Green
