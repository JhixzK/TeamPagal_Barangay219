<?php
/**
 * IMPROVED CERTIFICATE WORKFLOW FUNCTIONS
 * 
 * Drop-in replacements for api/certificates.php functions
 * Includes: validation, enhanced logging, improved notifications
 * 
 * Integration Steps:
 * 1. Add helper requires at top of api/certificates.php:
 *    require_once __DIR__ . '/helpers/certificate-validation.php';
 *    require_once __DIR__ . '/helpers/certificate-notifications.php';
 *    require_once __DIR__ . '/helpers/certificate-logging.php';
 * 
 * 2. Replace these functions in api/certificates.php with the code below
 * 3. Test using api/helpers/certificate-workflow-tests.php
 */

// ===================================================================
// IMPROVED: approveCertificateRequest()
// Location: api/certificates.php (lines ~790)
// Changes:
// - Added CertificateValidator before status change
// - Enhanced logging with CertificateWorkflowLogger
// - Improved notifications via CertificateNotifier
// ===================================================================

/**
 * REPLACE: Original approveCertificateRequest() (lines 790-830)
 * WITH: This improved version
 */
function approveCertificateRequest() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'POST required', null, 405);
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        sendResponse(false, 'ID required', null, 400);
    }

    try {
        $db = Database::getInstance();
        ensureCertificateWorkflowSchema();

        // IMPROVEMENT #1: Validate certificate data before approval
        require_once __DIR__ . '/helpers/certificate-validation.php';
        $validator = new CertificateValidator();
        $validationResult = $validator->validateBeforeApproval($id);
        
        if (!$validationResult->isEmpty()) {
            // Log validation failure for audit trail
            require_once __DIR__ . '/helpers/certificate-logging.php';
            $logger = new CertificateWorkflowLogger();
            $logger->logValidationFailure($id, (int)getCurrentUserId(), 
                                         $validationResult->getAllErrors());
            
            sendResponse(false, 'Validation failed: ' . $validationResult->getFirstError(), 
                        $validationResult->toArray(), 400);
            return;
        }

        // Fetch certificate for state check and logging
        $row = $db->fetchOne(
            "SELECT resident_id, status FROM certificate_requests WHERE id = ?", 
            [$id]
        );
        if (!$row) {
            sendResponse(false, 'Request not found', null, 404);
            return;
        }

        $currentStatus = normalizeStatus((string)$row['status']);
        if ($currentStatus !== 'pending') {
            sendResponse(false, 'Only pending requests can be approved', null, 400);
            return;
        }

        // IMPROVEMENT #2: Capture before-state for change tracking
        $beforeData = $db->fetchOne(
            "SELECT id, resident_id, cert_name, cert_age, cert_address, cert_purpose, 
                    admin_id, status FROM certificate_requests WHERE id = ?", 
            [$id]
        );

        // Perform status update to 'approved'
        $db->query(
            "UPDATE certificate_requests
             SET status = 'approved',
                 approved_at = COALESCE(approved_at, NOW()),
                 admin_id = ?
             WHERE id = ?",
            [(int)getCurrentUserId(), $id]
        );

        // IMPROVEMENT #3: Capture after-state and log changes
        require_once __DIR__ . '/helpers/certificate-logging.php';
        $afterData = $db->fetchOne(
            "SELECT id, resident_id, cert_name, cert_age, cert_address, cert_purpose,
                    admin_id, status FROM certificate_requests WHERE id = ?",
            [$id]
        );
        
        $logger = new CertificateWorkflowLogger();
        $logger->logApproval($id, (int)getCurrentUserId(), $beforeData, $afterData);

        // IMPROVEMENT #4: Send enhanced notification
        require_once __DIR__ . '/helpers/certificate-notifications.php';
        $notifier = new CertificateNotifier();
        $notifier->notifyApproved(
            $id, 
            (int)$row['resident_id'], 
            'Barangay Administrator'  // TODO: Get actual admin name from session/config
        );

        // Maintain backward compatibility with legacy logging
        logActivity('approve', 'certificates', $id, ['status' => 'approved']);

        sendResponse(true, 'Request approved', ['id' => $id, 'status' => 'approved']);
    } catch (Exception $e) {
        sendResponse(false, 'Error approving request: ' . $e->getMessage(), null, 500);
    }
}


// ===================================================================
// IMPROVED: approveAndPrepareForPickup()
// Location: api/certificates.php (lines ~860)
// Changes:
// - Added CertificateValidator::validateBeforeFinalization()
// - Enhanced logging with field-change tracking
// - Improved notifications with control number
// ===================================================================

/**
 * REPLACE: Original approveAndPrepareForPickup() (lines 860-975)
 * WITH: This improved version
 * 
 * Key improvements:
 * - Validates all data before finalization
 * - Logs field-by-field changes
 * - Enhanced notification with pickup location
 * - Better error messages
 */
function approveAndPrepareForPickup() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'POST required', null, 405);
    }

    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['cert_name'] ?? $_POST['name'] ?? ''));
    $age = (int)($_POST['cert_age'] ?? $_POST['age'] ?? 0);
    $address = trim((string)($_POST['cert_address'] ?? $_POST['address'] ?? ''));
    $purposeOption = trim((string)($_POST['purpose'] ?? $_POST['cert_purpose'] ?? ''));
    $purposeOther = trim((string)($_POST['purpose_other'] ?? ''));

    if ($id <= 0) {
        sendResponse(false, 'ID required', null, 400);
    }

    if (strtolower($purposeOption) === 'others' && $purposeOther === '') {
        sendResponse(false, 'Purpose details are required when Others is selected', null, 400);
    }

    $purpose = resolvePurpose($purposeOption, $purposeOther);

    try {
        $db = Database::getInstance();
        ensureCertificateWorkflowSchema();

        // IMPROVEMENT #1: Validate data completeness before finalization
        require_once __DIR__ . '/helpers/certificate-validation.php';
        $validator = new CertificateValidator();
        
        $overrideData = [
            'cert_name' => $name !== '' ? $name : null,
            'cert_age' => $age > 0 ? $age : null,
            'cert_address' => $address !== '' ? $address : null,
            'cert_purpose' => $purpose !== '' ? $purpose : null
        ];
        
        $validationResult = $validator->validateBeforeFinalization($id, $overrideData);
        if (!$validationResult->isEmpty()) {
            sendResponse(false, 'Finalization validation failed: ' . 
                        $validationResult->getFirstError(), 
                        $validationResult->toArray(), 400);
            return;
        }

        // Fetch full record with resident data
        $row = $db->fetchOne(
            "SELECT c.resident_id, c.certificate_type, c.status, c.cert_body, c.cert_name, 
                    c.cert_age, c.cert_address, c.cert_purpose, c.control_number, 
                    c.date_issued, c.civil_status, c.birth_date, r.civil_status,
                    r.first_name, r.middle_name, r.last_name, r.address AS resident_address
             FROM (
                SELECT c.resident_id, c.certificate_type, c.status, c.cert_body, c.cert_name,
                       c.cert_age, c.cert_address, c.cert_purpose, c.control_number,
                       c.date_issued, r.civil_status, r.birth_date, r.first_name,
                       r.middle_name, r.last_name, r.address AS resident_address
                FROM certificate_requests c
                LEFT JOIN residents r ON r.id = c.resident_id
                WHERE c.id = ?
            ) t",
            [$id]
        );

        if (!$row) {
            sendResponse(false, 'Request not found', null, 404);
            return;
        }

        $currentStatus = normalizeStatus((string)$row['status']);
        if ($currentStatus !== 'approved') {
            sendResponse(false, 'Only approved requests can be prepared for pickup', null, 400);
            return;
        }

        // IMPROVEMENT #2: Capture before-state for change tracking
        $beforeData = $row;

        // Resolve final certificate data
        $resolvedName = $name !== '' ? $name : trim(
            (string)($row['cert_name'] ?? '') !== ''
                ? (string)$row['cert_name']
                : ((string)($row['first_name'] ?? '') . ' '
                    . ((string)($row['middle_name'] ?? '') !== '' ? (string)$row['middle_name'] . ' ' : '')
                    . (string)($row['last_name'] ?? ''))
        );
        
        $resolvedAddress = $address !== '' ? $address : trim(
            (string)($row['cert_address'] ?? $row['resident_address'] ?? '')
        );
        
        $resolvedPurpose = $purpose !== '' ? $purpose : trim(
            (string)($row['cert_purpose'] ?? 'legal purpose')
        );
        
        $resolvedAge = $age > 0 ? $age : (int)($row['cert_age'] ?? 0);
        if ($resolvedAge <= 0) {
            $birthDateRaw = trim((string)($row['birth_date'] ?? ''));
            if ($birthDateRaw !== '') {
                $birthTs = strtotime($birthDateRaw);
                if ($birthTs !== false) {
                    $birthDate = new DateTime(date('Y-m-d', $birthTs));
                    $todayDate = new DateTime('today');
                    $resolvedAge = (int)$birthDate->diff($todayDate)->y;
                }
            }
        }

        $civilStatus = trim((string)($row['civil_status'] ?? getResidentCivilStatus((int)$row['resident_id'])));
        $issueDate = trim((string)($_POST['date_issued'] ?? $row['date_issued'] ?? ''));
        if ($issueDate === '') {
            $issueDate = date('Y-m-d');
        }
        $controlNumber = trim((string)($row['control_number'] ?? ''));
        if ($controlNumber === '') {
            $controlNumber = generateControlNumber();
        }

        $generatedBody = trim((string)($_POST['cert_body'] ?? ''));
        if ($generatedBody === '') {
            $generatedBody = buildCertificateBody(
                (string)$row['certificate_type'],
                $resolvedName,
                $resolvedAge > 0 ? $resolvedAge : null,
                $civilStatus,
                $resolvedAddress,
                $resolvedPurpose,
                date('m/d/Y', strtotime($issueDate)),
                $controlNumber
            );
        }

        // Update certificate to ready_for_pickup status
        $db->query(
            "UPDATE certificate_requests
             SET status = 'ready_for_pickup',
                 cert_name = ?,
                 cert_age = ?,
                 cert_address = ?,
                 cert_purpose = ?,
                 purpose = ?,
                 cert_body = ?,
                 control_number = ?,
                 date_issued = ?,
                 issued_date = ?,
                 approved_at = COALESCE(approved_at, NOW()),
                 ready_for_pickup_at = NOW(),
                 admin_id = ?
             WHERE id = ?",
            [
                $resolvedName,
                $resolvedAge > 0 ? $resolvedAge : null,
                $resolvedAddress,
                $resolvedPurpose,
                $resolvedPurpose,
                $generatedBody,
                $controlNumber,
                $issueDate,
                $issueDate,
                (int)getCurrentUserId(),
                $id
            ]
        );

        // IMPROVEMENT #3: Capture after-state and log with field tracking
        $afterData = $db->fetchOne(
            "SELECT id, resident_id, status, cert_name, cert_age, cert_address, 
                    cert_purpose, control_number, date_issued FROM certificate_requests WHERE id = ?",
            [$id]
        );

        require_once __DIR__ . '/helpers/certificate-logging.php';
        $logger = new CertificateWorkflowLogger();
        $finalData = [
            'control_number' => $controlNumber,
            'date_issued' => $issueDate,
            'cert_name' => $resolvedName,
            'cert_age' => $resolvedAge,
            'cert_address' => $resolvedAddress,
            'cert_purpose' => $resolvedPurpose,
            'reason' => 'Finalized and prepared for pickup'
        ];
        $logger->logFinalization($id, (int)getCurrentUserId(), $finalData);

        // IMPROVEMENT #4: Send enhanced notification with control number and location
        require_once __DIR__ . '/helpers/certificate-notifications.php';
        $notifier = new CertificateNotifier();
        $notifier->notifyReadyForPickup(
            $id, 
            (int)$row['resident_id'], 
            $controlNumber,
            'Barangay Hall, 2nd Floor'  // TODO: Configurable pickup location
        );

        // Backward compatibility
        logActivity('prepare', 'certificates', $id, 
                  ['status' => 'ready_for_pickup', 'control_number' => $controlNumber]);

        sendResponse(true, 'Request marked Ready for Pickup', [
            'id' => $id,
            'status' => 'ready_for_pickup',
            'control_number' => $controlNumber,
            'cert_name' => $resolvedName,
            'cert_age' => $resolvedAge,
            'cert_address' => $resolvedAddress,
            'date_issued' => $issueDate
        ]);
    } catch (Exception $e) {
        sendResponse(false, 'Error preparing for pickup: ' . $e->getMessage(), null, 500);
    }
}


// ===================================================================
// IMPROVED: rejectCertificateRequest()
// Location: api/certificates.php (lines ~985)
// Changes:
// - Added validation for rejection reason
// - Enhanced logging
// - Improved notifications
// ===================================================================

/**
 * REPLACE: Original rejectCertificateRequest() (lines 985-1040)
 * WITH: This improved version
 */
function rejectCertificateRequest() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'POST required', null, 405);
    }

    $id = (int)($_POST['id'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? $_POST['rejection_reason'] ?? ''));

    if ($id <= 0) {
        sendResponse(false, 'ID required', null, 400);
    }

    try {
        $db = Database::getInstance();
        ensureCertificateWorkflowSchema();

        // IMPROVEMENT #1: Validate rejection reason
        require_once __DIR__ . '/helpers/certificate-validation.php';
        $validator = new CertificateValidator();
        $validationResult = $validator->validateRejection($id, $reason);
        
        if (!$validationResult->isEmpty()) {
            sendResponse(false, 'Validation failed: ' . $validationResult->getFirstError(), null, 400);
            return;
        }

        $row = $db->fetchOne(
            "SELECT resident_id, status FROM certificate_requests WHERE id = ?", 
            [$id]
        );
        if (!$row) {
            sendResponse(false, 'Request not found', null, 404);
            return;
        }

        $currentStatus = normalizeStatus((string)$row['status']);
        if ($currentStatus !== 'pending') {
            sendResponse(false, 'Only pending requests can be rejected', null, 400);
            return;
        }

        // IMPROVEMENT #2: Capture before-state for logging
        $beforeData = $db->fetchOne(
            "SELECT id, status, rejection_reason FROM certificate_requests WHERE id = ?", 
            [$id]
        );

        // Perform rejection
        $db->query(
            "UPDATE certificate_requests
             SET status = 'rejected',
                 rejection_reason = ?,
                 remarks = ?,
                 rejected_at = NOW(),
                 admin_id = ?
             WHERE id = ?",
            [$reason, $reason, (int)getCurrentUserId(), $id]
        );

        // IMPROVEMENT #3: Enhanced logging
        require_once __DIR__ . '/helpers/certificate-logging.php';
        $logger = new CertificateWorkflowLogger();
        $logger->logRejection($id, (int)getCurrentUserId(), $reason, [
            'before_status' => $beforeData['status'] ?? null,
            'rejected_by_role' => getCurrentUserRole()
        ]);

        // IMPROVEMENT #4: Enhanced notification with rejection reason
        require_once __DIR__ . '/helpers/certificate-notifications.php';
        $notifier = new CertificateNotifier();
        $notifier->notifyRejected(
            $id, 
            (int)$row['resident_id'], 
            $reason,
            'Barangay Administrator'  // TODO: Get actual admin name
        );

        // Backward compatibility
        logActivity('reject', 'certificates', $id, ['reason' => $reason]);

        sendResponse(true, 'Request rejected', ['id' => $id, 'status' => 'rejected']);
    } catch (Exception $e) {
        sendResponse(false, 'Error rejecting request: ' . $e->getMessage(), null, 500);
    }
}


// ===================================================================
// IMPROVED: markCertificateReleased()
// Location: api/certificates.php (lines ~1041)
// Changes:
// - State validation
// - Enhanced logging
// - Improved notifications
// ===================================================================

/**
 * REPLACE: Original markCertificateReleased() (lines 1041-1120)
 * WITH: This improved version
 */
function markCertificateReleased() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'POST required', null, 405);
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        sendResponse(false, 'ID required', null, 400);
    }

    try {
        $db = Database::getInstance();
        ensureCertificateWorkflowSchema();

        $row = $db->fetchOne(
            "SELECT resident_id, certificate_type, status, cert_name, cert_age, 
                    cert_address, cert_purpose, first_name, middle_name, last_name,
                    resident_address, birth_date, civil_status, control_number
             FROM (
                SELECT c.resident_id, c.certificate_type, c.status, c.cert_name, c.cert_age,
                       c.cert_address, c.cert_purpose, c.control_number,
                       r.first_name, r.middle_name, r.last_name,
                       r.address AS resident_address, r.birth_date, r.civil_status
                FROM certificate_requests c
                LEFT JOIN residents r ON r.id = c.resident_id
                WHERE c.id = ?
             ) t",
            [$id]
        );

        if (!$row) {
            sendResponse(false, 'Request not found', null, 404);
            return;
        }

        $currentStatus = normalizeStatus((string)$row['status']);
        if ($currentStatus !== 'ready_for_pickup') {
            sendResponse(false, 'Only Ready for Pickup requests can be marked Released', null, 400);
            return;
        }

        // IMPROVEMENT #1: Capture before-state
        $beforeData = $row;

        $ctrlNum = trim((string)($row['control_number'] ?? ''));
        if ($ctrlNum === '') {
            $ctrlNum = generateControlNumber();
        }
        
        $issueDate = trim((string)($row['date_issued'] ?? ''));
        if ($issueDate === '') {
            $issueDate = date('Y-m-d');
        }

        // Release certificate
        $db->query(
            "UPDATE certificate_requests
             SET status = 'released',
                 control_number = ?,
                 date_issued = ?,
                 issued_date = ?,
                 released_at = NOW(),
                 admin_id = ?
             WHERE id = ?",
            [$ctrlNum, $issueDate, $issueDate, (int)getCurrentUserId(), $id]
        );

        // IMPROVEMENT #2: Enhanced logging
        require_once __DIR__ . '/helpers/certificate-logging.php';
        $logger = new CertificateWorkflowLogger();
        $logger->logRelease($id, (int)getCurrentUserId(), $ctrlNum);

        // Log PDF generation for audit trail
        $logger->logPDFGeneration($id, (int)getCurrentUserId(), 'release');

        // IMPROVEMENT #3: Final notification
        require_once __DIR__ . '/helpers/certificate-notifications.php';
        $notifier = new CertificateNotifier();
        $notifier->notifyReleased(
            $id, 
            (int)$row['resident_id'], 
            $ctrlNum,
            'Barangay Administrator'  // TODO: Get actual admin name
        );

        // Backward compatibility
        logActivity('release', 'certificates', $id, ['control_number' => $ctrlNum]);

        sendResponse(true, 'Certificate marked as Released', [
            'id' => $id,
            'status' => 'released',
            'control_number' => $ctrlNum
        ]);
    } catch (Exception $e) {
        sendResponse(false, 'Error releasing certificate: ' . $e->getMessage(), null, 500);
    }
}

?>
