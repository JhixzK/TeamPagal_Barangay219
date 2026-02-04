<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireAnyRole([ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY, ROLE_TREASURER]);

$page_title = 'Reports';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <h2><i class="bi bi-graph-up"></i> Reports</h2>
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5>Population Statistics</h5>
                        <button class="btn btn-primary" onclick="loadReport('population')">View Report</button>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5>Certificates Report</h5>
                        <button class="btn btn-primary" onclick="loadReport('certificates')">View Report</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="<?php echo ASSETS_URL; ?>css/js/reports.js"></script>
