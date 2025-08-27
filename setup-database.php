<?php

/**
 * Database Setup Script for Notification Service
 * This script creates the PostgreSQL schema and verifies the connection
 */

echo "🚀 Setting up PostgreSQL database for Notification Service\n";
echo "========================================================\n\n";

// Database configuration from .env
$config = [
    'host' => '127.0.0.1',
    'port' => '5432',
    'database' => 'other_app',
    'schema' => 'notification',
    'username' => 'postgres',
    'password' => 'tabita'
];

try {
    echo "1️⃣ Testing PostgreSQL connection...\n";
    
    // Connect to PostgreSQL database
    $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "   ✅ Connected to PostgreSQL database '{$config['database']}'\n";
    
    // Get PostgreSQL version
    $version = $pdo->query('SELECT version()')->fetchColumn();
    echo "   📊 PostgreSQL Version: " . substr($version, 0, 50) . "...\n\n";
    
    echo "2️⃣ Creating notification schema...\n";
    
    // Check if schema exists
    $schemaExists = $pdo->prepare("SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?");
    $schemaExists->execute([$config['schema']]);
    
    if ($schemaExists->fetchColumn()) {
        echo "   ℹ️  Schema '{$config['schema']}' already exists\n";
    } else {
        // Create schema
        $pdo->exec("CREATE SCHEMA {$config['schema']}");
        echo "   ✅ Created schema '{$config['schema']}'\n";
    }
    
    // Grant permissions
    $pdo->exec("GRANT ALL ON SCHEMA {$config['schema']} TO {$config['username']}");
    echo "   ✅ Granted permissions on schema\n";
    
    // Set search path for this session
    $pdo->exec("SET search_path TO {$config['schema']}, public");
    echo "   ✅ Set search_path to '{$config['schema']}, public'\n\n";
    
    echo "3️⃣ Verifying Laravel can connect...\n";
    
    // Test Laravel database connection
    $currentDir = __DIR__;
    chdir($currentDir);
    
    // Clear Laravel config cache
    exec('php artisan config:clear 2>&1', $output, $returnCode);
    if ($returnCode === 0) {
        echo "   ✅ Laravel config cache cleared\n";
    }
    
    // Test Laravel database connection
    exec('php artisan migrate:status 2>&1', $output, $returnCode);
    if ($returnCode === 0) {
        echo "   ✅ Laravel can connect to PostgreSQL\n";
    } else {
        echo "   ⚠️  Laravel connection issue (this is normal if migrations haven't run yet)\n";
        echo "   Output: " . implode("\n   ", array_slice($output, -3)) . "\n";
    }
    
    echo "\n4️⃣ Database setup summary:\n";
    echo "   📍 Host: {$config['host']}:{$config['port']}\n";
    echo "   📍 Database: {$config['database']}\n";
    echo "   📍 Schema: {$config['schema']}\n";
    echo "   📍 User: {$config['username']}\n";
    echo "   ✅ Ready for Laravel migrations!\n\n";
    
    echo "🎯 Next step: Run 'php artisan migrate' to create tables\n";
    
} catch (PDOException $e) {
    echo "❌ Database connection failed!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    
    echo "🔧 Troubleshooting steps:\n";
    echo "1. Verify PostgreSQL is running\n";
    echo "2. Check database 'other_app' exists\n";
    echo "3. Verify username/password: postgres/tabita\n";
    echo "4. Ensure port 5432 is accessible\n";
    
    exit(1);
} catch (Exception $e) {
    echo "❌ Setup failed: " . $e->getMessage() . "\n";
    exit(1);
}
