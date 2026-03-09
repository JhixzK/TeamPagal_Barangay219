<?php
/**
 * E-Barangay Information Management System
 * Resident Dashboard
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

// Require login and check if user is a resident
requireLogin();

$currentRole = getCurrentUserRole();
if (normalizeRole($currentRole) !== normalizeRole(ROLE_RESIDENT)) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit();
}

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
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resident Dashboard | E-Barangay Information Management System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
  <link rel="stylesheet" href="resident_dashboard.css">
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
      <span class="date-badge" id="topDateBadge"><?php echo date('F d, Y'); ?></span>
      <button class="icon-btn" aria-label="Notifications">
        <i class="fa-regular fa-bell"></i>
      </button>
      <div class="profile-dropdown" id="profileDropdown">
        <button class="profile-trigger" id="profileTrigger" aria-haspopup="true" aria-expanded="false">
          <img src="https://i.pravatar.cc/100?img=12" alt="Resident avatar">
          <i class="fa-solid fa-chevron-down"></i>
        </button>
        <div class="dropdown-menu" id="dropdownMenu" role="menu">
          <a href="resident_profile.php" role="menuitem">View Profile</a>
          <a href="#" role="menuitem">Account Settings</a>
          <a href="../api/auth.php?action=logout" role="menuitem">Logout</a>
        </div>
      </div>
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
        <a class="nav-item" href="<?php echo BASE_URL; ?>resident_profile.php">
          <i class="fa-regular fa-user"></i>
          <span class="label">My Profile</span>
        </a>
      </div>

      <div class="nav-group">
        <p class="group-title label">MAIN</p>
        <a class="nav-item active" href="<?php echo BASE_URL; ?>resident_dashboard.php">
          <i class="fa-solid fa-gauge-high"></i>
          <span class="label">Dashboard</span>
        </a>
      </div>

      <div class="nav-group">
        <p class="group-title label">SERVICES</p>
        <a class="nav-item" href="<?php echo BASE_URL; ?>request_certificate.php">
          <i class="fa-regular fa-file-lines"></i>
          <span class="label">Request Certificate</span>
        </a>
        <a class="nav-item" href="<?php echo BASE_URL; ?>my_requests.php">
          <i class="fa-solid fa-list-check"></i>
          <span class="label">My Requests</span>
        </a>
      </div>

      <div class="nav-group">
        <p class="group-title label">HOUSEHOLD</p>
        <a class="nav-item" href="<?php echo BASE_URL; ?>resident_household.php">
          <i class="fa-solid fa-house-user"></i>
          <span class="label">Household Information</span>
        </a>
      </div>

      <div class="nav-group">
        <p class="group-title label">COMMUNITY</p>
        <a class="nav-item" href="<?php echo BASE_URL; ?>resident_announcements.php">
          <i class="fa-regular fa-newspaper"></i>
          <span class="label">Announcements</span>
        </a>
        <a class="nav-item" href="#">
          <i class="fa-regular fa-comment-dots"></i>
          <span class="label">Complaints / Reports</span>
        </a>
      </div>

      <div class="nav-group">
        <p class="group-title label">OTHER</p>
        <a class="nav-item" href="#">
          <i class="fa-regular fa-bell"></i>
          <span class="label">Notifications</span>
        </a>
        <a class="nav-item" href="#">
          <i class="fa-regular fa-circle-question"></i>
          <span class="label">Help / Support</span>
        </a>
      </div>
    </nav>

    <div class="sidebar-bottom">
      <a class="nav-item logout" href="../api/auth.php?action=logout">
        <i class="fa-solid fa-arrow-right-from-bracket"></i>
        <span class="label">Logout</span>
      </a>
    </div>
  </aside>

  <main class="main-content" id="mainContent">
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

    <section class="stats-grid" aria-label="Resident dashboard statistics">
      <article class="stat-card card-1">
        <i class="fa-regular fa-folder-open stat-icon"></i>
        <h3>My Requests</h3>
        <p class="stat-value">26</p>
        <p class="stat-note">Total documents requested</p>
      </article>

      <article class="stat-card card-2">
        <i class="fa-regular fa-clock stat-icon"></i>
        <h3>Pending Requests</h3>
        <p class="stat-value">4</p>
        <p class="stat-note">Waiting for barangay review</p>
      </article>

      <article class="stat-card card-3">
        <i class="fa-regular fa-circle-check stat-icon"></i>
        <h3>Approved Documents</h3>
        <p class="stat-value">19</p>
        <p class="stat-note">Released and ready to claim</p>
      </article>

      <article class="stat-card card-4">
        <i class="fa-regular fa-bullhorn stat-icon"></i>
        <h3>Barangay Announcements</h3>
        <p class="stat-value">8</p>
        <p class="stat-note">Recent community updates</p>
      </article>
    </section>

    <section class="panels-grid">
      <article class="panel">
        <div class="panel-header">
          <h3>Announcements</h3>
        </div>
        <div class="announcement-list">
          <div class="announcement-item">
            <h4>Community Clean-Up Drive</h4>
            <p>Join us on Saturday at 7:00 AM for the monthly clean-up activity around Purok 3 and nearby streets.</p>
            <span>Posted: March 05, 2026</span>
          </div>
          <div class="announcement-item">
            <h4>Free Medical Check-Up</h4>
            <p>Barangay health workers will conduct free blood pressure and consultation services at the covered court.</p>
            <span>Posted: March 03, 2026</span>
          </div>
          <div class="announcement-item">
            <h4>Scholarship Application Reminder</h4>
            <p>Qualified students may submit scholarship requirements at the Barangay Hall until March 15, 2026.</p>
            <span>Posted: March 01, 2026</span>
          </div>
        </div>
      </article>

      <article class="panel">
        <div class="panel-header">
          <h3>My Recent Requests</h3>
        </div>
        <div class="table-wrap">
          <table class="request-table" id="requestTable">
            <thead>
              <tr>
                <th>Document Type</th>
                <th>Date Requested</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Barangay Clearance</td>
                <td>March 06, 2026</td>
                <td><span class="status pending">Pending</span></td>
              </tr>
              <tr>
                <td>Certificate of Residency</td>
                <td>March 02, 2026</td>
                <td><span class="status approved">Approved</span></td>
              </tr>
              <tr>
                <td>Business Permit Endorsement</td>
                <td>February 27, 2026</td>
                <td><span class="status rejected">Rejected</span></td>
              </tr>
            </tbody>
          </table>
          <p class="empty-state" id="emptyState" hidden>No recent requests</p>
        </div>
      </article>
    </section>
  </main>

  <!-- Announcement Modal -->
  <div id="announcementModal" class="modal" style="display: none;">
    <div class="modal-backdrop"></div>
    <div class="modal-content modal-lg">
      <div class="modal-header">
        <h2 class="modal-title">Announcement</h2>
        <button class="modal-close" aria-label="Close announcement">&times;</button>
      </div>
      <div class="modal-body">
        <p class="modal-announcement-date"></p>
        <div class="modal-announcement-content"></div>
      </div>
    </div>
  </div>

  <script src="resident_dashboard.js"></script>
  <script src="announcement-manager.js"></script>
</body>
</html>
