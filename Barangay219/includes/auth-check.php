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
    if ($role === ROLE_BARANGAY_CAPTAIN) {
        return true;
    }

    $permissions = getRolePermissions($role);
    if (isset($permissions[$module])) {
        return !empty($permissions[$module]['can_access']);
    }

    $defaults = getDefaultRolePermissions();
    return !empty($defaults[$role][$module]['can_access']);
}

/**
 * Require access to a module
 */
function requireModuleAccess($module) {
    requireLogin();
    if (!canAccessModule($module)) {
        header('Location: ' . BASE_URL . 'dashboard.php?error=access_denied');
        exit();
    }
}

/**
 * Check if user can perform a permission on a module
 */
function canPerformModulePermission($module, $permission) {
    if (!isLoggedIn()) {
        return false;
    }

    $role = getCurrentUserRole();
    if ($role === ROLE_BARANGAY_CAPTAIN) {
        return true;
    }

    $permissions = getRolePermissions($role);
    $modulePerms = $permissions[$module] ?? [];

    if (empty($modulePerms['can_access'])) {
        return false;
    }

    if ($permission === 'can_access' || $permission === 'access') {
        return true;
    }

    return !empty($modulePerms[$permission]);
}

/**
 * Require permission for a module (page use)
 */
function requireModulePermission($module, $permission) {
    requireLogin();
    if (!canPerformModulePermission($module, $permission)) {
        header('Location: ' . BASE_URL . 'dashboard.php?error=access_denied');
        exit();
    }
}

/**
 * Get role permissions from database with default fallback
 */
function getRolePermissions($role) {
    static $cache = [];

    if (!$role) {
        return [];
    }

    if (isset($cache[$role])) {
        return $cache[$role];
    }

    if (!rolePermissionsTableExists()) {
        $defaults = getDefaultRolePermissions();
        $cache[$role] = $defaults[$role] ?? [];
        return $cache[$role];
    }

    try {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT module, can_access, can_create, can_edit, can_delete FROM role_permissions WHERE role = ?",
            [$role]
        );
    } catch (Exception $e) {
        $defaults = getDefaultRolePermissions();
        $cache[$role] = $defaults[$role] ?? [];
        return $cache[$role];
    }

    if (empty($rows)) {
        $defaults = getDefaultRolePermissions();
        $cache[$role] = $defaults[$role] ?? [];
        return $cache[$role];
    }

    $permissions = [];
    foreach ($rows as $row) {
        $permissions[$row['module']] = [
            'can_access' => (bool)$row['can_access'],
            'can_create' => (bool)$row['can_create'],
            'can_edit' => (bool)$row['can_edit'],
            'can_delete' => (bool)$row['can_delete']
        ];
    }

    $cache[$role] = $permissions;
    return $permissions;
}

/**
 * Default permissions map
 */
function getDefaultRolePermissions() {
    return [
        ROLE_SECRETARY => [
            'dashboard' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'applications' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'residents' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'households' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'certificates' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'blotters' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'complaints' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'announcements' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'reports' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true]
        ],
        ROLE_TREASURER => [
            'dashboard' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'certificates' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'reports' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true]
        ],
        ROLE_KAGAWA => [
            'dashboard' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'blotters' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'complaints' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'announcements' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true]
        ],
        ROLE_SK_CHAIRMAN => [
            'dashboard' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'announcements' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true]
        ],
        ROLE_RESIDENT => [
            'dashboard' => ['can_access' => true, 'can_create' => false, 'can_edit' => false, 'can_delete' => false],
            'announcements' => ['can_access' => true, 'can_create' => false, 'can_edit' => false, 'can_delete' => false],
            'profile' => ['can_access' => true, 'can_create' => false, 'can_edit' => false, 'can_delete' => false]
        ]
    ];
}

/**
 * Check if the role_permissions table exists
 */
function rolePermissionsTableExists() {
    static $exists = null;

    if ($exists !== null) {
        return $exists;
    }

    try {
        $db = Database::getInstance();
        $result = $db->fetchOne("SHOW TABLES LIKE 'role_permissions'");
        $exists = !empty($result);
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
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
 * Log user activity for audit trail
 */
function logActivity($action, $module, $entityId = null, $details = null) {
    $userId = getCurrentUserId();
    if (!$userId) return;
    try {
        $db = Database::getInstance();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $detailsJson = $details !== null ? json_encode($details) : null;
        $db->query(
            "INSERT INTO activity_logs (user_id, action, module, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)",
            [$userId, $action, $module, $entityId, $detailsJson, $ip]
        );
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
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
