# Forgot Password System - Quick Reference Guide

## 📋 Overview

A complete, secure password reset system with two verification methods: Email and SMS OTP.

## 🚀 Quick Setup (2 minutes)

```bash
# 1. Run the migration to create database tables
php run-password-reset-migration.php

# 2. Test the system
php test-password-reset.php

# 3. Access the forgot password page
http://localhost/TeamPagal_Barangay219/Barangay219/public/forgot-password.php
```

## 📁 Files Created

| File | Purpose |
|------|---------|
| `public/forgot-password.php` | User selects verification method (Email or SMS) |
| `public/verify-reset.php` | Verify OTP or email token |
| `public/reset-password.php` | Enter new password |
| `api/password-reset.php` | API endpoints for all operations |
| `includes/password-reset.php` | Core utility functions |
| `database/migrations/003_password_reset_system.sql` | Database schema |
| `run-password-reset-migration.php` | Migration runner script |
| `test-password-reset.php` | Test script to verify setup |
| `FORGOT_PASSWORD_SETUP.md` | Complete documentation |

## 🔄 User Flow

### Email Method
```
Forgot Password → Enter Email → Send Link → Click Link → Reset Password → Done
```

### SMS Method
```
Forgot Password → Enter Email/Username → Send OTP → Enter 6 Digits → Reset Password → Done
```

## 🔐 Security Features

- ✅ Rate limiting (3 requests/hour)
- ✅ Time-limited tokens (60 min) & OTPs (10 min)
- ✅ Single-use verification codes
- ✅ Strong password requirements
- ✅ Audit logging with IP tracking
- ✅ Session-based verification

## 💪 Password Requirements

All of the following MUST be met:
- Minimum 8 characters
- At least 1 uppercase letter (A-Z)
- At least 1 lowercase letter (a-z)
- At least 1 number (0-9)
- At least 1 special character (!@#$%^&*)

Example: `NewPass@123`

## 🔌 API Endpoints

All endpoints are POST requests to `/api/password-reset.php`

### Initiate Reset
```
?action=initiate
Body: {"identifier": "email or username", "method": "email|sms"}
```

### Verify OTP (SMS)
```
?action=verify-otp
Body: {"otp": "123456", "user_identifier": "email or username"}
```

### Verify Token (Email)
```
?action=verify-token
Body: {"token": "token_from_email"}
```

### Reset Password
```
?action=reset-password
Body: {"new_password": "...", "confirm_password": "..."}
```

### Resend OTP
```
?action=resend-otp
Body: {"user_identifier": "email or username"}
```

## 🔍 Testing

### Development Mode
The system logs OTPs and tokens to PHP error log:

```bash
# View generated OTPs/tokens
tail -f /var/log/php_errors.log

# Look for:
# SMS OTP to +1234567890: 123456
# Email sent to user@example.com: Reset token: abc123...
```

### Manual Test Steps
1. Go to: `http://localhost/.../public/forgot-password.php`
2. Enter registered email or username
3. Select Email or SMS method
4. Check error log for OTP/token
5. Complete verification
6. Set new password
7. Login with new credentials

## 📊 Database Tables

### password_reset_tokens
Stores email verification tokens
```sql
SELECT * FROM password_reset_tokens;
```

### password_reset_otp
Stores SMS OTP codes
```sql
SELECT * FROM password_reset_otp;
```

### password_reset_rate_limit
Tracks rate limiting
```sql
SELECT * FROM password_reset_rate_limit;
```

### password_reset_logs
Audit trail of all reset activities
```sql
SELECT * FROM password_reset_logs ORDER BY created_at DESC;
```

## ⚙️ Configuration

Edit in `includes/password-reset.php`:

```php
define('OTP_LENGTH', 6);                          // 6-digit OTP
define('OTP_EXPIRY_MINUTES', 10);                 // 10 min validity
define('TOKEN_EXPIRY_MINUTES', 60);               // 60 min validity
define('MAX_OTP_ATTEMPTS', 5);                    // 5 wrong attempts then lock
define('MAX_RESET_REQUESTS_PER_HOUR', 3);         // Max 3 requests/hour
define('MAX_OTP_VERIFICATIONS_PER_HOUR', 10);     // Max 10 attempts/hour
define('MAX_RESEND_OTP_PER_HOUR', 5);             // Max 5 resends/hour
```

## 📧 Email & SMS Configuration

### To Enable Email Sending
Edit `sendResetLinkViaEmail()` in `includes/password-reset.php`:

```php
// Use mail(), PHPMailer, Swiftmailer, SendGrid, etc.
mail($email, $subject, $message, $headers);
```

### To Enable SMS Sending
Edit `sendOTPViaSMS()` in `includes/password-reset.php`:

```php
// Integrate with SMS provider (Twilio, Nexmo, Globe, etc.)
$client->messages->create($phone, ['body' => $message]);
```

## 🐛 Common Issues

| Issue | Solution |
|-------|----------|
| "No phone number found" | Add contact_number to resident record |
| "No email address found" | Add email to users table |
| "Too many attempts" | Wait 60 minutes for rate limit to reset |
| OTP not displaying | Check error log: `tail -f /var/log/php_errors.log` |
| Token not working | Token may have expired (> 60 minutes) |

## 📝 Log Examples

### Successful Reset
```sql
SELECT * FROM password_reset_logs 
WHERE action = 'password_reset' 
ORDER BY created_at DESC LIMIT 1;
```

### View All Activities for User
```sql
SELECT * FROM password_reset_logs 
WHERE user_id = 1 
ORDER BY created_at DESC;
```

### Failed Attempts
```sql
SELECT * FROM password_reset_logs 
WHERE success = 0 
ORDER BY created_at DESC LIMIT 10;
```

## 🧹 Maintenance

### Cleanup Expired Records
```php
require_once 'includes/password-reset.php';
cleanupExpiredPasswordResets();
```

Or automatically via scheduled task:
```bash
# Daily cleanup at 2 AM
0 2 * * * cd /path/to/Barangay219 && php -r "require 'includes/password-reset.php'; cleanupExpiredPasswordResets();"
```

## 🔗 Links

| Link | Purpose |
|------|---------|
| `/public/forgot-password.php` | Forgot password page |
| `/public/verify-reset.php` | Verification page |
| `/public/reset-password.php` | Password reset form |
| `/FORGOT_PASSWORD_SETUP.md` | Full documentation |
| `/run-password-reset-migration.php` | Setup script |
| `/test-password-reset.php` | Test script |

## 📞 Support

For detailed information, see `FORGOT_PASSWORD_SETUP.md` file.

---

**Version:** 1.0.0  
**Last Updated:** February 23, 2026  
**Status:** ✅ Production Ready
