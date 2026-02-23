# Philippine Mobile Number Password Reset - Implementation Guide

## Overview
This document describes the enhanced password reset system for the E-Barangay Information Management System with comprehensive Philippine mobile number support, automatic format conversion, resident database validation, and secure OTP management.

---

## Features Implemented

### 1. **Philippine Mobile Number Format Support**
- Accepts both formats:
  - `09XXXXXXXXX` (local format)
  - `+639XXXXXXXXX` (international format)
- Auto-converts `09` to `+639` automatically
- Displays formatted as `+63-9XXXXXXXXX` for user clarity
- Validates total length: exactly 11 digits (country code + 9 digits)

**Example Conversions:**
- User enters: `09171234567` → Processed as: `+639171234567`
- User enters: `9171234567` → Processed as: `+639171234567`
- User enters: `+63-917-123-4567` → Processed as: `+639171234567`

### 2. **Mobile Number Validation Against Database**
The system validates that the mobile number:
- Exists in the residents table (`contact_number` field)
- Is linked to an active user account (status = 'active')
- Has an approved resident record (record_status = 'active')

**Validation Function:** `validateMobileNumberInDatabase($phone)`
- Returns: valid status, user ID, resident ID, and descriptive message
- Prevents unregistered or inactive accounts from using SMS reset

### 3. **OTP Configuration**
- **Length:** 5 digits (configurable 4-6 digits for flexibility)
- **Expiry:** 5 minutes (can be adjusted 3-5 minutes range)
- **Max Attempts:** 5 (configurable 3-5 attempts)
- **Resend Rate Limiting:** Maximum 5 resends per hour
- **Status Code:** 4-6 digits validation on submission

### 4. **Security Features**

#### Rate Limiting
- **Password Reset Requests:** 3 attempts per hour per IP/user
- **OTP Verifications:** 10 attempts per hour
- **OTP Resends:** 5 per hour with 60-second cooldown between resends
- Clear error messages with remaining time if rate limited

#### Audit Logging
All password reset activities logged including:
- Action type (request_initiated, otp_sent, otp_verified, etc.)
- Method (email or sms)
- Mobile number (masked in logs)
- IP address
- User agent
- Result status (success/failure)
- Timestamp

#### Single-Use Enforcement
- OTP codes marked as `is_verified` after successful verification
- Tokens marked as `is_used` after password reset
- Expired codes automatically cleaned up (cron job recommended)

### 5. **User Experience Enhancements**

#### Mobile Number Input Field
- **Placeholder:** `09XX-XXXXXX or +63-9XX-XXXX`
- **Real-time Counter:** Shows `X/9 digits` while typing
- **Format Validation:** Red (incomplete) → Green ✓ (valid)
- **Automatic Formatting:** Converts 09 to +63 automatically
- **Masked Display:** After submission, shows as `+63-9***-****4567`

#### OTP Input Interface
- **Flexible Input:** Accepts 4-6 digits (fields auto-clear for flexibility)
- **Countdown Timer:** Real-time character counter
- **Paste Support:** Can paste full OTP code (only digits extracted)
- **Auto-focus:** Moves focus to next field automatically
- **Attempt Tracking:** Displays remaining attempts with visual indicators
  - Green: Multiple attempts remaining
  - Orange: 2-3 attempts remaining  
  - Red: 1 attempt remaining
  - Red with ❌: No attempts remaining

#### Success Messages
- Shows masked phone number confirmation
- Displays expiry time (5 minutes for SMS)
- Shows attempt limit (5 attempts)
- Clear instructions on next steps

---

## Database Schema Changes

### New Tables

#### `password_reset_tokens` (Email-based)
```sql
CREATE TABLE password_reset_tokens (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  token VARCHAR(64) UNIQUE NOT NULL,
  method VARCHAR(10),
  identifier VARCHAR(100),
  expires_at DATETIME,
  is_used TINYINT(1) DEFAULT 0,
  used_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### `password_reset_otp` (SMS-based)
```sql
CREATE TABLE password_reset_otp (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  otp_code VARCHAR(6),
  phone_number VARCHAR(20),
  attempt_count INT DEFAULT 0,
  max_attempts INT DEFAULT 5,
  expires_at DATETIME,
  is_verified TINYINT(1) DEFAULT 0,
  verified_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### `password_reset_rate_limit`
```sql
CREATE TABLE password_reset_rate_limit (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NULL,
  ip_address VARCHAR(45),
  action VARCHAR(30),
  request_count INT DEFAULT 0,
  last_request DATETIME,
  window_start DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### `password_reset_logs` (Audit Trail)
```sql
CREATE TABLE password_reset_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NULL,
  action VARCHAR(30),
  method VARCHAR(10),
  identifier VARCHAR(100),
  ip_address VARCHAR(45),
  user_agent TEXT,
  success TINYINT(1),
  details JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## API Endpoints

### 1. **Initiate Password Reset**
- **Endpoint:** `POST /api/password-reset.php?action=initiate`
- **Request:**
  ```json
  {
    "identifier": "09171234567",  // or +639171234567
    "method": "sms"                // or "email"
  }
  ```
- **Response (Success):**
  ```json
  {
    "success": true,
    "message": "Verification code sent successfully",
    "data": {
      "code": "CODE_SENT",
      "method": "sms",
      "identifier_hint": "+63-9***-****4567"
    }
  }
  ```
- **Validation Steps:**
  1. Mobile format normalization (`normalizePhilippineMobileNumber()`)
  2. Database validation (`validateMobileNumberInDatabase()`)
  3. Rate limit check
  4. OTP generation and storage
  5. SMS delivery (if configured)

### 2. **Verify OTP**
- **Endpoint:** `POST /api/password-reset.php?action=verify-otp`
- **Request:**
  ```json
  {
    "otp": "12345",                // 4-6 digits
    "user_identifier": "09171234567"
  }
  ```
- **Response (Success):**
  ```json
  {
    "success": true,
    "message": "OTP verified successfully",
    "data": {
      "code": "OTP_VERIFIED",
      "reset_token": "session_token"
    }
  }
  ```
- **Response (Failure):**
  ```json
  {
    "success": false,
    "message": "Invalid OTP",
    "data": {
      "code": "INVALID_OTP",
      "attempts_remaining": 2
    }
  }
  ```

### 3. **Resend OTP**
- **Endpoint:** `POST /api/password-reset.php?action=resend-otp`
- **Rate Limit:** 60-second cooldown between resends, max 5 per hour
- **Request:**
  ```json
  {
    "user_identifier": "09171234567"
  }
  ```
- **Response:**
  ```json
  {
    "success": true,
    "message": "New OTP sent successfully",
    "data": {
      "code": "OTP_RESENT",
      "identifier_hint": "+63-9***-****4567"
    }
  }
  ```

### 4. **Reset Password**
- **Endpoint:** `POST /api/password-reset.php?action=reset-password`
- **Request:**
  ```json
  {
    "new_password": "SecurePass123!",
    "confirm_password": "SecurePass123!"
  }
  ```
- **Password Requirements:**
  - Minimum 8 characters
  - At least 1 uppercase letter
  - At least 1 lowercase letter
  - At least 1 number
  - At least 1 special character

---

## Core Functions Added

### 1. **normalizePhilippineMobileNumber($phone)**
Converts various Philippine mobile formats to standard `+639XXXXXXXXX`

**Logic:**
```
Input: "09171234567" or "+639171234567" → Output: "+639171234567"
Input: "0917-123-4567" → Output: "+639171234567"
Input: "9171234567" → Output: "+639171234567"
Invalid inputs return false
```

### 2. **validateMobileNumberInDatabase($phone)**
Validates mobile number exists in resident database with active status

**Checks:**
- Mobile number matches `residents.contact_number`
- Linked user is active (`users.status = 'active'`)
- Resident record is approved (`residents.record_status = 'active'`)

**Returns:**
```php
[
  'valid' => bool,
  'user_id' => int|null,
  'resident_id' => int|null,
  'message' => string
]
```

### 3. **maskPhoneNumber($phone)**
Masks sensitive phone data for display: `+63-9***-****4567`

---

## User Flow

### SMS Reset Flow
1. **Step 1: Method Selection**
   - User clicks "SMS" button
   - Input field appears: "09XX-XXXXXX or +63-9XX-XXXX"
   - User enters mobile number

2. **Step 2: Validation & OTP Sending**
   - Format auto-converts `09` to `+63`
   - System validates against resident database
   - OTP generated and sent via SMS (simulated in logs)
   - Success message shows masked number: `+63-9***-****4567`
   - Message states: "Code expires in 5 minutes, 5 attempts allowed"

3. **Step 3: OTP Entry**
   - User receives 5-digit OTP on their phone
   - Enters digits 1-by-1 or pastes full code
   - System shows "X attempts remaining" indicator
   - Color changes: Green → Orange → Red as attempts decrease

4. **Step 4: Password Reset**
   - After OTP verification, user enters new password
   - Password strength indicator shows 5 requirements:
     - ✓ 8+ characters
     - ✓ 1 uppercase letter
     - ✓ 1 lowercase letter
     - ✓ 1 number
     - ✓ 1 special character
   - Confirmation required before submission

5. **Step 5: Completion**
   - Success message with redirect timer
   - User returns to login page

### Email Reset Flow
1. **Step 1: Method Selection**
   - User clicks "Email" button
   - Input field appears: "Enter your registered email address"

2. **Step 2: Validation & Token Sending**
   - Email validated
   - Reset token generated (32 bytes, hex-encoded)
   - Email sent with reset link
   - Success message shows masked email

3. **Step 3: Token Verification**
   - User clicks link in email or pastes token
   - Token validated against database
   - Expiry checked (60 minutes)

4. **Step 4: Password Reset**
   - Same as SMS flow (Steps 4-5)

---

## Error Handling

### Validation Errors
```
❌ Invalid Philippine mobile number format. Use 09XX-XXXXXX or +63-9XX-XXXX.
❌ Mobile number not found in resident database
❌ Your resident record is not approved yet. Please contact the barangay office.
```

### Rate Limiting
```
Rate Limited: Too many attempts. Please try again in X minutes.
SMS Resend: Please wait 60 seconds before requesting another code.
```

### OTP Errors
```
Invalid OTP (attempts remain)
⚠️ Attempts remaining: 2
❌ Maximum OTP attempts exceeded. Please request a new OTP.
❌ OTP expired. Please request a new one.
```

---

## Configuration Constants

Located in `includes/password-reset.php`:
```php
define('OTP_LENGTH', 5);                    // 5-digit OTP
define('OTP_EXPIRY_MINUTES', 5);           // Expires in 5 minutes
define('TOKEN_EXPIRY_MINUTES', 60);        // Email token expires in 60 minutes
define('MAX_OTP_ATTEMPTS', 5);             // 5 attempts allowed
define('MAX_RESET_REQUESTS_PER_HOUR', 3);  // Rate limit: 3 requests/hour
define('MAX_OTP_VERIFICATIONS_PER_HOUR', 10);
define('MAX_RESEND_OTP_PER_HOUR', 5);      // Max 5 resends/hour
```

---

## Testing Checklist

### Mobile Number Formatting
- [ ] Enter `09171234567` → converts to `+639171234567`
- [ ] Enter `+63-917-123-4567` → normalizes correctly
- [ ] Enter `9171234567` → adds country code
- [ ] Invalid formats rejected with clear message

### Database Validation
- [ ] Mobile number found in resident table
- [ ] Links to active user account
- [ ] Resident record has 'active' status
- [ ] Unregistered number shows error

### OTP Management
- [ ] 5-digit OTP generated correctly
- [ ] OTP expires after 5 minutes
- [ ] OTP attempts limited to 5
- [ ] Attempt count displays correctly (5 → 4 → 3 → 2 → 1)
- [ ] Max attempts triggers error

### Rate Limiting
- [ ] 3 reset requests allowed per hour per IP
- [ ] Rate limit message shows accurate reset time
- [ ] OTP resend cooldown: 60 seconds
- [ ] Resend max: 5 per hour

### User Masking
- [ ] Phone number shown as `+63-9***-****4567` after submission
- [ ] Email masked correctly (first 3 chars + middle ***: ...)
- [ ] Masked display in success messages
- [ ] Masked display in audit logs

### Input Validation
- [ ] 4-6 digit OTP acceptance
- [ ] Auto-paste OTP from clipboard
- [ ] Leading zeros handled correctly
- [ ] Special characters (-, +, space) handled

---

## SMS Provider Integration (Future)

To enable actual SMS delivery, update `sendOTPViaSMS()` function:

```php
function sendOTPViaSMS($phone_number, $otp) {
    // Example with Twilio
    $twilio = new ClientTwilio(TWILIO_SID, TWILIO_TOKEN);
    
    try {
        $message = $twilio->messages->create($phone_number, [
            'from' => TWILIO_PHONE,
            'body' => "Your verification code is: $otp (expires in 5 minutes)"
        ]);
        
        return ['success' => true, 'message_id' => $message->sid];
    } catch (Exception $e) {
        error_log("SMS send error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
```

Currently, OTP codes are logged to `error_log()` for development/testing.

---

## Security Considerations

1. **Timing Attacks:** Uses `hash_equals()` for OTP comparison
2. **SQL Injection:** All queries use parameterized PDO statements
3. **Rate Limiting:** IP and user-based rate limiting
4. **Session Security:** Session tokens stored in `$_SESSION`
5. **Audit Trail:** All activities logged with IP and user agent
6. **Token Uniqueness:** Database constraints on tokens
7. **HTTPS Required:** All OTP/token transfer should be over HTTPS
8. **Password Hashing:** Uses `password_hash()` with bcrypt

---

## Deployment Checklist

- [ ] Run database migration: `php run-password-reset-migration.php`
- [ ] Verify all 4 new tables created successfully
- [ ] Update SMS provider credentials (if using real SMS)
- [ ] Configure email provider for email resets
- [ ] Test with valid resident phone numbers
- [ ] Test rate limiting
- [ ] Verify audit logs are created
- [ ] Check mobile number masking in logs
- [ ] Set up cron job for expired token cleanup
- [ ] Configure HTTPS for production
- [ ] Update login page with "Forgot Password?" link
- [ ] Test complete SMS flow end-to-end
- [ ] Test complete Email flow end-to-end

---

## Troubleshooting

### OTP Not Receiving
1. Check `password_reset_logs` table for delivery status
2. Verify `sendOTPViaSMS()` is configured with SMS provider
3. Check resident database has valid `contact_number`
4. Verify phone number format is correct

### Mobile Number Not Found
1. Check resident table for `contact_number` field
2. Verify mobile number matches exactly (case-insensitive)
3. Check resident `record_status` is 'active'
4. Check linked user `status` is 'active'

### Rate Limit Issues
1. Check IP address in `password_reset_rate_limit`
2. Verify time window calculations
3. Check database time sync

---

## Support Contact
For issues or questions about the password reset system, contact the barangay IT support team.
