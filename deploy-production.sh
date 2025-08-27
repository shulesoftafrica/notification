#!/bin/bash

# Production Deployment Script for Notification Service
# This script deploys the notification service to production

set -e  # Exit on any error

echo "🚀 Starting Production Deployment for Notification Service"
echo "============================================================"

# Configuration
DEPLOYMENT_DIR="/var/www/notification-service"
BACKUP_DIR="/var/backups/notification-service"
SERVICE_NAME="notification-service"
NGINX_CONFIG="/etc/nginx/sites-available/notification-service"
SUPERVISOR_CONFIG="/etc/supervisor/conf.d/notification-workers.conf"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Helper functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Step 1: Pre-deployment checks
echo "📋 Step 1: Pre-deployment checks"
log_info "Checking system requirements..."

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   log_error "This script should not be run as root"
   exit 1
fi

# Check required commands
for cmd in php composer nginx mysql redis-server supervisor; do
    if ! command -v $cmd &> /dev/null; then
        log_error "$cmd is not installed"
        exit 1
    fi
done

log_info "✅ System requirements check passed"

# Step 2: Create backup
echo "📦 Step 2: Creating backup"
BACKUP_TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_PATH="$BACKUP_DIR/$BACKUP_TIMESTAMP"

log_info "Creating backup at $BACKUP_PATH"
mkdir -p $BACKUP_PATH

if [ -d "$DEPLOYMENT_DIR" ]; then
    cp -r $DEPLOYMENT_DIR $BACKUP_PATH/app
    log_info "✅ Application backup created"
fi

# Backup database
log_info "Creating database backup..."
mysqldump notification_service_production > $BACKUP_PATH/database.sql
log_info "✅ Database backup created"

# Step 3: Deploy application
echo "🔄 Step 3: Deploying application"

# Create deployment directory if it doesn't exist
mkdir -p $DEPLOYMENT_DIR

# Copy application files
log_info "Copying application files..."
rsync -av --exclude='node_modules' --exclude='.git' --exclude='storage/logs/*' \
      ./ $DEPLOYMENT_DIR/

# Set proper permissions
log_info "Setting file permissions..."
sudo chown -R www-data:www-data $DEPLOYMENT_DIR
sudo chmod -R 755 $DEPLOYMENT_DIR
sudo chmod -R 775 $DEPLOYMENT_DIR/storage
sudo chmod -R 775 $DEPLOYMENT_DIR/bootstrap/cache

log_info "✅ Application files deployed"

# Step 4: Install dependencies
echo "📦 Step 4: Installing dependencies"
cd $DEPLOYMENT_DIR

log_info "Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader

log_info "✅ Dependencies installed"

# Step 5: Environment configuration
echo "⚙️ Step 5: Environment configuration"

if [ ! -f ".env" ]; then
    log_info "Copying production environment file..."
    cp .env.production.example .env
    log_warn "⚠️  Please update .env with your production settings!"
fi

# Generate application key if not set
if ! grep -q "APP_KEY=base64:" .env; then
    log_info "Generating application key..."
    php artisan key:generate
fi

log_info "✅ Environment configured"

# Step 6: Database migration
echo "🗄️ Step 6: Database migration"

log_info "Running database migrations..."
php artisan migrate --force

log_info "✅ Database migrations completed"

# Step 7: Cache optimization
echo "⚡ Step 7: Cache optimization"

log_info "Clearing and optimizing caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

log_info "✅ Cache optimization completed"

# Step 8: Web server configuration
echo "🌐 Step 8: Web server configuration"

log_info "Configuring Nginx..."
sudo tee $NGINX_CONFIG > /dev/null <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name notifications.yourdomain.com;
    
    # Redirect HTTP to HTTPS
    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name notifications.yourdomain.com;
    
    root $DEPLOYMENT_DIR/public;
    index index.php;
    
    # SSL Configuration (update with your certificate paths)
    ssl_certificate /path/to/your/certificate.crt;
    ssl_certificate_key /path/to/your/private.key;
    
    # Security headers
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy "default-src 'self'; frame-ancestors 'none';" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    
    # Rate limiting
    limit_req_zone \$binary_remote_addr zone=api:10m rate=10r/s;
    limit_req zone=api burst=20 nodelay;
    
    # Health check (no rate limiting)
    location /health {
        try_files \$uri \$uri/ /index.php?\$query_string;
        access_log off;
    }
    
    # API routes
    location /api {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    # PHP handling
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        
        # Security
        fastcgi_hide_header X-Powered-By;
    }
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ /(storage|bootstrap|config|database|routes|resources|tests) {
        deny all;
    }
    
    # Gzip compression
    gzip on;
    gzip_types text/plain text/css text/js text/xml text/javascript application/javascript application/xml+rss application/json;
}
EOF

# Enable site
sudo ln -sf $NGINX_CONFIG /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

log_info "✅ Nginx configured"

# Step 9: Queue workers configuration
echo "👷 Step 9: Queue workers configuration"

log_info "Configuring Supervisor for queue workers..."
sudo tee $SUPERVISOR_CONFIG > /dev/null <<EOF
[program:notification-worker]
process_name=%(program_name)s_%(process_num)02d
command=php $DEPLOYMENT_DIR/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --queue=default,webhooks,notifications
directory=$DEPLOYMENT_DIR
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=$DEPLOYMENT_DIR/storage/logs/worker.log
stopwaitsecs=3600

[program:notification-scheduler]
process_name=%(program_name)s
command=php $DEPLOYMENT_DIR/artisan schedule:work
directory=$DEPLOYMENT_DIR
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
redirect_stderr=true
stdout_logfile=$DEPLOYMENT_DIR/storage/logs/scheduler.log

[program:notification-monitor]
process_name=%(program_name)s
command=php $DEPLOYMENT_DIR/artisan monitor:health --continuous
directory=$DEPLOYMENT_DIR
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
redirect_stderr=true
stdout_logfile=$DEPLOYMENT_DIR/storage/logs/monitor.log
EOF

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start notification-worker:*
sudo supervisorctl start notification-scheduler:*
sudo supervisorctl start notification-monitor:*

log_info "✅ Queue workers configured and started"

# Step 10: Setup monitoring
echo "📊 Step 10: Setting up monitoring"

# Create log rotation
sudo tee /etc/logrotate.d/notification-service > /dev/null <<EOF
$DEPLOYMENT_DIR/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    notifempty
    create 0644 www-data www-data
    postrotate
        supervisorctl restart notification-worker:*
    endscript
}
EOF

# Create systemd service for health monitoring
sudo tee /etc/systemd/system/notification-health.service > /dev/null <<EOF
[Unit]
Description=Notification Service Health Monitor
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=$DEPLOYMENT_DIR
ExecStart=/usr/bin/php artisan monitor:health --continuous
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable notification-health
sudo systemctl start notification-health

log_info "✅ Monitoring configured"

# Step 11: SSL/TLS Configuration
echo "🔒 Step 11: SSL/TLS Configuration"

log_warn "⚠️  SSL certificate setup required:"
log_warn "   1. Obtain SSL certificate from your CA"
log_warn "   2. Update paths in $NGINX_CONFIG"
log_warn "   3. Reload Nginx: sudo systemctl reload nginx"

# Step 12: Firewall configuration
echo "🛡️ Step 12: Firewall configuration"

log_info "Configuring UFW firewall..."
sudo ufw allow 22/tcp  # SSH
sudo ufw allow 80/tcp  # HTTP
sudo ufw allow 443/tcp # HTTPS
sudo ufw --force enable

log_info "✅ Firewall configured"

# Step 13: Final health check
echo "🏥 Step 13: Final health check"

log_info "Running post-deployment health check..."
sleep 5

# Test basic endpoint
if curl -s -o /dev/null -w "%{http_code}" http://localhost/health | grep -q "200"; then
    log_info "✅ Health check endpoint responding"
else
    log_error "❌ Health check endpoint not responding"
fi

# Step 14: Deployment summary
echo "📋 Step 14: Deployment Summary"
echo "============================================================"

log_info "🎉 Deployment completed successfully!"
echo ""
echo "Application Details:"
echo "  - Deployment Path: $DEPLOYMENT_DIR"
echo "  - Backup Path: $BACKUP_PATH"
echo "  - Nginx Config: $NGINX_CONFIG"
echo "  - Supervisor Config: $SUPERVISOR_CONFIG"
echo ""
echo "Services Status:"
echo "  - Nginx: $(systemctl is-active nginx)"
echo "  - PHP-FPM: $(systemctl is-active php8.2-fpm)"
echo "  - MySQL: $(systemctl is-active mysql)"
echo "  - Redis: $(systemctl is-active redis-server)"
echo "  - Supervisor: $(systemctl is-active supervisor)"
echo ""
echo "Queue Workers:"
sudo supervisorctl status notification-worker:*
echo ""
echo "Next Steps:"
echo "  1. Update .env with production settings"
echo "  2. Configure SSL certificate"
echo "  3. Set up monitoring alerts"
echo "  4. Configure provider API keys"
echo "  5. Test with sample messages"
echo ""
echo "Health Check URL: https://notifications.yourdomain.com/health"
echo "Admin Dashboard: https://notifications.yourdomain.com/api/admin/dashboard/overview"
echo ""
log_info "🚀 Notification Service is now LIVE!"

echo "============================================================"
echo "Deployment completed at: $(date)"
