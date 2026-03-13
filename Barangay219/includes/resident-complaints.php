<?php
if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/auth-check.php';

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
            'status' => 'Pending Review'
        ];
    }

    return [
        'jurisdiction_status' => 'Outside Jurisdiction',
        'status' => 'Referred to Other Barangay'
    ];
}

function residentComplaintsGenerateReference($db) {
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
    $labels = [
        'pending' => 'Pending Review',
        'under_review' => 'Under Investigation',
        'resolved' => 'Resolved',
        'dismissed' => 'Dismissed'
    ];

    return $labels[$status] ?? ($status ?: 'Pending Review');
}

function residentComplaintsStatusClass($status) {
    $status = residentComplaintsDisplayStatus($status);

    $map = [
        'Pending Review' => 'warning',
        'Under Investigation' => 'info',
        'Scheduled for Mediation' => 'primary',
        'Referred to Other Barangay' => 'secondary',
        'Resolved' => 'success',
        'Dismissed' => 'danger'
    ];

    return $map[$status] ?? 'secondary';
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
