<?php
header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('blotters');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        listBlotters();
        break;
    case 'get':
        getBlotter();
        break;
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
    default:
        sendResponse(false, 'Invalid action', null, 400);
}

function listBlotters() {
    try {
        $db = Database::getInstance();
        ensureBlotterRecordsBridgeSchema($db);

        $q = sanitizeInput($_GET['q'] ?? $_GET['search'] ?? '');
        $status = sanitizeInput($_GET['status'] ?? '');
        $from = sanitizeInput($_GET['from'] ?? '');
        $to = sanitizeInput($_GET['to'] ?? '');

        $where = '1=1';
        $params = [];

        if ($q !== '') {
            $term = '%' . $q . '%';
            $where .= " AND (br.reference_no LIKE ? OR br.case_title LIKE ? OR br.complainant_name_raw LIKE ? OR br.respondent_name_raw LIKE ? OR br.narrative LIKE ? OR br.status LIKE ? OR CONCAT(r.first_name, ' ', r.last_name) LIKE ?)";
            $params = array_merge($params, [$term, $term, $term, $term, $term, $term, $term]);
        }

        if ($status !== '') {
            $dbStatuses = mapAdminStatusToDbStatuses($status);
            if (!empty($dbStatuses)) {
                $where .= ' AND br.status IN (' . implode(',', array_fill(0, count($dbStatuses), '?')) . ')';
                $params = array_merge($params, $dbStatuses);
            }
        }

        if ($from !== '') {
            $where .= ' AND DATE(br.incident_datetime) >= ?';
            $params[] = $from;
        }

        if ($to !== '') {
            $where .= ' AND DATE(br.incident_datetime) <= ?';
            $params[] = $to;
        }

        $rows = $db->fetchAll(
            "SELECT
                br.*,
                r.first_name AS complainant_first_name,
                r.middle_name AS complainant_middle_name,
                r.last_name AS complainant_last_name,
                r.address AS complainant_address,
                r.contact_number AS complainant_contact_number
             FROM blotter_records br
             LEFT JOIN residents r ON r.id = br.complainant_id
             WHERE $where
             ORDER BY br.created_at DESC, br.id DESC",
            $params
        );

        $mapped = array_map(function ($row) {
            $complainants = buildComplainantPayload($row);
            $respondents = buildRespondentPayload($row);

            return [
                'id' => (int)($row['id'] ?? 0),
                'reference_no' => (string)($row['reference_no'] ?? ''),
                'case_title' => (string)($row['case_title'] ?? '') !== ''
                    ? (string)$row['case_title']
                    : ('Resident Report ' . (string)($row['reference_no'] ?? '')),
                'complainant_name' => json_encode($complainants),
                'respondent_name' => json_encode($respondents),
                'incident_date' => !empty($row['incident_datetime']) ? date('Y-m-d', strtotime((string)$row['incident_datetime'])) : null,
                'incident_type' => (string)($row['incident_type'] ?? ''),
                'incident_location' => (string)($row['incident_location'] ?? ''),
                'description' => (string)($row['narrative'] ?? ''),
                'status' => mapDbStatusToAdminStatus((string)($row['status'] ?? 'pending')),
                'settlement_date' => $row['settlement_date'] ?? null,
                'proof_of_incident_path' => $row['evidence_path'] ?? null,
                'is_new_resident_submission' => (($row['source'] ?? 'resident') === 'resident' && (string)($row['status'] ?? '') === 'pending'),
            ];
        }, $rows);

        sendResponse(true, 'Retrieved', $mapped);
    } catch (Exception $e) {
        error_log('Admin blotter list error: ' . $e->getMessage());
        sendResponse(false, 'Error', null, 500);
    }
}

function getBlotter() {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        sendResponse(false, 'ID required', null, 400);
        return;
    }

    try {
        $db = Database::getInstance();
        ensureBlotterRecordsBridgeSchema($db);

        $row = $db->fetchOne(
            "SELECT
                br.*,
                r.first_name AS complainant_first_name,
                r.middle_name AS complainant_middle_name,
                r.last_name AS complainant_last_name,
                r.address AS complainant_address,
                r.contact_number AS complainant_contact_number
             FROM blotter_records br
             LEFT JOIN residents r ON r.id = br.complainant_id
             WHERE br.id = ?
             LIMIT 1",
            [$id]
        );

        if (!$row) {
            sendResponse(false, 'Not found', null, 404);
            return;
        }

        $hearings = [];
        if (!empty($row['hearings_json'])) {
            $decoded = json_decode((string)$row['hearings_json'], true);
            if (is_array($decoded)) {
                $hearings = $decoded;
            }
        }
        if (empty($hearings) && !empty($row['hearing_date'])) {
            $hearings[] = [
                'hearing_date' => $row['hearing_date'],
                'status' => 'scheduled',
                'outcome' => '',
                'notes' => '',
                'next_hearing_date' => ''
            ];
        }

        $payload = [
            'id' => (int)($row['id'] ?? 0),
            'reference_no' => (string)($row['reference_no'] ?? ''),
            'case_title' => (string)($row['case_title'] ?? '') !== ''
                ? (string)$row['case_title']
                : ('Resident Report ' . (string)($row['reference_no'] ?? '')),
            'incident_date' => !empty($row['incident_datetime']) ? date('Y-m-d', strtotime((string)$row['incident_datetime'])) : '',
            'incident_type' => (string)($row['incident_type'] ?? ''),
            'incident_location' => (string)($row['incident_location'] ?? ''),
            'description' => (string)($row['narrative'] ?? ''),
            'witnesses' => witnessesToMultiline((string)($row['witnesses'] ?? '')),
            'status' => mapDbStatusToAdminStatus((string)($row['status'] ?? 'pending')),
            'hearing_date' => (string)($row['hearing_date'] ?? ''),
            'settlement_date' => (string)($row['settlement_date'] ?? ''),
            'dismissal_reason' => (string)($row['dismissal_reason'] ?? ''),
            'resolution_file' => (string)($row['resolution_file'] ?? ''),
            'proof_of_incident_path' => $row['evidence_path'] ?? null,
            'admin_updates' => (string)($row['admin_updates'] ?? ''),
            'complainant_name' => json_encode(buildComplainantPayload($row)),
            'respondent_name' => json_encode(buildRespondentPayload($row)),
            'hearings' => $hearings,
        ];

        sendResponse(true, 'Found', $payload);
    } catch (Exception $e) {
        error_log('Admin blotter get error: ' . $e->getMessage());
        sendResponse(false, 'Error', null, 500);
    }
}

function createBlotter() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'POST required', null, 405);
        return;
    }

    $case_title = sanitizeInput($_POST['case_title'] ?? '');
    $incident_date = sanitizeInput($_POST['incident_date'] ?? date('Y-m-d'));
    $incident_type = sanitizeInput($_POST['incident_type'] ?? 'other');
    $incident_type_custom = sanitizeInput($_POST['incident_type_custom'] ?? '');
    $incident_location = sanitizeInput($_POST['incident_location'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $witnesses_text = trim((string)($_POST['witnesses'] ?? ''));
    $status = sanitizeInput($_POST['status'] ?? 'pending');
    $settlement_date = sanitizeInput($_POST['settlement_date'] ?? '');

    $complainants_raw = $_POST['complainants'] ?? null;
    $respondents_raw = $_POST['respondents'] ?? null;
    $hearings_raw = $_POST['hearings'] ?? null;

    $legacy_complainant = sanitizeInput($_POST['complainant_name'] ?? '');
    $legacy_respondent = sanitizeInput($_POST['respondent_name'] ?? '');

    if ($case_title === '' || $description === '' || $incident_location === '') {
        sendResponse(false, 'Required fields missing', null, 400);
        return;
    }

    try {
        $db = Database::getInstance();
        ensureBlotterRecordsBridgeSchema($db);

        $proofPath = handleProofUpload($_FILES['proof_of_incident'] ?? null);
        if ($proofPath === '__UPLOAD_ERROR__') {
            sendResponse(false, 'Proof upload failed. Check file type/size and try again.', null, 400);
            return;
        }

        $complainants = parsePartyPayload($complainants_raw, $legacy_complainant);
        $respondents = parsePartyPayload($respondents_raw, $legacy_respondent);
        if (empty($complainants)) {
            sendResponse(false, 'At least one complainant is required.', null, 400);
            return;
        }

        $hearings = parseHearingsPayload($hearings_raw);
        if ($hearings === null) {
            sendResponse(false, 'Invalid hearings data', null, 400);
            return;
        }

        $primaryComplainant = $complainants[0];
        $primaryRespondent = $respondents[0] ?? [];

        $complainantId = isset($primaryComplainant['resident_id']) ? (int)$primaryComplainant['resident_id'] : 0;
        if ($complainantId <= 0) {
            $complainantId = null;
        }

        $dbStatus = mapAdminStatusToDbStatus($status);
        $incidentDatetime = $incident_date !== '' ? ($incident_date . ' 00:00:00') : date('Y-m-d H:i:s');
        $witnessesPayload = normalizeWitnessesPayloadFromText($witnesses_text);
        $firstHearing = firstHearingDate($hearings);
        $referenceNo = generateBlotterReferenceNumberFromRecords($db);

        $db->query(
            'INSERT INTO blotter_records (
                reference_no,
                source,
                case_title,
                complainant_id,
                complainant_name_raw,
                incident_type,
                incident_type_detail,
                incident_location,
                incident_datetime,
                narrative,
                status,
                respondent_name_raw,
                respondent_name,
                witnesses,
                hearing_date,
                hearings_json,
                settlement_date,
                admin_updates,
                action_requested,
                evidence_path
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $referenceNo,
                'admin',
                $case_title,
                $complainantId,
                sanitizeInput((string)($primaryComplainant['name'] ?? '')),
                ($incident_type === 'other' && $incident_type_custom !== '') ? 'other' : $incident_type,
                ($incident_type === 'other' && $incident_type_custom !== '') ? $incident_type_custom : null,
                $incident_location,
                $incidentDatetime,
                $description,
                $dbStatus,
                sanitizeInput((string)($primaryRespondent['name'] ?? '')),
                json_encode($respondents),
                $witnessesPayload,
                $firstHearing,
                !empty($hearings) ? json_encode($hearings) : null,
                normalizeDateValue($settlement_date),
                null,
                null,
                $proofPath,
            ]
        );

        sendResponse(true, 'Created', ['id' => (int)$db->lastInsertId()]);
    } catch (Exception $e) {
        error_log('Admin blotter create error: ' . $e->getMessage());
        sendResponse(false, 'Error', null, 500);
    }
}

function updateBlotter() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'POST required', null, 405);
        return;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        sendResponse(false, 'ID required', null, 400);
        return;
    }

    $complainants_raw = $_POST['complainants'] ?? null;
    $respondents_raw = $_POST['respondents'] ?? null;
    $hearings_raw = $_POST['hearings'] ?? null;
    $witnesses_text = trim((string)($_POST['witnesses'] ?? ''));

    try {
        $db = Database::getInstance();
        ensureBlotterRecordsBridgeSchema($db);

        $updates = [];
        $params = [];

        if (isset($_POST['case_title'])) {
            $updates[] = 'case_title = ?';
            $params[] = sanitizeInput((string)$_POST['case_title']);
        }
        if (isset($_POST['incident_location'])) {
            $updates[] = 'incident_location = ?';
            $params[] = sanitizeInput((string)$_POST['incident_location']);
        }
        if (isset($_POST['description'])) {
            $updates[] = 'narrative = ?';
            $params[] = sanitizeInput((string)$_POST['description']);
        }
        if (isset($_POST['status'])) {
            $updates[] = 'status = ?';
            $params[] = mapAdminStatusToDbStatus((string)$_POST['status']);
        }
        if (isset($_POST['settlement_date'])) {
            $updates[] = 'settlement_date = ?';
            $params[] = normalizeDateValue((string)$_POST['settlement_date']);
        }
        if (isset($_POST['incident_date'])) {
            $incidentDate = trim((string)$_POST['incident_date']);
            if ($incidentDate !== '') {
                $updates[] = 'incident_datetime = ?';
                $params[] = $incidentDate . ' 00:00:00';
            }
        }

        if (isset($_POST['incident_type'])) {
            $incident_type = sanitizeInput((string)$_POST['incident_type']);
            $incident_type_custom = sanitizeInput((string)($_POST['incident_type_custom'] ?? ''));
            if ($incident_type === 'other' && $incident_type_custom !== '') {
                $updates[] = 'incident_type = ?';
                $params[] = 'other';
                $updates[] = 'incident_type_detail = ?';
                $params[] = $incident_type_custom;
            } else {
                $updates[] = 'incident_type = ?';
                $params[] = $incident_type;
                $updates[] = 'incident_type_detail = ?';
                $params[] = null;
            }
        }

        $updates[] = 'witnesses = ?';
        $params[] = normalizeWitnessesPayloadFromText($witnesses_text);

        if ($complainants_raw !== null) {
            $complainants = parsePartyPayload($complainants_raw, '');
            if (!empty($complainants)) {
                $primary = $complainants[0];
                $updates[] = 'complainant_name_raw = ?';
                $params[] = sanitizeInput((string)($primary['name'] ?? ''));
                $rid = isset($primary['resident_id']) ? (int)$primary['resident_id'] : 0;
                $updates[] = 'complainant_id = ?';
                $params[] = $rid > 0 ? $rid : null;
            }
        }

        if ($respondents_raw !== null) {
            $respondents = parsePartyPayload($respondents_raw, '');
            $updates[] = 'respondent_name = ?';
            $params[] = json_encode($respondents);
            $updates[] = 'respondent_name_raw = ?';
            $params[] = !empty($respondents[0]['name']) ? sanitizeInput((string)$respondents[0]['name']) : null;
        }

        $proofPath = handleProofUpload($_FILES['proof_of_incident'] ?? null);
        if ($proofPath === '__UPLOAD_ERROR__') {
            sendResponse(false, 'Proof upload failed. Check file type/size and try again.', null, 400);
            return;
        }
        if ($proofPath) {
            $updates[] = 'evidence_path = ?';
            $params[] = $proofPath;
        }

        if ($hearings_raw !== null) {
            $hearings = parseHearingsPayload($hearings_raw);
            if ($hearings === null) {
                sendResponse(false, 'Invalid hearings data', null, 400);
                return;
            }
            $updates[] = 'hearings_json = ?';
            $params[] = !empty($hearings) ? json_encode($hearings) : null;
            $updates[] = 'hearing_date = ?';
            $params[] = firstHearingDate($hearings);

            $remarks = firstHearingRemark($hearings);
            if ($remarks !== null) {
                $updates[] = 'admin_updates = ?';
                $params[] = $remarks;
            }
        }

        if (empty($updates)) {
            sendResponse(false, 'Nothing to update', null, 400);
            return;
        }

        $params[] = $id;
        $db->query('UPDATE blotter_records SET ' . implode(', ', $updates) . ' WHERE id = ?', $params);

        sendResponse(true, 'Updated');
    } catch (Exception $e) {
        error_log('Admin blotter update error: ' . $e->getMessage());
        sendResponse(false, 'Error', null, 500);
    }
}

function deleteBlotter() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'POST required', null, 405);
        return;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        sendResponse(false, 'ID required', null, 400);
        return;
    }

    try {
        $db = Database::getInstance();
        ensureBlotterRecordsBridgeSchema($db);
        $db->query('DELETE FROM blotter_records WHERE id = ?', [$id]);
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

function mapDbStatusToAdminStatus(string $status): string {
    $s = strtolower(trim($status));
    switch ($s) {
        case 'investigation':
        case 'mediation':
            return 'under_investigation';
        case 'settled':
            return 'settled';
        case 'dismissed':
            return 'referred';
        case 'pending':
        default:
            return 'pending';
    }
}

function mapAdminStatusToDbStatus(string $status): string {
    $s = strtolower(trim($status));
    switch ($s) {
        case 'under_investigation':
            return 'investigation';
        case 'resolved':
            return 'settled';
        case 'settled':
            return 'settled';
        case 'referred':
            return 'dismissed';
        case 'pending':
        default:
            return 'pending';
    }
}

function mapAdminStatusToDbStatuses(string $status): array {
    $s = strtolower(trim($status));
    switch ($s) {
        case 'under_investigation':
            return ['investigation', 'mediation'];
        case 'resolved':
            return ['settled'];
        case 'settled':
            return ['settled'];
        case 'referred':
            return ['dismissed'];
        case 'pending':
            return ['pending'];
        default:
            return [];
    }
}

function parsePartyPayload($raw, string $fallback): array {
    if ($raw === null || $raw === '') {
        $clean = trim($fallback);
        return $clean !== '' ? [[
            'name' => sanitizeInput($clean),
            'address' => '',
            'contact' => '',
            'residency' => 'non_resident',
            'resident_id' => null,
        ]] : [];
    }

    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) {
        $clean = trim((string)$raw);
        return $clean !== '' ? [[
            'name' => sanitizeInput($clean),
            'address' => '',
            'contact' => '',
            'residency' => 'non_resident',
            'resident_id' => null,
        ]] : [];
    }

    $normalized = [];
    foreach ($decoded as $party) {
        if (!is_array($party)) continue;
        $name = sanitizeInput((string)($party['name'] ?? ''));
        if ($name === '') continue;
        $normalized[] = [
            'name' => $name,
            'address' => sanitizeInput((string)($party['address'] ?? '')),
            'contact' => sanitizeInput((string)($party['contact'] ?? '')),
            'residency' => (($party['residency'] ?? '') === 'resident') ? 'resident' : 'non_resident',
            'resident_id' => isset($party['resident_id']) ? (int)$party['resident_id'] : null,
        ];
    }
    return $normalized;
}

function buildComplainantPayload(array $row): array {
    if (!empty($row['complainant_id'])) {
        $name = trim(
            (string)($row['complainant_first_name'] ?? '') . ' ' .
            ((string)($row['complainant_middle_name'] ?? '') !== '' ? (string)$row['complainant_middle_name'] . ' ' : '') .
            (string)($row['complainant_last_name'] ?? '')
        );
        return [[
            'name' => $name !== '' ? $name : (string)($row['complainant_name_raw'] ?? ''),
            'address' => (string)($row['complainant_address'] ?? ''),
            'contact' => (string)($row['complainant_contact_number'] ?? ''),
            'residency' => 'resident',
            'resident_id' => (int)($row['complainant_id'] ?? 0),
        ]];
    }

    $raw = trim((string)($row['complainant_name_raw'] ?? ''));
    if ($raw === '') {
        return [];
    }
    return [[
        'name' => $raw,
        'address' => '',
        'contact' => '',
        'residency' => 'non_resident',
        'resident_id' => null,
    ]];
}

function buildRespondentPayload(array $row): array {
    $raw = (string)($row['respondent_name'] ?? '');
    $decoded = json_decode($raw, true);
    if (is_array($decoded) && !empty($decoded)) {
        return $decoded;
    }

    $name = trim((string)($row['respondent_name_raw'] ?? ''));
    if ($name === '') {
        $name = trim((string)($row['respondent_name'] ?? ''));
    }
    if ($name === '') {
        return [];
    }
    return [[
        'name' => $name,
        'address' => '',
        'contact' => '',
        'residency' => 'non_resident',
        'resident_id' => null,
    ]];
}

function normalizeWitnessesPayloadFromText(string $text): ?string {
    $trimmed = trim($text);
    if ($trimmed === '') {
        return null;
    }
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $trimmed));
    $clean = array_values(array_filter(array_map('trim', $lines)));
    if (empty($clean)) {
        return null;
    }
    return json_encode($clean, JSON_UNESCAPED_UNICODE);
}

function witnessesToMultiline(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $lines = array_values(array_filter(array_map(function($v) {
            return trim((string)$v);
        }, $decoded)));
        return implode("\n", $lines);
    }
    return $raw;
}

function firstHearingDate(array $hearings): ?string {
    foreach ($hearings as $h) {
        if (!is_array($h)) continue;
        $d = normalizeDateValue($h['hearing_date'] ?? null);
        if ($d) return $d;
    }
    return null;
}

function firstHearingRemark(array $hearings): ?string {
    foreach ($hearings as $h) {
        if (!is_array($h)) continue;
        $notes = trim((string)($h['notes'] ?? ''));
        if ($notes !== '') return sanitizeInput($notes);
        $outcome = trim((string)($h['outcome'] ?? ''));
        if ($outcome !== '') return sanitizeInput($outcome);
    }
    return null;
}

function generateBlotterReferenceNumberFromRecords($db): string {
    $year = date('Y');
    $prefix = 'BLT-' . $year . '-';
    $row = $db->fetchOne(
        'SELECT reference_no FROM blotter_records WHERE reference_no LIKE ? ORDER BY id DESC LIMIT 1',
        [$prefix . '%']
    );
    $seq = 1;
    if (!empty($row['reference_no']) && preg_match('/-(\d+)$/', (string)$row['reference_no'], $m)) {
        $seq = (int)$m[1] + 1;
    }
    return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
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

function ensureBlotterRecordsBridgeSchema($db) {
    $db->query(
        "CREATE TABLE IF NOT EXISTS blotter_records (
            id INT(11) NOT NULL AUTO_INCREMENT,
            reference_no VARCHAR(20) NOT NULL,
            source VARCHAR(20) NOT NULL DEFAULT 'resident',
            case_title VARCHAR(255) DEFAULT NULL,
            complainant_id INT(11) DEFAULT NULL,
            complainant_name_raw VARCHAR(255) DEFAULT NULL,
            incident_type VARCHAR(100) NOT NULL DEFAULT 'other',
            incident_type_detail VARCHAR(100) DEFAULT NULL,
            incident_location VARCHAR(255) NOT NULL,
            incident_datetime DATETIME NOT NULL,
            narrative TEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            respondent_name_raw VARCHAR(255) DEFAULT NULL,
            respondent_name TEXT DEFAULT NULL,
            respondent_id INT(11) DEFAULT NULL,
            witnesses TEXT DEFAULT NULL,
            hearing_date DATETIME DEFAULT NULL,
            hearings_json TEXT DEFAULT NULL,
            settlement_date DATE DEFAULT NULL,
            dismissal_reason TEXT DEFAULT NULL,
            resolution_file VARCHAR(255) DEFAULT NULL,
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
            KEY idx_blotter_respondent_id (respondent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    try {
        $nullable = $db->fetchOne("SHOW COLUMNS FROM blotter_records LIKE 'complainant_id'");
        if ($nullable && strtoupper((string)($nullable['Null'] ?? 'NO')) !== 'YES') {
            $db->query("ALTER TABLE blotter_records MODIFY complainant_id INT(11) NULL");
        }
    } catch (Exception $e) {
    }

    $requiredColumns = [
        'source' => "ALTER TABLE blotter_records ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'resident' AFTER reference_no",
        'case_title' => "ALTER TABLE blotter_records ADD COLUMN case_title VARCHAR(255) DEFAULT NULL AFTER source",
        'complainant_name_raw' => "ALTER TABLE blotter_records ADD COLUMN complainant_name_raw VARCHAR(255) DEFAULT NULL AFTER complainant_id",
        'respondent_id' => "ALTER TABLE blotter_records ADD COLUMN respondent_id INT(11) DEFAULT NULL AFTER respondent_name",
        'hearing_date' => "ALTER TABLE blotter_records ADD COLUMN hearing_date DATETIME DEFAULT NULL AFTER witnesses",
        'hearings_json' => "ALTER TABLE blotter_records ADD COLUMN hearings_json TEXT DEFAULT NULL AFTER hearing_date",
        'settlement_date' => "ALTER TABLE blotter_records ADD COLUMN settlement_date DATE DEFAULT NULL AFTER hearings_json",
        'dismissal_reason' => "ALTER TABLE blotter_records ADD COLUMN dismissal_reason TEXT DEFAULT NULL AFTER settlement_date",
        'resolution_file' => "ALTER TABLE blotter_records ADD COLUMN resolution_file VARCHAR(255) DEFAULT NULL AFTER dismissal_reason",
    ];

    foreach ($requiredColumns as $col => $sql) {
        try {
            $exists = $db->fetchOne(
                "SELECT COUNT(*) AS cnt
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'blotter_records' AND column_name = ?",
                [$col]
            );
            if ((int)($exists['cnt'] ?? 0) === 0) {
                $db->query($sql);
            }
        } catch (Exception $e) {
        }
    }

    try {
        $hearingColumn = $db->fetchOne(
            "SELECT data_type
             FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'blotter_records' AND column_name = 'hearing_date'"
        );
        $hearingType = strtolower((string)($hearingColumn['data_type'] ?? ''));
        if ($hearingType !== '' && $hearingType !== 'datetime') {
            $db->query("ALTER TABLE blotter_records MODIFY hearing_date DATETIME NULL");
        }
    } catch (Exception $e) {
    }
}

