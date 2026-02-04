<?php
header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireAnyRole([ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY, ROLE_TREASURER]);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list': listCertificates(); break;
    case 'get': getCertificate(); break;
    case 'create': createCertificate(); break;
    case 'update': updateCertificate(); break;
    case 'approve': approveCertificate(); break;
    case 'reject': rejectCertificate(); break;
    default: sendResponse(false, 'Invalid action', null, 400);
}

function listCertificates() {
    try {
        $db = Database::getInstance();
        $sql = "SELECT c.*, CONCAT(r.first_name, ' ', r.last_name) as resident_name 
                FROM certificate_requests c 
                LEFT JOIN residents r ON c.resident_id = r.id 
                ORDER BY c.created_at DESC";
        sendResponse(true, 'Certificates retrieved', $db->fetchAll($sql));
    } catch (Exception $e) {
        sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
    }
}

function getCertificate() {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    try {
        $db = Database::getInstance();
        $cert = $db->fetchOne("SELECT c.*, CONCAT(r.first_name, ' ', r.last_name) as resident_name FROM certificate_requests c LEFT JOIN residents r ON c.resident_id = r.id WHERE c.id = ?", [$id]);
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
    if (!$resident_id || !$certificate_type) { sendResponse(false, 'Required fields missing', null, 400); return; }
    try {
        $db = Database::getInstance();
        $db->query("INSERT INTO certificate_requests (resident_id, requested_by, certificate_type, purpose, status) VALUES (?, ?, ?, ?, 'pending')", 
                   [$resident_id, getCurrentUserId(), $certificate_type, $purpose]);
        sendResponse(true, 'Created', ['id' => $db->lastInsertId()]);
    } catch (Exception $e) {
        sendResponse(false, 'Error creating', null, 500);
    }
}

function updateCertificate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $id = intval($_POST['id'] ?? 0);
    $status = sanitizeInput($_POST['status'] ?? '');
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    try {
        $db = Database::getInstance();
        $updates = [];
        $params = [];
        if ($status) { $updates[] = "status = ?"; $params[] = $status; }
        if ($status === 'issued') { $updates[] = "issued_date = CURDATE()"; }
        if (empty($updates)) { sendResponse(false, 'Nothing to update', null, 400); return; }
        $params[] = $id;
        $db->query("UPDATE certificate_requests SET " . implode(', ', $updates) . " WHERE id = ?", $params);
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
        sendResponse(true, 'Approved');
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function rejectCertificate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    try {
        $db = Database::getInstance();
        $db->query("UPDATE certificate_requests SET status = 'rejected' WHERE id = ?", [$id]);
        sendResponse(true, 'Rejected');
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}
