# Notification Service — Complete API Reference

**Version:** 1.1  
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
   - [Resend Notifications](#33-resend-notifications)
   - [Get Notification Status](#34-get-notification-status)
   - [List Notifications](#35-list-notifications)
   - [Bulk Delete Notifications](#36-bulk-delete-notifications)
   - [Get SMS Balance](#37-get-sms-balance)
4. [SMS Session Management API](#4-sms-session-management-api)
   - [List SMS Sessions](#41-list-sms-sessions)
   - [Create SMS Session](#42-create-sms-session)
   - [Get SMS Session](#43-get-sms-session)
   - [Update SMS Session](#44-update-sms-session)
   - [Delete SMS Session](#45-delete-sms-session)
5. [WaSender Session Management API](#5-wasender-session-management-api)
   - [Create Session](#51-create-session)
   - [List Sessions](#52-list-sessions)
   - [Get Single Session](#53-get-single-session)
   - [Connect Session & Get QR Code](#54-connect-session--get-qr-code)
   - [Check Session Status](#55-check-session-status)
   - [Update Session](#56-update-session)
   - [Get QR Code](#57-get-qr-code)
   - [Delete Session](#58-delete-session)
6. [Rate Limiting & Throttling](#6-rate-limiting--throttling)
7. [Webhook Receiver Endpoints](#7-webhook-receiver-endpoints)
8. [Admin Authentication API](#8-admin-authentication-api)
9. [Error Reference](#9-error-reference)
10. [Field Validation Reference](#10-field-validation-reference)
11. [Implementation Notes](#11-implementation-notes)

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

> **Rate limiting applies** to `send`, `bulk/send`, and `resend`. See [Section 6](#6-rate-limiting--throttling) for details.

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

### 3.3 Resend Notifications

**`POST /api/notifications/resend`**

Re-queues one or more previously sent messages. For SMS, automatically checks the schema's credit balance and skips messages if credit is exhausted.

> **Rate limited:** 2 requests per second.

#### Request Headers

```
Content-Type: application/json
X-API-Key: your_api_key_here
```

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `schema_name` | string | ✅ | Tenant/schema identifier |
| `message_ids` | array | ✅ | Array of message IDs to resend (min 1) |
| `message_ids[]` | integer | ✅ | Must be a valid existing message ID |
| `scheduled_at` | datetime | — | ISO 8601 future timestamp for delivery |
| `rate_limit` | integer | — | Max messages per minute for staggered dispatch |

#### Example Request

```json
{
  "schema_name": "client_tenant_demo",
  "message_ids": [101, 102, 103],
  "rate_limit": 30
}
```

#### Response (200 OK — All resent)

```json
{
  "success": true,
  "message": "All messages have been resent successfully.",
  "total_messages": 3,
  "resent_count": 3,
  "skipped_count": 0,
  "results": [
    { "message_id": 101, "status": "queued", "channel": "sms", "recipient": "+255712345678" },
    { "message_id": 102, "status": "queued", "channel": "sms", "recipient": "+255712345679" },
    { "message_id": 103, "status": "queued", "channel": "sms", "recipient": "+255712345680" }
  ]
}
```

#### Response (200 OK — Some skipped due to no credit)

```json
{
  "success": true,
  "message": "Some messages were skipped due to insufficient credit.",
  "total_messages": 3,
  "resent_count": 2,
  "skipped_count": 1,
  "results": [
    { "message_id": 101, "status": "queued", "channel": "sms", "recipient": "+255712345678" },
    { "message_id": 102, "status": "queued", "channel": "sms", "recipient": "+255712345679" },
    { "message_id": 103, "status": "skipped_no_credit", "channel": "sms", "recipient": "+255712345680" }
  ]
}
```

#### Response (404 — No messages found)

```json
{
  "success": false,
  "error": "No messages found",
  "message": "No messages found with provided IDs"
}
```

---

### 3.4 Get Notification Status

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

### 3.5 List Notifications

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

### 3.6 Bulk Delete Notifications

**`DELETE /api/notifications/bulk/delete`**

Permanently deletes multiple notification records and their associated attachment files from storage.

#### Request Headers

```
Content-Type: application/json
X-API-Key: your_api_key_here
```

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `message_ids` | array | ✅ | Array of message IDs to delete (min 1) |
| `message_ids[]` | integer | ✅ | Must be a valid existing message ID |

#### Example Request

```json
{
  "message_ids": [101, 102, 103]
}
```

#### Response (200 OK)

```json
{
  "success": true,
  "message": "Messages deleted successfully",
  "deleted_count": 3,
  "message_ids": [101, 102, 103]
}
```

---

### 3.7 Get SMS Balance

**`POST /api/notifications/sms/balance`**

Returns the SMS credit balance for a given schema, calculated from total purchased SMS minus total sent.

#### Request Headers

```
Content-Type: application/json
X-API-Key: your_api_key_here
```

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `schema_name` | string | ✅ | Tenant/schema identifier |

#### Example Request

```json
{
  "schema_name": "client_tenant_demo"
}
```

#### Response (200 OK)

```json
{
  "total_sms": 5000,
  "total_sms_sent": 3240,
  "balance": 1760
}
```

---

## 4. SMS Session Management API

All SMS session endpoints require API key authentication. SMS sessions configure the sender name and SMS provider used per schema/tenant.

---

### 4.1 List SMS Sessions

**`GET /api/sms-sessions`**

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `schema_name` | string | Filter by schema name |
| `provider` | string | Filter by provider: `beem`, `termii`, `twilio` |
| `status` | string | Filter by `active` or `inactive` |
| `search` | string | Search across `schema_name`, `sender_name`, `provider`, `status` |
| `sort_by` | string | Column to sort by (default: `created_at`) |
| `sort_direction` | string | `asc` or `desc` (default: `desc`) |
| `per_page` | integer | Results per page (max 100, default: 20) |

#### Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "schema_name": "client_tenant_demo",
      "sender_name": "MYAPP",
      "provider": "beem",
      "status": "active",
      "created_at": "2026-03-26T10:00:00Z",
      "updated_at": "2026-03-26T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 5,
    "last_page": 1
  }
}
```

---

### 4.2 Create SMS Session

**`POST /api/sms-sessions`**

Creates a new SMS session for a schema. Each `schema_name` is enforced to have exactly one `sender_name` across all its sessions.

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `schema_name` | string | ✅ | Tenant identifier (max 255) |
| `sender_name` | string | — | SMS sender ID shown to recipients (max 255) |
| `provider` | string | — | `beem` (default), `termii`, or `twilio` |

#### Example Request

```json
{
  "schema_name": "client_tenant_demo",
  "sender_name": "MYAPP",
  "provider": "beem"
}
```

#### Response (201 Created)

```json
{
  "success": true,
  "message": "SMS session created successfully",
  "data": {
    "id": 1,
    "schema_name": "client_tenant_demo",
    "sender_name": "MYAPP",
    "provider": "beem",
    "status": "active",
    "created_at": "2026-03-26T10:00:00Z",
    "updated_at": "2026-03-26T10:00:00Z"
  }
}
```

#### Response (422 — Sender name conflict)

```json
{
  "success": false,
  "error": "Validation failed",
  "errors": {
    "sender_name": ["Each schema_name must be associated with exactly one sender_name."]
  }
}
```

---

### 4.3 Get SMS Session

**`GET /api/sms-sessions/{id}`**

#### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | SMS session ID |

#### Response (200 OK)

```json
{
  "success": true,
  "data": {
    "id": 1,
    "schema_name": "client_tenant_demo",
    "sender_name": "MYAPP",
    "provider": "beem",
    "status": "active",
    "created_at": "2026-03-26T10:00:00Z",
    "updated_at": "2026-03-26T10:00:00Z"
  }
}
```

---

### 4.4 Update SMS Session

**`PUT /api/sms-sessions/{id}`** or **`PATCH /api/sms-sessions/{id}`**

All fields are optional.

#### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | SMS session ID |

#### Request Body (all fields optional)

| Field | Type | Description |
|-------|------|-------------|
| `schema_name` | string | Tenant identifier (max 255) |
| `sender_name` | string | SMS sender ID (max 255) |
| `provider` | string | `beem`, `termii`, or `twilio` |
| `status` | string | `active` or `inactive` |

#### Example Request

```json
{
  "status": "inactive"
}
```

#### Response (200 OK)

```json
{
  "success": true,
  "message": "SMS session updated successfully",
  "data": {
    "id": 1,
    "schema_name": "client_tenant_demo",
    "sender_name": "MYAPP",
    "provider": "beem",
    "status": "inactive",
    "created_at": "2026-03-26T10:00:00Z",
    "updated_at": "2026-03-26T11:00:00Z"
  }
}
```

---

### 4.5 Delete SMS Session

**`DELETE /api/sms-sessions/{id}`**

#### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | SMS session ID |

#### Response (200 OK)

```json
{
  "success": true,
  "message": "SMS session deleted successfully"
}
```

---

## 5. WaSender Session Management API

All WaSender endpoints require API key authentication.

---

### 5.1 Create Session

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

### 5.2 List Sessions

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

### 5.3 Get Single Session

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

### 5.4 Connect Session & Get QR Code

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

### 5.5 Check Session Status

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

### 5.6 Update Session

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

### 5.7 Get QR Code

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

### 5.8 Delete Session

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

## 6. Rate Limiting & Throttling

Redis-based rate limiting is applied to notification send endpoints. Limits are configurable via environment variables.

### Throttled Endpoints

| Endpoint | Default Limit | Window |
|----------|--------------|--------|
| `POST /api/notifications/send` | 2 requests | per 1 second |
| `POST /api/notifications/bulk/send` | 1 request | per 2 seconds |
| `POST /api/notifications/resend` | 2 requests | per 1 second |

Throttle limits are tracked by **API key** (when authenticated) or by **IP address** (fallback). Responses on all non-throttled requests include rate limit headers:

```
X-RateLimit-Limit: 2
X-RateLimit-Remaining: 1
X-RateLimit-Reset: 1743000001
```

### Rate Limit Exceeded Response (429)

```json
{
  "success": false,
  "error": "Rate limit exceeded",
  "message": "Too many requests. Limit: 2 requests per 1 second(s)",
  "retry_after": 1,
  "limit": 2,
  "remaining": 0,
  "reset_at": 1743000001
}
```

Headers returned with 429:

```
X-RateLimit-Limit: 2
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1743000001
Retry-After: 1
```

### Check Throttling Status

**`GET /api/throttling/status`** — No authentication required.

Returns current rate limit counters for the calling API key or IP.

#### Response (200 OK)

```json
{
  "success": true,
  "data": {
    "single_notifications": {
      "current_attempts": 1,
      "max_attempts": 2,
      "remaining": 1,
      "reset_in_seconds": 0,
      "reset_at": null
    },
    "bulk_notifications": {
      "current_attempts": 0,
      "max_attempts": 1,
      "remaining": 1,
      "reset_in_seconds": 0,
      "reset_at": null
    },
    "identifier": "api_key",
    "timestamp": "2026-03-26T10:00:00.000Z"
  }
}
```

### Clear Throttling (Admin)

**`POST /api/throttling/clear`** — Requires admin authentication.

Clears rate limit counters for the calling API key or IP.

#### Response (200 OK)

```json
{
  "success": true,
  "message": "Throttling cleared successfully",
  "keys_cleared": 2
}
```

### Per-Provider Default Limits (configurable via `.env`)

| Provider | Default Max req/s |
|----------|------------------|
| `resend` | 2 |
| `sendgrid` | 10 |
| `mailgun` | 100 |
| `beem` | 10 |
| `termii` | 10 |
| `twilio` | configurable |

---

## 7. Webhook Receiver Endpoints

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

## 8. Admin Authentication API

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

## 9. Error Reference

### HTTP Status Codes

| Code | Meaning |
|------|---------|
| `200` | OK |
| `201` | Created (single notification sent, SMS session created) |
| `202` | Accepted (bulk notifications queued) |
| `400` | Bad Request (e.g. missing WaSender/SMS session) |
| `401` | Unauthorized (missing or invalid API key) |
| `403` | Forbidden |
| `404` | Not Found |
| `422` | Unprocessable Entity (validation failed) |
| `429` | Too Many Requests (rate limit exceeded) |
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
| Resend failure | 500 | `"Failed to resend messages"` |
| Rate limit exceeded | 429 | `"Rate limit exceeded"` |
| SMS balance fetch failure | 500 | `"Failed to retrieve SMS balance"` |
| SMS session create failure | 500 | `"Failed to create SMS session"` |
| SMS session not found | 404 | `"SMS session not found"` |

---

## 10. Field Validation Reference

### Allowed Values

**`channel`:** `email` | `sms` | `whatsapp`

**`provider`:** `twilio` | `whatsapp` | `sendgrid` | `mailgun` | `resend` | `beem` | `termii`  
> Note: WaSender is **not** a `provider` value. To use WaSender for WhatsApp, set `"type": "wasender"`.

**SMS session `provider`:** `beem` | `termii` | `twilio`

**SMS session `status`:** `active` | `inactive`

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
| `message_ids` (resend/delete) | min 1, no documented upper limit |

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

## 11. Implementation Notes

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

### Notification Response Fields

The `data` object returned by notification endpoints includes the following fields:

| Field | Present When | Description |
|-------|-------------|-------------|
| `id` | always | Message ID |
| `channel` | always | `email`, `sms`, or `whatsapp` |
| `recipient` | always | Recipient address |
| `subject` | email only | Email subject |
| `message` | always | Message body |
| `schema_name` | always | Tenant identifier |
| `status` | always | `pending`, `sent`, `delivered`, `failed`, `no_credit` |
| `provider` | always | Provider used |
| `priority` | always | Message priority |
| `scheduled_at` | when scheduled | ISO 8601 timestamp |
| `sent_at` | when sent | ISO 8601 timestamp |
| `delivered_at` | when delivered | ISO 8601 timestamp |
| `failed_at` | when failed | ISO 8601 timestamp |
| `external_id` | always | Provider's message ID |
| `error_message` | when `status=failed` | Error description |
| `retry_count` | always | Number of send attempts |
| `metadata` | when present | Custom key-value data |
| `tags` | when present | Message labels |
| `duration_ms` | always | Processing time in ms |
| `is_scheduled` | always | Boolean computed flag |
| `is_delivered` | always | Boolean computed flag |
| `is_failed` | always | Boolean computed flag |
| `delivery_status` | always | Human-readable status |
| `formatted_duration` | always | e.g. `"245ms"` |
| `created_at` | always | `Y-m-d H:i:s` |
| `updated_at` | always | `Y-m-d H:i:s` |

### Queue Worker

Bulk notifications and resend operations require a queue worker to be running:

```bash
php artisan queue:work
```

### SMS Session Constraints

- A `schema_name` must have **exactly one** `sender_name` across all its SMS sessions — attempting to create a second session with a different `sender_name` for the same schema returns a 422 validation error.
- If a session's `sender_name` is `null`, the system falls back to the default sender ID (`SHULESOFT`).
- The SMS balance check (used by `resend`) counts message units using multi-part SMS counting rules.

---

*This document reflects the actual application implementation as of March 26, 2026. Updated after merge of 19 remote commits.*
