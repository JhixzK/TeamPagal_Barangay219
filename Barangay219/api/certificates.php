<?php
header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
if (!canAccessModule('certificates') && !canAccessModule('applications') && !hasRole(ROLE_RESIDENT)) {
    sendResponse(false, 'Access denied', null, 403);
}

const CERT_ALLOWED_TYPES = [
    'barangay_certificate',
    'barangay_indigency',
    CERT_BARANGAY_CLEARANCE,
    CERT_RESIDENCY,
    CERT_TRANSFER_REQUEST,
    // Legacy compatibility aliases still accepted in API inputs.
    CERT_INDIGENCY,
    CERT_GOOD_MORAL
];

const CERT_ALLOWED_STATUSES = [
    'pending',
    'approved',
    'ready_for_pickup',
    'rejected',
    'released'
];

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
    case 'list_pending':
        listCertificates($action === 'list_pending');
        break;

    case 'get':
        getCertificate();
        break;

    case 'resident_submit':
        submitResidentCertificateRequest();
        break;

    case 'resident_options':
        if (!canPerformModulePermission('certificates', 'can_edit') && !canPerformModulePermission('applications', 'can_edit')
            && !canPerformModulePermission('certificates', 'can_create') && !canPerformModulePermission('applications', 'can_create')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        getCertificateResidentOptions();
        break;

    case 'create':
        if (!canPerformModulePermission('certificates', 'can_create') && !canPerformModulePermission('applications', 'can_create')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        createCertificateByAdmin();
        break;

    case 'direct_issue':
        if (!canPerformModulePermission('certificates', 'can_edit') && !canPerformModulePermission('applications', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        directIssueCertificateByAdmin();
        break;

    case 'update':
        if (!canPerformModulePermission('certificates', 'can_edit') && !canPerformModulePermission('applications', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        updateCertificateWorkflow();
        break;

    case 'approve_ready':
        if (!canPerformModulePermission('certificates', 'can_edit') && !canPerformModulePermission('applications', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        approveAndPrepareForPickup();
        break;

    case 'reject':
        if (!canPerformModulePermission('certificates', 'can_edit') && !canPerformModulePermission('applications', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        rejectCertificateRequest();
        break;

    case 'release':
        if (!canPerformModulePermission('certificates', 'can_edit') && !canPerformModulePermission('applications', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        markCertificateReleased();
        break;

    case 'generate_control':
        if (!canPerformModulePermission('certificates', 'can_edit') && !canPerformModulePermission('applications', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        ensureCertificateWorkflowSchema();
        sendResponse(true, 'Control number generated', ['control_number' => generateControlNumber()]);
        break;

    default:
        sendResponse(false, 'Invalid action', null, 400);
}

function ensureCertificateWorkflowSchema() {
    $db = Database::getInstance();

    $db->query(
        "CREATE TABLE IF NOT EXISTS certificate_requests (
            id INT(11) NOT NULL AUTO_INCREMENT,
            resident_id INT(11) NOT NULL,
            requested_by INT(11) DEFAULT NULL,
            certificate_type VARCHAR(120) NOT NULL,
            purpose TEXT DEFAULT NULL,
            status ENUM('pending','approved','ready_for_pickup','rejected','released') NOT NULL DEFAULT 'pending',
            cert_name VARCHAR(255) DEFAULT NULL,
            cert_age INT(11) DEFAULT NULL,
            cert_address TEXT DEFAULT NULL,
            cert_purpose TEXT DEFAULT NULL,
            cert_body TEXT DEFAULT NULL,
            purpose_option VARCHAR(120) DEFAULT NULL,
            purpose_other TEXT DEFAULT NULL,
            rejection_reason TEXT DEFAULT NULL,
            date_issued DATE DEFAULT NULL,
            issued_date DATE DEFAULT NULL,
            ready_for_pickup_at DATETIME DEFAULT NULL,
            released_at DATETIME DEFAULT NULL,
            approved_at DATETIME DEFAULT NULL,
            rejected_at DATETIME DEFAULT NULL,
            cancelled_at DATETIME DEFAULT NULL,
            admin_id INT(11) DEFAULT NULL,
            control_number VARCHAR(50) DEFAULT NULL,
            reference_number VARCHAR(50) DEFAULT NULL,
            remarks TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_resident (resident_id),
            KEY idx_status (status),
            KEY idx_type (certificate_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $columns = $db->fetchAll("SHOW COLUMNS FROM certificate_requests");
    $map = [];
    foreach ($columns as $column) {
        $map[$column['Field']] = true;
    }

    $addColumn = function($name, $definition) use ($db, $map) {
        if (!isset($map[$name])) {
            $db->query("ALTER TABLE certificate_requests ADD COLUMN {$name} {$definition}");
        }
    };

    $addColumn('application_ref', "VARCHAR(50) NULL");
    $addColumn('attachment', "VARCHAR(255) NULL");
    $addColumn('cert_name', "VARCHAR(255) NULL");
    $addColumn('cert_age', "INT(11) NULL");
    $addColumn('cert_address', "TEXT NULL");
    $addColumn('cert_purpose', "TEXT NULL");
    $addColumn('cert_body', "TEXT NULL");
    $addColumn('purpose_option', "VARCHAR(120) NULL");
    $addColumn('purpose_other', "TEXT NULL");
    $addColumn('purpose_details', "TEXT NULL");
    $addColumn('rejection_reason', "TEXT NULL");
    $addColumn('date_issued', "DATE NULL");
    $addColumn('issued_date', "DATE NULL");
    $addColumn('ready_for_pickup_at', "DATETIME NULL");
    $addColumn('released_at', "DATETIME NULL");
    $addColumn('approved_at', "DATETIME NULL");
    $addColumn('rejected_at', "DATETIME NULL");
    $addColumn('cancelled_at', "DATETIME NULL");
    $addColumn('admin_id', "INT(11) NULL");
    $addColumn('control_number', "VARCHAR(50) NULL");
    $addColumn('reference_number', "VARCHAR(50) NULL");
    $addColumn('remarks', "TEXT NULL");

    $db->query("UPDATE certificate_requests
                SET status = 'approved',
                    approved_at = COALESCE(approved_at, updated_at, created_at)
                WHERE status = 'under_review'");
    $db->query("UPDATE certificate_requests SET status = 'released' WHERE status = 'issued'");
    $db->query("UPDATE certificate_requests
                SET status = 'rejected',
                    rejection_reason = COALESCE(NULLIF(rejection_reason, ''), 'Converted from legacy cancelled status'),
                    remarks = COALESCE(NULLIF(remarks, ''), 'Converted from legacy cancelled status'),
                    rejected_at = COALESCE(rejected_at, cancelled_at, updated_at, created_at)
                WHERE status = 'cancelled'");

    $db->query("ALTER TABLE certificate_requests MODIFY COLUMN status ENUM('pending','approved','ready_for_pickup','rejected','released') NOT NULL DEFAULT 'pending'");

    $missingRefs = $db->fetchAll("SELECT id, created_at FROM certificate_requests WHERE reference_number IS NULL OR reference_number = '' ORDER BY id ASC");
    foreach ($missingRefs as $row) {
        $id = (int)$row['id'];
        $year = date('Y', strtotime($row['created_at'] ?: 'now'));
        $ref = 'REQ-BRGY219-' . $year . '-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
        $db->query("UPDATE certificate_requests SET reference_number = ? WHERE id = ?", [$ref, $id]);
    }

    $missingAppRefs = $db->fetchAll("SELECT id, created_at FROM certificate_requests WHERE application_ref IS NULL OR application_ref = '' ORDER BY id ASC");
    foreach ($missingAppRefs as $row) {
        $id = (int)$row['id'];
        $year = date('Y', strtotime($row['created_at'] ?: 'now'));
        $appRef = 'APP-' . $year . '-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
        $db->query("UPDATE certificate_requests SET application_ref = ? WHERE id = ?", [$appRef, $id]);
    }

    // Keep both legacy/new columns in sync for purpose details.
    if (isset($map['purpose_other']) && isset($map['purpose_details'])) {
        $db->query("UPDATE certificate_requests
                    SET purpose_details = COALESCE(NULLIF(purpose_details, ''), purpose_other)
                    WHERE (purpose_details IS NULL OR purpose_details = '')
                      AND purpose_other IS NOT NULL
                      AND purpose_other <> ''");
    }
}

function ensureNotificationsSchema() {
    $db = Database::getInstance();
    $db->query(
        "CREATE TABLE IF NOT EXISTS notifications (
            id INT(11) NOT NULL AUTO_INCREMENT,
            resident_id INT(11) DEFAULT NULL,
            user_id INT(11) DEFAULT NULL,
            title VARCHAR(255) DEFAULT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50) DEFAULT 'info',
            is_read TINYINT(1) DEFAULT 0,
            status VARCHAR(30) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_resident_id (resident_id),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function sendResidentNotification($residentId, $title, $message, $type = 'info') {
    if ($residentId <= 0) {
        return;
    }

    try {
        ensureNotificationsSchema();
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO notifications (resident_id, title, message, type, is_read) VALUES (?, ?, ?, ?, 0)",
            [$residentId, $title, $message, $type]
        );
    } catch (Exception $e) {
        error_log('Notification error: ' . $e->getMessage());
    }
}

function getCurrentResidentId() {
    $userId = (int)(getCurrentUserId() ?? 0);
    if ($userId <= 0) {
        return 0;
    }

    $db = Database::getInstance();
    $row = $db->fetchOne("SELECT resident_id FROM users WHERE id = ? LIMIT 1", [$userId]);
    return (int)($row['resident_id'] ?? 0);
}

function normalizeStatus(string $status): string {
    $status = strtolower(trim($status));
    if ($status === 'issued') {
        return 'released';
    }
    if ($status === 'under_review') {
        return 'approved';
    }
    if ($status === 'cancelled' || $status === 'canceled') {
        return 'rejected';
    }
    return $status;
}

function normalizeCertificateType(string $type): string {
    $normalized = strtolower(trim(str_replace(['-', '__'], [' ', '_'], $type)));
    $normalized = preg_replace('/\s+/', '_', $normalized);

    $aliases = [
        'barangay_certificate' => 'barangay_certificate',
        'certificate_of_residency' => 'certificate_residency',
        'certificate_residency' => 'certificate_residency',
        'barangay_indigency' => 'barangay_indigency',
        'certificate_indigency' => 'barangay_indigency',
        'certificate_of_indigency' => 'barangay_indigency',
        'barangay_clearance' => 'barangay_clearance',
        'transfer_request' => 'transfer_request'
    ];

    return $aliases[$normalized] ?? $normalized;
}

function resolvePurpose(string $purposeOption, string $purposeOther): string {
    if (strtolower(trim($purposeOption)) === 'others') {
        return trim($purposeOther) !== '' ? trim($purposeOther) : 'Others';
    }
    return trim($purposeOption);
}

function generateApplicationRefForId(int $id): string {
    return 'APP-' . date('Y') . '-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
}

function generateReferenceNumberForId(int $id): string {
    return 'REQ-BRGY219-' . date('Y') . '-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
}

function generateControlNumber(): string {
    $db = Database::getInstance();
    $year = date('Y');
    $prefix = 'BRGY219-' . $year . '-';
    $last = $db->fetchOne("SELECT control_number FROM certificate_requests WHERE control_number LIKE ? ORDER BY id DESC LIMIT 1", [$prefix . '%']);
    $seq = 1;
    if ($last && preg_match('/-(\d+)$/', (string)$last['control_number'], $m)) {
        $seq = (int)$m[1] + 1;
    }
    return $prefix . str_pad((string)$seq, 5, '0', STR_PAD_LEFT);
}

function buildPurposeChecklistText(string $selectedPurpose): string {
    $left = [
        'Application for Employment',
        'Hospital Purpose',
        'Medical Purpose',
        'Bank Transaction',
        'Organized Vending Permit',
        'For Travel Abroad',
    ];

    $right = [
        'School Admission/Requirement',
        'Processing of Calamity',
        'For Livelihood Loan',
        'Indigent Family',
        'DSWD Requirement',
        'Transfer of Residence',
    ];

    $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $selectedPurpose)));
    $allKnown = array_map('strtolower', array_merge($left, $right));
    $isOther = $normalized === '' || !in_array($normalized, $allKnown, true);

    $lineRows = [];
    for ($i = 0; $i < 6; $i++) {
        $leftMark = strtolower($left[$i]) === $normalized ? '(?)' : '( )';
        $rightMark = strtolower($right[$i]) === $normalized ? '(?)' : '( )';
        $lineRows[] = $leftMark . ' ' . $left[$i] . "\t" . $rightMark . ' ' . $right[$i];
    }

    $otherMark = $isOther ? '(?)' : '( )';
    $otherValue = $isOther && trim($selectedPurpose) !== '' ? trim($selectedPurpose) : '[PURPOSE]';
    $lineRows[] = $otherMark . ' Others: ' . $otherValue;

    return implode("\n", $lineRows);
}

function getCertificateBodyTemplate(string $certificateType): string {
    $normalized = strtolower(trim(str_replace('_', ' ', $certificateType)));

    if ($normalized === 'barangay clearance' || $normalized === 'barangay certificate') {
        return implode("\n", [
            'TO WHOM IT MAY CONCERN:',
            '',
            'This is to certify that [NAME], [AGE] years old, [CIVIL_STATUS], is a bonafide resident of this Barangay 219, Zone 20, District II, Tondo, Manila with his/her postal address at [ADDRESS].',
            '',
            'This certification was issued upon the request of the above mentioned name for whatever legal purpose that may serve him/her best.',
            '',
            'AS PER REQUIREMENT IN SUPPORTING HIS/HER DOCUMENT',
            '[PURPOSE_CHECKLIST]',
            '',
            'IN WITNESS WHEREOF, I have hereunto set my hand and affixed the official seal of this office. Done in the Barangay Hall Barangay 219, Zone 20, District II, City of Manila this [DATE_ISSUED].'
        ]);
    }

    if ($normalized === 'certificate indigency' || $normalized === 'certificate of indigency' || $normalized === 'barangay indigency') {
        return implode("\n", [
            'TO WHOM IT MAY CONCERN:',
            '',
            'This is to certify that [NAME], [AGE] years of age, [CIVIL_STATUS], is a bonafide resident of BARANGAY 219 Zone 20 with postal address at [ADDRESS].',
            '',
            'This is to further certify that the above mentioned name belongs to an indigent family of this barangay.',
            '',
            'Issued this [DATE_ISSUED] at Barangay 219 Zone 20 Manila.'
        ]);
    }

    return implode("\n", [
        'TO WHOM IT MAY CONCERN:',
        '',
        'This is to certify that [NAME], residing at [ADDRESS], is a bona fide resident of Barangay 219, Tondo, Manila.',
        '',
        'This certification is issued upon request for [PURPOSE].',
        '',
        'Issued this [DATE_ISSUED] at Barangay 219, Tondo, Manila.'
    ]);
}

function buildCertificateBody(string $certificateType, string $name, ?int $age, string $civilStatus, string $address, string $purpose, string $dateIssued, string $controlNumber): string {
    $template = getCertificateBodyTemplate($certificateType);

    $replacements = [
        '[NAME]' => $name !== '' ? $name : 'N/A',
        '[AGE]' => $age !== null && $age > 0 ? (string)$age : 'N/A',
        '[CIVIL_STATUS]' => $civilStatus !== '' ? ucfirst(strtolower($civilStatus)) : 'N/A',
        '[ADDRESS]' => $address !== '' ? $address : 'N/A',
        '[PURPOSE]' => $purpose !== '' ? $purpose : 'legal purpose',
        '[PURPOSE_CHECKLIST]' => buildPurposeChecklistText($purpose),
        '[DATE_ISSUED]' => $dateIssued,
        '[CONTROL_NUMBER]' => $controlNumber
    ];

    return strtr($template, $replacements);
}

function formatIssuedDateForBody(string $dateValue): string {
    $ts = strtotime($dateValue);
    if ($ts === false) {
        $ts = time();
    }

    $day = (int)date('j', $ts);
    $monthYear = date('F Y', $ts);
    $mod100 = $day % 100;

    if ($mod100 >= 11 && $mod100 <= 13) {
        $suffix = 'th';
    } else {
        $suffix = match ($day % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }

    return $day . $suffix . ' day of ' . $monthYear;
}

function getResidentCivilStatus(int $residentId): string {
    if ($residentId <= 0) {
        return '';
    }

    $db = Database::getInstance();
    $row = $db->fetchOne("SELECT civil_status FROM residents WHERE id = ? LIMIT 1", [$residentId]);
    return trim((string)($row['civil_status'] ?? ''));
}

function getCertificateResidentOptions() {
    try {
        $db = Database::getInstance();
        $q = trim((string)($_GET['q'] ?? ''));
        $limit = min(1000, max(50, (int)($_GET['limit'] ?? 500)));

        $params = [];
        $where = '';
        if ($q !== '') {
            $term = '%' . $q . '%';
            $where = "WHERE (first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ? OR CONCAT(last_name, ', ', first_name) LIKE ? )";
            $params = [$term, $term, $term, $term, $term];
        }

        $sql = "SELECT id, first_name, middle_name, last_name
                FROM residents
                {$where}
                ORDER BY last_name ASC, first_name ASC
                LIMIT {$limit}";

        $rows = $db->fetchAll($sql, $params);
        sendResponse(true, 'Resident options retrieved', ['residents' => $rows]);
    } catch (Exception $e) {
        sendResponse(false, 'Error loading resident options: ' . $e->getMessage(), null, 500);
    }
}

function listCertificates(bool $pendingOnly = false) {
    try {
        $db = Database::getInstance();
        ensureCertificateWorkflowSchema();

        $status = normalizeStatus((string)($_GET['status'] ?? ''));
        $q = trim((string)($_GET['q'] ?? $_GET['search'] ?? ''));
        $type = normalizeCertificateType((string)($_GET['type'] ?? ''));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(50, max(10, (int)($_GET['limit'] ?? ITEMS_PER_PAGE)));
        $offset = ($page - 1) * $limit;

        $where = ['1=1'];
        $params = [];

        if ($pendingOnly) {
            $where[] = 'c.status = ?';
            $params[] = 'pending';
        } elseif ($status !== '' && in_array($status, CERT_ALLOWED_STATUSES, true)) {
            $where[] = 'c.status = ?';
            $params[] = $status;
        }

        if ($type !== '') {
            $where[] = 'c.certificate_type = ?';
            $params[] = $type;
        }

        if ($q !== '') {
            $term = '%' . $q . '%';
            $where[] = "(c.application_ref LIKE ? OR c.reference_number LIKE ? OR c.control_number LIKE ? OR CONCAT(r.first_name, ' ', r.last_name) LIKE ?)";
            $params = array_merge($params, [$term, $term, $term, $term]);
        }

        $isResident = hasRole(ROLE_RESIDENT);
        $residentId = getCurrentResidentId();
        if ($isResident && $residentId > 0) {
            $where[] = 'c.resident_id = ?';
            $params[] = $residentId;
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) AS total FROM certificate_requests c LEFT JOIN residents r ON c.resident_id = r.id WHERE {$whereSql}";
        $total = (int)($db->fetchOne($countSql, $params)['total'] ?? 0);

        $sql = "SELECT c.*, CONCAT(r.first_name, ' ', COALESCE(r.middle_name, ''), ' ', r.last_name) AS resident_name,
                       r.address AS resident_address, r.birth_date, r.gender, r.civil_status
                FROM certificate_requests c
                LEFT JOIN residents r ON c.resident_id = r.id
                WHERE {$whereSql}
                ORDER BY c.created_at DESC
                LIMIT ? OFFSET ?";

        $rowsParams = array_merge($params, [$limit, $offset]);
        $rows = $db->fetchAll($sql, $rowsParams);

        sendResponse(true, 'Certificates retrieved', [
            'certificates' => $rows,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => max(1, (int)ceil($total / $limit))
        ]);
    } catch (Exception $e) {
        sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
    }
}

function getCertificate() {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        sendResponse(false, 'ID required', null, 400);
    }

    try {
        $db = Database::getInstance();
        ensureCertificateWorkflowSchema();

        $row = $db->fetchOne(
            "SELECT c.*, CONCAT(r.first_name, ' ', COALESCE(r.middle_name,''), ' ', r.last_name) AS resident_name,
                    r.address, r.birth_date, r.gender, r.civil_status, r.occupation, r.contact_number, r.citizenship
             FROM certificate_requests c
             LEFT JOIN residents r ON c.resident_id = r.id
             WHERE c.id = ?",
            [$id]
        );

        if (!$row) {
            sendResponse(false, 'Not found', null, 404);
        }

        $isResident = hasRole(ROLE_RESIDENT);
        $residentId = getCurrentResidentId();
        if ($isResident && $residentId > 0 && (int)$row['resident_id'] !== $residentId) {
            sendResponse(false, 'Access denied', null, 403);
        }

        sendResponse(true, 'Found', $row);
    } catch (Exception $e) {
        sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
    }
}

function submitResidentCertificateRequest() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'POST required', null, 405);
    }

    $residentId = getCurrentResidentId();
    if ($residentId <= 0) {
        sendResponse(false, 'Resident account required', null, 403);
    }

    $certificateType = normalizeCertificateType((string)($_POST['certificate_type'] ?? CERT_BARANGAY_CLEARANCE));
    $name = trim((string)($_POST['name'] ?? ''));
    $age = (int)($_POST['age'] ?? 0);
    $address = trim((string)($_POST['address'] ?? ''));
    $purposeOption = trim((string)($_POST['purpose'] ?? ''));
    $purposeOther = trim((string)($_POST['purpose_other'] ?? ''));

    if (!in_array($certificateType, CERT_ALLOWED_TYPES, true)) {
        sendResponse(false, 'Invalid certificate type', null, 400);
    }

    if ($name === '' || $age <= 0 || $address === '' || $purposeOption === '') {
        sendResponse(false, 'Name, age, address, and purpose are required', null, 400);
    }

    $purpose = resolvePurpose($purposeOption, $purposeOther);

    try {
        $db = Database::getInstance();
        ensureCertificateWorkflowSchema();

        $db->beginTransaction();

        $db->query(
            "INSERT INTO certificate_requests (
                resident_id, requested_by, certificate_type, purpose, status,
                cert_name, cert_age, cert_address, cert_purpose,
                purpose_option, purpose_other
             ) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?)",
            [
                $residentId,
                (int)getCurrentUserId(),
                $certificateType,
                $purpose,
                $name,
                $age,
                $address,
                $purpose,
                $purposeOption,
                $purposeOther !== '' ? $purposeOther : null
            ]
        );

        $id = (int)$db->lastInsertId();

        if ($purposeOther !== '') {
            $db->query("UPDATE certificate_requests SET purpose_details = ? WHERE id = ?", [$purposeOther, $id]);
        }
        $appRef = generateApplicationRefForId($id);
        $refNo = generateReferenceNumberForId($id);

        $db->query(
            "UPDATE certificate_requests SET application_ref = ?, reference_number = ? WHERE id = ?",
            [$appRef, $refNo, $id]
        );

        $db->commit();

        logActivity('create', 'certificates', $id, ['status' => 'pending', 'certificate_type' => $certificateType]);

        sendResponse(true, 'Certificate request submitted', [
            'id' => $id,
            'application_ref' => $appRef,
            'reference_number' => $refNo,
            'status' => 'pending'
        ]);
    } catch (Exception $e) {
        try {
            Database::getInstance()->rollback();
        } catch (Exception $rollbackErr) {
        }
        sendResponse(false, 'Error submitting request: ' . $e->getMessage(), null, 500);
    }
}

function createCertificateByAdmin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'POST required', null, 405);
    }

    $residentId = (int)($_POST['resident_id'] ?? 0);
    $certificateType = normalizeCertificateType((string)($_POST['certificate_type'] ?? ''));
    $name = trim((string)($_POST['cert_name'] ?? $_POST['name'] ?? ''));
    $age = (int)($_POST['cert_age'] ?? $_POST['age'] ?? 0);
    $address = trim((string)($_POST['cert_address'] ?? $_POST['address'] ?? ''));
    $purposeOption = trim((string)($_POST['purpose'] ?? $_POST['cert_purpose'] ?? ''));
    $purposeOther = trim((string)($_POST['purpose_other'] ?? ''));

    if ($residentId <= 0 || !in_array($certificateType, CERT_ALLOWED_TYPES, true)) {
        sendResponse(false, 'Resident and valid certificate type are required', null, 400);
    }

    if ($name === '' || $age <= 0 || $address === '' || $purposeOption === '') {
        sendResponse(false, 'Name, age, address, and purpose are required', null, 400);
    }

    $purpose = resolvePurpose($purposeOption, $purposeOther);

    try {
        $db = Database::getInstance();
        ensureCertificateWorkflowSchema();

        $db->query(
            "INSERT INTO certificate_requests (
                resident_id, requested_by, certificate_type, purpose, status,
                cert_name, cert_age, cert_address, cert_purpose,
                purpose_option, purpose_other
             ) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?)",
            [
                $residentId,
                (int)getCurrentUserId(),
                $certificateType,
                $purpose,
                $name,
                $age,
                $address,
                $purpose,
                $purposeOption,
                $purposeOther !== '' ? $purposeOther : null
            ]
        );

        $id = (int)$db->lastInsertId();

        if ($purposeOther !== '') {
            $db->query("UPDATE certificate_requests SET purpose_details = ? WHERE id = ?", [$purposeOther, $id]);
        }
        $appRef = generateApplicationRefForId($id);
        $refNo = generateReferenceNumberForId($id);

        $db->query("UPDATE certificate_requests SET application_ref = ?, reference_number = ? WHERE id = ?", [$appRef, $refNo, $id]);

        logActivity('create', 'certificates', $id, ['status' => 'pending', 'source' => 'admin']);

        sendResponse(true, 'Certificate request created', [
            'id' => $id,
            'application_ref' => $appRef,
            'reference_number' => $refNo,
            'status' => 'pending'
        ]);
    } catch (Exception $e) {
        sendResponse(false, 'Error creating request: ' . $e->getMessage(), null, 500);
    }
}

function directIssueCertificateByAdmin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'POST required', null, 405);
    }

    $residentId = (int)($_POST['resident_id'] ?? 0);
    $certificateType = normalizeCertificateType((string)($_POST['certificate_type'] ?? ''));
    $purposeOption = trim((string)($_POST['purpose'] ?? 'Walk-in issuance'));
    $purposeOther = trim((string)($_POST['purpose_other'] ?? ''));

    if ($residentId <= 0 || !in_array($certificateType, CERT_ALLOWED_TYPES, true)) {
        sendResponse(false, 'Resident and valid certificate type are required', null, 400);
    }

    $purpose = resolvePurpose($purposeOption !== '' ? $purposeOption : 'Walk-in issuance', $purposeOther);

    try {
        $db = Database::getInstance();
        ensureCertificateWorkflowSchema();

        $resident = $db->fetchOne(
            "SELECT first_name, middle_name, last_name, birth_date, address, civil_status
             FROM residents
             WHERE id = ?
             LIMIT 1",
            [$residentId]
        );

        if (!$resident) {
            sendResponse(false, 'Resident not found', null, 404);
        }

        $certName = trim(
            ((string)($resident['first_name'] ?? '')) . ' '
            . ((string)($resident['middle_name'] ?? '') !== '' ? ((string)$resident['middle_name'] . ' ') : '')
            . ((string)($resident['last_name'] ?? ''))
        );
        if ($certName === '') {
            $certName = 'N/A';
        }

        $certAddress = trim((string)($resident['address'] ?? ''));
        if ($certAddress === '') {
            $certAddress = 'Barangay 219, Tondo, Manila';
        }

        $certAge = null;
        $birthDateRaw = trim((string)($resident['birth_date'] ?? ''));
        if ($birthDateRaw !== '') {
            $birthTs = strtotime($birthDateRaw);
            if ($birthTs !== false) {
                $birthDate = new DateTime(date('Y-m-d', $birthTs));
                $todayDate = new DateTime('today');
                $computedAge = (int)$birthDate->diff($todayDate)->y;
                if ($computedAge > 0) {
                    $certAge = $computedAge;
                }
            }
        }

        $civilStatus = trim((string)($resident['civil_status'] ?? ''));
        $issueDate = date('Y-m-d');
        $controlNumber = generateControlNumber();
        $certBody = buildCertificateBody(
            $certificateType,
            $certName,
            $certAge,
            $civilStatus,
            $certAddress,
            $purpose,
            formatIssuedDateForBody($issueDate),
            $controlNumber
        );

        $db->beginTransaction();

        $db->query(
            "INSERT INTO certificate_requests (
                resident_id,
                requested_by,
                certificate_type,
                purpose,
                status,
                cert_name,
                cert_age,
                cert_address,
                cert_purpose,
                cert_body,
                purpose_option,
                purpose_other,
                date_issued,
                issued_date,
                approved_at,
                ready_for_pickup_at,
                admin_id,
                control_number
             ) VALUES (?, ?, ?, ?, 'ready_for_pickup', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)",
            [
                $residentId,
                (int)getCurrentUserId(),
                $certificateType,
                $purpose,
                $certName,
                $certAge,
                $certAddress,
                $purpose,
                $certBody,
                $purposeOption,
                $purposeOther !== '' ? $purposeOther : null,
                $issueDate,
                $issueDate,
                (int)getCurrentUserId(),
                $controlNumber
            ]
        );

        $id = (int)$db->lastInsertId();
        $appRef = generateApplicationRefForId($id);
        $refNo = generateReferenceNumberForId($id);

        $db->query(
            "UPDATE certificate_requests SET application_ref = ?, reference_number = ? WHERE id = ?",
            [$appRef, $refNo, $id]
        );

        $db->commit();

        sendResidentNotification(
            $residentId,
            'Certificate Ready for Pickup',
            'A walk-in certificate request has been prepared and is ready for pickup. Control number: ' . $controlNumber,
            'success'
        );

        logActivity('direct_issue', 'certificates', $id, [
            'status' => 'ready_for_pickup',
            'control_number' => $controlNumber,
            'source' => 'walk_in'
        ]);

        sendResponse(true, 'Walk-in certificate request issued successfully', [
            'id' => $id,
            'application_ref' => $appRef,
            'reference_number' => $refNo,
            'status' => 'ready_for_pickup',
            'control_number' => $controlNumber,
            'issuance_date' => $issueDate,
            'print_url' => BASE_URL . 'certificate-print.php?id=' . $id
        ]);
    } catch (Exception $e) {
        try {
            Database::getInstance()->rollback();
        } catch (Exception $rollbackErr) {
        }
        sendResponse(false, 'Error issuing walk-in certificate: ' . $e->getMessage(), null, 500);
    }
}

function updateCertificateWorkflow() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'POST required', null, 405);
    }

    $id = (int)($_POST['id'] ?? 0);
    $targetStatus = normalizeStatus((string)($_POST['status'] ?? ''));

    if ($id <= 0) {
        sendResponse(false, 'ID required', null, 400);
    }

    if ($targetStatus === '') {
        saveCertificateDraft();
        return;
    }

    if (!in_array($targetStatus, CERT_ALLOWED_STATUSES, true)) {
        sendResponse(false, 'Invalid status', null, 400);
    }

    if ($targetStatus === 'approved') {
        approveCertificateRequest();
        return;
    }

    if ($targetStatus === 'ready_for_pickup') {
        approveAndPrepareForPickup();
        return;
    }

    if ($targetStatus === 'rejected') {
        rejectCertificateRequest();
        return;
    }

    if ($targetStatus === 'released') {
        markCertificateReleased();
        return;
    }

    try {
        $db = Database::getInstance();
        ensureCertificateWorkflowSchema();

        $current = $db->fetchOne("SELECT status FROM certificate_requests WHERE id = ?", [$id]);
        if (!$current) {
            sendResponse(false, 'Request not found', null, 404);
        }

        $fromStatus = normalizeStatus((string)$current['status']);
        $allowedTransitions = [
            'pending' => [],
            'approved' => [],
            'ready_for_pickup' => [],
            'rejected' => [],
            'released' => []
        ];

        if (!in_array($targetStatus, $allowedTransitions[$fromStatus] ?? [], true)) {
            sendResponse(false, 'Invalid status transition', null, 400);
        }

        sendResponse(false, 'Invalid status transition', null, 400);
    } catch (Exception $e) {
        sendResponse(false, 'Error updating status: ' . $e->getMessage(), null, 500);
    }
}

function saveCertificateDraft() {
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

        $row = $db->fetchOne("SELECT status FROM certificate_requests WHERE id = ?", [$id]);
        if (!$row) {
            sendResponse(false, 'Request not found', null, 404);
        }

        $currentStatus = normalizeStatus((string)$row['status']);
        $certName = trim((string)($_POST['cert_name'] ?? $_POST['name'] ?? ''));
        $certAddress = trim((string)($_POST['cert_address'] ?? $_POST['address'] ?? ''));
        $certPurpose = trim((string)($_POST['cert_purpose'] ?? $_POST['purpose'] ?? ''));
        $remarksProvided = array_key_exists('remarks', $_POST);
        $remarks = trim((string)($_POST['remarks'] ?? ''));

        $updates = [];
        $params = [];

        if ($certName !== '' || $certAddress !== '' || $certPurpose !== '') {
            sendResponse(false, 'Manual certificate field editing is disabled. Use Prepare for Pickup for automatic generation.', null, 400);
        }

        if ($remarksProvided) {
            if ($currentStatus === 'pending') {
                sendResponse(false, 'Pending requests are read-only', null, 400);
            }
            $updates[] = 'remarks = ?';
            $params[] = $remarks;
        }

        if (empty($updates)) {
            sendResponse(false, 'No changes to save', null, 400);
        }

        $updates[] = 'admin_id = ?';
        $params[] = (int)getCurrentUserId();
        $params[] = $id;

        $db->query("UPDATE certificate_requests SET " . implode(', ', $updates) . " WHERE id = ?", $params);
        logActivity('update', 'certificates', $id, ['status' => $currentStatus, 'draft' => true]);

        sendResponse(true, 'Draft updated', ['id' => $id, 'status' => $currentStatus]);
    } catch (Exception $e) {
        sendResponse(false, 'Error saving draft: ' . $e->getMessage(), null, 500);
    }
}

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

        $row = $db->fetchOne("SELECT resident_id, status FROM certificate_requests WHERE id = ?", [$id]);
        if (!$row) {
            sendResponse(false, 'Request not found', null, 404);
        }

        $currentStatus = normalizeStatus((string)$row['status']);
        if ($currentStatus !== 'pending') {
            sendResponse(false, 'Only pending requests can be approved', null, 400);
        }

        $db->query(
            "UPDATE certificate_requests
             SET status = 'approved',
                 approved_at = COALESCE(approved_at, NOW()),
                 admin_id = ?
             WHERE id = ?",
            [(int)getCurrentUserId(), $id]
        );

        sendResidentNotification(
            (int)$row['resident_id'],
            'Certificate Approved',
            'Your certificate request has been approved and is now being prepared.',
            'success'
        );

        logActivity('approve', 'certificates', $id, ['status' => 'approved']);
        sendResponse(true, 'Request approved', ['id' => $id, 'status' => 'approved']);
    } catch (Exception $e) {
        sendResponse(false, 'Error approving request: ' . $e->getMessage(), null, 500);
    }
}

function approveAndPrepareForPickup() {
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
            "SELECT resident_id, certificate_type, status, cert_body, cert_name, cert_age, cert_address,
                  cert_purpose, purpose, control_number, date_issued, civil_status, birth_date,
                    first_name, middle_name, last_name, resident_address
             FROM (
                SELECT c.resident_id, c.certificate_type, c.status, c.cert_body, c.cert_name, c.cert_age,
                  c.cert_address, c.cert_purpose, c.purpose, c.control_number, c.date_issued,
                       r.civil_status, r.birth_date, r.first_name, r.middle_name, r.last_name,
                       r.address AS resident_address
                FROM certificate_requests c
                LEFT JOIN residents r ON r.id = c.resident_id
                WHERE c.id = ?
            ) t",
            [$id]
        );

        if (!$row) {
            sendResponse(false, 'Request not found', null, 404);
        }

        $currentStatus = normalizeStatus((string)$row['status']);
        if ($currentStatus !== 'approved') {
            sendResponse(false, 'Only approved requests can be prepared for pickup', null, 400);
        }

        $resolvedName = trim(
            (string)($row['cert_name'] ?? '') !== ''
                ? (string)$row['cert_name']
                : ((string)($row['first_name'] ?? '') . ' '
                    . ((string)($row['middle_name'] ?? '') !== '' ? (string)$row['middle_name'] . ' ' : '')
                    . (string)($row['last_name'] ?? ''))
        );
        $resolvedAddress = trim((string)($row['cert_address'] ?? $row['resident_address'] ?? ''));
        $resolvedPurpose = trim((string)($row['cert_purpose'] ?? ''));
        if ($resolvedPurpose === '') {
            $resolvedPurpose = trim((string)($row['purpose'] ?? ''));
        }
        if ($resolvedPurpose === '') {
            $resolvedPurpose = 'legal purpose';
        }
        $resolvedAge = (int)($row['cert_age'] ?? 0);
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
        $issueDate = date('Y-m-d');
        $controlNumber = trim((string)($row['control_number'] ?? ''));
        if ($controlNumber === '') {
            $controlNumber = generateControlNumber();
        }

        $generatedBody = '';
        if ($generatedBody === '') {
            $generatedBody = buildCertificateBody(
                (string)$row['certificate_type'],
                $resolvedName,
                $resolvedAge > 0 ? $resolvedAge : null,
                $civilStatus,
                $resolvedAddress,
                $resolvedPurpose,
                formatIssuedDateForBody($issueDate),
                $controlNumber
            );
        }

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

        sendResidentNotification(
            (int)$row['resident_id'],
            'Certificate Ready for Pickup',
            'Your certificate request has been finalized and is now ready for pickup.',
            'success'
        );

        logActivity('prepare', 'certificates', $id, ['status' => 'ready_for_pickup', 'control_number' => $controlNumber]);

        sendResponse(true, 'Request marked Ready for Pickup', [
            'id' => $id,
            'status' => 'ready_for_pickup',
            'date_issued' => $issueDate,
            'control_number' => $controlNumber
        ]);
    } catch (Exception $e) {
        sendResponse(false, 'Error preparing request for pickup: ' . $e->getMessage(), null, 500);
    }
}

function rejectCertificateRequest() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'POST required', null, 405);
    }

    $id = (int)($_POST['id'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? $_POST['rejection_reason'] ?? ''));

    if ($id <= 0) {
        sendResponse(false, 'ID required', null, 400);
    }

    if ($reason === '') {
        sendResponse(false, 'Rejection reason is required', null, 400);
    }

    try {
        $db = Database::getInstance();
        ensureCertificateWorkflowSchema();

        $row = $db->fetchOne("SELECT resident_id, status FROM certificate_requests WHERE id = ?", [$id]);
        if (!$row) {
            sendResponse(false, 'Request not found', null, 404);
        }

        $currentStatus = normalizeStatus((string)$row['status']);
        if ($currentStatus !== 'pending') {
            sendResponse(false, 'Only pending requests can be rejected', null, 400);
        }

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

        sendResidentNotification(
            (int)$row['resident_id'],
            'Certificate Rejected',
            'Your certificate request was rejected. Reason: ' . $reason,
            'danger'
        );

        logActivity('reject', 'certificates', $id, ['reason' => $reason]);

        sendResponse(true, 'Request rejected', ['id' => $id, 'status' => 'rejected']);
    } catch (Exception $e) {
        sendResponse(false, 'Error rejecting request: ' . $e->getMessage(), null, 500);
    }
}

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
                "SELECT resident_id, certificate_type, status, cert_name, cert_age, cert_address, cert_purpose,
                          first_name, middle_name, last_name, resident_address, birth_date, civil_status
             FROM (
                SELECT c.resident_id, c.certificate_type, c.status,
                              c.cert_name, c.cert_age, c.cert_address, c.cert_purpose,
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
        }

        $currentStatus = normalizeStatus((string)$row['status']);
        if ($currentStatus !== 'ready_for_pickup') {
            sendResponse(false, 'Only Ready for Pickup requests can be marked Released', null, 400);
        }

        $ctrlNum = trim((string)($row['control_number'] ?? ''));
        if ($ctrlNum === '') {
            $ctrlNum = generateControlNumber();
        }
        $issueDate = trim((string)($row['date_issued'] ?? ''));
        if ($issueDate === '') {
            $issueDate = date('Y-m-d');
        }
        $generatedBody = trim((string)($_POST['cert_body'] ?? ''));
        if ($generatedBody === '') {
            $generatedBody = trim((string)($row['cert_body'] ?? ''));
        }

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

        sendResidentNotification(
            (int)$row['resident_id'],
            'Certificate Released',
            'Your certificate has been released. Control number: ' . $ctrlNum,
            'success'
        );

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

function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}
