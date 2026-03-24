<?php
/**
 * Shared in-app notifications: schema, resident rows, staff fan-out by module.
 */
if (!defined('ACCESS_ALLOWED')) {
    die('Direct access not allowed');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * Create notifications table and add columns missing from older installs.
 */
function notificationsEnsureSchema(): void {
    $db = Database::getInstance();
    $db->query(
        "CREATE TABLE IF NOT EXISTS notifications (
            id INT(11) NOT NULL AUTO_INCREMENT,
            resident_id INT(11) DEFAULT NULL,
            user_id INT(11) DEFAULT NULL,
            title VARCHAR(255) DEFAULT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50) DEFAULT 'info',
            event_type VARCHAR(80) DEFAULT NULL,
            link_url VARCHAR(512) DEFAULT NULL,
            payload TEXT DEFAULT NULL,
            is_read TINYINT(1) DEFAULT 0,
            read_at DATETIME DEFAULT NULL,
            status VARCHAR(30) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_resident_id (resident_id),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at),
            KEY idx_is_read_user (user_id, is_read),
            KEY idx_is_read_resident (resident_id, is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $cols = notificationsTableColumns($db);
    $add = function (string $name, string $ddl) use ($db, $cols) {
        if (!in_array($name, $cols, true)) {
            $db->query("ALTER TABLE notifications ADD COLUMN $ddl");
        }
    };
    $add('user_id', 'user_id INT(11) DEFAULT NULL AFTER resident_id');
    $add('event_type', 'event_type VARCHAR(80) DEFAULT NULL AFTER type');
    $add('link_url', 'link_url VARCHAR(512) DEFAULT NULL AFTER event_type');
    $add('payload', 'payload TEXT DEFAULT NULL AFTER link_url');
    $add('read_at', 'read_at DATETIME DEFAULT NULL AFTER is_read');

    // Legacy: resident_id was NOT NULL — relax if needed
    try {
        $db->query("ALTER TABLE notifications MODIFY resident_id INT(11) DEFAULT NULL");
    } catch (Exception $e) {
        // ignore if already nullable or driver difference
    }
}

/**
 * @return list<string>
 */
function notificationsTableColumns($db): array {
    try {
        $rows = $db->fetchAll(
            "SELECT COLUMN_NAME AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications'"
        );
        return array_map(static function ($r) {
            return strtolower((string)($r['c'] ?? ''));
        }, $rows ?: []);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Comma-separated column list for SELECT (skips missing columns on old DBs).
 */
function notificationsListSelectColumns($db): string {
    $have = array_flip(notificationsTableColumns($db));
    if (empty($have)) {
        return 'id, title, message, type, is_read, created_at';
    }
    $want = ['id', 'title', 'message', 'type', 'event_type', 'link_url', 'payload', 'is_read', 'read_at', 'created_at'];
    $parts = [];
    foreach ($want as $c) {
        if (isset($have[$c])) {
            $parts[] = $c;
        }
    }
    return $parts ? implode(', ', $parts) : 'id, title, message, type, is_read, created_at';
}

/**
 * Safe ORDER BY for list queries (legacy tables may lack created_at).
 */
function notificationsListOrderBy($db): string {
    $cols = notificationsTableColumns($db);
    if (in_array('created_at', $cols, true)) {
        return 'created_at DESC';
    }
    return 'id DESC';
}

/**
 * Insert a resident-scoped notification (portal user linked to resident_id).
 */
function notificationsInsertForResident(
    int $residentId,
    string $title,
    string $message,
    string $type = 'info',
    ?string $eventType = null,
    ?string $linkUrl = null,
    ?string $payloadJson = null
): void {
    if ($residentId <= 0) {
        return;
    }
    try {
        notificationsEnsureSchema();
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO notifications (resident_id, user_id, title, message, type, event_type, link_url, payload, is_read, created_at)
             VALUES (?, NULL, ?, ?, ?, ?, ?, ?, 0, NOW())",
            [$residentId, $title, $message, $type, $eventType, $linkUrl, $payloadJson]
        );
    } catch (Exception $e) {
        error_log('notificationsInsertForResident: ' . $e->getMessage());
    }
}

/**
 * Insert a staff notification for one user.
 */
function notificationsInsertForUser(
    int $userId,
    string $title,
    string $message,
    string $type = 'info',
    ?string $eventType = null,
    ?string $linkUrl = null,
    ?string $payloadJson = null
): void {
    if ($userId <= 0) {
        return;
    }
    try {
        notificationsEnsureSchema();
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO notifications (resident_id, user_id, title, message, type, event_type, link_url, payload, is_read, created_at)
             VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, 0, NOW())",
            [$userId, $title, $message, $type, $eventType, $linkUrl, $payloadJson]
        );
    } catch (Exception $e) {
        error_log('notificationsInsertForUser: ' . $e->getMessage());
    }
}

/**
 * Single module check: DB row if present, else default role map (matches app sidebar behavior).
 */
function notificationsRoleHasModule(string $role, string $module): bool {
    $permissions = getRolePermissions($role);
    if (isset($permissions[$module])) {
        return !empty($permissions[$module]['can_access']);
    }
    $defaults = getDefaultRolePermissions();
    return !empty($defaults[$role][$module]['can_access']);
}

/**
 * Mirror staff module access for notification fan-out (no session).
 * - Uses defaults when role_permissions has no row for that module (avoids silent empty fan-out).
 * - Treats certificates ↔ applications as equivalent (sidebar uses both).
 */
function notificationsRoleCanAccessModule(string $role, string $module): bool {
    if (!function_exists('getRolePermissions')) {
        require_once __DIR__ . '/auth-check.php';
    }
    $role = normalizeRole($role);
    if ($role === '' || $role === normalizeRole(ROLE_RESIDENT)) {
        return false;
    }
    if ($module === 'officials') {
        return in_array($role, [normalizeRole(ROLE_SUPER_ADMIN), normalizeRole(ROLE_BARANGAY_CAPTAIN)], true);
    }
    if (in_array($role, [normalizeRole(ROLE_SUPER_ADMIN), normalizeRole(ROLE_BARANGAY_CAPTAIN)], true)) {
        return true;
    }
    if (notificationsRoleHasModule($role, $module)) {
        return true;
    }
    if ($module === 'certificates' && notificationsRoleHasModule($role, 'applications')) {
        return true;
    }
    if ($module === 'applications' && notificationsRoleHasModule($role, 'certificates')) {
        return true;
    }
    return false;
}

/**
 * Notify all active staff users who can access the given module.
 *
 * @param int $excludeUserId Actor to skip (e.g. who filed a walk-in complaint).
 */
function notificationsNotifyStaffForModule(
    string $module,
    string $title,
    string $message,
    string $type = 'info',
    ?string $eventType = null,
    ?string $linkUrl = null,
    ?string $payloadJson = null,
    int $excludeUserId = 0
): void {
    if (!function_exists('getRolePermissions')) {
        require_once __DIR__ . '/auth-check.php';
    }
    try {
        notificationsEnsureSchema();
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT DISTINCT id, role FROM users WHERE status = 'active' AND role IS NOT NULL AND role <> ?",
            [ROLE_RESIDENT]
        );
        foreach ($rows ?: [] as $row) {
            $uid = (int)($row['id'] ?? 0);
            $r = (string)($row['role'] ?? '');
            if ($uid <= 0 || ($excludeUserId > 0 && $uid === $excludeUserId)) {
                continue;
            }
            if (!notificationsRoleCanAccessModule($r, $module)) {
                continue;
            }
            notificationsInsertForUser($uid, $title, $message, $type, $eventType, $linkUrl, $payloadJson);
        }
    } catch (Exception $e) {
        error_log('notificationsNotifyStaffForModule: ' . $e->getMessage());
    }
}

/**
 * Notify all portal residents (users with resident_id) about a published announcement.
 */
function notificationsNotifyResidentsAnnouncement(int $announcementId, string $title): void {
    $title = trim($title);
    if ($title === '') {
        $title = 'Announcement';
    }
    try {
        notificationsEnsureSchema();
        $db = Database::getInstance();
        $link = BASE_URL . 'resident_announcements.php';
        $payload = json_encode(['announcement_id' => $announcementId], JSON_UNESCAPED_UNICODE);
        $msg = 'A new barangay announcement has been posted: ' . $title;
        $db->query(
            "INSERT INTO notifications (resident_id, user_id, title, message, type, event_type, link_url, payload, is_read, created_at)
             SELECT u.resident_id, NULL, ?, ?, 'info', 'announcement_published', ?, ?, 0, NOW()
             FROM users u
             WHERE u.status = 'active'
               AND u.resident_id IS NOT NULL
               AND u.resident_id > 0
               AND LOWER(COALESCE(u.role, '')) = ?",
            [
                'New announcement',
                $msg,
                $link,
                $payload,
                strtolower(ROLE_RESIDENT),
            ]
        );
    } catch (Exception $e) {
        error_log('notificationsNotifyResidentsAnnouncement: ' . $e->getMessage());
    }
}

/**
 * True if DB status value represents published/active announcement.
 */
function notificationsAnnouncementDbStatusIsPublished(string $dbStatus): bool {
    $s = strtolower(trim($dbStatus));
    return in_array($s, ['published', 'active', '1'], true);
}
