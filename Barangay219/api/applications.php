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
if (!canAccessModule('resident_applications')) {
    sendResponse(false, 'Access denied', null, 403);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        listApplications();
        break;
    case 'get':
        getApplication();
        break;
    case 'approve':
        if (!canPerformModulePermission('resident_applications', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        approveApplication();
        break;
    case 'reject':
        if (!canPerformModulePermission('resident_applications', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        rejectApplication();
        break;
    case 'activation_link':
        if (!canPerformModulePermission('resident_applications', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        getActivationLink();
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

function tableExists($db, $table) {
    $row = $db->fetchOne(
        "SELECT COUNT(*) AS cnt
         FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?",
        [$table]
    );
    return !empty($row) && (int)$row['cnt'] > 0;
}

function getTableColumns($db, $table) {
    $rows = $db->fetchAll(
        "SELECT column_name
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ?",
        [$table]
    );
    return array_map(static function($r) { return $r['column_name']; }, $rows);
}

function listApplications() {
    $status = $_GET['status'] ?? 'pending';
    $q = trim($_GET['q'] ?? '');
    $sex = trim($_GET['sex'] ?? '');
    $from = trim($_GET['from'] ?? '');
    $to = trim($_GET['to'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(10, (int)($_GET['limit'] ?? ITEMS_PER_PAGE)));
    $offset = ($page - 1) * $limit;

    $allowed_status = ['pending', 'approved', 'rejected'];
    if (!in_array($status, $allowed_status)) {
        $status = 'pending';
    }

    try {
        $db = Database::getInstance();
        if (!tableExists($db, 'resident_applications')) {
            sendResponse(false, 'Resident applications table is missing. Run database/migrations/001_resident_registration_workflow.sql', null, 500);
        }

        $cols = array_flip(getTableColumns($db, 'resident_applications'));
        $statusCol = isset($cols['record_status']) ? 'record_status' : (isset($cols['status']) ? 'status' : null);
        $sexCol = isset($cols['sex']) ? 'sex' : (isset($cols['gender']) ? 'gender' : null);

        if (!$statusCol) {
            sendResponse(false, 'Missing status column in resident_applications (expected record_status or status)', null, 500);
        }

        if ($status === 'pending') {
            // Support legacy pending-like values so newly submitted records still appear.
            $where = "`$statusCol` IN ('pending','submitted','new')";
            $params = [];
        } else {
            $where = "`$statusCol` = ?";
            $params = [$status];
        }

        if (!empty($q)) {
            $term = '%' . $q . '%';
            $where .= " AND (application_ref LIKE ? OR first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ? OR mobile_number LIKE ? OR email LIKE ?)";
            $params = array_merge($params, [$term, $term, $term, $term, $term, $term, $term]);
        }

        if (!empty($sex) && $sexCol) {
            $where .= " AND `$sexCol` = ?";
            $params[] = $sex;
        }

        if (!empty($from)) {
            $where .= " AND DATE(created_at) >= ?";
            $params[] = $from;
        }

        if (!empty($to)) {
            $where .= " AND DATE(created_at) <= ?";
            $params[] = $to;
        }

        $countSql = "SELECT COUNT(*) as total FROM resident_applications WHERE $where";
        $total = $db->fetchOne($countSql, $params)['total'];

        $selectSex = $sexCol ? "`$sexCol` AS sex" : "NULL AS sex";
        $selectStatus = "`$statusCol` AS record_status";
        $selectReviewedAt = isset($cols['reviewed_at']) ? "reviewed_at" : "NULL AS reviewed_at";
        $relCol = isset($cols['relationship_to_head']) ? 'relationship_to_head' : null;
        $roleCol = isset($cols['household_role']) ? 'household_role' : null;
        if ($relCol && $roleCol) {
            $selectRelationship = "COALESCE(NULLIF(`$relCol`, ''), `$roleCol`) AS relationship_to_head";
        } elseif ($relCol) {
            $selectRelationship = "`$relCol` AS relationship_to_head";
        } else {
            $selectRelationship = "NULL AS relationship_to_head";
        }
        $selectHouseholdRole = $roleCol ? "`$roleCol` AS household_role" : "NULL AS household_role";

        $sql = "SELECT id, application_ref, first_name, middle_name, last_name, suffix, $selectSex, birth_date,
                       civil_status, mobile_number, email, barangay, city, $selectRelationship, $selectHouseholdRole, family_code, $selectStatus,
                       created_at, $selectReviewedAt
                FROM resident_applications
                WHERE $where
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?";
        $list = $db->fetchAll($sql, array_merge($params, [$limit, $offset]));

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
        if (!tableExists($db, 'resident_applications')) {
            sendResponse(false, 'Resident applications table is missing. Run database/migrations/001_resident_registration_workflow.sql', null, 500);
        }
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
        if (!tableExists($db, 'resident_applications')) {
            sendResponse(false, 'Resident applications table is missing. Run database/migrations/001_resident_registration_workflow.sql', null, 500);
        }
        $cols = array_flip(getTableColumns($db, 'resident_applications'));
        $statusCol = isset($cols['record_status']) ? 'record_status' : (isset($cols['status']) ? 'status' : null);
        if (!$statusCol) {
            sendResponse(false, 'Missing status column in resident_applications', null, 500);
        }
        $db->beginTransaction();

        $app = $db->fetchOne("SELECT * FROM resident_applications WHERE id = ? AND `$statusCol` = 'pending'", [$id]);
        if (!$app) {
            $db->rollback();
            sendResponse(false, 'Application not found or already processed', null, 404);
        }

        $residentCode = generateResidentCode();
        $userId = getCurrentUserId();
        $resCols = array_flip(getTableColumns($db, 'residents'));
        $userCols = array_flip(getTableColumns($db, 'users'));

        // Build full address
        $addrParts = array_filter([$app['house_number'], $app['street'], $app['purok_sitio'], $app['barangay'], $app['city'], $app['province']]);
        $address = implode(', ', $addrParts);
        $appGender = $app['sex'] ?? ($app['gender'] ?? 'other');

        // Insert resident (schema tolerant)
        $residentData = [
            'resident_code' => $residentCode,
            'first_name' => $app['first_name'] ?? null,
            'middle_name' => $app['middle_name'] ?? null,
            'last_name' => $app['last_name'] ?? null,
            'suffix' => $app['suffix'] ?? null,
            'birth_date' => $app['birth_date'] ?? null,
            'place_of_birth' => $app['place_of_birth'] ?? null,
            'gender' => $appGender,
            'civil_status' => $app['civil_status'] ?? null,
            'citizenship' => $app['citizenship'] ?? 'Filipino',
            'occupation' => $app['occupation'] ?? null,
            'valid_id_type' => $app['valid_id_type'] ?? null,
            'valid_id_number' => $app['valid_id_number'] ?? null,
            'id_document_path' => $app['id_document_path'] ?? null,
            'address' => $address ?: ($app['barangay'] ?? ''),
            'house_number' => $app['house_number'] ?? null,
            'street' => $app['street'] ?? null,
            'purok_sitio' => $app['purok_sitio'] ?? null,
            'family_code' => $app['family_code'] ?? null,
            'relationship_to_head' => $app['relationship_to_head'] ?? null,
            'contact_number' => $app['mobile_number'] ?? null,
            'email' => $app['email'] ?? null,
            'length_of_residency' => $app['length_of_residency'] ?? null,
            'length_of_residency_years' => $app['length_of_residency_years'] ?? null,
            'emergency_contact_name' => $app['emergency_contact_name'] ?? null,
            'emergency_contact_number' => $app['emergency_contact_number'] ?? null,
            'emergency_contact_relationship' => $app['emergency_contact_relationship'] ?? null,
            'educational_attainment' => $app['educational_attainment'] ?? null,
            'employment_status' => $app['employment_status'] ?? null,
            'is_senior_citizen' => $app['is_senior_citizen'] ?? 0,
            'is_pwd' => $app['is_pwd'] ?? 0,
            'pwd_id_number' => $app['pwd_id_number'] ?? null,
            'is_solo_parent' => $app['is_solo_parent'] ?? 0,
            'solo_parent_id_number' => $app['solo_parent_id_number'] ?? null,
            'is_ip_member' => $app['is_ip_member'] ?? 0,
            'ip_group' => $app['ip_group'] ?? null,
            'is_4ps_beneficiary' => $app['is_4ps_beneficiary'] ?? 0,
            'record_status' => 'active',
            'verification_status' => 'pending',
            'remarks' => $remarks ?: null,
            'last_updated_by' => $userId,
            'last_updated_at' => date('Y-m-d H:i:s'),
            'status' => 'active',
            'household_id' => null
        ];

        $resInsertCols = [];
        $resInsertParams = [];
        foreach ($residentData as $col => $val) {
            if (isset($resCols[$col])) {
                $resInsertCols[] = $col;
                $resInsertParams[] = $val;
            }
        }

        if (!isset($resCols['first_name']) || !isset($resCols['last_name']) || !isset($resCols['birth_date'])) {
            throw new Exception('Residents table is missing required columns.');
        }

        $resColSql = '`' . implode('`,`', $resInsertCols) . '`';
        $resPlaceholders = implode(',', array_fill(0, count($resInsertCols), '?'));
        $db->query("INSERT INTO residents ($resColSql) VALUES ($resPlaceholders)", $resInsertParams);

        $residentId = $db->lastInsertId();

        // Auto-create/link household by Family Code
        $roleRaw = strtolower(trim((string)($app['relationship_to_head'] ?? $app['household_role'] ?? '')));
        $isHead = $roleRaw !== '' && (strpos($roleRaw, 'head') !== false || strpos($roleRaw, 'single') !== false);
        $familyCode = trim((string)($app['family_code'] ?? ''));

        if ($isHead && tableExists($db, 'households')) {
            $householdCols = array_flip(getTableColumns($db, 'households'));
            if (isset($householdCols['family_head_id']) && isset($householdCols['address'])) {
                $householdAddress = $address ?: trim((string)($app['barangay'] ?? ''));
                $householdMembers = isset($app['household_members']) ? (int)$app['household_members'] : 1;
                $householdMembers = max(1, $householdMembers);

                $hhCols = ['family_head_id', 'address', 'total_members', 'registration_date', 'family_code'];
                $hhValues = [$residentId, $householdAddress, $householdMembers, date('Y-m-d'), $familyCode ?: null];
                // Keep only columns that exist
                $finalCols = [];
                $finalVals = [];
                foreach ($hhCols as $idx => $col) {
                    if (isset($householdCols[$col])) {
                        $finalCols[] = $col;
                        $finalVals[] = $hhValues[$idx];
                    }
                }
                if ($finalCols) {
                    $hhColSql = '`' . implode('`,`', $finalCols) . '`';
                    $hhPlaceholders = implode(',', array_fill(0, count($finalCols), '?'));
                    $db->query("INSERT INTO households ($hhColSql) VALUES ($hhPlaceholders)", $finalVals);
                    $householdId = $db->lastInsertId();
                    $db->query("UPDATE residents SET household_id = ? WHERE id = ?", [$householdId, $residentId]);
                }
            }
        } elseif (!$isHead && tableExists($db, 'households')) {
            if ($familyCode === '') {
                throw new Exception('Member application is missing family code.');
            }

            $householdCols = array_flip(getTableColumns($db, 'households'));
            if (!isset($householdCols['family_code'])) {
                throw new Exception('Households table does not support family code linking.');
            }

            $linkedHousehold = $db->fetchOne(
                "SELECT id, total_members FROM households WHERE family_code = ? LIMIT 1",
                [$familyCode]
            );
            if (!$linkedHousehold) {
                throw new Exception('Entered family code was not found for any head household.');
            }

            $linkedHouseholdId = (int)$linkedHousehold['id'];
            $db->query("UPDATE residents SET household_id = ? WHERE id = ?", [$linkedHouseholdId, $residentId]);

            if (isset($householdCols['total_members'])) {
                $db->query("UPDATE households SET total_members = GREATEST(1, COALESCE(total_members,0) + 1) WHERE id = ?", [$linkedHouseholdId]);
            }
        }

        // Create user account with Resident ID as username, placeholder password until activation
        $activationToken = bin2hex(random_bytes(32));
        $activationExpires = date('Y-m-d H:i:s', strtotime('+7 days'));
        $placeholderHash = password_hash('PENDING_' . $activationToken, PASSWORD_DEFAULT);

        $userData = [
            'username' => $residentCode,
            'password' => $placeholderHash,
            'email' => $app['email'] ?? null,
            'role' => ROLE_RESIDENT,
            'resident_id' => $residentId,
            'status' => 'active',
            'activation_token' => $activationToken,
            'activation_expires' => $activationExpires
        ];
        $userInsertCols = [];
        $userInsertParams = [];
        foreach ($userData as $col => $val) {
            if (isset($userCols[$col])) {
                $userInsertCols[] = $col;
                $userInsertParams[] = $val;
            }
        }
        if (!isset($userCols['username']) || !isset($userCols['password']) || !isset($userCols['role'])) {
            throw new Exception('Users table is missing required columns.');
        }
        $userColSql = '`' . implode('`,`', $userInsertCols) . '`';
        $userPlaceholders = implode(',', array_fill(0, count($userInsertCols), '?'));
        $db->query("INSERT INTO users ($userColSql) VALUES ($userPlaceholders)", $userInsertParams);

        // Update application
        $updates = ["`$statusCol`='approved'"];
        $updateParams = [];
        if (isset($cols['reviewed_by'])) {
            $updates[] = "reviewed_by=?";
            $updateParams[] = $userId;
        }
        if (isset($cols['reviewed_at'])) {
            $updates[] = "reviewed_at=NOW()";
        }
        if (isset($cols['remarks'])) {
            $updates[] = "remarks=?";
            $updateParams[] = $remarks ?: null;
        }
        $updateParams[] = $id;
        $db->query("UPDATE resident_applications SET " . implode(', ', $updates) . " WHERE id=?", $updateParams);

        // Audit (optional)
        if (tableExists($db, 'application_audit_log')) {
            $db->query(
                "INSERT INTO application_audit_log (application_id, action, performed_by, details) VALUES (?, 'approved', ?, ?)",
                [$id, $userId, json_encode(['resident_id' => $residentId, 'resident_code' => $residentCode])]
            );
        }

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
        if (!tableExists($db, 'resident_applications')) {
            sendResponse(false, 'Resident applications table is missing. Run database/migrations/001_resident_registration_workflow.sql', null, 500);
        }
        $cols = array_flip(getTableColumns($db, 'resident_applications'));
        $statusCol = isset($cols['record_status']) ? 'record_status' : (isset($cols['status']) ? 'status' : null);
        if (!$statusCol) {
            sendResponse(false, 'Missing status column in resident_applications', null, 500);
        }

        $app = $db->fetchOne("SELECT id FROM resident_applications WHERE id = ? AND `$statusCol` = 'pending'", [$id]);
        if (!$app) {
            sendResponse(false, 'Application not found or already processed', null, 404);
        }

        $userId = getCurrentUserId();
        $updates = ["`$statusCol`='rejected'"];
        $updateParams = [];
        if (isset($cols['reviewed_by'])) {
            $updates[] = "reviewed_by=?";
            $updateParams[] = $userId;
        }
        if (isset($cols['reviewed_at'])) {
            $updates[] = "reviewed_at=NOW()";
        }
        if (isset($cols['rejection_reason'])) {
            $updates[] = "rejection_reason=?";
            $updateParams[] = $reason ?: null;
        }
        $updateParams[] = $id;
        $db->query("UPDATE resident_applications SET " . implode(', ', $updates) . " WHERE id=?", $updateParams);
        if (tableExists($db, 'application_audit_log')) {
            $db->query(
                "INSERT INTO application_audit_log (application_id, action, performed_by, details) VALUES (?, 'rejected', ?, ?)",
                [$id, $userId, json_encode(['rejection_reason' => $reason])]
            );
        }

        sendResponse(true, 'Application rejected.');
    } catch (Exception $e) {
        error_log('Reject application: ' . $e->getMessage());
        sendResponse(false, 'Rejection failed', null, 500);
    }
}

function getActivationLink() {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if (!$id) {
        sendResponse(false, 'Application ID required', null, 400);
    }

    try {
        $db = Database::getInstance();
        if (!tableExists($db, 'resident_applications')) {
            sendResponse(false, 'Resident applications table is missing. Run database/migrations/001_resident_registration_workflow.sql', null, 500);
        }

        $appCols = array_flip(getTableColumns($db, 'resident_applications'));
        $statusCol = isset($appCols['record_status']) ? 'record_status' : (isset($appCols['status']) ? 'status' : null);
        if (!$statusCol) {
            sendResponse(false, 'Missing status column in resident_applications', null, 500);
        }

        $app = $db->fetchOne("SELECT * FROM resident_applications WHERE id = ?", [$id]);
        if (!$app) {
            sendResponse(false, 'Application not found', null, 404);
        }
        if (($app[$statusCol] ?? '') !== 'approved') {
            sendResponse(false, 'Activation link is only available for approved applications.', null, 400);
        }

        $userCols = array_flip(getTableColumns($db, 'users'));
        if (!isset($userCols['activation_token']) || !isset($userCols['activation_expires']) || !isset($userCols['resident_id'])) {
            sendResponse(false, 'Users table is missing activation columns.', null, 500);
        }

        // Map approved application to resident user using strong identity fields.
        $user = $db->fetchOne(
            "SELECT u.id, u.username, u.activation_token, u.activation_expires
             FROM users u
             JOIN residents r ON r.id = u.resident_id
             WHERE u.role = ?
               AND r.first_name = ?
               AND r.last_name = ?
               AND r.birth_date = ?
               AND (
                    (? <> '' AND r.email = ?)
                    OR
                    (? <> '' AND r.contact_number = ?)
               )
             ORDER BY u.id DESC
             LIMIT 1",
            [
                ROLE_RESIDENT,
                $app['first_name'] ?? '',
                $app['last_name'] ?? '',
                $app['birth_date'] ?? null,
                $app['email'] ?? '',
                $app['email'] ?? '',
                $app['mobile_number'] ?? '',
                $app['mobile_number'] ?? ''
            ]
        );

        if (!$user) {
            sendResponse(false, 'Could not find resident user for this approved application.', null, 404);
        }

        $token = trim((string)($user['activation_token'] ?? ''));
        if ($token === '') {
            sendResponse(false, 'This resident account is already activated. Login should use Resident ID and password.', [
                'resident_code' => $user['username']
            ], 400);
        }

        $expires = $user['activation_expires'] ?? null;
        $isExpired = !$expires || strtotime($expires) <= time();

        if ($isExpired) {
            $token = bin2hex(random_bytes(32));
            $newExpiry = date('Y-m-d H:i:s', strtotime('+7 days'));
            $db->query(
                "UPDATE users SET activation_token = ?, activation_expires = ? WHERE id = ?",
                [$token, $newExpiry, $user['id']]
            );
            $expires = $newExpiry;
        }

        sendResponse(true, 'Activation link retrieved.', [
            'resident_code' => $user['username'],
            'activation_link' => BASE_URL . 'activate-account.php?token=' . $token,
            'activation_expires' => $expires
        ]);
    } catch (Exception $e) {
        error_log('Get activation link: ' . $e->getMessage());
        sendResponse(false, 'Failed to retrieve activation link', null, 500);
    }
}
