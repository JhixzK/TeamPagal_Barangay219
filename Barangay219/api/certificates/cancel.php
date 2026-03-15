<?php
require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    certJsonResponse(false, null, 'Method not allowed', 405);
}

try {
    $residentId = requireResidentSession();
    ensureCertificateRequestSchema();

    certJsonResponse(false, null, 'Request cancellation is no longer part of the certificate workflow', 400);
} catch (Exception $e) {
    error_log('Certificate cancel error: ' . $e->getMessage());
    certJsonResponse(false, null, 'Unable to cancel request', 500);
}
