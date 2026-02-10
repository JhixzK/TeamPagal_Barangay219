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
 * Get role permissions
 */
function getRolePermissionsApi() {
    $role = sanitizeInput($_GET['role'] ?? $_POST['role'] ?? '');
    $allowed_roles = [ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY, ROLE_TREASURER, ROLE_KAGAWA, ROLE_SK_CHAIRMAN, ROLE_RESIDENT];
    if (!$role || !in_array($role, $allowed_roles)) {
        sendResponse(false, 'Invalid role', null, 400);
        return;
    }

    $permissions = getRolePermissions($role, true);
    sendResponse(true, 'Permissions retrieved', [
        'role' => $role,
        'permissions' => $permissions
    ]);
}

/**
 * Save role permissions
 */
function saveRolePermissionsApi() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }

    $payload = null;
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $payload = $decoded;
        }
    }

    $role = sanitizeInput(($payload['role'] ?? $_POST['role'] ?? '') ?: '');
    $permissions = $payload['permissions'] ?? $_POST['permissions'] ?? null;

    $allowed_roles = [ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY, ROLE_TREASURER, ROLE_KAGAWA, ROLE_SK_CHAIRMAN, ROLE_RESIDENT];
    if (!$role || !in_array($role, $allowed_roles)) {
        sendResponse(false, 'Invalid role', null, 400);
        return;
    }

    if (!is_array($permissions)) {
        sendResponse(false, 'Invalid permissions payload', null, 400);
        return;
    }

    try {
        $db = Database::getInstance();
        $tableCheck = $db->fetchOne("SHOW TABLES LIKE 'role_permissions'");
        if (!$tableCheck) {
            sendResponse(false, 'Role permissions table not found. Run the enhancement migration or import script.', null, 500);
            return;
        }
    } catch (Exception $e) {
        sendResponse(false, 'Database error. Please check migration status.', null, 500);
        return;
    }

    $modules = getModuleList();
    $normalized = [];

    foreach ($modules as $module => $meta) {
        $incoming = $permissions[$module] ?? [];
        $canAccess = !empty($incoming['can_access']) ? 1 : 0;
        $canCreate = !empty($incoming['can_create']) ? 1 : 0;
        $canEdit = !empty($incoming['can_edit']) ? 1 : 0;
        $canDelete = !empty($incoming['can_delete']) ? 1 : 0;

        if ($canCreate || $canEdit || $canDelete) {
            $canAccess = 1;
        }

        if (!$canAccess) {
            $canCreate = 0;
            $canEdit = 0;
            $canDelete = 0;
        }

        $normalized[$module] = [
            'can_access' => $canAccess,
            'can_create' => $canCreate,
            'can_edit' => $canEdit,
            'can_delete' => $canDelete
        ];
    }

    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        $conn->beginTransaction();

        $db->query("DELETE FROM role_permissions WHERE role = ?", [$role]);

        foreach ($normalized as $module => $perm) {
            $db->query(
                "INSERT INTO role_permissions (role, module, can_access, can_create, can_edit, can_delete, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $role,
                    $module,
                    $perm['can_access'],
                    $perm['can_create'],
                    $perm['can_edit'],
                    $perm['can_delete'],
                    getCurrentUserId()
                ]
            );
        }

        $conn->commit();
        logActivity('update', 'role_permissions', null, ['role' => $role]);
        sendResponse(true, 'Permissions saved');
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollBack();
        }
        error_log('Save permissions error: ' . $e->getMessage());
        sendResponse(false, 'Error saving permissions: ' . $e->getMessage(), null, 500);
    }
}

/**
 * List all users
 */
function listUsers() {
    try {
        $db = Database::getInstance();
        
        $sql = "SELECT u.id, u.username, u.email, u.role, u.status, u.created_at,
                       r.first_name, r.last_name, r.middle_name
                FROM users u
                LEFT JOIN residents r ON u.resident_id = r.id
                ORDER BY u.created_at DESC";
        
        $users = $db->fetchAll($sql);
        
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
        
        // Insert user
        $sql = "INSERT INTO users (username, password, email, role, resident_id, status) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $params = [
            $username,
            $hashedPassword,
            $email ?: null,
            $role,
            $resident_id ?: null,
            USER_ACTIVE
        ];
        
        $db->query($sql, $params);
        $userId = $db->lastInsertId();
        logActivity('create', 'users', $userId, ['username' => $username]);
        
        // Get created user
        $user = $db->fetchOne("SELECT id, username, email, role, status FROM users WHERE id = ?", [$userId]);
        
        sendResponse(true, 'User created successfully', $user);
        
    } catch (Exception $e) {
        error_log("Create user error: " . $e->getMessage());
        sendResponse(false, 'Error creating user', null, 500);
    }
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
        $existing = $db->fetchOne("SELECT id FROM users WHERE id = ?", [$id]);
        if (!$existing) {
            sendResponse(false, 'User not found', null, 404);
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
