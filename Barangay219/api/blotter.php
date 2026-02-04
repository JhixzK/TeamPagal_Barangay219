<?php
header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireAnyRole([ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY, ROLE_KAGAWA]);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list': listBlotters(); break;
    case 'get': getBlotter(); break;
    case 'create': createBlotter(); break;
    case 'update': updateBlotter(); break;
    default: sendResponse(false, 'Invalid action', null, 400);
}

function listBlotters() {
    try {
        $db = Database::getInstance();
        sendResponse(true, 'Retrieved', $db->fetchAll("SELECT * FROM blotters ORDER BY incident_date DESC"));
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function getBlotter() {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    try {
        $db = Database::getInstance();
        $b = $db->fetchOne("SELECT * FROM blotters WHERE id = ?", [$id]);
        sendResponse($b ? true : false, $b ? 'Found' : 'Not found', $b);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function createBlotter() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $case_title = sanitizeInput($_POST['case_title'] ?? '');
    $complainant_name = sanitizeInput($_POST['complainant_name'] ?? '');
    $respondent_name = sanitizeInput($_POST['respondent_name'] ?? '');
    $incident_date = $_POST['incident_date'] ?? date('Y-m-d');
    $incident_location = sanitizeInput($_POST['incident_location'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    if (!$case_title || !$complainant_name || !$description) { sendResponse(false, 'Required fields missing', null, 400); return; }
    try {
        $db = Database::getInstance();
        $db->query("INSERT INTO blotters (case_title, complainant_name, respondent_name, incident_date, incident_location, description, handled_by) VALUES (?, ?, ?, ?, ?, ?, ?)", 
                   [$case_title, $complainant_name, $respondent_name, $incident_date, $incident_location, $description, getCurrentUserId()]);
        sendResponse(true, 'Created', ['id' => $db->lastInsertId()]);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function updateBlotter() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    $updates = [];
    $params = [];
    foreach (['case_title', 'complainant_name', 'respondent_name', 'incident_date', 'incident_location', 'description', 'status', 'settlement_date'] as $field) {
        if (isset($_POST[$field])) {
            $updates[] = "$field = ?";
            $params[] = $field === 'incident_date' || $field === 'settlement_date' ? $_POST[$field] : sanitizeInput($_POST[$field]);
        }
    }
    if (empty($updates)) { sendResponse(false, 'Nothing to update', null, 400); return; }
    $params[] = $id;
    try {
        $db = Database::getInstance();
        $db->query("UPDATE blotters SET " . implode(', ', $updates) . " WHERE id = ?", $params);
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
