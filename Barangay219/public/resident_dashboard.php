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

$residentStatusBadgeClass = 'badge-pending';
if ($residentProfile['resident_status'] === 'Verified') {
  $residentStatusBadgeClass = 'badge-verified';
} elseif ($residentProfile['resident_status'] === 'Incomplete Profile') {
  $residentStatusBadgeClass = 'badge-incomplete';
}

$householdStatusBadgeClass = $residentProfile['household_status'] === 'Linked' ? 'badge-linked' : 'badge-not-linked';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>resident_dashboard.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/resident_dashboard.css')); ?>">

<div class="main-content module-page" id="mainContent">
  <div class="container-fluid">
    <section class="dashboard-head">
      <div>
        <p class="portal-tag">RESIDENT PORTAL</p>
        <h2>Dashboard</h2>
        <p class="dashboard-subtitle">Access your barangay services, requests, and community updates.</p>
      </div>
      <div class="head-meta">
        <span class="view-badge">Resident View</span>
        <span class="date-badge" id="mainDateBadge"><?php echo date('F d, Y'); ?></span>
      </div>
    </section>

    <section class="profile-overview card-surface">
      <div class="profile-identity">
        <img src="<?php echo htmlspecialchars($residentProfile['avatar']); ?>" alt="Resident profile image">
        <div>
          <h3><?php echo htmlspecialchars($residentProfile['full_name']); ?></h3>
          <p><?php echo htmlspecialchars($email ?: 'No email on file'); ?></p>
          <p class="mb-0"><strong>Resident ID:</strong> <?php echo htmlspecialchars($residentProfile['resident_id'] ?: $username); ?></p>
        </div>
      </div>
      <div class="profile-status">
        <span class="pill <?php echo $householdStatusBadgeClass; ?>">
          <i class="fa-solid fa-house-user"></i>
          Household Status: <?php echo htmlspecialchars($residentProfile['household_status']); ?>
        </span>
      </div>
    </section>

    <section class="quick-actions card-surface" aria-label="Quick actions">
      <div class="panel-header compact">
        <h3>Quick Actions</h3>
      </div>
      <div class="quick-actions-grid">
        <a class="quick-btn" href="<?php echo BASE_URL; ?>request_certificate.php?certificate=barangay_certificate"><i class="fa-regular fa-file-lines"></i><span>Request Barangay Certificate</span></a>
        <a class="quick-btn" href="<?php echo BASE_URL; ?>request_certificate.php?certificate=certificate_indigency"><i class="fa-solid fa-hand-holding-heart"></i><span>Request Certificate of Indigency</span></a>
        <a class="quick-btn" href="<?php echo BASE_URL; ?>my_requests.php"><i class="fa-solid fa-list-check"></i><span>View My Requests</span></a>
        <a class="quick-btn" href="<?php echo BASE_URL; ?>resident_profile.php"><i class="fa-regular fa-user"></i><span>Update Profile</span></a>
        <a class="quick-btn" href="<?php echo BASE_URL; ?>resident_household.php"><i class="fa-solid fa-house"></i><span>Household Information</span></a>
      </div>
    </section>

    <section class="stats-grid" aria-label="Resident dashboard statistics">
      <article class="stat-card card-1" data-href="<?php echo BASE_URL; ?>my_requests.php" role="link" tabindex="0">
        <i class="fa-regular fa-folder-open stat-icon"></i>
        <h3>My Requests</h3>
        <p class="stat-value"><?php echo (int)$stats['total_requests']; ?></p>
        <p class="stat-note">Total documents requested</p>
      </article>

      <article class="stat-card card-2" data-href="<?php echo BASE_URL; ?>my_requests.php?status=Pending" role="link" tabindex="0">
        <i class="fa-regular fa-clock stat-icon"></i>
        <h3>Pending Requests</h3>
        <p class="stat-value"><?php echo (int)$stats['pending_requests']; ?></p>
        <p class="stat-note">Pending or under review</p>
      </article>

      <article class="stat-card card-3" data-href="<?php echo BASE_URL; ?>my_requests.php?status=Approved" role="link" tabindex="0">
        <i class="fa-regular fa-circle-check stat-icon"></i>
        <h3>Approved Documents</h3>
        <p class="stat-value"><?php echo (int)$stats['approved_documents']; ?></p>
        <p class="stat-note">Approved and ready for pickup</p>
      </article>

      <article class="stat-card card-4" data-href="<?php echo BASE_URL; ?>resident_announcements.php" role="link" tabindex="0">
        <i class="fa-regular fa-bullhorn stat-icon"></i>
        <h3>Barangay Announcements</h3>
        <p class="stat-value"><?php echo (int)$stats['active_announcements']; ?></p>
        <p class="stat-note">Recent community updates</p>
      </article>
    </section>

    <!-- Announcements Widget -->
    <section class="announcements-widget panel">
      <div class="panel-header">
        <h3><i class="fa-regular fa-bullhorn"></i> Latest Announcements</h3>
        <a href="<?php echo BASE_URL; ?>resident_announcements.php" class="view-all-link">View All</a>
      </div>
      <div id="dashboardAnnouncementsContainer" class="announcements-list-dashboard">
        <div class="loading-placeholder">
          <i class="fa-solid fa-spinner fa-spin"></i> Loading announcements...
        </div>
      </div>
    </section>

    <section class="dashboard-grid-two">
      <article class="panel">
        <div class="panel-header">
          <h3>Request Progress Tracker</h3>
        </div>
        <?php if (!$latestRequest): ?>
          <p class="info-empty">No recent requests found.</p>
        <?php else: ?>
          <div class="tracker-card">
            <p><strong>Document Type:</strong> <?php echo htmlspecialchars(residentDashboardPrettyLabel($latestRequest['request_type'] ?? 'Document Request')); ?></p>
            <p><strong>Date Requested:</strong> <?php echo !empty($latestRequest['requested_at']) ? htmlspecialchars(date('F d, Y', strtotime($latestRequest['requested_at']))) : '-'; ?></p>
            <p><strong>Current Status:</strong> <span class="tracker-badge <?php echo residentDashboardTrackerClass($latestRequest['status'] ?? 'Submitted'); ?>"><?php echo htmlspecialchars(residentDashboardPrettyLabel($latestRequest['status'] ?? 'Submitted')); ?></span></p>
          </div>
        <?php endif; ?>
      </article>

      <article class="panel">
        <div class="panel-header">
          <h3>Notifications</h3>
        </div>
        <?php if (!$dashboardNotifications): ?>
          <p class="info-empty">No new notifications.</p>
        <?php else: ?>
          <ul class="notification-list">
            <?php foreach ($dashboardNotifications as $notice): ?>
              <li>
                <h4><?php echo htmlspecialchars($notice['title'] ?? 'System Alert'); ?></h4>
                <p><?php echo htmlspecialchars($notice['message'] ?? 'You have a new update.'); ?></p>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </article>

      <article class="panel">
        <div class="panel-header">
          <h3>Household Snapshot</h3>
        </div>
        <?php if (!$householdSnapshot['linked']): ?>
          <p class="info-empty">You are not currently associated with any household. Please contact the barangay office to link your household record.</p>
        <?php else: ?>
          <ul class="detail-list">
            <li><strong>Household Head Name:</strong> <?php echo htmlspecialchars($householdSnapshot['head_name'] ?: '-'); ?></li>
            <li><strong>Number of Household Members:</strong> <?php echo (int)$householdSnapshot['member_count']; ?></li>
            <li><strong>Address:</strong> <?php echo htmlspecialchars($householdSnapshot['address'] ?: '-'); ?></li>
            <li><strong>Household ID:</strong> <?php echo (int)$householdSnapshot['household_id']; ?></li>
          </ul>
        <?php endif; ?>
      </article>

      <article class="panel">
        <div class="panel-header">
          <h3>Recent Requests</h3>
        </div>
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
    </section>

    <section class="panel emergency-panel">
      <div class="panel-header">
        <h3>Emergency Contacts</h3>
      </div>
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
    </section>
  </div>
</div>

<script src="<?php echo BASE_URL; ?>resident_dashboard.js?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/resident_dashboard.js')); ?>"></script>
<script src="<?php echo BASE_URL; ?>assets/css/js/dashboard-announcements.js?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/assets/css/js/dashboard-announcements.js')); ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
