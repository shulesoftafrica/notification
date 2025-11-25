# Resend Integration for Laravel Notification Service

## ✅ Successfully Integrated!

Resend.com has been successfully added as an email provider to your notification system.

## Configuration

The following has been configured:

### 1. Provider Configuration (`config/notification.php`)
```php
'resend' => [
    'driver' => 'resend',
    'api_key' => env('RESEND_API_KEY', 'fake-resend-key-for-testing'),
    'from_email' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
    'from_name' => env('MAIL_FROM_NAME', 'Example'),
    'channels' => ['email'],
    'priority' => 95, // Higher priority than SendGrid (90)
    'enabled' => true,
],
```

### 2. Channel Configuration
```php
'channels' => [
    'email' => [
        'providers' => ['resend', 'sendgrid', 'mailgun'],
        'default' => 'resend',
    ],
],
```

### 3. EmailAdapter Updated
- Added `sendViaResend()` method
- Added health check for Resend API
- Added cost calculation ($0.001 per email)
- Integrated with switch statement for provider selection

## Setup for Production

To use Resend in production:

### 1. Add to .env file:
```bash
RESEND_API_KEY=re_your_actual_resend_api_key_here
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Your App Name"
```

### 2. Verify Domain in Resend
1. Go to [Resend Dashboard](https://resend.com/domains)
2. Add your domain (e.g., `yourdomain.com`)
3. Add the required DNS records
4. Wait for verification

### 3. Test with Real API Key
```php
// Test notification
$result = $notificationService->send([
    'channel' => 'email',
    'to' => 'recipient@example.com',
    'subject' => 'Welcome!',
    'message' => 'Welcome to our service!',
    'provider' => 'resend' // Optional: force specific provider
]);
```

## Features Supported

✅ **Basic Email Sending**: Text and HTML content
✅ **Custom From Address**: Configurable sender info
✅ **Subject Lines**: Full subject support
✅ **Tags**: Up to 10 tags per email (from metadata)
✅ **Reply-To**: Custom reply-to addresses
✅ **Health Checks**: API validation
✅ **Error Handling**: Proper error responses
✅ **Cost Tracking**: $0.001 per email estimation
✅ **Provider Failover**: Automatic fallback to other providers

## API Endpoints

All existing API endpoints work with Resend:

```bash
# Send notification (will auto-select best provider)
POST /api/notifications/send
{
    "channel": "email",
    "to": "user@example.com",
    "subject": "Hello",
    "message": "Your message here"
}

# Force Resend provider
POST /api/notifications/send
{
    "channel": "email",
    "to": "user@example.com", 
    "subject": "Hello",
    "message": "Your message here",
    "provider": "resend"
}
```

## Priority System

Providers are selected by priority (higher = preferred):
- **Resend**: 95 (highest priority)
- **SendGrid**: 90
- **Mailgun**: 80

The system will automatically use Resend when it's healthy, and fall back to SendGrid/Mailgun if needed.

## Monitoring

Check logs for Resend activity:
```bash
tail -f storage/logs/laravel.log | grep resend
```

View metrics in database:
```sql
SELECT * FROM metrics WHERE JSON_EXTRACT(labels, '$.provider') = 'resend';
```

## 🎉 Ready to Use!

Your notification system now supports Resend with:
- Automatic provider selection
- Health monitoring
- Error handling
- Cost tracking
- Full API compatibility

Just add your real API key and verified domain to start sending emails via Resend!
