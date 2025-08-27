# Notification Service

A comprehensive, multi-channel notification service built with Laravel 12, supporting email, SMS, and WhatsApp messaging with enterprise-grade features.

## 🚀 Features

### Phase 3 - Advanced Features ✅
- **Template Management**: Create, manage, and preview message templates
- **Provider Configuration**: Configure and test multiple notification providers
- **Bulk Operations**: Send messages to thousands of recipients efficiently
- **Analytics Dashboard**: Comprehensive metrics and performance analytics
- **Rate Limiting**: Multi-level rate limiting with Redis backend
- **Enterprise APIs**: Advanced management and monitoring capabilities

### Phase 2 - Core Features ✅
- **Multi-channel Support**: Email, SMS, and WhatsApp notifications
- **Provider Abstraction**: Support for multiple providers per channel
- **Queue Processing**: Async message processing with Redis
- **Webhook Handling**: Real-time delivery status updates
- **HMAC Authentication**: Secure API access with project-based auth
- **Failover Support**: Automatic provider switching on failures

### Phase 1 - Foundation ✅
- **REST API**: Clean, RESTful API design
- **Database Design**: Optimized schema for high performance
- **Basic Messaging**: Core send/receive functionality
- **Health Monitoring**: System health and status endpoints

## 🏗️ Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   API Gateway   │───▶│  Auth & Rate    │───▶│   Controllers   │
│   (Routes)      │    │   Limiting      │    │   (Business)    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                                                       │
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Providers     │◀───│     Queue       │◀───│    Services     │
│  (Email/SMS)    │    │   Processing    │    │   (Core Logic)  │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                                                       │
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Analytics     │───▶│     Redis       │───▶│    Database     │
│   Tracking      │    │   (Cache/Rate)  │    │  (PostgreSQL)   │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## 📚 API Documentation

### Core Endpoints

```bash
# Health Check
GET /v1/health

# Send Message
POST /v1/messages

# Template Management
GET /v1/templates
POST /v1/templates
PUT /v1/templates/{id}

# Bulk Operations
POST /v1/bulk/messages
GET /v1/bulk/messages/{batchId}

# Analytics
GET /v1/analytics/dashboard
GET /v1/analytics/delivery-rates

# Provider Configuration
GET /v1/config/providers
POST /v1/config/providers
```

See [API Reference](docs/api-reference.md) for complete documentation.

## 🛠️ Installation

### Prerequisites
- PHP 8.3+
- Laravel 12
- PostgreSQL 13+
- Redis 6+
- Composer

### Setup

1. **Clone and Install**
```bash
git clone <repository-url>
cd notification-service
composer install
```

2. **Environment Configuration**
```bash
cp .env.example .env
php artisan key:generate
```

3. **Database Setup**
```bash
php artisan migrate
php artisan db:seed
```

4. **Queue Workers**
```bash
php artisan queue:work --queue=notifications,default
```

5. **Start Server**
```bash
php artisan serve
```

## 🔧 Configuration

### Environment Variables

```env
# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=notification_service

# Redis (Queues & Rate Limiting)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Queue Configuration
QUEUE_CONNECTION=redis

# Provider APIs
SENDGRID_API_KEY=your-sendgrid-key
MAILGUN_API_KEY=your-mailgun-key
TWILIO_SID=your-twilio-sid
TWILIO_TOKEN=your-twilio-token
```

### Rate Limiting

Rate limits are configurable per project, tenant, and endpoint:

```php
// Global Limits
'global' => [
    'minute' => 10000,
    'hour' => 500000,
    'day' => 10000000
],

// Bulk Operation Limits
'bulk' => [
    'minute' => 10,
    'hour' => 100,
    'day' => 1000
]
```

## 🧪 Testing

### Run Test Suite
```bash
# Unit Tests
php artisan test

# Feature Tests
php artisan test --testsuite=Feature

# API Tests
php artisan test tests/Feature/Api/
```

### Manual Testing
```bash
# Health Check
curl http://localhost:8000/api/v1/health

# Send Test Message
curl -X POST http://localhost:8000/api/v1/messages \
  -H "Content-Type: application/json" \
  -H "X-Project-ID: your-project-id" \
  -H "X-Signature: your-hmac-signature" \
  -d '{"channel": "email", "recipient": {"email": "test@example.com"}}'
```

See [Testing Guide](docs/phase3-testing.md) for comprehensive test procedures.

## 📊 Monitoring

### Health Endpoints
- `GET /v1/health` - Service health
- `GET /v1/admin/system-health` - Detailed system status
- `GET /v1/admin/queue-status` - Queue monitoring

### Analytics Dashboard
- Delivery rates and success metrics
- Provider performance comparison
- Cost tracking and optimization
- Volume analytics and trends

### Logging
```bash
# Monitor application logs
tail -f storage/logs/laravel.log

# Monitor rate limiting
redis-cli MONITOR | grep rate_limit
```

## 🚀 Deployment

### Production Checklist
- [ ] Environment variables configured
- [ ] Database migrations applied
- [ ] Queue workers running
- [ ] Redis configured and running
- [ ] Rate limits configured
- [ ] Provider credentials verified
- [ ] Monitoring setup

### Scaling Considerations
- **Horizontal Scaling**: Multiple app instances behind load balancer
- **Queue Workers**: Multiple worker processes for high throughput
- **Redis Clustering**: For high-availability rate limiting
- **Database Optimization**: Read replicas for analytics queries

## 🔐 Security

### Authentication
- HMAC-SHA256 signature verification
- Project-based access control
- Timestamp validation to prevent replay attacks

### Rate Limiting
- Multi-level rate limiting (global, project, tenant, endpoint)
- Redis-based token bucket algorithm
- Graceful degradation on limit exceeded

### Data Protection
- Sensitive data encryption at rest
- Secure provider credential storage
- Audit logging for compliance

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 📞 Support

- **Documentation**: [docs/](docs/)
- **API Reference**: [docs/api-reference.md](docs/api-reference.md)
- **Testing Guide**: [docs/phase3-testing.md](docs/phase3-testing.md)
- **Feature Guide**: [docs/phase3-features.md](docs/phase3-features.md)

## 🗺️ Roadmap

### Phase 4 - Future Enhancements
- [ ] Real-time WebSocket dashboard
- [ ] Machine learning analytics
- [ ] A/B testing framework
- [ ] Multi-region deployment
- [ ] Advanced webhook filtering

### Current Status: Phase 3 Complete ✅
All advanced enterprise features have been implemented and tested.

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
