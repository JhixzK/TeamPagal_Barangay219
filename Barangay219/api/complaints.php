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
    case 'delete': deleteComplaint(); break;
    default: sendResponse(false, 'Invalid action', null, 400);
}

function listComplaints() {
    try {
        $db = Database::getInstance();
        $status = $_GET['status'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(50, max(10, (int)($_GET['limit'] ?? ITEMS_PER_PAGE)));
        $offset = ($page - 1) * $limit;
        $where = "1=1";
        $params = [];
        if (in_array($status, ['pending', 'under_review', 'resolved', 'dismissed'])) {
            $where .= " AND c.status = ?";
            $params[] = $status;
        }
        $countSql = "SELECT COUNT(*) as total FROM complaints c WHERE $where";
        $total = (int)$db->fetchOne($countSql, $params)['total'];
        $hasResident = !empty($db->getConnection()->query("SHOW COLUMNS FROM complaints LIKE 'resident_id'")->fetchAll());
        $join = $hasResident ? " LEFT JOIN residents r ON c.resident_id = r.id " : " ";
        $residentSelect = $hasResident ? ", CONCAT(r.first_name, ' ', r.last_name) as resident_name " : ", NULL as resident_name ";
        $sql = "SELECT c.* $residentSelect FROM complaints c $join WHERE $where ORDER BY c.filing_date DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $list = $db->fetchAll($sql, $params);
        sendResponse(true, 'Retrieved', [
            'complaints' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]);
    } catch (Exception $e) {
        sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
    }
}

function getComplaint() {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    try {
        $db = Database::getInstance();
        $hasResident = !empty($db->getConnection()->query("SHOW COLUMNS FROM complaints LIKE 'resident_id'")->fetchAll());
        $sql = $hasResident
            ? "SELECT c.*, CONCAT(r.first_name, ' ', r.last_name) as resident_name FROM complaints c LEFT JOIN residents r ON c.resident_id = r.id WHERE c.id = ?"
            : "SELECT c.*, NULL as resident_name FROM complaints c WHERE c.id = ?";
        $c = $db->fetchOne($sql, [$id]);
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
    $resident_id = intval($_POST['resident_id'] ?? 0);
    $remarks = sanitizeInput($_POST['remarks'] ?? '');
    if (!$complaint_title || !$complainant_name || !$narrative) { sendResponse(false, 'Title, complainant, and narrative required', null, 400); return; }
    try {
        $db = Database::getInstance();
        $hasResident = !empty($db->getConnection()->query("SHOW COLUMNS FROM complaints LIKE 'resident_id'")->fetchAll());
        $hasRemarks = !empty($db->getConnection()->query("SHOW COLUMNS FROM complaints LIKE 'remarks'")->fetchAll());
        $cols = ['complaint_title', 'complainant_name', 'respondent_name', 'complaint_type', 'narrative', 'filing_date', 'handled_by'];
        $vals = [$complaint_title, $complainant_name, $respondent_name, $complaint_type, $narrative, $filing_date, getCurrentUserId()];
        if ($hasResident) { $cols[] = 'resident_id'; $vals[] = $resident_id ?: null; }
        if ($hasRemarks) { $cols[] = 'remarks'; $vals[] = $remarks ?: null; }
        $db->query("INSERT INTO complaints (" . implode(', ', $cols) . ") VALUES (" . implode(', ', array_fill(0, count($vals), '?')) . ")", $vals);
        $id = $db->lastInsertId();
        try { logActivity('create', 'complaints', $id); } catch (Exception $e) { /* activity_logs may not exist */ }
        sendResponse(true, 'Created', ['id' => $id]);
    } catch (Exception $e) {
        sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
    }
}

function updateComplaint() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    $db = Database::getInstance();
    $hasRemarks = !empty($db->getConnection()->query("SHOW COLUMNS FROM complaints LIKE 'remarks'")->fetchAll());
    $hasResident = !empty($db->getConnection()->query("SHOW COLUMNS FROM complaints LIKE 'resident_id'")->fetchAll());
    $baseFields = ['complaint_title', 'complainant_name', 'respondent_name', 'complaint_type', 'narrative', 'filing_date', 'status', 'resolution_date'];
    if ($hasRemarks) $baseFields[] = 'remarks';
    $updates = [];
    $params = [];
    foreach ($baseFields as $field) {
        if (isset($_POST[$field])) {
            $updates[] = "$field = ?";
            $params[] = in_array($field, ['filing_date', 'resolution_date']) ? $_POST[$field] : sanitizeInput($_POST[$field]);
        }
    }
    if ($hasResident && isset($_POST['resident_id'])) {
        $updates[] = "resident_id = ?";
        $params[] = intval($_POST['resident_id']) ?: null;
    }
    if (empty($updates)) { sendResponse(false, 'Nothing to update', null, 400); return; }
    $params[] = $id;
    try {
        $db->query("UPDATE complaints SET " . implode(', ', $updates) . " WHERE id = ?", $params);
        logActivity('update', 'complaints', $id);
        sendResponse(true, 'Updated');
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function deleteComplaint() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    try {
        $db = Database::getInstance();
        $db->query("DELETE FROM complaints WHERE id = ?", [$id]);
        logActivity('delete', 'complaints', $id);
        sendResponse(true, 'Deleted');
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}
