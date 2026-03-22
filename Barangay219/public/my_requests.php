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

<div class="main-content module-page resident-my-requests-page resident-theme">
  <div class="container-fluid">
    <section class="dashboard-hero card border-0 shadow-sm mb-4">
      <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="hero-copy">
          <p class="hero-kicker text-uppercase small mb-1">Resident Services Portal</p>
          <h2 class="mb-1"><i class="bi bi-journal-check me-2"></i>My Requests</h2>
          <p class="hero-subtitle mb-0">Monitor your certificate requests, statuses, and request history from one dashboard.</p>
        </div>
        <div class="text-md-end hero-meta">
          <span class="hero-date-badge fs-6 px-3 py-2" id="mainDateBadge">
            <i class="bi bi-calendar3 me-1"></i><?php echo date('F d, Y'); ?>
          </span>
          <div class="hero-chips mt-2">
            <span class="hero-chip"><i class="bi bi-person-check me-1"></i>Resident View</span>
            <span class="hero-chip"><i class="bi bi-hourglass-split me-1"></i>Live Status</span>
          </div>
        </div>
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

<style>
.resident-my-requests-page .dashboard-hero {
  border-radius: 16px;
  background: radial-gradient(circle at 0% 0%, rgba(147, 197, 253, 0.24), transparent 36%), linear-gradient(140deg, #f8fbff 0%, #eef4ff 58%, #f4f7fb 100%);
  border: 1px solid rgba(59, 130, 246, 0.2) !important;
  box-shadow: 0 16px 34px -24px rgba(37, 99, 235, 0.45);
}

.resident-my-requests-page .dashboard-hero .card-body {
  padding: 1.2rem 1.3rem;
}

.resident-my-requests-page .hero-kicker {
  color: #334155;
  letter-spacing: 0.08em;
  font-weight: 700;
}

.resident-my-requests-page .hero-copy h2 {
  color: #0f172a;
  font-weight: 700;
}

.resident-my-requests-page .hero-subtitle {
  color: #475569;
  max-width: 640px;
}

.resident-my-requests-page .hero-date-badge {
  display: inline-block;
  border-radius: 999px;
  background: rgba(37, 99, 235, 0.12);
  color: #1e3a8a;
  border: 1px solid rgba(37, 99, 235, 0.22);
  font-weight: 600;
}

.resident-my-requests-page .hero-chips {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

.resident-my-requests-page .hero-chip {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 0.2rem 0.6rem;
  font-size: 0.78rem;
  color: #334155;
  background: rgba(255, 255, 255, 0.7);
  border: 1px solid rgba(148, 163, 184, 0.35);
}

.resident-my-requests-page .controls-card,
.resident-my-requests-page .table-card {
  border-radius: 14px;
  border: 1px solid #e2e8f0 !important;
  box-shadow: 0 8px 20px -12px rgba(15, 23, 42, 0.18);
}

.resident-my-requests-page .search-wrap input,
.resident-my-requests-page .filter-wrap select {
  border: 1px solid #cbd5e1;
  background: #fff;
}

.resident-my-requests-page .search-wrap input:focus,
.resident-my-requests-page .filter-wrap select:focus {
  border-color: #60a5fa;
  outline: 2px solid rgba(96, 165, 250, 0.18);
}

.resident-my-requests-page .summary-card {
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.22);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12), 0 10px 22px -14px rgba(15, 23, 42, 0.28);
}

.resident-my-requests-page .table-wrap {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
}

.resident-my-requests-page .requests-table th {
  background: #f8fafc;
  color: #64748b;
}

.resident-my-requests-page .requests-table td {
  color: #1e293b;
}

.resident-my-requests-page .btn-primary {
  background: #2563eb;
}

.resident-my-requests-page .btn-primary:hover {
  background: #1d4ed8;
}

.resident-my-requests-page .btn-secondary {
  background: #eff6ff;
  color: #1d4ed8;
  border-color: #bfdbfe;
}

.resident-my-requests-page .btn-secondary:hover {
  background: #dbeafe;
}

@media (max-width: 992px) {
  .resident-my-requests-page .hero-chips {
    justify-content: flex-start;
  }

  .resident-my-requests-page .hero-meta {
    text-align: left !important;
    width: 100%;
  }
}
</style>

<script src="<?php echo BASE_URL; ?>my_requests.js?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/my_requests.js')); ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
