<?php
/**
 * E-Barangay Information Management System
 * Household Management API
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
        listHouseholds();
        break;
    
    case 'get':
        getHousehold();
        break;
    
    case 'create':
        createHousehold();
        break;
    
    case 'update':
        updateHousehold();
        break;
    
    case 'delete':
        deleteHousehold();
        break;
    
    case 'members':
        getHouseholdMembers();
        break;
    
    default:
        sendResponse(false, 'Invalid action', null, 400);
        break;
}

/**
 * List all households
 */
function listHouseholds() {
    try {
        $db = Database::getInstance();
        
        $sql = "SELECT h.*, 
                       CONCAT(r.first_name, ' ', COALESCE(r.middle_name, ''), ' ', r.last_name) as family_head_name,
                       r.contact_number as family_head_contact
                FROM households h
                LEFT JOIN residents r ON h.family_head_id = r.id
                ORDER BY h.registration_date DESC, h.id DESC";
        
        $households = $db->fetchAll($sql);
        
        sendResponse(true, 'Households retrieved successfully', $households);
        
    } catch (Exception $e) {
        error_log("List households error: " . $e->getMessage());
        sendResponse(false, 'Error retrieving households', null, 500);
    }
}

/**
 * Get single household with members
 */
function getHousehold() {
    $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    
    if (!$id) {
        sendResponse(false, 'Household ID is required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        $sql = "SELECT h.*, 
                       CONCAT(r.first_name, ' ', COALESCE(r.middle_name, ''), ' ', r.last_name) as family_head_name
                FROM households h
                LEFT JOIN residents r ON h.family_head_id = r.id
                WHERE h.id = ?";
        
        $household = $db->fetchOne($sql, [$id]);
        
        if (!$household) {
            sendResponse(false, 'Household not found', null, 404);
            return;
        }
        
        // Get household members
        $membersSql = "SELECT * FROM residents WHERE household_id = ? ORDER BY birth_date";
        $members = $db->fetchAll($membersSql, [$id]);
        $household['members'] = $members;
        
        sendResponse(true, 'Household retrieved successfully', $household);
        
    } catch (Exception $e) {
        error_log("Get household error: " . $e->getMessage());
        sendResponse(false, 'Error retrieving household', null, 500);
    }
}

/**
 * Create new household
 */
function createHousehold() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $family_head_id = intval($_POST['family_head_id'] ?? 0);
    $address = sanitizeInput($_POST['address'] ?? '');
    $registration_date = $_POST['registration_date'] ?? date('Y-m-d');
    
    // Validation
    if (!$family_head_id || empty($address)) {
        sendResponse(false, 'Family head ID and address are required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        // Check if family head exists
        $familyHead = $db->fetchOne("SELECT id FROM residents WHERE id = ?", [$family_head_id]);
        if (!$familyHead) {
            sendResponse(false, 'Family head not found', null, 404);
            return;
        }
        
        // Insert household
        $sql = "INSERT INTO households (family_head_id, address, total_members, registration_date) 
                VALUES (?, ?, 1, ?)";
        
        $db->query($sql, [$family_head_id, $address, $registration_date]);
        $householdId = $db->lastInsertId();
        
        // Update resident's household_id
        $db->query("UPDATE residents SET household_id = ? WHERE id = ?", [$householdId, $family_head_id]);
        
        // Get created household
        $household = $db->fetchOne("SELECT * FROM households WHERE id = ?", [$householdId]);
        
        sendResponse(true, 'Household created successfully', $household);
        
    } catch (Exception $e) {
        error_log("Create household error: " . $e->getMessage());
        sendResponse(false, 'Error creating household', null, 500);
    }
}

/**
 * Update household
 */
function updateHousehold() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $id = intval($_POST['id'] ?? 0);
    $family_head_id = intval($_POST['family_head_id'] ?? 0);
    $address = sanitizeInput($_POST['address'] ?? '');
    $total_members = intval($_POST['total_members'] ?? 0);
    
    if (!$id) {
        sendResponse(false, 'Household ID is required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        // Check if household exists
        $existing = $db->fetchOne("SELECT id FROM households WHERE id = ?", [$id]);
        if (!$existing) {
            sendResponse(false, 'Household not found', null, 404);
            return;
        }
        
        $updates = [];
        $params = [];
        
        if ($family_head_id > 0) {
            $updates[] = "family_head_id = ?";
            $params[] = $family_head_id;
        }
        
        if (!empty($address)) {
            $updates[] = "address = ?";
            $params[] = $address;
        }
        
        if ($total_members > 0) {
            $updates[] = "total_members = ?";
            $params[] = $total_members;
        }
        
        if (empty($updates)) {
            sendResponse(false, 'No fields to update', null, 400);
            return;
        }
        
        $params[] = $id;
        $sql = "UPDATE households SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $db->query($sql, $params);
        
        // Get updated household
        $household = $db->fetchOne("SELECT * FROM households WHERE id = ?", [$id]);
        
        sendResponse(true, 'Household updated successfully', $household);
        
    } catch (Exception $e) {
        error_log("Update household error: " . $e->getMessage());
        sendResponse(false, 'Error updating household', null, 500);
    }
}

/**
 * Delete household
 */
function deleteHousehold() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $id = intval($_POST['id'] ?? 0);
    
    if (!$id) {
        sendResponse(false, 'Household ID is required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        // Remove household_id from residents
        $db->query("UPDATE residents SET household_id = NULL WHERE household_id = ?", [$id]);
        
        // Delete household
        $db->query("DELETE FROM households WHERE id = ?", [$id]);
        
        sendResponse(true, 'Household deleted successfully', null);
        
    } catch (Exception $e) {
        error_log("Delete household error: " . $e->getMessage());
        sendResponse(false, 'Error deleting household', null, 500);
    }
}

/**
 * Get household members
 */
function getHouseholdMembers() {
    $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    
    if (!$id) {
        sendResponse(false, 'Household ID is required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        $sql = "SELECT * FROM residents WHERE household_id = ? ORDER BY birth_date";
        $members = $db->fetchAll($sql, [$id]);
        
        sendResponse(true, 'Household members retrieved successfully', $members);
        
    } catch (Exception $e) {
        error_log("Get household members error: " . $e->getMessage());
        sendResponse(false, 'Error retrieving household members', null, 500);
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
