<?php
header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('certificates');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list': listCertificates(); break;
    case 'get': getCertificate(); break;
    case 'create':
        if (!canPerformModulePermission('certificates', 'can_create') && !canPerformModulePermission('applications', 'can_create')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        createCertificate();
        break;
    case 'update':
        if (!canPerformModulePermission('certificates', 'can_edit') && !canPerformModulePermission('applications', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        updateCertificate();
        break;
    case 'approve':
        if (!canPerformModulePermission('certificates', 'can_edit') && !canPerformModulePermission('applications', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        approveCertificate();
        break;
    case 'reject':
        if (!canPerformModulePermission('certificates', 'can_edit') && !canPerformModulePermission('applications', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        rejectCertificate();
        break;
    case 'release':
        if (!canPerformModulePermission('certificates', 'can_edit') && !canPerformModulePermission('applications', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        releaseCertificate();
        break;
    case 'generate_control':
        if (!canPerformModulePermission('certificates', 'can_edit') && !canPerformModulePermission('applications', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        generateControlNumber();
        break;
    default: sendResponse(false, 'Invalid action', null, 400);
}

function generateApplicationRef() {
    $db = Database::getInstance();
    $year = date('Y');
    $prefix = 'APP-' . $year . '-';
    $last = $db->fetchOne("SELECT application_ref FROM certificate_requests WHERE application_ref LIKE ? ORDER BY id DESC LIMIT 1", [$prefix . '%']);
    $seq = 1;
    if ($last && preg_match('/-(\d+)$/', $last['application_ref'], $m)) {
        $seq = (int)$m[1] + 1;
    }
    return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
}

function generateControlNumber() {
    $db = Database::getInstance();
    $year = date('Y');
    $prefix = 'CTRL-' . $year . '-';
    $last = $db->fetchOne("SELECT control_number FROM certificate_requests WHERE control_number LIKE ? ORDER BY id DESC LIMIT 1", [$prefix . '%']);
    $seq = 1;
    if ($last && preg_match('/-(\d+)$/', $last['control_number'], $m)) {
        $seq = (int)$m[1] + 1;
    }
    return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
}

function listCertificates() {
    try {
        $db = Database::getInstance();
        $status = $_GET['status'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(50, max(10, (int)($_GET['limit'] ?? ITEMS_PER_PAGE)));
        $offset = ($page - 1) * $limit;

        $where = "1=1";
        $params = [];
        if (in_array($status, ['pending', 'approved', 'rejected', 'issued'])) {
            $where .= " AND c.status = ?";
            $params[] = $status;
        }

        $countSql = "SELECT COUNT(*) as total FROM certificate_requests c WHERE $where";
        $total = (int)$db->fetchOne($countSql, $params)['total'];

        $sql = "SELECT c.*, CONCAT(r.first_name, ' ', COALESCE(r.middle_name,''), ' ', r.last_name) as resident_name,
                r.address as resident_address, r.birth_date, r.gender, r.civil_status
                FROM certificate_requests c 
                LEFT JOIN residents r ON c.resident_id = r.id 
                WHERE $where
                ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $list = $db->fetchAll($sql, $params);

        // Ensure application_ref exists (for pre-migration data)
        foreach ($list as &$row) {
            if (empty($row['application_ref'])) $row['application_ref'] = 'APP-' . $row['id'] . '-' . date('Y', strtotime($row['created_at']));
        }

        sendResponse(true, 'Certificates retrieved', [
            'certificates' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]);
    } catch (Exception $e) {
        sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
    }
}

function getCertificate() {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    try {
        $db = Database::getInstance();
        $cert = $db->fetchOne("SELECT c.*, CONCAT(r.first_name, ' ', COALESCE(r.middle_name,''), ' ', r.last_name) as resident_name,
            r.address, r.birth_date, r.gender, r.civil_status, r.occupation, r.contact_number, r.citizenship
            FROM certificate_requests c LEFT JOIN residents r ON c.resident_id = r.id WHERE c.id = ?", [$id]);
        if ($cert && empty($cert['application_ref'])) $cert['application_ref'] = 'APP-' . $cert['id'] . '-' . date('Y', strtotime($cert['created_at']));
        sendResponse($cert ? true : false, $cert ? 'Found' : 'Not found', $cert);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function createCertificate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $resident_id = intval($_POST['resident_id'] ?? 0);
    $certificate_type = sanitizeInput($_POST['certificate_type'] ?? '');
    $purpose = sanitizeInput($_POST['purpose'] ?? '');
    $remarks = sanitizeInput($_POST['remarks'] ?? '');
    if (!$resident_id || !$certificate_type) { sendResponse(false, 'Resident and certificate type required', null, 400); return; }
    $allowed = [CERT_BARANGAY_CLEARANCE, CERT_INDIGENCY, CERT_RESIDENCY, CERT_GOOD_MORAL, CERT_TRANSFER_REQUEST];
    if (!in_array($certificate_type, $allowed)) { sendResponse(false, 'Invalid certificate type', null, 400); return; }
    try {
        $db = Database::getInstance();
        // Use minimal INSERT - works without migration (no application_ref, remarks columns)
        $db->query("INSERT INTO certificate_requests (resident_id, requested_by, certificate_type, purpose, status) VALUES (?, ?, ?, ?, 'pending')",
            [$resident_id, getCurrentUserId(), $certificate_type, $purpose ?: null]);
        $id = $db->lastInsertId();
        $appRef = 'APP-' . $id . '-' . date('Y');
        logActivity('create', 'certificates', $id, ['application_ref' => $appRef, 'certificate_type' => $certificate_type]);
        sendResponse(true, 'Application created', ['id' => $id, 'application_ref' => $appRef]);
    } catch (Exception $e) {
        sendResponse(false, 'Error creating: ' . $e->getMessage(), null, 500);
    }
}

function updateCertificate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $id = intval($_POST['id'] ?? 0);
    $status = sanitizeInput($_POST['status'] ?? '');
    $remarks = sanitizeInput($_POST['remarks'] ?? '');
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    $allowed = ['pending', 'approved', 'rejected', 'issued'];
    if (!in_array($status, $allowed)) { sendResponse(false, 'Invalid status', null, 400); return; }
    try {
        $db = Database::getInstance();
        $updates = ["status = ?"];
        $params = [$status];
        if ($status === 'issued') { $updates[] = "issued_date = CURDATE()"; }
        $cols = $db->getConnection()->query("SHOW COLUMNS FROM certificate_requests LIKE 'remarks'")->fetchAll();
        if (!empty($cols) && $remarks !== '') { $updates[] = "remarks = ?"; $params[] = $remarks; }
        $params[] = $id;
        $db->query("UPDATE certificate_requests SET " . implode(', ', $updates) . " WHERE id = ?", $params);
        logActivity('update', 'certificates', $id, ['status' => $status]);
        sendResponse(true, 'Updated');
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function approveCertificate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    try {
        $db = Database::getInstance();
        $db->query("UPDATE certificate_requests SET status = 'approved' WHERE id = ?", [$id]);
        logActivity('approve', 'certificates', $id);
        sendResponse(true, 'Approved');
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function rejectCertificate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $id = intval($_POST['id'] ?? 0);
    $reason = sanitizeInput($_POST['reason'] ?? '');
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    try {
        $db = Database::getInstance();
        $cols = $db->getConnection()->query("SHOW COLUMNS FROM certificate_requests LIKE 'remarks'")->fetchAll();
        if (!empty($cols) && $reason) {
            $db->query("UPDATE certificate_requests SET status = 'rejected', remarks = ? WHERE id = ?", [$reason, $id]);
        } else {
            $db->query("UPDATE certificate_requests SET status = 'rejected' WHERE id = ?", [$id]);
        }
        logActivity('reject', 'certificates', $id);
        sendResponse(true, 'Rejected');
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function releaseCertificate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    try {
        $db = Database::getInstance();
        // Use minimal UPDATE - works without control_number column
        $db->query("UPDATE certificate_requests SET status = 'issued', issued_date = CURDATE() WHERE id = ? AND status = 'approved'", [$id]);
        $ctrlNum = 'CTRL-' . $id . '-' . date('Y');
        try { logActivity('release', 'certificates', $id, ['control_number' => $ctrlNum]); } catch (Exception $e) { }
        sendResponse(true, 'Released', ['control_number' => $ctrlNum]);
    } catch (Exception $e) {
        sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
    }
}

function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}
