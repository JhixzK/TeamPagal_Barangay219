<?php
/**
 * E-Barangay Information Management System
 * Password Reset Utilities and Helper Functions
 */

// Prevent direct access
if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/auth-check.php';

// Password Reset Configuration
define('OTP_LENGTH', 6);  // 6-digit OTP (required)
define('OTP_EXPIRY_MINUTES', 5);  // 3-5 minutes expiry
define('TOKEN_EXPIRY_MINUTES', 60);
define('MAX_OTP_ATTEMPTS', 5);  // 3-5 attempts allowed
define('RATE_LIMIT_WINDOW_MINUTES', 60);
define('MAX_RESET_REQUESTS_PER_HOUR', 3);
define('MAX_OTP_VERIFICATIONS_PER_HOUR', 10);
define('MAX_RESEND_OTP_PER_HOUR', 5);

/**
 * Convert Philippine mobile number formats to standard +639XXXXXXXXX
 *
 * @param string $phone Phone number in various formats
 * @return string|false Standardized phone number or false if invalid
 */
function normalizePhilippineMobileNumber($phone) {
    // Remove all non-numeric characters except +
    $phone = preg_replace('/[^\d+]/', '', trim($phone));
    
    // Remove leading zeros if present and add country code
    if (preg_match('/^0/', $phone)) {
        $phone = '63' . substr($phone, 1);
    }
    
    // Remove + if present
    $phone = str_replace('+', '', $phone);
    
    // Must be exactly 11 digits starting with 639
    if (preg_match('/^639\d{8}$/', $phone)) {
        return '+' . $phone;
    }
    
    return false;
}

/**
 * Validate that mobile number exists in resident database
 *
 * @param string $phone Normalized phone number (+639XXXXXXXXX)
 * @return array ['valid' => bool, 'user_id' => int|null, 'resident_id' => int|null, 'message' => string]
 */
function validateMobileNumberInDatabase($phone) {
    try {
        $db = Database::getInstance();
        
        // Search in residents table by contact_number or email
        // First check if it's linked to an active user account
        $sql = "
            SELECT u.id as user_id, r.id as resident_id, r.record_status, u.status
            FROM users u
            LEFT JOIN residents r ON u.resident_id = r.id
            WHERE r.contact_number = ? AND u.status = 'active' AND r.record_status = 'active'
            LIMIT 1
        ";
        
        // Try exact match first
        $result = $db->fetchOne($sql, [$phone]);
        
        if (!$result) {
            // Try without + and -
            $phone_digits = preg_replace('/[^\d]/', '', $phone);
            $result = $db->fetchOne(
                "SELECT u.id as user_id, r.id as resident_id, r.record_status, u.status
                 FROM users u
                 LEFT JOIN residents r ON u.resident_id = r.id
                 WHERE REPLACE(REPLACE(r.contact_number, '+', ''), '-', '') = ? 
                 AND u.status = 'active' AND r.record_status = 'active'
                 LIMIT 1",
                [$phone_digits]
            );
        }
        
        if (!$result) {
            return [
                'valid' => false,
                'user_id' => null,
                'resident_id' => null,
                'message' => 'Mobile number not found in resident database'
            ];
        }
        
        // Check if resident record status is approved
        if ($result['resident_id'] && $result['record_status'] !== 'active') {
            return [
                'valid' => false,
                'user_id' => null,
                'resident_id' => $result['resident_id'],
                'message' => 'Your resident record is not approved yet. Please contact the barangay office.'
            ];
        }
        
        return [
            'valid' => true,
            'user_id' => $result['user_id'],
            'resident_id' => $result['resident_id'],
            'message' => 'Mobile number verified'
        ];
        
    } catch (Exception $e) {
        error_log("Mobile validation error: " . $e->getMessage());
        return [
            'valid' => false,
            'user_id' => null,
            'resident_id' => null,
            'message' => 'Error validating mobile number'
        ];
    }
}

/**
 * Generate OTP with configurable length (4-6 digits)
 */
function generateOTP($length = OTP_LENGTH) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

/**
 * Generate a secure token for email-based reset
 */
function generateResetToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Check rate limiting for password reset requests
 *
 * @param int|null $user_id User ID (null if checking by IP only)
 * @param string $action Action to check (request, otp_verify, token_verify, reset)
 * @param int $max_attempts Maximum attempts allowed
 * @param int $window_minutes Time window in minutes
 * @return array ['allowed' => bool, 'attempts' => int, 'reset_in' => int (seconds)]
 */
function checkRateLimit($user_id = null, $action = 'request', $max_attempts = MAX_RESET_REQUESTS_PER_HOUR, $window_minutes = RATE_LIMIT_WINDOW_MINUTES) {
    try {
        $db = Database::getInstance();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $now = new DateTime();
        $window_start = $now->modify('-' . $window_minutes . ' minutes');
        
        // Check existing rate limit record
        $sql = "SELECT * FROM password_reset_rate_limit 
                WHERE action = ? 
                AND (user_id = ? OR ip_address = ?) 
                AND window_start > ?";
        
        $record = $db->fetchOne($sql, [$action, $user_id, $ip, $window_start->format('Y-m-d H:i:s')]);
        
        if ($record) {
            // Update existing record
            if ($record['request_count'] >= $max_attempts) {
                // Rate limit exceeded
                $reset_time = strtotime($record['window_start']) + ($window_minutes * 60);
                $reset_in = max(0, $reset_time - time());
                
                return [
                    'allowed' => false,
                    'attempts' => $record['request_count'],
                    'reset_in' => $reset_in,
                    'message' => "Too many attempts. Please try again in " . ceil($reset_in / 60) . " minutes."
                ];
            }
            
            // Increment counter
            $db->query(
                "UPDATE password_reset_rate_limit SET request_count = request_count + 1, last_request = NOW(), updated_at = NOW() WHERE id = ?",
                [$record['id']]
            );
            
            return [
                'allowed' => true,
                'attempts' => $record['request_count'] + 1,
                'remaining' => max(0, $max_attempts - ($record['request_count'] + 1))
            ];
        } else {
            // Create new rate limit record
            $db->query(
                "INSERT INTO password_reset_rate_limit (user_id, ip_address, action, request_count, last_request, window_start) 
                 VALUES (?, ?, ?, 1, NOW(), ?)",
                [$user_id, $ip, $action, $now->format('Y-m-d H:i:s')]
            );
            
            return [
                'allowed' => true,
                'attempts' => 1,
                'remaining' => max(0, $max_attempts - 1)
            ];
        }
    } catch (Exception $e) {
        error_log("Rate limit check error: " . $e->getMessage());
        return ['allowed' => true, 'attempts' => 0]; // Allow on error
    }
}

/**
 * Send OTP via SMS (placeholder - integrate with actual SMS service)
 *
 * @param string $phone_number Phone number to send OTP
 * @param string $otp OTP code
 * @return bool Success status
 */
function sendOTPViaSMS($phone_number, $otp) {
    try {
        // TODO: Integrate with actual SMS provider (Twillio, Nexmo, etc.)
        // For now, log to file for testing
        $message = "Your E-Barangay password reset OTP is: " . $otp . ". Valid for 10 minutes. Do not share this code.";
        
        // Log OTP for development/testing purposes
        error_log("SMS OTP to {$phone_number}: {$otp}");
        
        // In production, call actual SMS API here
        // Example: sendViaTwilio($phone_number, $message);
        
        return true;
    } catch (Exception $e) {
        error_log("SMS send error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send reset link via Email (placeholder - integrate with actual Email service)
 *
 * @param string $email Email address
 * @param string $token Reset token
 * @param string $reset_link Full reset link URL
 * @return bool Success status
 */
function sendResetLinkViaEmail($email, $token, $reset_link) {
    try {
        // Email template
        $subject = "E-Barangay Password Reset Request";
        
        $message = "
        <h2>Password Reset Request</h2>
        <p>You requested a password reset for your E-Barangay account.</p>
        <p>Click the link below to reset your password:</p>
        <p><a href='{$reset_link}'>Reset Password</a></p>
        <p>Or copy this link: {$reset_link}</p>
        <p><strong>This link will expire in 60 minutes.</strong></p>
        <p>If you did not request this reset, please ignore this email or contact support.</p>
        <hr>
        <p>E-Barangay Information Management System</p>
        ";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . BARANGAY_NAME . " <noreply@ebarangay.local>\r\n";
        
        // Log email for development/testing
        error_log("Email sent to {$email}: Reset token: {$token}");
        
        // Send email using PHP mail or integrate with mail service
        $result = mail($email, $subject, $message, $headers);
        
        return $result;
    } catch (Exception $e) {
        error_log("Email send error: " . $e->getMessage());
        return false;
    }
}

/**
 * Initiate password reset request
 *
 * @param string $user_identifier Email or username
 * @param string $method 'email' or 'sms'
 * @return array Result array with status and data
 */
function initiatePasswordReset($user_identifier, $method) {
    try {
        $db = Database::getInstance();
        
        // Validate method
        if (!in_array($method, ['email', 'sms'])) {
            return [
                'success' => false,
                'message' => 'Invalid verification method',
                'code' => 'INVALID_METHOD'
            ];
        }
        
        // Check rate limiting
        $rate_check = checkRateLimit(null, 'request', MAX_RESET_REQUESTS_PER_HOUR, RATE_LIMIT_WINDOW_MINUTES);
        if (!$rate_check['allowed']) {
            logPasswordResetActivity(null, 'request_failed', $method, $user_identifier, 'rate_limit_exceeded');
            return [
                'success' => false,
                'message' => $rate_check['message'],
                'code' => 'RATE_LIMITED'
            ];
        }
        
        // Find user by email or username
        $sql = "SELECT id, username, email FROM users WHERE (email = ? OR username = ?) AND status = 'active'";
        $user = $db->fetchOne($sql, [$user_identifier, $user_identifier]);
        
        if (!$user) {
            // For security, don't reveal whether email/username exists
            logPasswordResetActivity(null, 'request_initiated', $method, $user_identifier, ['status' => 'user_not_found']);
            return [
                'success' => true,
                'message' => 'If an account exists with this identifier, you will receive a verification code.',
                'code' => 'REQUEST_SUBMITTED'
            ];
        }
        
        $user_id = $user['id'];
        $email = $user['email'];
        
        // Get phone number from residents table if user is linked to resident
        $phone = null;
        if ($user['resident_id']) {
            $resident = $db->fetchOne("SELECT contact_number FROM residents WHERE id = ?", [$user['resident_id']]);
            if ($resident && $resident['contact_number']) {
                $phone = $resident['contact_number'];
            }
        }
        
        // Validate identifier availability for method
        if ($method === 'sms' && !$phone) {
            logPasswordResetActivity($user_id, 'request_failed', $method, $user_identifier, 'no_phone_number');
            return [
                'success' => false,
                'message' => 'No phone number found for SMS verification. Please use Email verification.',
                'code' => 'NO_PHONE_NUMBER'
            ];
        }
        
        if ($method === 'email' && !$email) {
            logPasswordResetActivity($user_id, 'request_failed', $method, $user_identifier, 'no_email');
            return [
                'success' => false,
                'message' => 'No email address found in your account. Please contact support.',
                'code' => 'NO_EMAIL'
            ];
        }
        
        // Generate token/OTP and store
        if ($method === 'email') {
            $token = generateResetToken();
            $expires_at = date('Y-m-d H:i:s', time() + (TOKEN_EXPIRY_MINUTES * 60));
            
            $db->query(
                "INSERT INTO password_reset_tokens (user_id, token, method, identifier, expires_at) VALUES (?, ?, ?, ?, ?)",
                [$user_id, $token, $method, $email, $expires_at]
            );
            
            $token_id = $db->lastInsertId();
            
            // Generate reset link
            $reset_link = BASE_URL . "verify-reset.php?token=" . urlencode($token) . "&type=email";
            
            // Send email
            $send_result = sendResetLinkViaEmail($email, $token, $reset_link);
            
            logPasswordResetActivity($user_id, 'token_sent', $method, $email, ['token_id' => $token_id, 'sent' => $send_result]);
            
        } else { // SMS
            $otp = generateOTP();
            $expires_at = date('Y-m-d H:i:s', time() + (OTP_EXPIRY_MINUTES * 60));
            
            $db->query(
                "INSERT INTO password_reset_otp (user_id, otp_code, phone_number, expires_at) VALUES (?, ?, ?, ?)",
                [$user_id, $otp, $phone, $expires_at]
            );
            
            $otp_id = $db->lastInsertId();
            
            // Send OTP
            $send_result = sendOTPViaSMS($phone, $otp);
            
            logPasswordResetActivity($user_id, 'otp_sent', $method, $phone, ['otp_id' => $otp_id, 'sent' => $send_result]);
        }
        
        return [
            'success' => true,
            'message' => 'Verification code sent successfully',
            'code' => 'CODE_SENT',
            'method' => $method,
            'identifier_hint' => maskIdentifier($method === 'email' ? $email : $phone, $method)
        ];
        
    } catch (Exception $e) {
        error_log("Password reset initiation error: " . $e->getMessage());
        logPasswordResetActivity(null, 'request_error', $method ?? 'unknown', $user_identifier, ['error' => $e->getMessage()]);
        return [
            'success' => false,
            'message' => 'An error occurred. Please try again later.',
            'code' => 'ERROR'
        ];
    }
}

/**
 * Verify OTP for SMS-based reset
 *
 * @param string $otp OTP code entered by user
 * @param string $user_identifier Username or email (for finding user)
 * @return array Result array
 */
function verifyOTP($otp, $user_identifier) {
    try {
        $db = Database::getInstance();
        
        // Check rate limiting
        $rate_check = checkRateLimit(null, 'otp_verify', MAX_OTP_VERIFICATIONS_PER_HOUR, RATE_LIMIT_WINDOW_MINUTES);
        if (!$rate_check['allowed']) {
            return [
                'success' => false,
                'message' => $rate_check['message'],
                'code' => 'RATE_LIMITED'
            ];
        }
        
        // Find user
        $user = $db->fetchOne("SELECT id FROM users WHERE (email = ? OR username = ?) AND status = 'active'", [$user_identifier, $user_identifier]);
        if (!$user) {
            logPasswordResetActivity(null, 'otp_verify_failed', 'sms', $user_identifier, 'user_not_found');
            return [
                'success' => false,
                'message' => 'Invalid user identifier',
                'code' => 'USER_NOT_FOUND'
            ];
        }
        
        // Find valid OTP record
        $otp_record = $db->fetchOne(
            "SELECT * FROM password_reset_otp 
             WHERE user_id = ? 
             AND is_verified = 0 
             AND expires_at > NOW() 
             ORDER BY created_at DESC 
             LIMIT 1",
            [$user['id']]
        );
        
        if (!$otp_record) {
            logPasswordResetActivity($user['id'], 'otp_verify_failed', 'sms', $user_identifier, 'no_valid_otp');
            return [
                'success' => false,
                'message' => 'No valid OTP found. Please request a new one.',
                'code' => 'NO_VALID_OTP'
            ];
        }
        
        // Check if max attempts exceeded
        if ($otp_record['attempt_count'] >= $otp_record['max_attempts']) {
            logPasswordResetActivity($user['id'], 'otp_verify_failed', 'sms', $otp_record['phone_number'], 'max_attempts_exceeded');
            return [
                'success' => false,
                'message' => 'Maximum OTP attempts exceeded. Please request a new OTP.',
                'code' => 'MAX_ATTEMPTS_EXCEEDED'
            ];
        }
        
        // Increment attempt count
        $db->query(
            "UPDATE password_reset_otp SET attempt_count = attempt_count + 1 WHERE id = ?",
            [$otp_record['id']]
        );
        
        // Verify OTP
        if (!hash_equals($otp_record['otp_code'], $otp)) {
            logPasswordResetActivity($user['id'], 'otp_verify_failed', 'sms', $otp_record['phone_number'], ['attempt' => $otp_record['attempt_count'] + 1]);
            return [
                'success' => false,
                'message' => 'Invalid OTP. Please try again.',
                'code' => 'INVALID_OTP',
                'attempts_remaining' => max(0, $otp_record['max_attempts'] - ($otp_record['attempt_count'] + 1))
            ];
        }
        
        // Mark OTP as verified
        $db->query(
            "UPDATE password_reset_otp SET is_verified = 1, verified_at = NOW() WHERE id = ?",
            [$otp_record['id']]
        );
        
        // Generate session token for password reset
        $reset_session_token = bin2hex(random_bytes(32));
        $_SESSION['password_reset_token'] = $reset_session_token;
        $_SESSION['password_reset_user_id'] = $user['id'];
        $_SESSION['password_reset_method'] = 'sms';
        $_SESSION['password_reset_verified'] = true;
        $_SESSION['password_reset_expires'] = time() + (15 * 60); // 15 minutes
        
        logPasswordResetActivity($user['id'], 'otp_verified', 'sms', $otp_record['phone_number']);
        
        return [
            'success' => true,
            'message' => 'OTP verified successfully',
            'code' => 'OTP_VERIFIED',
            'reset_token' => $reset_session_token
        ];
        
    } catch (Exception $e) {
        error_log("OTP verification error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'An error occurred. Please try again.',
            'code' => 'ERROR'
        ];
    }
}

/**
 * Verify email reset token
 *
 * @param string $token Reset token from email link
 * @return array Result array
 */
function verifyResetToken($token) {
    try {
        $db = Database::getInstance();
        
        // Find and validate token
        $token_record = $db->fetchOne(
            "SELECT * FROM password_reset_tokens 
             WHERE token = ? 
             AND is_used = 0 
             AND expires_at > NOW()",
            [$token]
        );
        
        if (!$token_record) {
            logPasswordResetActivity(null, 'token_verify_failed', 'email', 'unknown', 'invalid_or_expired_token');
            return [
                'success' => false,
                'message' => 'Invalid or expired reset link. Please request a new one.',
                'code' => 'INVALID_TOKEN'
            ];
        }
        
        // Generate session token for password reset
        $reset_session_token = bin2hex(random_bytes(32));
        $_SESSION['password_reset_token'] = $reset_session_token;
        $_SESSION['password_reset_user_id'] = $token_record['user_id'];
        $_SESSION['password_reset_method'] = 'email';
        $_SESSION['password_reset_verified'] = true;
        $_SESSION['password_reset_expires'] = time() + (30 * 60); // 30 minutes for email link
        $_SESSION['password_reset_token_id'] = $token_record['id'];
        
        logPasswordResetActivity($token_record['user_id'], 'token_verified', 'email', $token_record['identifier']);
        
        return [
            'success' => true,
            'message' => 'Token verified successfully',
            'code' => 'TOKEN_VERIFIED',
            'reset_token' => $reset_session_token
        ];
        
    } catch (Exception $e) {
        error_log("Token verification error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'An error occurred. Please try again.',
            'code' => 'ERROR'
        ];
    }
}

/**
 * Reset password after verification
 *
 * @param int $user_id User ID
 * @param string $new_password New password
 * @return array Result array
 */
function resetPassword($user_id, $new_password) {
    try {
        $db = Database::getInstance();
        
        // Validate session
        if (!isset($_SESSION['password_reset_verified']) || 
            $_SESSION['password_reset_verified'] !== true || 
            $_SESSION['password_reset_user_id'] != $user_id ||
            time() > $_SESSION['password_reset_expires']) {
            
            logPasswordResetActivity($user_id, 'reset_failed', 'unknown', 'unknown', 'invalid_session');
            return [
                'success' => false,
                'message' => 'Invalid or expired reset session. Please start over.',
                'code' => 'INVALID_SESSION'
            ];
        }
        
        // Validate password
        $password_validation = validatePassword($new_password);
        if (!$password_validation['valid']) {
            logPasswordResetActivity($user_id, 'reset_failed', $_SESSION['password_reset_method'], 'unknown', $password_validation['errors']);
            return [
                'success' => false,
                'message' => 'Password does not meet requirements: ' . implode(', ', $password_validation['errors']),
                'code' => 'INVALID_PASSWORD',
                'requirements' => $password_validation['requirements']
            ];
        }
        
        // Hash password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password in database
        $db->query(
            "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?",
            [$password_hash, $user_id]
        );
        
        // Mark token/OTP as used
        if ($_SESSION['password_reset_method'] === 'email' && isset($_SESSION['password_reset_token_id'])) {
            $db->query(
                "UPDATE password_reset_tokens SET is_used = 1, used_at = NOW() WHERE id = ?",
                [$_SESSION['password_reset_token_id']]
            );
        } else if ($_SESSION['password_reset_method'] === 'sms') {
            // OTP already marked as verified, no need to mark as used again
        }
        
        // Invalidate all user sessions (logout all devices)
        // In production, you might want to invalidate active sessions from database
        
        logPasswordResetActivity($user_id, 'password_reset', $_SESSION['password_reset_method'], 'unknown', ['success' => true]);
        
        // Clear reset session variables
        unset($_SESSION['password_reset_token']);
        unset($_SESSION['password_reset_user_id']);
        unset($_SESSION['password_reset_method']);
        unset($_SESSION['password_reset_verified']);
        unset($_SESSION['password_reset_expires']);
        unset($_SESSION['password_reset_token_id']);
        
        return [
            'success' => true,
            'message' => 'Password reset successfully. You can now login with your new password.',
            'code' => 'PASSWORD_RESET_SUCCESS'
        ];
        
    } catch (Exception $e) {
        error_log("Password reset error: " . $e->getMessage());
        logPasswordResetActivity($user_id, 'reset_error', $_SESSION['password_reset_method'] ?? 'unknown', 'unknown', ['error' => $e->getMessage()]);
        return [
            'success' => false,
            'message' => 'An error occurred while resetting password. Please try again.',
            'code' => 'ERROR'
        ];
    }
}

/**
 * Validate password strength
 *
 * @param string $password Password to validate
 * @return array ['valid' => bool, 'errors' => array, 'requirements' => array]
 */
function validatePassword($password) {
    $errors = [];
    $requirements = [
        'min_length' => 'At least 8 characters',
        'uppercase' => 'At least one uppercase letter',
        'lowercase' => 'At least one lowercase letter',
        'number' => 'At least one number',
        'special' => 'At least one special character (!@#$%^&*)'
    ];
    
    if (strlen($password) < 8) {
        $errors[] = $requirements['min_length'];
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = $requirements['uppercase'];
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = $requirements['lowercase'];
    }
    
    if (!preg_match('/\d/', $password)) {
        $errors[] = $requirements['number'];
    }
    
    if (!preg_match('/[!@#$%^&*()_+=\[\]{};:\'",.<>?\\|`~\-]/', $password)) {
        $errors[] = $requirements['special'];
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'requirements' => $requirements
    ];
}

/**
 * Log password reset activity for audit trail
 *
 * @param int|null $user_id User ID (null if not identified)
 * @param string $action Action performed
 * @param string $method 'email' or 'sms'
 * @param string $identifier Email or phone number used
 * @param mixed $details Additional details
 */
function logPasswordResetActivity($user_id, $action, $method, $identifier, $details = null) {
    try {
        $db = Database::getInstance();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $details_json = is_array($details) || is_object($details) ? json_encode($details) : $details;
        
        $db->query(
            "INSERT INTO password_reset_logs (user_id, action, method, identifier, ip_address, user_agent, details) 
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$user_id, $action, $method, $identifier, $ip, $user_agent, $details_json]
        );
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}

/**
 * Mask identifier for security display (show last 4 chars only)
 *
 * @param string $identifier Email or phone number
 * @param string $type 'email' or 'sms'
 * @return string Masked identifier
 */
function maskIdentifier($identifier, $type = 'email') {
    if ($type === 'email') {
        $parts = explode('@', $identifier);
        if (count($parts) === 2) {
            $name = $parts[0];
            $domain = $parts[1];
            $masked_name = substr($name, 0, 1) . '***' . substr($name, -1);
            return $masked_name . '@' . $domain;
        }
    } else if ($type === 'sms') {
        // Show last 4 digits of phone number
        return '***-***-' . substr($identifier, -4);
    }
    return '***';
}

/**
 * Resend OTP
 *
 * @param string $user_identifier Username or email
 * @return array Result array
 */
function resendOTP($user_identifier) {
    try {
        $db = Database::getInstance();
        
        // Check rate limiting for resend
        $rate_check = checkRateLimit(null, 'otp_resend', MAX_RESEND_OTP_PER_HOUR, RATE_LIMIT_WINDOW_MINUTES);
        if (!$rate_check['allowed']) {
            return [
                'success' => false,
                'message' => $rate_check['message'],
                'code' => 'RATE_LIMITED'
            ];
        }
        
        // Find user
        $user = $db->fetchOne("SELECT id FROM users WHERE (email = ? OR username = ?) AND status = 'active'", [$user_identifier, $user_identifier]);
        if (!$user) {
            return [
                'success' => true,
                'message' => 'If an account exists, a new OTP will be sent.',
                'code' => 'OTP_REQUEST_SUBMITTED'
            ];
        }
        
        // Find latest OTP
        $otp_record = $db->fetchOne(
            "SELECT * FROM password_reset_otp 
             WHERE user_id = ? 
             AND is_verified = 0 
             AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
             ORDER BY created_at DESC 
             LIMIT 1",
            [$user['id']]
        );
        
        if (!$otp_record) {
            return [
                'success' => false,
                'message' => 'No recent OTP request found. Please initiate a new password reset.',
                'code' => 'NO_OTP_REQUEST'
            ];
        }
        
        // Generate new OTP
        $new_otp = generateOTP();
        $expires_at = date('Y-m-d H:i:s', time() + (OTP_EXPIRY_MINUTES * 60));
        
        // Update OTP record with new code
        $db->query(
            "UPDATE password_reset_otp SET otp_code = ?, expires_at = ?, attempt_count = 0, updated_at = NOW() WHERE id = ?",
            [$new_otp, $expires_at, $otp_record['id']]
        );
        
        // Send new OTP
        sendOTPViaSMS($otp_record['phone_number'], $new_otp);
        
        logPasswordResetActivity($user['id'], 'otp_resent', 'sms', $otp_record['phone_number']);
        
        return [
            'success' => true,
            'message' => 'New OTP sent successfully',
            'code' => 'OTP_RESENT',
            'identifier_hint' => maskIdentifier($otp_record['phone_number'], 'sms')
        ];
        
    } catch (Exception $e) {
        error_log("OTP resend error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'An error occurred. Please try again.',
            'code' => 'ERROR'
        ];
    }
}

/**
 * Clean up expired tokens and OTPs
 */
function cleanupExpiredPasswordResets() {
    try {
        $db = Database::getInstance();
        
        // Delete expired tokens
        $db->query("DELETE FROM password_reset_tokens WHERE expires_at < NOW()");
        
        // Delete expired OTPs
        $db->query("DELETE FROM password_reset_otp WHERE expires_at < NOW()");
        
        // Clean old rate limit records (older than 24 hours)
        $db->query("DELETE FROM password_reset_rate_limit WHERE window_start < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        
    } catch (Exception $e) {
        error_log("Cleanup error: " . $e->getMessage());
    }
}
