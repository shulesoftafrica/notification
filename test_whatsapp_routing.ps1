# WhatsApp Dual Routing Test Script

Write-Host "=== Testing WhatsApp Dual Routing ===" -ForegroundColor Cyan
Write-Host ""

$headers = @{
    "X-API-Key" = "test123456789012345678901234567890"
    "Content-Type" = "application/json"
}

$phone = "+255712345678"  # Change to your test WhatsApp number
$message = "Hello! This is a test message from the notification system with dual WhatsApp routing."

Write-Host "📱 Testing Official WhatsApp Business API..." -ForegroundColor Green

$officialBody = @{
    channel = "whatsapp"
    to = $phone
    message = $message
    type = "official"
    metadata = @{
        test_type = "official_whatsapp"
        campaign = "routing_test"
    }
} | ConvertTo-Json

try {
    $officialResponse = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/notifications/send" -Method POST -Headers $headers -Body $officialBody
    Write-Host "✅ Official WhatsApp Success!" -ForegroundColor Green
    Write-Host "Message ID: $($officialResponse.message_id)"
    Write-Host "Provider: $($officialResponse.provider)"
    Write-Host "Status: $($officialResponse.status)"
    Write-Host ""
} catch {
    Write-Host "❌ Official WhatsApp Failed: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
}

Start-Sleep -Seconds 2

Write-Host "📡 Testing Wasender API..." -ForegroundColor Yellow

$wasenderBody = @{
    channel = "whatsapp"
    to = $phone
    message = $message
    type = "wasender"
    metadata = @{
        test_type = "wasender"
        campaign = "routing_test"
    }
} | ConvertTo-Json

try {
    $wasenderResponse = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/notifications/send" -Method POST -Headers $headers -Body $wasenderBody
    Write-Host "✅ Wasender Success!" -ForegroundColor Green
    Write-Host "Message ID: $($wasenderResponse.message_id)"
    Write-Host "Provider: $($wasenderResponse.provider)"
    Write-Host "Status: $($wasenderResponse.status)"
    Write-Host ""
} catch {
    Write-Host "❌ Wasender Failed: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
}

Write-Host "=== Testing Media Messages ===" -ForegroundColor Magenta
Write-Host ""

# Test with media message (Official WhatsApp)
$mediaBody = @{
    channel = "whatsapp"
    to = $phone
    message = "Check out this image!"
    type = "official"
    metadata = @{
        media_type = "image"
        media_url = "https://picsum.photos/800/600"
        test_type = "media_official"
    }
} | ConvertTo-Json

try {
    $mediaResponse = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/notifications/send" -Method POST -Headers $headers -Body $mediaBody
    Write-Host "✅ Official WhatsApp Media Success!" -ForegroundColor Green
    Write-Host "Message ID: $($mediaResponse.message_id)"
    Write-Host "Provider: $($mediaResponse.provider)"
    Write-Host ""
} catch {
    Write-Host "❌ Official WhatsApp Media Failed: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
}

Write-Host "=== Configuration Check ===" -ForegroundColor Blue
Write-Host ""
Write-Host "Required environment variables:"
Write-Host "Official WhatsApp:"
Write-Host "  WHATSAPP_BUSINESS_PHONE_ID=your_phone_id"
Write-Host "  WHATSAPP_ACCESS_TOKEN=your_access_token"
Write-Host ""
Write-Host "Wasender:"
Write-Host "  WASENDER_API_URL=https://api.wasender.com"
Write-Host "  WASENDER_API_KEY=your_wasender_api_key"
Write-Host "  WASENDER_DEVICE_ID=your_device_id"
Write-Host ""
Write-Host "=== Test Complete ===" -ForegroundColor Cyan