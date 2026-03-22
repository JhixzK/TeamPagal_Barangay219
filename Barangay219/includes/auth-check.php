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
 * True when the current script lives under /api/ (JSON APIs must not redirect to HTML on auth failure).
 */
function isApiScriptRequest() {
    $name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $file = str_replace('\\', '/', (string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
    return (strpos($name, '/api/') !== false || strpos($file, '/api/') !== false);
}

/**
 * Send JSON error and exit (used by API scripts when login/module checks fail).
 */
function sendJsonAuthResponse($httpCode, $message) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }
    http_response_code((int)$httpCode);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'data' => null,
    ]);
    exit();
}

/**
 * Require login - redirect to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        if (isApiScriptRequest()) {
            sendJsonAuthResponse(401, 'Login required');
        }
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
 * Get current user role (real role from session)
 */
function getRealUserRole() {
    return $_SESSION['role'] ?? null;
}

function normalizeRole($role) {
    return $role !== null ? strtolower(trim((string)$role)) : null;
}

/**
 * Get current view mode for non-resident users
 */
function getViewMode() {
    if (!isLoggedIn()) {
        return 'official';
    }

    $realRole = normalizeRole(getRealUserRole());
    if ($realRole === normalizeRole(ROLE_RESIDENT)) {
        return 'resident';
    }

    $mode = $_SESSION['view_mode'] ?? 'official';
    return $mode === 'resident' ? 'resident' : 'official';
}

/**
 * Check if current session is in resident view mode
 */
function isResidentView() {
    return getViewMode() === 'resident';
}

/**
 * Check if current user can switch view mode
 */
function canSwitchToResidentView() {
    if (!isLoggedIn()) {
        return false;
    }

    $realRole = normalizeRole(getRealUserRole());
    return $realRole !== null && $realRole !== normalizeRole(ROLE_RESIDENT);
}

/**
 * Set view mode for non-resident users
 */
function setViewMode($mode) {
    if (!canSwitchToResidentView()) {
        $_SESSION['view_mode'] = 'official';
        return false;
    }

    $mode = $mode === 'resident' ? 'resident' : 'official';
    $_SESSION['view_mode'] = $mode;
    return true;
}

/**
 * Effective role (real role or resident if in resident view)
 */
function getEffectiveUserRole() {
    $realRole = normalizeRole(getRealUserRole());
    if ($realRole === normalizeRole(ROLE_RESIDENT)) {
        return ROLE_RESIDENT;
    }
    return isResidentView() ? ROLE_RESIDENT : $realRole;
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
    return normalizeRole(getEffectiveUserRole()) === normalizeRole($role);
}

/**
 * Check if user has any of the specified roles
 */
function hasAnyRole($roles) {
    $userRole = normalizeRole(getEffectiveUserRole());
    $normalized = array_map('normalizeRole', $roles);
    return in_array($userRole, $normalized, true);
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
 * Check if user is Super Admin (highest role)
 */
function isSuperAdmin() {
    return hasRole(ROLE_SUPER_ADMIN);
}

/**
 * Check if user is a system admin (Super Admin or Barangay Captain)
 */
function isSystemAdmin() {
    return hasAnyRole([ROLE_SUPER_ADMIN, ROLE_BARANGAY_CAPTAIN]);
}

/**
 * Require admin access
 */
function requireAdmin() {
    requireAnyRole([ROLE_SUPER_ADMIN, ROLE_BARANGAY_CAPTAIN]);
}

/**
 * Check if user can access a module based on role permissions
 */
function canAccessModule($module) {
    if (!isLoggedIn()) {
        return false;
    }

    // Lock down sensitive modules to system admins only.
    if ($module === 'officials') {
        return isSystemAdmin();
    }

    $role = normalizeRole(getEffectiveUserRole());
    if ($role === ROLE_SUPER_ADMIN || $role === ROLE_BARANGAY_CAPTAIN) {
        return true;
    }

    $permissions = getRolePermissions($role);
    if (isset($permissions[$module])) {
        return !empty($permissions[$module]['can_access']);
    }

    if (!rolePermissionsTableExists()) {
        $defaults = getDefaultRolePermissions();
        return !empty($defaults[$role][$module]['can_access']);
    }

    return false;
}

/**
 * Require access to a module
 */
function requireModuleAccess($module) {
    requireLogin();
    if (!canAccessModule($module)) {
        if (isApiScriptRequest()) {
            sendJsonAuthResponse(403, 'Access denied');
        }
        // Avoid self-redirect loops on pages that already live at dashboard.php.
        if (isResidentView()) {
            header('Location: ' . BASE_URL . 'resident_dashboard.php?error=access_denied');
        } else {
            header('Location: ' . BASE_URL . 'home.php?error=access_denied');
        }
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

    // Lock down sensitive modules to system admins only.
    if ($module === 'officials') {
        return isSystemAdmin();
    }

    $role = normalizeRole(getEffectiveUserRole());
    if ($role === ROLE_SUPER_ADMIN || $role === ROLE_BARANGAY_CAPTAIN) {
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

    // Access-only mode: create/edit/delete follow module access.
    return true;
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

    $roleRaw = $role;
    $role = normalizeRole($role);

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
        if (empty($rows) && $roleRaw && $roleRaw !== $role) {
            $rows = $db->fetchAll(
                "SELECT module, can_access, can_create, can_edit, can_delete FROM role_permissions WHERE role = ?",
                [$roleRaw]
            );
        }
    } catch (Exception $e) {
        $cache[$role] = [];
        return $cache[$role];
    }

    if (empty($rows)) {
        $cache[$role] = [];
        return $cache[$role];
    }

    $permissions = [];
    foreach ($rows as $row) {
        $access = (bool)$row['can_access'];
        $permissions[$row['module']] = [
            'can_access' => $access,
            // Access-only mode: keep keys for backward compatibility.
            'can_create' => $access,
            'can_edit' => $access,
            'can_delete' => $access
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
            'resident_applications' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'residents' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'households' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'certificates' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'blotters' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'complaints' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'announcements' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'reports' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'officials' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true]
        ],
        ROLE_TREASURER => [
            'dashboard' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'certificates' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'reports' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'officials' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true]
        ],
        ROLE_KAGAWA => [
            'dashboard' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'blotters' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'complaints' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'announcements' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'officials' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true]
        ],
        ROLE_SK_CHAIRMAN => [
            'dashboard' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'announcements' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true],
            'officials' => ['can_access' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true]
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
