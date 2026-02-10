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
requireModuleAccess('households');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        listHouseholds();
        break;
    
    case 'get':
        getHousehold();
        break;
    
    case 'create':
        if (!canPerformModulePermission('households', 'can_create')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        createHousehold();
        break;
    
    case 'update':
        if (!canPerformModulePermission('households', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        updateHousehold();
        break;
    
    case 'delete':
        if (!canPerformModulePermission('households', 'can_delete')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        deleteHousehold();
        break;
    
    case 'members':
        getHouseholdMembers();
        break;
    
    case 'add_member':
        addHouseholdMember();
        break;
    
    case 'remove_member':
        removeHouseholdMember();
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
        $q = sanitizeInput($_GET['q'] ?? $_GET['search'] ?? '');
        $from = sanitizeInput($_GET['from'] ?? '');
        $to = sanitizeInput($_GET['to'] ?? '');
        $where = '1=1';
        $params = [];
        if (!empty($q)) {
            $term = '%' . $q . '%';
            $where = "(h.address LIKE ? OR CONCAT(r.first_name, ' ', r.last_name) LIKE ?)";
            $params = [$term, $term];
        }
        if (!empty($from)) {
            $where .= " AND DATE(h.registration_date) >= ?";
            $params[] = $from;
        }
        if (!empty($to)) {
            $where .= " AND DATE(h.registration_date) <= ?";
            $params[] = $to;
        }
        $sql = "SELECT h.*, 
                       CONCAT(r.first_name, ' ', COALESCE(r.middle_name, ''), ' ', r.last_name) as family_head_name,
                       r.contact_number as family_head_contact
                FROM households h
                LEFT JOIN residents r ON h.family_head_id = r.id
                WHERE $where
                ORDER BY h.registration_date DESC, h.id DESC";
        
        $households = $db->fetchAll($sql, $params);
        
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
    $total_members = max(1, intval($_POST['total_members'] ?? 1));
    
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
        
        // Insert household (allow provided total_members)
        $sql = "INSERT INTO households (family_head_id, address, total_members, registration_date) 
                VALUES (?, ?, ?, ?)";
        
        $db->query($sql, [$family_head_id, $address, $total_members, $registration_date]);
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
 * Add resident to household
 */
function addHouseholdMember() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    $household_id = intval($_POST['household_id'] ?? 0);
    $resident_id = intval($_POST['resident_id'] ?? 0);
    if (!$household_id || !$resident_id) {
        sendResponse(false, 'Household ID and resident ID are required', null, 400);
        return;
    }
    try {
        $db = Database::getInstance();
        $household = $db->fetchOne("SELECT id, total_members FROM households WHERE id = ?", [$household_id]);
        if (!$household) { sendResponse(false, 'Household not found', null, 404); return; }
        $resident = $db->fetchOne("SELECT id FROM residents WHERE id = ?", [$resident_id]);
        if (!$resident) { sendResponse(false, 'Resident not found', null, 404); return; }
        $db->query("UPDATE residents SET household_id = ? WHERE id = ?", [$household_id, $resident_id]);
        $count = $db->fetchOne("SELECT COUNT(*) as c FROM residents WHERE household_id = ?", [$household_id])['c'];
        $db->query("UPDATE households SET total_members = ? WHERE id = ?", [$count, $household_id]);
        sendResponse(true, 'Member added to household');
    } catch (Exception $e) {
        error_log("Add member error: " . $e->getMessage());
        sendResponse(false, 'Error adding member', null, 500);
    }
}

/**
 * Remove resident from household
 */
function removeHouseholdMember() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    $resident_id = intval($_POST['resident_id'] ?? 0);
    if (!$resident_id) {
        sendResponse(false, 'Resident ID is required', null, 400);
        return;
    }
    try {
        $db = Database::getInstance();
        $resident = $db->fetchOne("SELECT household_id FROM residents WHERE id = ?", [$resident_id]);
        if (!$resident || !$resident['household_id']) {
            sendResponse(false, 'Resident not in a household', null, 400);
            return;
        }
        $household_id = $resident['household_id'];
        $db->query("UPDATE residents SET household_id = NULL WHERE id = ?", [$resident_id]);
        $count = $db->fetchOne("SELECT COUNT(*) as c FROM residents WHERE household_id = ?", [$household_id])['c'];
        $db->query("UPDATE households SET total_members = ? WHERE id = ?", [$count ?: 1, $household_id]);
        sendResponse(true, 'Member removed from household');
    } catch (Exception $e) {
        error_log("Remove member error: " . $e->getMessage());
        sendResponse(false, 'Error removing member', null, 500);
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
