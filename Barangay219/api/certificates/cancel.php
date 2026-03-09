<?php
require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    certJsonResponse(false, null, 'Method not allowed', 405);
}

try {
    $residentId = requireResidentSession();
    ensureCertificateRequestSchema();

    $requestId = (int)($_POST['id'] ?? 0);
    if ($requestId <= 0) {
        certJsonResponse(false, null, 'Invalid request id', 400);
    }

    $db = Database::getInstance();
    $existing = $db->fetchOne(
        "SELECT id, resident_id, status FROM certificate_requests WHERE id = ? LIMIT 1",
        [$requestId]
    );

    if (!$existing) {
        certJsonResponse(false, null, 'Request not found', 404);
    }

    if ((int)$existing['resident_id'] !== $residentId) {
        certJsonResponse(false, null, 'Forbidden', 403);
    }

    if (($existing['status'] ?? '') !== 'pending') {
        certJsonResponse(false, null, 'Only pending requests can be cancelled', 400);
    }

    $db->query(
        "UPDATE certificate_requests SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND resident_id = ?",
        [$requestId, $residentId]
    );

    certJsonResponse(true, [
        'id' => $requestId,
        'status' => 'cancelled'
    ], 'Request cancelled successfully');
} catch (Exception $e) {
    error_log('Certificate cancel error: ' . $e->getMessage());
    certJsonResponse(false, null, 'Unable to cancel request', 500);
}
