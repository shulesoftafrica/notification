-- Notification Service Test Data Setup for PostgreSQL
-- Execute this SQL to create test projects for development and testing

-- Note: Using PostgreSQL syntax

-- Create a test project
INSERT INTO projects (
    id,
    project_id,
    name, 
    api_key,
    secret_key, 
    webhook_url, 
    rate_limit_per_minute,
    rate_limit_per_hour,
    rate_limit_per_day,
    status,
    created_at, 
    updated_at
) VALUES (
    '550e8400-e29b-41d4-a716-446655440000',
    'test-project-001',
    'Test Project',
    'test-api-key-12345',
    'test-secret-key-for-hmac',
    'https://your-app.com/webhooks',
    100,
    5000,
    100000,
    'active',
    NOW(),
    NOW()
);

-- Create test tenants for the project
INSERT INTO project_tenants (
    id,
    project_id,
    tenant_id,
    permissions,
    created_at,
    updated_at
) VALUES 
(
    UUID(),
    'test-project-001',
    'tenant-demo',
    JSON_OBJECT('channels', JSON_ARRAY('email', 'sms'), 'rate_limit', 50),
    NOW(),
    NOW()
),
(
    UUID(),
    'test-project-001',
    'tenant-production',
    JSON_OBJECT('channels', JSON_ARRAY('email', 'sms', 'whatsapp'), 'rate_limit', 100),
    NOW(),
    NOW()
);

-- Create sample email template
INSERT INTO templates (
    id,
    template_id,
    name,
    project_id,
    tenant_id,
    channel,
    locale,
    version,
    status,
    content,
    variables,
    created_at,
    updated_at
) VALUES (
    UUID(),
    'welcome-email',
    'Welcome Email Template',
    'test-project-001',
    'tenant-demo',
    'email',
    'en',
    '1.0',
    'published',
    JSON_OBJECT('subject', 'Welcome to {{app_name}}!', 'html', '<h1>Welcome {{user_name}}!</h1><p>Thank you for joining {{app_name}}. We''re excited to have you on board.</p><p>Best regards,<br>The {{app_name}} Team</p>', 'text', 'Welcome {{user_name}}! Thank you for joining {{app_name}}.'),
    JSON_OBJECT('user_name', 'string', 'app_name', 'string'),
    NOW(),
    NOW()
);

-- Create sample SMS template
INSERT INTO templates (
    id,
    template_id,
    name,
    project_id,
    tenant_id,
    channel,
    locale,
    version,
    status,
    content,
    variables,
    created_at,
    updated_at
) VALUES (
    UUID(),
    'welcome-sms',
    'Welcome SMS Template',
    'test-project-001',
    'tenant-demo',
    'sms',
    'en',
    '1.0',
    'published',
    JSON_OBJECT('text', 'Welcome to {{app_name}}, {{user_name}}! Your account is ready.'),
    JSON_OBJECT('user_name', 'string', 'app_name', 'string'),
    NOW(),
    NOW()
);

-- Create provider configurations
INSERT INTO provider_configs (
    id,
    project_id,
    tenant_id,
    channel,
    provider,
    priority,
    enabled,
    config,
    limits,
    cost_tracking,
    created_at,
    updated_at
) VALUES 
(
    UUID(),
    'test-project-001',
    'tenant-demo',
    'email',
    'sendgrid',
    1,
    1,
    JSON_OBJECT(
        'api_key', 'your_sendgrid_api_key_here',
        'from_email', 'noreply@yourapp.com',
        'from_name', 'Your App Name'
    ),
    JSON_OBJECT('daily_limit', 1000, 'hourly_limit', 100),
    JSON_OBJECT('cost_per_message', 0.001),
    NOW(),
    NOW()
),
(
    UUID(),
    'test-project-001',
    'tenant-demo',
    'sms',
    'twilio',
    1,
    1,
    JSON_OBJECT(
        'account_sid', 'your_twilio_account_sid',
        'auth_token', 'your_twilio_auth_token',
        'from_number', '+1234567890'
    ),
    JSON_OBJECT('daily_limit', 500, 'hourly_limit', 50),
    JSON_OBJECT('cost_per_message', 0.0075),
    NOW(),
    NOW()
);

-- Verify the data was inserted correctly
SELECT 'Projects' as table_name, COUNT(*) as count FROM projects
UNION ALL
SELECT 'Project Tenants', COUNT(*) FROM project_tenants
UNION ALL
SELECT 'Templates', COUNT(*) FROM templates
UNION ALL
SELECT 'Provider Configs', COUNT(*) FROM provider_configs;

-- Show the test project details
SELECT 
    p.id,
    p.project_id,
    p.name,
    p.secret_key,
    p.rate_limit_per_minute,
    p.status,
    COUNT(pt.id) as tenant_count
FROM projects p
LEFT JOIN project_tenants pt ON p.project_id = pt.project_id
WHERE p.project_id = 'test-project-001'
GROUP BY p.id, p.project_id, p.name, p.secret_key, p.rate_limit_per_minute, p.status;
