<?php
/**
 * E-Barangay Information Management System
 * User Management API
 */

header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireAdmin(); // Only Barangay Captain can manage users

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        listUsers();
        break;
    case 'activity_logs':
        getActivityLogs();
        break;

    case 'get_permissions':
        getRolePermissionsApi();
        break;

    case 'save_permissions':
        saveRolePermissionsApi();
        break;
    
    case 'get':
        getUser();
        break;
    
    case 'create':
        createUser();
        break;
    
    case 'update':
        updateUser();
        break;
    
    case 'delete':
        deleteUser();
        break;
    
    case 'suspend':
        suspendUser();
        break;
    
    case 'activate':
        activateUser();
        break;
    
    default:
        sendResponse(false, 'Invalid action', null, 400);
        break;
}

/**
 * List all users
 */
function listUsers() {
    try {
        $db = Database::getInstance();

        $q = sanitizeInput($_GET['q'] ?? '');
        $role = sanitizeInput($_GET['role'] ?? '');
        $status = sanitizeInput($_GET['status'] ?? '');

        $where = '1=1';
        $params = [];
        if (!empty($q)) {
            $term = '%' . $q . '%';
            $where .= " AND (u.username LIKE ? OR u.email LIKE ? OR r.first_name LIKE ? OR r.last_name LIKE ? OR CONCAT(r.first_name, ' ', r.last_name) LIKE ?)";
            $params = array_merge($params, [$term, $term, $term, $term, $term]);
        }
        if (!empty($role)) {
            $where .= " AND u.role = ?";
            $params[] = $role;
        }
        if (!empty($status)) {
            $where .= " AND u.status = ?";
            $params[] = $status;
        }
        
        $sql = "SELECT u.id, u.username, u.email, u.role, u.status, u.created_at,
                       r.first_name, r.last_name, r.middle_name
                FROM users u
                LEFT JOIN residents r ON u.resident_id = r.id
                WHERE $where
                ORDER BY u.created_at DESC";
        
        $users = $db->fetchAll($sql, $params);
        
        // Remove sensitive data
        foreach ($users as &$user) {
            unset($user['password']);
            $user['full_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        }
        
        sendResponse(true, 'Users retrieved successfully', $users);
        
    } catch (Exception $e) {
        error_log("List users error: " . $e->getMessage());
        sendResponse(false, 'Error retrieving users', null, 500);
    }
}

/**
 * Get single user
 */
function getUser() {
    $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    
    if (!$id) {
        sendResponse(false, 'User ID is required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        $sql = "SELECT u.id, u.username, u.email, u.role, u.status, u.resident_id, u.created_at,
                       r.first_name, r.last_name, r.middle_name
                FROM users u
                LEFT JOIN residents r ON u.resident_id = r.id
                WHERE u.id = ?";
        
        $user = $db->fetchOne($sql, [$id]);
        
        if (!$user) {
            sendResponse(false, 'User not found', null, 404);
            return;
        }
        
        unset($user['password']);
        $user['full_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        
        sendResponse(true, 'User retrieved successfully', $user);
        
    } catch (Exception $e) {
        error_log("Get user error: " . $e->getMessage());
        sendResponse(false, 'Error retrieving user', null, 500);
    }
}

/**
 * Create new user
 */
function createUser() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = sanitizeInput($_POST['email'] ?? '');
    $role = sanitizeInput($_POST['role'] ?? '');
    $resident_id = intval($_POST['resident_id'] ?? 0);
    
    // Validation
    if (empty($username) || empty($password) || empty($role)) {
        sendResponse(false, 'Username, password, and role are required', null, 400);
        return;
    }
    
    if (strlen($password) < PASSWORD_MIN_LENGTH || strlen($password) > PASSWORD_MAX_LENGTH) {
        sendResponse(false, 'Password must be ' . PASSWORD_MIN_LENGTH . '-' . PASSWORD_MAX_LENGTH . ' characters', null, 400);
        return;
    }
    
    if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z0-9]*$/', $password)) {
        sendResponse(false, 'Password must contain a mix of letters and numbers', null, 400);
        return;
    }
    
    $allowed_roles = [ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY, ROLE_TREASURER, ROLE_KAGAWA, ROLE_SK_CHAIRMAN];
    if (!in_array($role, $allowed_roles)) {
        sendResponse(false, 'Invalid role', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        // Check if username already exists
        $checkSql = "SELECT id FROM users WHERE username = ?";
        $existing = $db->fetchOne($checkSql, [$username]);
        
        if ($existing) {
            sendResponse(false, 'Username already exists', null, 409);
            return;
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user (column-safe for older schemas)
        $cols = ['username', 'password', 'role'];
        $params = [$username, $hashedPassword, $role];

        if (userTableHasColumn($db, 'email')) {
            $cols[] = 'email';
            $params[] = $email ?: null;
        }
        if (userTableHasColumn($db, 'resident_id')) {
            $cols[] = 'resident_id';
            $params[] = $resident_id ?: null;
        }
        if (userTableHasColumn($db, 'status')) {
            $cols[] = 'status';
            $params[] = USER_ACTIVE;
        }

        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO users (" . implode(', ', $cols) . ") VALUES ($placeholders)";
        
        $db->query($sql, $params);
        $userId = $db->lastInsertId();
        logActivity('create', 'users', $userId, ['username' => $username]);
        
        // Get created user
        $user = $db->fetchOne("SELECT id, username, email, role, status FROM users WHERE id = ?", [$userId]);
        
        sendResponse(true, 'User created successfully', $user);
        
    } catch (Exception $e) {
        error_log("Create user error: " . $e->getMessage());
        $msg = DEBUG_MODE ? ('Error creating user: ' . $e->getMessage()) : 'Error creating user';
        sendResponse(false, $msg, null, 500);
    }
}

function userTableHasColumn($db, $column) {
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }
    try {
        $result = $db->fetchOne("SHOW COLUMNS FROM users LIKE ?", [$column]);
        $cache[$column] = !empty($result);
    } catch (Exception $e) {
        $cache[$column] = false;
    }
    return $cache[$column];
}

/**
 * Update user
 */
function updateUser() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $id = intval($_POST['id'] ?? 0);
    $email = sanitizeInput($_POST['email'] ?? '');
    $role = sanitizeInput($_POST['role'] ?? '');
    $resident_id = intval($_POST['resident_id'] ?? 0);
    $status = sanitizeInput($_POST['status'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!$id) {
        sendResponse(false, 'User ID is required', null, 400);
        return;
    }
    
    // Prevent updating own account status/role
    if ($id == getCurrentUserId()) {
        if ($status && $status !== USER_ACTIVE) {
            sendResponse(false, 'You cannot change your own account status', null, 403);
            return;
        }
    }
    
    try {
        $db = Database::getInstance();
        
        // Check if user exists
        $existing = $db->fetchOne("SELECT id, role FROM users WHERE id = ?", [$id]);
        if (!$existing) {
            sendResponse(false, 'User not found', null, 404);
            return;
        }

        $existing_role = strtolower(trim((string)($existing['role'] ?? '')));
        $requested_role = strtolower(trim((string)$role));
        if ($existing_role === ROLE_BARANGAY_CAPTAIN && $role !== '' && $requested_role !== ROLE_BARANGAY_CAPTAIN) {
            sendResponse(false, 'Barangay Captain role cannot be changed', null, 403);
            return;
        }
        
        // Build update query
        $updates = [];
        $params = [];
        
        if ($email !== '') {
            $updates[] = "email = ?";
            $params[] = $email ?: null;
        }
        
        if ($role !== '') {
            $allowed_roles = [ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY, ROLE_TREASURER, ROLE_KAGAWA, ROLE_SK_CHAIRMAN];
            if (in_array($role, $allowed_roles)) {
                $updates[] = "role = ?";
                $params[] = $role;
            }
        }
        
        if ($resident_id > 0) {
            $updates[] = "resident_id = ?";
            $params[] = $resident_id;
        }
        
        if ($status !== '') {
            $allowed_statuses = [USER_ACTIVE, USER_INACTIVE, USER_SUSPENDED];
            if (in_array($status, $allowed_statuses)) {
                $updates[] = "status = ?";
                $params[] = $status;
            }
        }
        
        if (!empty($password)) {
            if (strlen($password) < PASSWORD_MIN_LENGTH || strlen($password) > PASSWORD_MAX_LENGTH) {
                sendResponse(false, 'Password must be ' . PASSWORD_MIN_LENGTH . '-' . PASSWORD_MAX_LENGTH . ' characters', null, 400);
                return;
            }
            
            if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z0-9]*$/', $password)) {
                sendResponse(false, 'Password must contain a mix of letters and numbers', null, 400);
                return;
            }
            
            $updates[] = "password = ?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        
        if (empty($updates)) {
            sendResponse(false, 'No fields to update', null, 400);
            return;
        }
        
        $params[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $db->query($sql, $params);
        logActivity('update', 'users', $id);
        
        // Get updated user
        $user = $db->fetchOne("SELECT id, username, email, role, status FROM users WHERE id = ?", [$id]);
        
        sendResponse(true, 'User updated successfully', $user);
        
    } catch (Exception $e) {
        error_log("Update user error: " . $e->getMessage());
        sendResponse(false, 'Error updating user', null, 500);
    }
}

/**
 * Delete user (soft delete by setting status to suspended)
 */
function deleteUser() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $id = intval($_POST['id'] ?? 0);
    
    if (!$id) {
        sendResponse(false, 'User ID is required', null, 400);
        return;
    }
    
    // Prevent deleting own account
    if ($id == getCurrentUserId()) {
        sendResponse(false, 'You cannot delete your own account', null, 403);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        // Check if user exists
        $existing = $db->fetchOne("SELECT id FROM users WHERE id = ?", [$id]);
        if (!$existing) {
            sendResponse(false, 'User not found', null, 404);
            return;
        }
        
        // Soft delete by suspending
        $sql = "UPDATE users SET status = 'suspended' WHERE id = ?";
        $db->query($sql, [$id]);
        
        sendResponse(true, 'User suspended successfully', null);
        
    } catch (Exception $e) {
        error_log("Delete user error: " . $e->getMessage());
        sendResponse(false, 'Error suspending user', null, 500);
    }
}

/**
 * Suspend user
 */
function suspendUser() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $id = intval($_POST['id'] ?? 0);
    
    if (!$id) {
        sendResponse(false, 'User ID is required', null, 400);
        return;
    }
    
    if ($id == getCurrentUserId()) {
        sendResponse(false, 'You cannot suspend your own account', null, 403);
        return;
    }
    
    try {
        $db = Database::getInstance();
        $sql = "UPDATE users SET status = 'suspended' WHERE id = ?";
        $db->query($sql, [$id]);
        
        sendResponse(true, 'User suspended successfully', null);
        
    } catch (Exception $e) {
        error_log("Suspend user error: " . $e->getMessage());
        sendResponse(false, 'Error suspending user', null, 500);
    }
}

/**
 * Activate user
 */
function activateUser() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $id = intval($_POST['id'] ?? 0);
    
    if (!$id) {
        sendResponse(false, 'User ID is required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        $sql = "UPDATE users SET status = 'active' WHERE id = ?";
        $db->query($sql, [$id]);
        
        sendResponse(true, 'User activated successfully', null);
        
    } catch (Exception $e) {
        error_log("Activate user error: " . $e->getMessage());
        sendResponse(false, 'Error activating user', null, 500);
    }
}

/**
 * Get user activity logs
 */
function getActivityLogs() {
    try {
        $db = Database::getInstance();
        $userId = (int)($_GET['user_id'] ?? 0);
        $limit = min(100, max(10, (int)($_GET['limit'] ?? 50)));
        $where = $userId ? "al.user_id = ?" : "1=1";
        $params = $userId ? [$userId, $limit] : [$limit];
        $sql = "SELECT al.*, u.username FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id WHERE $where ORDER BY al.created_at DESC LIMIT ?";
        $logs = $db->fetchAll($sql, $params);
        sendResponse(true, 'Activity logs', $logs);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

/**
 * Get permissions for a role
 */
function getRolePermissionsApi() {
    $role = sanitizeInput($_GET['role'] ?? '');
    if (empty($role)) {
        sendResponse(false, 'Role is required', null, 400);
        return;
    }

    $role_normalized = strtolower(trim($role));

    $allowed_roles = [ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY, ROLE_TREASURER, ROLE_KAGAWA, ROLE_SK_CHAIRMAN, ROLE_RESIDENT];
    if (!in_array($role, $allowed_roles)) {
        sendResponse(false, 'Invalid role', null, 400);
        return;
    }

    if ($role_normalized === ROLE_BARANGAY_CAPTAIN) {
        $modules = ['dashboard', 'applications', 'resident_applications', 'residents', 'households', 'certificates', 'blotters', 'complaints', 'announcements', 'reports', 'users', 'profile'];
        $permissions = [];
        foreach ($modules as $module) {
            $permissions[$module] = [
                'can_access' => true,
                'can_create' => true,
                'can_edit' => true,
                'can_delete' => true
            ];
        }
        sendResponse(true, 'Role permissions loaded', ['role' => $role, 'permissions' => $permissions]);
        return;
    }

    $permissions = getRolePermissions($role);
    sendResponse(true, 'Role permissions loaded', ['role' => $role, 'permissions' => $permissions]);
}

/**
 * Save permissions for a role
 */
function saveRolePermissionsApi() {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    $role = sanitizeInput($payload['role'] ?? '');
    $permissions = $payload['permissions'] ?? [];

    if (empty($role)) {
        sendResponse(false, 'Role is required', null, 400);
        return;
    }

    $allowed_roles = [ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY, ROLE_TREASURER, ROLE_KAGAWA, ROLE_SK_CHAIRMAN, ROLE_RESIDENT];
    if (!in_array($role, $allowed_roles)) {
        sendResponse(false, 'Invalid role', null, 400);
        return;
    }

    if (strtolower(trim($role)) === ROLE_BARANGAY_CAPTAIN) {
        sendResponse(false, 'Barangay Captain permissions are fixed and cannot be edited', null, 403);
        return;
    }

    if (!is_array($permissions)) {
        sendResponse(false, 'Invalid permissions payload', null, 400);
        return;
    }

    if (!rolePermissionsTableExists()) {
        sendResponse(false, 'role_permissions table is missing. Run the migration to create it.', null, 500);
        return;
    }

    $allowed_modules = ['dashboard', 'applications', 'resident_applications', 'residents', 'households', 'certificates', 'blotters', 'complaints', 'announcements', 'reports', 'users', 'profile'];

    try {
        $db = Database::getInstance();
        $db->beginTransaction();

        foreach ($permissions as $module => $perms) {
            if (!in_array($module, $allowed_modules)) {
                continue;
            }

            $can_access = !empty($perms['can_access']) ? 1 : 0;
            $can_create = !empty($perms['can_create']) ? 1 : 0;
            $can_edit = !empty($perms['can_edit']) ? 1 : 0;
            $can_delete = !empty($perms['can_delete']) ? 1 : 0;

            $db->query(
                "INSERT INTO role_permissions (role, module, can_access, can_create, can_edit, can_delete)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE can_access = VALUES(can_access), can_create = VALUES(can_create), can_edit = VALUES(can_edit), can_delete = VALUES(can_delete)",
                [$role, $module, $can_access, $can_create, $can_edit, $can_delete]
            );
        }

        $db->commit();
        logActivity('update', 'role_permissions', null, ['role' => $role]);
        sendResponse(true, 'Permissions saved', null);
    } catch (Exception $e) {
        try {
            $db->rollback();
        } catch (Exception $rollbackError) {
        }
        $message = 'Error saving permissions';
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $message .= ': ' . $e->getMessage();
        }
        sendResponse(false, $message, null, 500);
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
