<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/resident-complaints.php';

residentComplaintsRequireResident();

$db = Database::getInstance();
$residentId = residentComplaintsGetResidentId();
$residentName = residentComplaintsGetResidentName();
$complaintId = (int)($_GET['id'] ?? 0);
$moduleReady = residentComplaintsTableExists($db);
$complaint = ($moduleReady && $complaintId) ? residentComplaintsFetchById($db, $complaintId, $residentId) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Complaint Details | E-Barangay Information Management System</title>
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
      <button class="menu-toggle" id="menuToggle" aria-label="Toggle sidebar"><i class="fa-solid fa-bars"></i></button>
      <div class="logo-wrap" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></div>
      <div class="system-text"><h1>E-Barangay Information Management System</h1><p>Barangay 219, Tondo, Manila</p></div>
    </div>
    <div class="header-right">
      <span class="date-badge" id="topDateBadge"><?php echo date('F d, Y'); ?></span>
      <button class="icon-btn" aria-label="Notifications"><i class="fa-regular fa-bell"></i></button>
      <div class="profile-dropdown" id="profileDropdown">
        <button class="profile-trigger" id="profileTrigger" aria-haspopup="true" aria-expanded="false">
          <img src="https://i.pravatar.cc/100?img=12" alt="Resident avatar"><i class="fa-solid fa-chevron-down"></i>
        </button>
        <div class="dropdown-menu" id="dropdownMenu" role="menu">
          <a href="<?php echo BASE_URL; ?>resident_profile.php" role="menuitem">View Profile</a>
          <a href="<?php echo BASE_URL; ?>../api/auth.php?action=logout" role="menuitem">Logout</a>
        </div>
      </div>
    </div>
  </header>

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-profile"><img src="https://i.pravatar.cc/120?img=12" alt="Resident profile image"><div class="profile-meta label"><h3><?php echo htmlspecialchars($residentName); ?></h3><p>Resident</p></div></div>
    <nav class="sidebar-nav">
      <div class="nav-group"><p class="group-title label">ACCOUNT</p><a class="nav-item" href="<?php echo BASE_URL; ?>resident_profile.php"><i class="fa-regular fa-user"></i><span class="label">My Profile</span></a></div>
      <div class="nav-group"><p class="group-title label">MAIN</p><a class="nav-item" href="<?php echo BASE_URL; ?>resident_dashboard.php"><i class="fa-solid fa-gauge-high"></i><span class="label">Dashboard</span></a></div>
      <div class="nav-group"><p class="group-title label">SERVICES</p><a class="nav-item" href="<?php echo BASE_URL; ?>request_certificate.php"><i class="fa-regular fa-file-lines"></i><span class="label">Request Certificate</span></a><a class="nav-item" href="<?php echo BASE_URL; ?>my_requests.php"><i class="fa-solid fa-list-check"></i><span class="label">My Requests</span></a></div>
      <div class="nav-group"><p class="group-title label">HOUSEHOLD</p><a class="nav-item" href="<?php echo BASE_URL; ?>resident_household.php"><i class="fa-solid fa-house-user"></i><span class="label">Household Information</span></a></div>
      <div class="nav-group"><p class="group-title label">COMMUNITY</p><a class="nav-item" href="<?php echo BASE_URL; ?>resident_announcements.php"><i class="fa-regular fa-newspaper"></i><span class="label">Announcements</span></a><a class="nav-item active" href="<?php echo BASE_URL; ?>complaints/my_complaints.php"><i class="fa-regular fa-comment-dots"></i><span class="label">Complaints / Reports</span></a></div>
    </nav>
    <div class="sidebar-bottom"><a class="nav-item logout" href="<?php echo BASE_URL; ?>../api/auth.php?action=logout"><i class="fa-solid fa-arrow-right-from-bracket"></i><span class="label">Logout</span></a></div>
  </aside>

  <main class="main-content resident-complaints-page">
    <section class="dashboard-head mb-3">
      <div>
        <p class="portal-tag">RESIDENT PORTAL</p>
        <h2>Complaint Details</h2>
        <p class="dashboard-subtitle">Review the full information and case handling status of your complaint.</p>
      </div>
      <div class="head-meta"><span class="view-badge">Resident View</span><span class="date-badge" id="mainDateBadge"><?php echo date('F d, Y'); ?></span></div>
    </section>

    <?php if (!$moduleReady): ?>
      <div class="alert alert-warning shadow-sm border-0"><?php echo htmlspecialchars(residentComplaintsMissingTableMessage()); ?></div>
    <?php elseif (!$complaint): ?>
      <div class="alert alert-warning shadow-sm border-0">The complaint record was not found or does not belong to your account.</div>
    <?php else: ?>
      <?php $displayStatus = residentComplaintsDisplayStatus($complaint['status'] ?? ''); $evidenceUrl = residentComplaintsEvidenceUrl($complaint['evidence_file'] ?? null); ?>
      <div class="row g-4">
        <div class="col-lg-8">
          <div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
            <h5 class="mb-3">Complaint Information</h5>
            <div class="row g-3">
              <div class="col-md-6"><label class="text-muted small d-block">Reference Number</label><div class="fw-semibold"><?php echo htmlspecialchars($complaint['reference_number'] ?: 'Pending'); ?></div></div>
              <div class="col-md-6"><label class="text-muted small d-block">Category</label><div><?php echo htmlspecialchars($complaint['category'] ?: '-'); ?></div></div>
              <div class="col-12"><label class="text-muted small d-block">Title</label><div><?php echo htmlspecialchars($complaint['title'] ?: '-'); ?></div></div>
              <div class="col-12"><label class="text-muted small d-block">Description</label><div><?php echo nl2br(htmlspecialchars($complaint['description'] ?: '-')); ?></div></div>
            </div>
          </div></div>

          <div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
            <h5 class="mb-3">Incident Details</h5>
            <div class="row g-3">
              <div class="col-md-6"><label class="text-muted small d-block">Incident Date</label><div><?php echo !empty($complaint['incident_date']) ? htmlspecialchars(date('F d, Y', strtotime($complaint['incident_date']))) : '-'; ?></div></div>
              <div class="col-md-6"><label class="text-muted small d-block">Incident Time</label><div><?php echo !empty($complaint['incident_time']) ? htmlspecialchars(date('h:i A', strtotime($complaint['incident_time']))) : '-'; ?></div></div>
            </div>
          </div></div>

          <div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
            <h5 class="mb-3">Incident Location</h5>
            <div class="row g-3">
              <div class="col-md-6"><label class="text-muted small d-block">House / Street</label><div><?php echo htmlspecialchars($complaint['incident_house_street'] ?: '-'); ?></div></div>
              <div class="col-md-6"><label class="text-muted small d-block">Purok / Zone</label><div><?php echo htmlspecialchars($complaint['incident_purok'] ?: '-'); ?></div></div>
              <div class="col-md-6"><label class="text-muted small d-block">Landmark</label><div><?php echo htmlspecialchars($complaint['incident_landmark'] ?: '-'); ?></div></div>
              <div class="col-md-6"><label class="text-muted small d-block">Barangay</label><div><?php echo htmlspecialchars($complaint['incident_barangay'] ?: '-'); ?></div></div>
            </div>
          </div></div>

          <div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
            <h5 class="mb-3">Respondent Information</h5>
            <div class="row g-3">
              <div class="col-md-6"><label class="text-muted small d-block">Respondent Name</label><div><?php echo htmlspecialchars($complaint['respondent_name'] ?: '-'); ?></div></div>
              <div class="col-md-6"><label class="text-muted small d-block">Respondent Residency</label><div><?php echo htmlspecialchars($complaint['respondent_residency'] ?: '-'); ?></div></div>
              <div class="col-md-6"><label class="text-muted small d-block">Respondent Address</label><div><?php echo htmlspecialchars($complaint['respondent_address'] ?: '-'); ?></div></div>
              <div class="col-md-3"><label class="text-muted small d-block">Respondent Barangay</label><div><?php echo htmlspecialchars($complaint['respondent_barangay'] ?: '-'); ?></div></div>
              <div class="col-md-3"><label class="text-muted small d-block">Respondent City</label><div><?php echo htmlspecialchars($complaint['respondent_city'] ?: '-'); ?></div></div>
            </div>
          </div></div>

          <div class="card border-0 shadow-sm"><div class="card-body p-4">
            <h5 class="mb-3">Evidence</h5>
            <?php if (!$evidenceUrl): ?>
              <p class="text-muted mb-0">No evidence file was uploaded.</p>
            <?php elseif (residentComplaintsIsImage($complaint['evidence_file'])): ?>
              <img src="<?php echo htmlspecialchars($evidenceUrl); ?>" alt="Uploaded evidence" class="img-fluid rounded" style="max-height:420px;object-fit:cover;">
            <?php else: ?>
              <a href="<?php echo htmlspecialchars($evidenceUrl); ?>" target="_blank" rel="noopener" class="btn btn-outline-primary">Download Evidence File</a>
            <?php endif; ?>
          </div></div>
        </div>

        <div class="col-lg-4">
          <div class="card border-0 shadow-sm"><div class="card-body p-4">
            <h5 class="mb-3">Case Handling</h5>
            <p class="mb-2"><span class="text-muted small d-block">Assigned Barangay Officer</span><?php echo htmlspecialchars($complaint['assigned_officer'] ?: 'Not yet assigned'); ?></p>
            <p class="mb-2"><span class="text-muted small d-block">Complaint Status</span><span class="badge text-bg-<?php echo residentComplaintsStatusClass($displayStatus); ?>"><?php echo htmlspecialchars($displayStatus); ?></span></p>
            <p class="mb-2"><span class="text-muted small d-block">Jurisdiction Status</span><span class="badge text-bg-<?php echo ($complaint['jurisdiction_status'] ?? 'Valid') === 'Valid' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($complaint['jurisdiction_status'] ?: 'Valid'); ?></span></p>
            <p class="mb-2"><span class="text-muted small d-block">Resolution Notes</span><?php echo nl2br(htmlspecialchars($complaint['resolution_notes'] ?: 'No resolution notes yet.')); ?></p>
            <p class="mb-0"><span class="text-muted small d-block">Referral Notes</span><?php echo nl2br(htmlspecialchars($complaint['referral_notes'] ?: 'No referral notes.')); ?></p>
          </div></div>
        </div>
      </div>
    <?php endif; ?>
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
  if (menuToggle && sidebar) menuToggle.addEventListener('click', function () { sidebar.classList.toggle('expanded'); });
  setDateBadges();
})();
</script>
</body>
</html>
