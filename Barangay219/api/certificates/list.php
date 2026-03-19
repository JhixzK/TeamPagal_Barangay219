<?php
require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    certJsonResponse(false, null, 'Method not allowed', 405);
}

try {
    $residentId = requireResidentSession();
    ensureCertificateRequestSchema();

    $db = Database::getInstance();
    $rows = $db->fetchAll(
        "SELECT id, certificate_type, purpose, reference_number, status, attachment,
                rejection_reason, remarks, created_at, updated_at
         FROM certificate_requests
         WHERE resident_id = ?
         ORDER BY created_at DESC, id DESC",
        [$residentId]
    );

    certJsonResponse(true, [
        'requests' => $rows
    ], 'Requests retrieved successfully');
} catch (Exception $e) {
    error_log('Certificate list error: ' . $e->getMessage());
    certJsonResponse(false, null, 'Unable to retrieve requests', 500);
}
