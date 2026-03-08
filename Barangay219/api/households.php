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
    
    case 'get_member':
        getHouseholdMemberDetails();
        break;
    
    case 'update_member':
        updateHouseholdMember();
        break;
    
    case 'delete_member':
        deleteHouseholdMember();
        break;
    
    case 'update_household_details':
        updateHouseholdDetails();
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
 * Add household member (new table structure)
 */
function addHouseholdMember() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    // Sanitize and validate inputs
    $household_id = intval($_POST['household_id'] ?? 0);
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $middle_name = sanitizeInput($_POST['middle_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $suffix = sanitizeInput($_POST['suffix'] ?? '');
    $relationship_to_head = sanitizeInput($_POST['relationship_to_head'] ?? '');
    $date_of_birth = sanitizeInput($_POST['date_of_birth'] ?? '');
    $gender = sanitizeInput($_POST['gender'] ?? '');
    $civil_status = sanitizeInput($_POST['civil_status'] ?? '');
    $occupation = sanitizeInput($_POST['occupation'] ?? '');
    $government_id_type = sanitizeInput($_POST['government_id_type'] ?? '');
    $government_id_number = sanitizeInput($_POST['government_id_number'] ?? '');
    $voter_status = sanitizeInput($_POST['voter_status'] ?? 'Not Registered');
    $voter_id_number = sanitizeInput($_POST['voter_id_number'] ?? '');
    $contact_number = sanitizeInput($_POST['contact_number'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $is_head = intval($_POST['is_head'] ?? 0);
    $is_senior_citizen = intval($_POST['is_senior_citizen'] ?? 0);
    $is_pwd = intval($_POST['is_pwd'] ?? 0);
    $is_4ps_beneficiary = intval($_POST['is_4ps_beneficiary'] ?? 0);
    $remarks = sanitizeInput($_POST['remarks'] ?? '');
    
    // Validation
    if (!$household_id || empty($first_name) || empty($last_name) || empty($relationship_to_head) || 
        empty($date_of_birth) || empty($gender) || empty($civil_status)) {
        sendResponse(false, 'Required fields are missing', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        // Check if household exists
        $household = $db->fetchOne("SELECT id FROM households WHERE id = ?", [$household_id]);
        if (!$household) {
            sendResponse(false, 'Household not found', null, 404);
            return;
        }
        
        // Insert household member
        $sql = "INSERT INTO household_members (
                    household_id, first_name, middle_name, last_name, suffix, 
                    relationship_to_head, date_of_birth, gender, civil_status, 
                    occupation, government_id_type, government_id_number, 
                    voter_status, voter_id_number, contact_number, email, 
                    is_head, is_senior_citizen, is_pwd, is_4ps_beneficiary, remarks
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $db->query($sql, [
            $household_id, $first_name, $middle_name, $last_name, $suffix,
            $relationship_to_head, $date_of_birth, $gender, $civil_status,
            $occupation, $government_id_type, $government_id_number,
            $voter_status, $voter_id_number, $contact_number, $email,
            $is_head, $is_senior_citizen, $is_pwd, $is_4ps_beneficiary, $remarks
        ]);
        
        // Update household statistics
        updateHouseholdStatistics($household_id);
        
        sendResponse(true, 'Household member added successfully');
        
    } catch (Exception $e) {
        error_log("Add household member error: " . $e->getMessage());
        sendResponse(false, 'Error adding household member: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Get household member details
 */
function getHouseholdMemberDetails() {
    $id = intval($_GET['id'] ?? 0);
    
    if (!$id) {
        sendResponse(false, 'Member ID is required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        $sql = "SELECT * FROM household_members WHERE id = ?";
        $member = $db->fetchOne($sql, [$id]);
        
        if (!$member) {
            sendResponse(false, 'Member not found', null, 404);
            return;
        }
        
        sendResponse(true, 'Member retrieved successfully', $member);
        
    } catch (Exception $e) {
        error_log("Get household member error: " . $e->getMessage());
        sendResponse(false, 'Error retrieving member', null, 500);
    }
}

/**
 * Update household member
 */
function updateHouseholdMember() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $id = intval($_POST['member_id'] ?? 0);
    $household_id = intval($_POST['household_id'] ?? 0);
    
    if (!$id || !$household_id) {
        sendResponse(false, 'Member ID and Household ID are required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        // Check if member exists
        $existing = $db->fetchOne("SELECT id, household_id FROM household_members WHERE id = ?", [$id]);
        if (!$existing) {
            sendResponse(false, 'Member not found', null, 404);
            return;
        }
        
        // Sanitize inputs
        $first_name = sanitizeInput($_POST['first_name'] ?? '');
        $middle_name = sanitizeInput($_POST['middle_name'] ?? '');
        $last_name = sanitizeInput($_POST['last_name'] ?? '');
        $suffix = sanitizeInput($_POST['suffix'] ?? '');
        $relationship_to_head = sanitizeInput($_POST['relationship_to_head'] ?? '');
        $date_of_birth = sanitizeInput($_POST['date_of_birth'] ?? '');
        $gender = sanitizeInput($_POST['gender'] ?? '');
        $civil_status = sanitizeInput($_POST['civil_status'] ?? '');
        $occupation = sanitizeInput($_POST['occupation'] ?? '');
        $government_id_type = sanitizeInput($_POST['government_id_type'] ?? '');
        $government_id_number = sanitizeInput($_POST['government_id_number'] ?? '');
        $voter_status = sanitizeInput($_POST['voter_status'] ?? 'Not Registered');
        $voter_id_number = sanitizeInput($_POST['voter_id_number'] ?? '');
        $contact_number = sanitizeInput($_POST['contact_number'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $is_head = intval($_POST['is_head'] ?? 0);
        $is_senior_citizen = intval($_POST['is_senior_citizen'] ?? 0);
        $is_pwd = intval($_POST['is_pwd'] ?? 0);
        $is_4ps_beneficiary = intval($_POST['is_4ps_beneficiary'] ?? 0);
        $remarks = sanitizeInput($_POST['remarks'] ?? '');
        
        // Update member
        $sql = "UPDATE household_members SET 
                first_name = ?, middle_name = ?, last_name = ?, suffix = ?,
                relationship_to_head = ?, date_of_birth = ?, gender = ?, civil_status = ?,
                occupation = ?, government_id_type = ?, government_id_number = ?,
                voter_status = ?, voter_id_number = ?, contact_number = ?, email = ?,
                is_head = ?, is_senior_citizen = ?, is_pwd = ?, is_4ps_beneficiary = ?,
                remarks = ?
                WHERE id = ?";
        
        $db->query($sql, [
            $first_name, $middle_name, $last_name, $suffix,
            $relationship_to_head, $date_of_birth, $gender, $civil_status,
            $occupation, $government_id_type, $government_id_number,
            $voter_status, $voter_id_number, $contact_number, $email,
            $is_head, $is_senior_citizen, $is_pwd, $is_4ps_beneficiary,
            $remarks, $id
        ]);
        
        // Update household statistics
        updateHouseholdStatistics($household_id);
        
        sendResponse(true, 'Member updated successfully');
        
    } catch (Exception $e) {
        error_log("Update member error: " . $e->getMessage());
        sendResponse(false, 'Error updating member: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Delete household member
 */
function deleteHouseholdMember() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $id = intval($_POST['member_id'] ?? 0);
    $household_id = intval($_POST['household_id'] ?? 0);
    
    if (!$id || !$household_id) {
        sendResponse(false, 'Member ID and Household ID are required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        // Check if member is household head
        $member = $db->fetchOne("SELECT is_head FROM household_members WHERE id = ?", [$id]);
        if ($member && $member['is_head'] == 1) {
            sendResponse(false, 'Cannot delete household head', null, 400);
            return;
        }
        
        // Delete member
        $db->query("DELETE FROM household_members WHERE id = ?", [$id]);
        
        // Update household statistics
        updateHouseholdStatistics($household_id);
        
        sendResponse(true, 'Member deleted successfully');
        
    } catch (Exception $e) {
        error_log("Delete member error: " . $e->getMessage());
        sendResponse(false, 'Error deleting member: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Update household details (address, emergency contact, notes)
 */
function updateHouseholdDetails() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $id = intval($_POST['household_id'] ?? 0);
    
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
        
        // Sanitize inputs
        $house_number = sanitizeInput($_POST['house_number'] ?? '');
        $street = sanitizeInput($_POST['street'] ?? '');
        $purok_sitio = sanitizeInput($_POST['purok_sitio'] ?? '');
        $postal_code = sanitizeInput($_POST['postal_code'] ?? '1013');
        $emergency_contact_name = sanitizeInput($_POST['emergency_contact_name'] ?? '');
        $emergency_contact_phone = sanitizeInput($_POST['emergency_contact_phone'] ?? '');
        $special_notes = sanitizeInput($_POST['special_notes'] ?? '');
        
        // Update household
        $sql = "UPDATE households SET 
                house_number = ?, street = ?, purok_sitio = ?, postal_code = ?,
                emergency_contact_name = ?, emergency_contact_phone = ?, special_notes = ?
                WHERE id = ?";
        
        $db->query($sql, [
            $house_number, $street, $purok_sitio, $postal_code,
            $emergency_contact_name, $emergency_contact_phone, $special_notes, $id
        ]);
        
        sendResponse(true, 'Household details updated successfully');
        
    } catch (Exception $e) {
        error_log("Update household details error: " . $e->getMessage());
        sendResponse(false, 'Error updating household details: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Update household statistics (total members, adults, minors, seniors)
 */
function updateHouseholdStatistics($household_id) {
    try {
        $db = Database::getInstance();
        
        // Get all members with ages
        $members = $db->fetchAll(
            "SELECT age FROM household_members WHERE household_id = ?", 
            [$household_id]
        );
        
        $total = count($members);
        $adults = 0;
        $minors = 0;
        $seniors = 0;
        
        foreach ($members as $member) {
            $age = intval($member['age']);
            
            if ($age >= 60) {
                $seniors++;
                $adults++; // Seniors are also counted as adults
            } elseif ($age >= 18) {
                $adults++;
            } else {
                $minors++;
            }
        }
        
        // Update household
        $sql = "UPDATE households SET 
                total_members = ?, number_of_adults = ?, 
                number_of_minors = ?, number_of_seniors = ?
                WHERE id = ?";
        
        $db->query($sql, [$total, $adults, $minors, $seniors, $household_id]);
        
    } catch (Exception $e) {
        error_log("Update household statistics error: " . $e->getMessage());
        // Don't throw error, just log it
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
 * Get household members (from household_members table)
 */
function getHouseholdMembers() {
    $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    
    if (!$id) {
        sendResponse(false, 'Household ID is required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        $sql = "SELECT * FROM household_members WHERE household_id = ? ORDER BY is_head DESC, date_of_birth ASC";
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
