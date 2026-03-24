<?php
header('Content-Type: application/json; charset=UTF-8');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth-check.php';

requireLogin();
requireModuleAccess('blotters');

if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_BARANGAY_CAPTAIN])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (!canPerformModulePermission('blotters', 'can_edit')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

function ensureProcessCaseSchema($db) {
    $ddl = [
        "ALTER TABLE blotter_records ADD COLUMN IF NOT EXISTS respondent_id INT(11) DEFAULT NULL",
        "ALTER TABLE blotter_records ADD COLUMN IF NOT EXISTS hearing_date DATETIME NULL",
        "ALTER TABLE blotter_records ADD COLUMN IF NOT EXISTS settlement_date DATE NULL",
        "ALTER TABLE blotter_records ADD COLUMN IF NOT EXISTS dismissal_reason TEXT NULL",
        "ALTER TABLE blotter_records ADD COLUMN IF NOT EXISTS resolution_file VARCHAR(255) NULL",
        "ALTER TABLE blotter_records MODIFY COLUMN hearing_date DATETIME NULL",
    ];

    foreach ($ddl as $sql) {
        try {
            $db->query($sql);
        } catch (Exception $e) {
        }
    }
}

function uploadResolutionFile($file) {
    if (!$file || !isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        return '__UPLOAD_ERROR__';
    }

    $maxSize = 5 * 1024 * 1024;
    if ((int)($file['size'] ?? 0) > $maxSize) {
        return '__UPLOAD_ERROR__';
    }

    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!in_array($ext, $allowed, true)) {
        return '__UPLOAD_ERROR__';
    }

    $uploadDir = PUBLIC_PATH . '/uploads/blotter_resolutions';
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
        return '__UPLOAD_ERROR__';
    }

    $filename = 'resolution_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $destination = $uploadDir . '/' . $filename;
    if (!@move_uploaded_file((string)$file['tmp_name'], $destination)) {
        return '__UPLOAD_ERROR__';
    }

    return 'uploads/blotter_resolutions/' . $filename;
}

function normalizeDateOrNull($value) {
    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return null;
    }
    return date('Y-m-d', $ts);
}

function normalizeDateTimeOrNull($value) {
    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return null;
    }
    return date('Y-m-d H:i:s', $ts);
}

try {
    $caseId = (int)($_POST['case_id'] ?? 0);
    $status = sanitizeInput($_POST['status'] ?? '');
    $respondentId = !empty($_POST['respondent_id']) ? (int)$_POST['respondent_id'] : null;
    $respondentsJsonRaw = $_POST['respondents_json'] ?? null;
    $adminNotes = sanitizeInput($_POST['admin_notes'] ?? '');
    $hearingDate = normalizeDateTimeOrNull($_POST['hearing_date'] ?? '');
    $settlementDate = normalizeDateOrNull($_POST['settlement_date'] ?? '');
    $dismissalReason = sanitizeInput($_POST['dismissal_reason'] ?? '');

    if ($caseId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Case ID required']);
        exit;
    }

    $allowedStatus = ['pending', 'investigation', 'mediation', 'settled', 'dismissed'];
    if (!in_array($status, $allowedStatus, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }

    if ($status === 'mediation' && $hearingDate === null) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Hearing date is required for mediation status.']);
        exit;
    }

    $resolutionPath = uploadResolutionFile($_FILES['resolution_file'] ?? null);
    if ($resolutionPath === '__UPLOAD_ERROR__') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Resolution file upload failed.']);
        exit;
    }

    $db = Database::getInstance();
    ensureProcessCaseSchema($db);

    $existing = $db->fetchOne(
        'SELECT id, status, respondent_id, settlement_date, hearing_date, dismissal_reason, resolution_file FROM blotter_records WHERE id = ? LIMIT 1',
        [$caseId]
    );

    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Case not found']);
        exit;
    }

    $nextHearingDate = null;
    $nextSettlementDate = null;
    $nextDismissalReason = null;
    $nextResolutionFile = null;

    if ($status === 'mediation') {
        $nextHearingDate = $hearingDate;
    } elseif ($status === 'settled') {
        $nextSettlementDate = $settlementDate;
        $nextResolutionFile = $resolutionPath ?: ($existing['resolution_file'] ?? null);
    } elseif ($status === 'dismissed') {
        $nextDismissalReason = $dismissalReason !== '' ? $dismissalReason : null;
    }

    $db->query('START TRANSACTION');

    $normalizedRespondents = null;
    $primaryRespondentId = null;
    $primaryRespondentName = null;

    if ($respondentsJsonRaw !== null && $respondentsJsonRaw !== '') {
        $decoded = json_decode((string)$respondentsJsonRaw, true);
        if (!is_array($decoded)) {
            $db->query('ROLLBACK');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid respondents payload']);
            exit;
        }

        $normalizedRespondents = [];
        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $residentId = isset($entry['resident_id']) ? (int)$entry['resident_id'] : 0;
            if ($residentId > 0) {
                $resident = $db->fetchOne(
                    'SELECT id, first_name, middle_name, last_name, address, contact_number FROM residents WHERE id = ? LIMIT 1',
                    [$residentId]
                );
                $name = $resident
                    ? trim((string)($resident['first_name'] ?? '') . ' ' . (string)($resident['middle_name'] ?? '') . ' ' . (string)($resident['last_name'] ?? ''))
                    : ($entry['name'] ?? '');
                $normalizedRespondents[] = [
                    'name' => $name,
                    'address' => $resident ? (string)($resident['address'] ?? '') : ($entry['address'] ?? ''),
                    'contact' => $resident ? (string)($resident['contact_number'] ?? '') : ($entry['contact'] ?? ''),
                    'residency' => 'resident',
                    'resident_id' => $residentId,
                ];
                continue;
            }

            $name = sanitizeInput((string)($entry['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $normalizedRespondents[] = [
                'name' => $name,
                'address' => sanitizeInput((string)($entry['address'] ?? '')),
                'contact' => sanitizeInput((string)($entry['contact'] ?? '')),
                'residency' => (($entry['residency'] ?? '') === 'resident') ? 'resident' : 'non_resident',
                'resident_id' => null,
            ];
        }

        $firstResident = null;
        foreach ($normalizedRespondents as $item) {
            if (!empty($item['resident_id'])) {
                $firstResident = (int)$item['resident_id'];
                break;
            }
        }
        $primaryRespondentId = $firstResident ?: null;
        $primaryRespondentName = !empty($normalizedRespondents[0]['name']) ? (string)$normalizedRespondents[0]['name'] : null;
    } elseif ($respondentId !== null) {
        $resident = $db->fetchOne(
            'SELECT id, first_name, middle_name, last_name, address, contact_number FROM residents WHERE id = ? LIMIT 1',
            [$respondentId]
        );

        if ($resident) {
            $normalizedRespondents = [[
                'name' => trim(($resident['first_name'] ?? '') . ' ' . ($resident['middle_name'] ?? '') . ' ' . ($resident['last_name'] ?? '')),
                'address' => $resident['address'] ?? '',
                'contact' => $resident['contact_number'] ?? '',
                'residency' => 'resident',
                'resident_id' => $respondentId,
            ]];
            $primaryRespondentId = $respondentId;
            $primaryRespondentName = (string)($normalizedRespondents[0]['name'] ?? null);
        }
    }

    $updates = [
        'status = ?',
        'respondent_id = ?',
        'respondent_name_raw = ?',
        'respondent_name = ?',
        'admin_updates = ?',
        'hearing_date = ?',
        'settlement_date = ?',
        'dismissal_reason = ?',
        'resolution_file = ?',
        'updated_at = NOW()'
    ];

    $params = [
        $status,
        $primaryRespondentId,
        $primaryRespondentName,
        $normalizedRespondents !== null ? json_encode($normalizedRespondents, JSON_UNESCAPED_UNICODE) : null,
        $adminNotes,
        $nextHearingDate,
        $nextSettlementDate,
        $nextDismissalReason,
        $nextResolutionFile,
    ];

    $params[] = $caseId;
    $db->query('UPDATE blotter_records SET ' . implode(', ', $updates) . ' WHERE id = ?', $params);

    $db->query('COMMIT');

    echo json_encode([
        'success' => true,
        'message' => 'Case processed successfully',
        'data' => [
            'case_id' => $caseId,
            'status' => $status,
            'respondent_id' => $primaryRespondentId,
            'respondents' => $normalizedRespondents,
            'hearing_date' => $nextHearingDate,
            'settlement_date' => $nextSettlementDate,
            'dismissal_reason' => $nextDismissalReason,
            'resolution_file' => $nextResolutionFile,
        ]
    ]);
} catch (Exception $e) {
    try {
        if (isset($db)) {
            $db->query('ROLLBACK');
        }
    } catch (Exception $rollbackError) {
    }

    error_log('Blotter process_case error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
