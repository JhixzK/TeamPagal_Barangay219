<?php
/**
 * E-Barangay Information Management System
 * Resident Information API
 */

header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireAnyRole([ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY]);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        listResidents();
        break;
    
    case 'get':
        getResident();
        break;
    
    case 'create':
        createResident();
        break;
    
    case 'update':
        updateResident();
        break;
    
    case 'delete':
        deleteResident();
        break;
    
    case 'search':
        searchResidents();
        break;
    
    default:
        sendResponse(false, 'Invalid action', null, 400);
        break;
}

/**
 * List all residents with pagination
 */
function listResidents() {
    try {
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? ITEMS_PER_PAGE);
        $offset = ($page - 1) * $limit;
        
        $db = Database::getInstance();
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM residents";
        $total = $db->fetchOne($countSql)['total'];
        
        // Get residents - ordered by ID so new residents appear at the end
        $sql = "SELECT r.*, h.address as household_address, h.total_members
                FROM residents r
                LEFT JOIN households h ON r.household_id = h.id
                ORDER BY r.id ASC
                LIMIT ? OFFSET ?";
        
        $residents = $db->fetchAll($sql, [$limit, $offset]);
        
        sendResponse(true, 'Residents retrieved successfully', [
            'residents' => $residents,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]);
        
    } catch (Exception $e) {
        error_log("List residents error: " . $e->getMessage());
        sendResponse(false, 'Error retrieving residents', null, 500);
    }
}

/**
 * Get single resident
 */
function getResident() {
    $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    
    if (!$id) {
        sendResponse(false, 'Resident ID is required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        $sql = "SELECT r.*, h.address as household_address, h.total_members, h.family_head_id
                FROM residents r
                LEFT JOIN households h ON r.household_id = h.id
                WHERE r.id = ?";
        
        $resident = $db->fetchOne($sql, [$id]);
        
        if (!$resident) {
            sendResponse(false, 'Resident not found', null, 404);
            return;
        }
        
        sendResponse(true, 'Resident retrieved successfully', $resident);
        
    } catch (Exception $e) {
        error_log("Get resident error: " . $e->getMessage());
        sendResponse(false, 'Error retrieving resident', null, 500);
    }
}

/**
 * Create new resident
 */
function createResident() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $middle_name = sanitizeInput($_POST['middle_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $suffix = sanitizeInput($_POST['suffix'] ?? '');
    $birth_date = $_POST['birth_date'] ?? '';
    $gender = sanitizeInput($_POST['gender'] ?? '');
    $civil_status = sanitizeInput($_POST['civil_status'] ?? '');
    $occupation = sanitizeInput($_POST['occupation'] ?? '');
    $citizenship = sanitizeInput($_POST['citizenship'] ?? 'Filipino');
    $address = sanitizeInput($_POST['address'] ?? '');
    $contact_number = sanitizeInput($_POST['contact_number'] ?? '');
    $household_id = intval($_POST['household_id'] ?? 0);
    $status = sanitizeInput($_POST['status'] ?? RESIDENT_ACTIVE);
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($birth_date) || empty($gender) || empty($address)) {
        sendResponse(false, 'First name, last name, birth date, gender, and address are required', null, 400);
        return;
    }
    
    $allowed_genders = ['male', 'female', 'other'];
    if (!in_array($gender, $allowed_genders)) {
        sendResponse(false, 'Invalid gender', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        $sql = "INSERT INTO residents (first_name, middle_name, last_name, suffix, birth_date, gender, 
                                      civil_status, occupation, citizenship, address, contact_number, 
                                      household_id, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $first_name,
            $middle_name ?: null,
            $last_name,
            $suffix ?: null,
            $birth_date,
            $gender,
            $civil_status ?: null,
            $occupation ?: null,
            $citizenship,
            $address,
            $contact_number ?: null,
            $household_id ?: null,
            $status
        ];
        
        $db->query($sql, $params);
        $residentId = $db->lastInsertId();
        
        // Get created resident
        $resident = $db->fetchOne("SELECT * FROM residents WHERE id = ?", [$residentId]);
        
        sendResponse(true, 'Resident created successfully', $resident);
        
    } catch (Exception $e) {
        error_log("Create resident error: " . $e->getMessage());
        sendResponse(false, 'Error creating resident', null, 500);
    }
}

/**
 * Update resident
 */
function updateResident() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $id = intval($_POST['id'] ?? 0);
    
    if (!$id) {
        sendResponse(false, 'Resident ID is required', null, 400);
        return;
    }
    
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $middle_name = sanitizeInput($_POST['middle_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $suffix = sanitizeInput($_POST['suffix'] ?? '');
    $birth_date = $_POST['birth_date'] ?? '';
    $gender = sanitizeInput($_POST['gender'] ?? '');
    $civil_status = sanitizeInput($_POST['civil_status'] ?? '');
    $occupation = sanitizeInput($_POST['occupation'] ?? '');
    $citizenship = sanitizeInput($_POST['citizenship'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $contact_number = sanitizeInput($_POST['contact_number'] ?? '');
    $household_id = intval($_POST['household_id'] ?? 0);
    $status = sanitizeInput($_POST['status'] ?? '');
    
    try {
        $db = Database::getInstance();
        
        // Check if resident exists
        $existing = $db->fetchOne("SELECT id FROM residents WHERE id = ?", [$id]);
        if (!$existing) {
            sendResponse(false, 'Resident not found', null, 404);
            return;
        }
        
        $sql = "UPDATE residents SET 
                first_name = ?, middle_name = ?, last_name = ?, suffix = ?, 
                birth_date = ?, gender = ?, civil_status = ?, occupation = ?, 
                citizenship = ?, address = ?, contact_number = ?, 
                household_id = ?, status = ?
                WHERE id = ?";
        
        $params = [
            $first_name,
            $middle_name ?: null,
            $last_name,
            $suffix ?: null,
            $birth_date,
            $gender,
            $civil_status ?: null,
            $occupation ?: null,
            $citizenship,
            $address,
            $contact_number ?: null,
            $household_id ?: null,
            $status,
            $id
        ];
        
        $db->query($sql, $params);
        
        // Get updated resident
        $resident = $db->fetchOne("SELECT * FROM residents WHERE id = ?", [$id]);
        
        sendResponse(true, 'Resident updated successfully', $resident);
        
    } catch (Exception $e) {
        error_log("Update resident error: " . $e->getMessage());
        sendResponse(false, 'Error updating resident', null, 500);
    }
}

/**
 * Delete resident (soft delete)
 */
function deleteResident() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $id = intval($_POST['id'] ?? 0);
    
    if (!$id) {
        sendResponse(false, 'Resident ID is required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        // Soft delete by setting status to inactive
        $sql = "UPDATE residents SET status = 'inactive' WHERE id = ?";
        $db->query($sql, [$id]);
        
        sendResponse(true, 'Resident deleted successfully', null);
        
    } catch (Exception $e) {
        error_log("Delete resident error: " . $e->getMessage());
        sendResponse(false, 'Error deleting resident', null, 500);
    }
}

/**
 * Search residents
 */
function searchResidents() {
    $query = sanitizeInput($_GET['q'] ?? $_POST['q'] ?? '');
    
    if (empty($query)) {
        sendResponse(false, 'Search query is required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        $searchTerm = "%{$query}%";
        $sql = "SELECT r.*, h.address as household_address
                FROM residents r
                LEFT JOIN households h ON r.household_id = h.id
                WHERE r.first_name LIKE ? 
                   OR r.middle_name LIKE ? 
                   OR r.last_name LIKE ? 
                   OR r.address LIKE ?
                   OR CONCAT(r.first_name, ' ', r.last_name) LIKE ?
                ORDER BY r.id ASC
                LIMIT 50";
        
        $residents = $db->fetchAll($sql, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        
        sendResponse(true, 'Search completed', $residents);
        
    } catch (Exception $e) {
        error_log("Search residents error: " . $e->getMessage());
        sendResponse(false, 'Error searching residents', null, 500);
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
