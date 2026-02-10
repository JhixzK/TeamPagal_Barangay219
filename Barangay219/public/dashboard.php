<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('dashboard');

$page_title = 'Dashboard';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <h2 class="mb-4"><i class="bi bi-speedometer2"></i> Dashboard</h2>
        
        <!-- Statistics Cards -->
        <div class="row g-3 mb-4" id="statsCards">
            <div class="col-md-4 col-lg-2">
                <a href="<?php echo BASE_URL; ?>residents.php" class="text-decoration-none">
                    <div class="stat-card bg-primary text-white">
                        <div class="stat-icon"><i class="bi bi-people"></i></div>
                        <div class="stat-value" id="totalResidents">-</div>
                        <div class="stat-label">Total Residents</div>
                    </div>
                </a>
            </div>
            <div class="col-md-4 col-lg-2">
                <a href="<?php echo BASE_URL; ?>households.php" class="text-decoration-none">
                    <div class="stat-card bg-success text-white">
                        <div class="stat-icon"><i class="bi bi-house-door"></i></div>
                        <div class="stat-value" id="totalHouseholds">-</div>
                        <div class="stat-label">Total Households</div>
                    </div>
                </a>
            </div>
            <div class="col-md-4 col-lg-2">
                <a href="<?php echo BASE_URL; ?>applications.php" class="text-decoration-none">
                    <div class="stat-card bg-info text-white">
                        <div class="stat-icon"><i class="bi bi-file-earmark-check"></i></div>
                        <div class="stat-value" id="issuedCertificates">-</div>
                        <div class="stat-label">Issued Certificates</div>
                    </div>
                </a>
            </div>
            <div class="col-md-4 col-lg-2">
                <a href="<?php echo BASE_URL; ?>applications.php" class="text-decoration-none">
                    <div class="stat-card bg-warning text-dark">
                        <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                        <div class="stat-value" id="pendingApplications">-</div>
                        <div class="stat-label">Pending Applications</div>
                    </div>
                </a>
            </div>
            <div class="col-md-4 col-lg-2">
                <a href="<?php echo BASE_URL; ?>complaints.php" class="text-decoration-none">
                    <div class="stat-card bg-danger text-white">
                        <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
                        <div class="stat-value" id="pendingComplaints">-</div>
                        <div class="stat-label">Pending Complaints</div>
                    </div>
                </a>
            </div>
            <div class="col-md-4 col-lg-2">
                <a href="<?php echo BASE_URL; ?>announcement.php" class="text-decoration-none">
                    <div class="stat-card bg-secondary text-white">
                        <div class="stat-icon"><i class="bi bi-megaphone"></i></div>
                        <div class="stat-value" id="activeAnnouncements">-</div>
                        <div class="stat-label">Announcements</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Charts and Activities Row -->
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Overview</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="overviewChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Activities</h5>
                        <a href="<?php echo BASE_URL; ?>reports.php" class="btn btn-sm btn-outline-primary">Reports</a>
                    </div>
                    <div class="card-body p-0">
                        <div id="recentActivitiesList" class="list-group list-group-flush">
                            <div class="list-group-item text-center text-muted py-4">Loading...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Navigation -->
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-link-45deg"></i> Quick Navigation</h5>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <?php if (canAccessModule('applications')): ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="<?php echo BASE_URL; ?>applications.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-file-earmark-person"></i> Applications
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (canAccessModule('residents')): ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="<?php echo BASE_URL; ?>residents.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-people"></i> Residents
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (canAccessModule('certificates')): ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="<?php echo BASE_URL; ?>certificates.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-file-earmark-text"></i> Certificates
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (canAccessModule('blotters')): ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="<?php echo BASE_URL; ?>blotter.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-journal-text"></i> Blotters
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (canAccessModule('complaints')): ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="<?php echo BASE_URL; ?>complaints.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-exclamation-triangle"></i> Complaints
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (canAccessModule('announcements')): ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="<?php echo BASE_URL; ?>announcement.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-megaphone"></i> Announcements
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (canAccessModule('reports')): ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="<?php echo BASE_URL; ?>reports.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-graph-up"></i> Reports
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    if (typeof window.API_URL === 'undefined') { window.API_URL = '<?php echo addslashes(API_URL); ?>'; }
</script>
<script src="<?php echo ASSETS_URL; ?>css/js/dashboard.js?v=<?php echo time(); ?>"></script>
