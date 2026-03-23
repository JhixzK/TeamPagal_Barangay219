<?php
require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    blotterJson(false, 'POST required', null, 405);
}

function blotterLimitError(): void {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Input exceeds maximum character limit.'
    ]);
    exit();
}

function normalizeRespondentsPayload($raw, string $fallback): array {
    if ($raw === null || $raw === '') {
        $name = trim($fallback);
        return $name !== '' ? [[
            'name' => sanitizeInput($name),
            'address' => '',
            'contact' => '',
            'residency' => 'non_resident',
            'resident_id' => null,
        ]] : [];
    }

    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) {
        $name = trim((string)$raw);
        return $name !== '' ? [[
            'name' => sanitizeInput($name),
            'address' => '',
            'contact' => '',
            'residency' => 'non_resident',
            'resident_id' => null,
        ]] : [];
    }

    $respondents = [];
    foreach ($decoded as $entry) {
        if (is_array($entry)) {
            $name = sanitizeInput((string)($entry['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $respondents[] = [
                'name' => $name,
                'address' => sanitizeInput((string)($entry['address'] ?? '')),
                'contact' => sanitizeInput((string)($entry['contact'] ?? '')),
                'residency' => (($entry['residency'] ?? '') === 'resident') ? 'resident' : 'non_resident',
                'resident_id' => isset($entry['resident_id']) ? (int)$entry['resident_id'] : null,
            ];
            continue;
        }

        $name = sanitizeInput((string)$entry);
        if ($name !== '') {
            $respondents[] = [
                'name' => $name,
                'address' => '',
                'contact' => '',
                'residency' => 'non_resident',
                'resident_id' => null,
            ];
        }
    }

    return $respondents;
}

try {
    $complainantId = requireResidentSessionForBlotter();
    ensureBlotterRecordsSchema();

    $incidentType = mapIncidentType((string)($_POST['incident_type'] ?? 'other'));
    $incidentTypeDetail = sanitizeInput((string)($_POST['incident_type_detail'] ?? ''));
    $incidentLocation = sanitizeInput((string)($_POST['incident_location'] ?? ''));
    $incidentDatetimeInput = trim((string)($_POST['incident_datetime'] ?? ''));
    $narrative = sanitizeInput((string)($_POST['narrative'] ?? ''));

    $respondentNameRaw = sanitizeInput((string)($_POST['respondent_name_raw'] ?? ''));
    $respondentsRaw = $_POST['respondents'] ?? null;

    $witnessesRaw = trim((string)($_POST['witnesses'] ?? ''));
    $isConfidential = ((string)($_POST['is_confidential'] ?? '0')) === '1' ? 1 : 0;
    $actionRequested = mapActionRequested((string)($_POST['action_requested'] ?? 'Mediation'));

    if (strlen($incidentLocation) > 255) {
        blotterLimitError();
    }
    if (strlen($incidentTypeDetail) > 100) {
        blotterLimitError();
    }
    if (strlen($respondentNameRaw) > 150) {
        blotterLimitError();
    }
    if (strlen($witnessesRaw) > 1000) {
        blotterLimitError();
    }
    if (strlen($narrative) > 3000) {
        blotterLimitError();
    }

    if ($incidentLocation === '' || $narrative === '' || $incidentDatetimeInput === '') {
        blotterJson(false, 'Incident location, date/time, and narrative are required.', null, 400);
    }

    $incidentTs = strtotime($incidentDatetimeInput);
    if ($incidentTs === false) {
        blotterJson(false, 'Invalid incident date/time.', null, 400);
    }
    $incidentDatetime = date('Y-m-d H:i:s', $incidentTs);

    $respondents = normalizeRespondentsPayload($respondentsRaw, $respondentNameRaw);
    if (empty($respondents)) {
        blotterJson(false, 'Respondent name is required.', null, 400);
    }
    foreach ($respondents as $respondent) {
        if (strlen((string)($respondent['name'] ?? '')) > 150) {
            blotterLimitError();
        }
    }

    $primaryRespondentName = (string)($respondents[0]['name'] ?? '');

    if ($incidentType === 'other' && $incidentTypeDetail === '') {
        blotterJson(false, 'Please specify the incident type detail for Other.', null, 400);
    }

    $witnessesPayload = null;
    if ($witnessesRaw !== '') {
        $normalizedWitnesses = str_replace(["\r\n", "\r"], "\n", $witnessesRaw);
        $lines = explode("\n", $normalizedWitnesses);
        $clean = array_map('trim', $lines);
        $clean = array_values(array_filter($clean));
        if (!empty($clean)) {
            $witnessesPayload = json_encode($clean, JSON_UNESCAPED_UNICODE);
        }
    }

    $evidencePath = saveBlotterEvidence($_FILES['evidence'] ?? []);
    $referenceNo = generateBlotterReferenceNumber();

    $db = Database::getInstance();
    $db->query(
        'INSERT INTO blotter_records (
            reference_no,
            complainant_id,
            incident_type,
            incident_type_detail,
            incident_location,
            incident_datetime,
            narrative,
            status,
            respondent_name_raw,
            respondent_name,
            respondent_id,
            witnesses,
            is_confidential,
            action_requested,
            evidence_path
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $referenceNo,
            $complainantId,
            $incidentType,
            $incidentTypeDetail !== '' ? $incidentTypeDetail : null,
            $incidentLocation,
            $incidentDatetime,
            $narrative,
            'pending',
            $primaryRespondentName,
            json_encode($respondents, JSON_UNESCAPED_UNICODE),
            null,
            $witnessesPayload,
            $isConfidential,
            $actionRequested,
            $evidencePath,
        ]
    );

    $newId = (int)$db->lastInsertId();
    logActivity('create', 'blotter_records', $newId, ['reference_no' => $referenceNo, 'status' => 'pending']);

    blotterJson(true, 'Incident report submitted successfully.', [
        'id' => $newId,
        'reference_no' => $referenceNo,
        'status' => 'pending',
    ]);
} catch (Exception $e) {
    error_log('Resident blotter create error: ' . $e->getMessage());
    blotterJson(false, 'Unable to submit incident report right now.', null, 500);
}
