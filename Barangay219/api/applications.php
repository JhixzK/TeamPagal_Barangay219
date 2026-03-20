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
    case 'approved_heads':
        if (!canPerformModulePermission('resident_applications', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        listApprovedHeads();
        break;
    case 'assign_household':
        if (!canPerformModulePermission('resident_applications', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        assignHouseholdToApprovedResident();
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
    case 'resolve_resident':
        if (!canPerformModulePermission('resident_applications', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        resolveResidentForApplication();
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

function generateUniqueHouseholdCode($db) {
    // HH-XXXXXX (6 digits)
    if (!columnExists($db, 'households', 'household_id_code')) {
        // Schema might not have household codes yet; return best-effort value.
        return 'HH-' . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    for ($i = 0; $i < 20; $i++) {
        $code = 'HH-' . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $exists = $db->fetchOne("SELECT id FROM households WHERE household_id_code = ? LIMIT 1", [$code]);
        if (!$exists) {
            return $code;
        }
    }
    throw new Exception('Unable to generate unique household code.');
}

function generateUniqueFamilyHeadCode($db, $isLandlord = false) {
    $prefix = $isLandlord ? 'LL-' : 'FH-';
    if (!columnExists($db, 'households', 'family_head_code')) {
        return $prefix . str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    }
    for ($i = 0; $i < 20; $i++) {
        $code = $prefix . str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        $exists = $db->fetchOne("SELECT id FROM households WHERE family_head_code = ? LIMIT 1", [$code]);
        if (!$exists) {
            return $code;
        }
    }
    throw new Exception('Unable to generate unique family head code.');
}

// Generate a unique FH-XXXXX or LL-XXXXX code stored on the residents table.
// Use $isLandlord = true for Landlord accounts (LL-XXXXX).
function generateResidentFamilyHeadCode($db, $isLandlord = false) {
    $prefix = $isLandlord ? 'LL-' : 'FH-';
    if (!tableExists($db, 'residents') || !columnExists($db, 'residents', 'family_head_code')) {
        return $prefix . str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    }
    for ($i = 0; $i < 30; $i++) {
        $code = $prefix . str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        $exists = $db->fetchOne("SELECT id FROM residents WHERE family_head_code = ? LIMIT 1", [$code]);
        if (!$exists) {
            return $code;
        }
    }
    throw new Exception('Unable to generate unique resident family head code.');
}

function generateUniqueFamilyCode($db) {
    // BR219-YYYY-XXXX (4 digits)
    if (!columnExists($db, 'households', 'family_code')) {
        // Schema might not have family codes yet; return best-effort value.
        $year = date('Y');
        return 'BR219-' . $year . '-' . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
    $year = date('Y');
    for ($i = 0; $i < 30; $i++) {
        $suffix = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $code = 'BR219-' . $year . '-' . $suffix;
        $exists = $db->fetchOne("SELECT id FROM households WHERE family_code = ? LIMIT 1", [$code]);
        if (!$exists) {
            return $code;
        }
    }
    throw new Exception('Unable to generate unique family code.');
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

function columnExists($db, $table, $column) {
    $row = $db->fetchOne(
        "SELECT COUNT(*) AS cnt
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?",
        [$table, $column]
    );
    return !empty($row) && (int)$row['cnt'] > 0;
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

    $allowed_status = ['', 'pending', 'approved', 'rejected'];
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

        if ($status === '' || $status === 'all') {
            $where = '1=1';
            $params = [];
        } elseif ($status === 'pending') {
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
        $selectApprovedResidentId = isset($cols['approved_resident_id']) ? "approved_resident_id" : "NULL AS approved_resident_id";
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
                       created_at, $selectReviewedAt, $selectApprovedResidentId
                FROM resident_applications
                WHERE $where
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?";
        $list = $db->fetchAll($sql, array_merge($params, [$limit, $offset]));

        foreach ($list as &$app) {
            $app['head_needs_assignment'] = false;
            if (($app['record_status'] ?? '') === 'approved') {
                $roleRaw = trim((string)($app['relationship_to_head'] ?? $app['household_role'] ?? ''));
                if (!isHeadRoleValue($roleRaw)) {
                    continue;
                }
                $resId = (int)($app['approved_resident_id'] ?? 0);
                if ($resId <= 0) {
                    $first = trim((string)($app['first_name'] ?? ''));
                    $last = trim((string)($app['last_name'] ?? ''));
                    $dob = trim((string)($app['birth_date'] ?? ''));
                    if ($first !== '' && $last !== '' && $dob !== '') {
                        $dobCol = columnExists($db, 'residents', 'birth_date') ? 'birth_date' : 'date_of_birth';
                        $found = $db->fetchOne(
                            "SELECT id, household_id FROM residents WHERE first_name = ? AND last_name = ? AND $dobCol = ? LIMIT 1",
                            [$first, $last, $dob]
                        );
                        if ($found) {
                            $resId = (int)$found['id'];
                        }
                    }
                }
                if ($resId > 0) {
                    $res = $db->fetchOne("SELECT household_id FROM residents WHERE id = ? LIMIT 1", [$resId]);
                    $app['head_needs_assignment'] = !$res || empty($res['household_id']) || (int)$res['household_id'] <= 0;
                }
            }
        }
        unset($app);

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
        $app['head_needs_assignment'] = false;
        if (($app['record_status'] ?? '') === 'approved') {
            $roleRaw = trim((string)($app['relationship_to_head'] ?? $app['household_role'] ?? ''));
            if (isHeadRoleValue($roleRaw)) {
                $resId = (int)($app['approved_resident_id'] ?? 0);
                if ($resId <= 0 && !empty($app['first_name']) && !empty($app['last_name']) && !empty($app['birth_date'])) {
                    $dobCol = columnExists($db, 'residents', 'birth_date') ? 'birth_date' : 'date_of_birth';
                    $found = $db->fetchOne(
                        "SELECT id, household_id FROM residents WHERE first_name = ? AND last_name = ? AND $dobCol = ? LIMIT 1",
                        [$app['first_name'], $app['last_name'], $app['birth_date']]
                    );
                    if ($found) $resId = (int)$found['id'];
                }
                if ($resId > 0) {
                    $res = $db->fetchOne("SELECT household_id FROM residents WHERE id = ? LIMIT 1", [$resId]);
                    $app['head_needs_assignment'] = !$res || empty($res['household_id']) || (int)$res['household_id'] <= 0;
                }
            }
        }
        sendResponse(true, 'Application retrieved', $app);
    } catch (Exception $e) {
        error_log('Get application: ' . $e->getMessage());
        sendResponse(false, 'Failed to load application', null, 500);
    }
}

function listApprovedHeads() {
    try {
        $db = Database::getInstance();
        if (!tableExists($db, 'resident_applications')) {
            sendResponse(false, 'Resident applications table is missing.', null, 500);
        }

        $cols = array_flip(getTableColumns($db, 'resident_applications'));
        $statusCol = isset($cols['record_status']) ? 'record_status' : (isset($cols['status']) ? 'status' : null);
        $selectApprovedResidentId = isset($cols['approved_resident_id']) ? 'approved_resident_id' : 'NULL AS approved_resident_id';
        $relCol = isset($cols['relationship_to_head']) ? 'relationship_to_head' : null;
        $roleCol = isset($cols['household_role']) ? 'household_role' : null;
        if ($relCol && $roleCol) {
            $selectRelationship = "COALESCE(NULLIF(`$relCol`, ''), `$roleCol`) AS relationship_to_head";
        } elseif ($relCol) {
            $selectRelationship = "`$relCol` AS relationship_to_head";
        } else {
            $selectRelationship = "NULL AS relationship_to_head";
        }

        $sql = "SELECT id, application_ref, first_name, middle_name, last_name, suffix, birth_date, $selectApprovedResidentId, $selectRelationship
                FROM resident_applications
                WHERE `$statusCol` = 'approved'
                ORDER BY created_at DESC";
        $rows = $db->fetchAll($sql);

        $heads = [];
        $dobCol = columnExists($db, 'residents', 'birth_date') ? 'birth_date' : 'date_of_birth';
        $hhCodeCol = columnExists($db, 'households', 'household_id_code');
        $hhCols = array_flip(getTableColumns($db, 'households'));
        $hhHeadCol = isset($hhCols['family_head_id']) ? 'family_head_id' : 'head_id';

        foreach ($rows as $app) {
            $roleRaw = trim((string)($app['relationship_to_head'] ?? ''));
            if (!isHeadRoleValue($roleRaw)) {
                continue;
            }

            $resId = (int)($app['approved_resident_id'] ?? 0);
            if ($resId <= 0) {
                $first = trim((string)($app['first_name'] ?? ''));
                $last = trim((string)($app['last_name'] ?? ''));
                $dob = trim((string)($app['birth_date'] ?? ''));
                if ($first !== '' && $last !== '' && $dob !== '') {
                    $found = $db->fetchOne(
                        "SELECT id, household_id FROM residents WHERE first_name = ? AND last_name = ? AND $dobCol = ? LIMIT 1",
                        [$first, $last, $dob]
                    );
                    if ($found) {
                        $resId = (int)$found['id'];
                    }
                }
            }

            if ($resId <= 0) {
                $heads[] = [
                    'id' => (int)$app['id'],
                    'application_ref' => $app['application_ref'] ?? 'APP-' . $app['id'],
                    'first_name' => $app['first_name'] ?? '',
                    'middle_name' => $app['middle_name'] ?? '',
                    'last_name' => $app['last_name'] ?? '',
                    'approved_resident_id' => 0,
                    'household_id' => 0,
                    'household_label' => '',
                    'head_needs_assignment' => true
                ];
                continue;
            }

            $res = $db->fetchOne("SELECT household_id FROM residents WHERE id = ? LIMIT 1", [$resId]);
            $hhId = (int)($res['household_id'] ?? 0);
            $headNeedsAssignment = $hhId <= 0;
            $householdLabel = '';

            if ($hhId > 0) {
                $hhSelect = "id";
                if ($hhCodeCol) $hhSelect .= ", household_id_code";
                $hhSelect .= ", $hhHeadCol AS family_head_id";
                $hh = $db->fetchOne("SELECT $hhSelect FROM households WHERE id = ?", [$hhId]);
                if ($hh) {
                    $code = trim((string)($hh['household_id_code'] ?? ''));
                    if ($code === '' || $code === '-' || strtolower($code) === 'null') {
                        $code = 'HH-' . str_pad((string)$hhId, 5, '0', STR_PAD_LEFT);
                    }
                    $headResId = (int)($hh['family_head_id'] ?? 0);
                    $headName = '';
                    if ($headResId > 0) {
                        $headRow = $db->fetchOne("SELECT first_name, middle_name, last_name FROM residents WHERE id = ?", [$headResId]);
                        if ($headRow) {
                            $headName = trim(implode(' ', array_filter([$headRow['first_name'] ?? '', $headRow['middle_name'] ?? '', $headRow['last_name'] ?? ''])));
                        }
                    }
                    $householdLabel = $code . ($headName !== '' ? ' - ' . $headName : '');
                }
            }

            $heads[] = [
                'id' => (int)$app['id'],
                'application_ref' => $app['application_ref'] ?? 'APP-' . $app['id'],
                'first_name' => $app['first_name'] ?? '',
                'middle_name' => $app['middle_name'] ?? '',
                'last_name' => $app['last_name'] ?? '',
                'approved_resident_id' => $resId,
                'household_id' => $hhId,
                'household_label' => $householdLabel,
                'head_needs_assignment' => $headNeedsAssignment
            ];
        }

        sendResponse(true, 'Approved heads retrieved', ['heads' => $heads]);
    } catch (Exception $e) {
        error_log('List approved heads: ' . $e->getMessage());
        sendResponse(false, 'Failed to load approved heads', null, 500);
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


function isHeadRoleValue($value) {
    $v = strtolower(trim((string)$value));
    if ($v === '') {
        return false;
    }

    $allowedHeadRoles = [
        'head',
        'head of family',
        'family head',
        'household head',
        'head of household',
        'head of the family',
        'landlord',
    ];

    if (in_array($v, $allowedHeadRoles, true)) {
        return true;
    }
    return strpos($v, 'head') !== false && strlen($v) <= 50;
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
            'proof_of_residency_path' => $app['proof_of_residency_path'] ?? null,
            'address' => $address ?: ($app['barangay'] ?? ''),
            'house_number' => $app['house_number'] ?? null,
            'street' => $app['street'] ?? null,
            'purok_sitio' => $app['purok_sitio'] ?? null,
            'family_code' => $app['family_code'] ?? null,
            'relationship_to_head' => $app['relationship_to_head'] ?? null,
            'contact_number' => $app['mobile_number'] ?? null,
            'email' => $app['email'] ?? null,
            'residency_start_date' => $app['residency_start_date'] ?? null,
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

        // If this application indicates a head of family role, generate a per-resident Family Head Code (FH-XXXXX or LL-XXXXX for Landlord).
        $relationshipRaw = trim((string)($app['relationship_to_head'] ?? ''));
        $householdRoleRaw = trim((string)($app['household_role'] ?? ''));
        $roleRaw = $relationshipRaw !== '' ? $relationshipRaw : $householdRoleRaw;
        $isHead = isHeadRoleValue($roleRaw);
        $isLandlord = strtolower($householdRoleRaw) === 'landlord';
        if ($isHead && columnExists($db, 'residents', 'family_head_code')) {
            $headCode = generateResidentFamilyHeadCode($db, $isLandlord);
            $db->query(
                "UPDATE residents SET family_head_code = ? WHERE id = ?",
                [$headCode, $residentId]
            );
        }

        // NOTE: Household records and member linking are now managed explicitly
        // via the Households module and the "Assign Household" action.
        // Approving an application will no longer auto-create or auto-attach households.

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
        if (isset($cols['approved_resident_id'])) {
            $updates[] = "approved_resident_id=?";
            $updateParams[] = (int)$residentId;
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

function assignHouseholdToApprovedResident() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Method not allowed', null, 405);
    }

    $appId = (int)($_POST['application_id'] ?? 0);
    $residentId = (int)($_POST['resident_id'] ?? 0);
    $householdId = (int)($_POST['household_id'] ?? 0);
    $familyHeadResidentId = intval($_POST['family_head_resident_id'] ?? 0);

    if (!$appId || !$residentId || !$householdId) {
        sendResponse(false, 'application_id, resident_id, and household_id are required', null, 400);
    }

    try {
        $db = Database::getInstance();
        if (!tableExists($db, 'resident_applications')) {
            sendResponse(false, 'Resident applications table is missing.', null, 500);
        }
        if (!tableExists($db, 'residents') || !tableExists($db, 'households')) {
            sendResponse(false, 'Residents/households tables are missing.', null, 500);
        }

        $appCols = array_flip(getTableColumns($db, 'resident_applications'));
        $statusCol = isset($appCols['record_status']) ? 'record_status' : (isset($appCols['status']) ? 'status' : null);
        if (!$statusCol) {
            sendResponse(false, 'Missing status column in resident_applications', null, 500);
        }

        $app = $db->fetchOne("SELECT * FROM resident_applications WHERE id = ?", [$appId]);
        if (!$app) {
            sendResponse(false, 'Application not found', null, 404);
        }
        if (($app[$statusCol] ?? '') !== 'approved') {
            sendResponse(false, 'Only approved applications can be assigned to a household', null, 400);
        }
        if (isset($appCols['approved_resident_id']) && !empty($app['approved_resident_id']) && (int)$app['approved_resident_id'] !== $residentId) {
            sendResponse(false, 'Resident does not match the approved resident for this application', null, 400);
        }

        $resident = $db->fetchOne("SELECT id, household_id FROM residents WHERE id = ? LIMIT 1", [$residentId]);
        if (!$resident) {
            sendResponse(false, 'Resident not found', null, 404);
        }
        $existingHouseholdId = (int)($resident['household_id'] ?? 0);
        if ($existingHouseholdId > 0) {
            if ($existingHouseholdId === $householdId) {
                sendResponse(false, 'Resident is already in this household', null, 400);
            } else {
                sendResponse(false, 'Resident is already assigned to a different household', null, 400);
            }
        }

        $household = $db->fetchOne("SELECT id FROM households WHERE id = ? LIMIT 1", [$householdId]);
        if (!$household) {
            sendResponse(false, 'Household not found', null, 404);
        }

        // Detect role from approved application (head vs member)
        $roleRaw = trim((string)($app['relationship_to_head'] ?? $app['household_role'] ?? ''));
        $isHead = isHeadRoleValue($roleRaw);

        // Link resident into household
        $db->query("UPDATE residents SET household_id = ? WHERE id = ?", [$householdId, $residentId]);

        // If this approved resident is a member, inherit codes from the selected head.
        // This avoids attaching to the wrong head when one household has multiple heads.
        $isMissingCode = function($v) {
            $s = trim((string)$v);
            return $s === '' || $s === '-' || strtolower($s) === 'null' || strtolower($s) === 'n/a';
        };

        if (!$isHead) {
            $selectedHeadRow = null;
            if ($familyHeadResidentId > 0) {
                $selectedHeadRow = $db->fetchOne(
                    "SELECT id, household_id, family_head_code FROM residents WHERE id = ? LIMIT 1",
                    [$familyHeadResidentId]
                );
                if (!$selectedHeadRow) {
                    sendResponse(false, 'Selected family head not found', null, 404);
                }
                if ((int)($selectedHeadRow['household_id'] ?? 0) !== $householdId) {
                    sendResponse(false, 'Selected family head does not belong to this household', null, 400);
                }
            }

            $hasResidentFamilyHeadCode = columnExists($db, 'residents', 'family_head_code');
            $hasResidentFamilyCode = columnExists($db, 'residents', 'family_code');

            if ($hasResidentFamilyHeadCode) {
                $selectedFamilyHeadCode = $selectedHeadRow['family_head_code'] ?? null;

                if ($isMissingCode($selectedFamilyHeadCode) && $familyHeadResidentId > 0) {
                    $selectedFamilyHeadCode = generateResidentFamilyHeadCode($db);
                    $db->query(
                        "UPDATE residents SET family_head_code = ? WHERE id = ?",
                        [$selectedFamilyHeadCode, $familyHeadResidentId]
                    );
                }

                if ($isMissingCode($selectedFamilyHeadCode) && $familyHeadResidentId <= 0) {
                    $hh = $db->fetchOne("SELECT family_head_code FROM households WHERE id = ? LIMIT 1", [$householdId]);
                    $selectedFamilyHeadCode = $hh['family_head_code'] ?? null;
                }

                if ($isMissingCode($selectedFamilyHeadCode) && $familyHeadResidentId <= 0) {
                    $headRow = $db->fetchOne(
                        "SELECT family_head_code FROM residents WHERE household_id = ? AND family_head_code IS NOT NULL AND TRIM(family_head_code) <> '' AND family_head_code <> '-' ORDER BY id DESC LIMIT 1",
                        [$householdId]
                    );
                    $selectedFamilyHeadCode = $headRow['family_head_code'] ?? null;
                }

                $db->query("UPDATE residents SET family_head_code = NULL WHERE id = ?", [$residentId]);

                if (!$isMissingCode($selectedFamilyHeadCode) && columnExists($db, 'households', 'family_head_code')) {
                    $db->query("UPDATE households SET family_head_code = ? WHERE id = ?", [$selectedFamilyHeadCode, $householdId]);
                }


            }

            if (columnExists($db, 'residents', 'relationship_to_head')) {
                $db->query("UPDATE residents SET relationship_to_head = 'Member' WHERE id = ?", [$residentId]);
            }
            if (columnExists($db, 'residents', 'household_role')) {
                $db->query("UPDATE residents SET household_role = 'Member' WHERE id = ?", [$residentId]);
            }

            if ($hasResidentFamilyCode) {
                $selectedFamilyCode = null;
                if ($familyHeadResidentId > 0) {
                    $headFamilyCodeRow = $db->fetchOne(
                        "SELECT family_code FROM residents WHERE id = ? LIMIT 1",
                        [$familyHeadResidentId]
                    );
                    $selectedFamilyCode = $headFamilyCodeRow['family_code'] ?? null;
                    if ($isMissingCode($selectedFamilyCode)) {
                        $selectedFamilyCode = generateUniqueFamilyCode($db);
                        $db->query("UPDATE residents SET family_code = ? WHERE id = ?", [$selectedFamilyCode, $familyHeadResidentId]);
                    }
                }
                if ($isMissingCode($selectedFamilyCode) && $familyHeadResidentId <= 0) {
                    $hh = $db->fetchOne("SELECT family_code FROM households WHERE id = ? LIMIT 1", [$householdId]);
                    $selectedFamilyCode = $hh['family_code'] ?? null;
                }
                if ($isMissingCode($selectedFamilyCode) && $familyHeadResidentId <= 0) {
                    $headRow = $db->fetchOne(
                        "SELECT family_code FROM residents WHERE household_id = ? AND family_code IS NOT NULL AND TRIM(family_code) <> '' AND family_code <> '-' ORDER BY id DESC LIMIT 1",
                        [$householdId]
                    );
                    $selectedFamilyCode = $headRow['family_code'] ?? null;
                }

                if (!$isMissingCode($selectedFamilyCode)) {
                    $db->query("UPDATE residents SET family_code = ? WHERE id = ?", [$selectedFamilyCode, $residentId]);
                }
            }
        }

        // If the approved application is for head of family, set as household head.
        // Only set family_head_id when empty so multiple heads in same household all stay as head.
        if ($isHead) {
            $currentHeadId = $db->fetchOne("SELECT family_head_id FROM households WHERE id = ? LIMIT 1", [$householdId]);
            $currentHeadIdVal = (int)($currentHeadId['family_head_id'] ?? 0);
            if ($currentHeadIdVal <= 0) {
                $db->query("UPDATE households SET family_head_id = ? WHERE id = ?", [$residentId, $householdId]);
            }
            if (columnExists($db, 'residents', 'relationship_to_head')) {
                $db->query("UPDATE residents SET relationship_to_head = 'Head' WHERE id = ?", [$residentId]);
            }
            $appHouseholdRole = strtolower(trim((string)($app['household_role'] ?? '')));
            $isLandlord = $appHouseholdRole === 'landlord';
            if (columnExists($db, 'residents', 'household_role')) {
                $roleValue = $isLandlord ? 'Landlord' : 'Head';
                $db->query("UPDATE residents SET household_role = ? WHERE id = ?", [$roleValue, $residentId]);
            }
            // Generate head/household codes if missing (schema tolerant).
            $householdCols = array_flip(getTableColumns($db, 'households'));
            if (isset($householdCols['household_id_code'])) {
                $db->query(
                    "UPDATE households
                     SET household_id_code = CASE
                         WHEN household_id_code IS NULL OR household_id_code = '' THEN ?
                         ELSE household_id_code
                     END
                     WHERE id = ?",
                    [generateUniqueHouseholdCode($db), $householdId]
                );
            }
            if (isset($householdCols['family_head_code'])) {
                $residentHeadCode = null;
                if (columnExists($db, 'residents', 'family_head_code')) {
                    $headRow = $db->fetchOne("SELECT family_head_code FROM residents WHERE id = ? LIMIT 1", [$residentId]);
                    $residentHeadCode = $headRow['family_head_code'] ?? null;
                    if ($isMissingCode($residentHeadCode)) {
                        $residentHeadCode = generateResidentFamilyHeadCode($db, $isLandlord);
                        $db->query("UPDATE residents SET family_head_code = ? WHERE id = ?", [$residentHeadCode, $residentId]);
                    }
                }
                if ($currentHeadIdVal <= 0) {
                    $fallbackCode = generateUniqueFamilyHeadCode($db, $isLandlord);
                    $db->query(
                        "UPDATE households SET family_head_code = ? WHERE id = ?",
                        [!$isMissingCode($residentHeadCode) ? $residentHeadCode : $fallbackCode, $householdId]
                    );
                }
            }
            // Set household_type from the family head's registration (application) when occupied.
            $appHouseholdType = trim((string)($app['household_type'] ?? ''));
            if ($appHouseholdType !== '' && isset($householdCols['household_type'])) {
                $db->query(
                    "UPDATE households SET household_type = ? WHERE id = ?",
                    [$appHouseholdType, $householdId]
                );
            }

            if (isset($householdCols['family_code'])) {
                $fc = generateUniqueFamilyCode($db);
                $db->query(
                    "UPDATE households
                     SET family_code = CASE
                         WHEN family_code IS NULL OR family_code = '' THEN ?
                         ELSE family_code
                     END
                     WHERE id = ?",
                    [$fc, $householdId]
                );
                // Sync head resident's family_code so members assigned later group correctly.
                if (columnExists($db, 'residents', 'family_code')) {
                    $hh = $db->fetchOne("SELECT family_code FROM households WHERE id = ? LIMIT 1", [$householdId]);
                    if ($hh && !empty(trim((string)($hh['family_code'] ?? '')))) {
                        $db->query("UPDATE residents SET family_code = ? WHERE id = ?", [$hh['family_code'], $residentId]);
                    }
                }
            }

            // Auto-fill household address/registration when missing from head resident.
            $current = $db->fetchOne("SELECT address, registration_date FROM households WHERE id = ?", [$householdId]);
            $needsAddress = $current && empty(trim((string)($current['address'] ?? '')));
            $needsRegDate = $current && empty($current['registration_date']);
            if ($needsAddress || $needsRegDate) {
                $headRow = $db->fetchOne(
                    "SELECT address, house_number, street, purok_sitio FROM residents WHERE id = ? LIMIT 1",
                    [$residentId]
                );
                $headAddress = '';
                if ($headRow) {
                    $parts = array_filter([
                        $headRow['house_number'] ?? null,
                        $headRow['street'] ?? null,
                        $headRow['purok_sitio'] ?? null
                    ], function($v) { return trim((string)$v) !== ''; });
                    $headAddress = trim((string)($headRow['address'] ?? ''));
                    if ($headAddress === '' && !empty($parts)) {
                        $headAddress = implode(', ', $parts);
                    }
                }

                $db->query(
                    "UPDATE households
                     SET address = CASE WHEN (address IS NULL OR address = '') THEN ? ELSE address END,
                         registration_date = CASE WHEN registration_date IS NULL OR registration_date = '' THEN ? ELSE registration_date END
                     WHERE id = ?",
                    [
                        $needsAddress ? ($headAddress !== '' ? $headAddress : null) : ($current['address'] ?? null),
                        $needsRegDate ? date('Y-m-d') : ($current['registration_date'] ?? null),
                        $householdId
                    ]
                );
            }
        }

        // Keep household member count aligned
        $countRow = $db->fetchOne("SELECT COUNT(*) as c FROM residents WHERE household_id = ?", [$householdId]);
        if ($countRow && isset($countRow['c'])) {
            $db->query("UPDATE households SET total_members = ? WHERE id = ?", [(int)$countRow['c'], $householdId]);
        }

        sendResponse(true, 'Household assigned successfully', [
            'resident_id' => $residentId,
            'household_id' => $householdId,
            'is_head' => $isHead
        ]);
    } catch (Exception $e) {
        error_log('Assign household: ' . $e->getMessage());
        sendResponse(false, 'Assign household failed: ' . $e->getMessage(), null, 500);
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

/**
 * Resolve the resident row for an approved application when approved_resident_id
 * is missing or schema does not include it.
 */
function resolveResidentForApplication() {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if (!$id) {
        sendResponse(false, 'Application ID required', null, 400);
    }

    try {
        $db = Database::getInstance();
        if (!tableExists($db, 'resident_applications')) {
            sendResponse(false, 'Resident applications table is missing.', null, 500);
        }
        if (!tableExists($db, 'residents')) {
            sendResponse(false, 'Residents table is missing.', null, 500);
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
            sendResponse(false, 'Resident can only be resolved for approved applications.', null, 400);
        }

        // If schema already has approved_resident_id, prefer that.
        if (isset($appCols['approved_resident_id']) && !empty($app['approved_resident_id'])) {
            sendResponse(true, 'Resident resolved.', [
                'resident_id' => (int)$app['approved_resident_id']
            ]);
        }

        // Otherwise, resolve by strong identity: name + birthdate + (email or phone).
        $first = $app['first_name'] ?? '';
        $last = $app['last_name'] ?? '';
        $birth = $app['birth_date'] ?? null;
        $email = $app['email'] ?? '';
        $mobile = $app['mobile_number'] ?? '';

        if ($first === '' || $last === '' || !$birth) {
            sendResponse(false, 'Insufficient identity details to resolve resident.', null, 400);
        }

        $params = [$first, $last, $birth];
        $whereExtra = '';
        if ($email !== '' || $mobile !== '') {
            $whereExtra = " AND (
                (? <> '' AND r.email = ?)
                OR
                (? <> '' AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(r.contact_number, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') = ?)
            )";
            $params[] = $email;
            $params[] = $email;
            $params[] = $mobile;
            $params[] = preg_replace('/\D/', '', (string)$mobile);
        }

        $sql = "SELECT r.id
                FROM residents r
                WHERE r.first_name = ?
                  AND r.last_name = ?
                  AND r.birth_date = ?"
               . $whereExtra .
               " ORDER BY r.id DESC
                 LIMIT 1";

        $resident = $db->fetchOne($sql, $params);
        if (!$resident) {
            sendResponse(false, 'Could not find resident record for this application.', null, 404);
        }

        $residentId = (int)$resident['id'];

        // If schema has approved_resident_id, persist mapping for future use.
        if (isset($appCols['approved_resident_id'])) {
            $db->query(
                "UPDATE resident_applications SET approved_resident_id = ? WHERE id = ?",
                [$residentId, $id]
            );
        }

        sendResponse(true, 'Resident resolved.', [
            'resident_id' => $residentId
        ]);
    } catch (Exception $e) {
        error_log('Resolve resident for application: ' . $e->getMessage());
        sendResponse(false, 'Failed to resolve resident for application', null, 500);
    }
}















