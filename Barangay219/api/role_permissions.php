<?php
/**
 * E-Barangay Information Management System
 * Role & Permission Management API
 */

header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('users');

if (!isSystemAdmin()) {
    sendResponse(false, 'Only Super Admin or Barangay Captain can access role permissions', null, 403);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_permissions':
        getRolePermissionsApi();
        break;

    case 'save_permissions':
        saveRolePermissionsApi();
        break;

    case 'list_official_users':
        listOfficialUsersApi();
        break;

    case 'get_user_permissions':
        getUserPermissionsApi();
        break;

    case 'save_user_permissions':
        saveUserPermissionsApi();
        break;

    case 'clear_user_permissions':
        clearUserPermissionsApi();
        break;

    default:
        sendResponse(false, 'Invalid action', null, 400);
        break;
}

function getRolePermissionsApi() {
    $role = sanitizeInput($_GET['role'] ?? '');
    if (empty($role)) {
        sendResponse(false, 'Role is required', null, 400);
        return;
    }

    $role_normalized = strtolower(trim($role));

    $allowed_roles = [ROLE_SUPER_ADMIN, ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY, ROLE_TREASURER, ROLE_KAGAWA, ROLE_SK_CHAIRMAN, ROLE_RESIDENT];
    if (!in_array($role_normalized, $allowed_roles, true)) {
        sendResponse(false, 'Invalid role', null, 400);
        return;
    }

    if ($role_normalized === ROLE_SUPER_ADMIN) {
        $modules = ['dashboard', 'applications', 'resident_applications', 'residents', 'households', 'certificates', 'blotters', 'complaints', 'announcements', 'reports', 'officials', 'users', 'profile'];
        $permissions = [];
        foreach ($modules as $module) {
            $permissions[$module] = [
                'can_access' => true,
                'can_create' => true,
                'can_edit' => true,
                'can_delete' => true
            ];
        }
        sendResponse(true, 'Role permissions loaded', ['role' => $role_normalized, 'permissions' => $permissions]);
        return;
    }

    if ($role_normalized === ROLE_BARANGAY_CAPTAIN) {
        $modules = ['dashboard', 'applications', 'resident_applications', 'residents', 'households', 'certificates', 'blotters', 'complaints', 'announcements', 'reports', 'officials', 'users', 'profile'];
        $permissions = [];
        foreach ($modules as $module) {
            $permissions[$module] = [
                'can_access' => true,
                // Access-only mode: keep keys for backward compatibility.
                'can_create' => true,
                'can_edit' => true,
                'can_delete' => true
            ];
        }
        sendResponse(true, 'Role permissions loaded', ['role' => $role_normalized, 'permissions' => $permissions]);
        return;
    }

    $permissions = getRolePermissions($role_normalized);
    sendResponse(true, 'Role permissions loaded', ['role' => $role_normalized, 'permissions' => $permissions]);
}

function saveRolePermissionsApi() {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    $role = sanitizeInput($payload['role'] ?? '');
    $permissions = $payload['permissions'] ?? [];

    if (empty($role)) {
        sendResponse(false, 'Role is required', null, 400);
        return;
    }

    $role_normalized = strtolower(trim($role));
    $allowed_roles = [ROLE_SUPER_ADMIN, ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY, ROLE_TREASURER, ROLE_KAGAWA, ROLE_SK_CHAIRMAN, ROLE_RESIDENT];
    if (!in_array($role_normalized, $allowed_roles, true)) {
        sendResponse(false, 'Invalid role', null, 400);
        return;
    }

    if ($role_normalized === ROLE_SUPER_ADMIN) {
        sendResponse(false, 'Super Admin permissions are fixed and cannot be edited', null, 403);
        return;
    }

    if ($role_normalized === ROLE_BARANGAY_CAPTAIN) {
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

            // Access-only mode: accept payload with only can_access.
            $can_access = !empty($perms['can_access']) ? 1 : 0;
            // Mirror access into other columns so older checks/UI stay consistent.
            $can_create = $can_access;
            $can_edit = $can_access;
            $can_delete = $can_access;

            $db->query(
                "INSERT INTO role_permissions (role, module, can_access, can_create, can_edit, can_delete)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE can_access = VALUES(can_access), can_create = VALUES(can_create), can_edit = VALUES(can_edit), can_delete = VALUES(can_delete)",
                [$role_normalized, $module, $can_access, $can_create, $can_edit, $can_delete]
            );
        }

        $db->commit();
        $roleLabel = ucwords(str_replace('_', ' ', $role_normalized));
        logActivity('update', 'role_permissions', null, [
            'role' => $role_normalized,
            'description' => 'Updated access permissions for ' . $roleLabel,
        ]);
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

function getAllowedRoles() {
    return [ROLE_SUPER_ADMIN, ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY, ROLE_TREASURER, ROLE_KAGAWA, ROLE_SK_CHAIRMAN, ROLE_RESIDENT];
}

function getAllowedModules() {
    return ['dashboard', 'applications', 'resident_applications', 'residents', 'households', 'certificates', 'blotters', 'complaints', 'announcements', 'reports', 'users', 'profile'];
}

function getOfficialAssignableRoles() {
    return [ROLE_SUPER_ADMIN, ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY, ROLE_TREASURER, ROLE_KAGAWA, ROLE_SK_CHAIRMAN];
}

function userPermissionsTableExistsApi() {
    return userPermissionsTableExists();
}

function listOfficialUsersApi() {
    try {
        $db = Database::getInstance();
        $roles = getOfficialAssignableRoles();
        $placeholders = implode(', ', array_fill(0, count($roles), '?'));
        $sql = "SELECT u.id, u.username, u.role, u.status, r.first_name, r.middle_name, r.last_name
                FROM users u
                LEFT JOIN residents r ON u.resident_id = r.id
                WHERE u.role IN ($placeholders) AND u.status = 'active'
                ORDER BY r.last_name ASC, r.first_name ASC, u.username ASC";
        $rows = $db->fetchAll($sql, $roles);
        $out = [];
        foreach ($rows as $row) {
            $full = trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            $out[] = [
                'user_id' => (int)$row['id'],
                'username' => (string)($row['username'] ?? ''),
                'role' => strtolower(trim((string)($row['role'] ?? ''))),
                'full_name' => $full !== '' ? preg_replace('/\s+/', ' ', $full) : (string)($row['username'] ?? ''),
                'has_custom_permissions' => !empty(getUserPermissions((int)$row['id'])),
            ];
        }
        sendResponse(true, 'Official users loaded', $out);
    } catch (Exception $e) {
        sendResponse(false, 'Error loading official users', null, 500);
    }
}

function findOfficialUserById($userId) {
    $userId = (int)$userId;
    if ($userId <= 0) {
        return null;
    }
    try {
        $db = Database::getInstance();
        $row = $db->fetchOne("SELECT id, role, status FROM users WHERE id = ? LIMIT 1", [$userId]);
    } catch (Exception $e) {
        return null;
    }
    if (!$row) {
        return null;
    }
    $role = strtolower(trim((string)($row['role'] ?? '')));
    if (!in_array($role, getOfficialAssignableRoles(), true)) {
        return null;
    }
    // Barangay Captain remains fixed and cannot be edited, including per-user overrides.
    if ($role === ROLE_BARANGAY_CAPTAIN) {
        return null;
    }
    return [
        'id' => (int)$row['id'],
        'role' => $role,
        'status' => strtolower(trim((string)($row['status'] ?? ''))),
    ];
}

function getUserPermissionsApi() {
    $userId = (int)($_GET['user_id'] ?? 0);
    if ($userId <= 0) {
        sendResponse(false, 'User ID is required', null, 400);
        return;
    }
    $user = findOfficialUserById($userId);
    if (!$user) {
        sendResponse(false, 'Official user not found', null, 404);
        return;
    }

    $rolePermissions = getRolePermissions($user['role']);
    $custom = getUserPermissions($userId);
    $effective = array_replace($rolePermissions, $custom);
    sendResponse(true, 'User permissions loaded', [
        'user_id' => $userId,
        'role' => $user['role'],
        'has_custom_permissions' => !empty($custom),
        'permissions' => $effective,
        'custom_permissions' => $custom,
    ]);
}

function saveUserPermissionsApi() {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    $userId = (int)($payload['user_id'] ?? 0);
    $permissions = $payload['permissions'] ?? [];

    if ($userId <= 0) {
        sendResponse(false, 'User ID is required', null, 400);
        return;
    }

    $user = findOfficialUserById($userId);
    if (!$user) {
        sendResponse(false, 'Official user not found', null, 404);
        return;
    }

    if (!is_array($permissions)) {
        sendResponse(false, 'Invalid permissions payload', null, 400);
        return;
    }

    if (!userPermissionsTableExistsApi()) {
        sendResponse(false, 'user_permissions table is missing. Run the migration to create it.', null, 500);
        return;
    }

    $allowedModules = getAllowedModules();
    try {
        $db = Database::getInstance();
        $db->beginTransaction();

        // Replace existing overrides (clean source of truth per user).
        $db->query("DELETE FROM user_permissions WHERE user_id = ?", [$userId]);

        foreach ($permissions as $module => $perms) {
            if (!in_array($module, $allowedModules, true)) {
                continue;
            }

            $can_access = !empty($perms['can_access']) ? 1 : 0;
            $can_create = array_key_exists('can_create', (array)$perms) ? (!empty($perms['can_create']) ? 1 : 0) : $can_access;
            $can_edit = array_key_exists('can_edit', (array)$perms) ? (!empty($perms['can_edit']) ? 1 : 0) : $can_access;
            $can_delete = array_key_exists('can_delete', (array)$perms) ? (!empty($perms['can_delete']) ? 1 : 0) : $can_access;

            $db->query(
                "INSERT INTO user_permissions (user_id, module, can_access, can_create, can_edit, can_delete)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$userId, $module, $can_access, $can_create, $can_edit, $can_delete]
            );
        }

        $db->commit();
        logActivity('update', 'user_permissions', $userId, [
            'user_id' => $userId,
            'description' => 'Updated custom permissions for user #' . $userId,
        ]);
        sendResponse(true, 'Custom permissions saved', null);
    } catch (Exception $e) {
        try {
            $db->rollback();
        } catch (Exception $rollbackError) {
        }
        $message = 'Error saving custom permissions';
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $message .= ': ' . $e->getMessage();
        }
        sendResponse(false, $message, null, 500);
    }
}

function clearUserPermissionsApi() {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    $userId = (int)($payload['user_id'] ?? 0);

    if ($userId <= 0) {
        sendResponse(false, 'User ID is required', null, 400);
        return;
    }

    $user = findOfficialUserById($userId);
    if (!$user) {
        sendResponse(false, 'Official user not found', null, 404);
        return;
    }

    if (!userPermissionsTableExistsApi()) {
        sendResponse(false, 'user_permissions table is missing. Run the migration to create it.', null, 500);
        return;
    }

    try {
        $db = Database::getInstance();
        $db->query("DELETE FROM user_permissions WHERE user_id = ?", [$userId]);
        logActivity('clear', 'user_permissions', $userId, [
            'user_id' => $userId,
            'description' => 'Cleared custom permissions for user #' . $userId,
        ]);
        sendResponse(true, 'Custom permissions cleared. User now follows role defaults.', null);
    } catch (Exception $e) {
        sendResponse(false, 'Error clearing custom permissions', null, 500);
    }
}

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
