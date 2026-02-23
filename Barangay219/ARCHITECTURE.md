# Password Reset System - Architecture & Flow

## System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     E-BARANGAY LOGIN PAGE                        │
│                          login.php                               │
│                  [Forgot Password?] Link                         │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│              FORGOT PASSWORD PAGE (Step 1)                       │
│                   forgot-password.php                            │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │ Enter: Email or Username                                │    │
│  │ Choose: [📧 Email]  [📱 SMS]                           │    │
│  │ Submit: "Send Code"                                     │    │
│  └────────┬──────────────────────────┬─────────────────────┘    │
└───────────┼──────────────────────────┼──────────────────────────┘
            │ Email Method             │ SMS Method
            │                          │
            ▼                          ▼
   ┌────────────────────┐   ┌──────────────────────┐
   │   API ENDPOINT     │   │    API ENDPOINT      │
   │   action=initiate  │   │    action=initiate   │
   │                    │   │                      │
   │ Email: Send Token  │   │ SMS: Send OTP        │
   └─────────┬──────────┘   └──────────┬───────────┘
             │                         │
             ├─→ password_reset_tokens └─→ password_reset_otp
             │   (Table)                   (Table)
             │                         
             ▼                         ▼
      Check logs for           Check logs for
      Email Token              OTP Code (6 digits)
             │                         │
             └────────┬────────────────┘
                      ▼
      ┌─────────────────────────────────────┐
      │  VERIFY PAGE (Step 2)               │
      │   verify-reset.php                  │
      │  ┌──────────────────────────────┐  │
      │  │ Email: Paste Code or         │  │
      │  │ Auto-verify from email link  │  │
      │  │                              │  │
      │  │ SMS: Enter 6 Digits          │  │
      │  │ [_][_][_][_][_][_]           │  │
      │  │ [Resend OTP] (60s cooldown)  │  │
      │  └──────────┬───────────────────┘  │
      └─────────────┼──────────────────────┘
                    │
         ┌──────────┴──────────────┬──────────────┐
         │                         │              │
      (Email)                  (SMS)          Rate Limit
         │                      │            Check
         ▼                      ▼              │
    verify-token          verify-otp          │
    API endpoint           API endpoint        │
         │                      │              │
         └──────────┬───────────┘              │
                    │ password_reset_rate_limit
                    ▼ table
      ┌─────────────────────────────────────────┐
      │    RESET PASSWORD PAGE (Step 3)         │
      │        reset-password.php               │
      │  ┌───────────────────────────────────┐  │
      │  │ New Password:     [__________]    │  │
      │  │ Strength: ████ Strong             │  │
      │  │ ✓ 8+ chars                        │  │
      │  │ ✓ Uppercase                       │  │
      │  │ ✓ Lowercase                       │  │
      │  │ ✓ Number                          │  │
      │  │ ✓ Special char                    │  │
      │  │                                   │  │
      │  │ Confirm Password: [__________]    │  │
      │  │ [Reset Password]                  │  │
      │  └───────────┬──────────────────────┘  │
      └──────────────┼─────────────────────────┘
                     │
                     ▼
            ┌────────────────────┐
            │   API Endpoint     │
            │ action=reset-       │
            │   password         │
            │                    │
            │ validatePassword() │
            │ resetPassword()    │
            └─────────┬──────────┘
                      │
      ┌───────────────┴──────────────┬──────────────┐
      │                              │              │
      ▼                              ▼              ▼
  Users Table              password_reset_        Audit Logs
  (password updated)         tokens (marked       (action=
                            is_used=1)            password_reset)
      │                              │              │
      └────────────┬─────────────────┴──────────────┘
                   │
                   ▼
         ┌──────────────────────┐
         │ SUCCESS MESSAGE      │
         │ Redirect to Login    │
         │ ✅ Password Reset!   │
         └────────────┬─────────┘
                      │
                      ▼
         ┌──────────────────────┐
         │   LOGIN PAGE         │
         │  New Password Works! │
         └──────────────────────┘
```

## Database Schema Relationships

```
users
├── id (PK)
├── username
├── password (hashed)
├── email
├── status
└── [password_reset_request_*] (Optional tracking)
    │
    ├─→ password_reset_tokens
    │   ├── id (PK)
    │   ├── user_id (FK) ──→ users.id
    │   ├── token (unique)
    │   ├── method = 'email'
    │   ├── identifier (email)
    │   ├── is_used
    │   ├── expires_at
    │   └── created_at
    │
    ├─→ password_reset_otp
    │   ├── id (PK)
    │   ├── user_id (FK) ──→ users.id
    │   ├── otp_code
    │   ├── phone_number
    │   ├── is_verified
    │   ├── attempt_count
    │   ├── expires_at
    │   └── created_at
    │
    ├─→ password_reset_rate_limit
    │   ├── id (PK)
    │   ├── user_id (FK) ──→ users.id
    │   ├── ip_address
    │   ├── action ('request', 'otp_verify', etc)
    │   ├── request_count
    │   ├── window_start
    │   └── created_at
    │
    └─→ password_reset_logs
        ├── id (PK)
        ├── user_id (FK) ──→ users.id
        ├── action
        ├── method ('email' / 'sms')
        ├── identifier
        ├── ip_address
        ├── user_agent
        ├── success (bool)
        ├── details (JSON)
        └── created_at
```

## Request/Response Flow

### Email Method Flow

```
CLIENT REQUEST:
    POST /api/password-reset.php?action=initiate
    {
        "identifier": "user@example.com",
        "method": "email"
    }

SERVER PROCESSING:
    1. Sanitize input
    2. Check rate limit (checkRateLimit)
    3. Find user by email/username
    4. Generate token (generateResetToken)
    5. Store in password_reset_tokens table
    6. Send email with reset link (sendResetLinkViaEmail)
    7. Log activity (logPasswordResetActivity)

SERVER RESPONSE:
    {
        "success": true,
        "message": "Verification code sent successfully",
        "data": {
            "code": "CODE_SENT",
            "method": "email",
            "identifier_hint": "us***le@example.com"
        }
    }

USER VERIFICATION:
    1. Clicks email link OR
    2. Pastes code on verify-reset.php
    
    POST /api/password-reset.php?action=verify-token
    {
        "token": "token_from_email_link"
    }

SERVER:
    1. Find token in password_reset_tokens
    2. Check not expired
    3. Check not already used
    4. Set session: password_reset_verified = true
    5. Log verification

PASSWORD RESET:
    POST /api/password-reset.php?action=reset-password
    {
        "new_password": "NewPass@123",
        "confirm_password": "NewPass@123"
    }

SERVER:
    1. Verify session has password_reset_verified
    2. Validate password strength
    3. Hash new password
    4. Update users.password
    5. Mark token as is_used = 1
    6. Clear reset session variables
    7. Log successful password reset

RESPONSE:
    {
        "success": true,
        "message": "Password reset successfully!",
        "data": {
            "code": "PASSWORD_RESET_SUCCESS"
        }
    }
```

### SMS Method Flow

```
CLIENT REQUEST:
    POST /api/password-reset.php?action=initiate
    {
        "identifier": "user@example.com",
        "method": "sms"
    }

SERVER PROCESSING:
    1. Check rate limit
    2. Find user
    3. Get phone number from residents table
    4. Generate OTP (generateOTP)
    5. Store in password_reset_otp table
    6. Send OTP via SMS (sendOTPViaSMS)
    7. Log activity

SERVER RESPONSE:
    {
        "success": true,
        "message": "OTP sent successfully",
        "data": {
            "code": "CODE_SENT",
            "method": "sms",
            "identifier_hint": "***-***-5678"
        }
    }

USER VERIFICATION:
    POST /api/password-reset.php?action=verify-otp
    {
        "otp": "123456",
        "user_identifier": "user@example.com"
    }

SERVER:
    1. Check rate limit (OTP verification limit)
    2. Find OTP record
    3. Check not expired
    4. Check not verified yet
    5. Validate OTP (constant-time comparison)
    6. Check attempt count < max_attempts
    7. If correct: mark is_verified = 1
    8. If wrong: increment attempt_count
    9. Set session: password_reset_verified = true
    10. Log verification result

PASSWORD RESET:
    [Same as Email method]
```

## Security Controls Flow

```
┌─────────────────────────────────────────┐
│   SECURITY CONTROLS                      │
└─────────────────────────────────────────┘

RATE LIMITING:
  ├─ Check at initialization: 3 reqs/hour
  ├─ Check at OTP verify: 10 attempts/hour
  ├─ Check at OTP resend: 5 resends/hour
  ├─ Sliding window based on ip_address
  └─ Stored in: password_reset_rate_limit

TIME LIMITS:
  ├─ Email token: 60 minutes
  ├─ SMS OTP: 10 minutes
  ├─ Reset session: 15-30 minutes
  └─ Checked against: expires_at column

SINGLE USE:
  ├─ Tokens: is_used flag
  ├─ OTPs: is_verified flag
  ├─ Prevents replay attacks
  └─ Enforced at database level

PASSWORD VALIDATION:
  ├─ Minimum 8 characters
  ├─ Uppercase + Lowercase + Number + Special
  ├─ Checked client-side (UX)
  ├─ Enforced server-side (security)
  └─ Real-time feedback on all requirements

ATTEMPT LIMITING:
  ├─ Max 5 wrong OTP attempts
  ├─ After 5: must request new OTP
  ├─ Tracked in: attempt_count column
  └─ Prevents brute force attacks

AUDIT LOGGING:
  ├─ Every action logged
  ├─ Includes: user_id, ip, user_agent
  ├─ Success/failure tracked
  ├─ Details stored as JSON
  └─ Stored in: password_reset_logs

SESSION VALIDATION:
  ├─ Checked at each step
  ├─ Must have: password_reset_verified = true
  ├─ Must not be expired
  ├─ Invalidated after successful reset
  └─ Prevents unauthorized access
```

## File Dependencies

```
LOGIN PAGE
└─→ forgot-password.php
    ├─→ config/constants.php
    ├─→ includes/auth-check.php
    └─→ JavaScript (inline)

FORGOT PASSWORD
└─→ API: api/password-reset.php
    ├─→ config/database.php
    ├─→ config/constants.php
    ├─→ includes/auth-check.php
    └─→ includes/password-reset.php
        ├─→ config/database.php
        ├─→ config/constants.php
        └─→ includes/auth-check.php

VERIFY PAGE
├─→ verify-reset.php
└─→ API: api/password-reset.php
    └─→ password-reset.php

RESET PAGE
├─→ reset-password.php
└─→ API: api/password-reset.php
    └─→ password-reset.php
```

## State Transitions

```
INITIAL STATE
├─ No reset in progress
├─ Session: No password_reset_* variables
└─ Database: No pending tokens/OTPs

    │
    ▼ (User clicks "Forgot Password")

REQUEST INITIATED
├─ Token/OTP generated
├─ Stored in database
├─ Rate limit checked
└─ Message sent

    │
    ├─ (No action) ──→ Token/OTP expires (10-60 min)
    │
    ▼ (User verifies)

VERIFIED STATE
├─ Session: password_reset_verified = true
├─ Session: password_reset_user_id = user_id
├─ Session: password_reset_method = email/sms
├─ Session: password_reset_expires = now + 15-30 min
└─ Can proceed to password reset

    │
    ├─ (Session expires) ──→ Back to INITIAL
    │
    ▼ (User resets password)

PASSWORD RESET
├─ New password hashed
├─ users.password updated
├─ Token/OTP marked as used
├─ Session cleared
└─ User logged out (for security)

    │
    ▼

RESET COMPLETE
├─ User redirected to login
├─ New password works
├─ All previous sessions invalidated
└─ Activity logged with timestamp

    │
    ▼ (User logs in with new password)

BACK TO NORMAL
└─ Authenticated session started
```

## Error Handling Flow

```
USER ACTION
    │
    ▼
VALIDATION
├─ Input sanitization
├─ Format checking
└─ Rate limit checking
    │
    ├─ FAIL ──→ Return error response
    │          └─→ Log failed attempt
    │
    ├─ SUCCESS
    │
    ▼
BUSINESS LOGIC
├─ Database operations
├─ External calls (SMS/Email)
└─ Session management
    │
    ├─ ERROR ──→ Rollback if needed
    │         └─→ Log error with stack trace
    │         └─→ Return generic error message
    │
    ├─ SUCCESS
    │
    ▼
RESPONSE
├─ Success message
├─ Data as needed
└─ HTTP status code

LOGGING
├─ All attempts tracked
├─ Success and failure logged
├─ Security events flagged
└─ Can audit any user's activity
```

---

**Version:** 1.0  
**Updated:** February 23, 2026  
**System:** E-Barangay Password Reset
