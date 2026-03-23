<?php
require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    blotterJson(false, 'GET required', null, 405);
}

try {
    $residentId = requireResidentSessionForBlotter();
    ensureBlotterRecordsSchema();

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        blotterJson(false, 'ID required', null, 400);
    }

    $db = Database::getInstance();
    $row = $db->fetchOne(
        'SELECT *
         FROM blotter_records
         WHERE id = ? AND complainant_id = ?
         LIMIT 1',
        [$id, $residentId]
    );

    if (!$row) {
        blotterJson(false, 'Incident report not found.', null, 404);
    }

    blotterJson(true, 'Incident report found.', ['record' => $row]);
} catch (Exception $e) {
    error_log('Resident blotter get error: ' . $e->getMessage());
    blotterJson(false, 'Unable to load incident report details.', null, 500);
}
