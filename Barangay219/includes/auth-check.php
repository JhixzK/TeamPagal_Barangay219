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
 * Get module definitions for role permissions
 */
function getModuleList() {
    return [
        'dashboard' => ['label' => 'Dashboard', 'crud' => false],
        'applications' => ['label' => 'Applications', 'crud' => true],
        'residents' => ['label' => 'Residents', 'crud' => true],
        'households' => ['label' => 'Households', 'crud' => true],
        'certificates' => ['label' => 'Certificates', 'crud' => true],
        'blotters' => ['label' => 'Blotters', 'crud' => true],
        'complaints' => ['label' => 'Complaints', 'crud' => true],
        'announcements' => ['label' => 'Announcements', 'crud' => true],
        'reports' => ['label' => 'Reports', 'crud' => false],
        'users' => ['label' => 'Users', 'crud' => true]
    ];
}

/**
 * Get legacy role permissions map (used as defaults)
 */
function getLegacyModulePermissions() {
    return [
        ROLE_SECRETARY => ['residents', 'applications', 'households', 'certificates', 'blotters', 'complaints', 'announcements', 'reports', 'dashboard'],
        ROLE_TREASURER => ['certificates', 'reports', 'dashboard'],
        ROLE_KAGAWA => ['blotters', 'complaints', 'announcements', 'dashboard'],
        ROLE_SK_CHAIRMAN => ['announcements', 'dashboard'],
        ROLE_RESIDENT => ['dashboard', 'announcements', 'profile']
    ];
}

/**
 * Build default permissions for a role based on legacy access
 */
function getDefaultRolePermissions($role) {
    $modules = getModuleList();
    $legacy = getLegacyModulePermissions();
    $allowed = $legacy[$role] ?? [];
    $defaults = [];

    foreach ($modules as $key => $meta) {
        $hasAccess = in_array($key, $allowed);
        $defaults[$key] = [
            'can_access' => $hasAccess ? 1 : 0,
            'can_create' => ($hasAccess && $meta['crud']) ? 1 : 0,
            'can_edit' => ($hasAccess && $meta['crud']) ? 1 : 0,
            'can_delete' => ($hasAccess && $meta['crud']) ? 1 : 0
        ];
    }

    if ($role === ROLE_BARANGAY_CAPTAIN) {
        foreach ($modules as $key => $meta) {
            $defaults[$key] = [
                'can_access' => 1,
                'can_create' => $meta['crud'] ? 1 : 0,
                'can_edit' => $meta['crud'] ? 1 : 0,
                'can_delete' => $meta['crud'] ? 1 : 0
            ];
        }
    }

    return $defaults;
}

/**
 * Load permissions from database
 */
function getRolePermissions($role, $withDefaults = false) {
    static $cache = [];
    $cacheKey = $role . ($withDefaults ? ':defaults' : ':raw');

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $permissions = [];

    try {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT module, can_access, can_create, can_edit, can_delete FROM role_permissions WHERE role = ?",
            [$role]
        );
        foreach ($rows as $row) {
            $permissions[$row['module']] = [
                'can_access' => (int)$row['can_access'],
                'can_create' => (int)$row['can_create'],
                'can_edit' => (int)$row['can_edit'],
                'can_delete' => (int)$row['can_delete']
            ];
        }
    } catch (Exception $e) {
        // Table may not exist yet; fall back to defaults
    }

    if ($withDefaults) {
        $defaults = getDefaultRolePermissions($role);
        foreach ($permissions as $module => $perm) {
            $defaults[$module] = array_merge($defaults[$module], $perm);
        }
        $permissions = $defaults;
    }

    $cache[$cacheKey] = $permissions;
    return $permissions;
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

    $permissions = getRolePermissions($role, true);
    if (!isset($permissions[$module])) {
        return false;
    }

    return (bool)$permissions[$module]['can_access'];
}

/**
 * Check if user has module permission
 */
function canModulePermission($module, $permission) {
    if (!isLoggedIn()) {
        return false;
    }

    $role = getCurrentUserRole();
    if ($role === ROLE_BARANGAY_CAPTAIN) {
        return true;
    }

    $permissions = getRolePermissions($role, true);
    if (!isset($permissions[$module])) {
        return false;
    }

    $map = [
        'access' => 'can_access',
        'create' => 'can_create',
        'edit' => 'can_edit',
        'delete' => 'can_delete'
    ];

    if (!isset($map[$permission])) {
        return false;
    }

    return (bool)$permissions[$module][$map[$permission]];
}

/**
 * Require module access for page views
 */
function requireModuleAccess($module) {
    requireLogin();
    if (!canAccessModule($module)) {
        header('Location: ' . BASE_URL . 'dashboard.php?error=access_denied');
        exit();
    }
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
