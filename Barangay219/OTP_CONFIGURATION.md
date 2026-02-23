# OTP Configuration & Security Specification

## OTP Configuration

### Current Settings
```
Length:           4-6 digits (default 5)
Expiry:          5 minutes (300 seconds)
Max Attempts:    5 attempts
Resend Cooldown: 60 seconds between resends
Resend Max:      5 resends per hour
Rate Limiting:   10 OTP verifications per hour per user/IP
```

### Configuration Constants
File: `includes/password-reset.php` (Lines 17-24)

```php
define('OTP_LENGTH', 5);                    // Can adjust to 4, 5, or 6
define('OTP_EXPIRY_MINUTES', 5);           // Can adjust to 3-5 minutes
define('TOKEN_EXPIRY_MINUTES', 60);        // Email token: 60 minutes
define('MAX_OTP_ATTEMPTS', 5);             // Can adjust to 3-5 attempts
define('RATE_LIMIT_WINDOW_MINUTES', 60);   // 1 hour rate limit window
define('MAX_RESET_REQUESTS_PER_HOUR', 3);  // Reset requests per hour
define('MAX_OTP_VERIFICATIONS_PER_HOUR', 10); // OTP verifications per hour
define('MAX_RESEND_OTP_PER_HOUR', 5);      // OTP resends per hour
```

### Example Configurations

#### **Strict Security (Financial Apps)**
```php
define('OTP_LENGTH', 6);           // 6 digits
define('OTP_EXPIRY_MINUTES', 3);   // 3 minutes
define('MAX_OTP_ATTEMPTS', 3);     // 3 attempts
define('MAX_RESEND_OTP_PER_HOUR', 3); // 3 resends
```

#### **Balanced (Current - Recommended for Barangay)**
```php
define('OTP_LENGTH', 5);           // 5 digits
define('OTP_EXPIRY_MINUTES', 5);   // 5 minutes
define('MAX_OTP_ATTEMPTS', 5);     // 5 attempts
define('MAX_RESEND_OTP_PER_HOUR', 5); // 5 resends
```

#### **User-Friendly (E-Commerce)**
```php
define('OTP_LENGTH', 4);           // 4 digits
define('OTP_EXPIRY_MINUTES', 10);  // 10 minutes
define('MAX_OTP_ATTEMPTS', 7);     // 7 attempts
define('MAX_RESEND_OTP_PER_HOUR', 10); // 10 resends
```

---

## OTP Flow & Validation

### 1. OTP Generation
**Function:** `generateOTP($length = OTP_LENGTH)`
```php
function generateOTP($length = OTP_LENGTH) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}
```

**Logic:**
- Uses cryptographically secure `random_int()`
- Generates number from 0 to 10^length - 1
- Pads with leading zeros to exact length
- Example with length=5: can generate 00000-99999

**Security:**
✓ Cryptographically random
✓ No character sets that could be confused (0/O, 1/l)
✓ All digits 0-9 are equally likely
✓ Entropy: 5-digit = ~16.6 bits, 6-digit = ~19.9 bits

### 2. OTP Storage
**Table:** `password_reset_otp`
```sql
CREATE TABLE password_reset_otp (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  otp_code VARCHAR(6),           -- Stored as plain text (see security note)
  phone_number VARCHAR(20),      -- Actual phone number
  attempt_count INT DEFAULT 0,   -- Current attempt count
  max_attempts INT DEFAULT 5,    -- Maximum attempts allowed
  expires_at DATETIME,           -- Expiry timestamp
  is_verified TINYINT(1) DEFAULT 0,  -- Marked true after successful verification
  verified_at DATETIME NULL,     -- Timestamp of verification
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Security Note on Plain Text OTP:**
- OTP codes are NOT hashed (unlike passwords)
- Why: Cannot verify without knowing original value (no password hashing function)
- Mitigation: Stored in protected database, encrypted DB connection recommended
- Alternative: Hash OTP and store both (original + hash) - not implemented

### 3. OTP Verification Process

**Step 1: Retrieve Valid OTP**
```sql
SELECT * FROM password_reset_otp 
WHERE user_id = ? 
  AND is_verified = 0 
  AND expires_at > NOW() 
ORDER BY created_at DESC 
LIMIT 1
```

**Step 2: Check Max Attempts**
```php
if ($otp_record['attempt_count'] >= MAX_OTP_ATTEMPTS) {
    // Max attempts exceeded
    return ['success' => false, 'code' => 'MAX_ATTEMPTS_EXCEEDED'];
}
```

**Step 3: Constant-Time Comparison**
```php
if (!hash_equals($otp_record['otp_code'], $otp)) {
    // Wrong OTP - increment attempt count
    return ['success' => false, 'code' => 'INVALID_OTP'];
}
```

**Why `hash_equals()`?**
- Prevents timing attacks (comparing string lengths reveals info)
- Takes same time regardless of match position
- Secure comparison for authentication codes

**Step 4: Mark as Verified**
```php
UPDATE password_reset_otp 
SET is_verified = 1, verified_at = NOW() 
WHERE id = ?
```

---

## Rate Limiting Details

### Reset Request Rate Limiting
**Limit:** 3 attempts per hour per user/IP

**Implementation:**
```php
checkRateLimit(
    $user_id = null,           // Can be null for IP-only check
    $action = 'request',       // Type of action
    $max_attempts = 3,         // Max attempts
    $window_minutes = 60       // Sliding window
)
```

**Logic:**
- Sliding window not fixed window
- Window resets when request falls outside time range
- Both user ID and IP tracked
- IP used if user not authenticated

**Table:** `password_reset_rate_limit`
```sql
CREATE TABLE password_reset_rate_limit (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NULL,
  ip_address VARCHAR(45),        -- IPv4 or IPv6
  action VARCHAR(30),             -- 'request', 'otp_verify', etc.
  request_count INT DEFAULT 0,
  last_request DATETIME,
  window_start DATETIME,          -- Start of current rate limit window
  created_at TIMESTAMP
);
```

### OTP Verification Rate Limiting
**Limit:** 10 verifications per hour per IP
**Action:** `otp_verify`

### OTP Resend Rate Limiting
**Limit:** 60-second cooldown between resends (client-side also shown)
**Maximum:** 5 resends per hour
**Action:** `resend_otp`

---

## Security Features

### 1. **Single-Use Enforcement**
- OTP marked `is_verified = 1` after successful use
- Subsequent verifications with same OTP fail
- Next reset request generates new OTP

### 2. **Expiry Enforcement**
```php
WHERE expires_at > NOW()  // Only non-expired OTPs accepted
```

### 3. **Time-Based Expiration**
```php
$expires_at = date('Y-m-d H:i:s', time() + (OTP_EXPIRY_MINUTES * 60));
// Example: Set to 5 minutes (300 seconds) from now
```

### 4. **Attempt Limiting**
- Track `attempt_count` for each OTP
- Increment on each failed attempt
- Block if count >= `max_attempts`
- Cannot brute force all 100k combinations of 5-digit code

**Brute Force Analysis:**
- 5-digit OTP: 100,000 combinations
- 5 attempts allowed: 0.005% success rate
- 60-second resend cooldown limits attempts
- Database rate limiting (10/hour) prevents automated attacks

### 5. **Audit Logging**
Every OTP action logged:
```php
logPasswordResetActivity(
    $user_id,        // Who
    'otp_verify_failed',  // What action
    'sms',          // Method
    $phone,         // Identifier (masked)
    ['attempt' => $count]  // Details
);
```

**Logged Table:** `password_reset_logs`
```sql
CREATE TABLE password_reset_logs (
  id INT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(30),           -- 'otp_sent', 'otp_verify_failed', etc.
  method VARCHAR(10),           -- 'sms' or 'email'
  identifier VARCHAR(100),      -- Phone (masked) or email
  ip_address VARCHAR(45),       -- Requester IP
  user_agent TEXT,              -- Browser info
  success TINYINT(1),           -- Success or failure
  details JSON,                 -- Extra data (attempt count, etc.)
  created_at TIMESTAMP
);
```

### 6. **User Identification**
- OTP linked to `user_id` in database
- Cannot apply OTP from one account to another
- User must provide mobile number to retrieve correct OTP

### 7. **Masking in Logs**
```php
// Phone number displayed as: +63-9***-****4567
maskIdentifier('+639171234567', 'sms')  // Masks middle 5 digits
```

---

## Password Requirements After OTP Verification

**File:** `includes/password-reset.php` `validatePassword()` function

### 5 Requirements:
1. **Minimum 8 characters**
2. **At least 1 uppercase letter** (A-Z)
3. **At least 1 lowercase letter** (a-z)
4. **At least 1 number** (0-9)
5. **At least 1 special character** (!@#$%^&*)

### Password Update
```php
// Uses bcrypt hashing
$hashed = password_hash($new_password, PASSWORD_BCRYPT);

// Update in database
UPDATE users SET password = ? WHERE id = ?
```

### Validation Regex (from requirements above)
```
✓ /^.{8,}$/         - At least 8 characters
✓ /[A-Z]/           - Contains uppercase
✓ /[a-z]/           - Contains lowercase
✓ /\d/              - Contains digit
✓ /[!@#$%^&*]/      - Contains special char
```

---

## Error Messages & User Feedback

### OTP Verification Errors
```
Invalid OTP
```
Shows: `⚠️ Attempts remaining: 4`

```
Maximum OTP attempts exceeded. Please request a new OTP.
```
After 5 failures.

```
OTP expired. Please request a new one.
```
After 5 minutes.

```
Mobile number not verified
```
If mobile validation fails during request.

### Rate Limit Errors
```
Too many attempts. Please try again in 45 minutes.
```
Shows exact wait time.

```
Please wait 60 seconds before requesting another code.
```
Resend cooldown.

---

## Customization Guide

### Change OTP Length
**File:** `includes/password-reset.php` Line 17
```php
define('OTP_LENGTH', 6);  // Change from 5 to 6
```

**Where it affects:**
- OTP generation: `generateOTP()` uses this constant
- API validation: `handleVerifyOTP()` will accept 4-6 digits
- Frontend: UI adjusts automatically

### Change Expiry Time
**File:** `includes/password-reset.php` Line 18
```php
define('OTP_EXPIRY_MINUTES', 10);  // Change from 5 to 10
```

**Where it affects:**
- OTP database record: `expires_at` set to X minutes from now
- Verification: Rejects if `NOW() > expires_at`
- Frontend: Update message to match

### Change Max Attempts
**File:** `includes/password-reset.php` Line 20
```php
define('MAX_OTP_ATTEMPTS', 3);  // Change from 5 to 3
```

**Where it affects:**
- Verification: Rejects if `attempt_count >= MAX_OTP_ATTEMPTS`
- Error messages: Shows attempts remaining
- Frontend: Update display accordingly

---

## Monitoring & Maintenance

### Check Failed OTP Attempts
```sql
SELECT user_id, COUNT(*) as failed_attempts
FROM password_reset_logs
WHERE action = 'otp_verify_failed'
  AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY user_id
ORDER BY failed_attempts DESC;
```

### Clean Up Expired OTPs
```sql
DELETE FROM password_reset_otp 
WHERE expires_at < NOW();
```

**Recommended:** Run daily via cron job
```bash
0 2 * * * php /path/to/cleanup-expired-otp.php
```

### Audit Trail Report
```sql
SELECT 
  DATE(created_at) as date,
  action,
  COUNT(*) as count,
  SUM(success) as successful
FROM password_reset_logs
WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at), action
ORDER BY created_at DESC;
```

---

## Troubleshooting

### Problem: OTP Verification Always Fails
**Possible Causes:**
1. Database time not synced (check `NOW()` vs server time)
2. OTP code has leading/trailing spaces
3. OTP code contains non-digits

**Fix:**
```php
// In API endpoint
$otp = trim($data['otp']);
$otp = preg_replace('/[^\d]/', '', $otp);
```

### Problem: "Maximum Attempts Exceeded" Immediately
**Possible Causes:**
1. Database `attempt_count` not reset on new OTP request
2. `max_attempts` field set too low in database

**Fix:**
```sql
UPDATE password_reset_otp 
SET attempt_count = 0 
WHERE user_id = ? AND is_verified = 0;
```

### Problem: Resend Cooldown Not Working
**Possible Causes:**
1. Client-side timer showing wrong value
2. Server timestamp incorrect
3. Rate limit table not updated

**Fix:**
Check database for rate limit records:
```sql
SELECT * FROM password_reset_rate_limit
WHERE action = 'resend_otp'
  AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR);
```

---

## Performance Considerations

### Database Indexes
**Recommended indexes for faster queries:**
```sql
CREATE INDEX idx_prt_user ON password_reset_tokens(user_id);
CREATE INDEX idx_prt_expires ON password_reset_tokens(expires_at);
CREATE INDEX idx_pro_user ON password_reset_otp(user_id);
CREATE INDEX idx_pro_expires ON password_reset_otp(expires_at);
CREATE INDEX idx_prrl_action ON password_reset_rate_limit(action, ip_address);
```

### Query Optimization
- OTP verification query uses indexed columns
- Rate limit check uses sliding window (efficient)
- Cleanup queries can run off-peak hours

---

## Security Best Practices

1. **HTTPS Only:** All OTP transmission must be over HTTPS
2. **No Logging:** Don't log full OTP codes in application logs
3. **Rate Limiting:** Implement both server-side and client-side limits
4. **Audit Trail:** Keep detailed logs for security analysis
5. **User Notification:** Notify users of password resets
6. **Session Security:** Use secure session handling
7. **CSRF Protection:** Implement CSRF tokens if applicable
8. **Database Security:** Encrypt database connection
9. **Access Control:** Restrict OTP API endpoints
10. **Monitoring:** Alert on suspicious patterns (10+ failures from same IP)

---

## Constants Summary Table

| Setting | Value | Range | Purpose |
|---------|-------|-------|---------|
| OTP_LENGTH | 5 | 4-6 | Digits in OTP code |
| OTP_EXPIRY_MINUTES | 5 | 3-5 | Code validity period |
| MAX_OTP_ATTEMPTS | 5 | 3-5 | Guessing attempts |
| MAX_RESET_REQUESTS_PER_HOUR | 3 | 1-5 | Reset requests |
| MAX_OTP_VERIFICATIONS_PER_HOUR | 10 | 5-20 | Verification attempts |
| MAX_RESEND_OTP_PER_HOUR | 5 | 3-10 | Resend limit |
| RATE_LIMIT_WINDOW_MINUTES | 60 | 30-120 | Rate limit window |
| TOKEN_EXPIRY_MINUTES | 60 | 30-120 | Email token validity |

---

For implementation details, see `PHILIPPINE_MOBILE_RESET.md`
