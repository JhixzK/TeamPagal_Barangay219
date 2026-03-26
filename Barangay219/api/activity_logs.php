<?php
/**
 * E-Barangay Information Management System
 * Activity Logs API
 */

header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('reports');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        getActivityLogs();
        break;

    default:
        sendResponse(false, 'Invalid action', null, 400);
        break;
}

function getActivityLogs() {
    try {
        $db = Database::getInstance();
        $userId = (int)($_GET['user_id'] ?? 0);
        $limit = min(100, max(10, (int)($_GET['limit'] ?? 50)));
        $exclude = activityLogsExcludeLoginSql('al');
        $where = $userId ? "al.user_id = ? AND $exclude" : "1=1 AND $exclude";
        $params = $userId ? [$userId] : [];
        // Integer LIMIT avoids MySQL native prepared statement issues with LIMIT ? placeholders.
        $sql = "SELECT al.*, u.username FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id WHERE $where ORDER BY al.created_at DESC LIMIT " . $limit;
        $logs = $db->fetchAll($sql, $params);
        sendResponse(true, 'Activity logs', activityLogsWithSummary($logs));
    } catch (Exception $e) {
        error_log('getActivityLogs: ' . $e->getMessage());
        sendResponse(false, defined('DEBUG_MODE') && DEBUG_MODE ? $e->getMessage() : 'Could not load activity logs', null, 500);
    }
}

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
