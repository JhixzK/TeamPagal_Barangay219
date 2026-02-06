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

// Upload directory (outside web root preferred; adjust as needed)
$UPLOAD_DIR = dirname(__DIR__) . '/uploads/applications/';
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

// Validate required fields
$first_name = sanitize($_POST['first_name'] ?? '');
$last_name = sanitize($_POST['last_name'] ?? '');
$sex = strtolower(sanitize($_POST['sex'] ?? ''));
$birth_date = $_POST['birth_date'] ?? '';
$civil_status = strtolower(sanitize($_POST['civil_status'] ?? ''));
$citizenship = sanitize($_POST['citizenship'] ?? 'Filipino');
$mobile_number = preg_replace('/\D/', '', sanitize($_POST['mobile_number'] ?? ''));
$emergency_contact_name = sanitize($_POST['emergency_contact_name'] ?? '');
$emergency_contact_number = preg_replace('/\D/', '', sanitize($_POST['emergency_contact_number'] ?? ''));
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
if (!$emergency_contact_name) $errors[] = 'Emergency contact name is required.';
if (!$emergency_contact_number || strlen($emergency_contact_number) < 10) $errors[] = 'Emergency contact number is required.';
if (!$emergency_contact_relationship) $errors[] = 'Emergency contact relationship is required.';
if (!in_array($valid_id_type, $allowed_id_types)) $errors[] = 'Valid ID type is required.';
if (!$valid_id_number || strlen($valid_id_number) > 100) $errors[] = 'Valid ID number is required.';
if (!$data_privacy) $errors[] = 'Data Privacy Act consent is required.';

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
        $id_document_path = 'applications/' . date('Ymd') . '_' . uniqid() . '_id.' . $ext;
        $dest = dirname(__DIR__) . '/uploads/' . $id_document_path;
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
        $proof_of_residency_path = 'applications/' . date('Ymd') . '_' . uniqid() . '_res.' . $ext;
        $dest = dirname(__DIR__) . '/uploads/' . $proof_of_residency_path;
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
$relationship_to_head = sanitize($_POST['relationship_to_head'] ?? '');
$house_number = sanitize($_POST['house_number'] ?? '');
$street = sanitize($_POST['street'] ?? '');
$purok_sitio = sanitize($_POST['purok_sitio'] ?? '');
$length_of_residency_years = isset($_POST['length_of_residency_years']) ? (int)$_POST['length_of_residency_years'] : null;
$email = sanitize($_POST['email'] ?? '');
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

// Senior citizen auto-validation (60+)
$age = (int)date('Y') - (int)date('Y', strtotime($birth_date));
$is_senior = $is_senior || $age >= 60;

// Generate application ref: APP-YYYYMMDD-NNNN
$prefix = 'APP-' . date('Ymd') . '-';
$db = Database::getInstance();
$last = $db->fetchOne("SELECT application_ref FROM resident_applications WHERE application_ref LIKE ? ORDER BY id DESC LIMIT 1", [$prefix . '%']);
$seq = 1;
if ($last) {
    $parts = explode('-', $last['application_ref']);
    $seq = (int)($parts[2] ?? 0) + 1;
}
$application_ref = $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);

try {
    $sql = "INSERT INTO resident_applications (
        application_ref, first_name, middle_name, last_name, suffix, sex, birth_date, place_of_birth,
        civil_status, citizenship, family_code, relationship_to_head,
        house_number, street, purok_sitio, barangay, city, province, length_of_residency_years,
        mobile_number, email, emergency_contact_name, emergency_contact_number, emergency_contact_relationship,
        educational_attainment, employment_status, occupation,
        is_senior_citizen, is_pwd, pwd_id_number, is_solo_parent, solo_parent_id_number,
        is_ip_member, ip_group, is_4ps_beneficiary,
        valid_id_type, valid_id_number, id_document_path, proof_of_residency_path, data_privacy_consent,
        record_status
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,'pending')";

    $params = [
        $application_ref, $first_name, $middle_name ?: null, $last_name, $suffix ?: null,
        $sex, $birth_date, $place_of_birth ?: null, $civil_status ?: null, $citizenship,
        $family_code ?: null, $relationship_to_head ?: null,
        $house_number ?: null, $street ?: null, $purok_sitio ?: null, $barangay, $city, $province,
        $length_of_residency_years ?: null,
        $mobile_number, $email ?: null, $emergency_contact_name, $emergency_contact_number, $emergency_contact_relationship,
        $educational_attainment ?: null, $employment_status ?: null, $occupation ?: null,
        $is_senior ? 1 : 0, $is_pwd ? 1 : 0, $pwd_id_number ?: null, $is_solo_parent ? 1 : 0, $solo_parent_id_number ?: null,
        $is_ip ? 1 : 0, $ip_group ?: null, $is_4ps ? 1 : 0,
        $valid_id_type, $valid_id_number, $id_document_path, $proof_of_residency_path
    ];

    $db->query($sql, $params);
    $appId = $db->lastInsertId();

    // Audit log
    $db->query(
        "INSERT INTO application_audit_log (application_id, action, details) VALUES (?, 'submitted', ?)",
        [$appId, json_encode(['application_ref' => $application_ref])]
    );

    sendJson(true, 'Application submitted successfully. Your application will be reviewed by the barangay. You will be notified once approved.', [
        'application_ref' => $application_ref,
        'application_id' => (int)$appId,
        'message' => 'No username or password is needed. After approval, you will receive your Resident ID and instructions to activate your account.'
    ], 201);

} catch (Exception $e) {
    error_log('Registration API error: ' . $e->getMessage());
    sendJson(false, 'Registration failed. Please try again or contact the barangay office.', null, 500);
}
