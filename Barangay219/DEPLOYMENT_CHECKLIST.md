# Password Reset System - Deployment Checklist

## Pre-Deployment Verification

### ✅ Files Created
- [ ] `public/forgot-password.php` - Main forgot password page
- [ ] `public/verify-reset.php` - Verification page (Email & SMS)
- [ ] `public/reset-password.php` - Password reset form
- [ ] `api/password-reset.php` - API endpoints
- [ ] `includes/password-reset.php` - Core utilities
- [ ] `database/migrations/003_password_reset_system.sql` - Database schema
- [ ] `run-password-reset-migration.php` - Migration runner
- [ ] `test-password-reset.php` - Test script
- [ ] `FORGOT_PASSWORD_SETUP.md` - Full documentation
- [ ] `PASSWORD_RESET_QUICK_REF.md` - Quick reference
- [ ] `IMPLEMENTATION_SUMMARY.md` - Implementation summary
- [ ] `ARCHITECTURE.md` - System architecture
- [ ] `DEPLOYMENT_CHECKLIST.md` - This file

### ✅ Code Changes
- [ ] Modified `public/login.php` - Added "Forgot Password?" link

### ✅ Database Setup
- [ ] Run migration: `php run-password-reset-migration.php`
- [ ] Verify tables created:
  - [ ] `password_reset_tokens`
  - [ ] `password_reset_otp`
  - [ ] `password_reset_rate_limit`
  - [ ] `password_reset_logs`
- [ ] Verify user table extended:
  - [ ] `password_reset_request_id` column
  - [ ] `password_reset_request_method` column
  - [ ] `password_reset_request_expires` column

## Pre-Production Testing

### 🧪 Unit Tests

#### Email Method
- [ ] Enter valid email address
- [ ] Select "Email" verification method
- [ ] Submit and receive confirmation
- [ ] Check error log for token
- [ ] Navigate to verify page
- [ ] Paste token and verify
- [ ] Enter new password meeting all requirements
- [ ] Password reset succeeds
- [ ] Can login with new password

#### SMS Method
- [ ] Enter valid email/username
- [ ] Select "SMS" verification method
- [ ] Submit and receive confirmation
- [ ] Check error log for OTP (6 digits)
- [ ] Navigate to verify page
- [ ] Enter OTP digit by digit
- [ ] Verify succeeds
- [ ] Enter new password meeting all requirements
- [ ] Password reset succeeds
- [ ] Can login with new password

#### Edge Cases
- [ ] Delete token from database, try to verify (should fail)
- [ ] Use expired token, try to verify (should fail)
- [ ] Enter wrong OTP 5 times (should be locked)
- [ ] Request 4 password resets in 1 hour (4th should fail)
- [ ] Enter password without uppercase (should show requirement)
- [ ] Enter password without special char (should show requirement)
- [ ] Enter mismatched passwords (should show error)
- [ ] Use token twice (2nd should fail)
- [ ] Wait for session to expire (should redirect)

### 🔒 Security Tests

#### Rate Limiting
- [ ] Test 3 requests/hour limit (4th fails)
- [ ] Test no requests bypassing limit
- [ ] Test rate limit resets after window
- [ ] Check rate_limit table populated correctly

#### Audit Logging
- [ ] Successful reset logged
- [ ] Failed attempt logged
- [ ] Each log includes:
  - [ ] User ID
  - [ ] Action
  - [ ] Method (email/sms)
  - [ ] IP address
  - [ ] Timestamp
  - [ ] Success status

#### Password Strength
- [ ] Test all 5 requirements enforced
- [ ] Verify error messages for each failure
- [ ] Confirm client & server-side validation
- [ ] Test with special characters: !@#$%^&*()_+-=

### 🎨 UI/UX Tests

#### Forgot Password Page
- [ ] Page loads without errors
- [ ] Email button click selects email
- [ ] SMS button click selects SMS
- [ ] Form submits with all required fields
- [ ] Error messages display properly
- [ ] Back to login link works
- [ ] Page responsive on mobile

#### Verify Page
- [ ] Email view shows correct UI
- [ ] SMS view shows correct UI
- [ ] OTP input auto-focuses between digits
- [ ] Paste OTP functionality works
- [ ] Resend button disabled until timer expires
- [ ] Timer counts down to zero
- [ ] Resend sends new OTP
- [ ] Error messages clear on new attempt

#### Reset Password Page
- [ ] Password strength indicator updates
- [ ] All 5 requirements show status
- [ ] Requirements update as user types
- [ ] Password match validation works
- [ ] Submit disabled until all requirements met
- [ ] Error message shows password mismatch
- [ ] Success message displays
- [ ] Redirect to login works

### 🔗 Integration Tests
- [ ] Login page → forgot-password link works
- [ ] forgot-password → verify page → reset-password → login works
- [ ] All API endpoints return correct JSON
- [ ] Session variables set correctly
- [ ] Session expired handling works
- [ ] Multiple tabs/windows don't interfere

### 📱 Browser Testing
- [ ] Chrome latest
- [ ] Firefox latest
- [ ] Safari latest
- [ ] Edge latest
- [ ] Mobile browsers (iOS Safari, Chrome Mobile)
- [ ] Check console for JavaScript errors

## Production Deployment Steps

### Phase 1: Setup
```bash
# 1. Backup current database
mysqldump -u root barangay219_db > backup_$(date +%Y%m%d).sql

# 2. Run database migration
php run-password-reset-migration.php

# 3. Verify setup
php test-password-reset.php

# 4. Check file permissions
chmod 644 public/forgot-password.php public/verify-reset.php public/reset-password.php
chmod 644 api/password-reset.php
chmod 644 includes/password-reset.php
```

### Phase 2: Configuration
- [ ] Update `includes/password-reset.php`:
  - [ ] Configure email sending (set up actual mail service)
  - [ ] Configure SMS sending (integrate SMS provider)
  - [ ] Adjust rate limits if needed
  - [ ] Update BARANGAY_NAME in emails
  - [ ] Set newsletter reply-to email

### Phase 3: Testing in Production
- [ ] Test with real email address (check spam folder)
- [ ] Test with registered phone number
- [ ] Monitor error logs for any issues
- [ ] Check password_reset_logs table for activities
- [ ] Verify rate limiting works correctly

### Phase 4: User Communication
- [ ] Update help documentation
- [ ] Notify users about Forgot Password feature
- [ ] Create FAQ for common issues
- [ ] Train support staff

### Phase 5: Monitoring
- [ ] Check logs daily for first week
- [ ] Monitor password_reset_logs for abuse
- [ ] Review failed attempts
- [ ] Check rate limiting hits

## Post-Deployment

### Daily (First Week)
- [ ] Check password_reset_logs for unusual activity
- [ ] Monitor failed reset attempts
- [ ] Check for any error messages in logs
- [ ] Verify email/SMS delivery working

### Weekly
- [ ] Review password reset activity statistics
- [ ] Check for abuse patterns
- [ ] Verify database tables not bloated
- [ ] Test email/SMS functionality

### Monthly
- [ ] Run cleanup script manually:
  ```sql
  -- Clean old logs (older than 90 days)
  DELETE FROM password_reset_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
  
  -- Optimize tables
  OPTIMIZE TABLE password_reset_tokens;
  OPTIMIZE TABLE password_reset_otp;
  OPTIMIZE TABLE password_reset_rate_limit;
  OPTIMIZE TABLE password_reset_logs;
  ```
- [ ] Review and update documentation
- [ ] Test Forgot Password workflow again
- [ ] Check for any reported issues

## Rollback Plan

If critical issues arise, rollback to prior version:

```bash
# 1. Restore database backup
mysql -u root barangay219_db < backup_YYYYMMDD.sql

# 2. Remove new files
rm public/forgot-password.php
rm public/verify-reset.php
rm public/reset-password.php
rm api/password-reset.php
rm includes/password-reset.php
rm database/migrations/003_password_reset_system.sql
rm run-password-reset-migration.php
rm test-password-reset.php

# 3. Revert login.php changes
git checkout public/login.php

# 4. Notify users of temporary issue
# 5. Investigate root cause
# 6. Fix and redeploy when ready
```

## Monitoring Queries

Keep these queries handy for monitoring:

```sql
-- Recent password reset activities
SELECT * FROM password_reset_logs 
ORDER BY created_at DESC LIMIT 20;

-- Failed attempts
SELECT user_id, COUNT(*) as failures, MAX(created_at) as last_attempt
FROM password_reset_logs 
WHERE success = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY user_id;

-- Active rate limits
SELECT * FROM password_reset_rate_limit 
WHERE window_start > DATE_SUB(NOW(), INTERVAL 1 HOUR);

-- Pending tokens (not yet used)
SELECT user_id, method, identifier, expires_at FROM password_reset_tokens 
WHERE is_used = 0 AND expires_at > NOW();

-- Expired tokens (for cleanup)
SELECT COUNT(*) FROM password_reset_tokens 
WHERE expires_at < NOW();

-- Tokens older than 7 days
SELECT COUNT(*) FROM password_reset_logs 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Most common failure reasons
SELECT action, COUNT(*) 
FROM password_reset_logs 
WHERE success = 0 
GROUP BY action 
ORDER BY COUNT(*) DESC;
```

## Support Resources

### User-Facing Documentation
- Forgot Password link on login page
- Help text on each page
- Error messages guide users
- Success messages provide feedback

### Admin Documentation
- `FORGOT_PASSWORD_SETUP.md` - Technical setup guide
- `PASSWORD_RESET_QUICK_REF.md` - Developer reference
- `ARCHITECTURE.md` - System design
- `IMPLEMENTATION_SUMMARY.md` - Feature overview

### Support Scripts
- `test-password-reset.php` - Verify system health
- `run-password-reset-migration.php` - Run migrations
- This checklist - Deployment verification

## Troubleshooting Guide

### Problem: Emails Not Sending
**Solution:**
1. Check mail configuration in `includes/password-reset.php`
2. Verify PHP mail() is enabled
3. Check error log for mail errors
4. Test with simple mail() in separate script
5. Use external mail service (Sendgrid, etc.)

### Problem: SMS Not Sending
**Solution:**
1. Check SMS provider credentials
2. Verify phone number format
3. Check provider account balance
4. Test with provider's test API
5. Check error log for specific errors

### Problem: Rate Limit Too Strict
**Solution:**
1. Edit constants in `includes/password-reset.php`
2. Increase `MAX_RESET_REQUESTS_PER_HOUR`
3. Redeploy
4. Test new limits

### Problem: Tokens Expiring Too Fast
**Solution:**
1. Edit in `includes/password-reset.php`
2. Increase `TOKEN_EXPIRY_MINUTES` or `OTP_EXPIRY_MINUTES`
3. Redeploy and test

## Sign-Off

- [ ] Manager approval
- [ ] Security review passed
- [ ] Performance testing passed
- [ ] User acceptance testing passed
- [ ] Production deployment authorized

**Deployed By:** ________________  
**Date:** ________________  
**Version:** 1.0.0  
**Environment:** Production  

---

**Status:** ✅ Ready for Production Deployment

