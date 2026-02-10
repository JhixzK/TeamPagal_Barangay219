<?php
/**
 * E-Barangay Resident Applications Management System - API
 * Handles application submission, tracking, and staff management for barangay services
 * 
 * Resident Endpoints (Public - for authenticated residents):
 * - get-types: Get available application types
 * - submit: Submit new application
 * - list-my: Get resident's own applications
 * - get: Get specific application details
 * - track: Track application by reference number
 * - upload-document: Upload supporting document
 * 
 * Staff Endpoints (requires staff role):
 * - list-all: Get all applications with filtering and sorting
 * - get-queue: Get pending applications in priority order
 * - approve: Approve application with scheduled release date
 * - reject: Reject application with reason
 * - release: Mark application as released
 * - add-notes: Add processing notes
 * - get-history: Get application audit trail
 * - get-statistics: Get application statistics and reports
 */

header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$action = preg_replace('/[^a-z0-9_-]/', '', strtolower($action));

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse($success = false, $message = '', $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

function getDB() {
    static $db = null;
    if ($db === null) {
        $db = Database::getInstance();
    }
    return $db;
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? 0;
}

function getCurrentUserRole() {
    return $_SESSION['role'] ?? '';
}

function isStaff() {
    $role = getCurrentUserRole();
    return in_array($role, [ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY, ROLE_TREASURER, ROLE_KAGAWA]);
}

function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}


// ============================================================================
// RESIDENT ENDPOINTS - APPLICATION SUBMISSION & TRACKING
// ============================================================================

/**
 * Get available application types
 */
function getApplicationTypes() {
    $db = getDB();
    $conn = $db->getConnection();
    
    try {
        $sql = "SELECT * FROM application_types WHERE is_active = TRUE ORDER BY type_name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(true, 'Application types retrieved', $types);
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving application types: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Submit new application by resident
 */
function submitApplication() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'POST method required', null, 405);
    }
    
    $db = getDB();
    $conn = $db->getConnection();
    
    // Only residents can submit applications
    if (getCurrentUserRole() !== ROLE_RESIDENT) {
        sendResponse(false, 'Only residents can submit applications', null, 403);
    }
    
    $resident_id = getCurrentUserId();
    $application_type_id = intval($_POST['application_type_id'] ?? 0);
    $purpose = sanitizeInput($_POST['purpose'] ?? '');
    $priority_level = sanitizeInput($_POST['priority_level'] ?? 'normal');
    
    if (!$application_type_id) {
        sendResponse(false, 'Application type is required', null, 400);
    }
    
    if (!in_array($priority_level, ['normal', 'urgent', 'vip'])) {
        $priority_level = 'normal';
    }
    
    try {
        // Verify resident exists and is active
        $stmt = $conn->prepare("SELECT id FROM residents WHERE id = ? AND status = 'active'");
        $stmt->execute([$resident_id]);
        if (!$stmt->fetch()) {
            sendResponse(false, 'Resident not found or inactive', null, 404);
        }
        
        // Verify application type exists and is active
        $stmt = $conn->prepare("SELECT id FROM application_types WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$application_type_id]);
        if (!$stmt->fetch()) {
            sendResponse(false, 'Invalid application type', null, 400);
        }
        
        // Generate unique reference number: APP + YYMMNNNNNN
        $year = date('y');
        $month = date('m');
        $prefix = 'APP' . $year . $month;
        
        $stmt = $conn->prepare(
            "SELECT IFNULL(MAX(CAST(SUBSTRING(reference_number, -5) AS UNSIGNED)), 0) + 1 as seq
             FROM applications WHERE reference_number LIKE ?"
        );
        $stmt->execute([$prefix . '%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $sequence = $result['seq'];
        $reference_number = $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);
        
        // Insert application
        $ip = sanitizeInput($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $sql = "INSERT INTO applications 
                (resident_id, application_type_id, reference_number, purpose, 
                 submission_date, submission_ip, status, priority_level)
                VALUES (?, ?, ?, ?, NOW(), ?, 'pending', ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$resident_id, $application_type_id, $reference_number, $purpose, $ip, $priority_level]);
        
        $applicationId = $conn->lastInsertId();
        
        // Log to history
        $sql = "INSERT INTO application_history 
                (application_id, action, action_by, new_status, description, ip_address)
                VALUES (?, 'submitted', ?, 'pending', 'Application submitted by resident', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$applicationId, $resident_id, $ip]);
        
        sendResponse(true, 'Application submitted successfully', [
            'application_id' => $applicationId,
            'reference_number' => $reference_number,
            'status' => 'pending',
            'submission_date' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        error_log('Submit application error: ' . $e->getMessage());
        sendResponse(false, 'Error submitting application: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Get resident's own applications
 */
function getMyApplications() {
    $db = getDB();
    $conn = $db->getConnection();
    
    $resident_id = getCurrentUserId();
    $status = sanitizeInput($_GET['status'] ?? '');
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);
    
    try {
        $where = 'a.resident_id = ?';
        $params = [$resident_id];
        
        if ($status) {
            $where .= ' AND a.status = ?';
            $params[] = $status;
        }
        
        $sql = "SELECT a.*, 
                at.type_name, at.category, at.processing_days,
                COUNT(DISTINCT att.id) as document_count
                FROM applications a
                LEFT JOIN application_types at ON a.application_type_id = at.id
                LEFT JOIN application_attachments att ON a.id = att.application_id
                WHERE $where
                GROUP BY a.id
                ORDER BY a.submission_date DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Count total
        $countSql = "SELECT COUNT(*) as total FROM applications a WHERE $where";
        $countParams = array_slice($params, 0, -2);
        $stmt = $conn->prepare($countSql);
        $stmt->execute($countParams);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        sendResponse(true, 'Applications retrieved', [
            'applications' => $applications,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
        
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving applications: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Get specific application details
 */
function getApplication() {
    $db = getDB();
    $conn = $db->getConnection();
    
    $application_id = intval($_GET['id'] ?? 0);
    $resident_id = getCurrentUserId();
    
    if (!$application_id) {
        sendResponse(false, 'Application ID is required', null, 400);
    }
    
    try {
        $sql = "SELECT a.*, 
                at.type_name, at.processing_days, at.category, at.fee_amount,
                CONCAT(r.first_name, ' ', r.middle_name, ' ', r.last_name) as resident_name,
                r.address as resident_address, r.contact_number,
                CONCAT(u.first_name, ' ', u.last_name) as reviewed_by_name
                FROM applications a
                LEFT JOIN application_types at ON a.application_type_id = at.id
                LEFT JOIN residents r ON a.resident_id = r.id
                LEFT JOIN users u ON a.reviewed_by = u.id
                WHERE a.id = ?";
        
        // Residents can only view their own applications unless staff
        if (!isStaff()) {
            $sql .= " AND a.resident_id = ?";
            $params = [$application_id, $resident_id];
        } else {
            $params = [$application_id];
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$application) {
            sendResponse(false, 'Application not found or access denied', null, 404);
        }
        
        // Get attachments
        $stmt = $conn->prepare(
            "SELECT id, file_name, original_filename, file_type, file_size, document_type, uploaded_at
             FROM application_attachments WHERE application_id = ? ORDER BY uploaded_at DESC"
        );
        $stmt->execute([$application_id]);
        $application['attachments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(true, 'Application retrieved', $application);
        
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving application: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Track application by reference number (public)
 */
function trackApplication() {
    $db = getDB();
    $conn = $db->getConnection();
    
    $reference_number = sanitizeInput($_GET['reference'] ?? '');
    
    if (!$reference_number) {
        sendResponse(false, 'Reference number is required', null, 400);
    }
    
    try {
        $sql = "SELECT a.id, a.reference_number, a.status, a.submission_date, 
                a.scheduled_release_date, a.actual_release_date, a.remarks,
                a.rejection_reason, a.estimated_completion, a.processing_notes,
                at.type_name, at.processing_days, at.category,
                COUNT(DISTINCT att.id) as document_count,
                DATEDIFF(CURDATE(), DATE(a.submission_date)) as days_pending
                FROM applications a
                LEFT JOIN application_types at ON a.application_type_id = at.id
                LEFT JOIN application_attachments att ON a.id = att.application_id
                WHERE a.reference_number = ?
                GROUP BY a.id";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$reference_number]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$application) {
            sendResponse(false, 'Application not found', null, 404);
        }
        
        sendResponse(true, 'Application tracked', $application);
        
    } catch (Exception $e) {
        sendResponse(false, 'Error tracking application: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Upload document for application
 */
function uploadDocument() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'POST method required', null, 405);
    }
    
    $application_id = intval($_POST['application_id'] ?? 0);
    $document_type = sanitizeInput($_POST['document_type'] ?? '');
    
    if (!$application_id) {
        sendResponse(false, 'Application ID is required', null, 400);
    }
    
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        sendResponse(false, 'No file uploaded or upload error', null, 400);
    }
    
    $db = getDB();
    $conn = $db->getConnection();
    
    try {
        // Verify application
        $stmt = $conn->prepare("SELECT resident_id, status FROM applications WHERE id = ?");
        $stmt->execute([$application_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$application) {
            sendResponse(false, 'Application not found', null, 404);
        }
        
        // Check permissions
        if ($application['resident_id'] !== getCurrentUserId() && !isStaff()) {
            sendResponse(false, 'Access denied', null, 403);
        }
        
        // Validate file
        $file = $_FILES['file'];
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 
                         'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        
        if (!in_array($file['type'], $allowed_types)) {
            sendResponse(false, 'File type not allowed. Allowed: PDF, JPEG, PNG, DOC, DOCX', null, 400);
        }
        
        if ($file['size'] > 5242880) { // 5MB limit
            sendResponse(false, 'File size exceeds 5MB limit', null, 400);
        }
        
        // Create uploads directory
        $upload_dir = __DIR__ . '/../uploads/applications/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $ext = ltrim(strrchr($file['name'], '.'), '.');
        $unique_filename = 'app_' . $application_id . '_' . time() . '.' . $ext;
        
        if (!move_uploaded_file($file['tmp_name'], $upload_dir . $unique_filename)) {
            sendResponse(false, 'Failed to upload file', null, 500);
        }
        
        // Save to database
        $sql = "INSERT INTO application_attachments 
                (application_id, file_name, original_filename, file_path, file_type, file_size, document_type, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $application_id,
            $unique_filename,
            $file['name'],
            '/uploads/applications/' . $unique_filename,
            $file['type'],
            $file['size'],
            $document_type,
            getCurrentUserId()
        ]);
        
        $attachmentId = $conn->lastInsertId();
        
        // Log to history
        $sql = "INSERT INTO application_history 
                (application_id, action, action_by, description)
                VALUES (?, 'document_uploaded', ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$application_id, getCurrentUserId(), 'Document uploaded: ' . $file['name']]);
        
        sendResponse(true, 'Document uploaded successfully', [
            'attachment_id' => $attachmentId,
            'file_name' => $unique_filename,
            'original_filename' => $file['name']
        ]);
        
    } catch (Exception $e) {
        error_log('Upload document error: ' . $e->getMessage());
        sendResponse(false, 'Error uploading document: ' . $e->getMessage(), null, 500);
    }
}

// ============================================================================
// STAFF ENDPOINTS - APPLICATION MANAGEMENT
// ============================================================================

/**
 * Get all applications with filtering (Staff only)
 */
function getAllApplications() {
    if (!canModulePermission('applications', 'access')) {
        sendResponse(false, 'Access denied', null, 403);
    }
    
    $db = getDB();
    $conn = $db->getConnection();
    
    $status = sanitizeInput($_GET['status'] ?? '');
    $type = sanitizeInput($_GET['type'] ?? '');
    $priority = sanitizeInput($_GET['priority'] ?? '');
    $search = sanitizeInput($_GET['search'] ?? '');
    $limit = min(intval($_GET['limit'] ?? 100), 1000);
    $offset = intval($_GET['offset'] ?? 0);
    
    try {
        $where = ['1=1'];
        $params = [];
        
        if ($status) {
            $where[] = 'a.status = ?';
            $params[] = $status;
        }
        
        if ($type) {
            $where[] = 'a.application_type_id = ?';
            $params[] = intval($type);
        }
        
        if ($priority) {
            $where[] = 'a.priority_level = ?';
            $params[] = $priority;
        }
        
        if ($search) {
            $where[] = '(a.reference_number LIKE ? OR CONCAT(r.first_name, r.last_name) LIKE ? OR r.contact_number LIKE ?)';
            $search_param = '%' . $search . '%';
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT a.id, a.reference_number, a.status, a.priority_level,
                a.submission_date, a.scheduled_release_date,
                at.type_name, at.processing_days,
                CONCAT(r.first_name, ' ', r.middle_name, ' ', r.last_name) as resident_name,
                r.contact_number, r.address,
                CONCAT(u.first_name, ' ', u.last_name) as reviewed_by_name,
                COUNT(DISTINCT att.id) as document_count,
                DATEDIFF(CURDATE(), DATE(a.submission_date)) as days_pending
                FROM applications a
                LEFT JOIN application_types at ON a.application_type_id = at.id
                LEFT JOIN residents r ON a.resident_id = r.id
                LEFT JOIN users u ON a.reviewed_by = u.id
                LEFT JOIN application_attachments att ON a.id = att.application_id
                WHERE $whereClause
                GROUP BY a.id
                ORDER BY a.priority_level DESC, a.submission_date ASC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM applications a 
                     LEFT JOIN residents r ON a.resident_id = r.id
                     WHERE $whereClause";
        $countParams = array_slice($params, 0, -2);
        $stmt = $conn->prepare($countSql);
        $stmt->execute($countParams);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        sendResponse(true, 'Applications retrieved', [
            'applications' => $applications,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
        
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving applications: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Get pending application queue  (Staff only)
 */
function getApplicationQueue() {
    if (!canModulePermission('applications', 'access')) {
        sendResponse(false, 'Access denied', null, 403);
    }
    
    $db = getDB();
    $conn = $db->getConnection();
    
    try {
        $sql = "SELECT 
                a.id,
                a.reference_number,
                CONCAT(r.first_name, ' ', r.middle_name, ' ', r.last_name) as resident_name,
                r.contact_number,
                r.address,
                at.type_name,
                a.purpose,
                a.status,
                a.priority_level,
                a.submission_date,
                a.scheduled_release_date,
                CONCAT(u.first_name, ' ', u.last_name) as reviewed_by,
                DATEDIFF(CURDATE(), DATE(a.submission_date)) as days_pending,
                a.documents_complete,
                at.processing_days,
                DATE_ADD(a.submission_date, INTERVAL at.processing_days DAY) as estimated_date,
                COUNT(DISTINCT att.id) as document_count
                FROM applications a
                LEFT JOIN residents r ON a.resident_id = r.id
                LEFT JOIN application_types at ON a.application_type_id = at.id
                LEFT JOIN users u ON a.reviewed_by = u.id
                LEFT JOIN application_attachments att ON a.id = att.application_id
                WHERE a.status IN ('pending', 'under_review', 'approved')
                GROUP BY a.id
                ORDER BY a.priority_level DESC, a.submission_date ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $queue = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(true, 'Application queue retrieved', $queue);
        
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving queue: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Approve application (Staff only)
 */
function approveApplication() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'POST method required', null, 405);
    }
    
    if (!canModulePermission('applications', 'edit')) {
        sendResponse(false, 'Access denied', null, 403);
    }
    
    $db = getDB();
    $conn = $db->getConnection();
    
    $application_id = intval($_POST['id'] ?? 0);
    $remarks = sanitizeInput($_POST['remarks'] ?? '');
    $scheduled_release = sanitizeInput($_POST['scheduled_release_date'] ?? '');
    
    if (!$application_id) {
        sendResponse(false, 'Application ID is required', null, 400);
    }
    
    try {
        // Get application and type info
        $stmt = $conn->prepare(
            "SELECT a.status, at.processing_days 
             FROM applications a
             LEFT JOIN application_types at ON a.application_type_id = at.id
             WHERE a.id = ?"
        );
        $stmt->execute([$application_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$application) {
            sendResponse(false, 'Application not found', null, 404);
        }
        
        // Calculate scheduled release date if not provided
        if (!$scheduled_release) {
            $scheduled_release = date('Y-m-d', strtotime('+' . ($application['processing_days'] ?? 3) . ' days'));
        }
        
        // Update application
        $sql = "UPDATE applications 
                SET status = 'approved',
                    reviewed_by = ?,
                    reviewed_date = NOW(),
                    scheduled_release_date = ?,
                    remarks = ?,
                    estimated_completion = DATE_ADD(NOW(), INTERVAL ? DAY),
                    updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            getCurrentUserId(),
            $scheduled_release,
            $remarks,
            $application['processing_days'] ?? 3,
            $application_id
        ]);
        
        // Log to history
        $sql = "INSERT INTO application_history 
                (application_id, action, action_by, old_status, new_status, description, ip_address)
                VALUES (?, 'approved', ?, ?, 'approved', ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $application_id,
            getCurrentUserId(),
            $application['status'] ?? 'pending',
            $remarks,
            sanitizeInput($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')
        ]);
        
        sendResponse(true, 'Application approved successfully', [
            'status' => 'approved',
            'scheduled_release' => $scheduled_release
        ]);
        
    } catch (Exception $e) {
        error_log('Approve application error: ' . $e->getMessage());
        sendResponse(false, 'Error approving application: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Reject application (Staff only)
 */
function rejectApplication() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'POST method required', null, 405);
    }
    
    if (!canModulePermission('applications', 'edit')) {
        sendResponse(false, 'Access denied', null, 403);
    }
    
    $db = getDB();
    $conn = $db->getConnection();
    
    $application_id = intval($_POST['id'] ?? 0);
    $reason = sanitizeInput($_POST['reason'] ?? '');
    
    if (!$application_id) {
        sendResponse(false, 'Application ID is required', null, 400);
    }
    
    if (!$reason) {
        sendResponse(false, 'Rejection reason is required', null, 400);
    }
    
    try {
        $sql = "UPDATE applications 
                SET status = 'rejected',
                    reviewed_by = ?,
                    reviewed_date = NOW(),
                    rejection_reason = ?,
                    updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([getCurrentUserId(), $reason, $application_id]);
        
        // Log to history
        $sql = "INSERT INTO application_history 
                (application_id, action, action_by, new_status, description, ip_address)
                VALUES (?, 'rejected', ?, 'rejected', ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $application_id,
            getCurrentUserId(),
            $reason,
            sanitizeInput($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')
        ]);
        
        sendResponse(true, 'Application rejected', ['status' => 'rejected']);
        
    } catch (Exception $e) {
        error_log('Reject application error: ' . $e->getMessage());
        sendResponse(false, 'Error rejecting application: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Release application (Staff only)
 */
function releaseApplication() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'POST method required', null, 405);
    }
    
    if (!canModulePermission('applications', 'edit')) {
        sendResponse(false, 'Access denied', null, 403);
    }
    
    $db = getDB();
    $conn = $db->getConnection();
    
    $application_id = intval($_POST['id'] ?? 0);
    $release_date = sanitizeInput($_POST['release_date'] ?? date('Y-m-d'));
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    if (!$application_id) {
        sendResponse(false, 'Application ID is required', null, 400);
    }
    
    try {
        $sql = "UPDATE applications 
                SET status = 'released',
                    actual_release_date = ?,
                    released_by = ?,
                    processing_notes = ?,
                    updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$release_date, getCurrentUserId(), $notes, $application_id]);
        
        // Log to history
        $sql = "INSERT INTO application_history 
                (application_id, action, action_by, new_status, description, ip_address)
                VALUES (?, 'released', ?, 'released', ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $application_id,
            getCurrentUserId(),
            $notes,
            sanitizeInput($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')
        ]);
        
        sendResponse(true, 'Application released', ['status' => 'released']);
        
    } catch (Exception $e) {
        error_log('Release application error: ' . $e->getMessage());
        sendResponse(false, 'Error releasing application: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Add processing notes (Staff only)
 */
function addNotes() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'POST method required', null, 405);
    }
    
    if (!canModulePermission('applications', 'edit')) {
        sendResponse(false, 'Access denied', null, 403);
    }
    
    $db = getDB();
    $conn = $db->getConnection();
    
    $application_id = intval($_POST['id'] ?? 0);
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    if (!$application_id || !$notes) {
        sendResponse(false, 'Application ID and notes are required', null, 400);
    }
    
    try {
        // Get current notes
        $stmt = $conn->prepare("SELECT processing_notes FROM applications WHERE id = ?");
        $stmt->execute([$application_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            sendResponse(false, 'Application not found', null, 404);
        }
        
        $current_notes = $result['processing_notes'] ?? '';
        $timestamp = date('Y-m-d H:i:s');
        $new_notes = $current_notes . "\n[{$timestamp}] " . $notes;
        
        $sql = "UPDATE applications 
                SET processing_notes = ?
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$new_notes, $application_id]);
        
        // Log to history
        $sql = "INSERT INTO application_history 
                (application_id, action, action_by, description)
                VALUES (?, 'note_added', ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$application_id, getCurrentUserId(), $notes]);
        
        sendResponse(true, 'Notes added successfully');
        
    } catch (Exception $e) {
        sendResponse(false, 'Error adding notes: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Get application history (Staff only)
 */
function getApplicationHistory() {
    if (!canModulePermission('applications', 'access')) {
        sendResponse(false, 'Access denied', null, 403);
    }
    
    $db = getDB();
    $conn = $db->getConnection();
    
    $application_id = intval($_GET['id'] ?? 0);
    
    if (!$application_id) {
        sendResponse(false, 'Application ID is required', null, 400);
    }
    
    try {
        $sql = "SELECT h.*, 
                CONCAT(u.first_name, ' ', u.last_name) as action_by_name
                FROM application_history h
                LEFT JOIN users u ON h.action_by = u.id
                WHERE h.application_id = ?
                ORDER BY h.action_timestamp DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$application_id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(true, 'Application history retrieved', $history);
        
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving history: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Get application statistics (Staff only)
 */
function getStatistics() {
    if (!canModulePermission('applications', 'access')) {
        sendResponse(false, 'Access denied', null, 403);
    }
    
    $db = getDB();
    $conn = $db->getConnection();
    
    try {
        // Get counts by status
        $stmt = $conn->prepare(
            "SELECT status, COUNT(*) as count FROM applications GROUP BY status"
        );
        $stmt->execute();
        $by_status = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get counts by type
        $stmt = $conn->prepare(
            "SELECT at.type_name, COUNT(*) as count 
             FROM applications a
             LEFT JOIN application_types at ON a.application_type_id = at.id
             WHERE at.id IS NOT NULL
             GROUP BY a.application_type_id ORDER BY count DESC"
        );
        $stmt->execute();
        $by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get by month (last 12 months)
        $stmt = $conn->prepare(
            "SELECT DATE_FORMAT(submission_date, '%Y-%m') as month, COUNT(*) as count
             FROM applications
             WHERE submission_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY DATE_FORMAT(submission_date, '%Y-%m')
             ORDER BY month DESC"
        );
        $stmt->execute();
        $by_month = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get pending counts
        $stmt = $conn->prepare(
            "SELECT 
             SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
             SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
             SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
             SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released,
             SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
             COUNT(*) as total
             FROM applications"
        );
        $stmt->execute();
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        sendResponse(true, 'Statistics retrieved', [
            'by_status' => $by_status,
            'by_type' => $by_type,
            'by_month' => $by_month,
            'summary' => $summary
        ]);
        
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving statistics: ' . $e->getMessage(), null, 500);
    }
}

// ============================================================================
// ROUTE HANDLING
// ============================================================================

try {
    switch ($action) {
        // Resident endpoints
        case 'get-types':
            getApplicationTypes();
            break;
        
        case 'submit':
            submitApplication();
            break;
        
        case 'list-my':
            getMyApplications();
            break;
        
        case 'get':
            getApplication();
            break;
        
        case 'track':
            trackApplication();
            break;
        
        case 'upload-document':
            uploadDocument();
            break;
        
        // Staff endpoints
        case 'list-all':
            if (!canModulePermission('applications', 'access')) { sendResponse(false, 'Access denied', null, 403); }
            getAllApplications();
            break;
        
        case 'get-queue':
            if (!canModulePermission('applications', 'access')) { sendResponse(false, 'Access denied', null, 403); }
            getApplicationQueue();
            break;
        
        case 'approve':
            if (!canModulePermission('applications', 'edit')) { sendResponse(false, 'Access denied', null, 403); }
            approveApplication();
            break;
        
        case 'reject':
            if (!canModulePermission('applications', 'edit')) { sendResponse(false, 'Access denied', null, 403); }
            rejectApplication();
            break;
        
        case 'release':
            if (!canModulePermission('applications', 'edit')) { sendResponse(false, 'Access denied', null, 403); }
            releaseApplication();
            break;
        
        case 'add-notes':
            if (!canModulePermission('applications', 'edit')) { sendResponse(false, 'Access denied', null, 403); }
            addNotes();
            break;
        
        case 'get-history':
            if (!canModulePermission('applications', 'access')) { sendResponse(false, 'Access denied', null, 403); }
            getApplicationHistory();
            break;
        
        case 'get-statistics':
            if (!canModulePermission('applications', 'access')) { sendResponse(false, 'Access denied', null, 403); }
            getStatistics();
            break;
        
        default:
            sendResponse(false, 'Invalid action: ' . htmlspecialchars($action), null, 400);
    }
} catch (Exception $e) {
    error_log('Applications API Error: ' . $e->getMessage());
    sendResponse(false, 'An error occurred: ' . $e->getMessage(), null, 500);
}
?>

