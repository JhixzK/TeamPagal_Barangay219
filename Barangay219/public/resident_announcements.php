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

$page_title = 'Announcements';
require_once __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';

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
<link rel="stylesheet" href="<?php echo BASE_URL; ?>resident_dashboard.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/resident_dashboard.css')); ?>">
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

<div class="main-content module-page resident-theme" id="mainContent">
  <div class="container-fluid">
    <div class="module-hero card border-0 shadow-sm mb-4">
      <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div class="hero-copy">
          <p class="module-kicker text-uppercase small mb-1">Resident Portal</p>
          <h2 class="mb-1"><i class="bi bi-megaphone me-2"></i>Announcements</h2>
          <p class="module-subtitle mb-0">View all community announcements and updates.</p>
        </div>
        <div class="text-md-end hero-meta">
          <span class="hero-date-badge fs-6 px-3 py-2" id="mainDateBadge">
            <i class="bi bi-calendar3 me-1"></i><?php echo date('F d, Y'); ?>
          </span>
          <div class="hero-chips mt-2">
            <span class="hero-chip"><i class="bi bi-person-check me-1"></i>Resident View</span>
            <span class="hero-chip"><i class="bi bi-broadcast me-1"></i>Community Updates</span>
          </div>
        </div>
      </div>
    </div>

    <section class="announcements-container">
      <div class="announcements-filters">
        <div class="search-box">
          <i class="bi bi-search" aria-hidden="true"></i>
          <input type="text" id="searchInput" placeholder="Search announcements..." aria-label="Search announcements by title">
        </div>
      </div>

      <div class="announcement-list">
        <!-- Announcements loaded here by announcement-manager.js -->
      </div>
    </section>
  </div>
</div>

<script src="<?php echo BASE_URL; ?>announcement-manager.js?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/announcement-manager.js')); ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
