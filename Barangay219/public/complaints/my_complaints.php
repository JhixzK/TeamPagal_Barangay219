<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/resident-complaints.php';

residentComplaintsRequireResident();

$db = Database::getInstance();
$residentId = residentComplaintsGetResidentId();
$residentName = residentComplaintsGetResidentName();

$moduleReady = residentComplaintsTableExists($db);
$complaints = [];

if ($moduleReady) {
    $complaints = $db->fetchAll(
        "SELECT id, reference_number, title, category, date_submitted, jurisdiction_status, status
         FROM complaints
         WHERE resident_id = ?
         ORDER BY date_submitted DESC, id DESC",
        [$residentId]
    );
}

$summary = [
    'total' => count($complaints),
    'active' => 0,
    'resolved' => 0,
    'referred' => 0
];

foreach ($complaints as $complaint) {
    $displayStatus = residentComplaintsDisplayStatus($complaint['status'] ?? '');
    if (in_array($displayStatus, ['Pending Review', 'Under Investigation', 'Scheduled for Mediation'], true)) {
        $summary['active']++;
    }
    if ($displayStatus === 'Resolved') {
        $summary['resolved']++;
    }
    if ($displayStatus === 'Referred to Other Barangay') {
        $summary['referred']++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Complaints | E-Barangay Information Management System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>resident_dashboard.css">
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
          <a href="<?php echo BASE_URL; ?>resident_profile.php" role="menuitem">View Profile</a>
          <a href="#" role="menuitem">Account Settings</a>
          <a href="<?php echo BASE_URL; ?>../api/auth.php?action=logout" role="menuitem">Logout</a>
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
        <a class="nav-item" href="<?php echo BASE_URL; ?>resident_announcements.php">
          <i class="fa-regular fa-newspaper"></i>
          <span class="label">Announcements</span>
        </a>
        <a class="nav-item active" href="<?php echo BASE_URL; ?>complaints/my_complaints.php">
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
      <a class="nav-item logout" href="<?php echo BASE_URL; ?>../api/auth.php?action=logout">
        <i class="fa-solid fa-arrow-right-from-bracket"></i>
        <span class="label">Logout</span>
      </a>
    </div>
  </aside>

  <main class="main-content resident-complaints-page">
    <section class="dashboard-head mb-3">
      <div>
        <p class="portal-tag">RESIDENT PORTAL</p>
        <h2>My Complaints</h2>
        <p class="dashboard-subtitle">Track the handling status of every complaint you submitted to the barangay.</p>
      </div>
      <div class="head-meta">
        <span class="view-badge">Resident View</span>
        <span class="date-badge" id="mainDateBadge"><?php echo date('F d, Y'); ?></span>
      </div>
    </section>

    <?php if (!$moduleReady): ?>
      <div class="alert alert-warning shadow-sm border-0 mb-4"><?php echo htmlspecialchars(residentComplaintsMissingTableMessage()); ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
      <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><p class="text-muted mb-2">Total Complaints</p><h3 class="mb-0"><?php echo (int)$summary['total']; ?></h3></div></div></div>
      <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><p class="text-muted mb-2">Active Cases</p><h3 class="mb-0"><?php echo (int)$summary['active']; ?></h3></div></div></div>
      <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><p class="text-muted mb-2">Referred</p><h3 class="mb-0"><?php echo (int)$summary['referred']; ?></h3></div></div></div>
      <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><p class="text-muted mb-2">Resolved</p><h3 class="mb-0"><?php echo (int)$summary['resolved']; ?></h3></div></div></div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body p-0">
        <?php if (!$complaints): ?>
          <div class="p-5 text-center">
            <h4 class="mb-2">No complaints submitted yet</h4>
            <p class="text-muted mb-4">When you report an incident, it will appear here for status tracking.</p>
            <a href="<?php echo BASE_URL; ?>complaints/submit_complaint.php" class="btn btn-primary">Submit Your First Complaint</a>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead>
                <tr>
                  <th>Reference Number</th>
                  <th>Title</th>
                  <th>Category</th>
                  <th>Date Submitted</th>
                  <th>Jurisdiction Status</th>
                  <th>Complaint Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($complaints as $complaint): ?>
                  <tr>
                    <td class="fw-semibold"><?php echo htmlspecialchars($complaint['reference_number'] ?: 'Pending'); ?></td>
                    <td><?php echo htmlspecialchars($complaint['title'] ?: 'Untitled Complaint'); ?></td>
                    <td><?php echo htmlspecialchars($complaint['category'] ?: 'Others'); ?></td>
                    <td><?php echo !empty($complaint['date_submitted']) ? htmlspecialchars(date('F d, Y h:i A', strtotime($complaint['date_submitted']))) : '-'; ?></td>
                    <td><span class="badge text-bg-<?php echo ($complaint['jurisdiction_status'] ?? 'Valid') === 'Valid' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($complaint['jurisdiction_status'] ?: 'Valid'); ?></span></td>
                    <td><?php $displayStatus = residentComplaintsDisplayStatus($complaint['status'] ?? ''); ?><span class="badge text-bg-<?php echo residentComplaintsStatusClass($displayStatus); ?>"><?php echo htmlspecialchars($displayStatus); ?></span></td>
                    <td><a href="<?php echo BASE_URL; ?>complaints/complaint_details.php?id=<?php echo (int)$complaint['id']; ?>" class="btn btn-sm btn-outline-primary">View Details</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

<script>
(function () {
  const profileTrigger = document.getElementById('profileTrigger');
  const dropdownMenu = document.getElementById('dropdownMenu');
  const sidebar = document.getElementById('sidebar');
  const menuToggle = document.getElementById('menuToggle');
  const topDateBadge = document.getElementById('topDateBadge');
  const mainDateBadge = document.getElementById('mainDateBadge');

  function setDateBadges() {
    const today = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: '2-digit' });
    if (topDateBadge) topDateBadge.textContent = today;
    if (mainDateBadge) mainDateBadge.textContent = today;
  }

  if (profileTrigger && dropdownMenu) {
    profileTrigger.addEventListener('click', function () {
      const expanded = profileTrigger.getAttribute('aria-expanded') === 'true';
      profileTrigger.setAttribute('aria-expanded', String(!expanded));
      dropdownMenu.classList.toggle('open', !expanded);
    });

    document.addEventListener('click', function (event) {
      if (!event.target.closest('#profileDropdown')) {
        profileTrigger.setAttribute('aria-expanded', 'false');
        dropdownMenu.classList.remove('open');
      }
    });
  }

  if (menuToggle && sidebar) {
    menuToggle.addEventListener('click', function () {
      sidebar.classList.toggle('expanded');
    });
  }

  setDateBadges();
})();
</script>
<script src="<?php echo ASSETS_URL; ?>css/js/view-mode-switch.js?v=<?php echo time(); ?>"></script>
</body>
</html>
