<?php
require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    blotterJson(false, 'GET required', null, 405);
}

try {
    $residentId = requireResidentSessionForBlotter();
    ensureBlotterRecordsSchema();

    $db = Database::getInstance();
    $rows = $db->fetchAll(
        'SELECT id, reference_no, incident_type, incident_type_detail, incident_location, incident_datetime, status, is_confidential, action_requested, created_at, updated_at
         FROM blotter_records
         WHERE complainant_id = ?
         ORDER BY created_at DESC, id DESC',
        [$residentId]
    );

    blotterJson(true, 'Incident reports loaded.', ['records' => $rows]);
} catch (Exception $e) {
    error_log('Resident blotter list error: ' . $e->getMessage());
    blotterJson(false, 'Unable to load incident reports.', null, 500);
}
