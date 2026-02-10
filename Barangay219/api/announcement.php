<?php
header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($action !== 'public_list') {
    requireLogin();
    requireModuleAccess('announcements');
}

switch ($action) {
    case 'list': listAnnouncements(); break;
    case 'get': getAnnouncement(); break;
    case 'create':
        if (!canPerformModulePermission('announcements', 'can_create')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        createAnnouncement();
        break;
    case 'update':
        if (!canPerformModulePermission('announcements', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        updateAnnouncement();
        break;
    case 'delete':
        if (!canPerformModulePermission('announcements', 'can_delete')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        deleteAnnouncement();
        break;
    case 'public_list': listAnnouncementsPublic(); break;
    default: sendResponse(false, 'Invalid action', null, 400);
}

function listAnnouncements() {
    try {
        $db = Database::getInstance();
        $status = $_GET['status'] ?? '';
        $q = sanitizeInput($_GET['q'] ?? $_GET['search'] ?? '');
        $from = sanitizeInput($_GET['from'] ?? '');
        $to = sanitizeInput($_GET['to'] ?? '');
        $where = "1=1";
        $params = [];
        if (!empty($q)) {
            $term = '%' . $q . '%';
            $where .= " AND (a.title LIKE ? OR a.content LIKE ?)";
            $params = array_merge($params, [$term, $term]);
        }
        if (in_array($status, ['active', 'inactive', 'expired', 'archived'])) {
            $where .= " AND a.status = ?";
            $params[] = $status;
        }
        if (!empty($from)) {
            $where .= " AND DATE(a.date_posted) >= ?";
            $params[] = $from;
        }
        if (!empty($to)) {
            $where .= " AND DATE(a.date_posted) <= ?";
            $params[] = $to;
        }
        $sql = "SELECT a.*, u.username as posted_by_name FROM announcements a LEFT JOIN users u ON a.posted_by = u.id WHERE $where ORDER BY a.date_posted DESC";
        sendResponse(true, 'Retrieved', $db->fetchAll($sql, $params));
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function listAnnouncementsPublic() {
    try {
        $db = Database::getInstance();
        $sql = "SELECT id, title, content, date_posted, expiration_date FROM announcements WHERE status = 'active' AND (expiration_date IS NULL OR expiration_date >= CURDATE()) ORDER BY date_posted DESC LIMIT 20";
        $list = $db->fetchAll($sql);
        sendResponse(true, 'Retrieved', $list);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function getAnnouncement() {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    try {
        $db = Database::getInstance();
        $a = $db->fetchOne("SELECT a.*, u.username as posted_by_name FROM announcements a LEFT JOIN users u ON a.posted_by = u.id WHERE a.id = ?", [$id]);
        sendResponse($a ? true : false, $a ? 'Found' : 'Not found', $a);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function createAnnouncement() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $title = sanitizeInput($_POST['title'] ?? '');
    $content = sanitizeInput($_POST['content'] ?? '');
    $date_posted = $_POST['date_posted'] ?? date('Y-m-d');
    $expiration_date = $_POST['expiration_date'] ?? null;
    if (!$title || !$content) { sendResponse(false, 'Title and content required', null, 400); return; }
    try {
        $db = Database::getInstance();
        $db->query("INSERT INTO announcements (title, content, posted_by, date_posted, expiration_date, status) VALUES (?, ?, ?, ?, ?, 'active')", 
                   [$title, $content, getCurrentUserId(), $date_posted, $expiration_date]);
        logActivity('create', 'announcements', $db->lastInsertId());
        sendResponse(true, 'Created', ['id' => $db->lastInsertId()]);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function updateAnnouncement() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    $updates = [];
    $params = [];
    foreach (['title', 'content', 'date_posted', 'expiration_date', 'status'] as $field) {
        if (isset($_POST[$field])) {
            $updates[] = "$field = ?";
            $params[] = $field === 'date_posted' || $field === 'expiration_date' ? $_POST[$field] : sanitizeInput($_POST[$field]);
        }
    }
    if (empty($updates)) { sendResponse(false, 'Nothing to update', null, 400); return; }
    $params[] = $id;
    try {
        $db = Database::getInstance();
        $db->query("UPDATE announcements SET " . implode(', ', $updates) . " WHERE id = ?", $params);
        sendResponse(true, 'Updated');
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function deleteAnnouncement() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    try {
        $db = Database::getInstance();
        try {
            $db->query("UPDATE announcements SET status = 'archived' WHERE id = ?", [$id]);
        } catch (Exception $e) {
            $db->query("UPDATE announcements SET status = 'inactive' WHERE id = ?", [$id]);
        }
        logActivity('archive', 'announcements', $id);
        sendResponse(true, 'Archived');
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}
