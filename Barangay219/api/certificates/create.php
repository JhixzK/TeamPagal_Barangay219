<?php
require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    certJsonResponse(false, null, 'Method not allowed', 405);
}

try {
    $residentId = requireResidentSession();
    ensureCertificateRequestSchema();

    $certificateType = normalizeCertificateType($_POST['certificate_type'] ?? '');
    $purpose = sanitizeInput((string)($_POST['purpose'] ?? ''));

    $allowedTypes = [
        'Barangay Clearance',
        'Certificate of Residency',
        'Certificate of Indigency',
        'Certificate of Good Moral Character',
        'Business Clearance',
        'Barangay ID Request'
    ];

    if ($certificateType === '' || !in_array($certificateType, $allowedTypes, true)) {
        certJsonResponse(false, null, 'Invalid certificate type', 400);
    }

    if ($purpose === '') {
        certJsonResponse(false, null, 'Purpose is required', 400);
    }

    $referenceNumber = generateCertificateReferenceNumber();
    $attachmentPath = saveAttachmentIfPresent('documents');

    $db = Database::getInstance();
    $columns = $db->fetchAll("SHOW COLUMNS FROM certificate_requests");
    $columnNames = array_column($columns, 'Field');

    $insertCols = ['resident_id', 'certificate_type', 'purpose', 'reference_number', 'status', 'attachment'];
    $insertVals = [$residentId, $certificateType, $purpose, $referenceNumber, 'pending', $attachmentPath];

    if (in_array('requested_by', $columnNames, true)) {
        $insertCols[] = 'requested_by';
        $insertVals[] = (int)($_SESSION['user_id'] ?? 0);
    }

    $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
    $sql = "INSERT INTO certificate_requests (" . implode(',', $insertCols) . ") VALUES (" . $placeholders . ")";

    $db->query($sql, $insertVals);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'reference_number' => $referenceNumber,
        'data' => [
            'reference_number' => $referenceNumber
        ]
    ]);
    exit;
} catch (Exception $e) {
    error_log('Certificate create error: ' . $e->getMessage());
    certJsonResponse(false, null, 'Unable to submit certificate request', 500);
}
