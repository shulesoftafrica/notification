# WhatsApp Dual Routing Implementation

## ✅ Implementation Complete!

I've successfully implemented WhatsApp dual routing that allows sending messages via either **Official WhatsApp Business API** or **Wasender** based on a `type` parameter in the API request.

## 🔧 What Was Implemented

### 1. Configuration Updates (`config/notification.php`)
- ✅ Added `wasender` provider configuration with API settings
- ✅ Updated `whatsapp` channel to include both providers: `['whatsapp', 'wasender']`
- ✅ Set priorities: Official WhatsApp (90), Wasender (85)

### 2. Request Validation (`app/Http/Requests/SendMessageRequest.php`)
- ✅ Added `type` parameter validation: `['official', 'wasender']`
- ✅ Optional parameter - defaults to 'official' if not provided

### 3. WhatsApp Adapter (`app/Services/Adapters/WhatsAppAdapter.php`)
- ✅ Updated constructor to accept `providerType` parameter
- ✅ Modified `send()` method to route based on `type` in metadata
- ✅ Added `sendViaWasender()` method for Wasender API integration
- ✅ Updated `sendViaWhatsAppAPI()` to continue supporting Official WhatsApp
- ✅ Added separate health check methods for both providers
- ✅ Dynamic provider name based on selected type

### 4. Notification Service (`app/Services/NotificationService.php`)
- ✅ Added Wasender to provider adapter matching
- ✅ Updated `selectProvider()` method with WhatsApp-specific routing logic
- ✅ Added `selectWhatsAppProvider()` method to handle type-based selection
- ✅ Passes `type` through metadata to adapters

### 5. Testing Tools
- ✅ Created `TestWhatsAppRouting` Artisan command
- ✅ Created PowerShell test script (`test_whatsapp_routing.ps1`)
- ✅ Updated Queue Testing Manual with WhatsApp routing examples

## 📤 API Usage

### Same Format, Different Types

The API format remains **exactly the same** - only the `type` parameter changes:

#### Send via Official WhatsApp Business API
```bash
curl -X POST http://127.0.0.1:8000/api/notifications/send \
-H "Content-Type: application/json" \
-H "X-API-Key: your_api_key" \
-d '{
    "channel": "whatsapp",
    "to": "+255712345678",
    "message": "Hello from Official WhatsApp!",
    "type": "official"
}'
```

#### Send via Wasender
```bash
curl -X POST http://127.0.0.1:8000/api/notifications/send \
-H "Content-Type: application/json" \
-H "X-API-Key": "your_api_key" \
-d '{
    "channel": "whatsapp",
    "to": "+255712345678",
    "message": "Hello from Wasender!",
    "type": "wasender"
}'
```

### PowerShell Examples

```powershell
$headers = @{
    "X-API-Key" = "your_api_key"
    "Content-Type" = "application/json"
}

# Official WhatsApp
$body = @{
    channel = "whatsapp"
    to = "+255712345678"
    message = "Test message"
    type = "official"  # ← Only this changes
} | ConvertTo-Json

Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/notifications/send" -Method POST -Headers $headers -Body $body

# Wasender
$body = @{
    channel = "whatsapp"
    to = "+255712345678"
    message = "Test message"
    type = "wasender"  # ← Only this changes
} | ConvertTo-Json

Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/notifications/send" -Method POST -Headers $headers -Body $body
```

## ⚙️ Configuration Required

### Environment Variables

Add these to your `.env` file:

```env
# Official WhatsApp Business API
WHATSAPP_BUSINESS_PHONE_ID=your_phone_number_id
WHATSAPP_ACCESS_TOKEN=your_meta_access_token

# Wasender API
WASENDER_API_URL=https://api.wasender.com
WASENDER_API_KEY=your_wasender_api_key
WASENDER_DEVICE_ID=your_device_id
```

## 🎯 How It Works

1. **API Request** arrives with `channel: "whatsapp"` and `type: "official"` or `type: "wasender"`
2. **Validation** ensures type is either 'official' or 'wasender'
3. **Provider Selection** 
   - If `type === "wasender"` → Uses Wasender provider
   - If `type === "official"` (or missing) → Uses Official WhatsApp
4. **Routing** sends to appropriate adapter method
5. **Delivery** message sent via selected service

## 🔀 Provider Routing Logic

```
Request with type="wasender"
    ↓
Check if Wasender is available
    ↓ Yes
Use Wasender API
    ↓
Send via sendViaWasender()
    ↓
Return result with provider="wasender"

Request with type="official" (or no type)
    ↓
Check if Official WhatsApp is available
    ↓ Yes
Use Official WhatsApp API
    ↓
Send via sendViaWhatsAppAPI()
    ↓
Return result with provider="whatsapp"
```

## 🧪 Testing

### Using Artisan Command
```bash
# Test Official WhatsApp
php artisan test:whatsapp-routing "+255712345678" "Test message" "official"

# Test Wasender
php artisan test:whatsapp-routing "+255712345678" "Test message" "wasender"
```

### Using PowerShell Script
```bash
.\test_whatsapp_routing.ps1
```

### Manual API Testing
```bash
# Test both types
curl -X POST http://127.0.0.1:8000/api/notifications/send -H "Content-Type: application/json" -H "X-API-Key: test123456789012345678901234567890" -d '{"channel":"whatsapp","to":"+255712345678","message":"Test Official","type":"official"}'

curl -X POST http://127.0.0.1:8000/api/notifications/send -H "Content-Type: application/json" -H "X-API-Key: test123456789012345678901234567890" -d '{"channel":"whatsapp","to":"+255712345678","message":"Test Wasender","type":"wasender"}'
```

## 📊 Features Supported

### Official WhatsApp Business API
- ✅ Text messages
- ✅ Template messages
- ✅ Media messages (image, video, audio, document)
- ✅ Interactive messages
- ✅ Location messages
- ✅ Contact messages
- ✅ Delivery receipts
- ✅ Read receipts

### Wasender API
- ✅ Text messages
- ✅ Media messages (basic support)
- ✅ Device-based routing
- ✅ Custom API URL support

## 🔍 Monitoring

Check which provider was used:

```sql
-- View recent WhatsApp notifications
SELECT id, recipient, provider, status, created_at 
FROM notification_logs 
WHERE channel = 'whatsapp' 
ORDER BY created_at DESC 
LIMIT 10;

-- Count by provider type
SELECT provider, COUNT(*) as count, status
FROM notification_logs 
WHERE channel = 'whatsapp'
GROUP BY provider, status;
```

## 🎉 Benefits

1. **Unified API** - Same request format for both providers
2. **Flexible Routing** - Choose provider per message
3. **Automatic Failover** - Falls back if primary provider fails
4. **Cost Optimization** - Use cheaper provider when appropriate
5. **Business Logic Control** - Route based on customer requirements
6. **Easy Testing** - Test both providers with single parameter change

## 🚀 Ready to Use!

The WhatsApp dual routing system is now fully implemented and ready for production use. Simply:

1. Add your provider credentials to `.env`
2. Use the `type` parameter in API calls to select provider
3. Monitor results in `notification_logs` table

The system automatically handles provider selection, failover, health monitoring, and delivery tracking!