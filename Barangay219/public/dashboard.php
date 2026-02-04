<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();

$page_title = 'Dashboard';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <h2 class="mb-4"><i class="bi bi-speedometer2"></i> Dashboard</h2>
        
        <div class="row" id="statsCards">
            <div class="col-md-3">
                <div class="stat-card bg-primary text-white">
                    <div class="stat-icon"><i class="bi bi-people"></i></div>
                    <div class="stat-value" id="totalResidents">-</div>
                    <div class="stat-label">Total Residents</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-success text-white">
                    <div class="stat-icon"><i class="bi bi-house-door"></i></div>
                    <div class="stat-value" id="totalHouseholds">-</div>
                    <div class="stat-label">Total Households</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-warning text-dark">
                    <div class="stat-icon"><i class="bi bi-file-earmark-text"></i></div>
                    <div class="stat-value" id="pendingCertificates">-</div>
                    <div class="stat-label">Pending Certificates</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-danger text-white">
                    <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="stat-value" id="pendingComplaints">-</div>
                    <div class="stat-label">Pending Complaints</div>
                </div>
            </div>
        </div>
    </div> <!-- End container-fluid -->

<?php include __DIR__ . '/../includes/footer.php'; ?>

<!-- Define API URL for JavaScript (if not already defined in header) -->
<script>
    if (typeof window.API_URL === 'undefined') {
        window.API_URL = '<?php echo API_URL; ?>';
    }
</script>
<script src="<?php echo ASSETS_URL; ?>css/js/dashboard.js?v=<?php echo time(); ?>"></script>
