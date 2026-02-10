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

<style>
@import url('https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&family=Merriweather:wght@700&display=swap');

.reports-page {
    font-family: 'Source Sans 3', sans-serif;
}
.reports-page .page-title {
    font-family: 'Merriweather', serif;
    letter-spacing: 0.2px;
}
.reports-page .report-card {
    border: 1px solid #e7ecf3;
    border-radius: 16px;
    box-shadow: 0 6px 16px rgba(24, 32, 56, 0.06);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.reports-page .report-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(24, 32, 56, 0.12);
}
.reports-page .report-card .card-body h5 {
    display: flex;
    align-items: center;
    gap: 8px;
}
.reports-page .report-card .btn {
    border-radius: 999px;
    padding: 6px 16px;
}
.reports-page .report-result {
    border-radius: 16px;
    border: 1px solid #e7ecf3;
}
.reports-page .report-result .card-header {
    background: linear-gradient(135deg, #f6f9ff 0%, #ffffff 100%);
}
.report-table th {
    text-transform: capitalize;
}
.print-header {
    display: none;
}

@media print {
    body { background: #fff !important; }
    .navbar, .sidebar, .card:not(.report-result), .btn, .search-bar { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; }
    .report-result { border: none !important; box-shadow: none !important; }
    .report-result .card-header { border: none !important; }
    .print-header { display: block; margin-bottom: 16px; }
    .print-header .print-title {
        font-family: 'Merriweather', serif;
        font-size: 20px;
        margin-bottom: 4px;
    }
    .print-header .print-meta { font-size: 12px; color: #333; }
}
</style>

<div class="main-content reports-page">
    <div class="container-fluid">
        <h2 class="mb-4 page-title"><i class="bi bi-graph-up"></i> Reports</h2>

        <div class="card mb-4 report-card">
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
                <div class="card h-100 report-card">
                    <div class="card-body">
                        <h5><i class="bi bi-people"></i> Population</h5>
                        <p class="text-muted small">Residents by gender and civil status</p>
                        <button class="btn btn-primary" onclick="loadReport('population', 'Population Report')">View Report</button>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 report-card">
                    <div class="card-body">
                        <h5><i class="bi bi-file-earmark-text"></i> Certificates</h5>
                        <p class="text-muted small">Certificates by type and status</p>
                        <button class="btn btn-primary" onclick="loadReport('certificates', 'Certificates Report')">View Report</button>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 report-card">
                    <div class="card-body">
                        <h5><i class="bi bi-file-earmark-person"></i> Applications</h5>
                        <p class="text-muted small">Certificate applications by status</p>
                        <button class="btn btn-primary" onclick="loadReport('applications', 'Applications Report')">View Report</button>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 report-card">
                    <div class="card-body">
                        <h5><i class="bi bi-journal-text"></i> Blotters</h5>
                        <p class="text-muted small">Blotter cases by status</p>
                        <button class="btn btn-primary" onclick="loadReport('blotters', 'Blotters Report')">View Report</button>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 report-card">
                    <div class="card-body">
                        <h5><i class="bi bi-exclamation-triangle"></i> Complaints</h5>
                        <p class="text-muted small">Complaints by status</p>
                        <button class="btn btn-primary" onclick="loadReport('complaints', 'Complaints Report')">View Report</button>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 report-card">
                    <div class="card-body">
                        <h5><i class="bi bi-megaphone"></i> Announcements</h5>
                        <p class="text-muted small">Announcements by status</p>
                        <button class="btn btn-primary" onclick="loadReport('announcements', 'Announcements Report')">View Report</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4 report-result" id="reportResult" style="display:none;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0" id="reportTitle">Report</h5>
                <button class="btn btn-sm btn-outline-primary" id="btnPrintReport" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print / Save as PDF
                </button>
            </div>
            <div class="card-body" id="reportContent">
                <div class="print-header">
                    <div class="print-title"><?php echo htmlspecialchars(BARANGAY_NAME); ?> - Reports</div>
                    <div class="print-meta">
                        <div><strong>Report:</strong> <span id="printReportTitle">-</span></div>
                        <div><strong>Date Range:</strong> <span id="printReportRange">All Dates</span></div>
                        <div><strong>Generated:</strong> <span id="printGeneratedAt">-</span></div>
                    </div>
                </div>
                <div id="reportBody"></div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="<?php echo ASSETS_URL; ?>css/js/reports.js?v=<?php echo time(); ?>"></script>
