<?php
require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    blotterJson(false, 'POST required', null, 405);
}

try {
    $complainantId = requireResidentSessionForBlotter();
    ensureBlotterRecordsSchema();

    $incidentType = mapIncidentType((string)($_POST['incident_type'] ?? 'other'));
    $incidentLocation = sanitizeInput((string)($_POST['incident_location'] ?? ''));
    $incidentDatetimeInput = trim((string)($_POST['incident_datetime'] ?? ''));
    $narrative = sanitizeInput((string)($_POST['narrative'] ?? ''));

    $isNonResident = ((string)($_POST['respondent_non_resident'] ?? '0')) === '1';
    $respondentId = (int)($_POST['respondent_id'] ?? 0);
    $respondentName = sanitizeInput((string)($_POST['respondent_name'] ?? ''));

    $witnessesRaw = trim((string)($_POST['witnesses'] ?? ''));
    $isConfidential = ((string)($_POST['is_confidential'] ?? '0')) === '1' ? 1 : 0;
    $actionRequested = mapActionRequested((string)($_POST['action_requested'] ?? 'Mediation'));

    if ($incidentLocation === '' || $narrative === '' || $incidentDatetimeInput === '') {
        blotterJson(false, 'Incident location, date/time, and narrative are required.', null, 400);
    }

    $incidentTs = strtotime($incidentDatetimeInput);
    if ($incidentTs === false) {
        blotterJson(false, 'Invalid incident date/time.', null, 400);
    }
    $incidentDatetime = date('Y-m-d H:i:s', $incidentTs);

    if ($isNonResident) {
        if ($respondentName === '') {
            blotterJson(false, 'Respondent name is required for non-resident respondents.', null, 400);
        }
        $respondentId = 0;
    } else {
        if ($respondentId <= 0) {
            blotterJson(false, 'Please select a respondent resident.', null, 400);
        }
        $db = Database::getInstance();
        $resident = $db->fetchOne('SELECT first_name, middle_name, last_name FROM residents WHERE id = ? LIMIT 1', [$respondentId]);
        if (!$resident) {
            blotterJson(false, 'Selected respondent was not found.', null, 404);
        }
        $respondentName = trim(
            (string)($resident['first_name'] ?? '') . ' '
            . ((string)($resident['middle_name'] ?? '') !== '' ? (string)$resident['middle_name'] . ' ' : '')
            . (string)($resident['last_name'] ?? '')
        );
    }

    $witnessesPayload = null;
    if ($witnessesRaw !== '') {
        $lines = preg_split('/\r\n|\r|\n/', $witnessesRaw);
        $clean = [];
        foreach ($lines as $line) {
            $entry = trim($line);
            if ($entry !== '') {
                $clean[] = $entry;
            }
        }
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
            incident_location,
            incident_datetime,
            narrative,
            status,
            respondent_name,
            respondent_id,
            witnesses,
            is_confidential,
            action_requested,
            evidence_path
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $referenceNo,
            $complainantId,
            $incidentType,
            $incidentLocation,
            $incidentDatetime,
            $narrative,
            'pending',
            $respondentName !== '' ? $respondentName : null,
            $respondentId > 0 ? $respondentId : null,
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
