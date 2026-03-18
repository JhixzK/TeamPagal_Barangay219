<?php
header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'update':
        updateProfile();
        break;
    case 'upload':
        uploadAvatar();
        break;
    default:
        sendResponse(false, 'Invalid action', null, 400);
        break;
}

function sendResponse($success = false, $message = '', $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

function uploadAvatar() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'POST required', null, 405);
    }

    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        sendResponse(false, 'No file uploaded or upload error', null, 400);
    }

    $userId = getCurrentUserId();
    if (!$userId) {
        sendResponse(false, 'Not authenticated', null, 401);
    }

    // CSRF token check if provided
    $token = $_POST[CSRF_TOKEN_NAME] ?? null;
    if ($token && !verifyCSRFToken($token)) {
        sendResponse(false, 'Invalid CSRF token', null, 403);
    }

    $file = $_FILES['avatar'];
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        sendResponse(false, 'File too large', null, 400);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ALLOWED_IMAGE_TYPES)) {
        sendResponse(false, 'Invalid file type', null, 400);
    }

    // Determine extension
    $ext = '';
    switch ($mime) {
        case 'image/png': $ext = 'png'; break;
        case 'image/gif': $ext = 'gif'; break;
        default: $ext = 'jpg'; break;
    }

    $uploadDir = PUBLIC_PATH . '/uploads/profile';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $target = $uploadDir . '/' . $userId . '.' . $ext;

    // Remove other existing extensions for this user
    foreach (['png','jpg','jpeg','gif'] as $e) {
        $old = $uploadDir . '/' . $userId . '.' . $e;
        if (file_exists($old) && $old !== $target) {
            @unlink($old);
        }
    }

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        sendResponse(false, 'Failed to save file', null, 500);
    }

    $publicUrl = BASE_URL . 'uploads/profile/' . $userId . '.' . $ext;

    sendResponse(true, 'Avatar uploaded', ['url' => $publicUrl]);
}

function updateProfile() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'POST required', null, 405);
    }

    $userId = getCurrentUserId();
    if (!$userId) {
        sendResponse(false, 'Not authenticated', null, 401);
    }

    $db = Database::getInstance();

    $residentId = $_SESSION['resident_id'] ?? null;
    if (!$residentId) {
        $row = $db->fetchOne('SELECT resident_id FROM users WHERE id = ?', [$userId]);
        $residentId = $row['resident_id'] ?? null;
    }

    if (!$residentId) {
        sendResponse(false, 'No resident record linked to this account.', null, 400);
    }

    $residentColsRows = $db->fetchAll('SHOW COLUMNS FROM residents');
    $userColsRows = $db->fetchAll('SHOW COLUMNS FROM users');
    $residentCols = array_map(static function ($r) { return $r['Field']; }, $residentColsRows);
    $userCols = array_map(static function ($r) { return $r['Field']; }, $userColsRows);

    $allowedInput = [
        'first_name', 'middle_name', 'last_name', 'suffix',
        'date_of_birth', 'birth_date', 'place_of_birth',
        'gender', 'civil_status',
        'mobile_number', 'contact_number',
        'email',
        'house_no', 'house_number', 'street', 'address',
        'residency_start_date',
        'length_of_residency_years',
        'occupation', 'employment_status',
        'emergency_contact_name', 'emergency_contact_number'
    ];

    $aliases = [
        'first_name' => ['first_name'],
        'middle_name' => ['middle_name'],
        'last_name' => ['last_name'],
        'suffix' => ['suffix'],
        'date_of_birth' => ['date_of_birth', 'birth_date'],
        'birth_date' => ['birth_date', 'date_of_birth'],
        'place_of_birth' => ['place_of_birth'],
        'gender' => ['gender'],
        'civil_status' => ['civil_status'],
        'mobile_number' => ['mobile_number', 'contact_number'],
        'contact_number' => ['contact_number', 'mobile_number'],
        'email' => ['email'],
        'house_no' => ['house_no', 'house_number'],
        'house_number' => ['house_number', 'house_no'],
        'street' => ['street'],
        'address' => ['address'],
        'residency_start_date' => ['residency_start_date'],
        'length_of_residency_years' => ['length_of_residency_years'],
        'occupation' => ['occupation'],
        'employment_status' => ['employment_status'],
        'emergency_contact_name' => ['emergency_contact_name'],
        'emergency_contact_number' => ['emergency_contact_number']
    ];

    $residentUpdate = [];
    $userUpdate = [];

    foreach ($allowedInput as $key) {
        if (!array_key_exists($key, $_POST)) {
            continue;
        }

        $value = trim((string)$_POST[$key]);
        $targetCol = null;

        if (isset($aliases[$key])) {
            foreach ($aliases[$key] as $candidate) {
                if (in_array($candidate, $residentCols, true)) {
                    $targetCol = $candidate;
                    break;
                }
            }
        }

        if ($targetCol !== null) {
            if ($targetCol === 'birth_date' || $targetCol === 'date_of_birth') {
                if ($value !== '') {
                    $ts = strtotime($value);
                    if ($ts === false) {
                        sendResponse(false, 'Invalid date format for birth date.', null, 400);
                    }
                    $value = date('Y-m-d', $ts);
                } else {
                    $value = null;
                }
            }

            if ($targetCol === 'residency_start_date') {
                if ($value !== '') {
                    $ts = strtotime($value);
                    if ($ts === false) {
                        sendResponse(false, 'Invalid date format for residency start date.', null, 400);
                    }
                    if ($ts > time()) {
                        sendResponse(false, 'Residency start date cannot be in the future.', null, 400);
                    }
                    $value = date('Y-m-d', $ts);
                    
                    // Recalculate length_of_residency automatically
                    $startDateTime = new DateTime($value);
                    $todayDateTime = new DateTime();
                    $interval = $todayDateTime->diff($startDateTime);
                    $years = $interval->y;
                    $months = $interval->m;
                    
                    $computedLength = $years . ' year' . ($years === 1 ? '' : 's') . ' ' . $months . ' month' . ($months === 1 ? '' : 's');
                    $computedYears = (float)($years + ($months / 12));
                    
                    // Store both values
                    $residentUpdate['length_of_residency'] = $computedLength;
                    $residentUpdate['length_of_residency_years'] = $computedYears;
                } else {
                    $value = null;
                    // Clear computed values if residency date is cleared
                    $residentUpdate['length_of_residency'] = null;
                    $residentUpdate['length_of_residency_years'] = null;
                }
            }

            if (in_array($targetCol, ['gender', 'civil_status', 'employment_status'], true)) {
                $value = strtolower($value);
            }

            if ($targetCol === 'length_of_residency_years') {
                $value = ($value === '') ? null : max(0, (int)$value);
            }

            $residentUpdate[$targetCol] = ($value === '') ? null : $value;
        }

        if ($key === 'email' && in_array('email', $userCols, true)) {
            $userUpdate['email'] = ($value === '') ? null : $value;
        }
    }

    if (empty($residentUpdate) && empty($userUpdate)) {
        sendResponse(false, 'No valid fields to update.', null, 400);
    }

    try {
        $db->beginTransaction();

        if (!empty($residentUpdate)) {
            $setParts = [];
            $params = [];
            foreach ($residentUpdate as $col => $val) {
                $setParts[] = "`$col` = ?";
                $params[] = $val;
            }
            if (in_array('updated_at', $residentCols, true)) {
                $setParts[] = '`updated_at` = CURRENT_TIMESTAMP';
            }
            $params[] = $residentId;
            $sql = 'UPDATE residents SET ' . implode(', ', $setParts) . ' WHERE id = ?';
            $db->query($sql, $params);
        }

        if (!empty($userUpdate)) {
            $setParts = [];
            $params = [];
            foreach ($userUpdate as $col => $val) {
                $setParts[] = "`$col` = ?";
                $params[] = $val;
            }
            $params[] = $userId;
            $sql = 'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = ?';
            $db->query($sql, $params);

            if (array_key_exists('email', $userUpdate)) {
                $_SESSION['email'] = $userUpdate['email'];
            }
        }

        $db->commit();
        sendResponse(true, 'Profile updated successfully.');
    } catch (Exception $e) {
        try { $db->rollback(); } catch (Exception $ignored) {}
        sendResponse(false, DEBUG_MODE ? $e->getMessage() : 'Failed to update profile.', null, 500);
    }
}
