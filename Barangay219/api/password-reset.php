<?php
/**
 * E-Barangay Information Management System
 * Password Reset API Endpoints
 */

header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/password-reset.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'initiate':
        handleInitiateReset();
        break;
    
    case 'verify-otp':
        handleVerifyOTP();
        break;
    
    case 'verify-token':
        handleVerifyToken();
        break;
    
    case 'reset-password':
        handleResetPassword();
        break;

    case 'reset-with-token':
        handleResetWithToken();
        break;
    
    case 'resend-otp':
        handleResendOTP();
        break;
    
    case 'validate-identifier':
        handleValidateIdentifier();
        break;
    
    default:
        sendResponse(false, 'Invalid action', null, 400);
        break;
}

/**
 * Handle password reset initiation
 * POST /api/password-reset.php?action=initiate
 * Body: { identifier, method }
 */
function handleInitiateReset() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = [];
    }
    $identifier = trim((string)($data['identifier'] ?? ''));
    $method = sanitizeInput($data['method'] ?? '');
    
    if (empty($identifier) || empty($method)) {
        sendResponse(false, 'Identifier and method are required', null, 400);
        return;
    }
    
    // Handle SMS method with mobile number normalization and validation
    if ($method === 'sms') {
        // Normalize Philippine mobile number format
        $normalized_phone = normalizePhilippineMobileNumber($identifier);
        
        if (!$normalized_phone) {
            // If normalization fails, try to validate the identifier as-is
            $normalized_phone = $identifier;
        }
        
        // Use normalized phone for reset initiation
        $identifier = $normalized_phone;
    }
    
    $result = initiatePasswordReset($identifier, $method);
    
    sendResponse($result['success'], $result['message'], [
        'code' => $result['code'],
        'method' => $result['method'] ?? null,
        'identifier_hint' => $result['identifier_hint'] ?? null
    ], $result['success'] ? 200 : 400);
}

/**
 * Handle OTP verification
 * POST /api/password-reset.php?action=verify-otp
 * Body: { otp, user_identifier }
 */
function handleVerifyOTP() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $otp = sanitizeInput($data['otp'] ?? '');
    $user_identifier = sanitizeInput($data['user_identifier'] ?? '');
    
    if (empty($otp) || empty($user_identifier)) {
        sendResponse(false, 'OTP and user identifier are required', null, 400);
        return;
    }
    
    // OTP must be exactly 6 digits
    if (strlen($otp) !== 6 || !ctype_digit($otp)) {
        sendResponse(false, 'OTP must be exactly 6 digits', null, 400);
        return;
    }
    
    $result = verifyOTP($otp, $user_identifier);
    
    sendResponse($result['success'], $result['message'], [
        'code' => $result['code'],
        'reset_token' => $result['reset_token'] ?? null,
        'attempts_remaining' => $result['attempts_remaining'] ?? null
    ], $result['success'] ? 200 : 400);
}

/**
 * Handle email token verification
 * POST /api/password-reset.php?action=verify-token
 * Body: { token }
 */
function handleVerifyToken() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $token = sanitizeInput($data['token'] ?? '');
    
    if (empty($token)) {
        sendResponse(false, 'Token is required', null, 400);
        return;
    }
    
    $result = verifyResetToken($token);

    $statusCode = $result['success'] ? 200 : mapTokenErrorStatusCode($result['code'] ?? '');

    sendResponse($result['success'], $result['message'], [
        'code' => $result['code'],
        'reset_token' => $result['reset_token'] ?? null
    ], $statusCode);
}

/**
 * Handle password reset
 * POST /api/password-reset.php?action=reset-password
 * Body: { new_password, confirm_password }
 * Session must have password_reset_verified = true
 */
function handleResetPassword() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    // Verify session
    if (!isset($_SESSION['password_reset_verified']) || $_SESSION['password_reset_verified'] !== true) {
        sendResponse(false, 'Password reset not verified. Please verify your identity first.', null, 401);
        return;
    }
    
    if (!isset($_SESSION['password_reset_user_id'])) {
        sendResponse(false, 'Invalid reset session', null, 401);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $new_password = $data['new_password'] ?? '';
    $confirm_password = $data['confirm_password'] ?? '';
    
    // Note: Don't sanitize passwords with htmlspecialchars
    if (empty($new_password) || empty($confirm_password)) {
        sendResponse(false, 'Password and confirmation are required', null, 400);
        return;
    }
    
    if ($new_password !== $confirm_password) {
        sendResponse(false, 'Passwords do not match', null, 400);
        return;
    }
    
    $result = resetPassword($_SESSION['password_reset_user_id'], $new_password);
    
    sendResponse($result['success'], $result['message'], [
        'code' => $result['code'],
        'requirements' => $result['requirements'] ?? null
    ], $result['success'] ? 200 : 400);
}

/**
 * Handle direct password reset with token (single-step).
 * POST /api/password-reset.php?action=reset-with-token
 * Body: { token, new_password, confirm_password }
 */
function handleResetWithToken() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $token = sanitizeInput($data['token'] ?? '');
    $new_password = $data['new_password'] ?? '';
    $confirm_password = $data['confirm_password'] ?? '';

    if (empty($token) || empty($new_password) || empty($confirm_password)) {
        sendResponse(false, 'Token, password, and confirmation are required', null, 400);
        return;
    }

    $result = resetPasswordWithToken($token, $new_password, $confirm_password);

    $statusCode = $result['success'] ? 200 : mapTokenErrorStatusCode($result['code'] ?? '');

    sendResponse($result['success'], $result['message'], [
        'code' => $result['code'],
        'requirements' => $result['requirements'] ?? null
    ], $statusCode);
}

/**
 * Map token-related reset errors to clearer HTTP status codes.
 */
function mapTokenErrorStatusCode($code) {
    switch ($code) {
        case 'INVALID_TOKEN':
            return 404;
        case 'TOKEN_EXPIRED':
            return 410;
        case 'TOKEN_ALREADY_USED':
            return 409;
        case 'ERROR':
            return 500;
        default:
            return 400;
    }
}

/**
 * Handle OTP resend
 * POST /api/password-reset.php?action=resend-otp
 * Body: { user_identifier }
 */
function handleResendOTP() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $user_identifier = sanitizeInput($data['user_identifier'] ?? '');
    
    if (empty($user_identifier)) {
        sendResponse(false, 'User identifier is required', null, 400);
        return;
    }
    
    $result = resendOTP($user_identifier);
    
    sendResponse($result['success'], $result['message'], [
        'code' => $result['code'],
        'identifier_hint' => $result['identifier_hint'] ?? null
    ], $result['success'] ? 200 : 400);
}

/**
 * Validate identifier to check if user exists (for UX improvement)
 * POST /api/password-reset.php?action=validate-identifier
 * Body: { identifier }
 */
function handleValidateIdentifier() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $identifier = sanitizeInput($data['identifier'] ?? '');
    
    if (empty($identifier)) {
        sendResponse(false, 'Identifier is required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        // Check if user exists
        $user = $db->fetchOne(
            "SELECT id, email FROM users WHERE (email = ? OR username = ?) AND status = 'active'",
            [$identifier, $identifier]
        );
        
        if ($user) {
            // Check if user has email for email verification
            $has_email = !empty($user['email']);
            
            // Check if user has phone for SMS verification
            $has_phone = false;
            // This would be checked from residents table based on active sessions
            
            sendResponse(true, 'User found', [
                'exists' => true,
                'can_use_email' => $has_email
            ]);
        } else {
            // For security, don't reveal whether user exists
            sendResponse(true, 'Validation complete', [
                'exists' => false
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Identifier validation error: " . $e->getMessage());
        sendResponse(false, 'An error occurred', null, 500);
    }
}

/**
 * Send JSON response
 */
function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit();
}
