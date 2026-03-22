<?php
/**
 * E-Barangay Information Management System
 * Resident Dashboard
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

// Require login and check if user is a resident
requireLogin();

if (!isResidentView()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit();
}

// Use officials layout components for consistent header/sidebar
$page_title = 'Resident Dashboard';
require_once __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';

// Get user information
$userId = getCurrentUserId();
$username = $_SESSION['username'] ?? 'Resident';
$email = $_SESSION['email'] ?? '';
$residentId = $_SESSION['resident_id'] ?? null;

function residentDashboardConnectMysqli() {
  mysqli_report(MYSQLI_REPORT_OFF);
  $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  if ($conn->connect_errno) {
    return null;
  }
  $conn->set_charset(DB_CHARSET);
  return $conn;
}

function residentDashboardPrettyLabel($value) {
  $value = str_replace('_', ' ', (string)$value);
  return ucwords(trim($value));
}

function residentDashboardTableExists($conn, $tableName) {
  $sql = 'SHOW TABLES LIKE ?';
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return false;
  }
  $stmt->bind_param('s', $tableName);
  $stmt->execute();
  $result = $stmt->get_result();
  $exists = $result && $result->num_rows > 0;
  $stmt->close();
  return $exists;
}

function residentDashboardTableColumns($conn, $tableName) {
  $columns = [];
  $sql = "SHOW COLUMNS FROM `" . $conn->real_escape_string($tableName) . "`";
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

function residentDashboardColumnExists($conn, $tableName, $columnName) {
  if (!residentDashboardTableExists($conn, $tableName)) {
    return false;
  }
  $columns = residentDashboardTableColumns($conn, $tableName);
  return in_array($columnName, $columns, true);
}

function residentDashboardFetchOne($conn, $sql, $types = '', $params = []) {
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

function residentDashboardFetchAll($conn, $sql, $types = '', $params = []) {
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

function residentDashboardResolveResidentId($conn, $userId, $username, $sessionResidentId, $email = '') {
  $resolvedId = (int)$sessionResidentId;

  if ($resolvedId <= 0 && $userId > 0 && residentDashboardColumnExists($conn, 'users', 'resident_id')) {
    $userRow = residentDashboardFetchOne($conn, 'SELECT resident_id FROM users WHERE id = ? LIMIT 1', 'i', [(int)$userId]);
    if (!empty($userRow['resident_id'])) {
      $resolvedId = (int)$userRow['resident_id'];
    }
  }

  if ($resolvedId <= 0 && $username !== '' && residentDashboardColumnExists($conn, 'residents', 'resident_code')) {
    $residentRow = residentDashboardFetchOne($conn, 'SELECT id FROM residents WHERE resident_code = ? LIMIT 1', 's', [(string)$username]);
    if (!empty($residentRow['id'])) {
      $resolvedId = (int)$residentRow['id'];

      if ($userId > 0 && residentDashboardColumnExists($conn, 'users', 'resident_id')) {
        $sql = 'UPDATE users SET resident_id = ? WHERE id = ? AND (resident_id IS NULL OR resident_id = 0)';
        $stmt = $conn->prepare($sql);
        if ($stmt) {
          $stmt->bind_param('ii', $resolvedId, $userId);
          $stmt->execute();
          $stmt->close();
        }
      }
    }
  }

  if ($resolvedId <= 0 && $email !== '' && residentDashboardColumnExists($conn, 'residents', 'email')) {
    $residentRow = residentDashboardFetchOne($conn, 'SELECT id FROM residents WHERE email = ? LIMIT 1', 's', [(string)$email]);
    if (!empty($residentRow['id'])) {
      $resolvedId = (int)$residentRow['id'];

      if ($userId > 0 && residentDashboardColumnExists($conn, 'users', 'resident_id')) {
        $sql = 'UPDATE users SET resident_id = ? WHERE id = ? AND (resident_id IS NULL OR resident_id = 0)';
        $stmt = $conn->prepare($sql);
        if ($stmt) {
          $stmt->bind_param('ii', $resolvedId, $userId);
          $stmt->execute();
          $stmt->close();
        }
      }
    }
  }

  return $resolvedId;
}

function residentDashboardStatusClass($status) {
  $normalized = strtolower(trim(str_replace('_', ' ', (string)$status)));
  if (in_array($normalized, ['pending', 'submitted', 'under review'], true)) {
    return 'status-pending';
  }
  if (in_array($normalized, ['approved', 'ready for pickup', 'issued', 'released'], true)) {
    return 'status-approved';
  }
  if ($normalized === 'rejected') {
    return 'status-rejected';
  }
  if ($normalized === 'cancelled' || $normalized === 'canceled') {
    return 'status-cancelled';
  }
  return 'status-pending';
}

function residentDashboardTrackerClass($status) {
  $normalized = strtolower(trim(str_replace('_', ' ', (string)$status)));
  if ($normalized === 'submitted') {
    return 'tracker-submitted';
  }
  if ($normalized === 'under review' || $normalized === 'pending') {
    return 'tracker-review';
  }
  if ($normalized === 'approved') {
    return 'tracker-approved';
  }
  if ($normalized === 'ready for pickup' || $normalized === 'issued') {
    return 'tracker-ready';
  }
  if ($normalized === 'released' || $normalized === 'completed') {
    return 'tracker-released';
  }
  if ($normalized === 'cancelled' || $normalized === 'canceled' || $normalized === 'rejected') {
    return 'tracker-cancelled';
  }
  return 'tracker-submitted';
}

$residentName = $username;
$residentProfile = [
  'avatar' => 'https://i.pravatar.cc/160?img=12',
  'full_name' => $username,
  'resident_id' => $username,
  'resident_status' => 'Pending Verification',
  'household_status' => 'Not Linked'
];

$stats = [
  'total_requests' => 0,
  'pending_requests' => 0,
  'approved_documents' => 0,
  'active_announcements' => 0
];

$latestRequest = null;
$recentRequests = [];
$recentAnnouncements = [];
$dashboardNotifications = [];

$householdSnapshot = [
  'linked' => false,
  'household_id' => null,
  'head_name' => '-',
  'member_count' => 0,
  'address' => '-',
  'emergency_contact_name' => '',
  'emergency_contact_number' => ''
];

$residentEmergencyContact = [
  'name' => '',
  'number' => '',
  'relationship' => ''
];

$dashboardEmergencyContacts = [
  ['label' => 'Barangay Office', 'number' => '(02) 8242-2190'],
  ['label' => 'Police Station', 'number' => '911 / (02) 8527-0000'],
  ['label' => 'Fire Department', 'number' => '911 / (02) 8426-0219'],
  ['label' => 'Health Center', 'number' => '(02) 8731-1122']
];

$requestTable = null;
$requestTypeColumn = null;
$requestDateColumn = null;
$requestIdColumn = 'id';

$conn = residentDashboardConnectMysqli();
if ($conn) {
  $residentId = residentDashboardResolveResidentId($conn, (int)$userId, (string)$username, (int)$residentId, (string)$email);
  if ($residentId > 0) {
    $_SESSION['resident_id'] = $residentId;
  }
}

if ($conn && (int)$residentId > 0) {
  if (residentDashboardTableExists($conn, 'residents')) {
    $residentCols = residentDashboardTableColumns($conn, 'residents');
    $selectResidentParts = ['id', 'first_name', 'middle_name', 'last_name', 'address', 'contact_number'];

    if (in_array('status', $residentCols, true)) {
      $selectResidentParts[] = 'status';
    }
    if (in_array('verification_status', $residentCols, true)) {
      $selectResidentParts[] = 'verification_status';
    }
    if (in_array('record_status', $residentCols, true)) {
      $selectResidentParts[] = 'record_status';
    }
    if (in_array('resident_code', $residentCols, true)) {
      $selectResidentParts[] = 'resident_code';
    }
    if (in_array('household_id', $residentCols, true)) {
      $selectResidentParts[] = 'household_id';
    }
    if (in_array('profile_image', $residentCols, true)) {
      $selectResidentParts[] = 'profile_image';
    }
    if (in_array('emergency_contact_name', $residentCols, true)) {
      $selectResidentParts[] = 'emergency_contact_name';
    }
    if (in_array('emergency_contact_number', $residentCols, true)) {
      $selectResidentParts[] = 'emergency_contact_number';
    }
    if (in_array('emergency_contact_relationship', $residentCols, true)) {
      $selectResidentParts[] = 'emergency_contact_relationship';
    }

    $residentSql = 'SELECT ' . implode(', ', $selectResidentParts) . ' FROM residents WHERE id = ? LIMIT 1';
    $residentRow = residentDashboardFetchOne($conn, $residentSql, 'i', [(int)$residentId]);

    if ($residentRow) {
      $residentName = trim(($residentRow['first_name'] ?? '') . ' ' . (($residentRow['middle_name'] ?? '') ? $residentRow['middle_name'] . ' ' : '') . ($residentRow['last_name'] ?? ''));
      if ($residentName === '') {
        $residentName = $username;
      }

      $residentProfile['full_name'] = $residentName;
      if (!empty($residentRow['resident_code'])) {
        $residentProfile['resident_id'] = (string)$residentRow['resident_code'];
      }

      if (!empty($residentRow['profile_image'])) {
        $residentProfile['avatar'] = htmlspecialchars((string)$residentRow['profile_image']);
      }

      $residentEmergencyContact['name'] = (string)($residentRow['emergency_contact_name'] ?? '');
      $residentEmergencyContact['number'] = (string)($residentRow['emergency_contact_number'] ?? '');
      $residentEmergencyContact['relationship'] = (string)($residentRow['emergency_contact_relationship'] ?? '');

      $residentStatusRaw = strtolower(trim((string)(
        ($residentRow['verification_status'] ?? '')
        ?: ($residentRow['record_status'] ?? '')
        ?: ($residentRow['status'] ?? '')
      )));
      $isProfileIncomplete = empty($residentRow['first_name']) || empty($residentRow['last_name']) || empty($residentRow['address']) || empty($residentRow['contact_number']);
      if ($isProfileIncomplete) {
        $residentProfile['resident_status'] = 'Incomplete Profile';
      } elseif (in_array($residentStatusRaw, ['active', 'approved', 'verified'], true)) {
        $residentProfile['resident_status'] = 'Verified';
      } else {
        $residentProfile['resident_status'] = 'Pending Verification';
      }

      if (!empty($residentRow['household_id'])) {
        $householdSnapshot['linked'] = true;
        $householdSnapshot['household_id'] = (int)$residentRow['household_id'];
        $residentProfile['household_status'] = 'Linked';
      }
    }
  }

  if (residentDashboardTableExists($conn, 'certificate_requests')) {
    $requestTable = 'certificate_requests';
  } elseif (residentDashboardTableExists($conn, 'document_requests')) {
    $requestTable = 'document_requests';
  }

  if ($requestTable !== null) {
    $requestColumns = residentDashboardTableColumns($conn, $requestTable);
    $requestTypeColumn = in_array('document_type', $requestColumns, true) ? 'document_type' : (in_array('certificate_type', $requestColumns, true) ? 'certificate_type' : null);
    $requestDateColumn = in_array('date_requested', $requestColumns, true) ? 'date_requested' : (in_array('created_at', $requestColumns, true) ? 'created_at' : null);
    if (in_array('request_id', $requestColumns, true)) {
      $requestIdColumn = 'request_id';
    }

    if ($requestTypeColumn && $requestDateColumn) {
      $statsSql = "SELECT
              COUNT(*) AS total_requests,
              COALESCE(SUM(LOWER(REPLACE(status, '_', ' ')) IN ('pending', 'under review')), 0) AS pending_requests,
              COALESCE(SUM(LOWER(REPLACE(status, '_', ' ')) IN ('approved', 'ready for pickup', 'issued')), 0) AS approved_documents
             FROM {$requestTable}
             WHERE resident_id = ?";
      $statsRow = residentDashboardFetchOne($conn, $statsSql, 'i', [(int)$residentId]);
      if ($statsRow) {
        $stats['total_requests'] = (int)$statsRow['total_requests'];
        $stats['pending_requests'] = (int)$statsRow['pending_requests'];
        $stats['approved_documents'] = (int)$statsRow['approved_documents'];
      }

      $latestSql = "SELECT {$requestIdColumn} AS request_id, {$requestTypeColumn} AS request_type, {$requestDateColumn} AS requested_at, status
              FROM {$requestTable}
              WHERE resident_id = ?
              ORDER BY {$requestDateColumn} DESC
              LIMIT 1";
      $latestRequest = residentDashboardFetchOne($conn, $latestSql, 'i', [(int)$residentId]);
      if ($latestRequest && strtolower((string)$latestRequest['status']) === 'pending') {
        $latestRequest['status'] = 'Submitted';
      }

      $recentSql = "SELECT {$requestIdColumn} AS request_id, {$requestTypeColumn} AS request_type, {$requestDateColumn} AS requested_at, status
              FROM {$requestTable}
              WHERE resident_id = ?
              ORDER BY {$requestDateColumn} DESC
              LIMIT 5";
      $recentRequests = residentDashboardFetchAll($conn, $recentSql, 'i', [(int)$residentId]);
    }
  }

  if (residentDashboardTableExists($conn, 'announcements')) {
    // Count both old schema (active) and new schema (published) announcements
    $annCountRow = residentDashboardFetchOne($conn, "SELECT COUNT(*) AS total FROM announcements WHERE status = 'active' OR status = 'published'");
    $stats['active_announcements'] = (int)($annCountRow['total'] ?? 0);
    
    // Announcements are now loaded via JavaScript API and dashboard-announcements.js
  }

  if ($householdSnapshot['linked'] && residentDashboardTableExists($conn, 'households')) {
    $householdId = (int)$householdSnapshot['household_id'];
    $householdColumns = residentDashboardTableColumns($conn, 'households');
    $allowedHouseholdFields = ['id', 'family_head_id', 'total_members', 'address', 'house_number', 'street', 'purok_sitio', 'barangay', 'city', 'province', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_number'];
    $availableHouseholdFields = [];
    foreach ($allowedHouseholdFields as $field) {
      if (in_array($field, $householdColumns, true)) {
        $availableHouseholdFields[] = $field;
      }
    }

    if (!in_array('id', $availableHouseholdFields, true)) {
      $availableHouseholdFields[] = 'id';
    }

    $householdSelect = implode(', ', $availableHouseholdFields);
    $householdRow = residentDashboardFetchOne(
      $conn,
      "SELECT {$householdSelect}
       FROM households
       WHERE id = ?
       LIMIT 1",
      'i',
      [$householdId]
    );

    if ($householdRow) {
      $householdSnapshot['household_id'] = (int)$householdRow['id'];
      $householdSnapshot['member_count'] = (int)($householdRow['total_members'] ?? 0);

      $addressParts = [];
      foreach (['house_number', 'street', 'purok_sitio', 'barangay', 'city', 'province'] as $part) {
        if (!empty($householdRow[$part])) {
          $addressParts[] = trim((string)$householdRow[$part]);
        }
      }
      $householdSnapshot['address'] = !empty($householdRow['address'])
        ? (string)$householdRow['address']
        : (!empty($addressParts) ? implode(', ', $addressParts) : '-');
      $householdSnapshot['emergency_contact_name'] = (string)($householdRow['emergency_contact_name'] ?? '');
      $householdSnapshot['emergency_contact_number'] = (string)(
        $householdRow['emergency_contact_number']
        ?? ($householdRow['emergency_contact_phone'] ?? '')
      );

      if (residentDashboardTableExists($conn, 'household_members')) {
        $memberRow = residentDashboardFetchOne(
          $conn,
          'SELECT COUNT(*) AS total FROM household_members WHERE household_id = ?',
          'i',
          [$householdId]
        );
        if ($memberRow) {
          $householdSnapshot['member_count'] = max($householdSnapshot['member_count'], (int)$memberRow['total']);
        }
      }

      if (!empty($householdRow['family_head_id'])) {
        $headRow = residentDashboardFetchOne(
          $conn,
          'SELECT first_name, middle_name, last_name FROM residents WHERE id = ? LIMIT 1',
          'i',
          [(int)$householdRow['family_head_id']]
        );
        if ($headRow) {
          $householdSnapshot['head_name'] = trim(($headRow['first_name'] ?? '') . ' ' . (($headRow['middle_name'] ?? '') ? $headRow['middle_name'] . ' ' : '') . ($headRow['last_name'] ?? ''));
        }
      }
    } else {
      $householdSnapshot['linked'] = false;
      $residentProfile['household_status'] = 'Not Linked';
    }
  }

  if (residentDashboardTableExists($conn, 'notifications')) {
    $notificationColumns = residentDashboardTableColumns($conn, 'notifications');
    $messageColumn = in_array('message', $notificationColumns, true) ? 'message' : (in_array('content', $notificationColumns, true) ? 'content' : 'title');
    $titleColumn = in_array('title', $notificationColumns, true) ? 'title' : null;
    $dateColumn = in_array('created_at', $notificationColumns, true) ? 'created_at' : (in_array('date_created', $notificationColumns, true) ? 'date_created' : (in_array('date_posted', $notificationColumns, true) ? 'date_posted' : null));

    if ($messageColumn && $dateColumn) {
      $selectTitle = $titleColumn ? "{$titleColumn} AS title," : "'' AS title,";
      $notificationSql = "SELECT id, {$selectTitle} {$messageColumn} AS message, {$dateColumn} AS created_at
                FROM notifications";
      $whereParts = [];
      $bindTypes = '';
      $bindValues = [];

      if (in_array('resident_id', $notificationColumns, true)) {
        $whereParts[] = 'resident_id = ?';
        $bindTypes .= 'i';
        $bindValues[] = (int)$residentId;
      } elseif (in_array('user_id', $notificationColumns, true)) {
        $whereParts[] = 'user_id = ?';
        $bindTypes .= 'i';
        $bindValues[] = (int)$userId;
      }

      if (in_array('is_read', $notificationColumns, true)) {
        $whereParts[] = 'is_read = 0';
      } elseif (in_array('status', $notificationColumns, true)) {
        $whereParts[] = "LOWER(status) IN ('unread', 'new', 'pending')";
      }

      if (!empty($whereParts)) {
        $notificationSql .= ' WHERE ' . implode(' AND ', $whereParts);
      }

      $notificationSql .= " ORDER BY {$dateColumn} DESC LIMIT 5";
      $dashboardNotifications = residentDashboardFetchAll($conn, $notificationSql, $bindTypes, $bindValues);
    }
  }

  $contactTableCandidates = ['emergency_contacts', 'contact_numbers', 'hotlines'];
  foreach ($contactTableCandidates as $contactTable) {
    if (!residentDashboardTableExists($conn, $contactTable)) {
      continue;
    }

    $contactCols = residentDashboardTableColumns($conn, $contactTable);
    $labelCol = in_array('label', $contactCols, true)
      ? 'label'
      : (in_array('name', $contactCols, true)
        ? 'name'
        : (in_array('contact_name', $contactCols, true) ? 'contact_name' : null));
    $numberCol = in_array('number', $contactCols, true)
      ? 'number'
      : (in_array('phone', $contactCols, true)
        ? 'phone'
        : (in_array('contact_number', $contactCols, true)
          ? 'contact_number'
          : (in_array('mobile_number', $contactCols, true) ? 'mobile_number' : null)));

    if (!$labelCol || !$numberCol) {
      continue;
    }

    $where = [];
    if (in_array('is_active', $contactCols, true)) {
      $where[] = 'is_active = 1';
    } elseif (in_array('status', $contactCols, true)) {
      $where[] = "LOWER(status) IN ('active', 'published', 'enabled')";
    }

    $orderBy = in_array('priority', $contactCols, true)
      ? 'priority ASC'
      : (in_array('sort_order', $contactCols, true)
        ? 'sort_order ASC'
        : (in_array('id', $contactCols, true) ? 'id ASC' : $labelCol . ' ASC'));

    $sql = "SELECT {$labelCol} AS label, {$numberCol} AS number FROM {$contactTable}";
    if (!empty($where)) {
      $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= " ORDER BY {$orderBy} LIMIT 6";

    $liveContacts = residentDashboardFetchAll($conn, $sql);
    if (!empty($liveContacts)) {
      $dashboardEmergencyContacts = [];
      foreach ($liveContacts as $item) {
        $label = trim((string)($item['label'] ?? ''));
        $number = trim((string)($item['number'] ?? ''));
        if ($label !== '' && $number !== '') {
          $dashboardEmergencyContacts[] = ['label' => $label, 'number' => $number];
        }
      }
      if (!empty($dashboardEmergencyContacts)) {
        break;
      }
    }
  }

  $conn->close();
}

if ($latestRequest) {
  $latestStatus = strtolower(trim(str_replace('_', ' ', (string)($latestRequest['status'] ?? ''))));
  if (in_array($latestStatus, ['ready for pickup', 'issued'], true)) {
    $dashboardNotifications[] = [
      'title' => 'Document Ready for Pickup',
      'message' => 'One of your document requests is ready for pickup at the barangay office.',
      'created_at' => date('Y-m-d H:i:s')
    ];
  }
}

if ($residentProfile['resident_status'] === 'Incomplete Profile') {
  $dashboardNotifications[] = [
    'title' => 'Profile Incomplete',
    'message' => 'Please update your resident profile details to unlock all services.',
    'created_at' => date('Y-m-d H:i:s')
  ];
}

if (!$householdSnapshot['linked']) {
  $dashboardNotifications[] = [
    'title' => 'Household Not Linked',
    'message' => 'Your account is not yet associated with a household record.',
    'created_at' => date('Y-m-d H:i:s')
  ];
}

if (count($dashboardNotifications) > 5) {
  $dashboardNotifications = array_slice($dashboardNotifications, 0, 5);
}

$residentStatusBadgeClass = 'text-bg-warning';
if ($residentProfile['resident_status'] === 'Verified') {
  $residentStatusBadgeClass = 'text-bg-success';
} elseif ($residentProfile['resident_status'] === 'Incomplete Profile') {
  $residentStatusBadgeClass = 'text-bg-danger';
}

$householdStatusBadgeClass = $residentProfile['household_status'] === 'Linked' ? 'text-bg-primary' : 'text-bg-secondary';
?>
<div class="main-content dashboard-page resident-dashboard-page resident-theme" id="mainContent">
  <div class="container-fluid">
    <div class="dashboard-hero card border-0 shadow-sm mb-4">
      <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="hero-copy">
          <p class="hero-kicker text-uppercase small mb-1">Resident Services Portal</p>
          <h2 class="mb-1"><i class="bi bi-house-heart me-2"></i>Resident Dashboard</h2>
          <p class="hero-subtitle mb-0">Track your requests, household details, announcements, and account updates in one place.</p>
        </div>
        <div class="text-md-end hero-meta">
          <span class="hero-date-badge fs-6 px-3 py-2" id="mainDateBadge">
            <i class="bi bi-calendar3 me-1"></i><?php echo date('F d, Y'); ?>
          </span>
          <div class="hero-chips mt-2">
            <span class="hero-chip"><i class="bi bi-person-check me-1"></i>Resident View</span>
            <span class="hero-chip"><i class="bi bi-shield-lock me-1"></i>Account Secure</span>
          </div>
        </div>
      </div>
    </div>

    <div class="card dash-panel mb-4">
      <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
          <img class="resident-avatar" src="<?php echo htmlspecialchars($residentProfile['avatar']); ?>" alt="Resident profile image">
          <div>
            <h5 class="mb-1"><?php echo htmlspecialchars($residentProfile['full_name']); ?></h5>
            <p class="text-muted mb-1"><?php echo htmlspecialchars($email ?: 'No email on file'); ?></p>
            <p class="mb-0 resident-meta"><strong>Resident ID:</strong> <?php echo htmlspecialchars($residentProfile['resident_id'] ?: $username); ?></p>
          </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
          <span class="badge <?php echo $residentStatusBadgeClass; ?> px-3 py-2">Status: <?php echo htmlspecialchars($residentProfile['resident_status']); ?></span>
          <span class="badge <?php echo $householdStatusBadgeClass; ?> px-3 py-2">Household: <?php echo htmlspecialchars($residentProfile['household_status']); ?></span>
        </div>
      </div>
    </div>

    <div class="row g-3 mb-4" aria-label="Resident dashboard statistics">
      <div class="col-6 col-lg-3">
        <a href="<?php echo BASE_URL; ?>my_requests.php" class="text-decoration-none dashboard-kpi-link">
          <div class="dashboard-kpi-card kpi-primary">
            <div class="kpi-icon"><i class="bi bi-folder2-open"></i></div>
            <div class="kpi-value"><?php echo (int)$stats['total_requests']; ?></div>
            <div class="kpi-label">My Requests</div>
          </div>
        </a>
      </div>
      <div class="col-6 col-lg-3">
        <a href="<?php echo BASE_URL; ?>my_requests.php?status=Pending" class="text-decoration-none dashboard-kpi-link">
          <div class="dashboard-kpi-card kpi-teal">
            <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
            <div class="kpi-value"><?php echo (int)$stats['pending_requests']; ?></div>
            <div class="kpi-label">Pending Requests</div>
          </div>
        </a>
      </div>
      <div class="col-6 col-lg-3">
        <a href="<?php echo BASE_URL; ?>my_requests.php?status=Approved" class="text-decoration-none dashboard-kpi-link">
          <div class="dashboard-kpi-card kpi-sky">
            <div class="kpi-icon"><i class="bi bi-patch-check"></i></div>
            <div class="kpi-value"><?php echo (int)$stats['approved_documents']; ?></div>
            <div class="kpi-label">Approved Documents</div>
          </div>
        </a>
      </div>
      <div class="col-6 col-lg-3">
        <a href="<?php echo BASE_URL; ?>resident_announcements.php" class="text-decoration-none dashboard-kpi-link">
          <div class="dashboard-kpi-card kpi-amber">
            <div class="kpi-icon"><i class="bi bi-megaphone"></i></div>
            <div class="kpi-value"><?php echo (int)$stats['active_announcements']; ?></div>
            <div class="kpi-label">Announcements</div>
          </div>
        </a>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-xl-7">
        <div class="card dash-panel h-100">
          <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-lightning-charge me-2 text-primary"></i>Quick Actions</h6>
          </div>
          <div class="card-body pt-0">
            <div class="quick-actions-grid mb-3">
              <a class="quick-action-btn" href="<?php echo BASE_URL; ?>request_certificate.php?certificate=barangay_certificate"><i class="bi bi-file-earmark-text"></i><span>Request Barangay Certificate</span></a>
              <a class="quick-action-btn" href="<?php echo BASE_URL; ?>request_certificate.php?certificate=certificate_indigency"><i class="bi bi-heart-pulse"></i><span>Request Certificate of Indigency</span></a>
              <a class="quick-action-btn" href="<?php echo BASE_URL; ?>my_requests.php"><i class="bi bi-list-check"></i><span>View My Requests</span></a>
              <a class="quick-action-btn" href="<?php echo BASE_URL; ?>resident_profile.php"><i class="bi bi-person-circle"></i><span>Update Profile</span></a>
              <a class="quick-action-btn" href="<?php echo BASE_URL; ?>resident_household.php"><i class="bi bi-house-door"></i><span>Household Information</span></a>
            </div>
            <div class="announcements-widget">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0"><i class="bi bi-megaphone me-2 text-primary"></i>Latest Announcements</h6>
                <a href="<?php echo BASE_URL; ?>resident_announcements.php" class="view-all-link">View All</a>
              </div>
              <div id="dashboardAnnouncementsContainer" class="announcements-list-dashboard">
                <div class="loading-placeholder">
                  <i class="bi bi-arrow-repeat"></i> Loading announcements...
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-xl-5">
        <div class="card dash-panel h-100">
          <div class="card-header bg-white border-0">
            <h6 class="mb-0"><i class="bi bi-bell me-2 text-primary"></i>Notifications</h6>
          </div>
          <div class="card-body pt-0">
            <?php if (!$dashboardNotifications): ?>
              <p class="info-empty mb-0">No new notifications.</p>
            <?php else: ?>
              <ul class="notification-list mb-0">
                <?php foreach ($dashboardNotifications as $notice): ?>
                  <li>
                    <h4><?php echo htmlspecialchars($notice['title'] ?? 'System Alert'); ?></h4>
                    <p><?php echo htmlspecialchars($notice['message'] ?? 'You have a new update.'); ?></p>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-xl-6">
        <div class="card dash-panel h-100">
          <div class="card-header bg-white border-0">
            <h6 class="mb-0"><i class="bi bi-signpost-split me-2 text-primary"></i>Request Progress Tracker</h6>
          </div>
          <div class="card-body pt-0">
            <?php if (!$latestRequest): ?>
              <p class="info-empty mb-0">No recent requests found.</p>
            <?php else: ?>
              <ul class="mini-list mb-0">
                <li><span>Document Type</span><strong><?php echo htmlspecialchars(residentDashboardPrettyLabel($latestRequest['request_type'] ?? 'Document Request')); ?></strong></li>
                <li><span>Date Requested</span><strong><?php echo !empty($latestRequest['requested_at']) ? htmlspecialchars(date('F d, Y', strtotime($latestRequest['requested_at']))) : '-'; ?></strong></li>
                <li><span>Current Status</span><strong><span class="tracker-badge <?php echo residentDashboardTrackerClass($latestRequest['status'] ?? 'Submitted'); ?>"><?php echo htmlspecialchars(residentDashboardPrettyLabel($latestRequest['status'] ?? 'Submitted')); ?></span></strong></li>
              </ul>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-xl-6">
        <div class="card dash-panel h-100">
          <div class="card-header bg-white border-0">
            <h6 class="mb-0"><i class="bi bi-house-check me-2 text-primary"></i>Household Snapshot</h6>
          </div>
          <div class="card-body pt-0">
            <?php if (!$householdSnapshot['linked']): ?>
              <p class="info-empty mb-0">You are not currently associated with any household. Please contact the barangay office to link your household record.</p>
            <?php else: ?>
              <ul class="mini-list mb-0">
                <li><span>Household Head</span><strong><?php echo htmlspecialchars($householdSnapshot['head_name'] ?: '-'); ?></strong></li>
                <li><span>Total Members</span><strong><?php echo (int)$householdSnapshot['member_count']; ?></strong></li>
                <li><span>Address</span><strong><?php echo htmlspecialchars($householdSnapshot['address'] ?: '-'); ?></strong></li>
                <li><span>Household ID</span><strong><?php echo (int)$householdSnapshot['household_id']; ?></strong></li>
              </ul>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-12">
        <div class="card dash-panel">
          <div class="card-header bg-white border-0">
            <h6 class="mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Requests</h6>
          </div>
          <div class="card-body pt-0">
            <div class="table-wrap">
              <table class="request-table" id="requestTable">
                <thead>
                  <tr>
                    <th>Document Type</th>
                    <th>Date Requested</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$recentRequests): ?>
                    <tr>
                      <td colspan="4" class="table-empty">No recent requests found.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($recentRequests as $request): ?>
                      <?php $statusClass = residentDashboardStatusClass($request['status'] ?? 'pending'); ?>
                      <tr>
                        <td><?php echo htmlspecialchars(residentDashboardPrettyLabel($request['request_type'] ?? 'Document Request')); ?></td>
                        <td><?php echo !empty($request['requested_at']) ? htmlspecialchars(date('F d, Y', strtotime($request['requested_at']))) : '-'; ?></td>
                        <td><span class="status <?php echo $statusClass; ?>"><?php echo htmlspecialchars(residentDashboardPrettyLabel($request['status'] ?? 'pending')); ?></span></td>
                        <td><a class="text-link" href="<?php echo BASE_URL; ?>my_requests.php">View Details</a></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-12">
        <div class="card dash-panel emergency-panel">
          <div class="card-header bg-white border-0">
            <h6 class="mb-0"><i class="bi bi-telephone-forward me-2 text-primary"></i>Emergency Contacts</h6>
          </div>
          <div class="card-body pt-0">
            <div class="emergency-grid">
              <?php if (!empty($residentEmergencyContact['name']) || !empty($residentEmergencyContact['number'])): ?>
                <div class="contact-item">
                  <h4>My Emergency Contact<?php echo !empty($residentEmergencyContact['relationship']) ? ' (' . htmlspecialchars($residentEmergencyContact['relationship']) . ')' : ''; ?></h4>
                  <p><?php echo htmlspecialchars($residentEmergencyContact['name'] ?: 'Not specified'); ?></p>
                  <p><?php echo htmlspecialchars($residentEmergencyContact['number'] ?: 'No number on file'); ?></p>
                </div>
              <?php endif; ?>

              <?php if (!empty($householdSnapshot['emergency_contact_name']) || !empty($householdSnapshot['emergency_contact_number'])): ?>
                <div class="contact-item">
                  <h4>Household Emergency Contact</h4>
                  <p><?php echo htmlspecialchars($householdSnapshot['emergency_contact_name'] ?: 'Not specified'); ?></p>
                  <p><?php echo htmlspecialchars($householdSnapshot['emergency_contact_number'] ?: 'No number on file'); ?></p>
                </div>
              <?php endif; ?>

              <?php foreach ($dashboardEmergencyContacts as $contact): ?>
                <div class="contact-item">
                  <h4><?php echo htmlspecialchars((string)($contact['label'] ?? 'Emergency Contact')); ?></h4>
                  <p><?php echo htmlspecialchars((string)($contact['number'] ?? '-')); ?></p>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.resident-dashboard-page .dashboard-hero {
  border-radius: 16px;
  background: radial-gradient(circle at 0% 0%, rgba(147, 197, 253, 0.24), transparent 36%), linear-gradient(140deg, #f8fbff 0%, #eef4ff 58%, #f4f7fb 100%);
  border: 1px solid rgba(59, 130, 246, 0.2) !important;
  box-shadow: 0 16px 34px -24px rgba(37, 99, 235, 0.45);
}

.resident-dashboard-page .dashboard-hero .card-body {
  padding: 1.2rem 1.3rem;
}

.resident-dashboard-page .hero-kicker {
  color: #334155;
  letter-spacing: 0.08em;
  font-weight: 700;
}

.resident-dashboard-page .hero-copy h2 {
  color: #0f172a;
  font-weight: 700;
}

.resident-dashboard-page .hero-subtitle {
  color: #475569;
  max-width: 640px;
}

.resident-dashboard-page .hero-date-badge {
  display: inline-block;
  border-radius: 999px;
  background: rgba(37, 99, 235, 0.12);
  color: #1e3a8a;
  border: 1px solid rgba(37, 99, 235, 0.22);
  font-weight: 600;
}

.resident-dashboard-page .hero-chips {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

.resident-dashboard-page .hero-chip {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 0.2rem 0.6rem;
  font-size: 0.78rem;
  color: #334155;
  background: rgba(255, 255, 255, 0.7);
  border: 1px solid rgba(148, 163, 184, 0.35);
}

.resident-dashboard-page .dash-panel {
  border-radius: 14px;
  border: 1px solid #e2e8f0 !important;
  box-shadow: 0 8px 20px -12px rgba(15, 23, 42, 0.18);
}

.resident-dashboard-page .dash-panel .card-header {
  padding: 0.85rem 1rem 0.6rem;
  border-bottom: 1px solid #f1f5f9;
}

.resident-dashboard-page .dash-panel .card-header h6 {
  font-size: 0.85rem;
  font-weight: 700;
  color: #1e293b;
}

.resident-dashboard-page .resident-avatar {
  width: 62px;
  height: 62px;
  border-radius: 999px;
  object-fit: cover;
  border: 2px solid #dbeafe;
}

.resident-dashboard-page .resident-meta {
  color: #334155;
  font-size: 0.82rem;
}

.resident-dashboard-page .dashboard-kpi-card {
  position: relative;
  border-radius: 14px;
  padding: 0.85rem 1rem 0.7rem;
  min-height: 100px;
  color: #fff;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.22);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12);
  transition: transform 0.2s, box-shadow 0.2s;
}

.resident-dashboard-page .dashboard-kpi-card::before {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.16), transparent 55%);
  pointer-events: none;
}

.resident-dashboard-page .dashboard-kpi-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 14px 24px rgba(15, 23, 42, 0.2);
}

.resident-dashboard-page .kpi-icon {
  position: absolute;
  right: 12px;
  top: 10px;
  font-size: 1.15rem;
  opacity: 0.9;
}

.resident-dashboard-page .kpi-value {
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1.15;
  margin-top: 0.15rem;
  font-variant-numeric: tabular-nums;
}

.resident-dashboard-page .kpi-label {
  font-size: 0.78rem;
  opacity: 0.92;
  margin-top: 2px;
}

.resident-dashboard-page .kpi-primary {
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
}

.resident-dashboard-page .kpi-teal {
  background: linear-gradient(135deg, #0f766e, #115e59);
}

.resident-dashboard-page .kpi-sky {
  background: linear-gradient(135deg, #0284c7, #0369a1);
}

.resident-dashboard-page .kpi-amber {
  background: linear-gradient(135deg, #d97706, #b45309);
}

.resident-dashboard-page .quick-actions-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(220px, 1fr));
  gap: 0.7rem;
}

.resident-dashboard-page .quick-action-btn {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  text-decoration: none;
  color: #1e293b;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 0.62rem 0.72rem;
  font-size: 0.83rem;
  font-weight: 600;
  transition: all 0.2s ease;
}

.resident-dashboard-page .quick-action-btn:hover {
  background: #eff6ff;
  border-color: #93c5fd;
  color: #1e40af;
}

.resident-dashboard-page .view-all-link,
.resident-dashboard-page .text-link {
  color: #2563eb;
  font-weight: 600;
  text-decoration: none;
  font-size: 0.78rem;
}

.resident-dashboard-page .view-all-link:hover,
.resident-dashboard-page .text-link:hover {
  text-decoration: underline;
}

.resident-dashboard-page .mini-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.resident-dashboard-page .mini-list li {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.7rem;
  border-bottom: 1px solid #f1f5f9;
  padding: 0.58rem 0;
  font-size: 0.83rem;
}

.resident-dashboard-page .mini-list li:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.resident-dashboard-page .mini-list span {
  color: #64748b;
}

.resident-dashboard-page .mini-list strong {
  color: #0f172a;
  text-align: right;
}

.resident-dashboard-page .tracker-badge,
.resident-dashboard-page .status {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 0.2rem 0.62rem;
  font-size: 0.73rem;
  font-weight: 700;
  text-transform: capitalize;
}

.resident-dashboard-page .tracker-submitted,
.resident-dashboard-page .tracker-review,
.resident-dashboard-page .status.status-pending {
  background: #fff7ed;
  color: #9a3412;
}

.resident-dashboard-page .tracker-approved,
.resident-dashboard-page .tracker-ready,
.resident-dashboard-page .tracker-released,
.resident-dashboard-page .status.status-approved {
  background: #ecfdf5;
  color: #166534;
}

.resident-dashboard-page .tracker-cancelled,
.resident-dashboard-page .status.status-rejected,
.resident-dashboard-page .status.status-cancelled {
  background: #fee2e2;
  color: #991b1b;
}

.resident-dashboard-page .notification-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.58rem;
}

.resident-dashboard-page .notification-list li {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #f8fafc;
  padding: 0.62rem 0.72rem;
}

.resident-dashboard-page .notification-list h4 {
  margin: 0 0 0.18rem;
  font-size: 0.82rem;
  color: #0f172a;
}

.resident-dashboard-page .notification-list p {
  margin: 0;
  font-size: 0.78rem;
  color: #475569;
}

.resident-dashboard-page .info-empty {
  color: #64748b;
  font-size: 0.84rem;
}

.resident-dashboard-page .table-wrap {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow-x: auto;
}

.resident-dashboard-page .request-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 620px;
}

.resident-dashboard-page .request-table th,
.resident-dashboard-page .request-table td {
  padding: 0.68rem 0.72rem;
  border-bottom: 1px solid #f1f5f9;
  font-size: 0.8rem;
  color: #1e293b;
  vertical-align: middle;
}

.resident-dashboard-page .request-table th {
  background: #f8fafc;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  font-size: 0.7rem;
}

.resident-dashboard-page .table-empty {
  color: #64748b;
  text-align: center;
}

.resident-dashboard-page .emergency-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 0.72rem;
}

.resident-dashboard-page .contact-item {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #f8fafc;
  padding: 0.62rem 0.72rem;
}

.resident-dashboard-page .contact-item h4 {
  margin: 0 0 0.22rem;
  font-size: 0.8rem;
  color: #0f172a;
}

.resident-dashboard-page .contact-item p {
  margin: 0;
  font-size: 0.76rem;
  color: #475569;
}

@media (max-width: 991px) {
  .resident-dashboard-page .quick-actions-grid {
    grid-template-columns: 1fr;
  }

  .resident-dashboard-page .hero-chips {
    justify-content: flex-start;
  }

  .resident-dashboard-page .hero-meta {
    text-align: left !important;
    width: 100%;
  }
}

@media (max-width: 768px) {
  .resident-dashboard-page .dashboard-kpi-card {
    min-height: 88px;
    padding: 0.7rem 0.8rem 0.6rem;
  }

  .resident-dashboard-page .kpi-value {
    font-size: 1.4rem;
  }
}
</style>

<script src="<?php echo BASE_URL; ?>resident_dashboard.js?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/resident_dashboard.js')); ?>"></script>
<script src="<?php echo BASE_URL; ?>assets/css/js/dashboard-announcements.js?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/assets/css/js/dashboard-announcements.js')); ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
