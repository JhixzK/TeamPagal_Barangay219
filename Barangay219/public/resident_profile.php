<?php
/**
 * E-Barangay Information Management System
 * Resident Profile Module
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();

if (!isResidentView()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit();
}

$userId = (int)(getCurrentUserId() ?? 0);
$username = $_SESSION['username'] ?? 'resident';
$residentId = (int)($_SESSION['resident_id'] ?? 0);

if ($residentId <= 0) {
    header('Location: ' . BASE_URL . 'resident_dashboard.php?error=no_resident_link');
    exit();
}

// Use officials layout components for consistent header/sidebar
$page_title = 'My Profile';
require_once __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rpFormatPhone($value) {
    $raw = trim((string)$value);
    if ($raw === '') {
        return '';
    }
    $digits = preg_replace('/\D+/', '', $raw);
    if (strpos($digits, '63') === 0) {
        $digits = substr($digits, 2);
    }
    if (strpos($digits, '0') === 0) {
        $digits = substr($digits, 1);
    }
    $digits = substr($digits, 0, 10);
    if (strlen($digits) < 10) {
        return $raw;
    }
    return '+63 ' . $digits;
}
function rpConnectMysqli() {
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_errno) {
        return null;
    }
    $conn->set_charset(DB_CHARSET);
    return $conn;
}

function rpTableExists($conn, $table) {
    $sql = 'SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();
    return ((int)$total) > 0;
}

function rpGetColumns($conn, $table) {
    $columns = [];
    $sql = "SHOW COLUMNS FROM `" . $conn->real_escape_string($table) . "`";
    $result = $conn->query($sql);
    if (!$result) {
        return $columns;
    }
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['Field'])) {
            $columns[] = $row['Field'];
        }
    }
    return $columns;
}

function rpFetchOne($conn, $sql, $types = '', $params = []) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function rpFetchAll($conn, $sql, $types = '', $params = []) {
    $rows = [];
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $rows;
    }
    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        $stmt->close();
        return $rows;
    }
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();
    return $rows;
}

function rpUpdateResidents($conn, $residentId, $fields, $residentColumns, $userId) {
    $setParts = [];
    $params = [];
    $types = '';

    foreach ($fields as $column => $value) {
        if (!in_array($column, $residentColumns, true)) {
            continue;
        }
        $setParts[] = "`{$column}` = ?";
        $params[] = $value;
        $types .= 's';
    }

    if (in_array('last_updated_by', $residentColumns, true)) {
        $setParts[] = '`last_updated_by` = ?';
        $params[] = $userId;
        $types .= 'i';
    }

    if (in_array('last_updated_at', $residentColumns, true)) {
        $setParts[] = '`last_updated_at` = NOW()';
    }

    if (in_array('updated_at', $residentColumns, true)) {
        $setParts[] = '`updated_at` = NOW()';
    }

    if (empty($setParts)) {
        return true;
    }

    $sql = 'UPDATE residents SET ' . implode(', ', $setParts) . ' WHERE id = ?';
    $types .= 'i';
    $params[] = $residentId;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function rpEnsureSectionUpdatesTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS profile_section_updates (
        id INT(11) NOT NULL AUTO_INCREMENT,
        resident_id INT(11) NOT NULL,
        section_name VARCHAR(60) NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_resident_section (resident_id, section_name),
        KEY idx_section_updated (section_name, updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);
}

function rpTouchSection($conn, $residentId, $section) {
    $sql = "INSERT INTO profile_section_updates (resident_id, section_name, updated_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('is', $residentId, $section);
    $stmt->execute();
    $stmt->close();
}

function rpVerificationBadge($status) {
    $normalized = strtolower(trim((string)$status));
    if (in_array($normalized, ['verified', 'active', 'approved'], true)) {
        return ['label' => 'Verified', 'class' => 'verified'];
    }
    if (in_array($normalized, ['rejected', 'declined', 'denied'], true)) {
        return ['label' => 'Rejected', 'class' => 'rejected'];
    }
    return ['label' => 'Pending Verification', 'class' => 'pending'];
}

  function rpFormatIdTypeLabel($value) {
    $normalized = strtolower(trim((string)$value));
    if ($normalized === '') {
      return 'N/A';
    }

    $map = [
      'psa_birth' => 'PSA Birth Certificate',
      'passport' => 'Passport',
      'drivers_license' => "Driver's License",
      'umid' => 'UMID',
      'postal_id' => 'Postal ID',
      'sss_id' => 'SSS ID',
      'prc_id' => 'PRC ID',
      'voters_id' => "Voter's ID",
      'national_id' => 'National ID',
      'other' => 'Other'
    ];

    if (isset($map[$normalized])) {
      return $map[$normalized];
    }

    $label = str_replace('_', ' ', $normalized);
    return ucwords($label);
  }

  function rpFileUrlFromPath($path) {
    $value = trim((string)$path);
    if ($value === '') {
      return '';
    }
    if (preg_match('#^https?://#i', $value)) {
      return $value;
    }
    return BASE_URL . ltrim($value, '/');
  }

$conn = rpConnectMysqli();
$pageErrors = [];
$successMessage = '';

if (!$conn) {
    $pageErrors[] = 'Unable to connect to database.';
}

$residentCols = [];
$userCols = [];
$householdCols = [];

if ($conn) {
    if (!rpTableExists($conn, 'residents') || !rpTableExists($conn, 'users')) {
        $pageErrors[] = 'Required tables are missing. Please run database setup.';
    } else {
        $residentCols = rpGetColumns($conn, 'residents');
        $userCols = rpGetColumns($conn, 'users');
        if (rpTableExists($conn, 'households')) {
            $householdCols = rpGetColumns($conn, 'households');
        }
        rpEnsureSectionUpdatesTable($conn);
    }
}

if ($conn && empty($pageErrors) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = trim((string)($_POST['section'] ?? ''));

    if ($section === 'personal') {
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $birthDate = trim((string)($_POST['birth_date'] ?? ''));
        $placeOfBirth = trim((string)($_POST['place_of_birth'] ?? ''));
        $gender = strtolower(trim((string)($_POST['gender'] ?? '')));
        $civilStatus = strtolower(trim((string)($_POST['civil_status'] ?? '')));

        if ($firstName === '' || $lastName === '') {
            $pageErrors[] = 'First name and last name are required.';
        }
        if ($birthDate !== '' && strtotime($birthDate) === false) {
            $pageErrors[] = 'Invalid birth date format.';
        }

        if (empty($pageErrors)) {
            $fields = [
                'first_name' => $firstName,
                'middle_name' => trim((string)($_POST['middle_name'] ?? '')),
                'last_name' => $lastName,
                'suffix' => trim((string)($_POST['suffix'] ?? '')),
                'birth_date' => $birthDate === '' ? null : date('Y-m-d', strtotime($birthDate)),
                'place_of_birth' => $placeOfBirth,
                'gender' => $gender,
                'civil_status' => $civilStatus,
                'citizenship' => trim((string)($_POST['citizenship'] ?? 'Filipino')),
                'valid_id_type' => strtolower(trim((string)($_POST['valid_id_type'] ?? ''))),
                'valid_id_number' => trim((string)($_POST['valid_id_number'] ?? '')),
                'national_id' => trim((string)($_POST['valid_id_number'] ?? ''))
            ];

            if (rpUpdateResidents($conn, $residentId, $fields, $residentCols, $userId)) {
                rpTouchSection($conn, $residentId, 'personal');
                $successMessage = 'Personal information updated.';
            } else {
                $pageErrors[] = 'Failed to update personal information.';
            }
        }
    }

    if ($section === 'contact') {
        $mobile = trim((string)($_POST['contact_number'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        if ($mobile !== '' && !preg_match('/^(\+63|0)?\d{10}$/', preg_replace('/\s+/', '', $mobile))) {
            $pageErrors[] = 'Invalid mobile number format.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $pageErrors[] = 'Invalid email address format.';
        }

        if (empty($pageErrors)) {
            $fields = [
                'contact_number' => $mobile,
                'mobile_number' => $mobile,
                'email' => $email,
                'emergency_contact_name' => trim((string)($_POST['emergency_contact_name'] ?? '')),
                'emergency_contact_number' => trim((string)($_POST['emergency_contact_number'] ?? '')),
                'emergency_contact_relationship' => trim((string)($_POST['emergency_contact_relationship'] ?? ''))
            ];

            $okResident = rpUpdateResidents($conn, $residentId, $fields, $residentCols, $userId);
            $okUser = true;
            if (in_array('email', $userCols, true)) {
                $stmt = $conn->prepare('UPDATE users SET email = ? WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('si', $email, $userId);
                    $okUser = $stmt->execute();
                    $stmt->close();
                }
            }

            if ($okResident && $okUser) {
                $_SESSION['email'] = $email;
                rpTouchSection($conn, $residentId, 'contact');
                $successMessage = 'Contact information updated.';
            } else {
                $pageErrors[] = 'Failed to update contact information.';
            }
        }
    }

    if ($section === 'address') {
        $fields = [
            'house_number' => trim((string)($_POST['house_number'] ?? '')),
            'house_no' => trim((string)($_POST['house_number'] ?? '')),
            'street' => trim((string)($_POST['street'] ?? '')),
            'purok_sitio' => trim((string)($_POST['purok_sitio'] ?? '')),
            'barangay' => trim((string)($_POST['barangay'] ?? 'Barangay 219')),
            'city' => trim((string)($_POST['city'] ?? 'Manila')),
            'province' => trim((string)($_POST['province'] ?? 'Metro Manila')),
            'length_of_residency_years' => trim((string)($_POST['length_of_residency_years'] ?? '')),
            'address' => trim((string)($_POST['address'] ?? ''))
        ];

        if ($fields['address'] === '') {
            $line1 = trim($fields['house_number'] . ' ' . $fields['street']);
            $parts = array_filter([
              $line1,
              $fields['purok_sitio'],
              $fields['barangay'],
              $fields['city'],
              $fields['province']
            ], static function ($v) {
              return trim((string)$v) !== '';
            });
            $fields['address'] = implode(', ', $parts);
        }

        if (rpUpdateResidents($conn, $residentId, $fields, $residentCols, $userId)) {
            rpTouchSection($conn, $residentId, 'address');
            $successMessage = 'Address information updated.';
        } else {
            $pageErrors[] = 'Failed to update address information.';
        }
    }

    if ($section === 'household') {
        $householdIdInput = (int)($_POST['household_id'] ?? 0);
        $relationship = trim((string)($_POST['relationship_to_head'] ?? ''));

        if ($householdIdInput > 0 && rpTableExists($conn, 'households')) {
            $exists = rpFetchOne($conn, 'SELECT id FROM households WHERE id = ? LIMIT 1', 'i', [$householdIdInput]);
            if (!$exists) {
                $pageErrors[] = 'Household ID not found.';
            }
        }

        if (empty($pageErrors)) {
            $fields = [
                'household_id' => $householdIdInput > 0 ? (string)$householdIdInput : null,
                'relationship_to_head' => $relationship,
                'household_type' => trim((string)($_POST['household_type'] ?? ''))
            ];

            if (rpUpdateResidents($conn, $residentId, $fields, $residentCols, $userId)) {
                rpTouchSection($conn, $residentId, 'household');
                $successMessage = 'Household information updated.';
            } else {
                $pageErrors[] = 'Failed to update household information.';
            }
        }
    }

    if ($section === 'employment') {
        $fields = [
            'educational_attainment' => trim((string)($_POST['educational_attainment'] ?? '')),
            'occupation' => trim((string)($_POST['occupation'] ?? '')),
            'employment_status' => strtolower(trim((string)($_POST['employment_status'] ?? ''))),
            'employer_name' => trim((string)($_POST['employer_name'] ?? '')),
            'economic_classification' => trim((string)($_POST['economic_classification'] ?? ''))
        ];

        if (rpUpdateResidents($conn, $residentId, $fields, $residentCols, $userId)) {
            rpTouchSection($conn, $residentId, 'employment');
            $successMessage = 'Employment/Education information updated.';
        } else {
            $pageErrors[] = 'Failed to update employment/education information.';
        }
    }

    if ($section === 'special') {
        $fields = [
            'is_senior_citizen' => isset($_POST['is_senior_citizen']) ? '1' : '0',
            'is_pwd' => isset($_POST['is_pwd']) ? '1' : '0',
            'pwd_id_number' => trim((string)($_POST['pwd_id_number'] ?? '')),
            'is_solo_parent' => isset($_POST['is_solo_parent']) ? '1' : '0',
            'solo_parent_id_number' => trim((string)($_POST['solo_parent_id_number'] ?? '')),
            'is_ip_member' => isset($_POST['is_ip_member']) ? '1' : '0',
            'ip_group' => trim((string)($_POST['ip_group'] ?? '')),
            'is_4ps_beneficiary' => isset($_POST['is_4ps_beneficiary']) ? '1' : '0'
        ];

        if (rpUpdateResidents($conn, $residentId, $fields, $residentCols, $userId)) {
            rpTouchSection($conn, $residentId, 'special');
            $successMessage = 'Special status updated.';
        } else {
            $pageErrors[] = 'Failed to update special status.';
        }
    }

    if ($section === 'account') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($newPassword === '' || strlen($newPassword) < 8) {
            $pageErrors[] = 'New password must be at least 8 characters.';
        }
        if ($newPassword !== $confirmPassword) {
            $pageErrors[] = 'Password confirmation does not match.';
        }

        $userRow = rpFetchOne($conn, 'SELECT password FROM users WHERE id = ? LIMIT 1', 'i', [$userId]);
        if (!$userRow || !password_verify($currentPassword, (string)$userRow['password'])) {
            $pageErrors[] = 'Current password is incorrect.';
        }

        if (empty($pageErrors)) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('si', $newHash, $userId);
                if ($stmt->execute()) {
                    rpTouchSection($conn, $residentId, 'account');
                    $successMessage = 'Password changed successfully.';
                } else {
                    $pageErrors[] = 'Failed to change password.';
                }
                $stmt->close();
            }
        }
    }

    if ($section === 'verification_upload') {
      $pageErrors[] = 'ID verification now uses the document submitted during registration. Profile ID upload is disabled.';
    }
}

$resident = [];
$user = [];
$household = [];
$sectionUpdated = [];
$approvedApplication = [];

if ($conn && empty($pageErrors)) {
    $resident = rpFetchOne($conn, 'SELECT * FROM residents WHERE id = ? LIMIT 1', 'i', [$residentId]) ?? [];
    $userSelectParts = ['username', 'email', 'created_at'];
    if (in_array('two_factor_enabled', $userCols, true)) {
        $userSelectParts[] = 'two_factor_enabled';
    }
    $userSelectSql = implode(', ', $userSelectParts);
    $user = rpFetchOne($conn, "SELECT {$userSelectSql} FROM users WHERE id = ? LIMIT 1", 'i', [$userId]) ?? [];

    $householdId = (int)($resident['household_id'] ?? 0);
    if ($householdId > 0 && rpTableExists($conn, 'households')) {
        $household = rpFetchOne($conn, 'SELECT * FROM households WHERE id = ? LIMIT 1', 'i', [$householdId]) ?? [];
    }

    if (rpTableExists($conn, 'profile_section_updates')) {
        $rows = rpFetchAll($conn, 'SELECT section_name, updated_at FROM profile_section_updates WHERE resident_id = ?', 'i', [$residentId]);
        foreach ($rows as $row) {
            $sectionUpdated[$row['section_name']] = $row['updated_at'];
        }
    }

    if (rpTableExists($conn, 'resident_applications')) {
      $appCols = array_flip(rpGetColumns($conn, 'resident_applications'));
      $statusCol = isset($appCols['record_status']) ? 'record_status' : (isset($appCols['status']) ? 'status' : '');

      if ($statusCol !== '') {
        $selectApprovedResidentId = isset($appCols['approved_resident_id']) ? 'approved_resident_id' : 'NULL AS approved_resident_id';
        $selectValidIdType = isset($appCols['valid_id_type']) ? 'valid_id_type' : 'NULL AS valid_id_type';
        $selectValidIdNumber = isset($appCols['valid_id_number']) ? 'valid_id_number' : 'NULL AS valid_id_number';
        $selectIdDoc = isset($appCols['id_document_path']) ? 'id_document_path' : 'NULL AS id_document_path';
        $selectProofDoc = isset($appCols['proof_of_residency_path']) ? 'proof_of_residency_path' : 'NULL AS proof_of_residency_path';
        $orderBy = isset($appCols['reviewed_at']) ? 'reviewed_at DESC, id DESC' : 'id DESC';

        if (isset($appCols['approved_resident_id'])) {
          $approvedApplication = rpFetchOne(
            $conn,
            "SELECT id, $selectApprovedResidentId, $selectValidIdType, $selectValidIdNumber, $selectIdDoc, $selectProofDoc
             FROM resident_applications
             WHERE approved_resident_id = ? AND `$statusCol` = 'approved'
                     ORDER BY $orderBy
             LIMIT 1",
            'i',
            [$residentId]
          ) ?? [];
        }

        if (empty($approvedApplication)) {
          $firstName = trim((string)($resident['first_name'] ?? ''));
          $lastName = trim((string)($resident['last_name'] ?? ''));
          $birthDate = trim((string)($resident['birth_date'] ?? ''));

          if ($firstName !== '' && $lastName !== '' && $birthDate !== '') {
            $approvedApplication = rpFetchOne(
              $conn,
              "SELECT id, $selectApprovedResidentId, $selectValidIdType, $selectValidIdNumber, $selectIdDoc, $selectProofDoc
               FROM resident_applications
               WHERE LOWER(first_name) = LOWER(?)
                 AND LOWER(last_name) = LOWER(?)
                 AND birth_date = ?
                 AND `$statusCol` = 'approved'
                         ORDER BY $orderBy
               LIMIT 1",
              'sss',
              [$firstName, $lastName, $birthDate]
            ) ?? [];
          }
        }
      }
    }
}

if ($conn) {
    $conn->close();
}

$pick = static function (array $row, array $keys, $default = '') {
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            return $row[$key];
        }
    }
    return $default;
};

$fullName = trim(($resident['first_name'] ?? '') . ' ' . (($resident['middle_name'] ?? '') ? $resident['middle_name'] . ' ' : '') . ($resident['last_name'] ?? ''));
if ($fullName === '') {
    $fullName = $username;
}

$residentCode = (string)($resident['resident_code'] ?? '');
$residentDisplayId = $residentCode !== '' ? $residentCode : ('RES-219-' . date('Y') . '-' . str_pad((string)$residentId, 6, '0', STR_PAD_LEFT));
$verificationStatus = $pick($resident, ['verification_status', 'record_status'], 'pending');
$verification = rpVerificationBadge($verificationStatus);
$verificationBadgeClass = 'text-bg-secondary';
switch (strtolower((string)($verification['class'] ?? ''))) {
    case 'verified':
        $verificationBadgeClass = 'text-bg-success';
        break;
    case 'rejected':
        $verificationBadgeClass = 'text-bg-danger';
        break;
    case 'pending':
        $verificationBadgeClass = 'text-bg-warning';
        break;
    default:
        $verificationBadgeClass = 'text-bg-secondary';
        break;
}
$createdAt = $pick($resident, ['created_at'], $pick($user, ['created_at']));
$createdAtLabel = $createdAt ? date('F d, Y', strtotime($createdAt)) : 'N/A';

$twoFaAvailable = array_key_exists('two_factor_enabled', $user);
$twoFaOn = $twoFaAvailable && (int)($user['two_factor_enabled'] ?? 0) === 1;

$resolvedValidIdType = $pick($resident, ['valid_id_type'], $pick($approvedApplication, ['valid_id_type']));
$resolvedValidIdNumber = $pick($resident, ['valid_id_number', 'national_id'], $pick($approvedApplication, ['valid_id_number']));
$resolvedIdDocumentPath = $pick($resident, ['id_document_path'], $pick($approvedApplication, ['id_document_path']));
$resolvedProofDocumentPath = $pick($resident, ['proof_of_residency_path'], $pick($approvedApplication, ['proof_of_residency_path']));
$resolvedIdTypeLabel = rpFormatIdTypeLabel($resolvedValidIdType);
$idDocumentUrl = rpFileUrlFromPath($resolvedIdDocumentPath);
$proofDocumentUrl = rpFileUrlFromPath($resolvedProofDocumentPath);

$displayHouseNumber = trim((string)$pick($resident, ['house_number', 'house_no']));
$displayStreet = trim((string)$pick($resident, ['street']));
$displayPurokSitio = trim((string)$pick($resident, ['purok_sitio']));

if ($displayPurokSitio === '' && $displayStreet !== '') {
  if (preg_match('/\b(purok\s*\d+|sitio\s*\d+)\b/i', $displayStreet, $m)) {
    $displayPurokSitio = trim((string)$m[1]);
    $displayStreet = trim((string)preg_replace('/\b(purok\s*\d+|sitio\s*\d+)\b/i', '', $displayStreet));
    $displayStreet = trim($displayStreet, " ,-");
  }
}

if ($displayPurokSitio === '' && $displayHouseNumber !== '') {
  if (preg_match('/\b(purok\s*\d+|sitio\s*\d+)\b/i', $displayHouseNumber, $m)) {
    $displayPurokSitio = trim((string)$m[1]);
    $displayHouseNumber = trim((string)preg_replace('/\b(purok\s*\d+|sitio\s*\d+)\b/i', '', $displayHouseNumber));
    $displayHouseNumber = trim($displayHouseNumber, " ,-");
  }
}

$avatarPath = $resolvedIdDocumentPath;
$avatarUrl = 'https://i.pravatar.cc/140?img=12';
if ($avatarPath !== '') {
    $avatarUrl = BASE_URL . ltrim($avatarPath, '/');
}

$criticalFields = [
    'Place of Birth' => trim((string)$pick($resident, ['place_of_birth'])),
    'Civil Status' => trim((string)$pick($resident, ['civil_status'])),
    'Address' => trim((string)$pick($resident, ['address', 'street'])),
    'Household Link' => ((int)$pick($resident, ['household_id'], 0)) > 0 ? 'linked' : '',
    'Birth Date' => trim((string)$pick($resident, ['birth_date', 'date_of_birth'])),
    'Gender' => trim((string)$pick($resident, ['gender', 'sex'])),
    'Contact Number' => rpFormatPhone($pick($resident, ['contact_number', 'mobile_number'])),
    'Email' => trim((string)$pick($resident, ['email'], $pick($user, ['email'])))
];

$missingFields = [];
$filled = 0;
foreach ($criticalFields as $label => $value) {
    if ($value === '') {
        $missingFields[] = $label;
    } else {
        $filled++;
    }
}
$completionPercent = (int)round(($filled / max(1, count($criticalFields))) * 100);

$householdHeadName = 'N/A';
$householdMemberCount = 'N/A';
$householdAddress = 'N/A';
if (!empty($household)) {
    $householdAddress = trim((string)($household['address'] ?? ''));
    $householdMemberCount = (string)($household['total_members'] ?? 'N/A');
    $headId = (int)($household['family_head_id'] ?? 0);
    if ($headId > 0 && $conn === null) {
        // no-op placeholder
    }
}

$verificationComment = (string)$pick($resident, ['rejection_reason', 'remarks']);
$isRejected = strtolower($verification['class']) === 'rejected';

function formatSectionUpdated($sectionUpdated, $sectionName) {
    if (empty($sectionUpdated[$sectionName])) {
        return 'Not updated yet';
    }
    return date('F d, Y h:i A', strtotime((string)$sectionUpdated[$sectionName]));
}
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>resident_profile.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/resident_profile.css')); ?>">

<div class="main-content module-page resident-profile-page resident-theme">
  <div class="container-fluid">
    <section class="dashboard-hero card border-0 shadow-sm mb-4">
      <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="hero-copy">
          <p class="hero-kicker text-uppercase small mb-1">Resident Services Portal</p>
          <h2 class="mb-1"><i class="bi bi-person-vcard me-2"></i>My Profile</h2>
          <p class="hero-subtitle mb-0">Manage your resident information, verification status, and account security.</p>
        </div>
        <div class="text-md-end hero-meta">
          <span class="hero-date-badge fs-6 px-3 py-2" id="mainDateBadge">
            <i class="bi bi-calendar3 me-1"></i><?php echo date('F d, Y'); ?>
          </span>
          <div class="hero-chips mt-2">
            <span class="hero-chip"><i class="bi bi-person-check me-1"></i>Resident View</span>
            <span class="hero-chip"><i class="bi bi-shield-check me-1"></i>Profile Secure</span>
          </div>
        </div>
      </div>
    </section>

    <?php if (!empty($pageErrors)): ?>
      <section class="notice error-notice">
        <h4>Unable to Save Changes</h4>
        <ul>
          <?php foreach ($pageErrors as $error): ?>
            <li><?php echo h($error); ?></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <?php if ($successMessage !== ''): ?>
      <section class="notice success-notice">
        <h4>Update Successful</h4>
        <p><?php echo h($successMessage); ?></p>
      </section>
    <?php endif; ?>

    <section class="profile-summary card sticky-profile dash-panel mb-4">
      <div class="summary-left">
        <img src="<?php echo h($avatarUrl); ?>" alt="Resident profile image">
        <div class="summary-meta">
          <h3><?php echo h($fullName); ?></h3>
          <p class="resident-id">Resident ID: <?php echo h($residentDisplayId); ?></p>
          <p class="resident-address">Created: <?php echo h($createdAtLabel); ?></p>
          <span class="badge <?php echo h($verificationBadgeClass); ?>"><?php echo h($verification['label']); ?></span>
        </div>
      </div>
      <div class="completion-box">
        <p>Profile Completion</p>
        <strong><?php echo (int)$completionPercent; ?>%</strong>
        <div class="completion-track"><div class="completion-fill" style="width: <?php echo (int)$completionPercent; ?>%;"></div></div>
      </div>
    </section>

    <?php if (!empty($missingFields)): ?>
      <section class="notice warning-notice">
        <h4>Missing Critical Fields</h4>
        <p>Please complete the following for verification and service eligibility.</p>
        <div class="missing-list">
          <?php foreach ($missingFields as $missing): ?>
            <span><?php echo h($missing); ?></span>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($isRejected && $verificationComment !== ''): ?>
      <section class="notice error-notice">
        <h4>Verification Rejected</h4>
        <p>Admin comment: <?php echo h($verificationComment); ?></p>
      </section>
    <?php endif; ?>

    <section class="cards-grid">
      <article class="card info-card">
        <div class="card-head">
          <div>
            <h3>Personal Information</h3>
            <small>Last Updated: <?php echo h(formatSectionUpdated($sectionUpdated, 'personal')); ?></small>
          </div>
          <button class="btn-link toggle-btn" data-target="form-personal"><i class="bi bi-pencil-square me-1"></i> Edit</button>
        </div>
        <div class="info-list">
          <div class="info-row"><span>First Name</span><strong><?php echo h($pick($resident, ['first_name'], 'N/A')); ?></strong></div>
          <div class="info-row"><span>Middle Name</span><strong><?php echo h($pick($resident, ['middle_name'], 'N/A')); ?></strong></div>
          <div class="info-row"><span>Last Name</span><strong><?php echo h($pick($resident, ['last_name'], 'N/A')); ?></strong></div>
          <div class="info-row"><span>Suffix</span><strong><?php echo h($pick($resident, ['suffix'], 'N/A')); ?></strong></div>
          <div class="info-row"><span>Date of Birth</span><strong><?php echo h($pick($resident, ['birth_date'], 'N/A')); ?></strong></div>
          <div class="info-row"><span>Place of Birth</span><strong><?php echo h($pick($resident, ['place_of_birth'], 'N/A')); ?></strong></div>
          <div class="info-row"><span>Gender</span><strong><?php echo h($pick($resident, ['gender', 'sex'], 'N/A')); ?></strong></div>
          <div class="info-row"><span>Civil Status</span><strong><?php echo h($pick($resident, ['civil_status'], 'N/A')); ?></strong></div>
          <div class="info-row"><span>Citizenship</span><strong><?php echo h($pick($resident, ['citizenship'], 'N/A')); ?></strong></div>
          <div class="info-row"><span>Government ID Type</span><strong><?php echo h($resolvedIdTypeLabel); ?></strong></div>
          <div class="info-row"><span>ID Number</span><strong><?php echo h($resolvedValidIdNumber !== '' ? $resolvedValidIdNumber : 'N/A'); ?></strong></div>
        </div>

        <form method="POST" class="edit-form hidden" id="form-personal">
          <input type="hidden" name="section" value="personal">
          <div class="form-grid two-col">
            <label><span>First Name</span><input type="text" name="first_name" value="<?php echo h($pick($resident, ['first_name'])); ?>" required></label>
            <label><span>Middle Name</span><input type="text" name="middle_name" value="<?php echo h($pick($resident, ['middle_name'])); ?>"></label>
            <label><span>Last Name</span><input type="text" name="last_name" value="<?php echo h($pick($resident, ['last_name'])); ?>" required></label>
            <label><span>Suffix</span><input type="text" name="suffix" value="<?php echo h($pick($resident, ['suffix'])); ?>"></label>
            <label><span>Birth Date</span><input type="date" name="birth_date" value="<?php echo h($pick($resident, ['birth_date'])); ?>"></label>
            <label><span>Place of Birth</span><input type="text" name="place_of_birth" value="<?php echo h($pick($resident, ['place_of_birth'])); ?>"></label>
            <label><span>Gender</span><input type="text" name="gender" value="<?php echo h($pick($resident, ['gender', 'sex'])); ?>"></label>
            <label><span>Civil Status</span><input type="text" name="civil_status" value="<?php echo h($pick($resident, ['civil_status'])); ?>"></label>
            <label><span>Citizenship</span><input type="text" name="citizenship" value="<?php echo h($pick($resident, ['citizenship'], 'Filipino')); ?>"></label>
            <label><span>Government ID Type</span><input type="text" name="valid_id_type" value="<?php echo h($resolvedValidIdType); ?>"></label>
            <label class="full"><span>ID Number</span><input type="text" name="valid_id_number" value="<?php echo h($resolvedValidIdNumber); ?>"></label>
          </div>
          <div class="form-actions"><button class="btn-primary" type="submit">Save Personal Info</button></div>
        </form>
      </article>

      <article class="card info-card">
        <div class="card-head">
          <div>
            <h3>Contact Information</h3>
            <small>Last Updated: <?php echo h(formatSectionUpdated($sectionUpdated, 'contact')); ?></small>
          </div>
          <button class="btn-link toggle-btn" data-target="form-contact"><i class="bi bi-pencil-square me-1"></i> Edit</button>
        </div>
        <div class="info-list">
          <div class="info-row"><span>Mobile Number</span><strong><?php echo h(rpFormatPhone($pick($resident, ['contact_number', 'mobile_number'], 'N/A'))); ?></strong></div>
          <div class="info-row"><span>Email Address</span><strong><?php echo h($pick($resident, ['email'], $pick($user, ['email'], 'N/A'))); ?></strong></div>
          <div class="info-row"><span>Emergency Contact Person</span><strong><?php echo h($pick($resident, ['emergency_contact_name'], 'N/A')); ?></strong></div>
          <div class="info-row"><span>Emergency Contact Number</span><strong><?php echo h(rpFormatPhone($pick($resident, ['emergency_contact_number'], 'N/A'))); ?></strong></div>
          <div class="info-row"><span>Relationship</span><strong><?php echo h($pick($resident, ['emergency_contact_relationship'], 'N/A')); ?></strong></div>
        </div>

        <form method="POST" class="edit-form hidden" id="form-contact">
          <input type="hidden" name="section" value="contact">
          <div class="form-grid two-col">
            <label><span>Mobile Number</span><input type="text" name="contact_number" value="<?php echo h(rpFormatPhone($pick($resident, ['contact_number', 'mobile_number']))); ?>"></label>
            <label><span>Email Address</span><input type="email" name="email" value="<?php echo h($pick($resident, ['email'], $pick($user, ['email']))); ?>"></label>
            <label><span>Emergency Contact Person</span><input type="text" name="emergency_contact_name" value="<?php echo h($pick($resident, ['emergency_contact_name'])); ?>"></label>
            <label><span>Emergency Contact Number</span><input type="text" name="emergency_contact_number" value="<?php echo h(rpFormatPhone($pick($resident, ['emergency_contact_number']))); ?>"></label>
            <label class="full"><span>Emergency Contact Relationship</span><input type="text" name="emergency_contact_relationship" value="<?php echo h($pick($resident, ['emergency_contact_relationship'])); ?>"></label>
          </div>
          <div class="form-actions"><button class="btn-primary" type="submit">Save Contact Info</button></div>
        </form>
      </article>

      <article class="card info-card">
        <div class="card-head">
          <div>
            <h3>Address Information</h3>
            <small>Last Updated: <?php echo h(formatSectionUpdated($sectionUpdated, 'address')); ?></small>
          </div>
          <button class="btn-link toggle-btn" data-target="form-address"><i class="bi bi-pencil-square me-1"></i> Edit</button>
        </div>
        <div class="info-list">
          <div class="info-row"><span>House Number / Street</span><strong><?php echo h(trim($displayHouseNumber . ' ' . $displayStreet) ?: 'N/A'); ?></strong></div>
          <div class="info-row"><span>Purok / Sitio</span><strong><?php echo h($displayPurokSitio !== '' ? $displayPurokSitio : 'N/A'); ?></strong></div>
          <div class="info-row"><span>Barangay</span><strong><?php echo h($pick($resident, ['barangay'], 'Barangay 219')); ?></strong></div>
          <div class="info-row"><span>City / Municipality</span><strong><?php echo h($pick($resident, ['city'], 'Manila')); ?></strong></div>
          <div class="info-row"><span>Province</span><strong><?php echo h($pick($resident, ['province'], 'Metro Manila')); ?></strong></div>
          <div class="info-row"><span>Length of Residency</span><strong><?php echo h($pick($resident, ['length_of_residency_years', 'years_of_residency'], 'N/A')); ?></strong></div>
        </div>

        <form method="POST" class="edit-form hidden" id="form-address">
          <input type="hidden" name="section" value="address">
          <div class="form-grid two-col">
            <label><span>House Number</span><input type="text" name="house_number" value="<?php echo h($displayHouseNumber); ?>"></label>
            <label><span>Street</span><input type="text" name="street" value="<?php echo h($displayStreet); ?>"></label>
            <label><span>Purok / Sitio</span><input type="text" name="purok_sitio" value="<?php echo h($displayPurokSitio); ?>"></label>
            <label><span>Barangay</span><input type="text" name="barangay" value="<?php echo h($pick($resident, ['barangay'], 'Barangay 219')); ?>"></label>
            <label><span>City</span><input type="text" name="city" value="<?php echo h($pick($resident, ['city'], 'Manila')); ?>"></label>
            <label><span>Province</span><input type="text" name="province" value="<?php echo h($pick($resident, ['province'], 'Metro Manila')); ?>"></label>
            <label><span>Length of Residency (years)</span><input type="number" min="0" name="length_of_residency_years" value="<?php echo h($pick($resident, ['length_of_residency_years', 'years_of_residency'])); ?>"></label>
            <label><span>Full Address</span><input type="text" name="address" value="<?php echo h($pick($resident, ['address'])); ?>"></label>
          </div>
          <div class="form-actions"><button class="btn-primary" type="submit">Save Address Info</button></div>
        </form>
      </article>

      <article class="card info-card">
        <div class="card-head">
          <div>
            <h3>Household Information</h3>
            <small>Last Updated: <?php echo h(formatSectionUpdated($sectionUpdated, 'household')); ?></small>
          </div>
          <button class="btn-link toggle-btn" data-target="form-household"><i class="bi bi-pencil-square me-1"></i> Edit</button>
        </div>
        <div class="info-list">
          <div class="info-row"><span>Household ID</span><strong><?php echo h($pick($resident, ['household_id'], 'Not Linked')); ?></strong></div>
          <div class="info-row"><span>Household Head Name</span><strong><?php echo h($pick($resident, ['household_head_name'], 'N/A')); ?></strong></div>
          <div class="info-row"><span>Relationship</span><strong><?php echo h($pick($resident, ['relationship_to_head'], 'N/A')); ?></strong></div>
          <div class="info-row"><span>Household Type</span><strong><?php echo h($pick($resident, ['household_type'], 'N/A')); ?></strong></div>
          <div class="info-row"><span>Number of Members</span><strong><?php echo h($pick($household, ['total_members'], 'N/A')); ?></strong></div>
        </div>

        <form method="POST" class="edit-form hidden" id="form-household">
          <input type="hidden" name="section" value="household">
          <div class="form-grid two-col">
            <label><span>Household ID</span><input type="number" min="0" name="household_id" value="<?php echo h($pick($resident, ['household_id'])); ?>"></label>
            <label><span>Relationship to Household Head</span><input type="text" name="relationship_to_head" value="<?php echo h($pick($resident, ['relationship_to_head'])); ?>"></label>
            <label><span>Household Type</span><input type="text" name="household_type" value="<?php echo h($pick($resident, ['household_type'])); ?>"></label>
          </div>
          <div class="form-actions"><button class="btn-primary" type="submit"><?php echo ((int)$pick($resident, ['household_id'], 0) > 0) ? 'Update Household Link' : 'Link Household'; ?></button></div>
        </form>
      </article>

      <article class="card info-card">
        <div class="card-head">
          <div>
            <h3>Employment / Education</h3>
            <small>Last Updated: <?php echo h(formatSectionUpdated($sectionUpdated, 'employment')); ?></small>
          </div>
          <button class="btn-link toggle-btn" data-target="form-employment"><i class="bi bi-pencil-square me-1"></i> Edit</button>
        </div>
        <div class="info-list">
          <div class="info-row"><span>Educational Attainment</span><strong><?php echo h($pick($resident, ['educational_attainment'], 'N/A')); ?></strong></div>
          <div class="info-row"><span>Occupation</span><strong><?php echo h($pick($resident, ['occupation'], 'N/A')); ?></strong></div>
          <div class="info-row"><span>Employment Status</span><strong><?php echo h($pick($resident, ['employment_status'], 'N/A')); ?></strong></div>
          <div class="info-row"><span>Employer Name</span><strong><?php echo h($pick($resident, ['employer_name'], 'N/A')); ?></strong></div>
          <div class="info-row"><span>Economic Classification</span><strong><?php echo h($pick($resident, ['economic_classification'], 'N/A')); ?></strong></div>
        </div>

        <form method="POST" class="edit-form hidden" id="form-employment">
          <input type="hidden" name="section" value="employment">
          <div class="form-grid two-col">
            <label><span>Educational Attainment</span><input type="text" name="educational_attainment" value="<?php echo h($pick($resident, ['educational_attainment'])); ?>"></label>
            <label><span>Occupation</span><input type="text" name="occupation" value="<?php echo h($pick($resident, ['occupation'])); ?>"></label>
            <label><span>Employment Status</span><input type="text" name="employment_status" value="<?php echo h($pick($resident, ['employment_status'])); ?>"></label>
            <label><span>Employer Name</span><input type="text" name="employer_name" value="<?php echo h($pick($resident, ['employer_name'])); ?>"></label>
            <label><span>Economic Classification</span><input type="text" name="economic_classification" value="<?php echo h($pick($resident, ['economic_classification'])); ?>"></label>
          </div>
          <div class="form-actions"><button class="btn-primary" type="submit">Save Employment/Education</button></div>
        </form>
      </article>

      <article class="card info-card">
        <div class="card-head">
          <div>
            <h3>Special Status</h3>
            <small>Last Updated: <?php echo h(formatSectionUpdated($sectionUpdated, 'special')); ?></small>
          </div>
          <button class="btn-link toggle-btn" data-target="form-special"><i class="bi bi-pencil-square me-1"></i> Edit</button>
        </div>
        <div class="info-list">
          <div class="info-row"><span>Senior Citizen</span><strong><?php echo ((int)$pick($resident, ['is_senior_citizen'], 0) === 1) ? 'Yes' : 'No'; ?></strong></div>
          <div class="info-row"><span>PWD</span><strong><?php echo ((int)$pick($resident, ['is_pwd'], 0) === 1) ? 'Yes' : 'No'; ?></strong></div>
          <div class="info-row"><span>PWD ID Number</span><strong><?php echo h($pick($resident, ['pwd_id_number'], 'N/A')); ?></strong></div>
          <div class="info-row"><span>Solo Parent</span><strong><?php echo ((int)$pick($resident, ['is_solo_parent'], 0) === 1) ? 'Yes' : 'No'; ?></strong></div>
          <div class="info-row"><span>Solo Parent ID</span><strong><?php echo h($pick($resident, ['solo_parent_id_number'], 'N/A')); ?></strong></div>
          <div class="info-row"><span>IP Member</span><strong><?php echo ((int)$pick($resident, ['is_ip_member'], 0) === 1) ? 'Yes' : 'No'; ?></strong></div>
          <div class="info-row"><span>IP Group</span><strong><?php echo h($pick($resident, ['ip_group'], 'N/A')); ?></strong></div>
          <div class="info-row"><span>4Ps Beneficiary</span><strong><?php echo ((int)$pick($resident, ['is_4ps_beneficiary'], 0) === 1) ? 'Yes' : 'No'; ?></strong></div>
        </div>

        <form method="POST" class="edit-form hidden" id="form-special">
          <input type="hidden" name="section" value="special">
          <div class="form-grid two-col">
            <label class="check"><input type="checkbox" name="is_senior_citizen" <?php echo ((int)$pick($resident, ['is_senior_citizen'], 0) === 1) ? 'checked' : ''; ?>><span>Senior Citizen</span></label>
            <label class="check"><input type="checkbox" name="is_pwd" <?php echo ((int)$pick($resident, ['is_pwd'], 0) === 1) ? 'checked' : ''; ?>><span>PWD</span></label>
            <label><span>PWD ID Number</span><input type="text" name="pwd_id_number" value="<?php echo h($pick($resident, ['pwd_id_number'])); ?>"></label>
            <label class="check"><input type="checkbox" name="is_solo_parent" <?php echo ((int)$pick($resident, ['is_solo_parent'], 0) === 1) ? 'checked' : ''; ?>><span>Solo Parent</span></label>
            <label><span>Solo Parent ID</span><input type="text" name="solo_parent_id_number" value="<?php echo h($pick($resident, ['solo_parent_id_number'])); ?>"></label>
            <label class="check"><input type="checkbox" name="is_ip_member" <?php echo ((int)$pick($resident, ['is_ip_member'], 0) === 1) ? 'checked' : ''; ?>><span>IP Member</span></label>
            <label><span>IP Group</span><input type="text" name="ip_group" value="<?php echo h($pick($resident, ['ip_group'])); ?>"></label>
            <label class="check"><input type="checkbox" name="is_4ps_beneficiary" <?php echo ((int)$pick($resident, ['is_4ps_beneficiary'], 0) === 1) ? 'checked' : ''; ?>><span>4Ps Beneficiary</span></label>
          </div>
          <div class="form-actions"><button class="btn-primary" type="submit">Save Special Status</button></div>
        </form>
      </article>

      <article class="card info-card">
        <div class="card-head">
          <div>
            <h3>Account Information</h3>
            <small>Last Updated: <?php echo h(formatSectionUpdated($sectionUpdated, 'account')); ?></small>
          </div>
          <button class="btn-link toggle-btn" data-target="form-account"><i class="bi bi-key me-1"></i> Change Password</button>
        </div>
        <div class="info-list">
          <div class="info-row"><span>Username</span><strong><?php echo h($pick($user, ['username'], $username)); ?></strong></div>
          <div class="info-row"><span>Password</span><strong>************</strong></div>
          <div class="info-row"><span>Account Created</span><strong><?php echo h($createdAtLabel); ?></strong></div>
        </div>

        <form method="POST" class="edit-form hidden" id="form-account">
          <input type="hidden" name="section" value="account">
          <div class="form-grid">
            <label><span>Current Password</span><input type="password" name="current_password" required></label>
            <label><span>New Password</span><input type="password" name="new_password" minlength="8" required></label>
            <label><span>Confirm New Password</span><input type="password" name="confirm_password" minlength="8" required></label>
          </div>
          <div class="form-actions"><button class="btn-primary" type="submit">Update Password</button></div>
        </form>
      </article>

      <?php if ($twoFaAvailable): ?>
      <article class="card info-card">
        <div class="card-head">
          <div>
            <h3>Email verification at login</h3>
            <small>Optional extra security for your account</small>
          </div>
        </div>
        <div class="info-list">
          <div class="info-row"><span>Status</span><strong id="residentTwoFaStatus"><?php echo $twoFaOn ? 'Enabled' : 'Disabled'; ?></strong></div>
          <div class="info-row"><span>Note</span><strong>Uses the email on your account. Update contact email first if needed.</strong></div>
        </div>
        <div class="edit-form" style="border-top: 1px dashed #cbd5e1; padding-top: 0.75rem;">
          <div class="form-grid">
            <label><span>Confirm with password</span><input type="password" id="residentTwoFaPassword" autocomplete="current-password" placeholder="Your current password"></label>
          </div>
          <div class="form-actions" style="display:flex;flex-wrap:wrap;gap:0.5rem;">
            <button type="button" class="btn-primary" id="residentTwoFaEnableBtn" <?php echo $twoFaOn ? 'style="display:none;"' : ''; ?>>Enable</button>
            <button type="button" class="btn-link" id="residentTwoFaDisableBtn" <?php echo $twoFaOn ? '' : 'style="display:none;"'; ?>>Disable</button>
          </div>
          <p class="small text-muted mb-0 mt-2" id="residentTwoFaMsg"></p>
        </div>
      </article>
      <?php endif; ?>

      <article class="card info-card full-width">
        <div class="card-head">
          <div>
            <h3>Verification / ID Upload</h3>
            <small>Last Updated: <?php echo h(formatSectionUpdated($sectionUpdated, 'verification')); ?></small>
          </div>
        </div>
        <div class="info-list">
          <div class="info-row"><span>Uploaded ID Type</span><strong><?php echo h($resolvedIdTypeLabel); ?></strong></div>
          <div class="info-row"><span>Valid ID Document</span><strong><?php if ($resolvedIdDocumentPath !== ''): ?><a href="<?php echo h($idDocumentUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo h($resolvedIdDocumentPath); ?></a><?php else: ?>No ID uploaded<?php endif; ?></strong></div>
          <div class="info-row"><span>Proof of Residency</span><strong><?php if ($resolvedProofDocumentPath !== ''): ?><a href="<?php echo h($proofDocumentUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo h($resolvedProofDocumentPath); ?></a><?php else: ?>No proof uploaded<?php endif; ?></strong></div>
          <div class="info-row"><span>Verification Status</span><strong><span class="badge <?php echo h($verificationBadgeClass); ?>"><?php echo h($verification['label']); ?></span></strong></div>
          <div class="info-row"><span>Certificate Requests</span><strong><?php echo strtolower($verification['class']) === 'verified' ? 'Enabled' : 'Disabled until verified'; ?></strong></div>
          <div class="info-row"><span>Verification Source</span><strong>Registration submission</strong></div>
        </div>
        <div class="info-row"><span>Note</span><strong>ID re-upload in resident profile is disabled. Any ID for verification must come from registration.</strong></div>
      </article>
    </section>
  </div>
</div>

<style>
.resident-profile-page .dashboard-hero {
  border-radius: 16px;
  background: radial-gradient(circle at 0% 0%, rgba(147, 197, 253, 0.24), transparent 36%), linear-gradient(140deg, #f8fbff 0%, #eef4ff 58%, #f4f7fb 100%);
  border: 1px solid rgba(59, 130, 246, 0.2) !important;
  box-shadow: 0 16px 34px -24px rgba(37, 99, 235, 0.45);
}

.resident-profile-page .dashboard-hero .card-body {
  padding: 1.2rem 1.3rem;
}

.resident-profile-page .hero-kicker {
  color: #334155;
  letter-spacing: 0.08em;
  font-weight: 700;
}

.resident-profile-page .hero-copy h2 {
  color: #0f172a;
  font-weight: 700;
}

.resident-profile-page .hero-subtitle {
  color: #475569;
  max-width: 640px;
}

.resident-profile-page .hero-date-badge {
  display: inline-block;
  border-radius: 999px;
  background: rgba(37, 99, 235, 0.12);
  color: #1e3a8a;
  border: 1px solid rgba(37, 99, 235, 0.22);
  font-weight: 600;
}

.resident-profile-page .hero-chips {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

.resident-profile-page .hero-chip {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 0.2rem 0.6rem;
  font-size: 0.78rem;
  color: #334155;
  background: rgba(255, 255, 255, 0.7);
  border: 1px solid rgba(148, 163, 184, 0.35);
}

.resident-profile-page .notice {
  border-radius: 12px;
  border-width: 1px;
  box-shadow: 0 8px 20px -14px rgba(15, 23, 42, 0.2);
}

.resident-profile-page .dash-panel,
.resident-profile-page .card.info-card {
  border-radius: 14px;
  border: 1px solid #e2e8f0 !important;
  box-shadow: 0 8px 20px -12px rgba(15, 23, 42, 0.18);
}

.resident-profile-page .sticky-profile {
  top: 84px;
  padding: 1rem;
  background: #ffffff;
}

.resident-profile-page .summary-left img {
  border: 2px solid #dbeafe;
}

.resident-profile-page .summary-meta h3 {
  color: #0f172a;
  font-size: 1.35rem;
}

.resident-profile-page .summary-meta p {
  color: #64748b;
}

.resident-profile-page .completion-track {
  background: #e2e8f0;
}

.resident-profile-page .completion-fill {
  background: linear-gradient(90deg, #2563eb, #1d4ed8);
}

.resident-profile-page .cards-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(320px, 1fr));
  gap: 14px;
  margin-bottom: 16px;
}

.resident-profile-page .info-card {
  padding: 1rem;
  background: #fff;
}

.resident-profile-page .card-head {
  margin-bottom: 0.72rem;
}

.resident-profile-page .card-head h3 {
  color: #1e293b;
  font-size: 1rem;
  margin: 0;
}

.resident-profile-page .card-head small {
  color: #64748b;
}

.resident-profile-page .btn-link {
  background: #eff6ff;
  color: #1d4ed8;
  border-radius: 10px;
  border: 1px solid #bfdbfe;
}

.resident-profile-page .btn-link:hover {
  background: #dbeafe;
}

.resident-profile-page .btn-primary {
  background: #2563eb;
}

.resident-profile-page .btn-primary:hover {
  background: #1d4ed8;
}

.resident-profile-page .info-row {
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 0.45rem;
}

.resident-profile-page .info-row span {
  color: #64748b;
}

.resident-profile-page .info-row strong {
  color: #0f172a;
}

.resident-profile-page .edit-form {
  border-top: 1px dashed #cbd5e1;
}

.resident-profile-page .form-grid input {
  border: 1px solid #cbd5e1;
  background: #fff;
}

.resident-profile-page .form-grid input:focus {
  border-color: #60a5fa;
  outline: 2px solid rgba(96, 165, 250, 0.18);
}

@media (max-width: 992px) {
  .resident-profile-page .hero-chips {
    justify-content: flex-start;
  }

  .resident-profile-page .hero-meta {
    text-align: left !important;
    width: 100%;
  }

  .resident-profile-page .cards-grid {
    grid-template-columns: 1fr;
  }
}
</style>

<script>
(function() {
    var api = window.API_URL || '';
    var pwd = document.getElementById('residentTwoFaPassword');
    var msg = document.getElementById('residentTwoFaMsg');
    var st = document.getElementById('residentTwoFaStatus');
    var btnEn = document.getElementById('residentTwoFaEnableBtn');
    var btnDis = document.getElementById('residentTwoFaDisableBtn');
    if (!api || !pwd || !btnEn || !btnDis) {
        return;
    }
    function setUi(enabled) {
        if (st) {
            st.textContent = enabled ? 'Enabled' : 'Disabled';
        }
        btnEn.style.display = enabled ? 'none' : '';
        btnDis.style.display = enabled ? '' : 'none';
    }
    function callToggle(enable) {
        var p = pwd.value || '';
        if (!p) {
            if (msg) {
                msg.textContent = 'Enter your current password.';
                msg.className = 'small text-danger mb-0 mt-2';
            }
            return;
        }
        if (msg) {
            msg.textContent = 'Updating…';
            msg.className = 'small text-muted mb-0 mt-2';
        }
        var fd = new FormData();
        fd.append('action', 'toggle_two_factor');
        fd.append('password', p);
        fd.append('enable', enable ? '1' : '0');
        fetch(api + 'auth.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) {
                    pwd.value = '';
                    setUi(!!(d.data && d.data.two_factor_enabled));
                    if (msg) {
                        msg.textContent = d.message || 'Saved.';
                        msg.className = 'small text-success mb-0 mt-2';
                    }
                } else if (msg) {
                    msg.textContent = d.message || 'Could not update.';
                    msg.className = 'small text-danger mb-0 mt-2';
                }
            })
            .catch(function() {
                if (msg) {
                    msg.textContent = 'Network error.';
                    msg.className = 'small text-danger mb-0 mt-2';
                }
            });
    }
    btnEn.addEventListener('click', function() { callToggle(true); });
    btnDis.addEventListener('click', function() { callToggle(false); });
})();
</script>
<script src="<?php echo BASE_URL; ?>resident_profile.js?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/resident_profile.js')); ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
