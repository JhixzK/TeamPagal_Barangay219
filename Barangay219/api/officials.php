<?php
/**
 * E-Barangay Information Management System
 * Officials Management API
 */

header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireAnyRole([ROLE_SUPER_ADMIN, ROLE_BARANGAY_CAPTAIN]);
requireModuleAccess('officials');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        listOfficials();
        break;
    case 'resident_search':
        residentSearch();
        break;
    case 'get':
        getOfficial();
        break;
    case 'create':
        createOfficial();
        break;
    case 'update':
        updateOfficial();
        break;
    case 'delete':
        deleteOfficial();
        break;
    default:
        sendResponse(false, 'Invalid action', null, 400);
}

function officialsTableExists($db) {
    static $exists = null;
    if ($exists !== null) return $exists;
    try {
        $row = $db->fetchOne("SHOW TABLES LIKE 'officials'");
        $exists = !empty($row);
    } catch (Exception $e) {
        $exists = false;
    }
    return $exists;
}

function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

function normalizePosition($pos) {
    $pos = strtolower(trim((string)$pos));
    $map = [
        'captain' => 'barangay_captain',
        'punong barangay' => 'barangay_captain',
        'barangay captain' => 'barangay_captain',
        'barangay_captain' => 'barangay_captain',
        'kagawad' => 'kagawad',
        'sk' => 'sk_chairperson',
        'sk chairperson' => 'sk_chairperson',
        'sk_chairperson' => 'sk_chairperson',
        'secretary' => 'secretary',
        'treasurer' => 'treasurer'
    ];
    return $map[$pos] ?? $pos;
}

function getCoreLimits() {
    return [
        'barangay_captain' => 1,
        'kagawad' => 7,
        'sk_chairperson' => 1,
        'secretary' => 1,
        'treasurer' => 1
    ];
}

function countActiveByPosition($db, $excludeId = null) {
    $params = [];
    $where = "status = 'active'";
    if ($excludeId) {
        $where .= " AND id <> ?";
        $params[] = (int)$excludeId;
    }
    $rows = $db->fetchAll("SELECT position, COUNT(*) AS cnt FROM officials WHERE $where GROUP BY position", $params);
    $counts = [];
    foreach ($rows as $r) {
        $counts[$r['position']] = (int)$r['cnt'];
    }
    return $counts;
}

function validateCoreLimits($db, $position, $status, $excludeId = null) {
    if ($status !== 'active') {
        return null;
    }
    $limits = getCoreLimits();
    if (!isset($limits[$position])) {
        return 'Invalid position.';
    }
    $counts = countActiveByPosition($db, $excludeId);
    $current = (int)($counts[$position] ?? 0);
    if ($current >= (int)$limits[$position]) {
        if ($position === 'kagawad') {
            return 'You can only have 7 active Kagawad officials.';
        }
        return 'This position already has an active official.';
    }

    // Total core officials active must not exceed 10
    $totalActive = 0;
    foreach ($counts as $c) $totalActive += (int)$c;
    if ($totalActive >= 10) {
        return 'You can only have 10 active core officials.';
    }
    return null;
}

function listOfficials() {
    try {
        $db = Database::getInstance();
        if (!officialsTableExists($db)) {
            sendResponse(true, 'Officials table not found', []);
        }
        $rows = $db->fetchAll("SELECT * FROM officials ORDER BY status DESC, position ASC, full_name ASC");
        sendResponse(true, 'Officials loaded', $rows);
    } catch (Exception $e) {
        sendResponse(false, 'Error loading officials', null, 500);
    }
}

function residentSearch() {
    $q = sanitizeInput($_GET['q'] ?? $_POST['q'] ?? '');
    $q = trim((string)$q);
    if ($q === '' || strlen($q) < 2) {
        sendResponse(true, 'Resident search', []);
    }

    try {
        $db = Database::getInstance();
        $term = '%' . $q . '%';
        $rows = $db->fetchAll(
            "SELECT id, resident_code, first_name, middle_name, last_name, suffix
             FROM residents
             WHERE status = 'active'
               AND (
                   resident_code LIKE ?
                   OR first_name LIKE ?
                   OR middle_name LIKE ?
                   OR last_name LIKE ?
                   OR CONCAT(first_name, ' ', last_name) LIKE ?
                   OR CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?
               )
             ORDER BY id ASC
             LIMIT 25",
            [$term, $term, $term, $term, $term, $term]
        );

        $out = array_map(static function ($r) {
            $full = trim(($r['first_name'] ?? '') . ' ' . ($r['middle_name'] ?? '') . ' ' . ($r['last_name'] ?? '') . ' ' . ($r['suffix'] ?? ''));
            $full = preg_replace('/\s+/', ' ', $full);
            $code = $r['resident_code'] ?? '';
            return [
                'id' => (int)$r['id'],
                'resident_code' => $code,
                'full_name' => $full,
                'label' => trim($full . ($code ? " ($code)" : ''))
            ];
        }, $rows);

        sendResponse(true, 'Resident search', $out);
    } catch (Exception $e) {
        sendResponse(false, 'Error searching residents', null, 500);
    }
}

function resolveResidentFullName($db, $residentId) {
    $residentId = (int)$residentId;
    if ($residentId <= 0) return null;
    $row = $db->fetchOne(
        "SELECT first_name, middle_name, last_name, suffix
         FROM residents
         WHERE id = ?
         LIMIT 1",
        [$residentId]
    );
    if (!$row) return null;
    $full = trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? '') . ' ' . ($row['suffix'] ?? ''));
    $full = preg_replace('/\s+/', ' ', $full);
    return $full ?: null;
}

function usersTableHasColumn($db, $column) {
    static $cache = [];
    if (array_key_exists($column, $cache)) return $cache[$column];
    try {
        $row = $db->fetchOne("SHOW COLUMNS FROM users LIKE ?", [$column]);
        $cache[$column] = !empty($row);
    } catch (Exception $e) {
        $cache[$column] = false;
    }
    return $cache[$column];
}

function mapPositionToUserRole($position) {
    $p = strtolower(trim((string)$position));
    if ($p === 'barangay_captain') return ROLE_BARANGAY_CAPTAIN;
    if ($p === 'kagawad') return ROLE_KAGAWA;
    if ($p === 'sk_chairperson') return ROLE_SK_CHAIRMAN;
    if ($p === 'secretary') return ROLE_SECRETARY;
    if ($p === 'treasurer') return ROLE_TREASURER;
    return null;
}

function syncUserRoleForOfficial($db, $residentId, $position, $status) {
    $residentId = (int)$residentId;
    if ($residentId <= 0) return;
    if (strtolower(trim((string)$status)) !== 'active') return;

    if (!usersTableHasColumn($db, 'resident_id') || !usersTableHasColumn($db, 'role')) {
        return;
    }

    $targetRole = mapPositionToUserRole($position);
    if (!$targetRole) return;

    // If the resident has a linked user account, update its role.
    // Never override super_admin.
    $db->query(
        "UPDATE users
         SET role = ?
         WHERE resident_id = ?
           AND (role IS NULL OR role <> ?)",
        [$targetRole, $residentId, ROLE_SUPER_ADMIN]
    );
}

function getOfficial() {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if (!$id) {
        sendResponse(false, 'ID is required', null, 400);
    }
    try {
        $db = Database::getInstance();
        $row = $db->fetchOne("SELECT * FROM officials WHERE id = ?", [$id]);
        if (!$row) {
            sendResponse(false, 'Official not found', null, 404);
        }
        sendResponse(true, 'Official loaded', $row);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function createOfficial() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
    }
    $resident_id = (int)($_POST['resident_id'] ?? 0);
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $position = normalizePosition($_POST['position'] ?? '');
    $term_start = sanitizeInput($_POST['term_start'] ?? '');
    $term_end = sanitizeInput($_POST['term_end'] ?? '');
    $status = strtolower(trim((string)($_POST['status'] ?? 'active')));

    if ($position === '') {
        sendResponse(false, 'Position is required', null, 400);
    }
    if ($position === 'barangay_captain' && !isSuperAdmin()) {
        sendResponse(false, 'Only Super Admin can add Punong Barangay (Captain).', null, 403);
    }
    if ($status !== 'active' && $status !== 'inactive') {
        $status = 'active';
    }

    try {
        $db = Database::getInstance();
        if (!officialsTableExists($db)) {
            sendResponse(false, 'Officials table is missing. Run the migration.', null, 500);
        }

        if ($resident_id > 0) {
            $resolvedName = resolveResidentFullName($db, $resident_id);
            if (!$resolvedName) {
                sendResponse(false, 'Selected resident was not found.', null, 400);
            }
            $full_name = $resolvedName;
        }

        if ($full_name === '') {
            sendResponse(false, 'Please select a resident (full name is required).', null, 400);
        }

        $err = validateCoreLimits($db, $position, $status);
        if ($err) {
            sendResponse(false, $err, null, 400);
        }

        $db->query(
            "INSERT INTO officials (position, full_name, user_id, resident_id, term_start, term_end, status) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$position, $full_name, null, ($resident_id > 0 ? $resident_id : null), ($term_start ?: null), ($term_end ?: null), $status]
        );

        if ($resident_id > 0) {
            syncUserRoleForOfficial($db, $resident_id, $position, $status);
        }
        sendResponse(true, 'Official added', ['id' => $db->lastInsertId()]);
    } catch (Exception $e) {
        $msg = (defined('DEBUG_MODE') && DEBUG_MODE) ? ('Error: ' . $e->getMessage()) : 'Error adding official';
        sendResponse(false, $msg, null, 500);
    }
}

function updateOfficial() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
    }
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        sendResponse(false, 'ID is required', null, 400);
    }

    $resident_id = (int)($_POST['resident_id'] ?? 0);
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $position = normalizePosition($_POST['position'] ?? '');
    $term_start = sanitizeInput($_POST['term_start'] ?? '');
    $term_end = sanitizeInput($_POST['term_end'] ?? '');
    $status = strtolower(trim((string)($_POST['status'] ?? 'active')));

    if ($position === '') {
        sendResponse(false, 'Position is required', null, 400);
    }
    if ($status !== 'active' && $status !== 'inactive') {
        $status = 'active';
    }

    try {
        $db = Database::getInstance();
        $existing = $db->fetchOne("SELECT * FROM officials WHERE id = ?", [$id]);
        if (!$existing) {
            sendResponse(false, 'Official not found', null, 404);
        }

        if ($position === 'barangay_captain' && !isSuperAdmin()) {
            sendResponse(false, 'Only Super Admin can update Punong Barangay (Captain).', null, 403);
        }

        if ($resident_id > 0) {
            $resolvedName = resolveResidentFullName($db, $resident_id);
            if (!$resolvedName) {
                sendResponse(false, 'Selected resident was not found.', null, 400);
            }
            $full_name = $resolvedName;
        }

        if ($full_name === '') {
            sendResponse(false, 'Please select a resident (full name is required).', null, 400);
        }

        $err = validateCoreLimits($db, $position, $status, $id);
        if ($err) {
            sendResponse(false, $err, null, 400);
        }

        $db->query(
            "UPDATE officials SET position = ?, full_name = ?, user_id = ?, resident_id = ?, term_start = ?, term_end = ?, status = ? WHERE id = ?",
            [$position, $full_name, null, ($resident_id > 0 ? $resident_id : null), ($term_start ?: null), ($term_end ?: null), $status, $id]
        );

        if ($resident_id > 0) {
            syncUserRoleForOfficial($db, $resident_id, $position, $status);
        }
        sendResponse(true, 'Official updated', null);
    } catch (Exception $e) {
        $msg = (defined('DEBUG_MODE') && DEBUG_MODE) ? ('Error: ' . $e->getMessage()) : 'Error updating official';
        sendResponse(false, $msg, null, 500);
    }
}

function deleteOfficial() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
    }
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        sendResponse(false, 'ID is required', null, 400);
    }
    try {
        $db = Database::getInstance();
        $existing = $db->fetchOne("SELECT id, position FROM officials WHERE id = ?", [$id]);
        if (!$existing) {
            sendResponse(false, 'Official not found', null, 404);
        }

        $position = normalizePosition($existing['position'] ?? '');
        if ($position === 'barangay_captain' && !isSuperAdmin()) {
            sendResponse(false, 'Only Super Admin can remove the Punong Barangay (Captain).', null, 403);
        }

        // Soft delete: set inactive
        $db->query("UPDATE officials SET status = 'inactive' WHERE id = ?", [$id]);
        sendResponse(true, 'Official removed', null);
    } catch (Exception $e) {
        sendResponse(false, 'Error removing official', null, 500);
    }
}

