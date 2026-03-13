<?php
header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('blotters');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list': listBlotters(); break;
    case 'get': getBlotter(); break;
    case 'create':
        if (!canPerformModulePermission('blotters', 'can_create')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        createBlotter();
        break;
    case 'update':
        if (!canPerformModulePermission('blotters', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        updateBlotter();
        break;
    case 'delete':
        if (!canPerformModulePermission('blotters', 'can_delete')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        deleteBlotter();
        break;
    default: sendResponse(false, 'Invalid action', null, 400);
}

function listBlotters() {
    try {
        $db = Database::getInstance();
        $q = sanitizeInput($_GET['q'] ?? $_GET['search'] ?? '');
        $status = sanitizeInput($_GET['status'] ?? '');
        $from = sanitizeInput($_GET['from'] ?? '');
        $to = sanitizeInput($_GET['to'] ?? '');

        $where = '1=1';
        $params = [];
        if (!empty($q)) {
            $term = '%' . $q . '%';
            $where .= " AND (case_title LIKE ? OR complainant_name LIKE ? OR respondent_name LIKE ? OR status LIKE ?)";
            $params = array_merge($params, [$term, $term, $term, $term]);
        }
        if (!empty($status)) {
            $where .= " AND status = ?";
            $params[] = $status;
        }
        if (!empty($from)) {
            $where .= " AND DATE(incident_date) >= ?";
            $params[] = $from;
        }
        if (!empty($to)) {
            $where .= " AND DATE(incident_date) <= ?";
            $params[] = $to;
        }

        $sql = "SELECT * FROM blotters WHERE $where ORDER BY incident_date DESC";
        sendResponse(true, 'Retrieved', $db->fetchAll($sql, $params));
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
        if ($b) {
            $b['hearings'] = $db->fetchAll("SELECT * FROM blotter_hearings WHERE blotter_id = ? ORDER BY hearing_date ASC, id ASC", [$id]);
        }
        sendResponse($b ? true : false, $b ? 'Found' : 'Not found', $b);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function createBlotter() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $case_title = sanitizeInput($_POST['case_title'] ?? '');
    $incident_date = $_POST['incident_date'] ?? date('Y-m-d');
    $incident_type = sanitizeInput($_POST['incident_type'] ?? '');
    $incident_type_custom = sanitizeInput($_POST['incident_type_custom'] ?? '');
    if ($incident_type === 'other' && $incident_type_custom !== '') {
        $incident_type = $incident_type_custom;
    }
    $incident_location = sanitizeInput($_POST['incident_location'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');

    // Accept complainants/respondents as JSON arrays from frontend
    $complainants_raw = $_POST['complainants'] ?? null;
    $respondents_raw = $_POST['respondents'] ?? null;
    $complainant_name = '';
    $respondent_name = '';
    if ($complainants_raw) {
        // try to ensure it is valid JSON
        $decoded = json_decode($complainants_raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $complainant_name = json_encode($decoded);
        } else {
            $complainant_name = sanitizeInput($complainants_raw);
        }
    } else {
        $complainant_name = sanitizeInput($_POST['complainant_name'] ?? '');
    }
    if ($respondents_raw) {
        $decoded = json_decode($respondents_raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $respondent_name = json_encode($decoded);
        } else {
            $respondent_name = sanitizeInput($respondents_raw);
        }
    } else {
        $respondent_name = sanitizeInput($_POST['respondent_name'] ?? '');
    }

    $status = sanitizeInput($_POST['status'] ?? 'pending');
    $settlement_date = $_POST['settlement_date'] ?? null;
    $hearings_raw = $_POST['hearings'] ?? null;

    if (!$case_title || !$complainant_name || !$description) { sendResponse(false, 'Required fields missing', null, 400); return; }
    try {
        $db = Database::getInstance();
        ensureEnhancedBlotterColumns($db);
        $proofPath = handleProofUpload($_FILES['proof_of_incident'] ?? null);
        if ($proofPath === '__UPLOAD_ERROR__') {
            sendResponse(false, 'Proof upload failed. Check file type/size and try again.', null, 400);
            return;
        }

        $cols = ['case_title', 'complainant_name', 'respondent_name', 'incident_date', 'incident_location', 'description', 'status', 'settlement_date', 'handled_by'];
        $vals = [$case_title, $complainant_name, $respondent_name, $incident_date, $incident_location, $description, $status, $settlement_date, getCurrentUserId()];

        if (blotterTableHasColumn($db, 'incident_type')) {
            $cols[] = 'incident_type';
            $vals[] = $incident_type;
        }
        if (blotterTableHasColumn($db, 'proof_of_incident_path')) {
            $cols[] = 'proof_of_incident_path';
            $vals[] = $proofPath;
        }

        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $db->query("INSERT INTO blotters (" . implode(', ', $cols) . ") VALUES ($placeholders)", $vals);
        $blotter_id = $db->lastInsertId();
        if ($hearings_raw !== null) {
            $hearings = parseHearingsPayload($hearings_raw);
            if ($hearings === null) {
                sendResponse(false, 'Invalid hearings data', null, 400);
                return;
            }
            insertHearings($db, $blotter_id, $hearings);
        }
        sendResponse(true, 'Created', ['id' => $blotter_id]);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function updateBlotter() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    $hearings_raw = $_POST['hearings'] ?? null;
    $complainants_raw = $_POST['complainants'] ?? null;
    $respondents_raw = $_POST['respondents'] ?? null;
    try {
        $db = Database::getInstance();
        ensureEnhancedBlotterColumns($db);

        $updates = [];
        $params = [];
        foreach (['case_title', 'incident_date', 'incident_location', 'description', 'status', 'settlement_date'] as $field) {
            if (isset($_POST[$field])) {
                $updates[] = "$field = ?";
                $params[] = $field === 'incident_date' || $field === 'settlement_date' ? $_POST[$field] : sanitizeInput($_POST[$field]);
            }
        }

        if ($complainants_raw !== null) {
            $decoded = json_decode($complainants_raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $updates[] = "complainant_name = ?";
                $params[] = json_encode($decoded);
            } else {
                $updates[] = "complainant_name = ?";
                $params[] = sanitizeInput($complainants_raw);
            }
        } elseif (isset($_POST['complainant_name'])) {
            $updates[] = "complainant_name = ?";
            $params[] = sanitizeInput($_POST['complainant_name']);
        }

        if ($respondents_raw !== null) {
            $decoded = json_decode($respondents_raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $updates[] = "respondent_name = ?";
                $params[] = json_encode($decoded);
            } else {
                $updates[] = "respondent_name = ?";
                $params[] = sanitizeInput($respondents_raw);
            }
        } elseif (isset($_POST['respondent_name'])) {
            $updates[] = "respondent_name = ?";
            $params[] = sanitizeInput($_POST['respondent_name']);
        }

        if (isset($_POST['incident_type']) && blotterTableHasColumn($db, 'incident_type')) {
            $incident_type = sanitizeInput($_POST['incident_type']);
            $incident_type_custom = sanitizeInput($_POST['incident_type_custom'] ?? '');
            if ($incident_type === 'other' && $incident_type_custom !== '') {
                $incident_type = $incident_type_custom;
            }
            $updates[] = "incident_type = ?";
            $params[] = $incident_type;
        }

        $proofPath = handleProofUpload($_FILES['proof_of_incident'] ?? null);
        if ($proofPath === '__UPLOAD_ERROR__') {
            sendResponse(false, 'Proof upload failed. Check file type/size and try again.', null, 400);
            return;
        }
        if ($proofPath && blotterTableHasColumn($db, 'proof_of_incident_path')) {
            $updates[] = "proof_of_incident_path = ?";
            $params[] = $proofPath;
        }

        if (empty($updates)) { sendResponse(false, 'Nothing to update', null, 400); return; }
        $params[] = $id;

        $db->query("UPDATE blotters SET " . implode(', ', $updates) . " WHERE id = ?", $params);
        if ($hearings_raw !== null) {
            $hearings = parseHearingsPayload($hearings_raw);
            if ($hearings === null) {
                sendResponse(false, 'Invalid hearings data', null, 400);
                return;
            }
            $db->query("DELETE FROM blotter_hearings WHERE blotter_id = ?", [$id]);
            insertHearings($db, $id, $hearings);
        }
        sendResponse(true, 'Updated');
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function deleteBlotter() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    try {
        $db = Database::getInstance();
        $db->query("DELETE FROM blotters WHERE id = ?", [$id]);
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

function parseHearingsPayload($raw) {
    if ($raw === null || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return null;
    }
    return $decoded;
}

function normalizeDateValue($value) {
    $v = trim((string)$value);
    return $v === '' ? null : $v;
}

function insertHearings($db, $blotter_id, $hearings) {
    if (empty($hearings)) {
        return;
    }
    $allowed_statuses = ['scheduled', 'completed', 'postponed', 'cancelled'];
    foreach ($hearings as $hearing) {
        if (!is_array($hearing)) {
            continue;
        }
        $hearing_date = normalizeDateValue($hearing['hearing_date'] ?? null);
        $status = sanitizeInput($hearing['status'] ?? 'scheduled');
        if (!in_array($status, $allowed_statuses, true)) {
            $status = 'scheduled';
        }
        $outcome = sanitizeInput($hearing['outcome'] ?? '');
        $notes = sanitizeInput($hearing['notes'] ?? '');
        $next_hearing_date = normalizeDateValue($hearing['next_hearing_date'] ?? null);

        if (!$hearing_date && !$outcome && !$notes && !$next_hearing_date) {
            continue;
        }

        $db->query(
            "INSERT INTO blotter_hearings (blotter_id, hearing_date, status, outcome, notes, next_hearing_date) VALUES (?, ?, ?, ?, ?, ?)",
            [$blotter_id, $hearing_date, $status, $outcome, $notes, $next_hearing_date]
        );
    }
}

function blotterTableHasColumn($db, $column) {
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }
    try {
        $result = $db->fetchOne(
            "SELECT COUNT(*) AS cnt
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'blotters'
               AND COLUMN_NAME = ?",
            [$column]
        );
        $cache[$column] = !empty($result) && (int)($result['cnt'] ?? 0) > 0;
    } catch (Exception $e) {
        $cache[$column] = false;
    }
    return $cache[$column];
}

function handleProofUpload($file) {
    if (!$file || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return '__UPLOAD_ERROR__';
    }

    $maxSize = 5 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        return '__UPLOAD_ERROR__';
    }

    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    if (!in_array($ext, $allowed, true)) {
        return '__UPLOAD_ERROR__';
    }

    $uploadDir = PUBLIC_PATH . '/uploads/blotter_proofs';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $filename = 'proof_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $destination = $uploadDir . '/' . $filename;
    if (!@move_uploaded_file($file['tmp_name'], $destination)) {
        return '__UPLOAD_ERROR__';
    }

    return 'uploads/blotter_proofs/' . $filename;
}

function ensureEnhancedBlotterColumns($db) {
    try {
        $partyCols = $db->fetchAll(
            "SELECT COLUMN_NAME, DATA_TYPE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'blotters'
               AND COLUMN_NAME IN ('complainant_name', 'respondent_name')"
        );

        foreach ($partyCols as $col) {
            $name = strtolower((string)($col['COLUMN_NAME'] ?? ''));
            $type = strtolower((string)($col['DATA_TYPE'] ?? ''));
            if (($name === 'complainant_name' || $name === 'respondent_name') && $type !== 'text' && $type !== 'longtext' && $type !== 'mediumtext') {
                if ($name === 'complainant_name') {
                    $db->query("ALTER TABLE blotters MODIFY complainant_name TEXT NOT NULL");
                } else {
                    $db->query("ALTER TABLE blotters MODIFY respondent_name TEXT NULL");
                }
            }
        }
    } catch (Exception $e) {
    }

    try {
        $hasIncidentType = !empty($db->fetchOne("SHOW COLUMNS FROM blotters LIKE 'incident_type'"));
        if (!$hasIncidentType) {
            $db->query("ALTER TABLE blotters ADD COLUMN incident_type VARCHAR(100) NULL AFTER incident_location");
        }
    } catch (Exception $e) {
    }

    try {
        $hasProofPath = !empty($db->fetchOne("SHOW COLUMNS FROM blotters LIKE 'proof_of_incident_path'"));
        if (!$hasProofPath) {
            $db->query("ALTER TABLE blotters ADD COLUMN proof_of_incident_path VARCHAR(255) NULL AFTER description");
        }
    } catch (Exception $e) {
    }
}
