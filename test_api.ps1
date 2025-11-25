$headers = @{
    "X-API-Key" = "test123456789012345678901234567890"
    "Content-Type" = "application/json"
}

$body = @{
    channel = "email"
    to = "test@example.com"
    subject = "Test Subject"
    message = "Test message content"
} | ConvertTo-Json

try {
    $response = Invoke-RestMethod -Uri "http://127.0.0.1:8001/api/notifications/send" -Method POST -Headers $headers -Body $body
    Write-Host "Success:"
    $response | ConvertTo-Json -Depth 5
} catch {
    Write-Host "Error: $($_.Exception.Message)"
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host "Response body: $responseBody"
    }
}
