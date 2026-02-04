<?php
header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireAnyRole([ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY, ROLE_KAGAWA, ROLE_SK_CHAIRMAN]);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list': listAnnouncements(); break;
    case 'get': getAnnouncement(); break;
    case 'create': createAnnouncement(); break;
    case 'update': updateAnnouncement(); break;
    case 'delete': deleteAnnouncement(); break;
    default: sendResponse(false, 'Invalid action', null, 400);
}

function listAnnouncements() {
    try {
        $db = Database::getInstance();
        $sql = "SELECT a.*, u.username as posted_by_name FROM announcements a LEFT JOIN users u ON a.posted_by = u.id ORDER BY a.date_posted DESC";
        sendResponse(true, 'Retrieved', $db->fetchAll($sql));
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
        $db->query("DELETE FROM announcements WHERE id = ?", [$id]);
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
