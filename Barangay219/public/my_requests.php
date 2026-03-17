<?php
/**
 * E-Barangay Information Management System
 * My Requests - Resident Portal
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
$page_title = 'My Requests';
require_once __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';

// Get user information
$userId = getCurrentUserId();
$username = $_SESSION['username'] ?? 'Resident';
$email = $_SESSION['email'] ?? '';
$residentId = $_SESSION['resident_id'] ?? null;

// Get resident details from database if available
$db = Database::getInstance();
$residentName = $username;
if ($residentId) {
    $sql = "SELECT first_name, middle_name, last_name FROM residents WHERE id = ?";
    $resident = $db->fetchOne($sql, [$residentId]);
    if ($resident) {
        $residentName = trim($resident['first_name'] . ' ' . ($resident['middle_name'] ? $resident['middle_name'] . ' ' : '') . $resident['last_name']);
    }
}
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>my_requests.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/my_requests.css')); ?>">

<div class="main-content module-page">
  <div class="container-fluid">
    <section class="page-head">
      <div>
        <p class="portal-tag">RESIDENT PORTAL</p>
        <h2>My Requests</h2>
        <p class="page-subtitle">Track the status of your barangay document requests.</p>
      </div>
      <div class="head-meta">
        <span class="view-badge">Resident View</span>
        <span class="date-badge" id="mainDateBadge"><?php echo date('F d, Y'); ?></span>
      </div>
    </section>

    <section class="card controls-card">
      <div class="search-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="searchInput" placeholder="Search by Request ID or Certificate Type">
      </div>
      <label class="filter-wrap">
        <span>Status Filter</span>
        <select id="statusFilter">
          <option value="All">All</option>
          <option value="Pending">Pending</option>
          <option value="Under Review">Under Review</option>
          <option value="Approved">Approved</option>
          <option value="Rejected">Rejected</option>
          <option value="Ready for Pickup">Ready for Pickup</option>
          <option value="Completed">Completed</option>
        </select>
      </label>
    </section>

    <section class="summary-grid" id="summaryCards">
      <article class="summary-card total">
        <p>Total Requests</p>
        <h3 id="totalRequests">0</h3>
      </article>
      <article class="summary-card pending">
        <p>Pending Requests</p>
        <h3 id="pendingRequests">0</h3>
      </article>
      <article class="summary-card approved">
        <p>Approved Requests</p>
        <h3 id="approvedRequests">0</h3>
      </article>
      <article class="summary-card rejected">
        <p>Rejected Requests</p>
        <h3 id="rejectedRequests">0</h3>
      </article>
    </section>

    <section class="card table-card">
      <div class="table-wrap" id="tableWrap">
        <table class="requests-table">
          <thead>
            <tr>
              <th>Request ID</th>
              <th>Certificate Type</th>
              <th>Purpose</th>
              <th>Date Requested</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="requestsTableBody"></tbody>
        </table>
      </div>

      <div class="empty-state hidden" id="emptyState">
        <p>You have not submitted any document requests yet.</p>
        <a href="request_certificate.php" class="btn-primary">Request Certificate</a>
      </div>

      <div class="pagination" id="pagination"></div>
    </section>

    <div class="modal-backdrop hidden" id="detailsModal">
      <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="detailsModalTitle">
        <div class="modal-head">
          <h3 id="detailsModalTitle">Request Details</h3>
          <button type="button" class="icon-close" id="closeModalBtn" aria-label="Close details modal">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <div class="modal-content" id="modalContent"></div>

        <div class="modal-actions" id="modalActions">
          <button type="button" class="btn-secondary" id="closeModalFooterBtn">Close</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="<?php echo BASE_URL; ?>my_requests.js?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/my_requests.js')); ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
