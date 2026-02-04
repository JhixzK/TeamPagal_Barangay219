<?php
/**
 * E-Barangay Information Management System
 * Authentication API Endpoints
 */

header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    
    case 'logout':
        handleLogout();
        break;
    
    case 'check':
        checkAuth();
        break;
    
    default:
        sendResponse(false, 'Invalid action', null, 400);
        break;
}

/**
 * Handle login request
 */
function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($username) || empty($password)) {
        sendResponse(false, 'Username and password are required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        // Get user from database
        $sql = "SELECT id, username, password, email, role, status, resident_id 
                FROM users 
                WHERE username = ? AND status != 'suspended'";
        $user = $db->fetchOne($sql, [$username]);
        
        if (!$user) {
            sendResponse(false, 'Invalid username or password', null, 401);
            return;
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            sendResponse(false, 'Invalid username or password', null, 401);
            return;
        }
        
        // Check if account is active
        if ($user['status'] !== 'active') {
            sendResponse(false, 'Your account is inactive. Please contact the administrator.', null, 403);
            return;
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['resident_id'] = $user['resident_id'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        // Get user info with resident details
        $userInfo = getUserInfo($user['id']);
        
        // Remove password from response
        unset($userInfo['password']);
        
        sendResponse(true, 'Login successful', [
            'user' => $userInfo,
            'redirect' => BASE_URL . 'dashboard.php'
        ]);
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        sendResponse(false, 'An error occurred during login. Please try again.', null, 500);
    }
}

/**
 * Handle logout request
 */
function handleLogout() {
    logout();
    sendResponse(true, 'Logged out successfully', [
        'redirect' => BASE_URL . 'index.php'
    ]);
}

/**
 * Check authentication status
 */
function checkAuth() {
    if (isLoggedIn()) {
        $userInfo = getUserInfo();
        if ($userInfo) {
            unset($userInfo['password']);
            sendResponse(true, 'User is authenticated', ['user' => $userInfo]);
        } else {
            sendResponse(false, 'User session invalid', null, 401);
        }
    } else {
        sendResponse(false, 'User is not authenticated', null, 401);
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
