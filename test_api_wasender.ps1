# Test Wasender via API
Write-Host "=== Testing Wasender Direct API ===" -ForegroundColor Cyan

$headers = @{
    "Authorization" = "Bearer de042e1a46b394de63bed34c5b2d9c55108db5061b075b29ce9225be30d7cca2"
    "Content-Type" = "application/json"
}

$body = @{
    to = "+255714825469"
    text = "Hello from PowerShell API Test!"
} | ConvertTo-Json

Write-Host "Sending request to Wasender API..." -ForegroundColor Yellow
Write-Host "Body: $body"

try {
    $response = Invoke-RestMethod -Uri "https://wasenderapi.com/api/send-message" -Method POST -Headers $headers -Body $body
    Write-Host "✅ Wasender API Success!" -ForegroundColor Green
    Write-Host "Response: $($response | ConvertTo-Json -Depth 3)"
    
} catch {
    Write-Host "❌ Wasender API Failed:" -ForegroundColor Red
    Write-Host $_.Exception.Message
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $errorBody = $reader.ReadToEnd()
        Write-Host "Error body: $errorBody"
    }
}
