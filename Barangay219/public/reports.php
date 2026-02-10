<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('reports');

$page_title = 'Reports';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <h2 class="mb-4"><i class="bi bi-graph-up"></i> Reports</h2>

        <div class="card mb-4">
            <div class="card-body">
                <h5>Filter by Date</h5>
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label">From</label>
                        <input type="date" class="form-control" id="filterFrom">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To</label>
                        <input type="date" class="form-control" id="filterTo">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-outline-secondary" onclick="applyFilter()">Apply</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5><i class="bi bi-people"></i> Population</h5>
                        <p class="text-muted small">Residents by gender and civil status</p>
                        <button class="btn btn-primary" onclick="loadReport('population', 'Population Report')">View Report</button>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5><i class="bi bi-file-earmark-text"></i> Certificates</h5>
                        <p class="text-muted small">Certificates by type and status</p>
                        <button class="btn btn-primary" onclick="loadReport('certificates', 'Certificates Report')">View Report</button>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5><i class="bi bi-file-earmark-person"></i> Applications</h5>
                        <p class="text-muted small">Certificate applications by status</p>
                        <button class="btn btn-primary" onclick="loadReport('applications', 'Applications Report')">View Report</button>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5><i class="bi bi-journal-text"></i> Blotters</h5>
                        <p class="text-muted small">Blotter cases by status</p>
                        <button class="btn btn-primary" onclick="loadReport('blotters', 'Blotters Report')">View Report</button>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5><i class="bi bi-exclamation-triangle"></i> Complaints</h5>
                        <p class="text-muted small">Complaints by status</p>
                        <button class="btn btn-primary" onclick="loadReport('complaints', 'Complaints Report')">View Report</button>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5><i class="bi bi-megaphone"></i> Announcements</h5>
                        <p class="text-muted small">Announcements by status</p>
                        <button class="btn btn-primary" onclick="loadReport('announcements', 'Announcements Report')">View Report</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4" id="reportResult" style="display:none;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0" id="reportTitle">Report</h5>
                <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print / Save as PDF
                </button>
            </div>
            <div class="card-body" id="reportContent"></div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="<?php echo ASSETS_URL; ?>css/js/reports.js?v=<?php echo time(); ?>"></script>
