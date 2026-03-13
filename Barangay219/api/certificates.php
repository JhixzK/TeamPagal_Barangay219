<?php
header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
if (!canAccessModule('certificates') && !canAccessModule('applications')) {
    sendResponse(false, 'Access denied', null, 403);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list': listCertificates(); break;
    case 'get': getCertificate(); break;
    case 'create':
        if (!canPerformModulePermission('certificates', 'can_create') && !canPerformModulePermission('applications', 'can_create')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        createCertificate();
        break;
    case 'update':
        if (!canPerformModulePermission('certificates', 'can_edit') && !canPerformModulePermission('applications', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        updateCertificate();
        break;
    case 'approve':
        if (!canPerformModulePermission('certificates', 'can_edit') && !canPerformModulePermission('applications', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        approveCertificate();
        break;
    case 'reject':
        if (!canPerformModulePermission('certificates', 'can_edit') && !canPerformModulePermission('applications', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        rejectCertificate();
        break;
    case 'release':
        if (!canPerformModulePermission('certificates', 'can_edit') && !canPerformModulePermission('applications', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        releaseCertificate();
        break;
    case 'generate_control':
        if (!canPerformModulePermission('certificates', 'can_edit') && !canPerformModulePermission('applications', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        ensureCertificateSchemaForAdmin();
        sendResponse(true, 'Control number generated', ['control_number' => generateControlNumber()]);
        break;
    default: sendResponse(false, 'Invalid action', null, 400);
}

function generateApplicationRef() {
    $db = Database::getInstance();
    $year = date('Y');
    $prefix = 'APP-' . $year . '-';
    $last = $db->fetchOne("SELECT application_ref FROM certificate_requests WHERE application_ref LIKE ? ORDER BY id DESC LIMIT 1", [$prefix . '%']);
    $seq = 1;
    if ($last && preg_match('/-(\d+)$/', $last['application_ref'], $m)) {
        $seq = (int)$m[1] + 1;
    }
    return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
}

function generateControlNumber() {
    $db = Database::getInstance();
    $year = date('Y');
    $prefix = 'BRGY219-' . $year . '-';
    $last = $db->fetchOne("SELECT control_number FROM certificate_requests WHERE control_number LIKE ? ORDER BY id DESC LIMIT 1", [$prefix . '%']);
    $seq = 1;
    if ($last && preg_match('/-(\d+)$/', $last['control_number'], $m)) {
        $seq = (int)$m[1] + 1;
    }
    return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
}

function ensureCertificateSchemaForAdmin() {
    $db = Database::getInstance();
    $db->query(
        "CREATE TABLE IF NOT EXISTS certificate_requests (
            id INT(11) NOT NULL AUTO_INCREMENT,
            resident_id INT(11) NOT NULL,
            requested_by INT(11) DEFAULT NULL,
            certificate_type VARCHAR(120) NOT NULL,
            purpose TEXT DEFAULT NULL,
            status ENUM('pending','under_review','approved','rejected','issued','cancelled') NOT NULL DEFAULT 'pending',
            issued_date DATE DEFAULT NULL,
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
        $map[$column['Field']] = $column;
    }

    $addColumn = function ($name, $definition) use ($db, $map) {
        if (!isset($map[$name])) {
            $db->query("ALTER TABLE certificate_requests ADD COLUMN {$name} {$definition}");
        }
    };

    $addColumn('reference_number', "VARCHAR(50) NULL");
    $addColumn('attachment', "VARCHAR(255) NULL");
    $addColumn('cert_name', "VARCHAR(255) NULL");
    $addColumn('cert_address', "TEXT NULL");
    $addColumn('cert_purpose', "TEXT NULL");
    $addColumn('cert_body', "TEXT NULL");
    $addColumn('issued_date', "DATE NULL");
    $addColumn('date_issued', "DATE NULL");
    $addColumn('control_number', "VARCHAR(50) NULL");
    $addColumn('approved_at', "DATETIME NULL");
    $addColumn('admin_id', "INT(11) NULL");

    $db->query("ALTER TABLE certificate_requests MODIFY COLUMN status ENUM('pending','under_review','approved','rejected','issued','cancelled') NOT NULL DEFAULT 'pending'");

    $missingRefs = $db->fetchAll("SELECT id, created_at FROM certificate_requests WHERE reference_number IS NULL OR reference_number = '' ORDER BY id ASC");
    foreach ($missingRefs as $row) {
        $id = (int)$row['id'];
        $year = date('Y', strtotime($row['created_at'] ?: 'now'));
        $reference = 'REQ-BRGY219-' . $year . '-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
        $db->query("UPDATE certificate_requests SET reference_number = ? WHERE id = ?", [$reference, $id]);
    }

    $index = $db->fetchOne("SHOW INDEX FROM certificate_requests WHERE Key_name = 'uniq_reference_number'");
    if (!$index) {
        $db->query("ALTER TABLE certificate_requests ADD UNIQUE KEY uniq_reference_number (reference_number)");
    }
}

function listCertificates() {
    try {
        $db = Database::getInstance();
        ensureCertificateSchemaForAdmin();
        $status = $_GET['status'] ?? '';
        $q = sanitizeInput($_GET['q'] ?? $_GET['search'] ?? '');
        $type = sanitizeInput($_GET['type'] ?? '');
        $from = sanitizeInput($_GET['from'] ?? '');
        $to = sanitizeInput($_GET['to'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(50, max(10, (int)($_GET['limit'] ?? ITEMS_PER_PAGE)));
        $offset = ($page - 1) * $limit;

        $where = "1=1";
        $params = [];
        if (!empty($q)) {
            $term = '%' . $q . '%';
            $where .= " AND (c.application_ref LIKE ? OR c.control_number LIKE ? OR CONCAT(r.first_name, ' ', r.last_name) LIKE ?)";
            $params = array_merge($params, [$term, $term, $term]);
        }
        if (in_array($status, ['pending', 'under_review', 'approved', 'rejected', 'issued', 'cancelled'])) {
            $where .= " AND c.status = ?";
            $params[] = $status;
        }
        if (!empty($type)) {
            $where .= " AND c.certificate_type = ?";
            $params[] = $type;
        }
        if (!empty($from)) {
            $where .= " AND DATE(c.created_at) >= ?";
            $params[] = $from;
        }
        if (!empty($to)) {
            $where .= " AND DATE(c.created_at) <= ?";
            $params[] = $to;
        }

        $countSql = "SELECT COUNT(*) as total FROM certificate_requests c LEFT JOIN residents r ON c.resident_id = r.id WHERE $where";
        $total = (int)$db->fetchOne($countSql, $params)['total'];

        $sql = "SELECT c.*, CONCAT(r.first_name, ' ', COALESCE(r.middle_name,''), ' ', r.last_name) as resident_name,
                r.address as resident_address, r.birth_date, r.gender, r.civil_status
                FROM certificate_requests c 
                LEFT JOIN residents r ON c.resident_id = r.id 
                WHERE $where
                ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $list = $db->fetchAll($sql, $params);

        // Ensure application_ref exists (for pre-migration data)
        foreach ($list as &$row) {
            if (empty($row['application_ref'])) $row['application_ref'] = 'APP-' . $row['id'] . '-' . date('Y', strtotime($row['created_at']));
        }

        sendResponse(true, 'Certificates retrieved', [
            'certificates' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]);
    } catch (Exception $e) {
        sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
    }
}

function getCertificate() {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    try {
        $db = Database::getInstance();
        ensureCertificateSchemaForAdmin();
        $cert = $db->fetchOne("SELECT c.*, CONCAT(r.first_name, ' ', COALESCE(r.middle_name,''), ' ', r.last_name) as resident_name,
            r.address, r.birth_date, r.gender, r.civil_status, r.occupation, r.contact_number, r.citizenship
            FROM certificate_requests c LEFT JOIN residents r ON c.resident_id = r.id WHERE c.id = ?", [$id]);
        if ($cert && empty($cert['application_ref'])) $cert['application_ref'] = 'APP-' . $cert['id'] . '-' . date('Y', strtotime($cert['created_at']));
        sendResponse($cert ? true : false, $cert ? 'Found' : 'Not found', $cert);
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function createCertificate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $resident_id = intval($_POST['resident_id'] ?? 0);
    $certificate_type = sanitizeInput($_POST['certificate_type'] ?? '');
    $purpose = sanitizeInput($_POST['purpose'] ?? '');
    $remarks = sanitizeInput($_POST['remarks'] ?? '');
    if (!$resident_id || !$certificate_type) { sendResponse(false, 'Resident and certificate type required', null, 400); return; }
    $allowed = [CERT_BARANGAY_CLEARANCE, CERT_INDIGENCY, CERT_RESIDENCY, CERT_GOOD_MORAL, CERT_TRANSFER_REQUEST];
    if (!in_array($certificate_type, $allowed)) { sendResponse(false, 'Invalid certificate type', null, 400); return; }
    try {
        $db = Database::getInstance();
        ensureCertificateSchemaForAdmin();
        $referenceNumber = 'REQ-BRGY219-' . date('Y') . '-TMP';
        $db->query("INSERT INTO certificate_requests (resident_id, requested_by, certificate_type, purpose, status, reference_number) VALUES (?, ?, ?, ?, 'pending', ?)",
            [$resident_id, getCurrentUserId(), $certificate_type, $purpose ?: null, $referenceNumber]);
        $id = $db->lastInsertId();
        $appRef = 'APP-' . $id . '-' . date('Y');
        $referenceNumber = 'REQ-BRGY219-' . date('Y') . '-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
        $db->query("UPDATE certificate_requests SET reference_number = ? WHERE id = ?", [$referenceNumber, $id]);
        logActivity('create', 'certificates', $id, ['application_ref' => $appRef, 'certificate_type' => $certificate_type]);
        sendResponse(true, 'Application created', ['id' => $id, 'application_ref' => $appRef, 'reference_number' => $referenceNumber]);
    } catch (Exception $e) {
        sendResponse(false, 'Error creating: ' . $e->getMessage(), null, 500);
    }
}

function updateCertificate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $id = intval($_POST['id'] ?? 0);
    $status = sanitizeInput($_POST['status'] ?? '');
    $remarks = sanitizeInput($_POST['remarks'] ?? '');
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    $allowed = ['under_review', 'approved', 'rejected', 'issued'];
    if (!in_array($status, $allowed)) { sendResponse(false, 'Invalid status', null, 400); return; }
    try {
        $db = Database::getInstance();
        ensureCertificateSchemaForAdmin();
        $current = $db->fetchOne("SELECT status FROM certificate_requests WHERE id = ?", [$id]);
        if (!$current) {
            sendResponse(false, 'Application not found', null, 404);
            return;
        }

        $currentStatus = (string)($current['status'] ?? 'pending');
        $transitions = [
            'pending' => ['under_review', 'rejected'],
            'under_review' => ['approved', 'rejected'],
            'approved' => ['issued'],
            'rejected' => [],
            'issued' => [],
            'cancelled' => []
        ];

        if (!in_array($status, $transitions[$currentStatus] ?? [], true)) {
            sendResponse(false, 'Invalid status transition', null, 400);
            return;
        }

        $updates = ["status = ?"];
        $params = [$status];
        if ($status === 'approved') {
            $updates[] = "approved_at = NOW()";
            $updates[] = "admin_id = ?";
            $params[] = (int)getCurrentUserId();
        }
        if ($status === 'issued') {
            $updates[] = "issued_date = CURDATE()";
            $updates[] = "date_issued = CURDATE()";
            $updates[] = "admin_id = ?";
            $params[] = (int)getCurrentUserId();
        }
        $cols = $db->getConnection()->query("SHOW COLUMNS FROM certificate_requests LIKE 'remarks'")->fetchAll();
        if (!empty($cols) && $remarks !== '') { $updates[] = "remarks = ?"; $params[] = $remarks; }
        $params[] = $id;
        $db->query("UPDATE certificate_requests SET " . implode(', ', $updates) . " WHERE id = ?", $params);
        logActivity('update', 'certificates', $id, ['status' => $status]);
        sendResponse(true, 'Updated');
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function approveCertificate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    try {
        $db = Database::getInstance();
        ensureCertificateSchemaForAdmin();
        $db->query("UPDATE certificate_requests SET status = 'approved', approved_at = NOW(), admin_id = ? WHERE id = ? AND status = 'under_review'", [(int)getCurrentUserId(), $id]);
        logActivity('approve', 'certificates', $id);
        sendResponse(true, 'Approved');
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function rejectCertificate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $id = intval($_POST['id'] ?? 0);
    $reason = sanitizeInput($_POST['reason'] ?? '');
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    try {
        $db = Database::getInstance();
        ensureCertificateSchemaForAdmin();
        $cols = $db->getConnection()->query("SHOW COLUMNS FROM certificate_requests LIKE 'remarks'")->fetchAll();
        if (!empty($cols) && $reason) {
            $db->query("UPDATE certificate_requests SET status = 'rejected', remarks = ? WHERE id = ?", [$reason, $id]);
        } else {
            $db->query("UPDATE certificate_requests SET status = 'rejected' WHERE id = ?", [$id]);
        }
        logActivity('reject', 'certificates', $id);
        sendResponse(true, 'Rejected');
    } catch (Exception $e) {
        sendResponse(false, 'Error', null, 500);
    }
}

function releaseCertificate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'POST required', null, 405); return; }
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { sendResponse(false, 'ID required', null, 400); return; }
    $certName = sanitizeInput($_POST['cert_name'] ?? '');
    $certAddress = sanitizeInput($_POST['cert_address'] ?? '');
    $certPurpose = sanitizeInput($_POST['cert_purpose'] ?? '');
    $certBody = trim((string)($_POST['cert_body'] ?? ''));
    $dateIssued = sanitizeInput($_POST['date_issued'] ?? '');

    if ($certName === '' || $certAddress === '' || $certPurpose === '' || $certBody === '') {
        sendResponse(false, 'Certificate name, address, purpose, and body are required before issuing.', null, 400);
        return;
    }

    if ($dateIssued !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateIssued)) {
        sendResponse(false, 'Invalid date issued format.', null, 400);
        return;
    }

    try {
        $db = Database::getInstance();
        ensureCertificateSchemaForAdmin();
        $row = $db->fetchOne("SELECT status FROM certificate_requests WHERE id = ?", [$id]);
        if (!$row) {
            sendResponse(false, 'Application not found', null, 404);
            return;
        }
        if (($row['status'] ?? '') !== 'approved') {
            sendResponse(false, 'Only approved applications can be issued.', null, 400);
            return;
        }

        $ctrlNum = generateControlNumber();
        $issueDateSql = $dateIssued !== '' ? $dateIssued : date('Y-m-d');

        $db->query(
            "UPDATE certificate_requests
             SET status = 'issued',
                 cert_name = ?,
                 cert_address = ?,
                 cert_purpose = ?,
                 cert_body = ?,
                 date_issued = ?,
                 issued_date = ?,
                 control_number = ?,
                 admin_id = ?,
                 approved_at = COALESCE(approved_at, NOW())
             WHERE id = ? AND status = 'approved'",
            [
                $certName,
                $certAddress,
                $certPurpose,
                $certBody,
                $issueDateSql,
                $issueDateSql,
                $ctrlNum,
                (int)getCurrentUserId(),
                $id
            ]
        );
        try { logActivity('release', 'certificates', $id, ['control_number' => $ctrlNum]); } catch (Exception $e) { }
        sendResponse(true, 'Released', ['control_number' => $ctrlNum]);
    } catch (Exception $e) {
        sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
    }
}

function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}
