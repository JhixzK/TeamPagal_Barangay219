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

$page_title = 'Request Confirmation';
require_once __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';

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
<link rel="stylesheet" href="<?php echo BASE_URL; ?>request_certificate.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/request_certificate.css')); ?>">

<div class="main-content module-page resident-theme">
  <div class="container-fluid">
  <div class="resident-request-certificate">
    <section class="dashboard-hero card border-0 shadow-sm mb-4">
      <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="hero-copy">
          <p class="hero-kicker text-uppercase small mb-1">Resident Services Portal</p>
          <h2 class="mb-1"><i class="bi bi-check2-circle me-2"></i>Request Submitted Successfully</h2>
          <p class="hero-subtitle mb-0">Your certificate request has been recorded.</p>
        </div>
        <div class="text-md-end hero-meta">
          <span class="hero-date-badge fs-6 px-3 py-2" id="mainDateBadge">
            <i class="bi bi-calendar3 me-1"></i><?php echo date('F d, Y'); ?>
          </span>
          <div class="hero-chips mt-2">
            <span class="hero-chip"><i class="bi bi-person-check me-1"></i>Resident View</span>
            <span class="hero-chip"><i class="bi bi-receipt me-1"></i>Request Confirmation</span>
          </div>
        </div>
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
  </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
