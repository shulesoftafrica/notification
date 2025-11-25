# 🔐 Admin Dashboard Login Guide

## 🎯 How to Access the Admin Dashboard

### **Step 1: Start the Application**
```bash
cd notification-service
php artisan serve --port=8002
```

### **Step 2: Access the Login Page**
Open your browser and navigate to:
```
http://127.0.0.1:8002/admin/login
```

### **Step 3: Login Credentials**
Use these pre-configured admin credentials:

**Primary Admin Account:**
- **Email**: `admin@notification.local`
- **Password**: `MySecurePassword123`

**Secondary Admin Account:**
- **Email**: `admin@yourcompany.com`
- **Password**: `password`

### **Step 4: Dashboard Access**
After successful login, you'll be redirected to:
```
http://127.0.0.1:8002/admin/dashboard
```

---

## 📊 Dashboard Features

### **Real-Time Monitoring**
- ✅ System health status
- ✅ Message delivery statistics
- ✅ Success rate monitoring
- ✅ Queue depth tracking

### **Provider Health**
- ✅ Email provider status (SendGrid, Mailgun, etc.)
- ✅ SMS provider status (Twilio, Vonage, etc.)
- ✅ WhatsApp provider status
- ✅ Success rates and response times

### **Recent Activity**
- ✅ Latest messages sent
- ✅ Delivery statuses
- ✅ Provider usage
- ✅ Error tracking

### **Auto-Refresh**
- ✅ Dashboard updates every 30 seconds
- ✅ Real-time status monitoring
- ✅ Live metrics display

---

## 🔧 Admin Management

### **Creating New Admin Users**

1. **Generate Password Hash:**
```bash
php artisan admin:generate-password YourNewPassword123
```

2. **Add to Configuration:**
Edit `config/notification.php`:
```php
'credentials' => [
    'new-admin@company.com' => '$2y$12$hash_generated_above',
    // ... existing admins
],
```

### **Session Management**
- ✅ 8-hour session duration
- ✅ Automatic token refresh
- ✅ IP address validation
- ✅ Secure logout functionality

### **Security Features**
- ✅ HMAC-based authentication
- ✅ IP whitelisting (configurable)
- ✅ Rate limiting protection
- ✅ Session monitoring and logging

---

## 🌐 Available Endpoints

### **Authentication Endpoints**
```
POST /api/admin/auth/login     - Admin login
POST /api/admin/auth/logout    - Admin logout
GET  /api/admin/auth/me        - Current user info
POST /api/admin/auth/refresh   - Refresh token
```

### **Dashboard API Endpoints**
```
GET /api/admin/dashboard/overview        - System overview
GET /api/admin/dashboard/metrics         - Detailed metrics
GET /api/admin/dashboard/provider-health - Provider status
GET /api/admin/dashboard/recent-messages - Recent activity
```

### **Web Interface**
```
GET /admin/login      - Login page
GET /admin/dashboard  - Dashboard interface
GET /admin/           - Redirects to dashboard
```

---

## 🚀 Quick Start Guide

### **1. Immediate Access**
```bash
# Start the server
php artisan serve --port=8002

# Open browser to:
http://127.0.0.1:8002/admin/login

# Login with:
Email: admin@notification.local
Password: MySecurePassword123
```

### **2. Production Setup**
```bash
# Set environment variables
ADMIN_ALLOWED_IPS=192.168.1.100,10.0.0.50
ADMIN_VALIDATE_IP=true

# Update admin credentials in config/notification.php
# Use strong passwords generated with:
php artisan admin:generate-password StrongPassword123
```

---

## 🔒 Security Best Practices

### **Password Requirements**
- ✅ Minimum 8 characters
- ✅ Use strong, unique passwords
- ✅ Regular password rotation
- ✅ No shared accounts

### **IP Whitelisting** (Recommended)
```env
# In .env file
ADMIN_ALLOWED_IPS=192.168.1.100,10.0.0.50,YOUR.OFFICE.IP
```

### **HTTPS in Production**
- ✅ Always use HTTPS for admin access
- ✅ Secure cookies enabled
- ✅ HSTS headers configured

---

## 📱 Mobile-Friendly Interface

The admin dashboard is fully responsive and works on:
- ✅ Desktop computers
- ✅ Tablets
- ✅ Mobile phones
- ✅ All modern browsers

---

## 🎉 **YOU'RE ALL SET!**

### **Login Now:**
1. Go to: `http://127.0.0.1:8002/admin/login`
2. Email: `admin@notification.local`
3. Password: `MySecurePassword123`
4. Click "Sign In"

### **Dashboard Features:**
- 📊 Real-time system monitoring
- 📈 Performance metrics and analytics
- 🔧 Provider health monitoring
- 📋 Recent message activity
- ⚡ Auto-refreshing data

**Your enterprise-grade admin dashboard is ready to use!** 🚀

---

*Need help? Check the logs in `storage/logs/laravel.log` for any authentication issues.*
