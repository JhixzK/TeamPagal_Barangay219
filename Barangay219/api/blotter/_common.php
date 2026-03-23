<?php
header('Content-Type: application/json; charset=UTF-8');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth-check.php';

function blotterJson(bool $success, string $message, $data = null, int $httpCode = 200): void {
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ]);
    exit();
}

function requireResidentSessionForBlotter(): int {
    requireLogin();

    if (!hasRole(ROLE_RESIDENT)) {
        blotterJson(false, 'Only resident accounts can submit incident reports.', null, 403);
    }

    $residentId = (int)($_SESSION['resident_id'] ?? 0);
    if ($residentId > 0) {
        return $residentId;
    }

    $userId = (int)(getCurrentUserId() ?? 0);
    if ($userId <= 0) {
        blotterJson(false, 'Unauthorized', null, 401);
    }

    $db = Database::getInstance();
    $row = $db->fetchOne('SELECT resident_id FROM users WHERE id = ? LIMIT 1', [$userId]);
    $residentId = (int)($row['resident_id'] ?? 0);

    if ($residentId <= 0) {
        $username = trim((string)($_SESSION['username'] ?? ''));
        if ($username !== '') {
            $resident = $db->fetchOne('SELECT id FROM residents WHERE resident_code = ? LIMIT 1', [$username]);
            $residentId = (int)($resident['id'] ?? 0);
            if ($residentId > 0) {
                $db->query('UPDATE users SET resident_id = ? WHERE id = ? AND (resident_id IS NULL OR resident_id = 0)', [$residentId, $userId]);
            }
        }
    }

    if ($residentId <= 0) {
        blotterJson(false, 'Resident profile is not linked to this account.', null, 400);
    }

    $_SESSION['resident_id'] = $residentId;
    return $residentId;
}

function ensureBlotterRecordsSchema(): void {
    $db = Database::getInstance();

    $db->query(
        "CREATE TABLE IF NOT EXISTS blotter_records (
            id INT(11) NOT NULL AUTO_INCREMENT,
            reference_no VARCHAR(20) NOT NULL,
            complainant_id INT(11) NOT NULL,
            incident_type ENUM('physical_assault','theft','threat','harassment','property_damage','domestic_dispute','public_disturbance','other') NOT NULL DEFAULT 'other',
            incident_location VARCHAR(255) NOT NULL,
            incident_datetime DATETIME NOT NULL,
            narrative TEXT NOT NULL,
            status ENUM('pending','investigation','mediation','settled','dismissed') NOT NULL DEFAULT 'pending',
            respondent_name_raw VARCHAR(255) DEFAULT NULL,
            respondent_name VARCHAR(255) DEFAULT NULL,
            respondent_id INT(11) DEFAULT NULL,
            witnesses TEXT DEFAULT NULL,
            is_confidential TINYINT(1) NOT NULL DEFAULT 0,
            action_requested VARCHAR(50) DEFAULT NULL,
            evidence_path VARCHAR(255) DEFAULT NULL,
            admin_updates TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_blotter_reference_no (reference_no),
            KEY idx_blotter_complainant (complainant_id),
            KEY idx_blotter_status (status),
            KEY idx_blotter_incident_datetime (incident_datetime),
            KEY idx_blotter_respondent_id (respondent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $hasRawNameColumn = $db->fetchOne("SHOW COLUMNS FROM blotter_records LIKE 'respondent_name_raw'");
    if (!$hasRawNameColumn) {
        $db->query("ALTER TABLE blotter_records ADD COLUMN respondent_name_raw VARCHAR(255) DEFAULT NULL AFTER status");
    }
}

function generateBlotterReferenceNumber(): string {
    $db = Database::getInstance();
    $year = date('Y');
    $prefix = 'BLT-' . $year . '-';

    $row = $db->fetchOne(
        'SELECT reference_no FROM blotter_records WHERE reference_no LIKE ? ORDER BY id DESC LIMIT 1',
        [$prefix . '%']
    );

    $seq = 1;
    if (!empty($row['reference_no']) && preg_match('/-(\\d+)$/', (string)$row['reference_no'], $m)) {
        $seq = (int)$m[1] + 1;
    }

    return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}

function saveBlotterEvidence(array $file): ?string {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        blotterJson(false, 'Evidence upload failed.', null, 400);
    }

    $size = (int)($file['size'] ?? 0);
    if ($size > 10 * 1024 * 1024) {
        blotterJson(false, 'Evidence file must be 10MB or below.', null, 400);
    }

    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        blotterJson(false, 'Only JPG, JPEG, PNG, or WEBP evidence files are allowed.', null, 400);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, (string)$file['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
    if ($mime !== '' && !in_array($mime, $allowedMime, true)) {
        blotterJson(false, 'Invalid evidence file type.', null, 400);
    }

    $relativeDir = 'uploads/blotter';
    $absDir = dirname(__DIR__, 2) . '/' . $relativeDir;
    if (!is_dir($absDir) && !mkdir($absDir, 0775, true)) {
        blotterJson(false, 'Unable to create evidence directory.', null, 500);
    }

    $name = 'blt_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $absPath = $absDir . '/' . $name;
    if (!move_uploaded_file((string)$file['tmp_name'], $absPath)) {
        blotterJson(false, 'Unable to save uploaded evidence.', null, 500);
    }

    return $relativeDir . '/' . $name;
}

function mapIncidentType(string $value): string {
    $v = strtolower(trim($value));
    $allowed = ['physical_assault','theft','threat','harassment','property_damage','domestic_dispute','public_disturbance','other'];
    return in_array($v, $allowed, true) ? $v : 'other';
}

function mapActionRequested(string $value): string {
    $v = trim($value);
    if ($v === '') {
        return 'Mediation';
    }
    $allowed = ['Mediation', 'Record Only', 'Immediate Intervention'];
    return in_array($v, $allowed, true) ? $v : 'Mediation';
}
