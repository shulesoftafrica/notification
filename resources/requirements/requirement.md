# Centralized Notification Service - Technical Requirements (Laravel)

**Goal:** Build a standalone Laravel-based service to handle all outbound notifications (Email, SMS, WhatsApp) for multiple SaaS projects.

---

## 1) Architecture

* Laravel API-only application.
* Event-driven and queue-based processing.
* Background workers handle sending messages via channel adapters.
* Dedicated Notification DB for messages, templates, tenant/project configs, logs, and analytics.
* Multi-tenant aware: each message tied to `project_id` and `tenant_id`.

---

## 2) Multi-Tenancy & Projects

* Service does not read project databases directly.
* Projects push templates and send events/messages via API or message bus.
* Templates are stored per project and per tenant.

---

## 3) Database

* Dedicated database with tables:

  * `projects`, `tenants`, `api_clients`, `templates`, `messages`, `provider_configs`, `rate_limits`, `receipts`, `audit_logs`, `contacts_cache`.
* Store minimal PII; encrypt sensitive fields.
* Index on `tenant_id`, `project_id`, `status`, `idempotency_key`.

---

## 4) Messaging

* Single API entry point: `sendMessage()`.
* Channels handled via adapters: EmailAdapter, SmsAdapter, WhatsAppAdapter.
* Support multi-channel fallbacks and retries.
* Use queue (Redis/SQS) for asynchronous sending.
* Return unique `message_id` immediately upon enqueue.
* Idempotency: prevent duplicates with `idempotency_key`.
* Record provider IDs, costs, and status updates.

---

## 5) Templates

* Store per project/tenant/channel/locale.
* Versioned with draft/publish workflow.
* Render templates server-side using variables passed from projects.

---

## 6) Delivery Receipts

* Receive webhooks from providers to update message status: `sent`, `delivered`, `failed`.
* Match provider messages to internal `message_id`.
* Provide API or project webhook for status reporting.

---

## 7) Project Authentication & Message Format

### Authentication Method: HMAC-SHA256 Signature

Each project receives:
- **API Key:** Unique identifier (e.g., `proj_abc123_live`)
- **Secret Key:** For HMAC signing (e.g., `sk_live_xyz789...`)
- **Project ID:** Internal identifier (e.g., `project_123`)

### Required Headers for ALL Requests

```http
Content-Type: application/json
X-API-Key: proj_abc123_live
X-Signature: sha256=<hmac_signature>
X-Timestamp: 1692180000
X-Project-ID: project_123
X-Tenant-ID: tenant_456
X-Idempotency-Key: unique_request_id_123
X-Request-ID: req_uuid_for_tracing
```

### HMAC Signature Generation

**Signing String Format:**
```
{method}\n{uri}\n{timestamp}\n{body_hash}
```

**Example for Project A:**
```php
// Project A sending a message
$method = 'POST';
$uri = '/v1/messages';
$timestamp = time();
$body = json_encode($payload);
$body_hash = hash('sha256', $body);

$signing_string = "{$method}\n{$uri}\n{$timestamp}\n{$body_hash}";
$signature = hash_hmac('sha256', $signing_string, $secret_key);

// Header: X-Signature: sha256={$signature}
```

### Message Format Specification

**Standard Message Payload (All Projects):**
```json
{
  "to": {
    "email": "user@example.com",
    "phone": "+1234567890",
    "name": "John Doe"
  },
  "channel": "email",
  "template_id": "welcome_email_v1",
  "variables": {
    "user": {
      "name": "John Doe",
      "email": "user@example.com"
    },
    "app_name": "Project A App",
    "verification_code": "123456"
  },
  "options": {
    "fallback_channels": ["sms"],
    "priority": "normal",
    "scheduled_at": "2025-08-26T15:30:00Z"
  },
  "metadata": {
    "external_id": "user_123_welcome",
    "campaign_id": "onboarding_2025",
    "source": "user_registration"
  }
}
```

### Example Requests from Different Projects

**Project A (E-commerce Platform):**
```http
POST /v1/messages HTTP/1.1
Host: notifications.service.com
Content-Type: application/json
X-API-Key: proj_ecom_live_abc123
X-Signature: sha256=d4f2c8b1a7e9f3d2c5b8a1e4f7c0d3a6b9e2f5c8a1d4f7b0c3e6f9a2d5c8b1e4
X-Timestamp: 1692180000
X-Project-ID: ecommerce_platform
X-Tenant-ID: store_456
X-Idempotency-Key: order_confirmation_789_20250826
X-Request-ID: req_550e8400-e29b-41d4-a716-446655440000

{
  "to": {
    "email": "customer@email.com",
    "name": "Jane Smith"
  },
  "channel": "email",
  "template_id": "order_confirmation_v2",
  "variables": {
    "customer": {
      "name": "Jane Smith",
      "email": "customer@email.com"
    },
    "order": {
      "id": "ORD-789",
      "total": "$129.99",
      "items": ["Widget A", "Widget B"]
    },
    "store_name": "Amazing Store"
  },
  "options": {
    "priority": "high",
    "fallback_channels": ["sms"]
  },
  "metadata": {
    "external_id": "order_789_confirmation",
    "campaign_id": "order_confirmations",
    "source": "checkout_complete"
  }
}
```

**Project B (CRM System):**
```http
POST /v1/messages HTTP/1.1
Host: notifications.service.com
Content-Type: application/json
X-API-Key: proj_crm_live_def456
X-Signature: sha256=a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2
X-Timestamp: 1692180000
X-Project-ID: crm_system
X-Tenant-ID: company_789
X-Idempotency-Key: lead_followup_456_20250826
X-Request-ID: req_660e8400-e29b-41d4-a716-446655440001

{
  "to": {
    "phone": "+1234567890",
    "name": "Bob Johnson"
  },
  "channel": "sms",
  "template_id": "lead_followup_sms_v1",
  "variables": {
    "lead": {
      "name": "Bob Johnson",
      "company": "Tech Corp"
    },
    "agent": {
      "name": "Sarah Wilson",
      "phone": "+1987654321"
    },
    "meeting_time": "Tomorrow at 2 PM"
  },
  "options": {
    "priority": "normal"
  },
  "metadata": {
    "external_id": "lead_456_followup",
    "campaign_id": "lead_nurturing_q3",
    "source": "crm_automation"
  }
}
```

### Authentication Middleware Flow

**Laravel Middleware: `AuthenticateProject`**
```php
public function handle($request, $next)
{
    // 1. Extract headers
    $apiKey = $request->header('X-API-Key');
    $signature = $request->header('X-Signature');
    $timestamp = $request->header('X-Timestamp');
    $projectId = $request->header('X-Project-ID');
    $tenantId = $request->header('X-Tenant-ID');
    
    // 2. Validate timestamp (prevent replay attacks)
    if (abs(time() - $timestamp) > 300) { // 5 minutes
        return response()->json(['error' => 'Request expired'], 401);
    }
    
    // 3. Lookup project credentials
    $project = Project::where('api_key', $apiKey)
                     ->where('project_id', $projectId)
                     ->where('status', 'active')
                     ->first();
                     
    if (!$project) {
        return response()->json(['error' => 'Invalid API key'], 401);
    }
    
    // 4. Verify HMAC signature
    $expectedSignature = $this->generateSignature(
        $request->method(),
        $request->getRequestUri(),
        $timestamp,
        $request->getContent(),
        $project->secret_key
    );
    
    if (!hash_equals($expectedSignature, str_replace('sha256=', '', $signature))) {
        return response()->json(['error' => 'Invalid signature'], 401);
    }
    
    // 5. Validate tenant access
    if (!$project->tenants()->where('tenant_id', $tenantId)->exists()) {
        return response()->json(['error' => 'Tenant access denied'], 403);
    }
    
    // 6. Attach to request for downstream use
    $request->merge([
        'authenticated_project' => $project,
        'authenticated_tenant_id' => $tenantId
    ]);
    
    return $next($request);
}
```

### Database Schema for Project Authentication

```sql
-- Projects table
CREATE TABLE projects (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    project_id VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    api_key VARCHAR(100) UNIQUE NOT NULL,
    secret_key TEXT NOT NULL, -- Encrypted
    status ENUM('active', 'suspended', 'inactive') DEFAULT 'active',
    rate_limit_per_minute INTEGER DEFAULT 1000,
    rate_limit_per_hour INTEGER DEFAULT 50000,
    rate_limit_per_day INTEGER DEFAULT 1000000,
    webhook_url TEXT,
    webhook_secret TEXT, -- Encrypted
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_api_key (api_key),
    INDEX idx_project_id (project_id)
);

-- Project-Tenant associations
CREATE TABLE project_tenants (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    project_id VARCHAR(50) NOT NULL,
    tenant_id VARCHAR(50) NOT NULL,
    permissions JSON NOT NULL, -- ["send_messages", "manage_templates"]
    status ENUM('active', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id),
    UNIQUE KEY unique_project_tenant (project_id, tenant_id),
    INDEX idx_tenant_project (tenant_id, project_id)
);

-- API request logs for monitoring
CREATE TABLE api_request_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    project_id VARCHAR(50) NOT NULL,
    tenant_id VARCHAR(50),
    request_id VARCHAR(100),
    endpoint VARCHAR(255),
    method VARCHAR(10),
    status_code INTEGER,
    response_time_ms INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project_created (project_id, created_at),
    INDEX idx_request_id (request_id)
);
```

### Security Considerations

**1. API Key Rotation:**
- Projects can request new API keys
- Old keys have 30-day grace period
- Automatic notification before expiry

**2. Rate Limiting by Project:**
- Per-project rate limits in database
- Redis-based token bucket implementation
- Different limits for different subscription tiers

**3. Request Validation:**
- Timestamp validation (max 5 minutes old)
- Signature verification with constant-time comparison
- Idempotency key checking (24-hour window)

**4. Monitoring & Alerting:**
- Failed authentication attempts tracking
- Unusual request patterns detection
- Automatic suspension on suspicious activity

---

## 8) API Endpoints

* `POST /v1/messages` - enqueue message (requires project authentication)
* `GET /v1/messages/{message_id}/status` - get status (requires project authentication)
* `POST /v1/templates` - create/update templates (requires project authentication)
* `POST /v1/receipts/{provider}` - provider webhook (no authentication)
* `POST /v1/tenants/{id}/config` - channel/provider settings (requires admin authentication)

### Response Format for sendMessage

**Success Response (202 Accepted):**
```json
{
  "data": {
    "message_id": "msg_550e8400_e29b_41d4",
    "status": "queued",
    "created_at": "2025-08-26T10:30:00Z",
    "estimated_delivery": "2025-08-26T10:30:30Z"
  },
  "meta": {
    "project_id": "ecommerce_platform",
    "tenant_id": "store_456",
    "channel": "email",
    "priority": "high"
  }
}
```

**Error Response (Authentication Failed):**
```json
{
  "error": {
    "code": "AUTHENTICATION_FAILED",
    "message": "Invalid HMAC signature",
    "details": {
      "expected_format": "X-Signature: sha256=<hmac_hash>",
      "provided": "sha256=invalid_hash"
    },
    "trace_id": "trace_550e8400_e29b_41d4"
  }
}
```

### Project Onboarding Process

**Step 1: Project Registration**
```sql
INSERT INTO projects (
  project_id, name, api_key, secret_key, 
  status, created_at, rate_limit_per_minute
) VALUES (
  'ecommerce_platform', 
  'E-commerce Platform',
  'proj_ecom_live_abc123',
  'sk_live_xyz789abcdef...',
  'active',
  NOW(),
  1000
);
```

**Step 2: Tenant Association**
```sql
INSERT INTO project_tenants (
  project_id, tenant_id, permissions, created_at
) VALUES (
  'ecommerce_platform',
  'store_456', 
  '["send_messages", "manage_templates"]',
  NOW()
);
```

**Step 3: SDK Integration Example**

**PHP SDK for Projects:**
```php
<?php

class NotificationServiceClient 
{
    private $apiKey;
    private $secretKey;
    private $projectId;
    private $baseUrl;
    
    public function __construct($apiKey, $secretKey, $projectId, $baseUrl) {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->projectId = $projectId;
        $this->baseUrl = $baseUrl;
    }
    
    public function sendMessage($tenantId, $payload) {
        $timestamp = time();
        $body = json_encode($payload);
        $signature = $this->generateSignature('POST', '/v1/messages', $timestamp, $body);
        
        $headers = [
            'Content-Type: application/json',
            'X-API-Key: ' . $this->apiKey,
            'X-Signature: sha256=' . $signature,
            'X-Timestamp: ' . $timestamp,
            'X-Project-ID: ' . $this->projectId,
            'X-Tenant-ID: ' . $tenantId,
            'X-Idempotency-Key: ' . uniqid('msg_', true),
            'X-Request-ID: ' . $this->generateUuid()
        ];
        
        // Make HTTP request...
        return $this->makeRequest('POST', '/v1/messages', $headers, $body);
    }
    
    private function generateSignature($method, $uri, $timestamp, $body) {
        $bodyHash = hash('sha256', $body);
        $signingString = "{$method}\n{$uri}\n{$timestamp}\n{$bodyHash}";
        return hash_hmac('sha256', $signingString, $this->secretKey);
    }
}

// Usage in Project A:
$client = new NotificationServiceClient(
    'proj_ecom_live_abc123',
    'sk_live_xyz789abcdef...',
    'ecommerce_platform',
    'https://notifications.service.com'
);

$response = $client->sendMessage('store_456', [
    'to' => ['email' => 'customer@email.com', 'name' => 'Jane Smith'],
    'channel' => 'email',
    'template_id' => 'order_confirmation_v2',
    'variables' => ['order' => ['id' => 'ORD-789']]
]);
```

---

## 9) Workers & Jobs

* Jobs: `DispatchMessage`, `SendEmail`, `SendSms`, `SendWhatsapp`, `HandleReceipt`.
* Workers read queue, call adapters, update DB.
* Handle retries with exponential backoff.

---

## 9) Providers

* Email: SMTP, SES, SendGrid
* SMS: Africa’s Talking, Twilio
* WhatsApp: WhatsApp Cloud API
* Adapters implement unified interface: `send(Message $message): SendResult`
* Multi-provider support with failover

---

## 10) Observability

* Structured JSON logging, correlation IDs
* Metrics: queue depth, throughput, delivery success, cost per channel/tenant
* Admin dashboard for templates, message status, provider configs, rate limits
* Alerts for failures, queue saturation, SLA breaches

---

## 11) API Specifications & Standards

### Request/Response Schemas
* **Content-Type:** `application/json` for all requests/responses
* **API Versioning:** URL-based versioning (`/v1/`, `/v2/`)
* **Standard HTTP Status Codes:**
  - `200 OK` - Successful operation
  - `201 Created` - Resource created successfully
  - `202 Accepted` - Request accepted for processing
  - `400 Bad Request` - Invalid request data
  - `401 Unauthorized` - Authentication required
  - `403 Forbidden` - Insufficient permissions
  - `404 Not Found` - Resource not found
  - `409 Conflict` - Duplicate idempotency key
  - `422 Unprocessable Entity` - Validation errors
  - `429 Too Many Requests` - Rate limit exceeded
  - `500 Internal Server Error` - Server error

### Request Validation (JSON Schema)
```json
// POST /v1/messages
{
  "to": "string|required|email_or_phone",
  "channel": "enum:email,sms,whatsapp|required",
  "template_id": "string|required",
  "variables": "object|optional",
  "fallback_channels": "array|optional",
  "priority": "enum:low,normal,high,urgent|default:normal",
  "scheduled_at": "datetime|optional|future"
}
```

### Error Response Format
```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "to": ["The to field is required."],
      "template_id": ["The selected template_id is invalid."]
    },
    "trace_id": "uuid"
  }
}
```

### Pagination (RFC 5988)
* Use `Link` header for pagination
* Query params: `page`, `per_page` (max 100), `cursor` for large datasets
* Response meta: `total`, `per_page`, `current_page`, `last_page`

---

* OAuth2 or HMAC for API authentication
* Encrypt sensitive data at rest and in transit
* RBAC for admin console
* Audit logs for all actions

---

## 19) Development Implementation Plan

### Phase 1: Foundation (Weeks 1-2)
1. **Laravel Setup:** Create API-only Laravel app with traditional PHP setup
2. **Database Design:** Implement migrations for all core tables
3. **Authentication:** Implement HMAC authentication middleware
4. **Basic API Structure:** Create controllers, requests, resources
5. **Environment Configuration:** Setup .env files for different environments

### Phase 2: Core Messaging (Weeks 3-4)
1. **Message API:** Implement `POST /v1/messages` with validation
2. **Queue System:** Configure Redis/SQS with Laravel Horizon
3. **Job Processing:** Create and test background job workers
4. **Basic Adapters:** Implement email adapter (SMTP/SES)

### Phase 3: Template System (Week 5)
1. **Template Storage:** Implement template CRUD operations
2. **Template Engine:** Integrate Blade with variable validation
3. **Template Rendering:** Server-side rendering with security controls
4. **Preview System:** Template testing and preview API

### Phase 4: Provider Integration (Weeks 6-7)
1. **Provider Adapters:** Complete SMS and WhatsApp adapters
2. **Failover Logic:** Implement provider failover and load balancing
3. **Webhook Handlers:** Provider receipt processing
4. **Provider Health Monitoring:** Circuit breaker implementation

### Phase 5: Advanced Features (Weeks 8-9)
1. **Rate Limiting:** Implement multi-level rate limiting
2. **Client Webhooks:** Outbound webhook system with retries
3. **Monitoring:** Metrics collection and dashboard setup
4. **Admin Interface:** Basic admin console for management

### Phase 6: Production Readiness (Weeks 10-12)
1. **Security Hardening:** Complete security implementation
2. **Performance Optimization:** Query optimization, caching
3. **Testing:** Comprehensive test suite (unit, integration, load)
4. **Documentation:** API documentation, deployment guides
5. **Deployment:** Production deployment with CI/CD pipeline

---

### Outbound Webhooks (to Client Apps)
* **Delivery Guarantees:** At-least-once delivery with exponential backoff
* **Retry Policy:** 1s, 2s, 4s, 8s, 16s, 30s, 1m, 2m, 5m, 10m (max 10 attempts)
* **Timeout:** 30 seconds per attempt
* **Expected Response:** HTTP 2xx for success
* **Signature Verification:** HMAC-SHA256 with secret key
* **Headers:**
  ```
  X-Webhook-Signature: sha256=<signature>
  X-Webhook-Timestamp: <unix_timestamp>
  X-Webhook-Event: message.delivered
  User-Agent: NotificationService/1.0
  ```

### Webhook Payload Format
```json
{
  "event": "message.delivered",
  "timestamp": "2025-08-26T10:30:00Z",
  "data": {
    "message_id": "msg_123",
    "external_id": "client_ref_456",
    "status": "delivered",
    "channel": "email",
    "delivered_at": "2025-08-26T10:29:45Z",
    "provider": "sendgrid",
    "cost": {"amount": 0.001, "currency": "USD"}
  }
}
```

### Webhook Management API
* `POST /v1/webhooks` - Register webhook endpoint
* `PUT /v1/webhooks/{id}` - Update webhook configuration
* `DELETE /v1/webhooks/{id}` - Delete webhook
* `POST /v1/webhooks/{id}/test` - Test webhook delivery

---

## 13) Template System Specifications

### Template Engine
* **Engine:** Laravel Blade with custom notification extensions
* **Syntax:** Mustache-style variables `{{ variable }}` for safety
* **Security:** Auto-escaping enabled, no raw PHP execution

### Template Structure
```json
{
  "id": "welcome_email_v1",
  "name": "Welcome Email",
  "channel": "email",
  "locale": "en",
  "version": "1.0",
  "status": "published", // draft, published, archived
  "content": {
    "subject": "Welcome to {{ app_name }}!",
    "html_body": "<h1>Hello {{ user.name }}</h1>...",
    "text_body": "Hello {{ user.name }}...",
    "from_name": "{{ app_name }} Team",
    "reply_to": "support@{{ domain }}"
  },
  "variables": {
    "user.name": {"type": "string", "required": true},
    "app_name": {"type": "string", "required": true},
    "domain": {"type": "string", "required": true}
  }
}
```

### Template Validation & Testing
* **Variable Validation:** Type checking, required field validation
* **Preview API:** `POST /v1/templates/{id}/preview` with test data
* **A/B Testing:** Support for template variants
* **Approval Workflow:** Draft → Review → Published states

### Template Inheritance
* Base templates for common layouts
* Channel-specific overrides
* Locale-specific content with fallbacks

---

## 14) Provider Configuration & Management

### Provider Configuration Schema
```json
{
  "tenant_id": "tenant_123",
  "channel": "email",
  "provider": "sendgrid",
  "priority": 1, // Lower number = higher priority
  "enabled": true,
  "config": {
    "api_key": "encrypted_key",
    "from_email": "noreply@client.com",
    "from_name": "Client App"
  },
  "limits": {
    "daily_limit": 10000,
    "monthly_limit": 300000,
    "rate_per_minute": 600
  },
  "cost_tracking": {
    "cost_per_message": 0.001,
    "currency": "USD",
    "billing_code": "email_notifications"
  }
}
```

### Failover Strategy
* **Primary-Secondary Pattern:** Automatic failover on provider failure
* **Round-Robin:** Distribute load across multiple providers
* **Cost Optimization:** Route to cheapest available provider
* **Geographic Routing:** Provider selection based on recipient location

### Provider Health Monitoring
* **Health Checks:** Periodic API availability tests
* **Circuit Breaker Pattern:** Temporarily disable failing providers
* **Provider Metrics:** Success rate, latency, cost per provider

---

## 15) Rate Limiting & Quotas

### Multi-Level Rate Limiting
1. **Global Service Level:** Overall system capacity protection
2. **Per-Tenant Level:** Prevent tenant abuse
3. **Per-Provider Level:** Respect provider limits
4. **Per-Channel Level:** Different limits for email/SMS/WhatsApp

### Rate Limiting Implementation
* **Algorithm:** Token bucket with Redis backend
* **Windows:** Per-minute, per-hour, per-day limits
* **Burst Handling:** Allow short bursts within limits
* **Headers:** Include rate limit info in responses
  ```
  X-RateLimit-Limit: 1000
  X-RateLimit-Remaining: 999
  X-RateLimit-Reset: 1692180000
  ```

### Quota Management
```json
{
  "tenant_id": "tenant_123",
  "quotas": {
    "email": {"daily": 10000, "monthly": 300000},
    "sms": {"daily": 1000, "monthly": 30000},
    "whatsapp": {"daily": 500, "monthly": 15000}
  },
  "usage": {
    "email": {"daily": 1250, "monthly": 45000},
    "sms": {"daily": 150, "monthly": 5500}
  }
}
```

---

## 16) Monitoring, Metrics & Observability

### Key Performance Indicators (KPIs)
* **Delivery Metrics:**
  - Delivery success rate (target: >99.5%)
  - Average delivery time (target: <30s)
  - Queue processing time (target: <5s)
* **Business Metrics:**
  - Messages per tenant per day
  - Cost per message by channel
  - Template usage analytics
* **System Metrics:**
  - Queue depth and processing rate
  - Provider response times
  - Error rates by provider/channel

### Service Level Agreements (SLAs)
* **Availability:** 99.9% uptime (8.76 hours downtime/year)
* **Performance:** 95% of messages delivered within 60 seconds
* **Queue Processing:** 99% of messages processed within 5 seconds
* **API Response Time:** 95% of API calls respond within 500ms

### Alerting Strategy
* **Critical Alerts:** Service down, queue saturation (>10k messages)
* **Warning Alerts:** High error rate (>5%), slow processing (>2 min)
* **Info Alerts:** Provider failover, quota approaching (>80%)

### Observability Stack
* **Logging:** Structured JSON logs with correlation IDs
* **Metrics:** Prometheus/InfluxDB with Grafana dashboards
* **Tracing:** Distributed tracing for request flows
* **Health Checks:** `/health` endpoint with dependency status

### Dashboard Components
1. **Operational Dashboard:** Real-time metrics, queue status
2. **Business Dashboard:** Usage analytics, cost tracking
3. **Tenant Dashboard:** Per-tenant metrics and quotas
4. **Provider Dashboard:** Provider performance and costs

---

## 17) Security Standards

### Authentication & Authorization
* **API Authentication:** OAuth2 Bearer tokens or HMAC-SHA256
* **Admin Console:** OAuth2 with RBAC (Role-Based Access Control)
* **Webhook Authentication:** HMAC signature verification
* **Service-to-Service:** mTLS for internal communication

### Data Protection
* **Encryption at Rest:** AES-256 for sensitive fields (API keys, PII)
* **Encryption in Transit:** TLS 1.3 for all communications
* **Key Management:** AWS KMS/Azure Key Vault for key rotation
* **PII Handling:** Minimal storage, automatic expiration (30 days)

### Security Headers
```
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Content-Security-Policy: default-src 'self'
```

### Audit Logging
* **What to Log:** All API calls, admin actions, configuration changes
* **Log Format:** JSON with correlation IDs
* **Retention:** 1 year for audit logs, 90 days for debug logs
* **Compliance:** Support for GDPR, SOC2, HIPAA requirements

---

## 18) Security

## 12) Development Steps

## 20) Original Development Steps

1. Create Laravel app `notifications-svc`.
2. Implement database migrations and models.
3. Implement `sendMessage()` API and validation.
4. Implement queue configuration and background workers.
5. Implement channel adapters (Email, SMS, WhatsApp).
6. Implement template management and rendering.
7. Implement webhooks to receive provider receipts.
8. Implement admin dashboard for template, tenant, and provider management.
9. Implement metrics, logging, and monitoring.
10. Implement idempotency, retries, and multi-channel fallbacks.

---

## 21) Technical Architecture Details

### Technology Stack
* **Framework:** Laravel 10+ (PHP 8.2+)
* **Database:** PostgreSQL with Redis for caching/queues
* **Queue System:** Laravel Horizon with Redis
* **Monitoring:** Laravel Telescope for development, Prometheus + Grafana for production
* **Logging:** Monolog with structured JSON output
* **Cache:** Redis with Laravel Cache
* **File Storage:** Local storage for development, S3-compatible for production
* **Web Server:** Apache/Nginx with PHP-FPM

### Database Schema Design
```sql
-- Core tables with indexes
CREATE INDEX idx_messages_tenant_status ON messages(tenant_id, status);
CREATE INDEX idx_messages_created_at ON messages(created_at);
CREATE INDEX idx_messages_idempotency ON messages(idempotency_key);
CREATE INDEX idx_templates_tenant_channel ON templates(tenant_id, channel, status);
CREATE INDEX idx_receipts_message_id ON receipts(message_id);
```

### Caching Strategy
* **Template Caching:** Cache rendered templates for 1 hour
* **Provider Config:** Cache provider configurations for 15 minutes
* **Rate Limiting:** Redis-based token bucket implementation
* **API Responses:** Cache static responses for 5 minutes

### Queue Configuration
```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => [
            'high' => 'notifications:high',
            'default' => 'notifications:default',
            'low' => 'notifications:low'
        ],
        'retry_after' => 300,
        'block_for' => null,
    ],
]
```

---

## 22) Deployment & Infrastructure

### Environment Configuration
* **Development:** Local XAMPP/WAMP/MAMP setup with Redis
* **Staging:** Traditional server setup with reduced resources
* **Production:** Load-balanced servers with Redis cluster
* **Backup Strategy:** Automated database backups, 30-day retention

### Server Requirements
* **PHP:** 8.2+ with extensions (redis, pdo_pgsql, mbstring, openssl, curl, json)
* **Web Server:** Apache 2.4+ or Nginx 1.18+
* **Database:** PostgreSQL 13+
* **Redis:** 6.0+ for queues and caching
* **Composer:** Latest version for dependency management

### Monitoring Infrastructure
* **Health Checks:** Custom health endpoint monitoring
* **Log Management:** File-based logs with rotation
* **Metrics Collection:** Custom Laravel metrics with database storage
* **Alerting:** Email/SMS alerts for critical failures

---

---

## 23) Final Implementation Steps

### Prerequisites Setup
1. **Development Environment:**
   - XAMPP/WAMP with PHP 8.2+, PostgreSQL 13+
   - Redis server (Windows/Linux)
   - Composer installed globally
   - Git for version control

2. **Required PHP Extensions:**
   ```bash
   # Verify extensions are enabled
   php -m | grep -E "(redis|pdo_pgsql|mbstring|openssl|curl|json)"
   ```

### Step-by-Step Implementation Plan

#### Week 1: Project Foundation
**Day 1-2: Laravel Setup**
```bash
# 1. Create new Laravel project
composer create-project laravel/laravel notification-service
cd notification-service

# 2. Install required packages
composer require predis/predis
composer require laravel/horizon
composer require laravel/telescope --dev

# 3. Configure environment
cp .env.example .env
# Update database and Redis settings
```

**Day 3-4: Database Design**
```bash
# Create core migrations
php artisan make:migration create_projects_table
php artisan make:migration create_project_tenants_table
php artisan make:migration create_templates_table
php artisan make:migration create_messages_table
php artisan make:migration create_provider_configs_table
php artisan make:migration create_receipts_table
php artisan make:migration create_api_request_logs_table
```

**Day 5: Models and Relationships**
```bash
# Create Eloquent models
php artisan make:model Project
php artisan make:model ProjectTenant
php artisan make:model Template
php artisan make:model Message
php artisan make:model ProviderConfig
php artisan make:model Receipt
php artisan make:model ApiRequestLog
```

#### Week 2: Authentication & Core API
**Day 1-2: Authentication Middleware**
```bash
# Create authentication middleware
php artisan make:middleware AuthenticateProject
php artisan make:request SendMessageRequest
php artisan make:resource MessageResource
```

**Day 3-4: Message API Controller**
```bash
# Create controllers
php artisan make:controller Api/V1/MessageController
php artisan make:controller Api/V1/TemplateController
php artisan make:controller Api/V1/WebhookController

# Create jobs
php artisan make:job DispatchMessage
php artisan make:job SendEmail
php artisan make:job SendSms
php artisan make:job SendWhatsapp
```

**Day 5: API Routes and Testing**
```bash
# Setup routes in routes/api.php
# Create basic tests
php artisan make:test MessageApiTest
```

#### Week 3: Queue System & Background Processing
**Day 1-2: Queue Configuration**
```bash
# Configure Redis queues
php artisan queue:table
php artisan migrate

# Setup Horizon for queue monitoring
php artisan horizon:install
php artisan vendor:publish --provider="Laravel\Horizon\HorizonServiceProvider"
```

**Day 3-5: Provider Adapters**
```bash
# Create adapter interfaces and implementations
# EmailAdapter, SmsAdapter, WhatsAppAdapter
mkdir app/Services/Adapters
```

#### Week 4: Template System
**Day 1-3: Template Management**
```bash
# Template CRUD operations
php artisan make:controller Api/V1/TemplateController
# Template rendering service
# Variable validation
```

**Day 4-5: Template Testing & Preview**
```bash
# Template preview API
# A/B testing support
```

#### Week 5-6: Provider Integration
**Day 1-5: Complete Provider Adapters**
- SendGrid/SES for Email
- Twilio/Africa's Talking for SMS  
- WhatsApp Cloud API
- Provider health monitoring
- Failover logic

#### Week 7-8: Advanced Features
**Day 1-5: Rate Limiting & Monitoring**
```bash
# Rate limiting middleware
php artisan make:middleware RateLimitRequests
# Metrics collection
# Basic admin dashboard
```

#### Week 9-10: Webhook System
**Day 1-5: Webhook Implementation**
```bash
# Outbound webhooks to clients
php artisan make:job DeliverWebhook
# Provider webhook handlers
# Retry mechanisms
```

#### Week 11-12: Production Readiness
**Day 1-10: Testing & Optimization**
```bash
# Comprehensive test suite
vendor/bin/phpunit

# Performance optimization
# Security hardening
# Documentation
```

### Development Commands Reference

**Start Development Environment:**
```bash
# Start queue workers
php artisan horizon

# Start development server
php artisan serve

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed
```

**Production Deployment:**
```bash
# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Setup supervisor for queue workers
# Configure Apache/Nginx virtual host
# Setup SSL certificates
```

### File Structure Overview
```
notification-service/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   ├── Middleware/
│   │   └── Requests/
│   ├── Models/
│   ├── Jobs/
│   ├── Services/
│   │   ├── Adapters/
│   │   ├── Auth/
│   │   └── Template/
│   └── Providers/
├── config/
├── database/
│   ├── migrations/
│   └── seeders/
├── routes/
│   └── api.php
├── tests/
└── storage/logs/
```

### Configuration Files to Create/Modify

**1. .env Configuration:**
```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=notification_service
DB_USERNAME=root
DB_PASSWORD=

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=redis
HORIZON_PREFIX=notification_

# Provider APIs
SENDGRID_API_KEY=
TWILIO_SID=
TWILIO_TOKEN=
WHATSAPP_ACCESS_TOKEN=
```

**2. Horizon Configuration (config/horizon.php):**
```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['high', 'default', 'low'],
            'balance' => 'auto',
            'processes' => 10,
            'tries' => 3,
        ],
    ],
]
```

This implementation plan provides a clear, step-by-step approach to building the notification service without Docker dependencies, using traditional PHP/Laravel deployment methods.

**Outcome:** A fully functional notification service that can handle multiple projects, process messages through various channels, and provide comprehensive monitoring and analytics - all deployable on traditional LAMP/LEMP stacks.
