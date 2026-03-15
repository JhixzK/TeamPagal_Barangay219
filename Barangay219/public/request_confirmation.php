<?php
/**
 * E-Barangay Information Management System
 * Request Confirmation (Resident)
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();

$currentRole = getCurrentUserRole();
if (normalizeRole($currentRole) !== normalizeRole(ROLE_RESIDENT)) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit();
}

$residentId = (int)($_SESSION['resident_id'] ?? 0);
$username = $_SESSION['username'] ?? 'Resident';

function rcConfirmConnect() {
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_errno) {
        return null;
    }
    $conn->set_charset(DB_CHARSET);
    return $conn;
}

$requestData = null;
$errorMessage = '';
$tracking = trim((string)($_GET['tracking'] ?? ''));
$residentName = $username;

$mysqli = rcConfirmConnect();
if (!$mysqli) {
    $errorMessage = 'Unable to connect to the database.';
}

if ($mysqli && $residentId > 0) {
    $nameStmt = $mysqli->prepare('SELECT first_name, middle_name, last_name FROM residents WHERE id = ? LIMIT 1');
    if ($nameStmt) {
        $nameStmt->bind_param('i', $residentId);
        $nameStmt->execute();
        $res = $nameStmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        if ($row) {
            $residentName = trim(($row['first_name'] ?? '') . ' ' . (($row['middle_name'] ?? '') ? $row['middle_name'] . ' ' : '') . ($row['last_name'] ?? ''));
          if ($residentName === '') {
            $residentName = $username;
          }
        }
        $nameStmt->close();
    }

    if ($tracking !== '') {
      $sql = 'SELECT id, reference_number, certificate_type, created_at, status FROM certificate_requests WHERE reference_number = ? AND resident_id = ? LIMIT 1';
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('si', $tracking, $residentId);
            $stmt->execute();
            $result = $stmt->get_result();
            $requestData = $result ? $result->fetch_assoc() : null;
            $stmt->close();
        }
    }

    $mysqli->close();
}

if ($tracking === '') {
    $errorMessage = 'Tracking ID is missing.';
} elseif (!$requestData && $errorMessage === '') {
    $errorMessage = 'Request not found for this account.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Request Confirmation | E-Barangay Information Management System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
  <link rel="stylesheet" href="request_certificate.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/request_certificate.css')); ?>">
</head>
<body>
  <header class="top-header">
    <div class="header-left">
      <button class="menu-toggle" id="menuToggle" aria-label="Toggle sidebar">
        <i class="fa-solid fa-bars"></i>
      </button>
      <div class="logo-wrap" aria-hidden="true">
        <i class="fa-solid fa-shield-halved"></i>
      </div>
      <div class="system-text">
        <h1>E-Barangay Information Management System</h1>
        <p>Barangay 219, Tondo, Manila</p>
      </div>
    </div>

    <div class="header-right">
      <span class="date-badge"><?php echo date('F d, Y'); ?></span>
      <button class="icon-btn" aria-label="Notifications">
        <i class="fa-regular fa-bell"></i>
      </button>
    </div>
  </header>

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-profile">
      <img src="https://i.pravatar.cc/120?img=12" alt="Resident profile image">
      <div class="profile-meta label">
        <h3><?php echo htmlspecialchars($residentName); ?></h3>
        <p>Resident</p>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-group">
        <p class="group-title label">ACCOUNT</p>
        <a class="nav-item" href="resident_profile.php"><i class="fa-regular fa-user"></i><span class="label">My Profile</span></a>
      </div>
      <div class="nav-group">
        <p class="group-title label">MAIN</p>
        <a class="nav-item" href="resident_dashboard.php"><i class="fa-solid fa-gauge-high"></i><span class="label">Dashboard</span></a>
      </div>
      <div class="nav-group">
        <p class="group-title label">SERVICES</p>
        <a class="nav-item active" href="request_certificate.php"><i class="fa-regular fa-file-lines"></i><span class="label">Request Certificate</span></a>
        <a class="nav-item" href="my_requests.php"><i class="fa-solid fa-list-check"></i><span class="label">My Requests</span></a>
      </div>
      <div class="nav-group">
        <p class="group-title label">HOUSEHOLD</p>
        <a class="nav-item" href="<?php echo BASE_URL; ?>resident_household.php"><i class="fa-solid fa-house-user"></i><span class="label">Household Information</span></a>
      </div>
      <div class="nav-group">
        <p class="group-title label">COMMUNITY</p>
        <a class="nav-item" href="<?php echo BASE_URL; ?>resident_announcements.php"><i class="fa-regular fa-newspaper"></i><span class="label">Announcements</span></a>
        <a class="nav-item" href="<?php echo BASE_URL; ?>complaints/my_complaints.php"><i class="fa-regular fa-comment-dots"></i><span class="label">Complaints / Reports</span></a>
      </div>
      <div class="nav-group">
        <p class="group-title label">OTHER</p>
        <a class="nav-item" href="#"><i class="fa-regular fa-bell"></i><span class="label">Notifications</span></a>
        <a class="nav-item" href="#"><i class="fa-regular fa-circle-question"></i><span class="label">Help / Support</span></a>
      </div>
    </nav>

    <div class="sidebar-bottom">
      <a class="nav-item logout" href="../api/auth.php?action=logout">
        <i class="fa-solid fa-arrow-right-from-bracket"></i>
        <span class="label">Logout</span>
      </a>
    </div>
  </aside>

  <main class="main-content">
    <section class="page-head">
      <div>
        <p class="portal-tag">RESIDENT PORTAL</p>
        <h2>Request Submitted Successfully</h2>
        <p class="page-subtitle">Your certificate request has been recorded.</p>
      </div>
    </section>

    <section class="card form-card confirmation-card">
      <?php if ($errorMessage !== ''): ?>
        <div class="notice error-notice">
          <h4>Request Lookup Error</h4>
          <p><?php echo htmlspecialchars($errorMessage); ?></p>
        </div>
      <?php else: ?>
        <div class="confirmation-badge">Submitted</div>
        <div class="summary-grid single-col">
          <div class="summary-item"><span>Tracking ID</span><strong><?php echo htmlspecialchars($requestData['reference_number']); ?></strong></div>
          <div class="summary-item"><span>Certificate Type</span><strong><?php echo htmlspecialchars($requestData['certificate_type']); ?></strong></div>
          <div class="summary-item"><span>Date Requested</span><strong><?php echo htmlspecialchars(date('F d, Y h:i A', strtotime($requestData['created_at']))); ?></strong></div>
          <div class="summary-item"><span>Status</span><strong class="status-badge submitted">Submitted</strong></div>
          <div class="summary-item"><span>Expected Processing Time</span><strong>1-2 working days</strong></div>
        </div>
      <?php endif; ?>

      <div class="actions">
        <a class="btn-primary" href="my_requests.php"><i class="fa-solid fa-list-check"></i> View My Requests</a>
        <a class="btn-secondary" href="request_certificate.php">Submit Another Request</a>
      </div>
    </section>
  </main>
</body>
</html>
