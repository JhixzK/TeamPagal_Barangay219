<?php
header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireAnyRole([ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY, ROLE_KAGAWA]);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list': listComplaints(); break;
    case 'get': getComplaint(); break;
    case 'create': createComplaint(); break;
    case 'update': updateComplaint(); break;
    default: sendResponse(false, 'Invalid action', null, 400);
}

function listComplaints() {
    try {
        $db = Database::getInstance();
        sendResponse(true, 'Retrieved', $db->fetchAll("SELECT * FROM complaints ORDER BY filing_date DESC"));
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function getComplaint() {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    try {
        $db = Database::getInstance();
        $c = $db->fetchOne("SELECT * FROM complaints WHERE id = ?", [$id]);
        sendResponse($c ? true : false, $c ? 'Found' : 'Not found', $c);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function createComplaint() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $complaint_title = sanitizeInput($_POST['complaint_title'] ?? '');
    $complainant_name = sanitizeInput($_POST['complainant_name'] ?? '');
    $respondent_name = sanitizeInput($_POST['respondent_name'] ?? '');
    $complaint_type = sanitizeInput($_POST['complaint_type'] ?? '');
    $narrative = sanitizeInput($_POST['narrative'] ?? '');
    $filing_date = $_POST['filing_date'] ?? date('Y-m-d');
    if (!$complaint_title || !$complainant_name || !$narrative) { sendResponse(false, 'Required fields missing', null, 400); return; }
    try {
        $db = Database::getInstance();
        $db->query("INSERT INTO complaints (complaint_title, complainant_name, respondent_name, complaint_type, narrative, filing_date, handled_by) VALUES (?, ?, ?, ?, ?, ?, ?)", 
                   [$complaint_title, $complainant_name, $respondent_name, $complaint_type, $narrative, $filing_date, getCurrentUserId()]);
        sendResponse(true, 'Created', ['id' => $db->lastInsertId()]);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function updateComplaint() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    $updates = [];
    $params = [];
    foreach (['complaint_title', 'complainant_name', 'respondent_name', 'complaint_type', 'narrative', 'status', 'resolution_date'] as $field) {
        if (isset($_POST[$field])) {
            $updates[] = "$field = ?";
            $params[] = $field === 'filing_date' || $field === 'resolution_date' ? $_POST[$field] : sanitizeInput($_POST[$field]);
        }
    }
    if (empty($updates)) { sendResponse(false, 'Nothing to update', null, 400); return; }
    $params[] = $id;
    try {
        $db = Database::getInstance();
        $db->query("UPDATE complaints SET " . implode(', ', $updates) . " WHERE id = ?", $params);
        sendResponse(true, 'Updated');
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}
