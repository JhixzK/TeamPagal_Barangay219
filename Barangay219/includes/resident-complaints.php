<?php
if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/auth-check.php';
require_once __DIR__ . '/complaint-statuses.php';

function residentComplaintsRequireResident() {
    requireLogin();

    if (!isResidentView()) {
        header('Location: ' . BASE_URL . 'dashboard.php');
        exit();
    }

    if (empty($_SESSION['resident_id'])) {
        header('Location: ' . BASE_URL . 'resident_dashboard.php?error=resident_record_missing');
        exit();
    }
}

function residentComplaintsGetResidentId() {
    return (int)($_SESSION['resident_id'] ?? 0);
}

function residentComplaintsGetResidentName() {
    $user = getUserInfo();
    if (!$user) {
        return $_SESSION['username'] ?? 'Resident';
    }

    $fullName = trim(($user['first_name'] ?? '') . ' ' . (($user['middle_name'] ?? '') ? ($user['middle_name'] . ' ') : '') . ($user['last_name'] ?? ''));

    return $fullName !== '' ? $fullName : ($_SESSION['username'] ?? 'Resident');
}

function residentComplaintsSystemBarangay() {
    $parts = explode(',', BARANGAY_NAME);
    return trim($parts[0] ?? BARANGAY_NAME);
}

function residentComplaintsNormalizeText($value) {
    $value = strtolower((string)$value);
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
    return trim((string)$value);
}

function residentComplaintsResolveJurisdiction($incidentBarangay) {
    $systemBarangay = residentComplaintsNormalizeText(residentComplaintsSystemBarangay());
    $incidentBarangay = residentComplaintsNormalizeText($incidentBarangay);

    if ($incidentBarangay !== '' && $incidentBarangay === $systemBarangay) {
        return [
            'jurisdiction_status' => 'Valid',
            'status' => 'pending'
        ];
    }

    return [
        'jurisdiction_status' => 'Outside Jurisdiction',
        'status' => 'rejected'
    ];
}

function residentComplaintsGenerateReference($db) {
    // Some installations may not yet have the `reference_number` column.
    // In that case we cannot generate/store a unique complaint reference in the DB.
    if (!residentComplaintsHasComplaintColumn($db, 'reference_number')) {
        return null;
    }

    $year = date('Y');
    $prefix = 'CMP-' . $year . '-';
    $row = $db->fetchOne(
        "SELECT reference_number FROM complaints WHERE reference_number LIKE ? ORDER BY reference_number DESC LIMIT 1",
        [$prefix . '%']
    );

    $next = 1;
    if (!empty($row['reference_number']) && preg_match('/^CMP-' . preg_quote($year, '/') . '-(\d+)$/', $row['reference_number'], $matches)) {
        $next = ((int)$matches[1]) + 1;
    }

    return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function residentComplaintsGetComplaintColumns($db) {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $rows = $db->fetchAll("SHOW COLUMNS FROM complaints");
        $cols = [];
        foreach ($rows as $row) {
            if (!empty($row['Field'])) {
                $cols[] = $row['Field'];
            }
        }
        $cache = $cols;
        return $cache;
    } catch (Exception $e) {
        $cache = [];
        return $cache;
    }
}

function residentComplaintsHasComplaintColumn($db, $column) {
    $column = (string)$column;
    if ($column === '') return false;
    $cols = residentComplaintsGetComplaintColumns($db);
    return in_array($column, $cols, true);
}

function residentComplaintsPickComplaintColumn($db, $candidates) {
    if (!is_array($candidates)) {
        return null;
    }
    foreach ($candidates as $col) {
        $col = (string)$col;
        if ($col !== '' && residentComplaintsHasComplaintColumn($db, $col)) {
            return $col;
        }
    }
    return null;
}

function residentComplaintsSelectExpr($db, $candidates, $alias, $fallbackSql = 'NULL') {
    $alias = preg_replace('/[^a-zA-Z0-9_]+/', '', (string)$alias);
    if ($alias === '') {
        $alias = 'col';
    }
    $col = residentComplaintsPickComplaintColumn($db, (array)$candidates);
    if ($col) {
        // Column names come from a known allow-list and are not user input.
        return $col . ' AS ' . $alias;
    }
    return $fallbackSql . ' AS ' . $alias;
}

function residentComplaintsDisplayReference($complaintRow) {
    $id = (int)($complaintRow['id'] ?? 0);
    $ref = (string)($complaintRow['reference_number'] ?? '');
    if ($ref !== '') {
        return $ref;
    }
    if ($id <= 0) {
        return 'Pending';
    }
    // Fallback reference format for older schemas (not stored in DB).
    return 'CMP-' . date('Y') . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
}

function residentComplaintsHandleUpload($file) {
    if (empty($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Evidence upload failed. Please try again.'];
    }

    if ((int)$file['size'] > MAX_UPLOAD_SIZE) {
        return [null, 'Evidence file must not exceed 5MB.'];
    }

    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $allowed = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'pdf' => ['application/pdf']
    ];

    if (!isset($allowed[$extension])) {
        return [null, 'Only JPG, JPEG, PNG, and PDF files are allowed.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
    if ($finfo) {
        finfo_close($finfo);
    }

    if ($mimeType === false || $mimeType === null || !in_array($mimeType, $allowed[$extension], true)) {
        return [null, 'The uploaded evidence file type is not valid.'];
    }

    $uploadDir = PUBLIC_PATH . '/uploads/complaints';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        return [null, 'Unable to prepare the evidence upload directory.'];
    }

    $filename = 'complaint_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $targetPath = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [null, 'Unable to save the uploaded evidence file.'];
    }

    return ['uploads/complaints/' . $filename, null];
}

function residentComplaintsDisplayStatus($status) {
    $code = strtolower(trim((string)$status));
    if ($code === '') {
        return complaintStatusLabel('pending');
    }
    if (complaintStatusIsValid($code)) {
        return complaintStatusLabel($code);
    }
    $legacy = [
        'pending review' => 'pending',
        'under review' => 'in_progress',
        'under_review' => 'in_progress',
        'under investigation' => 'in_progress',
        'scheduled for mediation' => 'in_progress',
        'referred to other barangay' => 'rejected',
        'resolved' => 'resolved',
        'dismissed' => 'rejected',
    ];
    if (isset($legacy[$code])) {
        return complaintStatusLabel($legacy[$code]);
    }
    return complaintStatusLabel('pending');
}

function residentComplaintsStatusClass($status) {
    $code = strtolower(trim((string)$status));
    $map = [
        'pending' => 'warning',
        'approved' => 'primary',
        'assigned' => 'info',
        'in_progress' => 'info',
        'resolved' => 'success',
        'rejected' => 'danger',
        'pending review' => 'warning',
        'under_review' => 'info',
        'under investigation' => 'info',
        'scheduled for mediation' => 'info',
        'referred to other barangay' => 'secondary',
        'dismissed' => 'danger',
    ];
    return $map[$code] ?? 'secondary';
}

function residentComplaintsEvidenceUrl($path) {
    if (!$path) {
        return null;
    }

    return BASE_URL . ltrim($path, '/');
}

function residentComplaintsIsImage($path) {
    $extension = strtolower(pathinfo((string)$path, PATHINFO_EXTENSION));
    return in_array($extension, ['jpg', 'jpeg', 'png'], true);
}

function residentComplaintsFetchById($db, $complaintId, $residentId) {
    return $db->fetchOne(
        "SELECT * FROM complaints WHERE id = ? AND resident_id = ? LIMIT 1",
        [(int)$complaintId, (int)$residentId]
    );
}

function residentComplaintsTableExists($db) {
    try {
        $row = $db->fetchOne("SHOW TABLES LIKE 'complaints'");
        return !empty($row);
    } catch (Exception $exception) {
        return false;
    }
}

function residentComplaintsMissingTableMessage() {
    return 'Complaints module database table is not available yet. Run migration file database/migrations/004_resident_complaints_module.sql then reload this page.';
}
