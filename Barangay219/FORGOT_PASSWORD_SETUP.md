# E-Barangay Forgot Password System Setup

## Overview

The Forgot Password feature enables users to securely reset their account passwords using two verification methods:

1. **Email Verification** - Send a reset link/token via email
2. **SMS Verification** - Send a One-Time Password (OTP) via SMS

## Features Implemented

### Security Features
- ✓ Rate limiting on password reset attempts
- ✓ Time-limited tokens (60 minutes for email, 10 minutes for OTP)
- ✓ Single-use tokens and OTPs
- ✓ Maximum OTP verification attempts (5 attempts)
- ✓ Password strength requirements enforcement
- ✓ Complete audit trail logging
- ✓ Session-based verification tracking
- ✓ IP address and user agent logging
- ✓ Automatic cleanup of expired tokens/OTPs

### User Experience
- ✓ Simple method selection (Email or SMS)
- ✓ Real-time password strength indicator
- ✓ Visual password requirement validation
- ✓ OTP digit-by-digit input with auto-focus
- ✓ One-click OTP resend (with cooldown)
- ✓ Email link auto-verification
- ✓ Clear error messages and guidance

## Installation & Setup

### 1. Database Migration

Run the password reset system migration to create required tables:

```sql
-- Option A: Run the migration file directly
mysql -u root -p barangay219_db < database/migrations/003_password_reset_system.sql

-- Option B: Run via PHP script (if created)
php database/migrations/run-migration.php --type=password_reset
```

**Tables Created:**
- `password_reset_tokens` - Email reset tokens
- `password_reset_otp` - SMS OTP codes
- `password_reset_rate_limit` - Rate limiting enforcement
- `password_reset_logs` - Audit trail

**User Table Columns Added:**
- `password_reset_request_id` - Track active reset requests
- `password_reset_request_method` - Track method used
- `password_reset_request_expires` - Track expiration

### 2. Files Created

```
public/
  ├── forgot-password.php           # Main forgot password page (method selection)
  ├── verify-reset.php              # Verification page (OTP or token entry)
  └── reset-password.php            # Final password entry page

api/
  └── password-reset.php            # API endpoints

includes/
  └── password-reset.php            # Helper functions and utilities

database/migrations/
  └── 003_password_reset_system.sql # Database schema

Barangay219/
  └── FORGOT_PASSWORD_SETUP.md     # This file
```

### 3. Configuration

The system uses predefined constants. If you need to adjust them, edit `includes/password-reset.php`:

```php
define('OTP_LENGTH', 6);                          // Length of OTP code
define('OTP_EXPIRY_MINUTES', 10);                 // OTP expires in 10 minutes
define('TOKEN_EXPIRY_MINUTES', 60);               // Email token expires in 60 minutes
define('MAX_OTP_ATTEMPTS', 5);                    // Max OTP verification attempts
define('RATE_LIMIT_WINDOW_MINUTES', 60);          // Rate limit window
define('MAX_RESET_REQUESTS_PER_HOUR', 3);         // Max password reset initiation
define('MAX_OTP_VERIFICATIONS_PER_HOUR', 10);     // Max OTP verifications
define('MAX_RESEND_OTP_PER_HOUR', 5);             // Max OTP resends
```

### 4. Email & SMS Configuration (Important!)

Currently, the system logs OTPs and emails to the error log for development/testing.

**To enable actual SMS sending:**
Edit `includes/password-reset.php` - `sendOTPViaSMS()` function:

```php
function sendOTPViaSMS($phone_number, $otp) {
    // Integrate with SMS provider (Twilio, Nexmo, Globe Labs, etc.)
    // Example with Twilio:
    
    require_once 'twilio-php/vendor/autoload.php';
    $client = new Twilio\Rest\Client(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN);
    
    $client->messages->create(
        $phone_number,
        [
            'from' => TWILIO_PHONE_NUMBER,
            'body' => "Your E-Barangay password reset OTP is: {$otp}. Valid for 10 minutes."
        ]
    );
    
    return true;
}
```

**To enable actual Email sending:**
Modify `sendResetLinkViaEmail()` function to use a mail service:

```php
// Example with PHPMailer:
$mail = new PHPMailer(true);
$mail->Host = MAIL_HOST;
$mail->Username = MAIL_USER;
$mail->Password = MAIL_PASS;
$mail->setFrom('noreply@ebarangay.local');
$mail->addAddress($email);
$mail->Subject = 'Password Reset Request';
$mail->Body = $message;
$mail->isHTML(true);

return $mail->send();
```

## User Flow

### Flow A: Email Verification

1. User clicks "Forgot Password" on login page
2. Enters email/username and selects "Email" method
3. System sends reset link via email (or logs it for testing)
4. User clicks link in email (auto-verifies) OR pastes code on verify page
5. User enters new password meeting requirements
6. Password reset confirmed, user redirected to login

### Flow B: SMS Verification

1. User clicks "Forgot Password" on login page
2. Enters email/username and selects "SMS" method
3. System sends 6-digit OTP via SMS (or logs it for testing)
4. User enters OTP with visual digit-by-digit input
5. User can resend OTP if needed (with 60-second cooldown)
6. After OTP verification, user enters new password
7. Password reset confirmed, user redirected to login

## API Endpoints

All endpoints are in `api/password-reset.php`:

### 1. Initiate Password Reset
```
POST /api/password-reset.php?action=initiate
Content-Type: application/json

{
    "identifier": "user@example.com or username",
    "method": "email" or "sms"
}

Response:
{
    "success": true,
    "message": "Code sent successfully!",
    "data": {
        "code": "CODE_SENT",
        "method": "email",
        "identifier_hint": "us***le@example.com"
    }
}
```

### 2. Verify OTP (SMS)
```
POST /api/password-reset.php?action=verify-otp
Content-Type: application/json

{
    "otp": "123456",
    "user_identifier": "user@example.com or username"
}

Response:
{
    "success": true,
    "message": "OTP verified successfully",
    "data": {
        "code": "OTP_VERIFIED",
        "reset_token": "session_token"
    }
}
```

### 3. Verify Token (Email)
```
POST /api/password-reset.php?action=verify-token
Content-Type: application/json

{
    "token": "token_from_email_link"
}

Response:
{
    "success": true,
    "message": "Token verified successfully",
    "data": {
        "code": "TOKEN_VERIFIED",
        "reset_token": "session_token"
    }
}
```

### 4. Reset Password
```
POST /api/password-reset.php?action=reset-password
Content-Type: application/json

{
    "new_password": "NewPassword@123",
    "confirm_password": "NewPassword@123"
}

Response:
{
    "success": true,
    "message": "Password reset successfully!",
    "data": {
        "code": "PASSWORD_RESET_SUCCESS"
    }
}
```

### 5. Resend OTP
```
POST /api/password-reset.php?action=resend-otp
Content-Type: application/json

{
    "user_identifier": "user@example.com or username"
}

Response:
{
    "success": true,
    "message": "New OTP sent successfully",
    "data": {
        "code": "OTP_RESENT",
        "identifier_hint": "***-***-1234"
    }
}
```

## Password Requirements

Passwords must meet ALL of the following criteria:

- ✓ Minimum 8 characters long
- ✓ At least one uppercase letter (A-Z)
- ✓ At least one lowercase letter (a-z)
- ✓ At least one number (0-9)
- ✓ At least one special character (!@#$%^&*)

Example valid password: `Secure@Pass123`

## Security Considerations

### Rate Limiting
The system implements rate limiting to prevent abuse:

- Max 3 password reset requests per hour per IP
- Max 10 OTP verification attempts per hour per IP
- Max 5 OTP resends per hour per IP
- OTP has max 5 incorrect attempts before requiring new request

### Tokens & OTPs
- Email tokens expire after 60 minutes of inactivity
- SMS OTPs expire after 10 minutes of inactivity
- Tokens are single-use (automatically invalidated)
- OTPs are single-use (automatically marked as verified)

### Audit Trail
All password reset activities are logged with:
- User ID
- Action taken
- Verification method used
- Identifier (email/phone) used
- IP address
- User agent
- Timestamp
- Success/failure status
- Additional details as JSON

**View logs:**
```sql
SELECT * FROM password_reset_logs ORDER BY created_at DESC LIMIT 50;
```

### Session Management
- Password reset requires verified session
- Session expires after 30 minutes (email) or 15 minutes (SMS)
- Cannot reuse same verification for multiple resets
- Upon successful password reset, all previous sessions are invalidated

## Testing & Debugging

### Check Email/SMS Logs
Development mode logs OTPs/tokens to PHP error log:

```
# View PHP error log
tail -f /var/log/php_errors.log

# Look for:
# SMS sent to +61412345678: 123456
# Email sent to user@example.com: Reset token: abc123def456... 
```

### Manual Testing
1. Go to: `http://localhost/TeamPagal_Barangay219/Barangay219/public/forgot-password.php`
2. Enter registered email or username
3. Select verification method
4. Check error log for OTP or email token
5. Complete verification
6. Set new password
7. Login with new credentials

### Check Database Records
```sql
-- View password reset tokens
SELECT * FROM password_reset_tokens ORDER BY created_at DESC;

-- View OTP records
SELECT * FROM password_reset_otp ORDER BY created_at DESC;

-- View rate limiting
SELECT * FROM password_reset_rate_limit ORDER BY created_at DESC;

-- View audit logs
SELECT * FROM password_reset_logs ORDER BY created_at DESC;
```

### Clear Expired Records
The system automatically cleans up expired tokens/OTPs when triggered, or you can manually run:

```php
require_once 'includes/password-reset.php';
cleanupExpiredPasswordResets();
```

## Troubleshooting

### "No phone number found for SMS verification"
- User account must be linked to a resident record
- Resident record must have a `contact_number` field populated
- Check: `SELECT * FROM residents WHERE id = ?;`

### "No email address found"
- User record must have an email address in `users.email` field
- Check: `SELECT email FROM users WHERE id = ?;`

### "Too many attempts"
- Rate limit exceeded - user must wait until the rate limit window expires
- Check current rate limits: `SELECT * FROM password_reset_rate_limit;`

### OTP not sending
- Check PHP error log for "SMS send error"
- Verify SMS provider credentials are configured
- Check `sendOTPViaSMS()` function implementation

### Email not sending
- Check PHP error log for "Email send error"
- Verify mail() function is enabled in php.ini
- Check `sendResetLinkViaEmail()` function implementation
- Look for emails in spam folder

### Session expired error
- User took too long to verify (> 30 min for email, 15 min for SMS)
- User must restart the password reset process
- This is a security feature

## Maintenance

### Weekly Tasks
1. Review password reset logs for suspicious activity
2. Monitor rate limit table for abuse patterns
3. Ensure Email/SMS services are functioning

### Monthly Tasks
1. Archive old password reset logs (older than 90 days)
2. Review and update password requirements if needed
3. Update rate limiting settings based on usage patterns

### Database Optimization
```sql
-- Add indexes if not already created
CREATE INDEX idx_pw_reset_expires ON password_reset_tokens(expires_at);
CREATE INDEX idx_pw_reset_otp_expires ON password_reset_otp(expires_at);
CREATE INDEX idx_pw_reset_logs_date ON password_reset_logs(created_at);

-- Optimize tables
OPTIMIZE TABLE password_reset_tokens;
OPTIMIZE TABLE password_reset_otp;
OPTIMIZE TABLE password_reset_rate_limit;
OPTIMIZE TABLE password_reset_logs;
```

## Integration with Existing Auth System

The password reset system integrates seamlessly with the existing authentication:

1. Uses same `users` table and authentication structure
2. Leverages existing database connection pool
3. Uses same session management
4. Compatible with existing permission system
5. Logs to same activity tracking system

## Support & Future Enhancements

### Potential Enhancements
- Two-factor authentication (2FA)
- Security questions backup verification
- Account recovery instructions
- Biometric password reset (fingerprint/face ID)
- Social login integration
- Custom email templates
- SMS templates with multiple languages

### Support Contacts
- System Administrator: [contact info]
- Email Support: noreply@ebarangay.local
- Technical Issues: Submit to issue tracker

---

**Last Updated:** February 23, 2026  
**Version:** 1.0.0  
**Status:** Production Ready
