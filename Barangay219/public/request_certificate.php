<?php
/**
 * E-Barangay Information Management System
 * Request Certificate (Resident)
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();

if (!isResidentView()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit();
}

// This page includes layout templates early; buffer output so POST redirects remain valid.
if (ob_get_level() === 0) {
  ob_start();
}

$page_title = 'Request Certificate';
require_once __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';

$userId = (int)(getCurrentUserId() ?? 0);
$username = $_SESSION['username'] ?? 'Resident';
$email = $_SESSION['email'] ?? '';
$residentId = (int)($_SESSION['resident_id'] ?? 0);
$debugEnabled = defined('DEBUG_MODE') && DEBUG_MODE && (($_GET['debug'] ?? '0') === '1');
$debugData = [];

function rcConnectMysqli() {
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_errno) {
        return null;
    }
    $conn->set_charset(DB_CHARSET);
    return $conn;
}

function rcFetchOne($conn, $sql, $types = '', $params = []) {
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

function rcTableExists($conn, $tableName) {
  $sql = "SELECT COUNT(*) AS total
      FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name = ?";
  $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $tableName);
  if (!$stmt->execute()) {
    $stmt->close();
    return false;
  }

  $stmt->bind_result($total);
  $stmt->fetch();
  $exists = ((int)$total) > 0;
    $stmt->close();
    return $exists;
}

function rcColumnExists($conn, $tableName, $columnName) {
  $sql = "SELECT COUNT(*) AS total
      FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return false;
  }
  $stmt->bind_param('ss', $tableName, $columnName);
  if (!$stmt->execute()) {
    $stmt->close();
    return false;
  }
  $stmt->bind_result($total);
  $stmt->fetch();
  $exists = ((int)$total) > 0;
  $stmt->close();
  return $exists;
}

function rcResolveResidentId($conn, $userId, $username, $sessionResidentId) {
  $resolvedId = (int)$sessionResidentId;

  if ($resolvedId <= 0 && $userId > 0 && rcTableExists($conn, 'users') && rcColumnExists($conn, 'users', 'resident_id')) {
    $userRow = rcFetchOne($conn, "SELECT resident_id FROM users WHERE id = ? LIMIT 1", 'i', [(int)$userId]);
    if (!empty($userRow['resident_id'])) {
      $resolvedId = (int)$userRow['resident_id'];
    }
  }

  if ($resolvedId <= 0 && $username !== '' && rcTableExists($conn, 'residents') && rcColumnExists($conn, 'residents', 'resident_code')) {
    $residentRow = rcFetchOne($conn, "SELECT id FROM residents WHERE resident_code = ? LIMIT 1", 's', [(string)$username]);
    if (!empty($residentRow['id'])) {
      $resolvedId = (int)$residentRow['id'];
      if ($userId > 0 && rcTableExists($conn, 'users') && rcColumnExists($conn, 'users', 'resident_id')) {
        $conn->query("UPDATE users SET resident_id = " . $resolvedId . " WHERE id = " . (int)$userId . " AND (resident_id IS NULL OR resident_id = 0)");
      }
    }
  }

  return $resolvedId;
}

  function rcLog($message, $context = []) {
    $suffix = '';
    if (!empty($context)) {
      $json = json_encode($context);
      $suffix = $json !== false ? ' | ' . $json : '';
    }
    error_log('[request_certificate] ' . $message . $suffix);
  }

  function rcDebugAdd(&$debugData, $key, $value) {
    $debugData[$key] = $value;
  }

  function rcBuildResidentSelectSql($conn) {
    if (!rcTableExists($conn, 'residents')) {
      return null;
    }

    $birthExpr = rcColumnExists($conn, 'residents', 'birth_date')
      ? 'birth_date'
      : (rcColumnExists($conn, 'residents', 'date_of_birth') ? 'date_of_birth' : 'NULL');
    $contactExpr = rcColumnExists($conn, 'residents', 'contact_number')
      ? 'contact_number'
      : (rcColumnExists($conn, 'residents', 'mobile_number') ? 'mobile_number' : "''");
    $residentCodeExpr = rcColumnExists($conn, 'residents', 'resident_code') ? 'resident_code' : "''";
    $verificationExpr = rcColumnExists($conn, 'residents', 'verification_status') ? 'verification_status' : "''";
    $statusExpr = rcColumnExists($conn, 'residents', 'status') ? 'status' : "''";
    $recordStatusExpr = rcColumnExists($conn, 'residents', 'record_status') ? 'record_status' : "''";
    $householdExpr = rcColumnExists($conn, 'residents', 'household_id') ? 'household_id' : 'NULL';

    return "SELECT id,
             {$residentCodeExpr} AS resident_code,
             first_name,
             middle_name,
             last_name,
             {$birthExpr} AS birth_date,
             COALESCE(gender, '') AS gender,
             COALESCE(civil_status, '') AS civil_status,
             {$contactExpr} AS contact_number,
             COALESCE(address, '') AS address,
             COALESCE({$verificationExpr}, '') AS verification_status,
             COALESCE({$statusExpr}, '') AS status,
             COALESCE({$recordStatusExpr}, '') AS record_status,
             {$householdExpr} AS household_id
        FROM residents
        WHERE %s
        LIMIT 1";
  }

function rcPrettyLabel($value) {
    $value = str_replace('_', ' ', (string)$value);
    return ucwords(trim($value));
}

function rcFormatPhone($value) {
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

function rcGenerateReferenceNumber($conn) {
  $year = date('Y');
  $prefix = 'REQ-BRGY219-' . $year . '-';
  $seq = 1;

  $row = rcFetchOne(
    $conn,
    "SELECT reference_number FROM certificate_requests WHERE reference_number LIKE ? ORDER BY id DESC LIMIT 1",
    's',
    [$prefix . '%']
  );

  if (!empty($row['reference_number']) && preg_match('/-(\d+)$/', (string)$row['reference_number'], $match)) {
    $seq = (int)$match[1] + 1;
  }

  return $prefix . str_pad((string)$seq, 5, '0', STR_PAD_LEFT);
}

$certificateOptions = [
  'Barangay Certificate',
  'Barangay Indigency'
];

$purposeOptionsByType = [
  'Barangay Certificate' => [
    'Application for Employment',
    'School Admission/Requirement',
    'Hospital Purpose',
    'Processing of Calamity',
    'Medical Purpose',
    'For Livelihood Loan',
    'Bank Transaction',
    'Indigent Family',
    'Organized Vending Permit',
    'DSWD Requirement',
    'For Travel Abroad',
    'Transfer of Residence',
    'Others'
  ],
  'Barangay Indigency' => [
    'Financial Assistance',
    'Medical Purpose',
    'Hospital Purpose',
    'DSWD Requirement',
    'Others'
  ]
];

$residentData = [
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'birth_date' => '',
    'gender' => '',
    'civil_status' => '',
    'contact_number' => '',
    'address' => '',
    'status' => '',
    'record_status' => '',
    'household_id' => null
];

$formData = [
    'certificate_type' => '',
    'purpose' => '',
    'purpose_other' => '',
    'business_name' => '',
    'business_address' => '',
    'declaration' => ''
];

  $certificatePresetMap = [
    'barangay_certificate' => 'Barangay Certificate',
    'barangay_clearance' => 'Barangay Certificate',
    'certificate_indigency' => 'Barangay Indigency',
    'barangay_indigency' => 'Barangay Indigency'
  ];

  $presetKey = strtolower(trim((string)($_GET['certificate'] ?? '')));
  if ($presetKey !== '' && isset($certificatePresetMap[$presetKey])) {
    $formData['certificate_type'] = $certificatePresetMap[$presetKey];
  }

$errors = [];
$warningMessage = '';

$residentName = $username;
$dateOfBirth = '';
$residentFullAddress = 'Barangay 219, Tondo, Manila';
$civilStatus = '';
$gender = '';
$contactNumber = '';

$eligibility = [
    'resident_verified' => false,
    'profile_complete' => false,
    'household_linked' => false,
    'request_table_ready' => false
];

$mysqli = rcConnectMysqli();
if (!$mysqli) {
    $errors[] = 'Unable to connect to the database right now. Please try again later.';
}

if ($mysqli) {
  $residentId = rcResolveResidentId($mysqli, $userId, (string)$username, (int)$residentId);
  if ($residentId > 0) {
    $_SESSION['resident_id'] = $residentId;
  }
  rcDebugAdd($debugData, 'session_user_id', $userId);
  rcDebugAdd($debugData, 'session_username', $username);
  rcDebugAdd($debugData, 'session_resident_id_initial', (int)($_SESSION['resident_id'] ?? 0));
  rcDebugAdd($debugData, 'resident_id_resolved', $residentId);
  rcLog('Resolved resident id', ['user_id' => $userId, 'username' => $username, 'resident_id' => $residentId]);
}

if ($mysqli && $residentId > 0) {
  $createTableSql = "CREATE TABLE IF NOT EXISTS certificate_requests (
    id INT(11) NOT NULL AUTO_INCREMENT,
    resident_id INT(11) NOT NULL,
    certificate_type VARCHAR(120) NOT NULL,
    purpose TEXT DEFAULT NULL,
    attachment VARCHAR(255) DEFAULT NULL,
    reference_number VARCHAR(50) NOT NULL,
    status ENUM('pending','approved','ready_for_pickup','rejected','released') NOT NULL DEFAULT 'pending',
    cert_name VARCHAR(255) DEFAULT NULL,
    cert_address TEXT DEFAULT NULL,
    cert_purpose TEXT DEFAULT NULL,
    cert_body TEXT DEFAULT NULL,
    date_issued DATE DEFAULT NULL,
    control_number VARCHAR(50) DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    admin_id INT(11) DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_reference_number (reference_number),
    KEY idx_resident_id (resident_id),
    KEY idx_status (status),
    KEY idx_certificate_type (certificate_type)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $mysqli->query($createTableSql);

    if (rcTableExists($mysqli, 'residents')) {
      $residentSelect = rcBuildResidentSelectSql($mysqli);
      $row = null;

      if ($residentSelect !== null) {
        $residentQueryById = sprintf($residentSelect, 'id = ?');
        $row = rcFetchOne($mysqli, $residentQueryById, 'i', [$residentId]);
        rcDebugAdd($debugData, 'query_by_id_found', (bool)$row);

        if (!$row && $username !== '' && rcColumnExists($mysqli, 'residents', 'resident_code')) {
          $residentQueryByCode = sprintf($residentSelect, 'resident_code = ?');
          $row = rcFetchOne($mysqli, $residentQueryByCode, 's', [$username]);
          rcDebugAdd($debugData, 'query_by_resident_code_found', (bool)$row);
          if ($row && !empty($row['id'])) {
            $residentId = (int)$row['id'];
            $_SESSION['resident_id'] = $residentId;
            if ($userId > 0 && rcTableExists($mysqli, 'users') && rcColumnExists($mysqli, 'users', 'resident_id')) {
              $fixStmt = $mysqli->prepare('UPDATE users SET resident_id = ? WHERE id = ?');
              if ($fixStmt) {
                $fixStmt->bind_param('ii', $residentId, $userId);
                $fixStmt->execute();
                $fixStmt->close();
              }
            }
          }
        }
      }

        if ($row) {
            $residentData = array_merge($residentData, $row);
            $residentName = trim(($row['first_name'] ?? '') . ' ' . (($row['middle_name'] ?? '') ? $row['middle_name'] . ' ' : '') . ($row['last_name'] ?? ''));
            if ($residentName === '') {
                $residentName = $username;
            }

            $dateOfBirth = !empty($row['birth_date']) ? date('F d, Y', strtotime($row['birth_date'])) : '';
            $residentFullAddress = (string)($row['address'] ?: 'Barangay 219, Tondo, Manila');
            $civilStatus = rcPrettyLabel($row['civil_status'] ?? '');
            $gender = rcPrettyLabel($row['gender'] ?? '');
            $contactNumber = rcFormatPhone($row['contact_number'] ?? '');

            $statusRaw = strtolower(trim((string)($row['verification_status'] ?: $row['status'] ?: $row['record_status'])));
            $eligibility['resident_verified'] = in_array($statusRaw, ['active', 'approved', 'verified'], true);
            $eligibility['profile_complete'] = !empty($row['first_name'])
                && !empty($row['last_name'])
                && !empty($row['birth_date'])
                && !empty($row['address'])
                && !empty($row['civil_status'])
                && !empty($row['gender'])
                && !empty($row['contact_number']);
            $eligibility['household_linked'] = !empty($row['household_id']);
            rcDebugAdd($debugData, 'resident_name_loaded', $residentName);
            rcDebugAdd($debugData, 'resident_code_loaded', (string)($row['resident_code'] ?? ''));
            rcDebugAdd($debugData, 'resident_status_raw', $statusRaw);
            rcDebugAdd($debugData, 'eligibility', $eligibility);
        } else {
            $errors[] = 'Resident record was not found. Please contact the barangay office.';
            rcLog('Resident lookup failed', ['resident_id' => $residentId, 'username' => $username]);
        }
    } else {
        $errors[] = 'Residents table is missing. Please run database setup.';
          rcLog('Residents table missing');
    }

    $eligibility['request_table_ready'] = rcTableExists($mysqli, 'certificate_requests');
}

if ($mysqli && $residentId <= 0) {
  $errors[] = 'Resident session is not linked to a resident profile. Please logout/login and try again.';
  rcLog('Resident session missing resident_id', ['user_id' => $userId, 'username' => $username]);
}

$canSubmit = $eligibility['resident_verified']
    && $eligibility['profile_complete']
    && $eligibility['request_table_ready']
    && empty($errors);

if (!$canSubmit && empty($errors)) {
  $warningMessage = 'Your profile is incomplete or not verified. Please update your profile before requesting certificates.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['certificate_type'] = trim((string)($_POST['certificate_type'] ?? ''));
    $formData['purpose'] = trim((string)($_POST['purpose'] ?? ''));
    $formData['purpose_other'] = trim((string)($_POST['purpose_other'] ?? ''));
    $formData['business_name'] = trim((string)($_POST['business_name'] ?? ''));
    $formData['business_address'] = trim((string)($_POST['business_address'] ?? ''));
    $formData['declaration'] = isset($_POST['declaration']) ? '1' : '';

    if (!$canSubmit) {
        $errors[] = 'You are currently not eligible to submit a certificate request.';
    }

    if (!in_array($formData['certificate_type'], $certificateOptions, true)) {
        $errors[] = 'Please select a valid certificate type.';
    }

    $isIndigencyRequest = ($formData['certificate_type'] === 'Barangay Indigency');
    if (!$isIndigencyRequest) {
      $selectedPurposeOptions = $purposeOptionsByType[$formData['certificate_type']] ?? [];
      if (!in_array($formData['purpose'], $selectedPurposeOptions, true)) {
        $errors[] = 'Please select a valid purpose category.';
      }

      if ($formData['purpose'] === 'Others' && $formData['purpose_other'] === '') {
        $errors[] = 'Please specify the purpose.';
      }
    } else {
      // Purpose is not required for Barangay Indigency requests.
      $formData['purpose'] = '';
      $formData['purpose_other'] = '';
    }

    if ($formData['declaration'] !== '1') {
        $errors[] = 'You must certify that the information is true and correct.';
    }

    $uploadedFiles = [];
    $uploadedPaths = [];

    if (!isset($_FILES['documents']) || !isset($_FILES['documents']['name']) || !is_array($_FILES['documents']['name'])) {
        $errors[] = 'Please upload at least one supporting document.';
    } else {
        $names = $_FILES['documents']['name'];
        $tmpNames = $_FILES['documents']['tmp_name'];
        $sizes = $_FILES['documents']['size'];
        $errorsUpload = $_FILES['documents']['error'];

        for ($i = 0; $i < count($names); $i++) {
            if (($errorsUpload[$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $uploadedFiles[] = [
                'name' => (string)$names[$i],
                'tmp_name' => (string)$tmpNames[$i],
                'size' => (int)$sizes[$i],
                'error' => (int)$errorsUpload[$i]
            ];
        }

        if (count($uploadedFiles) === 0) {
            $errors[] = 'Please upload at least one supporting document.';
        }

        if (count($uploadedFiles) > 3) {
            $errors[] = 'Maximum of 3 files only.';
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $allowedMime = ['image/jpeg', 'image/png', 'application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        foreach ($uploadedFiles as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'One of the files failed to upload. Please try again.';
                continue;
            }

            if ($file['size'] > (5 * 1024 * 1024)) {
                $errors[] = 'Each file must be 5MB or below.';
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExtensions, true)) {
                $errors[] = 'Only JPG, PNG, and PDF files are allowed.';
            }

            $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
            if ($mime && !in_array($mime, $allowedMime, true)) {
                $errors[] = 'Invalid file format detected.';
            }
        }

        if ($finfo) {
            finfo_close($finfo);
        }
    }

    if (empty($errors) && $mysqli) {
      // Only prevent duplicate submissions while an equivalent request is still pending review.
      // Once a request moves forward (approved/ready/released/rejected), resident can submit again.
      $duplicateSql = "SELECT id, reference_number
               FROM certificate_requests
               WHERE resident_id = ?
                 AND certificate_type = ?
                 AND status = 'pending'
               ORDER BY created_at DESC, id DESC
               LIMIT 1";
      $duplicateRow = rcFetchOne($mysqli, $duplicateSql, 'is', [$residentId, $formData['certificate_type']]);
      if ($duplicateRow) {
        $tracking = trim((string)($duplicateRow['reference_number'] ?? ''));
        if ($tracking !== '') {
          $errors[] = 'You already have a pending request for this certificate (Tracking: ' . $tracking . ').';
        } else {
          $errors[] = 'You already have a pending request for this certificate.';
        }
      }
    }

    if (empty($errors) && $mysqli) {
        $uploadDir = __DIR__ . '/uploads/request_documents';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        if (!is_dir($uploadDir)) {
            $errors[] = 'Upload directory is not writable. Please contact support.';
        } else {
            foreach ($uploadedFiles as $file) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $safeName = 'req_' . $residentId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $safeName;

                if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $errors[] = 'Unable to save uploaded files. Please try again.';
                    break;
                }

                $uploadedPaths[] = 'uploads/request_documents/' . $safeName;
            }
        }
    }

    if (empty($errors) && $mysqli) {
      $isIndigencyRequest = ($formData['certificate_type'] === 'Barangay Indigency');
      $finalPurpose = $isIndigencyRequest
        ? ''
        : ($formData['purpose'] === 'Others' ? $formData['purpose_other'] : $formData['purpose']);
      $attachmentValue = $uploadedPaths[0] ?? null;
      $referenceNumber = rcGenerateReferenceNumber($mysqli);

      $mysqli->begin_transaction();

      try {
        $insertColumns = ['resident_id', 'certificate_type', 'purpose', 'status', 'created_at'];
        $placeholders = ['?', '?', '?', "'pending'", 'NOW()'];
        $bindTypes = 'iss';
        $bindValues = [
          $residentId,
          $formData['certificate_type'],
          $finalPurpose
        ];

        if (rcColumnExists($mysqli, 'certificate_requests', 'requested_by')) {
          $insertColumns[] = 'requested_by';
          $placeholders[] = '?';
          $bindTypes .= 'i';
          $bindValues[] = max(0, (int)$userId);
        }

        if (rcColumnExists($mysqli, 'certificate_requests', 'attachment')) {
          $insertColumns[] = 'attachment';
          $placeholders[] = '?';
          $bindTypes .= 's';
          $bindValues[] = $attachmentValue;
        }

        if (rcColumnExists($mysqli, 'certificate_requests', 'reference_number')) {
          $insertColumns[] = 'reference_number';
          $placeholders[] = '?';
          $bindTypes .= 's';
          $bindValues[] = $referenceNumber;
        }

        if (rcColumnExists($mysqli, 'certificate_requests', 'purpose_option')) {
          $insertColumns[] = 'purpose_option';
          $placeholders[] = '?';
          $bindTypes .= 's';
          $bindValues[] = $isIndigencyRequest ? null : $formData['purpose'];
        }

        if (rcColumnExists($mysqli, 'certificate_requests', 'purpose_other')) {
          $insertColumns[] = 'purpose_other';
          $placeholders[] = '?';
          $bindTypes .= 's';
          $bindValues[] = ($formData['purpose'] === 'Others') ? $formData['purpose_other'] : null;
        }

        if (rcColumnExists($mysqli, 'certificate_requests', 'application_ref')) {
          $insertColumns[] = 'application_ref';
          $placeholders[] = '?';
          $bindTypes .= 's';
          $bindValues[] = 'APP-' . date('Y') . '-' . str_pad((string)mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        }

        $insertSql = 'INSERT INTO certificate_requests (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $mysqli->prepare($insertSql);
        if (!$stmt) {
          throw new Exception('Failed to prepare insert statement.');
        }

        $stmt->bind_param($bindTypes, ...$bindValues);

        if (!$stmt->execute()) {
          $dbError = $stmt->error ?: $mysqli->error;
          $stmt->close();
          rcLog('Request insert failed', ['error' => $dbError, 'sql' => $insertSql]);
          throw new Exception('Failed to save request.');
        }

        $stmt->close();
        $mysqli->commit();

        $redirectUrl = BASE_URL . 'request_confirmation.php?tracking=' . urlencode($referenceNumber);
        if (!headers_sent()) {
          header('Location: ' . $redirectUrl);
          exit();
        }

        // Fallback redirect when shared layout already produced output.
        $safeRedirectUrl = json_encode($redirectUrl);
        echo '<script>window.location.replace(' . $safeRedirectUrl . ');</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8') . '"></noscript>';
        exit();
      } catch (Exception $ex) {
        $mysqli->rollback();
        $errors[] = $ex->getMessage();
      }
    }
}

if ($mysqli) {
    $mysqli->close();
}

$purposeOptions = $purposeOptionsByType[$formData['certificate_type']] ?? [];
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>request_certificate.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/request_certificate.css')); ?>">

<div class="main-content module-page">
  <div class="container-fluid">
  <div class="resident-request-certificate">
    <section class="page-head">
      <div>
        <p class="portal-tag">RESIDENT PORTAL</p>
        <h2>Request Certificate</h2>
        <p class="page-subtitle">Submit an official certificate request and receive a tracking code.</p>
      </div>
      <div class="head-meta">
        <span class="view-badge">Resident View</span>
        <span class="date-badge" id="mainDateBadge"><?php echo date('F d, Y'); ?></span>
      </div>
    </section>

    <?php if (!empty($errors)): ?>
      <section class="notice error-notice">
        <h4>Unable to Submit Request</h4>
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <?php if ($warningMessage !== ''): ?>
      <section class="notice warning-notice" id="eligibilityWarning">
        <h4>Eligibility Check Required</h4>
        <p><?php echo htmlspecialchars($warningMessage); ?></p>
      </section>
    <?php endif; ?>

    <?php if ($debugEnabled): ?>
      <section class="notice warning-notice">
        <h4>Debug Output (Temporary)</h4>
        <pre style="white-space:pre-wrap"><?php echo htmlspecialchars(json_encode($debugData, JSON_PRETTY_PRINT)); ?></pre>
      </section>
    <?php endif; ?>

    <form id="requestForm" method="POST" enctype="multipart/form-data" novalidate>
      <section class="card form-card">
        <div class="card-head">
          <h3><i class="fa-regular fa-file-lines"></i> Certificate Selection</h3>
        </div>
        <div class="form-grid two-col">
          <label class="field">
            <span>Certificate Type</span>
            <select id="certificateType" name="certificate_type" required>
              <option value="">Select certificate type</option>
              <?php foreach ($certificateOptions as $option): ?>
                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $formData['certificate_type'] === $option ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($option); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small class="error" id="certificateTypeError"></small>
          </label>

          <label class="field" id="purposeFieldWrap">
            <span>Purpose</span>
            <select id="purpose" name="purpose" required>
              <option value="">Select purpose</option>
              <?php foreach ($purposeOptions as $option): ?>
                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $formData['purpose'] === $option ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($option); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small class="error" id="purposeError"></small>
          </label>

          <label class="field hidden" id="purposeOtherWrap">
            <span>Specify Purpose</span>
            <input type="text" id="purposeOther" name="purpose_other" maxlength="120" value="<?php echo htmlspecialchars($formData['purpose_other']); ?>" placeholder="Type your specific purpose">
            <small class="error" id="purposeOtherError"></small>
          </label>

          <label class="field hidden" id="businessNameWrap">
            <span>Business Name</span>
            <input type="text" id="businessName" name="business_name" maxlength="180" value="<?php echo htmlspecialchars($formData['business_name']); ?>" placeholder="Enter business name">
            <small class="error" id="businessNameError"></small>
          </label>

          <label class="field hidden" id="businessAddressWrap">
            <span>Business Address</span>
            <input type="text" id="businessAddress" name="business_address" maxlength="255" value="<?php echo htmlspecialchars($formData['business_address']); ?>" placeholder="Enter business address">
            <small class="error" id="businessAddressError"></small>
          </label>
        </div>
      </section>

      <section class="card form-card">
        <div class="card-head">
          <h3><i class="fa-regular fa-id-card"></i> Resident Information (Auto Filled)</h3>
        </div>
        <div class="form-grid two-col">
          <label class="field">
            <span>Full Name</span>
            <input type="text" value="<?php echo htmlspecialchars($residentName); ?>" readonly>
          </label>
          <label class="field">
            <span>Date of Birth</span>
            <input type="text" value="<?php echo htmlspecialchars($dateOfBirth); ?>" readonly>
          </label>
          <label class="field">
            <span>Address</span>
            <input type="text" value="<?php echo htmlspecialchars($residentFullAddress); ?>" readonly>
          </label>
          <label class="field">
            <span>Civil Status</span>
            <input type="text" value="<?php echo htmlspecialchars($civilStatus); ?>" readonly>
          </label>
          <label class="field">
            <span>Gender</span>
            <input type="text" value="<?php echo htmlspecialchars($gender); ?>" readonly>
          </label>
          <label class="field">
            <span>Contact Number</span>
            <input type="text" value="<?php echo htmlspecialchars($contactNumber); ?>" readonly>
          </label>
        </div>

        <div class="eligibility-list">
          <span class="eligibility-pill <?php echo $eligibility['resident_verified'] ? 'ok' : 'bad'; ?>">Resident Verified</span>
          <span class="eligibility-pill <?php echo $eligibility['profile_complete'] ? 'ok' : 'bad'; ?>">Profile Complete</span>
          <span class="eligibility-pill ok">Household Linked (Optional)</span>
        </div>
      </section>

      <section class="card form-card">
        <div class="card-head">
          <h3><i class="fa-solid fa-list-check"></i> Required Documents</h3>
        </div>
        <ul class="requirements-list" id="requirementsList">
          <li>Select a certificate type to view document requirements.</li>
        </ul>
      </section>

      <section class="card form-card">
        <div class="card-head">
          <h3><i class="fa-solid fa-cloud-arrow-up"></i> File Uploads</h3>
        </div>
        <div class="upload-box" id="uploadBox">
          <input type="file" id="documents" name="documents[]" accept=".jpg,.jpeg,.png,.pdf" multiple hidden>
          <button type="button" class="btn-ghost" id="browseBtn"><i class="fa-regular fa-folder-open"></i> Choose Files</button>
          <p>Upload up to 3 files (JPG, PNG, PDF)</p>
          <small>Max size: 5MB each</small>
        </div>
        <ul class="file-list" id="fileList"></ul>
        <small class="error" id="documentsError"></small>
      </section>

      <section class="card form-card">
        <div class="card-head">
          <h3><i class="fa-solid fa-receipt"></i> Request Summary</h3>
        </div>
        <div class="summary-grid">
          <div class="summary-item"><span>Certificate Type</span><strong id="summaryCertificate">-</strong></div>
          <div class="summary-item"><span>Purpose</span><strong id="summaryPurpose">-</strong></div>
          <div class="summary-item"><span>Resident Name</span><strong id="summaryName"><?php echo htmlspecialchars($residentName); ?></strong></div>
          <div class="summary-item"><span>Address</span><strong id="summaryAddress"><?php echo htmlspecialchars($residentFullAddress); ?></strong></div>
          <div class="summary-item"><span>Uploaded Documents</span><strong id="summaryDocuments">None</strong></div>
        </div>

        <div class="processing-notice">
          <p><strong>Processing Time:</strong> 1-2 working days</p>
          <p><strong>Claim Location:</strong> Barangay Hall Records Section</p>
          <p><strong>Reminder:</strong> Residents must bring a valid ID when claiming the document.</p>
        </div>

        <div class="status-legend">
          <span class="state-badge submitted">Pending</span>
          <span class="state-badge approved">Approved</span>
          <span class="state-badge ready">Ready for Pickup</span>
          <span class="state-badge released">Released</span>
          <span class="state-badge rejected">Rejected</span>
        </div>

        <label class="declaration">
          <input type="checkbox" id="declaration" name="declaration" value="1" <?php echo $formData['declaration'] === '1' ? 'checked' : ''; ?>>
          <span>I certify that the information provided is true and correct.</span>
        </label>
        <small class="error" id="declarationError"></small>

        <div class="actions">
          <button type="submit" class="btn-primary" id="submitBtn" <?php echo $canSubmit ? '' : 'disabled'; ?>><i class="fa-regular fa-paper-plane"></i> Submit Request</button>
          <button type="reset" class="btn-secondary" id="resetBtn">Clear Form</button>
        </div>
      </section>
    </form>
  </div>
  </div>
</div>

<script src="<?php echo BASE_URL; ?>request_certificate.js?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/request_certificate.js')); ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
