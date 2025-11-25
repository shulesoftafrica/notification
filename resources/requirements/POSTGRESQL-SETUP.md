# PostgreSQL Setup Guide for Notification Service

## 🐘 PostgreSQL Installation on Windows

### Option 1: Download PostgreSQL Installer (Recommended)

1. **Download PostgreSQL 15+**
   - Visit: https://www.postgresql.org/download/windows/
   - Download the Windows installer
   - Choose PostgreSQL 15 or later

2. **Installation Steps**
   ```
   - Run the installer as Administrator
   - Install Location: C:\Program Files\PostgreSQL\15
   - Data Directory: C:\Program Files\PostgreSQL\15\data
   - Password: Set a password for 'postgres' user (remember this!)
   - Port: 5432 (default)
   - Locale: Default locale
   - Components: PostgreSQL Server, pgAdmin 4, Command Line Tools
   ```

3. **Add to PATH (Important)**
   ```
   - Add C:\Program Files\PostgreSQL\15\bin to your PATH environment variable
   - Open Command Prompt as Admin and run: setx PATH "%PATH%;C:\Program Files\PostgreSQL\15\bin"
   - Restart your terminal/PowerShell
   ```

### Option 2: Using Chocolatey (Alternative)

```powershell
# Install Chocolatey if not already installed
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://community.chocolatey.org/install.ps1'))

# Install PostgreSQL
choco install postgresql --params '/Password:your_password'
```

### Option 3: Using Docker (For Development)

```powershell
# Install Docker Desktop first, then run:
docker run --name postgres-notification \
  -e POSTGRES_DB=notification_service \
  -e POSTGRES_USER=postgres \
  -e POSTGRES_PASSWORD=your_password \
  -p 5432:5432 \
  -d postgres:15

# Connect to the container
docker exec -it postgres-notification psql -U postgres -d notification_service
```

## 🔧 PHP PostgreSQL Extension Setup

### Enable pdo_pgsql in PHP

1. **Edit php.ini file**
   ```
   Location: C:\xampp\php\php.ini (for XAMPP)
   
   Find and uncomment these lines:
   extension=pdo_pgsql
   extension=pgsql
   ```

2. **Restart Apache**
   ```
   - Stop and start Apache in XAMPP Control Panel
   ```

3. **Verify Extension**
   ```powershell
   php -m | grep pgsql
   # Should show: pdo_pgsql, pgsql
   ```

## 🗄️ Database Setup

### 1. Create Database
```sql
-- Connect as postgres user
psql -U postgres -h localhost

-- Create database
CREATE DATABASE notification_service;

-- Create dedicated user (optional but recommended)
CREATE USER notification_user WITH PASSWORD 'secure_password';
GRANT ALL PRIVILEGES ON DATABASE notification_service TO notification_user;

-- Connect to the new database
\c notification_service;

-- Grant schema privileges
GRANT ALL ON SCHEMA public TO notification_user;
```

### 2. Update Laravel Configuration

Update `.env` file:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=notification_service
DB_USERNAME=postgres  # or notification_user
DB_PASSWORD=your_password
```

### 3. Test Connection
```powershell
cd C:\xampp\htdocs\notification\notification-service
php artisan migrate:status
```

## 🚀 Migration Updates for PostgreSQL

Our migrations need some adjustments for PostgreSQL compatibility:

### Key Differences from MySQL:
1. **UUID Type**: PostgreSQL has native UUID support
2. **JSON Type**: Better JSON support than MySQL
3. **Boolean Type**: True boolean type (not tinyint)
4. **Text Type**: No length limit needed
5. **Enum Type**: Different enum syntax
6. **Index Names**: Must be unique across entire database

### Updated Migration Commands
```powershell
# Drop existing tables (if any from MySQL)
php artisan migrate:reset

# Run fresh migrations for PostgreSQL
php artisan migrate:fresh

# Seed test data
php artisan db:seed
```

## 🔍 PostgreSQL Management Tools

### pgAdmin 4 (Included with installer)
- Access: http://localhost/pgAdmin4 or standalone app
- GUI for database management, queries, and monitoring

### Command Line Tools
```powershell
# Connect to database
psql -U postgres -d notification_service

# Common commands
\dt          # List tables
\d table_name # Describe table structure
\q           # Quit
```

### Laravel Telescope PostgreSQL
```powershell
# PostgreSQL works better with Telescope than MySQL
# Better JSON support for complex query analysis
php artisan telescope:install
php artisan migrate
```

## 🐛 Common Issues & Solutions

### Issue 1: "php_pgsql.dll not found"
**Solution:**
1. Copy `php_pgsql.dll` and `php_pdo_pgsql.dll` from PHP installation
2. Ensure they're in the PHP extensions directory
3. Restart web server

### Issue 2: Connection refused
**Solution:**
1. Check PostgreSQL service is running: `services.msc`
2. Verify port 5432 is not blocked by firewall
3. Check pg_hba.conf for connection permissions

### Issue 3: Laravel migration fails
**Solution:**
1. Drop and recreate database if switching from MySQL
2. Clear Laravel cache: `php artisan config:clear`
3. Check migration syntax for PostgreSQL compatibility

## 📊 Performance Benefits of PostgreSQL

### For Notification Service:
1. **Better JSON Support**: Native JSON operations for message metadata
2. **Advanced Indexing**: GIN indexes for JSON fields
3. **Full-Text Search**: Built-in for message content search
4. **Concurrent Performance**: Better handling of high-volume message processing
5. **Data Integrity**: Stronger ACID compliance
6. **Extensibility**: Custom functions for complex notification logic

### PostgreSQL vs MySQL for Our Use Case:
- ✅ Better JSON handling for message metadata
- ✅ Superior concurrent write performance
- ✅ Advanced indexing for complex queries
- ✅ Better handling of queue operations
- ✅ Native UUID support
- ✅ More reliable for high-volume notifications

## 🎯 Next Steps

After PostgreSQL setup:
1. **Update migrations** for PostgreSQL compatibility
2. **Test database connection** with Laravel
3. **Run fresh migrations** to create schema
4. **Update test data** for PostgreSQL syntax
5. **Verify API functionality** with new database

PostgreSQL will provide better performance and reliability for our notification service! 🚀
