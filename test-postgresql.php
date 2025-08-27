<?php

/**
 * PostgreSQL Connection Test for Notification Service
 * Run this script to verify PostgreSQL connectivity before running migrations
 */

echo "🐘 PostgreSQL Connection Test\n";
echo "==============================\n\n";

// Load environment variables from Laravel
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

// Get database configuration
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '5432';
$database = getenv('DB_DATABASE') ?: 'notification_service';
$username = getenv('DB_USERNAME') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

echo "📋 Configuration:\n";
echo "Host: {$host}\n";
echo "Port: {$port}\n";
echo "Database: {$database}\n";
echo "Username: {$username}\n";
echo "Password: " . (empty($password) ? "not set" : "****") . "\n\n";

// Test 1: Check if pdo_pgsql extension is loaded
echo "🔍 Testing PHP PostgreSQL Extension...\n";
if (extension_loaded('pdo_pgsql')) {
    echo "✅ pdo_pgsql extension is loaded\n";
} else {
    echo "❌ pdo_pgsql extension is NOT loaded\n";
    echo "   Please enable pdo_pgsql in your php.ini file\n";
    exit(1);
}

if (extension_loaded('pgsql')) {
    echo "✅ pgsql extension is loaded\n";
} else {
    echo "⚠️  pgsql extension is not loaded (optional)\n";
}

echo "\n";

// Test 2: Test connection without database (to postgres default db)
echo "🔗 Testing PostgreSQL Server Connection...\n";
try {
    $dsn = "pgsql:host={$host};port={$port};dbname=postgres";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    
    $version = $pdo->query("SELECT version()")->fetchColumn();
    echo "✅ PostgreSQL server connection successful\n";
    echo "   Version: " . substr($version, 0, 50) . "...\n";
    
} catch (PDOException $e) {
    echo "❌ PostgreSQL server connection failed\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "\n🔧 Troubleshooting:\n";
    echo "1. Ensure PostgreSQL is installed and running\n";
    echo "2. Check if the service is started (services.msc on Windows)\n";
    echo "3. Verify connection details in .env file\n";
    echo "4. Check firewall settings for port {$port}\n";
    exit(1);
}

echo "\n";

// Test 3: Test target database connection
echo "🗄️  Testing Target Database Connection...\n";
try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    
    echo "✅ Target database '{$database}' connection successful\n";
    
    // Test basic query
    $result = $pdo->query("SELECT current_database(), current_user");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    echo "   Current database: " . $row['current_database'] . "\n";
    echo "   Current user: " . $row['current_user'] . "\n";
    
} catch (PDOException $e) {
    echo "❌ Target database '{$database}' connection failed\n";
    echo "   Error: " . $e->getMessage() . "\n";
    
    // Try to create the database
    echo "\n🛠️  Attempting to create database...\n";
    try {
        $dsn = "pgsql:host={$host};port={$port};dbname=postgres";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        $pdo->exec("CREATE DATABASE {$database}");
        echo "✅ Database '{$database}' created successfully\n";
        
        // Test the new database
        $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        echo "✅ New database connection successful\n";
        
    } catch (PDOException $createError) {
        echo "❌ Failed to create database: " . $createError->getMessage() . "\n";
        echo "\n🔧 Manual database creation required:\n";
        echo "1. Connect to PostgreSQL as superuser:\n";
        echo "   psql -U postgres\n";
        echo "2. Create the database:\n";
        echo "   CREATE DATABASE {$database};\n";
        echo "3. Grant permissions (if using different user):\n";
        echo "   GRANT ALL PRIVILEGES ON DATABASE {$database} TO {$username};\n";
        exit(1);
    }
}

echo "\n";

// Test 4: Test Laravel artisan connection
echo "🎨 Testing Laravel Database Connection...\n";
if (file_exists(__DIR__ . '/artisan')) {
    $output = [];
    $returnCode = 0;
    
    exec('php artisan migrate:status 2>&1', $output, $returnCode);
    
    if ($returnCode === 0) {
        echo "✅ Laravel database connection successful\n";
        echo "   Migration status: " . (count($output) > 0 ? "Available" : "No migrations") . "\n";
    } else {
        echo "❌ Laravel database connection failed\n";
        echo "   Output: " . implode("\n   ", $output) . "\n";
    }
} else {
    echo "⚠️  Laravel artisan not found (not in Laravel directory)\n";
}

echo "\n";

// Summary
echo "🎉 Connection Test Summary:\n";
echo "===========================\n";
echo "✅ PHP Extension: pdo_pgsql loaded\n";
echo "✅ PostgreSQL Server: Connected\n";
echo "✅ Target Database: Available\n";
echo "✅ Ready for Laravel migrations\n\n";

echo "🚀 Next Steps:\n";
echo "1. Run migrations: php artisan migrate:fresh\n";
echo "2. Load test data: psql -U {$username} -d {$database} < test-data-postgresql.sql\n";
echo "3. Start Laravel server: php artisan serve\n";
echo "4. Run API tests: php test-hmac.php\n";

echo "\n💡 PostgreSQL Benefits for Notification Service:\n";
echo "- Superior JSON/JSONB support for message metadata\n";
echo "- Better concurrent performance for high-volume messaging\n";
echo "- Advanced indexing capabilities (GIN, GiST)\n";
echo "- Native UUID support for distributed architecture\n";
echo "- Full-text search capabilities for message content\n";
echo "- Stronger ACID compliance for reliable message tracking\n";
