<?php
/**
 * E-Barangay - Resident Applications API (Barangay Staff Only)
 * Review, approve, reject pending applications
 */

header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireAnyRole([ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY]);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        listApplications();
        break;
    case 'get':
        getApplication();
        break;
    case 'approve':
        approveApplication();
        break;
    case 'reject':
        rejectApplication();
        break;
    default:
        sendResponse(false, 'Invalid action', null, 400);
        break;
}

function sendResponse($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

function listApplications() {
    $status = $_GET['status'] ?? 'pending';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(10, (int)($_GET['limit'] ?? ITEMS_PER_PAGE)));
    $offset = ($page - 1) * $limit;

    $allowed_status = ['pending', 'approved', 'rejected'];
    if (!in_array($status, $allowed_status)) {
        $status = 'pending';
    }

    try {
        $db = Database::getInstance();
        $countSql = "SELECT COUNT(*) as total FROM resident_applications WHERE record_status = ?";
        $total = $db->fetchOne($countSql, [$status])['total'];

        $sql = "SELECT id, application_ref, first_name, middle_name, last_name, sex, birth_date,
                       civil_status, mobile_number, barangay, city, record_status,
                       created_at, reviewed_at
                FROM resident_applications
                WHERE record_status = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?";
        $list = $db->fetchAll($sql, [$status, $limit, $offset]);

        sendResponse(true, 'Applications retrieved', [
            'applications' => $list,
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => (int)ceil($total / $limit)
        ]);
    } catch (Exception $e) {
        error_log('Applications list: ' . $e->getMessage());
        sendResponse(false, 'Failed to load applications', null, 500);
    }
}

function getApplication() {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if (!$id) {
        sendResponse(false, 'Application ID required', null, 400);
    }

    try {
        $db = Database::getInstance();
        $sql = "SELECT * FROM resident_applications WHERE id = ?";
        $app = $db->fetchOne($sql, [$id]);
        if (!$app) {
            sendResponse(false, 'Application not found', null, 404);
        }
        // Do not expose full file paths; only relative paths for display
        sendResponse(true, 'Application retrieved', $app);
    } catch (Exception $e) {
        error_log('Get application: ' . $e->getMessage());
        sendResponse(false, 'Failed to load application', null, 500);
    }
}

function generateResidentCode() {
    $db = Database::getInstance();
    $year = date('Y');
    $prefix = 'BR219-' . $year . '-';
    $last = $db->fetchOne("SELECT resident_code FROM residents WHERE resident_code LIKE ? ORDER BY id DESC LIMIT 1", [$prefix . '%']);
    $seq = 1;
    if ($last) {
        $parts = explode('-', $last['resident_code']);
        $seq = (int)($parts[2] ?? 0) + 1;
    }
    return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
}

function approveApplication() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Method not allowed', null, 405);
    }

    $id = (int)($_POST['id'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    if (!$id) {
        sendResponse(false, 'Application ID required', null, 400);
    }

    try {
        $db = Database::getInstance();
        $db->beginTransaction();

        $app = $db->fetchOne("SELECT * FROM resident_applications WHERE id = ? AND record_status = 'pending'", [$id]);
        if (!$app) {
            $db->rollback();
            sendResponse(false, 'Application not found or already processed', null, 404);
        }

        $residentCode = generateResidentCode();
        $userId = getCurrentUserId();

        // Build full address
        $addrParts = array_filter([$app['house_number'], $app['street'], $app['purok_sitio'], $app['barangay'], $app['city'], $app['province']]);
        $address = implode(', ', $addrParts);

        // Insert resident
        $insRes = "INSERT INTO residents (
            resident_code, first_name, middle_name, last_name, suffix, birth_date, place_of_birth,
            gender, civil_status, citizenship, occupation, address, house_number, street, purok_sitio,
            contact_number, email, length_of_residency_years,
            emergency_contact_name, emergency_contact_number, emergency_contact_relationship,
            educational_attainment, employment_status,
            is_senior_citizen, is_pwd, pwd_id_number, is_solo_parent, is_ip_member, is_4ps_beneficiary,
            record_status, remarks, last_updated_by, last_updated_at, status, household_id
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'active',NULL)";

        $db->query($insRes, [
            $residentCode, $app['first_name'], $app['middle_name'] ?: null, $app['last_name'], $app['suffix'] ?: null,
            $app['birth_date'], $app['place_of_birth'] ?: null, $app['sex'], $app['civil_status'] ?: null,
            $app['citizenship'] ?: 'Filipino', $app['occupation'] ?: null, $address,
            $app['house_number'] ?: null, $app['street'] ?: null, $app['purok_sitio'] ?: null,
            $app['mobile_number'], $app['email'] ?: null, $app['length_of_residency_years'] ?: null,
            $app['emergency_contact_name'], $app['emergency_contact_number'], $app['emergency_contact_relationship'],
            $app['educational_attainment'] ?: null, $app['employment_status'] ?: null,
            $app['is_senior_citizen'] ?? 0, $app['is_pwd'] ?? 0, $app['pwd_id_number'] ?: null,
            $app['is_solo_parent'] ?? 0, $app['is_ip_member'] ?? 0, $app['is_4ps_beneficiary'] ?? 0,
            'active', $remarks ?: null, $userId, date('Y-m-d H:i:s')
        ]);

        $residentId = $db->lastInsertId();

        // Create user account with Resident ID as username, placeholder password until activation
        $activationToken = bin2hex(random_bytes(32));
        $activationExpires = date('Y-m-d H:i:s', strtotime('+7 days'));
        $placeholderHash = password_hash('PENDING_' . $activationToken, PASSWORD_DEFAULT);

        $insUser = "INSERT INTO users (username, password, email, role, resident_id, status, activation_token, activation_expires)
                    VALUES (?,?,?,?,?,?,?,?)";
        $db->query($insUser, [
            $residentCode,
            $placeholderHash,
            $app['email'] ?: null,
            ROLE_RESIDENT,
            $residentId,
            'active',
            $activationToken,
            $activationExpires
        ]);

        // Update application
        $db->query(
            "UPDATE resident_applications SET record_status='approved', reviewed_by=?, reviewed_at=NOW(), remarks=? WHERE id=?",
            [$userId, $remarks ?: null, $id]
        );

        // Audit
        $db->query(
            "INSERT INTO application_audit_log (application_id, action, performed_by, details) VALUES (?, 'approved', ?, ?)",
            [$id, $userId, json_encode(['resident_id' => $residentId, 'resident_code' => $residentCode])]
        );

        $db->commit();

        sendResponse(true, 'Application approved. Resident ID generated.', [
            'resident_code' => $residentCode,
            'resident_id' => (int)$residentId,
            'activation_token' => $activationToken,
            'activation_link' => BASE_URL . 'activate-account.php?token=' . $activationToken
        ]);
    } catch (Exception $e) {
        if (isset($db)) $db->rollback();
        error_log('Approve application: ' . $e->getMessage());
        sendResponse(false, 'Approval failed: ' . ($e->getMessage()), null, 500);
    }
}

function rejectApplication() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Method not allowed', null, 405);
    }

    $id = (int)($_POST['id'] ?? 0);
    $reason = trim($_POST['rejection_reason'] ?? '');

    if (!$id) {
        sendResponse(false, 'Application ID required', null, 400);
    }

    try {
        $db = Database::getInstance();
        $app = $db->fetchOne("SELECT id FROM resident_applications WHERE id = ? AND record_status = 'pending'", [$id]);
        if (!$app) {
            sendResponse(false, 'Application not found or already processed', null, 404);
        }

        $userId = getCurrentUserId();
        $db->query(
            "UPDATE resident_applications SET record_status='rejected', reviewed_by=?, reviewed_at=NOW(), rejection_reason=? WHERE id=?",
            [$userId, $reason ?: null, $id]
        );
        $db->query(
            "INSERT INTO application_audit_log (application_id, action, performed_by, details) VALUES (?, 'rejected', ?, ?)",
            [$id, $userId, json_encode(['rejection_reason' => $reason])]
        );

        sendResponse(true, 'Application rejected.');
    } catch (Exception $e) {
        error_log('Reject application: ' . $e->getMessage());
        sendResponse(false, 'Rejection failed', null, 500);
    }
}
