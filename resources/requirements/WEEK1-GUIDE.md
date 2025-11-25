# Notification Service - Week 1 Implementation Guide

## 🚀 Overview

This is the Week 1 implementation of a comprehensive multi-tenant notification service built with Laravel 12, featuring HMAC authentication, message queuing, and multi-provider support for email, SMS, and WhatsApp.

**Database:** PostgreSQL for better JSON support and performance

## 📋 What's Implemented in Week 1

### ✅ Core Foundation
- **Laravel 12 Project Setup** with PHP 8.3 compatibility
- **Environment Configuration** for MySQL, Redis, and provider APIs
- **Database Schema** with 6 comprehensive tables
- **HMAC-SHA256 Authentication** for secure API access
- **Message API Endpoints** for sending and retrieving messages
- **Queue Infrastructure** ready for message processing
- **Debugging Tools** with Laravel Telescope

### ✅ Database Architecture
- `projects` - Project management with encrypted secrets
- `project_tenants` - Multi-tenant associations
- `templates` - Versioned message templates
- `messages` - Complete message lifecycle tracking
- `provider_configs` - Multi-provider configurations
- `receipts` - Delivery receipt tracking
- `api_request_logs` - Complete API audit logging

## 🛠️ Installation & Setup

### Prerequisites
- XAMPP with PHP 8.3+ and PostgreSQL 13+
- Composer
- Redis server

### Step 1: PostgreSQL Setup
**Important:** PostgreSQL must be installed and configured first.
See `POSTGRESQL-SETUP.md` for detailed installation instructions.

### Step 2: Database Setup
Create the database and run migrations:
```bash
# Create database (run in psql)
CREATE DATABASE notification_service;

# Run migrations
php artisan migrate:fresh
```

### Step 3: Environment Variables
Key configurations in `.env`:
```env
# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=notification_service
DB_USERNAME=postgres
DB_PASSWORD=your_password

# Redis Queue
QUEUE_CONNECTION=redis
REDIS_CLIENT=predis

# Provider APIs (configure with your keys)
SENDGRID_API_KEY=your_sendgrid_key
TWILIO_ACCOUNT_SID=your_twilio_sid
TWILIO_AUTH_TOKEN=your_twilio_token
WHATSAPP_TOKEN=your_whatsapp_token

# Security
APP_KEY=base64:... (generated)
HMAC_SECRET_KEY=your_hmac_secret
```

### Step 4: Provider Dependencies
Install provider SDKs:
```bash
composer require sendgrid/sendgrid
composer require twilio/sdk
```

## 🐘 PostgreSQL Benefits for Notification Service

### Why PostgreSQL over MySQL:
- **Superior JSON Support**: Better handling of message metadata and templates
- **Advanced Indexing**: GIN indexes for complex JSON queries  
- **Concurrent Performance**: Better handling of high-volume notifications
- **Full-Text Search**: Built-in search capabilities for message content
- **Data Integrity**: Stronger ACID compliance for reliable message tracking
- **UUID Support**: Native UUID type for better distributed system support

## 🔐 Authentication System

### HMAC-SHA256 Signature Authentication
Projects authenticate using HMAC signatures in the `Authorization` header:

**Header Format:**
```
Authorization: Bearer PROJECT_ID:TIMESTAMP:SIGNATURE
```

**Signature Generation (PHP example):**
```php
$projectId = 'your-project-uuid';
$secretKey = 'your-project-secret';
$timestamp = time();
$method = 'POST';
$uri = '/api/v1/messages';
$body = '{"message":{"content":"Hello World"}}';

// Create signature payload
$payload = $method . '|' . $uri . '|' . $body . '|' . $timestamp;

// Generate HMAC signature
$signature = hash_hmac('sha256', $payload, $secretKey);

// Authorization header
$authHeader = "Bearer {$projectId}:{$timestamp}:{$signature}";
```

## 📡 API Endpoints

### Health Check
```
GET /api/health
```
No authentication required. Returns service status.

### Send Message
```
POST /api/v1/messages
Authorization: Bearer PROJECT_ID:TIMESTAMP:SIGNATURE
Content-Type: application/json

{
    "tenant_id": "tenant-uuid",
    "channel": "email",
    "recipient": {
        "email": "user@example.com",
        "name": "John Doe"
    },
    "message": {
        "subject": "Welcome!",
        "content": "Welcome to our service",
        "template_id": "welcome-email"
    },
    "metadata": {
        "user_id": "123",
        "campaign": "onboarding"
    }
}
```

### Get Message Status
```
GET /api/v1/messages/{messageId}
Authorization: Bearer PROJECT_ID:TIMESTAMP:SIGNATURE
```

### List Messages
```
GET /api/v1/messages?channel=email&status=sent&limit=50
Authorization: Bearer PROJECT_ID:TIMESTAMP:SIGNATURE
```

## 🧪 Testing the Implementation

### 1. Create a Test Project
```sql
INSERT INTO projects (
    id, 
    name, 
    secret_key, 
    webhook_url, 
    rate_limit_per_minute,
    created_at, 
    updated_at
) VALUES (
    '550e8400-e29b-41d4-a716-446655440000',
    'Test Project',
    'test-secret-key-for-hmac',
    'https://your-app.com/webhooks',
    100,
    NOW(),
    NOW()
);
```

### 2. Test Authentication with cURL
```bash
# Test health endpoint (no auth)
curl -X GET http://localhost/notification/notification-service/public/api/health

# Test protected endpoint (requires auth)
curl -X POST http://localhost/notification/notification-service/public/api/v1/messages \
  -H "Authorization: Bearer 550e8400-e29b-41d4-a716-446655440000:1640995200:your-calculated-signature" \
  -H "Content-Type: application/json" \
  -d '{
    "tenant_id": "tenant-123",
    "channel": "email",
    "recipient": {"email": "test@example.com"},
    "message": {"subject": "Test", "content": "Hello World"}
  }'
```

### 3. Monitor with Laravel Telescope
Access debugging dashboard:
```
http://localhost/notification/notification-service/public/telescope
```

## 🔧 Development Tools

### Queue Worker (for message processing)
```bash
php artisan queue:work redis --sleep=3 --tries=3
```

### Tinker Console (for testing models)
```bash
php artisan tinker

# Test creating a project
$project = App\Models\Project::create([
    'name' => 'Test Project',
    'secret_key' => 'test-secret',
    'webhook_url' => 'https://example.com/webhook'
]);

# Test relationships
$project->messages()->count();
```

### Database Inspection
```bash
# Check migration status
php artisan migrate:status

# Reset database (if needed)
php artisan migrate:fresh

# Seed test data (when seeders are created)
php artisan db:seed
```

## 📊 Model Relationships

### Project Model
```php
// A project has many tenants
$project->tenants;

// A project has many messages across all tenants
$project->messages;

// A project has many templates
$project->templates;

// A project has provider configurations
$project->providerConfigs;
```

### Message Model
```php
// Message belongs to project and tenant
$message->project;
$message->projectTenant;

// Message has many receipts (delivery confirmations)
$message->receipts;

// Message uses a template
$message->template;
```

## 🛡️ Security Features

### 1. HMAC Authentication
- Request tampering protection
- Timestamp validation (prevents replay attacks)
- Project-specific secret keys

### 2. Input Validation
- Comprehensive request validation rules
- Sanitized database inputs
- Type-safe model attributes

### 3. Rate Limiting
- Per-project rate limits
- Configurable limits in database
- Redis-based tracking

### 4. Audit Logging
- All API requests logged
- Request/response body tracking
- Performance metrics

## 📈 Next Steps (Week 2+)

### Immediate Priorities
1. **Message Queue Processing** - Implement job classes for actual message sending
2. **Provider Integration** - Add SendGrid, Twilio, WhatsApp adapters
3. **Template Engine** - Build dynamic template rendering
4. **Webhook Handlers** - Process delivery receipts from providers

### Future Enhancements
1. **Admin Dashboard** - Web interface for project management
2. **Advanced Analytics** - Delivery rates, performance metrics
3. **A/B Testing** - Template variation testing
4. **Scheduled Messages** - Delayed and recurring messages

## 🐛 Troubleshooting

### Common Issues

1. **Database Connection**
   ```bash
   # Test connection
   php artisan migrate:status
   ```

2. **Redis Connection**
   ```bash
   # Test Redis
   php artisan queue:work --once
   ```

3. **Authentication Errors**
   - Verify HMAC signature calculation
   - Check timestamp (must be within 5 minutes)
   - Ensure project exists in database

4. **Route Not Found**
   ```bash
   # Clear route cache
   php artisan route:clear
   
   # List routes
   php artisan route:list --path=api
   ```

## 📚 Code Structure

```
app/
├── Http/
│   ├── Controllers/Api/V1/
│   │   └── MessageController.php
│   ├── Middleware/
│   │   └── AuthenticateProject.php
│   ├── Requests/
│   │   └── SendMessageRequest.php
│   └── Resources/
│       └── MessageResource.php
├── Models/
│   ├── Project.php
│   ├── ProjectTenant.php
│   ├── Template.php
│   ├── Message.php
│   ├── ProviderConfig.php
│   └── Receipt.php
database/
├── migrations/
│   ├── create_projects_table.php
│   ├── create_project_tenants_table.php
│   ├── create_templates_table.php
│   ├── create_messages_table.php
│   ├── create_provider_configs_table.php
│   └── create_receipts_table.php
routes/
└── api.php
```

This Week 1 implementation provides a solid foundation for a production-ready notification service with authentication, database architecture, and API endpoints ready for testing and further development.
