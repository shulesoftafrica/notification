# Notification Service API Reference - Phase 3

## Base URL
```
https://api.yourcompany.com/v1
```

## Authentication

All API requests require HMAC-SHA256 authentication:

```
X-Project-ID: your-project-id
X-Signature: hmac-sha256-signature
X-Timestamp: unix-timestamp
```

## Rate Limiting

API requests are rate limited based on several factors:
- **Global**: 10,000/min, 500,000/hour, 10,000,000/day
- **Project**: Configurable per project
- **Tenant**: Configurable per tenant
- **Endpoint**: Varies by operation type
- **Bulk Operations**: 10/min, 100/hour, 1,000/day

Rate limit information is returned in response headers:
```
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1640995200
```

---

## Health & Status

### Get System Health
```http
GET /health
```

**Response:**
```json
{
  "status": "healthy",
  "timestamp": "2024-01-15T10:30:00Z",
  "version": "1.0.0",
  "service": "notification-service"
}
```

---

## Core Messaging

### Send Message
```http
POST /messages
```

**Request Body:**
```json
{
  "channel": "email",
  "recipient": {
    "email": "user@example.com",
    "name": "John Doe"
  },
  "content": {
    "subject": "Welcome!",
    "body": "Welcome to our platform!"
  },
  "options": {
    "provider": "sendgrid",
    "priority": "normal",
    "tags": ["welcome", "onboarding"]
  }
}
```

**Response:**
```json
{
  "message_id": "msg_1234567890",
  "status": "queued",
  "channel": "email",
  "recipient": "user@example.com",
  "estimated_delivery": "2024-01-15T10:35:00Z"
}
```

### Get Message Status
```http
GET /messages/{messageId}
```

**Response:**
```json
{
  "message_id": "msg_1234567890",
  "status": "delivered",
  "channel": "email",
  "recipient": "user@example.com",
  "provider": "sendgrid",
  "sent_at": "2024-01-15T10:32:00Z",
  "delivered_at": "2024-01-15T10:33:00Z",
  "events": [
    {
      "type": "queued",
      "timestamp": "2024-01-15T10:30:00Z"
    },
    {
      "type": "sent",
      "timestamp": "2024-01-15T10:32:00Z"
    },
    {
      "type": "delivered",
      "timestamp": "2024-01-15T10:33:00Z"
    }
  ]
}
```

---

## Template Management

### List Templates
```http
GET /templates?page=1&per_page=20&channel=email
```

**Response:**
```json
{
  "data": [
    {
      "id": "tpl_1234567890",
      "name": "welcome_email",
      "channel": "email",
      "subject": "Welcome {{user.first_name}}!",
      "content": "Hello {{user.first_name}}, welcome to our platform!",
      "variables": ["user.first_name", "user.email"],
      "metadata": {
        "category": "onboarding",
        "version": "1.0"
      },
      "created_at": "2024-01-15T10:00:00Z",
      "updated_at": "2024-01-15T10:00:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 45,
    "total_pages": 3
  }
}
```

### Create Template
```http
POST /templates
```

**Request Body:**
```json
{
  "name": "welcome_email",
  "channel": "email",
  "subject": "Welcome {{user.first_name}}!",
  "content": "Hello {{user.first_name}}, welcome to our platform! Your email is {{user.email}}.",
  "variables": ["user.first_name", "user.email"],
  "metadata": {
    "category": "onboarding",
    "version": "1.0",
    "description": "Welcome email for new users"
  }
}
```

**Response:**
```json
{
  "id": "tpl_1234567890",
  "name": "welcome_email",
  "channel": "email",
  "subject": "Welcome {{user.first_name}}!",
  "content": "Hello {{user.first_name}}, welcome to our platform! Your email is {{user.email}}.",
  "variables": ["user.first_name", "user.email"],
  "metadata": {
    "category": "onboarding",
    "version": "1.0",
    "description": "Welcome email for new users"
  },
  "created_at": "2024-01-15T10:00:00Z",
  "updated_at": "2024-01-15T10:00:00Z"
}
```

### Get Template
```http
GET /templates/{templateId}
```

### Update Template
```http
PUT /templates/{templateId}
```

### Delete Template
```http
DELETE /templates/{templateId}
```

### Preview Template
```http
POST /templates/{templateId}/preview
```

**Request Body:**
```json
{
  "data": {
    "user": {
      "first_name": "John",
      "email": "john@example.com"
    }
  }
}
```

**Response:**
```json
{
  "preview": {
    "subject": "Welcome John!",
    "content": "Hello John, welcome to our platform! Your email is john@example.com."
  },
  "variables_used": ["user.first_name", "user.email"],
  "missing_variables": []
}
```

### Validate Template
```http
POST /templates/{templateId}/validate
```

**Response:**
```json
{
  "valid": true,
  "syntax_errors": [],
  "variables_found": ["user.first_name", "user.email"],
  "unused_variables": [],
  "missing_variables": []
}
```

---

## Provider Configuration

### List Provider Configurations
```http
GET /config/providers?channel=email
```

**Response:**
```json
{
  "data": [
    {
      "id": "cfg_1234567890",
      "provider": "sendgrid",
      "channel": "email",
      "priority": 1,
      "status": "active",
      "config": {
        "from_email": "noreply@example.com",
        "from_name": "Your Company"
      },
      "quota_limits": {
        "daily": 10000,
        "monthly": 300000
      },
      "quota_usage": {
        "daily": 1250,
        "monthly": 45000
      },
      "created_at": "2024-01-15T10:00:00Z"
    }
  ]
}
```

### Create Provider Configuration
```http
POST /config/providers
```

**Request Body:**
```json
{
  "provider": "sendgrid",
  "channel": "email",
  "priority": 1,
  "config": {
    "api_key": "your-sendgrid-api-key",
    "from_email": "noreply@example.com",
    "from_name": "Your Company"
  },
  "quota_limits": {
    "daily": 10000,
    "monthly": 300000
  }
}
```

### Update Provider Configuration
```http
PUT /config/providers/{configId}
```

### Delete Provider Configuration
```http
DELETE /config/providers/{configId}
```

### Test Provider Configuration
```http
POST /config/providers/{configId}/test
```

**Request Body:**
```json
{
  "test_type": "connectivity",
  "recipient": "test@example.com"
}
```

**Response:**
```json
{
  "test_successful": true,
  "response_time": 250,
  "provider_response": {
    "status": "success",
    "message_id": "test_msg_123"
  },
  "timestamp": "2024-01-15T10:30:00Z"
}
```

### Get Quota Information
```http
GET /config/quotas
```

**Response:**
```json
{
  "quotas": [
    {
      "provider": "sendgrid",
      "channel": "email",
      "limits": {
        "daily": 10000,
        "monthly": 300000
      },
      "usage": {
        "daily": 1250,
        "monthly": 45000
      },
      "percentage_used": {
        "daily": 12.5,
        "monthly": 15.0
      }
    }
  ],
  "total_usage": {
    "messages_today": 2500,
    "messages_this_month": 75000
  }
}
```

---

## Bulk Operations

### Send Bulk Messages
```http
POST /bulk/messages
```

**Request Body:**
```json
{
  "template_id": "tpl_1234567890",
  "channel": "email",
  "recipients": [
    {
      "email": "user1@example.com",
      "data": {
        "user": {
          "first_name": "John",
          "email": "user1@example.com"
        }
      }
    },
    {
      "email": "user2@example.com",
      "data": {
        "user": {
          "first_name": "Jane",
          "email": "user2@example.com"
        }
      }
    }
  ],
  "options": {
    "batch_size": 100,
    "delay_between_batches": 1000,
    "priority": "normal"
  }
}
```

**Response:**
```json
{
  "batch_id": "batch_1234567890",
  "status": "processing",
  "total_recipients": 2,
  "estimated_completion": "2024-01-15T10:35:00Z",
  "created_at": "2024-01-15T10:30:00Z"
}
```

### Get Bulk Job Status
```http
GET /bulk/messages/{batchId}
```

**Response:**
```json
{
  "batch_id": "batch_1234567890",
  "status": "completed",
  "total_recipients": 2,
  "processed": 2,
  "successful": 2,
  "failed": 0,
  "progress_percentage": 100,
  "started_at": "2024-01-15T10:30:00Z",
  "completed_at": "2024-01-15T10:32:00Z",
  "results": [
    {
      "recipient": "user1@example.com",
      "status": "sent",
      "message_id": "msg_1234567891"
    },
    {
      "recipient": "user2@example.com",
      "status": "sent",
      "message_id": "msg_1234567892"
    }
  ],
  "errors": []
}
```

### Cancel Bulk Job
```http
POST /bulk/messages/{batchId}/cancel
```

**Response:**
```json
{
  "batch_id": "batch_1234567890",
  "status": "cancelled",
  "processed": 150,
  "cancelled": 850,
  "cancelled_at": "2024-01-15T10:35:00Z"
}
```

### List Bulk Jobs
```http
GET /bulk/jobs?status=completed&page=1&per_page=20
```

**Response:**
```json
{
  "data": [
    {
      "batch_id": "batch_1234567890",
      "status": "completed",
      "total_recipients": 1000,
      "successful": 995,
      "failed": 5,
      "created_at": "2024-01-15T09:00:00Z",
      "completed_at": "2024-01-15T09:15:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 25,
    "total_pages": 2
  }
}
```

---

## Analytics

### Get Delivery Rates
```http
GET /analytics/delivery-rates?period=7d&channel=email&provider=sendgrid
```

**Response:**
```json
{
  "period": "7d",
  "channel": "email",
  "provider": "sendgrid",
  "metrics": {
    "total_sent": 10000,
    "delivered": 9850,
    "failed": 150,
    "delivery_rate": 98.5,
    "failure_rate": 1.5
  },
  "daily_breakdown": [
    {
      "date": "2024-01-09",
      "sent": 1500,
      "delivered": 1485,
      "failed": 15,
      "delivery_rate": 99.0
    }
  ]
}
```

### Get Daily Volume
```http
GET /analytics/daily-volume?period=30d&channel=email
```

**Response:**
```json
{
  "period": "30d",
  "channel": "email",
  "total_volume": 150000,
  "average_daily": 5000,
  "daily_data": [
    {
      "date": "2024-01-15",
      "volume": 5250,
      "channels": {
        "email": 4000,
        "sms": 1000,
        "whatsapp": 250
      }
    }
  ]
}
```

### Get Provider Performance
```http
GET /analytics/provider-performance?period=7d&channel=email
```

**Response:**
```json
{
  "period": "7d",
  "channel": "email",
  "providers": [
    {
      "provider": "sendgrid",
      "messages_sent": 6000,
      "delivery_rate": 98.5,
      "average_delivery_time": 2.3,
      "cost_per_message": 0.001,
      "total_cost": 6.00
    },
    {
      "provider": "mailgun",
      "messages_sent": 4000,
      "delivery_rate": 97.8,
      "average_delivery_time": 3.1,
      "cost_per_message": 0.0008,
      "total_cost": 3.20
    }
  ]
}
```

### Get Cost Analytics
```http
GET /analytics/cost-analytics?period=30d&breakdown=provider
```

**Response:**
```json
{
  "period": "30d",
  "total_cost": 450.75,
  "cost_breakdown": {
    "by_provider": [
      {
        "provider": "sendgrid",
        "cost": 250.50,
        "percentage": 55.6
      },
      {
        "provider": "twilio",
        "cost": 200.25,
        "percentage": 44.4
      }
    ],
    "by_channel": [
      {
        "channel": "email",
        "cost": 250.50,
        "percentage": 55.6
      },
      {
        "channel": "sms",
        "cost": 200.25,
        "percentage": 44.4
      }
    ]
  },
  "daily_costs": [
    {
      "date": "2024-01-15",
      "cost": 15.25
    }
  ]
}
```

### Get Engagement Metrics
```http
GET /analytics/engagement-metrics?period=7d&channel=email
```

**Response:**
```json
{
  "period": "7d",
  "channel": "email",
  "metrics": {
    "total_delivered": 9850,
    "opened": 4925,
    "clicked": 985,
    "open_rate": 50.0,
    "click_rate": 10.0,
    "click_to_open_rate": 20.0
  },
  "daily_engagement": [
    {
      "date": "2024-01-15",
      "delivered": 1485,
      "opened": 742,
      "clicked": 148,
      "open_rate": 50.0,
      "click_rate": 10.0
    }
  ]
}
```

### Get Dashboard Overview
```http
GET /analytics/dashboard
```

**Response:**
```json
{
  "summary": {
    "messages_today": 2500,
    "messages_this_month": 75000,
    "delivery_rate_today": 98.2,
    "cost_today": 15.25,
    "cost_this_month": 450.75
  },
  "recent_activity": [
    {
      "timestamp": "2024-01-15T10:30:00Z",
      "type": "bulk_job_completed",
      "description": "Bulk job batch_1234567890 completed successfully"
    }
  ],
  "provider_status": [
    {
      "provider": "sendgrid",
      "status": "healthy",
      "last_check": "2024-01-15T10:29:00Z"
    }
  ],
  "queue_status": {
    "pending_jobs": 42,
    "processing_rate": "150/min"
  }
}
```

---

## Administrative

### Get System Health
```http
GET /admin/system-health
```

**Response:**
```json
{
  "database": "connected",
  "redis": "connected",
  "queue": "active",
  "providers": {
    "sendgrid": "active",
    "mailgun": "active",
    "twilio": "active"
  },
  "timestamp": "2024-01-15T10:30:00Z"
}
```

### Get Queue Status
```http
GET /admin/queue-status
```

**Response:**
```json
{
  "pending_jobs": 42,
  "failed_jobs": 3,
  "processed_today": 15742,
  "processing_rate": "150/min",
  "timestamp": "2024-01-15T10:30:00Z"
}
```

---

## Error Responses

### Authentication Error
```json
{
  "error": {
    "code": "AUTHENTICATION_FAILED",
    "message": "Invalid HMAC signature",
    "trace_id": "req-1234567890"
  }
}
```

### Rate Limit Error
```json
{
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Rate limit exceeded. Too many requests.",
    "details": {
      "limit": 1000,
      "reset_time": 1640995200,
      "retry_after": 60
    },
    "trace_id": "req-1234567890"
  }
}
```

### Validation Error
```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "field": "email",
      "message": "The email field is required."
    },
    "trace_id": "req-1234567890"
  }
}
```

### Not Found Error
```json
{
  "error": {
    "code": "NOT_FOUND",
    "message": "The requested resource was not found.",
    "details": {
      "resource": "template",
      "id": "tpl_nonexistent"
    },
    "trace_id": "req-1234567890"
  }
}
```

---

## Webhooks

The notification service sends webhooks for various events:

### Message Events
- `message.sent`
- `message.delivered`
- `message.failed`
- `message.bounced`
- `message.opened` (email only)
- `message.clicked` (email only)

### Bulk Job Events
- `bulk_job.started`
- `bulk_job.completed`
- `bulk_job.failed`
- `bulk_job.cancelled`

### Provider Events
- `provider.quota_warning`
- `provider.quota_exceeded`
- `provider.error`

**Webhook Payload Example:**
```json
{
  "event": "message.delivered",
  "timestamp": "2024-01-15T10:33:00Z",
  "data": {
    "message_id": "msg_1234567890",
    "channel": "email",
    "provider": "sendgrid",
    "recipient": "user@example.com",
    "delivered_at": "2024-01-15T10:33:00Z"
  }
}
```
