<?php
/**
 * E-Barangay - Public Resident Registration API
 * No authentication required. Creates PENDING application only.
 * Resident ID and password are created AFTER barangay approval.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(false, 'Method not allowed', null, 405);
}

// Upload directory (public so files are browser-accessible in resident-applications view)
$UPLOAD_DIR = PUBLIC_PATH . '/uploads/applications/';
if (!is_dir($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0755, true);
}

$allowed_id_types = ['psa_birth', 'passport', 'drivers_license', 'umid', 'postal_id', 'sss_id', 'prc_id', 'voters_id', 'national_id', 'other'];
$allowed_ext = ['pdf', 'jpg', 'jpeg', 'png'];
$max_file_size = 5 * 1024 * 1024; // 5MB

// Helper
function sendJson($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

function sanitize($s) {
    return htmlspecialchars(strip_tags(trim($s ?? '')), ENT_QUOTES, 'UTF-8');
}

function normalizeIdentifier($value) {
    return strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$value));
}

function normalizePhoneNumber($value) {
    return preg_replace('/\D/', '', (string)$value);
}

function normalizeEmailAddress($value) {
    return strtolower(trim((string)$value));
}

function findDuplicateApplication($db, $first_name, $last_name, $birth_date, $mobile_number, $email, $valid_id_type, $valid_id_number) {
    $normalizedId = normalizeIdentifier($valid_id_number);

    if ($normalizedId !== '') {
        $byId = $db->fetchOne(
            "SELECT id, application_ref, record_status, created_at
             FROM resident_applications
             WHERE valid_id_type = ?
               AND LOWER(REPLACE(REPLACE(valid_id_number, ' ', ''), '-', '')) = ?
               AND record_status IN ('pending', 'approved')
             ORDER BY id DESC
             LIMIT 1",
            [$valid_id_type, $normalizedId]
        );
        if (!empty($byId)) {
            $byId['match_type'] = 'valid_id';
            return $byId;
        }
    }

    $byIdentity = $db->fetchOne(
        "SELECT id, application_ref, record_status, created_at
         FROM resident_applications
         WHERE LOWER(first_name) = LOWER(?)
           AND LOWER(last_name) = LOWER(?)
           AND birth_date = ?
           AND record_status IN ('pending', 'approved')
         ORDER BY id DESC
         LIMIT 1",
        [$first_name, $last_name, $birth_date]
    );
    if (!empty($byIdentity)) {
        $byIdentity['match_type'] = 'name_birthdate';
        return $byIdentity;
    }

    if ($mobile_number !== '') {
        $byPhone = $db->fetchOne(
            "SELECT id, application_ref, record_status, created_at
             FROM resident_applications
             WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(mobile_number, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') = ?
               AND record_status IN ('pending', 'approved')
             ORDER BY id DESC
             LIMIT 1",
            [$mobile_number]
        );
        if (!empty($byPhone)) {
            $byPhone['match_type'] = 'phone_number';
            return $byPhone;
        }
    }

    if ($email !== '') {
        $byEmail = $db->fetchOne(
            "SELECT id, application_ref, record_status, created_at
             FROM resident_applications
             WHERE LOWER(email) = LOWER(?)
               AND record_status IN ('pending', 'approved')
             ORDER BY id DESC
             LIMIT 1",
            [$email]
        );
        if (!empty($byEmail)) {
            $byEmail['match_type'] = 'email_address';
            return $byEmail;
        }
    }

    return null;
}

function findExistingResidentOrAccount($db, $resident_identifier, $first_name, $last_name, $birth_date, $mobile_number, $email) {
    if (tableExists($db, 'residents')) {
        if ($resident_identifier !== '') {
            $byResidentCode = $db->fetchOne(
                "SELECT id, resident_code, first_name, last_name, birth_date, contact_number, email
                 FROM residents
                 WHERE resident_code = ?
                 LIMIT 1",
                [$resident_identifier]
            );
            if (!empty($byResidentCode)) {
                return ['match_type' => 'resident_id', 'record' => $byResidentCode];
            }
        }

        $byIdentity = $db->fetchOne(
            "SELECT id, resident_code, first_name, last_name, birth_date, contact_number, email
             FROM residents
             WHERE LOWER(first_name) = LOWER(?)
               AND LOWER(last_name) = LOWER(?)
               AND birth_date = ?
             LIMIT 1",
            [$first_name, $last_name, $birth_date]
        );
        if (!empty($byIdentity)) {
            return ['match_type' => 'name_birthdate', 'record' => $byIdentity];
        }

        if ($mobile_number !== '') {
            $byPhone = $db->fetchOne(
                "SELECT id, resident_code, first_name, last_name, birth_date, contact_number, email
                 FROM residents
                 WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(contact_number, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') = ?
                 LIMIT 1",
                [$mobile_number]
            );
            if (!empty($byPhone)) {
                return ['match_type' => 'phone_number', 'record' => $byPhone];
            }
        }

        if ($email !== '') {
            $byResidentEmail = $db->fetchOne(
                "SELECT id, resident_code, first_name, last_name, birth_date, contact_number, email
                 FROM residents
                 WHERE LOWER(email) = LOWER(?)
                 LIMIT 1",
                [$email]
            );
            if (!empty($byResidentEmail)) {
                return ['match_type' => 'email_address', 'record' => $byResidentEmail];
            }
        }
    }

    if ($email !== '' && tableExists($db, 'users')) {
        $byUserEmail = $db->fetchOne(
            "SELECT id, username, resident_id, status
             FROM users
             WHERE LOWER(email) = LOWER(?)
             LIMIT 1",
            [$email]
        );
        if (!empty($byUserEmail)) {
            return ['match_type' => 'email_address', 'record' => $byUserEmail];
        }
    }

    return null;
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

function addColumnIfMissing($db, $table, $column, $definition) {
    $row = $db->fetchOne(
        "SELECT COUNT(*) AS cnt
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
        [$table, $column]
    );
    if (empty($row) || (int)$row['cnt'] === 0) {
        $db->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

// Validate required fields
$first_name = sanitize($_POST['first_name'] ?? '');
$last_name = sanitize($_POST['last_name'] ?? '');
$sex = strtolower(sanitize($_POST['sex'] ?? ''));
$birth_date = $_POST['birth_date'] ?? '';
$civil_status = strtolower(sanitize($_POST['civil_status'] ?? ''));
$citizenship = sanitize($_POST['citizenship'] ?? 'Filipino');
$mobile_number = normalizePhoneNumber($_POST['mobile_number'] ?? '');
$email = normalizeEmailAddress($_POST['email'] ?? '');
$resident_identifier = sanitize($_POST['resident_id'] ?? ($_POST['resident_code'] ?? ''));
$emergency_contact_name = sanitize($_POST['emergency_contact_name'] ?? '');
$emergency_contact_number = normalizePhoneNumber($_POST['emergency_contact_number'] ?? '');
$emergency_contact_relationship = sanitize($_POST['emergency_contact_relationship'] ?? '');
$valid_id_type = sanitize($_POST['valid_id_type'] ?? '');
$valid_id_number = sanitize($_POST['valid_id_number'] ?? '');
$data_privacy = isset($_POST['data_privacy_consent']) && $_POST['data_privacy_consent'] === '1';

$errors = [];

if (!$first_name || strlen($first_name) > 100) $errors[] = 'First name is required (max 100 chars).';
if (!$last_name || strlen($last_name) > 100) $errors[] = 'Last name is required (max 100 chars).';
if (!in_array($sex, ['male', 'female', 'other'])) $errors[] = 'Valid sex is required.';
if (!$birth_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) $errors[] = 'Valid date of birth is required.';
if (strtotime($birth_date) > time()) $errors[] = 'Date of birth cannot be in the future.';
if (!strlen($mobile_number) || strlen($mobile_number) < 10) $errors[] = 'Valid mobile number is required.';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email address is required.';
if (!$emergency_contact_name) $errors[] = 'Emergency contact name is required.';
if (!$emergency_contact_number || strlen($emergency_contact_number) < 10) $errors[] = 'Emergency contact number is required.';
if (!$emergency_contact_relationship) $errors[] = 'Emergency contact relationship is required.';
if (!in_array($valid_id_type, $allowed_id_types)) $errors[] = 'Valid ID type is required.';
if (!$valid_id_number || strlen($valid_id_number) > 100) $errors[] = 'Valid ID number is required.';
if (!$data_privacy) $errors[] = 'Data Privacy Act consent is required.';

$lockName = null;
$lockAcquired = false;
$db = null;

try {
    $db = Database::getInstance();
    if (!tableExists($db, 'resident_applications')) {
        sendJson(false, 'Registration module is not ready. Please run database/migrations/001_resident_registration_workflow.sql', null, 500);
    }

    $lockName = 'regdup_' . substr(sha1(strtolower($valid_id_type . '|' . normalizeIdentifier($valid_id_number))), 0, 40);
    $lockRow = $db->fetchOne("SELECT GET_LOCK(?, 10) AS lck", [$lockName]);
    $lockAcquired = !empty($lockRow) && (int)$lockRow['lck'] === 1;

    if (!$lockAcquired) {
        sendJson(false, 'A similar registration is currently being processed. Please wait a moment and try again.', null, 429);
    }

    $duplicate = findDuplicateApplication($db, $first_name, $last_name, $birth_date, $mobile_number, $email, $valid_id_type, $valid_id_number);
    if (!empty($duplicate)) {
        $matchType = $duplicate['match_type'] ?? 'identity';
        $matchLabels = [
            'valid_id' => 'valid ID',
            'name_birthdate' => 'full name and birthdate',
            'phone_number' => 'phone number',
            'email_address' => 'email address',
            'identity' => 'identity details'
        ];
        $matchLabel = $matchLabels[$matchType] ?? $matchLabels['identity'];

        sendJson(false, 'Registration not accepted. A pending/approved application with the same ' . $matchLabel . ' already exists.', [
            'match_type' => $matchType,
            'existing_application_ref' => $duplicate['application_ref'] ?? null,
            'existing_status' => $duplicate['record_status'] ?? null,
            'existing_created_at' => $duplicate['created_at'] ?? null
        ], 409);
    }

    $existingResident = findExistingResidentOrAccount($db, $resident_identifier, $first_name, $last_name, $birth_date, $mobile_number, $email);
    if (!empty($existingResident)) {
        $matchType = $existingResident['match_type'] ?? 'identity';
        $matchLabels = [
            'resident_id' => 'Resident ID',
            'name_birthdate' => 'full name and birthdate',
            'phone_number' => 'phone number',
            'email_address' => 'email address',
            'identity' => 'identity details'
        ];
        $matchLabel = $matchLabels[$matchType] ?? $matchLabels['identity'];

        sendJson(false, 'Registration not accepted. This resident already has an account record (matched by ' . $matchLabel . ').', [
            'match_type' => $matchType
        ], 409);
    }
} catch (Exception $e) {
    error_log('Registration duplicate check error: ' . $e->getMessage());
    sendJson(false, 'Unable to validate duplicate submission. Please try again.', null, 500);
}

// Barangay/City/Province - fixed for Barangay 219
$barangay = 'Barangay 219, Tondo';
$city = 'Manila';
$province = 'Metro Manila';

// File uploads
$id_document_path = null;
$proof_of_residency_path = null;

if (isset($_FILES['id_document']) && $_FILES['id_document']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['id_document']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext) || $_FILES['id_document']['size'] > $max_file_size) {
        $errors[] = 'Valid ID document: PDF, JPG, PNG, max 5MB.';
    } else {
        $filename = date('Ymd') . '_' . uniqid() . '_id.' . $ext;
        $id_document_path = 'uploads/applications/' . $filename;
        $dest = $UPLOAD_DIR . $filename;
        if (!move_uploaded_file($_FILES['id_document']['tmp_name'], $dest)) {
            $errors[] = 'Failed to save ID document.';
            $id_document_path = null;
        }
    }
} else {
    $errors[] = 'Valid ID document upload is required.';
}

if (isset($_FILES['proof_of_residency']) && $_FILES['proof_of_residency']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['proof_of_residency']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext) || $_FILES['proof_of_residency']['size'] > $max_file_size) {
        $errors[] = 'Proof of residency: PDF, JPG, PNG, max 5MB.';
    } else {
        $filename = date('Ymd') . '_' . uniqid() . '_res.' . $ext;
        $proof_of_residency_path = 'uploads/applications/' . $filename;
        $dest = $UPLOAD_DIR . $filename;
        if (!move_uploaded_file($_FILES['proof_of_residency']['tmp_name'], $dest)) {
            $errors[] = 'Failed to save proof of residency.';
            $proof_of_residency_path = null;
        }
    }
} else {
    $errors[] = 'Proof of residency upload is required.';
}

if (!empty($errors)) {
    sendJson(false, implode(' ', $errors), ['errors' => $errors], 400);
}

// Build application data
$middle_name = sanitize($_POST['middle_name'] ?? '');
$suffix = sanitize($_POST['suffix'] ?? '');
$place_of_birth = sanitize($_POST['place_of_birth'] ?? '');
$family_code = sanitize($_POST['family_code'] ?? '');
$household_role = sanitize($_POST['household_role'] ?? '');
$household_type = sanitize($_POST['household_type'] ?? '');
$relationship_to_head = sanitize($_POST['relationship_to_head'] ?? ($household_role ?? ''));
$household_members = isset($_POST['household_members']) ? (int)$_POST['household_members'] : null;
$house_number = sanitize($_POST['house_number'] ?? '');
$street = sanitize($_POST['street'] ?? '');
$purok_sitio = sanitize($_POST['purok_sitio'] ?? '');
$length_of_residency_years = isset($_POST['length_of_residency_years']) ? (int)$_POST['length_of_residency_years'] : null;
$educational_attainment = sanitize($_POST['educational_attainment'] ?? '');
$employment_status = sanitize($_POST['employment_status'] ?? '');
$occupation = sanitize($_POST['occupation'] ?? '');
$is_senior = isset($_POST['is_senior_citizen']) && $_POST['is_senior_citizen'] === '1';
$is_pwd = isset($_POST['is_pwd']) && $_POST['is_pwd'] === '1';
$pwd_id_number = $is_pwd ? sanitize($_POST['pwd_id_number'] ?? '') : null;
$is_solo_parent = isset($_POST['is_solo_parent']) && $_POST['is_solo_parent'] === '1';
$solo_parent_id_number = $is_solo_parent ? sanitize($_POST['solo_parent_id_number'] ?? '') : null;
$is_ip = isset($_POST['is_ip_member']) && $_POST['is_ip_member'] === '1';
$ip_group = $is_ip ? sanitize($_POST['ip_group'] ?? '') : null;
$is_4ps = isset($_POST['is_4ps_beneficiary']) && $_POST['is_4ps_beneficiary'] === '1';

$allowed_household_types = [
    'Nuclear Family',
    'Extended Family',
    'Single Parent Household',
    'Couple Only',
    'Single Person Household',
    'Non-Relative Household',
    'Other (Specify)'
];

if ($household_role === 'Head of Household') {
    if ($household_type === '') {
        $errors[] = 'Household type is required when household role is Head of Household.';
    } elseif (!in_array($household_type, $allowed_household_types, true)) {
        $errors[] = 'Invalid household type selected.';
    }
}

// Senior citizen auto-validation (60+)
$age = (int)date('Y') - (int)date('Y', strtotime($birth_date));
$is_senior = $is_senior || $age >= 60;

try {
    // Generate application ref: APP-YYYYMMDD-NNNN
    $prefix = 'APP-' . date('Ymd') . '-';
    $last = $db->fetchOne("SELECT application_ref FROM resident_applications WHERE application_ref LIKE ? ORDER BY id DESC LIMIT 1", [$prefix . '%']);
    $seq = 1;
    if ($last) {
        $parts = explode('-', $last['application_ref']);
        $seq = (int)($parts[2] ?? 0) + 1;
    }
    $application_ref = $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);

    // Ensure newer columns exist for household role tracking (safe no-op if already present)
    addColumnIfMissing($db, 'resident_applications', 'relationship_to_head', "VARCHAR(50) DEFAULT NULL");
    addColumnIfMissing($db, 'resident_applications', 'household_role', "VARCHAR(80) DEFAULT NULL");
    addColumnIfMissing($db, 'resident_applications', 'household_type', "VARCHAR(80) DEFAULT NULL");
    addColumnIfMissing($db, 'resident_applications', 'household_members', "INT(11) DEFAULT NULL");

    $existingCols = array_flip(getTableColumns($db, 'resident_applications'));

    $db->beginTransaction();

    $insertData = [
        'application_ref' => $application_ref,
        'first_name' => $first_name,
        'middle_name' => $middle_name ?: null,
        'last_name' => $last_name,
        'suffix' => $suffix ?: null,
        'sex' => $sex,
        'birth_date' => $birth_date,
        'place_of_birth' => $place_of_birth ?: null,
        'civil_status' => $civil_status ?: null,
        'citizenship' => $citizenship,
        'family_code' => $family_code ?: null,
        'relationship_to_head' => $relationship_to_head ?: null,
        'household_role' => ($household_role ?: $relationship_to_head) ?: null,
        'household_type' => $household_type ?: null,
        'household_members' => ($household_members !== null && $household_members > 0) ? $household_members : null,
        'house_number' => $house_number ?: null,
        'street' => $street ?: null,
        'purok_sitio' => $purok_sitio ?: null,
        'barangay' => $barangay,
        'city' => $city,
        'province' => $province,
        'length_of_residency_years' => $length_of_residency_years ?: null,
        'mobile_number' => $mobile_number,
        'email' => $email ?: null,
        'emergency_contact_name' => $emergency_contact_name,
        'emergency_contact_number' => $emergency_contact_number,
        'emergency_contact_relationship' => $emergency_contact_relationship,
        'educational_attainment' => $educational_attainment ?: null,
        'employment_status' => $employment_status ?: null,
        'occupation' => $occupation ?: null,
        'is_senior_citizen' => $is_senior ? 1 : 0,
        'is_pwd' => $is_pwd ? 1 : 0,
        'pwd_id_number' => $pwd_id_number ?: null,
        'is_solo_parent' => $is_solo_parent ? 1 : 0,
        'solo_parent_id_number' => $solo_parent_id_number ?: null,
        'is_ip_member' => $is_ip ? 1 : 0,
        'ip_group' => $ip_group ?: null,
        'is_4ps_beneficiary' => $is_4ps ? 1 : 0,
        'valid_id_type' => $valid_id_type,
        'valid_id_number' => $valid_id_number,
        'id_document_path' => $id_document_path,
        'proof_of_residency_path' => $proof_of_residency_path,
        'data_privacy_consent' => 1,
        'record_status' => 'pending'
    ];

    $requiredColumns = ['application_ref', 'first_name', 'last_name', 'sex', 'birth_date', 'mobile_number'];
    foreach ($requiredColumns as $col) {
        if (!isset($existingCols[$col])) {
            throw new Exception("Missing required column in resident_applications: " . $col);
        }
    }

    $cols = [];
    $params = [];
    foreach ($insertData as $col => $val) {
        if (isset($existingCols[$col])) {
            $cols[] = $col;
            $params[] = $val;
        }
    }

    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $colSql = '`' . implode('`,`', $cols) . '`';
    $sql = "INSERT INTO resident_applications ($colSql) VALUES ($placeholders)";
    $db->query($sql, $params);
    $appId = $db->lastInsertId();

    // Audit log (optional, only if table exists)
    if (tableExists($db, 'application_audit_log')) {
        $db->query(
            "INSERT INTO application_audit_log (application_id, action, details) VALUES (?, 'submitted', ?)",
            [$appId, json_encode(['application_ref' => $application_ref])]
        );
    }

    $db->commit();

    sendJson(true, 'Application submitted successfully. Your application will be reviewed by the barangay. You will be notified once approved.', [
        'application_ref' => $application_ref,
        'application_id' => (int)$appId,
        'message' => 'No username or password is needed. After approval, you will receive your Resident ID and instructions to activate your account.'
    ], 201);

} catch (Exception $e) {
    if (isset($db) && method_exists($db, 'rollback')) {
        try { $db->rollback(); } catch (Exception $ignored) {}
    }
    error_log('Registration API error: ' . $e->getMessage());
    $message = 'Registration failed. Please try again or contact the barangay office.';
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $message .= ' [' . $e->getMessage() . ']';
    }
    sendJson(false, $message, null, 500);
} finally {
    if ($lockAcquired && $db && $lockName) {
        try {
            $db->query("SELECT RELEASE_LOCK(?)", [$lockName]);
        } catch (Exception $ignored) {
            // Lock auto-releases at connection end.
        }
    }
}
