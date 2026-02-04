<?php
/**
 * E-Barangay Information Management System
 * Authentication and Authorization Helper Functions
 */

// Prevent direct access
if (!defined('ACCESS_ALLOWED')) {
    die('Direct access not allowed');
}

require_once __DIR__ . '/../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']) && isset($_SESSION['role']);
}

/**
 * Require login - redirect to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit();
    }
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

/**
 * Get current username
 */
function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

/**
 * Check if user has specific role
 */
function hasRole($role) {
    return getCurrentUserRole() === $role;
}

/**
 * Check if user has any of the specified roles
 */
function hasAnyRole($roles) {
    $userRole = getCurrentUserRole();
    return in_array($userRole, $roles);
}

/**
 * Require specific role - redirect if user doesn't have required role
 */
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: ' . BASE_URL . 'dashboard.php?error=access_denied');
        exit();
    }
}

/**
 * Require any of the specified roles
 */
function requireAnyRole($roles) {
    requireLogin();
    if (!hasAnyRole($roles)) {
        header('Location: ' . BASE_URL . 'dashboard.php?error=access_denied');
        exit();
    }
}

/**
 * Check if user is Barangay Captain (admin)
 */
function isAdmin() {
    return hasRole(ROLE_BARANGAY_CAPTAIN);
}

/**
 * Require admin access
 */
function requireAdmin() {
    requireRole(ROLE_BARANGAY_CAPTAIN);
}

/**
 * Check if user can access a module based on role permissions
 */
function canAccessModule($module) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $role = getCurrentUserRole();
    
    // Barangay Captain has full access
    if ($role === ROLE_BARANGAY_CAPTAIN) {
        return true;
    }
    
    // Define role permissions
    $permissions = [
        ROLE_SECRETARY => ['residents', 'households', 'certificates', 'blotters', 'complaints', 'announcements', 'reports', 'dashboard'],
        ROLE_TREASURER => ['certificates', 'reports', 'dashboard'],
        ROLE_KAGAWA => ['blotters', 'complaints', 'announcements', 'dashboard'],
        ROLE_SK_CHAIRMAN => ['announcements', 'dashboard']
    ];
    
    return isset($permissions[$role]) && in_array($module, $permissions[$role]);
}

/**
 * Sanitize input to prevent XSS
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get user information from database
 */
function getUserInfo($userId = null) {
    if ($userId === null) {
        $userId = getCurrentUserId();
    }
    
    if (!$userId) {
        return null;
    }
    
    $db = Database::getInstance();
    $sql = "SELECT u.*, r.first_name, r.last_name, r.middle_name 
            FROM users u 
            LEFT JOIN residents r ON u.resident_id = r.id 
            WHERE u.id = ? AND u.status = 'active'";
    
    return $db->fetchOne($sql, [$userId]);
}

/**
 * Logout user
 */
function logout() {
    $_SESSION = array();
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
}
