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
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        sendResponse(false, 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters', null, 400);
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
            if (strlen($password) < PASSWORD_MIN_LENGTH) {
                sendResponse(false, 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters', null, 400);
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
