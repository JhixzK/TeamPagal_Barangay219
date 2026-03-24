<?php
/**
 * In-app notifications API (staff: user_id, residents: resident_id).
 */
header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/notifications-store.php';

requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function notificationsGetResidentIdForSession(): int {
    if (!empty($_SESSION['resident_id'])) {
        return (int)$_SESSION['resident_id'];
    }
    $uid = (int)(getCurrentUserId() ?? 0);
    if ($uid <= 0) {
        return 0;
    }
    $db = Database::getInstance();
    $row = $db->fetchOne("SELECT resident_id FROM users WHERE id = ? LIMIT 1", [$uid]);
    return (int)($row['resident_id'] ?? 0);
}

switch ($action) {
    case 'list':
        notificationsList();
        break;
    case 'unread_count':
        notificationsUnreadCount();
        break;
    case 'mark_read':
        notificationsMarkRead();
        break;
    case 'mark_all_read':
        notificationsMarkAllRead();
        break;
    case 'clear_all':
        notificationsClearAll();
        break;
    case 'delete':
        notificationsDeleteOne();
        break;
    default:
        notificationsSendJson(false, 'Invalid action', null, 400);
}

function notificationsUsesResidentScope(): bool {
    refreshSessionRole();
    if (isResidentView()) {
        return true;
    }
    return normalizeRole(getRealUserRole()) === normalizeRole(ROLE_RESIDENT);
}

function notificationsScopeParams(): ?array {
    notificationsEnsureSchema();
    if (notificationsUsesResidentScope()) {
        $rid = notificationsGetResidentIdForSession();
        if ($rid > 0) {
            return ['resident', $rid];
        }
        // Official in "Resident" view but no linked resident record — still use staff inbox
        if (function_exists('canSwitchToResidentView') && canSwitchToResidentView()) {
            $uid = (int)(getCurrentUserId() ?? 0);
            if ($uid > 0) {
                return ['user', $uid];
            }
        }
        return null;
    }
    $uid = (int)(getCurrentUserId() ?? 0);
    if ($uid <= 0) {
        return null;
    }
    return ['user', $uid];
}

function notificationsUnreadTotal($db, array $scope): int {
    if ($scope[0] === 'resident') {
        $unread = $db->fetchOne(
            "SELECT COUNT(*) AS c FROM notifications WHERE resident_id = ? AND user_id IS NULL AND is_read = 0",
            [$scope[1]]
        );
    } else {
        $unread = $db->fetchOne(
            "SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND resident_id IS NULL AND is_read = 0",
            [$scope[1]]
        );
    }
    if (!is_array($unread)) {
        return 0;
    }
    return (int)($unread['c'] ?? 0);
}

function notificationsList(): void {
    try {
        $limit = min(50, max(5, (int)($_GET['limit'] ?? 20)));
        $scope = notificationsScopeParams();
        if ($scope === null) {
            notificationsSendJson(true, 'OK', ['notifications' => [], 'total_unread' => 0]);
        }
        $db = Database::getInstance();
        $cols = notificationsListSelectColumns($db);
        $orderBy = notificationsListOrderBy($db);
        if ($scope[0] === 'resident') {
            $sql = "SELECT {$cols}
                    FROM notifications WHERE resident_id = ? AND user_id IS NULL
                    ORDER BY {$orderBy} LIMIT " . (int)$limit;
            $rows = $db->fetchAll($sql, [$scope[1]]);
        } else {
            $sql = "SELECT {$cols}
                    FROM notifications WHERE user_id = ? AND resident_id IS NULL
                    ORDER BY {$orderBy} LIMIT " . (int)$limit;
            $rows = $db->fetchAll($sql, [$scope[1]]);
        }
        $totalUnread = notificationsUnreadTotal($db, $scope);
        notificationsSendJson(true, 'OK', [
            'notifications' => is_array($rows) ? $rows : [],
            'total_unread' => $totalUnread,
        ]);
    } catch (Throwable $e) {
        error_log('notificationsList: ' . $e->getMessage());
        notificationsSendJson(false, 'Could not load notifications', null, 500);
    }
}

function notificationsUnreadCount(): void {
    try {
        $scope = notificationsScopeParams();
        if ($scope === null) {
            notificationsSendJson(true, 'OK', ['count' => 0]);
        }
        $db = Database::getInstance();
        notificationsSendJson(true, 'OK', ['count' => notificationsUnreadTotal($db, $scope)]);
    } catch (Throwable $e) {
        error_log('notificationsUnreadCount: ' . $e->getMessage());
        notificationsSendJson(true, 'OK', ['count' => 0]);
    }
}

function notificationsMarkRead(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        notificationsSendJson(false, 'POST required', null, 405);
    }
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        notificationsSendJson(false, 'ID required', null, 400);
    }
    $scope = notificationsScopeParams();
    if ($scope === null) {
        notificationsSendJson(false, 'Invalid scope', null, 400);
    }
    $db = Database::getInstance();
    if ($scope[0] === 'resident') {
        $db->query(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND resident_id = ? AND user_id IS NULL",
            [$id, $scope[1]]
        );
    } else {
        $db->query(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ? AND resident_id IS NULL",
            [$id, $scope[1]]
        );
    }
    notificationsSendJson(true, 'Updated');
}

function notificationsMarkAllRead(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        notificationsSendJson(false, 'POST required', null, 405);
    }
    $scope = notificationsScopeParams();
    if ($scope === null) {
        notificationsSendJson(false, 'Invalid scope', null, 400);
    }
    $db = Database::getInstance();
    if ($scope[0] === 'resident') {
        $db->query(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE resident_id = ? AND user_id IS NULL AND is_read = 0",
            [$scope[1]]
        );
    } else {
        $db->query(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND resident_id IS NULL AND is_read = 0",
            [$scope[1]]
        );
    }
    notificationsSendJson(true, 'Updated');
}

function notificationsClearAll(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        notificationsSendJson(false, 'POST required', null, 405);
    }
    $scope = notificationsScopeParams();
    if ($scope === null) {
        notificationsSendJson(false, 'Invalid scope', null, 400);
    }
    $db = Database::getInstance();
    if ($scope[0] === 'resident') {
        $db->query(
            "DELETE FROM notifications WHERE resident_id = ? AND user_id IS NULL",
            [$scope[1]]
        );
    } else {
        $db->query(
            "DELETE FROM notifications WHERE user_id = ? AND resident_id IS NULL",
            [$scope[1]]
        );
    }
    notificationsSendJson(true, 'Cleared', ['deleted' => true]);
}

function notificationsDeleteOne(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        notificationsSendJson(false, 'POST required', null, 405);
    }
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        notificationsSendJson(false, 'ID required', null, 400);
    }
    $scope = notificationsScopeParams();
    if ($scope === null) {
        notificationsSendJson(false, 'Invalid scope', null, 400);
    }
    $db = Database::getInstance();
    if ($scope[0] === 'resident') {
        $stmt = $db->query(
            "DELETE FROM notifications WHERE id = ? AND resident_id = ? AND user_id IS NULL",
            [$id, $scope[1]]
        );
    } else {
        $stmt = $db->query(
            "DELETE FROM notifications WHERE id = ? AND user_id = ? AND resident_id IS NULL",
            [$id, $scope[1]]
        );
    }
    if ($stmt === false) {
        notificationsSendJson(false, 'Could not delete', null, 500);
    }
    if ((int)$stmt->rowCount() < 1) {
        notificationsSendJson(false, 'Not found or access denied', null, 404);
    }
    notificationsSendJson(true, 'Deleted');
}

function notificationsSendJson(bool $success, string $message, $data = null, int $http = 200): void {
    http_response_code($http);
    $jsonFlags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $payload = json_encode(
        ['success' => $success, 'message' => $message, 'data' => $data],
        $jsonFlags
    );
    if ($payload === false) {
        $payload = '{"success":false,"message":"Invalid response encoding","data":null}';
    }
    echo $payload;
    exit();
}
