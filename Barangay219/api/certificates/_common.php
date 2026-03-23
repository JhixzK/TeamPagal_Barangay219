<?php
/**
 * Shared helpers for resident certificate request endpoints.
 */

header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth-check.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

function certJsonResponse($success, $data = null, $message = '', $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode([
        'success' => (bool)$success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function requireResidentSession() {
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        certJsonResponse(false, null, 'Unauthorized', 401);
    }

    $realRole = normalizeRole(getRealUserRole());
    $isTrueResident = ($realRole === normalizeRole(ROLE_RESIDENT));
    $isStaffInResidentView = !$isTrueResident
        && function_exists('isResidentView')
        && isResidentView();

    if (!$isTrueResident && !$isStaffInResidentView) {
        certJsonResponse(false, null, 'Forbidden', 403);
    }

    $residentId = (int)($_SESSION['resident_id'] ?? 0);
    if ($residentId <= 0) {
        certJsonResponse(false, null, 'Resident session is invalid', 401);
    }

    return $residentId;
}

function ensureCertificateRequestSchema() {
    $db = Database::getInstance();

    $db->query(
        "CREATE TABLE IF NOT EXISTS certificate_requests (
            id INT(11) NOT NULL AUTO_INCREMENT,
            resident_id INT(11) NOT NULL,
            certificate_type VARCHAR(120) NOT NULL,
            purpose TEXT DEFAULT NULL,
            reference_number VARCHAR(50) NOT NULL,
            status ENUM('pending','approved','ready_for_pickup','rejected','released') NOT NULL DEFAULT 'pending',
            attachment VARCHAR(255) DEFAULT NULL,
            cert_name VARCHAR(255) DEFAULT NULL,
            cert_address TEXT DEFAULT NULL,
            cert_purpose TEXT DEFAULT NULL,
            cert_body TEXT DEFAULT NULL,
            date_issued DATE DEFAULT NULL,
            control_number VARCHAR(50) DEFAULT NULL,
            approved_at DATETIME DEFAULT NULL,
            admin_id INT(11) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_reference_number (reference_number),
            KEY idx_resident_id (resident_id),
            KEY idx_status (status),
            CONSTRAINT fk_certificate_requests_resident
                FOREIGN KEY (resident_id) REFERENCES residents(id)
                ON UPDATE CASCADE ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $columns = $db->fetchAll("SHOW COLUMNS FROM certificate_requests");
    $columnMap = [];
    foreach ($columns as $column) {
        $columnMap[$column['Field']] = $column;
    }

    $addColumnIfMissing = function ($name, $definition) use ($db, $columnMap) {
        if (!isset($columnMap[$name])) {
            $db->query("ALTER TABLE certificate_requests ADD COLUMN {$name} {$definition}");
        }
    };

    $addColumnIfMissing('reference_number', "VARCHAR(50) NULL");
    $addColumnIfMissing('attachment', "VARCHAR(255) NULL");
    $addColumnIfMissing('cert_name', "VARCHAR(255) NULL");
    $addColumnIfMissing('cert_address', "TEXT NULL");
    $addColumnIfMissing('cert_purpose', "TEXT NULL");
    $addColumnIfMissing('cert_body', "TEXT NULL");
    $addColumnIfMissing('date_issued', "DATE NULL");
    $addColumnIfMissing('control_number', "VARCHAR(50) NULL");
    $addColumnIfMissing('approved_at', "DATETIME NULL");
    $addColumnIfMissing('admin_id', "INT(11) NULL");
    $addColumnIfMissing('created_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    $addColumnIfMissing('updated_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

    if (isset($columnMap['certificate_type']) && stripos($columnMap['certificate_type']['Type'], 'varchar') === false) {
        $db->query("ALTER TABLE certificate_requests MODIFY COLUMN certificate_type VARCHAR(120) NOT NULL");
    }

    $db->query("UPDATE certificate_requests
                SET status = 'approved',
                    approved_at = COALESCE(approved_at, updated_at, created_at)
                WHERE status = 'under_review'");
    $db->query("UPDATE certificate_requests SET status = 'ready_for_pickup' WHERE status = 'approved' AND control_number IS NOT NULL AND control_number <> ''");
    $db->query("UPDATE certificate_requests SET status = 'released' WHERE status = 'issued'");
    $db->query("UPDATE certificate_requests SET status = 'rejected' WHERE status = 'cancelled'");

    $db->query("ALTER TABLE certificate_requests MODIFY COLUMN status ENUM('pending','approved','ready_for_pickup','rejected','released') NOT NULL DEFAULT 'pending'");

    // Backfill reference_number for existing records before enforcing uniqueness.
    $missingRefs = $db->fetchAll("SELECT id, created_at FROM certificate_requests WHERE reference_number IS NULL OR reference_number = '' ORDER BY id ASC");
    foreach ($missingRefs as $row) {
        $id = (int)$row['id'];
        $year = date('Y', strtotime($row['created_at'] ?: 'now'));
        $referenceNumber = 'REQ-BRGY219-' . $year . '-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
        $db->query("UPDATE certificate_requests SET reference_number = ? WHERE id = ?", [$referenceNumber, $id]);
    }

    $indexExists = $db->fetchOne("SHOW INDEX FROM certificate_requests WHERE Key_name = 'uniq_reference_number'");
    if (!$indexExists) {
        $db->query("ALTER TABLE certificate_requests ADD UNIQUE KEY uniq_reference_number (reference_number)");
    }

    if (isset($columnMap['reference_number']) && $columnMap['reference_number']['Null'] === 'YES') {
        $db->query("ALTER TABLE certificate_requests MODIFY COLUMN reference_number VARCHAR(50) NOT NULL");
    }
}

function generateCertificateReferenceNumber() {
    $db = Database::getInstance();
    $year = date('Y');
    $prefix = 'REQ-BRGY219-' . $year . '-';

    $lastRow = $db->fetchOne(
        "SELECT reference_number FROM certificate_requests WHERE reference_number LIKE ? ORDER BY id DESC LIMIT 1",
        [$prefix . '%']
    );

    $nextSequence = 1;
    if (!empty($lastRow['reference_number']) && preg_match('/REQ-BRGY219-\\d{4}-(\\d{5})$/', $lastRow['reference_number'], $matches)) {
        $nextSequence = ((int)$matches[1]) + 1;
    }

    return $prefix . str_pad((string)$nextSequence, 5, '0', STR_PAD_LEFT);
}

function normalizeCertificateType($value) {
    return sanitizeInput((string)$value);
}

function saveAttachmentIfPresent($inputKey = 'documents') {
    if (empty($_FILES[$inputKey]) && empty($_FILES['documents'])) {
        $fallbackKey = 'documents[]';
        if (empty($_FILES[$fallbackKey])) {
            return null;
        }
        $inputKey = $fallbackKey;
    }

    $fileBucket = $_FILES[$inputKey];
    $hasMultiple = is_array($fileBucket['name']);

    $name = $hasMultiple ? ($fileBucket['name'][0] ?? '') : ($fileBucket['name'] ?? '');
    $tmpName = $hasMultiple ? ($fileBucket['tmp_name'][0] ?? '') : ($fileBucket['tmp_name'] ?? '');
    $error = $hasMultiple ? ($fileBucket['error'][0] ?? UPLOAD_ERR_NO_FILE) : ($fileBucket['error'] ?? UPLOAD_ERR_NO_FILE);
    $size = $hasMultiple ? ($fileBucket['size'][0] ?? 0) : ($fileBucket['size'] ?? 0);

    if ($error === UPLOAD_ERR_NO_FILE || !$name) {
        return null;
    }

    if ($error !== UPLOAD_ERR_OK) {
        certJsonResponse(false, null, 'File upload failed', 400);
    }

    if ($size > 5 * 1024 * 1024) {
        certJsonResponse(false, null, 'Attachment exceeds 5MB limit', 400);
    }

    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    if (!in_array($extension, $allowed, true)) {
        certJsonResponse(false, null, 'Invalid attachment type', 400);
    }

    $relativeDir = 'uploads/applications/certificate_requests';
    $absoluteDir = dirname(__DIR__, 2) . '/' . $relativeDir;
    if (!is_dir($absoluteDir)) {
        mkdir($absoluteDir, 0775, true);
    }

    $safeFileName = time() . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
    $absolutePath = $absoluteDir . '/' . $safeFileName;
    $relativePath = $relativeDir . '/' . $safeFileName;

    if (!move_uploaded_file($tmpName, $absolutePath)) {
        certJsonResponse(false, null, 'Unable to save attachment', 500);
    }

    return $relativePath;
}
