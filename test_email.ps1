# Test Email via Resend API
Write-Host "Testing Email via Resend Provider..." -ForegroundColor Green

# Test email notification
$body = @{
    type = "email"
    to = "abutalha@gmail.com"
    subject = "Test Email from Resend"
    message = "Hello! This is a test email sent via Resend API through the notification service. If you receive this, the email integration is working perfectly!"
} | ConvertTo-Json

Write-Host "Sending email to: abutalha@gmail.com" -ForegroundColor Yellow
Write-Host "Subject: Test Email from Resend" -ForegroundColor Yellow
Write-Host "Provider: Resend (Priority 95)" -ForegroundColor Yellow

try {
    $response = Invoke-RestMethod -Uri "http://localhost:8002/api/notification" `
        -Method POST `
        -Headers @{"Content-Type"="application/json"} `
        -Body $body
    
    Write-Host "`nResponse received:" -ForegroundColor Green
    $response | ConvertTo-Json -Depth 3
    
    if ($response.success -eq $true) {
        Write-Host "`n✅ Email sent successfully!" -ForegroundColor Green
        Write-Host "Provider used: $($response.data.provider)" -ForegroundColor Cyan
        Write-Host "Status: $($response.data.status)" -ForegroundColor Cyan
        Write-Host "Message ID: $($response.data.id)" -ForegroundColor Cyan
    } else {
        Write-Host "`n❌ Email sending failed!" -ForegroundColor Red
        Write-Host "Error: $($response.message)" -ForegroundColor Red
    }
} catch {
    Write-Host "`n❌ Request failed!" -ForegroundColor Red
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $errorBody = $reader.ReadToEnd()
        Write-Host "Response body: $errorBody" -ForegroundColor Red
    }
}

Write-Host "`n📧 Email Provider Configuration:" -ForegroundColor Magenta
Write-Host "- Default Provider: Resend (Priority 95)" -ForegroundColor Gray  
Write-Host "- From Email: noreply@mail.shulesoft.co" -ForegroundColor Gray
Write-Host "- From Name: Shulesoft" -ForegroundColor Gray
Write-Host "- API Key: Configured ✅" -ForegroundColor Gray
Write-Host "- Fallback: SendGrid (Priority 90), Mailgun (Priority 80)" -ForegroundColor Gray