<?php
/**
 * E-Barangay Information Management System
 * Resident Announcements Page
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

// Require login and check if user is a resident
requireLogin();

if (!isResidentView()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit();
}

// Get user information
$userId = getCurrentUserId();
$username = $_SESSION['username'] ?? 'Resident';
$email = $_SESSION['email'] ?? '';
$residentId = $_SESSION['resident_id'] ?? null;

// Get resident name from database
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
  <title>Announcements | E-Barangay Information Management System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
  <link rel="stylesheet" href="resident_dashboard.css">
  <style>
    .announcements-container {
      max-width: 900px;
      margin: 0 auto;
    }

    .announcements-header {
      margin-bottom: 20px;
      padding-bottom: 16px;
      border-bottom: 1px solid var(--border-soft);
    }

    .announcements-header h2 {
      margin: 0;
      color: var(--text-main);
      font-size: 28px;
      font-weight: 600;
    }

    .announcements-header p {
      margin: 8px 0 0;
      color: var(--text-muted);
      font-size: 16px;
    }

    .announcements-filters {
      display: flex;
      gap: 12px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }

    .search-box {
      flex: 1;
      min-width: 280px;
      position: relative;
    }

    .search-box i {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: #9aa4b2;
      font-size: 14px;
      pointer-events: none;
    }

    .search-box input {
      width: 100%;
      padding: 13px 14px 13px 40px;
      border: 1px solid var(--border-soft);
      border-radius: 999px;
      font-size: 15px;
      font-family: "Poppins", sans-serif;
      background: #fff;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .search-box input:focus {
      outline: none;
      border-color: var(--blue-800);
      box-shadow: 0 0 0 3px rgba(45, 83, 185, 0.1);
    }

    .announcement-list {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 20px;
      align-items: stretch;
    }

    @media (max-width: 1199px) {
      .announcement-list {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 768px) {
      .announcements-header h2 {
        font-size: 24px;
      }

      .announcement-list {
        grid-template-columns: 1fr;
        gap: 16px;
      }
    }
  </style>
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
      <?php if (canSwitchToResidentView()): ?>
        <div class="view-switch" role="group" aria-label="View mode switch">
          <span class="view-label">Official</span>
          <label class="switch">
            <input type="checkbox" data-view-mode-toggle <?php echo isResidentView() ? 'checked' : ''; ?>>
            <span class="slider"></span>
          </label>
          <span class="view-label">Resident</span>
        </div>
      <?php endif; ?>
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
        <a class="nav-item" href="<?php echo BASE_URL; ?>resident_dashboard.php">
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
        <a class="nav-item active" href="<?php echo BASE_URL; ?>resident_announcements.php">
          <i class="fa-regular fa-newspaper"></i>
          <span class="label">Announcements</span>
        </a>
        <a class="nav-item" href="<?php echo BASE_URL; ?>complaints/my_complaints.php">
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
    <section class="announcements-container">
      <div class="announcements-header">
        <h2>Announcements</h2>
        <p>View all community announcements and updates</p>
      </div>

      <div class="announcements-filters">
        <div class="search-box">
          <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
          <input type="text" id="searchInput" placeholder="Search announcements..." aria-label="Search announcements by title">
        </div>
      </div>

      <div class="announcement-list">
        <!-- Announcements loaded here by announcement-manager.js -->
      </div>
    </section>
  </main>

  <script src="resident_dashboard.js?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/resident_dashboard.js')); ?>"></script>
  <script src="<?php echo ASSETS_URL; ?>css/js/view-mode-switch.js?v=<?php echo time(); ?>"></script>
  <script src="announcement-manager.js?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/announcement-manager.js')); ?>"></script>
</body>
</html>
