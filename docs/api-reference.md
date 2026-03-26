# Notification Service — Complete API Reference

**Version:** 1.0  
**Base URL:** `http://your-domain.com/api`  
**Authentication:** API Key (all endpoints except Health)  
**Last Updated:** March 26, 2026

> **Note for local development:** Base URL is `http://localhost/notification/api`

---

## Table of Contents

1. [Authentication](#1-authentication)
2. [Health Check](#2-health-check)
3. [Notification API](#3-notification-api)
   - [Send Single Notification](#31-send-single-notification)
   - [Send Bulk Notifications](#32-send-bulk-notifications)
   - [Get Notification Status](#33-get-notification-status)
   - [List Notifications](#34-list-notifications)
4. [WaSender Session Management API](#4-wasender-session-management-api)
   - [Create Session](#41-create-session)
   - [List Sessions](#42-list-sessions)
   - [Get Single Session](#43-get-single-session)
   - [Connect Session & Get QR Code](#44-connect-session--get-qr-code)
   - [Check Session Status](#45-check-session-status)
   - [Update Session](#46-update-session)
   - [Get QR Code](#47-get-qr-code)
   - [Delete Session](#48-delete-session)
5. [Webhook Receiver Endpoints](#5-webhook-receiver-endpoints)
6. [Admin Authentication API](#6-admin-authentication-api)
7. [Error Reference](#7-error-reference)
8. [Field Validation Reference](#8-field-validation-reference)
9. [Implementation Notes](#9-implementation-notes)

---

## 1. Authentication

All endpoints (except Health Check) require an API key. The key must be **at least 32 characters** long.

### Supported Header Methods

| Header | Example |
|--------|---------|
| `X-API-Key` | `X-API-Key: your_api_key_here` |
| `X-Api-Key` | `X-Api-Key: your_api_key_here` |
| `X-AUTH-TOKEN` | `X-AUTH-TOKEN: your_api_key_here` |
| `X-Auth-Token` | `X-Auth-Token: your_api_key_here` |
| `Authorization` | `Authorization: Bearer your_api_key_here` |

You may also pass the key as a query parameter `?api_key=your_api_key` (not recommended for production).

### Authentication Error Response (401)

```json
{
  "success": false,
  "error": "Unauthorized",
  "message": "API key required. Please provide an API key in X-API-Key, Authorization, or X-Auth-Token header."
}
```

---

## 2. Health Check

### GET `/api/health`

Returns the operational status of the service. **No authentication required.**

Also available at `GET /api/up`.

#### Response (200 — Healthy)

```json
{
  "status": "healthy",
  "timestamp": "2026-03-26T10:00:00.000Z",
  "checks": {
    "database": true,
    "cache": true
  },
  "uptime": "5d 3h 12m"
}
```

#### Response (503 — Unhealthy)

```json
{
  "status": "unhealthy",
  "timestamp": "2026-03-26T10:00:00.000Z",
  "checks": {
    "database": false,
    "cache": true
  }
}
```

---

## 3. Notification API

All Notification endpoints require API key authentication.

---

### 3.1 Send Single Notification

**`POST /api/notifications/send`**

Sends a single notification via email, SMS, or WhatsApp.

#### Request Headers

```
Content-Type: application/json
X-API-Key: your_api_key_here
```

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `schema_name` | string | ✅ | Tenant/schema identifier (max 255) |
| `channel` | string | ✅ | `email`, `sms`, or `whatsapp` |
| `to` | string | ✅ | Recipient — email address or phone number (max 255) |
| `message` | string | ✅ | Message content (max 4096 chars) |
| `subject` | string | ✅ if email | Email subject line (max 255) |
| `provider` | string | — | `twilio`, `whatsapp`, `sendgrid`, `mailgun`, `resend`, `beem`, `termii` |
| `type` | string | — | `wasender` or `official` — used to select WaSender for WhatsApp |
| `priority` | string | — | `low`, `normal` (default), `high`, `urgent` |
| `scheduled_at` | datetime | — | ISO 8601 future timestamp to schedule delivery |
| `sender_name` | string | — | Override sender name (max 50) |
| `template_id` | string | — | Template identifier (max 100) |
| `template_data` | object | — | Key-value pairs for template substitution (max 10 keys, each value max 1000 chars) |
| `metadata` | object | — | Custom key-value data stored with the message (max 10 keys, each value max 500 chars) |
| `tags` | array | — | String labels for the message (max 10 tags, each max 50 chars) |
| `webhook_url` | string (URL) | — | URL to receive delivery status callbacks (max 2048) |
| `attachment` | string | — | Base64-encoded file content (with or without `data:mime/type;base64,` prefix) |
| `attachment_name` | string | ✅ if attachment | Original filename (max 255) |
| `attachment_type` | string | ✅ if attachment | MIME type of the file (max 100) |

#### Example — Email

```json
{
  "schema_name": "client_tenant_demo",
  "channel": "email",
  "to": "customer@example.com",
  "subject": "Order Confirmation",
  "message": "Your order has been confirmed!",
  "provider": "sendgrid",
  "priority": "high",
  "metadata": { "order_id": "12345" },
  "tags": ["order", "confirmation"],
  "webhook_url": "https://your-app.com/webhook",
  "attachment": "data:application/pdf;base64,JVBERi0xLjQ...",
  "attachment_name": "invoice.pdf",
  "attachment_type": "application/pdf"
}
```

#### Example — SMS

```json
{
  "schema_name": "client_tenant_demo",
  "channel": "sms",
  "to": "+255712345678",
  "message": "Your verification code is: 123456",
  "provider": "beem",
  "priority": "urgent",
  "metadata": { "verification_type": "login" }
}
```

#### Example — WhatsApp via WaSender

```json
{
  "schema_name": "client_tenant_demo",
  "channel": "whatsapp",
  "to": "+255712345678",
  "message": "Hello! Your order is ready for pickup.",
  "type": "wasender",
  "priority": "normal",
  "metadata": { "order_id": "12345" }
}
```

#### Response (201 Created — Success)

```json
{
  "success": true,
  "message_id": 123,
  "external_id": "provider_message_id_abc123",
  "status": "sent",
  "provider": "sendgrid",
  "data": {
    "id": 123,
    "channel": "email",
    "recipient": "customer@example.com",
    "subject": "Order Confirmation",
    "message": "Your order has been confirmed!",
    "status": "sent",
    "priority": "high",
    "provider": "sendgrid",
    "external_id": "provider_message_id_abc123",
    "sent_at": "2026-03-26T10:30:15Z",
    "created_at": "2026-03-26T10:30:00Z",
    "updated_at": "2026-03-26T10:30:15Z"
  }
}
```

#### Response (400 — WaSender Session Missing)

```json
{
  "success": false,
  "error": "WaSender session not found or API key unavailable",
  "message": "No active WaSender session found for schema: client_tenant_demo"
}
```

#### Response (400 — SMS Session Missing)

```json
{
  "success": false,
  "error": "SMS session not found",
  "message": "No SMS session found for schema: client_tenant_demo"
}
```

---

### 3.2 Send Bulk Notifications

**`POST /api/notifications/bulk/send`**

Queues multiple notifications for background delivery.

#### Request Headers

```
Content-Type: application/json
X-API-Key: your_api_key_here
```

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `schema_name` | string | ✅ | Tenant/schema identifier |
| `channel` | string | ✅ | `email`, `sms`, or `whatsapp` |
| `messages` | array | ✅ | Array of message objects (min 1, max 1000) |
| `messages[].to` | string | ✅ | Recipient for this message (max 255) |
| `messages[].message` | string | ✅ | Message body (max 4096) |
| `messages[].subject` | string | ✅ if email | Subject for this message (max 255) |
| `messages[].metadata` | object | — | Per-message metadata (max 10 keys) |
| `provider` | string | — | `twilio`, `whatsapp`, `sendgrid`, `mailgun`, `resend`, `beem`, `termii` |
| `type` | string | — | `wasender` or `official` |
| `priority` | string | — | `low`, `normal` (default), `high`, `urgent` |
| `scheduled_at` | datetime | — | ISO 8601 future timestamp for scheduled delivery |
| `rate_limit` | integer | — | Max messages per minute (min 1, max 1000) |
| `batch_size` | integer | — | Messages per processing batch (min 1, max 100) |
| `sender_name` | string | — | Sender name override (max 50) |
| `metadata` | object | — | Global metadata applied to all messages (max 10 keys) |
| `tags` | array | — | Labels applied to all messages (max 10 tags) |
| `webhook_url` | string (URL) | — | Delivery status callback URL (max 2048) |
| `attachment` | string | — | Base64-encoded file shared across all messages |
| `attachment_name` | string | ✅ if attachment | Original filename (max 255) |
| `attachment_type` | string | ✅ if attachment | MIME type (max 100) |

#### Example Request

```json
{
  "schema_name": "client_tenant_demo",
  "channel": "email",
  "provider": "sendgrid",
  "priority": "normal",
  "scheduled_at": "2026-04-01T09:00:00Z",
  "rate_limit": 100,
  "batch_size": 50,
  "metadata": { "campaign_id": "spring_sale_2026" },
  "tags": ["bulk", "campaign"],
  "webhook_url": "https://your-app.com/webhook",
  "messages": [
    {
      "to": "alice@example.com",
      "subject": "Spring Sale is Here!",
      "message": "Hi Alice, don't miss our spring deals!",
      "metadata": { "user_id": "u001" }
    },
    {
      "to": "bob@example.com",
      "subject": "Spring Sale is Here!",
      "message": "Hi Bob, don't miss our spring deals!",
      "metadata": { "user_id": "u002" }
    }
  ]
}
```

#### Response (202 Accepted)

```json
{
  "success": true,
  "message": "Bulk messages queued successfully",
  "total_count": 2,
  "status": "pending",
  "scheduled_at": "2026-04-01T09:00:00.000Z",
  "data": {
    "channel": "email",
    "total_count": 2,
    "priority": "normal",
    "scheduled_at": "2026-04-01T09:00:00.000Z",
    "message_ids": [124, 125]
  }
}
```

---

### 3.3 Get Notification Status

**`GET /api/notifications/{id}`**

Retrieves the current status and details of a specific notification.

#### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Notification message ID |

#### Response (200 OK)

```json
{
  "success": true,
  "data": {
    "id": 123,
    "channel": "email",
    "recipient": "customer@example.com",
    "subject": "Order Confirmation",
    "message": "Your order has been confirmed!",
    "status": "delivered",
    "priority": "high",
    "provider": "sendgrid",
    "external_id": "provider_message_id_abc123",
    "sent_at": "2026-03-26T10:30:15Z",
    "delivered_at": "2026-03-26T10:30:45Z",
    "metadata": {
      "order_id": "12345",
      "schema_name": "client_tenant_demo"
    },
    "tags": ["order", "confirmation"],
    "webhook_url": "https://your-app.com/webhook",
    "attachment": "attachments/attachment_abc123.pdf",
    "attachment_metadata": {
      "original_name": "invoice.pdf",
      "mime_type": "application/pdf",
      "size": 245760,
      "extension": "pdf"
    },
    "created_at": "2026-03-26T10:30:00Z",
    "updated_at": "2026-03-26T10:30:45Z"
  }
}
```

#### Response (404 — Not Found)

```json
{
  "success": false,
  "error": "Message not found",
  "message": "No query results for model [App\\Models\\Message] 999"
}
```

---

### 3.4 List Notifications

**`GET /api/notifications`**

Returns a paginated list of notifications scoped to the authenticated API key.

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `channel` | string | Filter by `email`, `sms`, or `whatsapp` |
| `status` | string | Filter by `pending`, `sent`, `delivered`, or `failed` |
| `from` | datetime | Start of date range (e.g. `2026-03-01 00:00:00`) |
| `to` | datetime | End of date range (e.g. `2026-03-31 23:59:59`) |
| `recipient` | string | Partial match on recipient address/number |
| `page` | integer | Page number (default: 1) |
| `per_page` | integer | Results per page (default: 20, max: 100) |

#### Example Request

```
GET /api/notifications?channel=email&status=delivered&from=2026-03-01&per_page=50
```

#### Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "channel": "email",
      "recipient": "customer@example.com",
      "subject": "Order Confirmation",
      "status": "delivered",
      "provider": "sendgrid",
      "sent_at": "2026-03-26T10:30:15Z",
      "created_at": "2026-03-26T10:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 50,
    "total": 156,
    "last_page": 4
  }
}
```

---

## 4. WaSender Session Management API

All WaSender endpoints require API key authentication.

---

### 4.1 Create Session

**`POST /api/wasender/sessions/create`**

Creates a new WhatsApp session by registering it with the WaSender API and storing it locally.

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `schema_name` | string | ✅ | Tenant identifier (max 255) |
| `name` | string | ✅ | Display name for the session (max 255) |
| `phone_number` | string | ✅ | WhatsApp phone number (max 20) |
| `account_protection` | boolean | — | Enable account protection (default: `true`) |
| `log_messages` | boolean | — | Log incoming messages (default: `true`) |
| `read_incoming_messages` | boolean | — | Mark messages as read (default: `false`) |
| `webhook_url` | string (URL) | — | URL for session event callbacks (max 500) |
| `webhook_enabled` | boolean | — | Enable webhook delivery (default: `false`) |
| `webhook_events` | array | — | Events to subscribe to: `messages.received`, `session.status`, `messages.update` |

#### Example Request

```json
{
  "schema_name": "client_tenant_demo",
  "name": "Demo Business WhatsApp",
  "phone_number": "+255712345678",
  "account_protection": true,
  "log_messages": true,
  "read_incoming_messages": false,
  "webhook_url": "https://webhook.example.com/wasender",
  "webhook_enabled": true,
  "webhook_events": ["messages.received", "session.status"]
}
```

#### Response (200 OK — Success)

```json
{
  "success": true,
  "message": "WhatsApp session created successfully",
  "data": {
    "id": 1,
    "schema_name": "client_tenant_demo",
    "wasender_session_id": "ws_abc123",
    "name": "Demo Business WhatsApp",
    "phone_number": "+255712345678",
    "status": "disconnected",
    "account_protection": true,
    "log_messages": true,
    "read_incoming_messages": false,
    "webhook_url": "https://webhook.example.com/wasender",
    "webhook_enabled": true,
    "webhook_events": ["messages.received", "session.status"],
    "api_key": "wa_key_xyz789",
    "created_at": "2026-03-26T10:00:00Z",
    "updated_at": "2026-03-26T10:00:00Z"
  },
  "api_response": {
    "success": true,
    "data": {
      "id": "ws_abc123",
      "name": "Demo Business WhatsApp",
      "status": "disconnected"
    }
  }
}
```

---

### 4.2 List Sessions

**`GET /api/wasender/sessions`**

Returns all stored WaSender sessions.

#### Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "schema_name": "client_tenant_demo",
      "wasender_session_id": "ws_abc123",
      "name": "Demo Business WhatsApp",
      "phone_number": "+255712345678",
      "status": "connected",
      "created_at": "2026-03-26T10:00:00Z",
      "updated_at": "2026-03-26T10:15:00Z"
    }
  ]
}
```

---

### 4.3 Get Single Session

**`GET /api/wasender/sessions/{id}`**

Returns full details of a specific session.

#### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Local session ID |

#### Response (200 OK)

```json
{
  "success": true,
  "data": {
    "id": 1,
    "schema_name": "client_tenant_demo",
    "wasender_session_id": "ws_abc123",
    "name": "Demo Business WhatsApp",
    "phone_number": "+255712345678",
    "status": "connected",
    "account_protection": true,
    "log_messages": true,
    "read_incoming_messages": false,
    "webhook_url": "https://webhook.example.com/wasender",
    "webhook_enabled": true,
    "webhook_events": ["messages.received", "session.status"],
    "created_at": "2026-03-26T10:00:00Z",
    "updated_at": "2026-03-26T10:15:00Z"
  }
}
```

---

### 4.4 Connect Session & Get QR Code

**`POST /api/wasender/sessions/{id}/connect`**

Initiates a WhatsApp connection for the session. Returns a QR code to scan.

#### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Local session ID |

#### Response (200 OK)

```json
{
  "success": true,
  "message": "Session connect request successful",
  "data": {
    "session": {
      "id": 1,
      "status": "connecting",
      "updated_at": "2026-03-26T10:20:00Z"
    },
    "qr_code": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...",
    "status": "connecting"
  },
  "api_response": {
    "success": true,
    "data": {
      "status": "connecting",
      "qrCode": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA..."
    }
  }
}
```

---

### 4.5 Check Session Status

**`GET /api/wasender/sessions/{id}/status`**

Retrieves the current connection status of a session from the WaSender API.

#### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Local session ID |

#### Response (200 OK)

```json
{
  "success": true,
  "message": "Session status retrieved successfully",
  "data": {
    "session": {
      "id": 1,
      "status": "connected",
      "updated_at": "2026-03-26T10:25:00Z"
    },
    "status": "connected"
  },
  "api_response": {
    "status": "connected",
    "device_info": {
      "battery": 85,
      "connected": true
    }
  }
}
```

---

### 4.6 Update Session

**`PUT /api/wasender/sessions/{id}`**

Updates an existing session's configuration. All fields are optional.

#### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Local session ID |

#### Request Body (all fields optional)

| Field | Type | Description |
|-------|------|-------------|
| `name` | string | New display name (max 255) |
| `phone_number` | string | New phone number (max 20) |
| `account_protection` | boolean | Enable/disable account protection |
| `log_messages` | boolean | Enable/disable message logging |
| `read_incoming_messages` | boolean | Enable/disable auto read receipts |
| `webhook_url` | string (URL) | New webhook URL (max 500) |
| `webhook_enabled` | boolean | Enable/disable webhook |
| `webhook_events` | array | `messages.received`, `session.status`, `messages.update` |

#### Example Request

```json
{
  "name": "Updated Business WhatsApp",
  "webhook_enabled": false
}
```

#### Response (200 OK)

```json
{
  "success": true,
  "message": "WhatsApp session updated successfully",
  "data": {
    "id": 1,
    "schema_name": "client_tenant_demo",
    "name": "Updated Business WhatsApp",
    "phone_number": "+255712345678",
    "webhook_enabled": false,
    "updated_at": "2026-03-26T10:30:00Z"
  },
  "api_response": {
    "success": true,
    "data": {
      "name": "Updated Business WhatsApp",
      "webhook_enabled": false
    }
  }
}
```

---

### 4.7 Get QR Code

**`GET /api/wasender/sessions/{id}/qrcode`**

Retrieves a fresh QR code for an existing session.

#### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Local session ID |

#### Response (200 OK)

```json
{
  "success": true,
  "message": "QR code retrieved successfully",
  "data": {
    "session": {
      "id": 1,
      "schema_name": "client_tenant_demo",
      "status": "connecting"
    },
    "qr_code": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA..."
  },
  "api_response": {
    "data": {
      "qrCode": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA..."
    }
  }
}
```

---

### 4.8 Delete Session

**`DELETE /api/wasender/sessions/{id}`**

Deletes the session locally and removes it from the WaSender API.

#### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Local session ID |

#### Response (200 OK)

```json
{
  "success": true,
  "message": "WhatsApp session deleted successfully",
  "data": {
    "deleted_local_id": 1,
    "deleted_wasender_id": "ws_abc123"
  },
  "api_response": {}
}
```

---

## 5. Webhook Receiver Endpoints

These endpoints receive inbound delivery status events from messaging providers. **No authentication required** — verification is handled internally per provider.

| Method | Endpoint | Description |
|--------|----------|-------------|
| `ANY` | `/api/webhook/whatsapp` | WhatsApp (WaSender) inbound events & verification |
| `ANY` | `/api/webhook/twilio` | Twilio delivery status callbacks |
| `ANY` | `/api/webhook/sendgrid` | SendGrid email event webhooks |
| `ANY` | `/api/webhook/mailgun` | Mailgun email event webhooks |
| `ANY` | `/api/webhook/test` | Test endpoint — echoes back the payload |
| `ANY` | `/api/webhook/{provider}` | Generic fallback for any other provider |

### Webhook Response Format

All webhook endpoints respond with:

```json
{
  "status": "received",
  "processed": true
}
```

### Test Webhook Response

```json
{
  "status": "received",
  "message": "Test webhook received successfully",
  "timestamp": "2026-03-26T10:00:00.000Z",
  "payload": { }
}
```

### WhatsApp Webhook Verification (GET)

For WhatsApp webhook setup verification:

```
GET /api/webhook/whatsapp?hub_mode=subscribe&hub_verify_token=<token>&hub_challenge=<challenge>
```

Returns the `hub_challenge` value if the token matches.

---

## 6. Admin Authentication API

Admin session management. These endpoints use session-based authentication (not API key).

### 6.1 Admin Login

**`POST /api/admin/auth/login`**

```json
{
  "email": "admin@example.com",
  "password": "secret"
}
```

### 6.2 Admin Logout

**`POST /api/admin/auth/logout`**

### 6.3 Refresh Token

**`POST /api/admin/auth/refresh`**

### 6.4 Get Current Admin

**`GET /api/admin/auth/me`**

---

## 7. Error Reference

### HTTP Status Codes

| Code | Meaning |
|------|---------|
| `200` | OK |
| `201` | Created (single notification sent) |
| `202` | Accepted (bulk notifications queued) |
| `400` | Bad Request (e.g. missing WaSender/SMS session) |
| `401` | Unauthorized (missing or invalid API key) |
| `403` | Forbidden |
| `404` | Not Found |
| `422` | Unprocessable Entity (validation failed) |
| `500` | Internal Server Error |
| `503` | Service Unavailable (health check failed) |

### Standard Error Response

```json
{
  "success": false,
  "error": "Short error title",
  "message": "Detailed description of what went wrong"
}
```

### Validation Error Response (422)

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "channel": ["Message channel must be one of: sms, email, whatsapp"],
    "to": ["Recipient is required"]
  }
}
```

### Common Error Scenarios

| Scenario | HTTP Code | `error` value |
|----------|-----------|---------------|
| Missing API key | 401 | `"Unauthorized"` |
| Invalid API key format (< 32 chars) | 401 | `"Unauthorized"` |
| Notification not found | 404 | `"Message not found"` |
| WaSender session not connected | 400 | `"WaSender session not found or API key unavailable"` |
| SMS session not found | 400 | `"SMS session not found"` |
| Validation failure | 422 | `"Validation failed"` |
| Attachment processing failure | 500 | `"Failed to process attachment"` |
| Bulk queue failure | 500 | `"Failed to queue bulk messages"` |

---

## 8. Field Validation Reference

### Allowed Values

**`channel`:** `email` | `sms` | `whatsapp`

**`provider`:** `twilio` | `whatsapp` | `sendgrid` | `mailgun` | `resend` | `beem` | `termii`  
> Note: WaSender is **not** a `provider` value. To use WaSender for WhatsApp, set `"type": "wasender"`.

**`type`:** `wasender` | `official`

**`priority`:** `low` | `normal` | `high` | `urgent`

**`webhook_events` (WaSender):** `messages.received` | `session.status` | `messages.update`

### Limits

| Field | Limit |
|-------|-------|
| `message` | 4096 characters |
| `subject` | 255 characters |
| `metadata` keys | max 10 |
| `metadata` values | 500 characters each |
| `template_data` values | 1000 characters each |
| `tags` | max 10, each 50 characters |
| `sender_name` | 50 characters |
| `messages` (bulk) | min 1, max 1000 |
| `batch_size` | 1–100 |
| `rate_limit` | 1–1000 messages/min |

### Supported Attachment MIME Types

| Category | MIME Types |
|----------|-----------|
| Images | `image/jpeg`, `image/jpg`, `image/png`, `image/gif`, `image/webp` |
| Documents | `application/pdf`, `application/msword`, `application/vnd.openxmlformats-officedocument.wordprocessingml.document` |
| Spreadsheets | `application/vnd.ms-excel`, `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` |
| Text | `text/plain`, `text/csv` |
| Video | `video/mp4`, `video/webm`, `video/quicktime`, `video/x-msvideo` |
| Audio | `audio/mpeg`, `audio/wav`, `audio/ogg` |

Attachments are stored in `storage/app/public/attachments/` with auto-generated unique filenames.

---

## 9. Implementation Notes

### Multi-Tenancy via `schema_name`

Every notification and WaSender session request must include `schema_name`. This is used to:
- Look up the correct WaSender API key for WhatsApp delivery
- Look up the correct SMS sender name for SMS delivery
- Scope notification records per tenant

### WhatsApp Delivery Flow

1. Create a WaSender session: `POST /api/wasender/sessions/create`
2. Connect the session: `POST /api/wasender/sessions/{id}/connect`
3. Scan the returned QR code in WhatsApp on the device
4. Verify connection: `GET /api/wasender/sessions/{id}/status`
5. Send messages: `POST /api/notifications/send` with `"channel": "whatsapp"` and `"type": "wasender"`

> The system automatically resolves the WaSender API key from the session record matching the `schema_name`. The session must have `status: connected`.

### SMS Delivery Flow

1. Ensure an SMS session record exists in the database for the `schema_name`
2. The session's `sender_name` field is used as the SMS sender ID (falls back to default `SHULESOFT` if `null`)
3. Send messages: `POST /api/notifications/send` with `"channel": "sms"`

### Bulk Message Processing

- Messages are dispatched to a background queue (Laravel queue worker required)
- Rate limiting is applied as a delay between jobs: `delay = (index / rate_limit) * 60` seconds
- All messages in a bulk request share one attachment (if provided)
- `scheduled_at` sets the earliest dispatch time; `rate_limit` staggers deliveries beyond that

### Attachment Processing

1. Strip `data:mime/type;base64,` prefix if present
2. Decode base64 to binary
3. Store file in `storage/app/public/attachments/{unique_id}.{ext}`
4. File extension is derived from the `attachment_type` MIME type
5. Metadata (name, type, size, extension) is stored with the message record

### Queue Worker

Bulk notifications require a queue worker to be running:

```bash
php artisan queue:work
```

---

*This document reflects the actual application implementation as of March 26, 2026.*
