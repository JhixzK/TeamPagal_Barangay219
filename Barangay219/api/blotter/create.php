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

    $respondentNameRaw = sanitizeInput((string)($_POST['respondent_name_raw'] ?? ''));

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

    if ($respondentNameRaw === '') {
        blotterJson(false, 'Respondent name is required.', null, 400);
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
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $referenceNo,
            $complainantId,
            $incidentType,
            $incidentLocation,
            $incidentDatetime,
            $narrative,
            'pending',
            $respondentNameRaw,
            $respondentNameRaw,
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
