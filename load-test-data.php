<?php

/**
 * Load Test Data Script for PostgreSQL
 */

echo "🔄 Loading test data into notification schema...\n";
echo "================================================\n\n";

// Database configuration
$config = [
    'host' => '127.0.0.1',
    'port' => '5432',
    'database' => 'other_app',
    'schema' => 'notification',
    'username' => 'postgres',
    'password' => 'tabita'
];

try {
    // Connect to PostgreSQL
    $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✅ Connected to PostgreSQL\n";
    
    // Set search path to notification schema
    $pdo->exec("SET search_path TO {$config['schema']}, public");
    echo "✅ Set search_path to notification schema\n\n";
    
    echo "1️⃣ Inserting test project...\n";
    
    // Insert test project
    $projectSql = "
        INSERT INTO projects (
            id, project_id, name, api_key, secret_key, webhook_url, 
            rate_limit_per_minute, rate_limit_per_hour, rate_limit_per_day,
            status, created_at, updated_at
        ) VALUES (
            '550e8400-e29b-41d4-a716-446655440000'::uuid,
            'test-project-001',
            'Test Project',
            'test-api-key-12345',
            'test-secret-key-for-hmac',
            'https://your-app.com/webhooks',
            100, 5000, 100000,
            'active',
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        ) ON CONFLICT (id) DO UPDATE SET updated_at = CURRENT_TIMESTAMP
    ";
    
    $pdo->exec($projectSql);
    echo "   ✅ Test project created\n";
    
    echo "2️⃣ Inserting test tenants...\n";
    
    // Insert test tenants
    $tenantSql = "
        INSERT INTO project_tenants (
            id, project_id, tenant_id, permissions, status, created_at, updated_at
        ) VALUES 
        (
            gen_random_uuid(),
            'test-project-001',
            'tenant-demo',
            '{\"channels\": [\"email\", \"sms\"], \"rate_limit\": 50}'::jsonb,
            'active',
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        ),
        (
            gen_random_uuid(),
            'test-project-001',
            'tenant-production',
            '{\"channels\": [\"email\", \"sms\", \"whatsapp\"], \"rate_limit\": 100}'::jsonb,
            'active',
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        ) ON CONFLICT (project_id, tenant_id) DO UPDATE SET updated_at = CURRENT_TIMESTAMP
    ";
    
    $pdo->exec($tenantSql);
    echo "   ✅ Test tenants created\n";
    
    echo "3️⃣ Inserting email template...\n";
    
    // Insert email template
    $emailTemplateSql = "
        INSERT INTO templates (
            id, template_id, name, project_id, tenant_id, channel, locale, version, status, content, variables, created_at, updated_at
        ) VALUES (
            gen_random_uuid(),
            'welcome-email',
            'Welcome Email Template',
            'test-project-001',
            'tenant-demo',
            'email',
            'en',
            '1.0',
            'published',
            '{\"subject\": \"Welcome to {{app_name}}!\", \"html\": \"<h1>Welcome {{user_name}}!</h1><p>Thank you for joining {{app_name}}.</p>\", \"text\": \"Welcome {{user_name}}!\"}'::jsonb,
            '{\"user_name\": \"string\", \"app_name\": \"string\"}'::jsonb,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        ) ON CONFLICT (project_id, tenant_id, template_id) DO UPDATE SET updated_at = CURRENT_TIMESTAMP
    ";
    
    $pdo->exec($emailTemplateSql);
    echo "   ✅ Email template created\n";
    
    echo "4️⃣ Inserting SMS template...\n";
    
    // Insert SMS template
    $smsTemplateSql = "
        INSERT INTO templates (
            id, template_id, name, project_id, tenant_id, channel, locale, version, status, content, variables, created_at, updated_at
        ) VALUES (
            gen_random_uuid(),
            'welcome-sms',
            'Welcome SMS Template',
            'test-project-001',
            'tenant-demo',
            'sms',
            'en',
            '1.0',
            'published',
            '{\"text\": \"Welcome to {{app_name}}, {{user_name}}! Your account is ready.\"}'::jsonb,
            '{\"user_name\": \"string\", \"app_name\": \"string\"}'::jsonb,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        ) ON CONFLICT (project_id, tenant_id, template_id) DO UPDATE SET updated_at = CURRENT_TIMESTAMP
    ";
    
    $pdo->exec($smsTemplateSql);
    echo "   ✅ SMS template created\n";
    
    echo "5️⃣ Inserting provider configs...\n";
    
    // Insert provider configs
    $providerSql = "
        INSERT INTO provider_configs (
            id, project_id, tenant_id, channel, provider, priority, enabled, config, limits, cost_tracking, created_at, updated_at
        ) VALUES 
        (
            gen_random_uuid(),
            'test-project-001',
            'tenant-demo',
            'email',
            'sendgrid',
            1, true,
            '{\"api_key\": \"your_sendgrid_api_key_here\", \"from_email\": \"noreply@yourapp.com\"}'::jsonb,
            '{\"daily_limit\": 1000, \"hourly_limit\": 100}'::jsonb,
            '{\"cost_per_message\": 0.001}'::jsonb,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        ),
        (
            gen_random_uuid(),
            'test-project-001',
            'tenant-demo',
            'sms',
            'twilio',
            1, true,
            '{\"account_sid\": \"your_twilio_account_sid\", \"auth_token\": \"your_twilio_auth_token\"}'::jsonb,
            '{\"daily_limit\": 500, \"hourly_limit\": 50}'::jsonb,
            '{\"cost_per_message\": 0.0075}'::jsonb,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        )
    ";
    
    $pdo->exec($providerSql);
    echo "   ✅ Provider configs created\n\n";
    
    echo "📊 Data Summary:\n";
    
    // Get counts
    $counts = $pdo->query("
        SELECT 'Projects' as table_name, COUNT(*) as count FROM projects
        UNION ALL
        SELECT 'Project Tenants', COUNT(*) FROM project_tenants
        UNION ALL
        SELECT 'Templates', COUNT(*) FROM templates
        UNION ALL
        SELECT 'Provider Configs', COUNT(*) FROM provider_configs
    ")->fetchAll();
    
    foreach ($counts as $count) {
        echo "   {$count['table_name']}: {$count['count']}\n";
    }
    
    echo "\n📋 Test Project Details:\n";
    
    // Get project details
    $project = $pdo->query("
        SELECT 
            p.id, p.project_id, p.name, p.secret_key, p.rate_limit_per_minute, p.status,
            COUNT(pt.id) as tenant_count
        FROM projects p
        LEFT JOIN project_tenants pt ON p.project_id = pt.project_id
        WHERE p.project_id = 'test-project-001'
        GROUP BY p.id, p.project_id, p.name, p.secret_key, p.rate_limit_per_minute, p.status
    ")->fetch();
    
    if ($project) {
        echo "   Project ID: {$project['project_id']}\n";
        echo "   Name: {$project['name']}\n";
        echo "   Secret Key: {$project['secret_key']}\n";
        echo "   Rate Limit: {$project['rate_limit_per_minute']}/min\n";
        echo "   Status: {$project['status']}\n";
        echo "   Tenants: {$project['tenant_count']}\n";
    }
    
    echo "\n🎉 Test data loaded successfully!\n";
    echo "Ready to test the notification service API.\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
