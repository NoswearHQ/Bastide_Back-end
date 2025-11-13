# Production Deployment Checklist for Order Email System

## ✅ Pre-Deployment Verification

1. **Monolog Configuration** ✓
   - `order_email` channel configured for production
   - Handler writes to `var/log/order_email.log`
   - Log level: `debug` (captures all details)

2. **Security Configuration** ✓
   - Firewall `public_api` allows `/api/orders` without authentication
   - Access control rules allow public access to test endpoints

3. **SMTP Configuration** ✓
   - Credentials hardcoded in controller (can be moved to .env later)
   - SSL on port 465 configured correctly

## 📋 Production Deployment Steps

### 1. Ensure Log Directory Exists and Has Correct Permissions

```bash
# SSH into production server
cd /var/www/your-project/api

# Create log directory if it doesn't exist
mkdir -p var/log

# Set permissions (adjust based on your web server user)
sudo chown -R www-data:www-data var/log
sudo chmod -R 775 var/log

# Or if using Apache/Nginx with specific user:
sudo chown -R apache:apache var/log
# or
sudo chown -R nginx:nginx var/log
```

### 2. Clear Production Cache

```bash
cd /var/www/your-project/api

# Clear production cache
php bin/console cache:clear --env=prod --no-debug

# Warm up cache
php bin/console cache:warmup --env=prod --no-debug
```

### 3. Verify Routes Are Registered

```bash
php bin/console debug:router --env=prod | grep orders
```

Should show:
- `app_order_sendorder` - POST /api/orders/send
- `app_order_testsmtp` - POST|GET /api/orders/test-smtp
- `app_order_testlog` - GET /api/orders/test-log

### 4. Test Logging Endpoint

```bash
# Test log file creation
curl https://api.bastide.com.tn/api/orders/test-log

# Check log file
tail -f var/log/order_email.log
```

### 5. Test SMTP Endpoint

```bash
# Test SMTP connection
curl https://api.bastide.com.tn/api/orders/test-smtp

# Check logs for SMTP connection details
tail -f var/log/order_email.log
```

### 6. Verify Email Was Received

- Check inbox: `contact@bastidemedical.tn`
- Verify email content
- Check logs for any SMTP errors

### 7. Test Full Order Flow

1. Use frontend to submit an order
2. Check `var/log/order_email.log` for detailed logs
3. Verify email is received
4. Check if frontend shows success/error correctly

## 🔍 Troubleshooting Production Issues

### If Log File Is Not Created

```bash
# Check permissions
ls -la var/log/

# Check if web server can write
sudo -u www-data touch var/log/test_write.log
sudo rm var/log/test_write.log

# Ensure directory exists
mkdir -p var/log
chmod 777 var/log  # Temporary for testing
```

### If SMTP Connection Fails

1. Check logs: `tail -f var/log/order_email.log`
2. Look for error messages about SMTP connection
3. Common issues:
   - Firewall blocking port 465
   - Incorrect SMTP credentials
   - SSL/TLS configuration issues
   - Server IP not whitelisted

### If Routes Return 404

1. Clear cache: `php bin/console cache:clear --env=prod`
2. Verify firewall configuration is deployed
3. Check web server configuration (nginx/apache)

### If Email Is Not Received

1. Check logs for "EMAIL SENT SUCCESSFULLY" message
2. If sent successfully, check spam folder
3. Verify recipient email: `contact@bastidemedical.tn`
4. Check SMTP server logs (if accessible)

## 📝 Log File Location

- **Development**: `api/var/log/order_email.log`
- **Production**: `/var/www/your-project/api/var/log/order_email.log`

## 🔐 Security Notes

- Test endpoints (`/test-log`, `/test-smtp`) are publicly accessible for debugging
- **IMPORTANT**: Remove test endpoints from `security.yaml` after debugging
- Consider moving SMTP credentials to environment variables for better security

## ✨ Post-Deployment

After confirming everything works:
1. Remove test endpoints from `security.yaml` (lines 63-65)
2. Remove test endpoint methods from `OrderController.php`
3. Optionally restrict error messages in production (already configured)

