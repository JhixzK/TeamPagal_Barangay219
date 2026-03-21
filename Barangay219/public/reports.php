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
.reports-page .report-filter-btn {
    border-radius: 999px;
    border-color: #dbe3ee;
    color: #475569;
    background: #fff;
}
.reports-page .report-filter-btn:hover {
    background: #f5f9ff;
    border-color: #d6e4ff;
    color: #2f4f95;
}
.reports-page .report-result {
    border-radius: 16px;
    border: 1px solid #e7ecf3;
}
.reports-page .report-result .card-header {
    background: linear-gradient(135deg, #f6f9ff 0%, #ffffff 100%);
}
.reports-page .report-summary-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.32rem 0.65rem;
    border-radius: 999px;
    background: #eaf6ff;
    color: #1f5f8b;
    font-weight: 600;
    font-size: 0.82rem;
}
.reports-page .report-table-wrap {
    border: 1px solid #e7ecf3;
    border-radius: 12px;
    overflow-x: auto;
    overflow-y: auto;
    max-height: min(62vh, 640px);
    background: #fff;
    scrollbar-width: thin;
    scrollbar-color: #94a3b8 #f1f5f9;
}
.reports-page .report-table-wrap::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}
.reports-page .report-table-wrap::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 999px;
}
.reports-page .report-table-wrap::-webkit-scrollbar-thumb {
    background: #94a3b8;
    border-radius: 999px;
    border: 2px solid #f1f5f9;
}
.reports-page .report-table-wrap::-webkit-scrollbar-thumb:hover {
    background: #64748b;
}
.reports-page .report-table {
    margin-bottom: 0;
}
.reports-page .report-table > :not(caption) > * > * {
    border-bottom: 1px solid #edf1f6;
    padding: 0.75rem 0.8rem;
    vertical-align: middle;
}
.reports-page .report-table thead th {
    text-transform: capitalize;
    border-bottom: 1px solid #dfe6ef;
    color: #4b5563;
    font-size: 0.81rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    background: #f9fbfd;
    position: sticky;
    top: 0;
    z-index: 2;
}
.reports-page .report-table tbody td {
    color: #334155;
    font-size: 0.92rem;
}
.reports-page .report-table tbody tr:hover {
    background: #f8fbff;
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

<div class="main-content module-page reports-page">
    <div class="container-fluid">
        <div class="module-hero card border-0 shadow-sm mb-4">
            <div class="card-body">
                <p class="module-kicker text-uppercase small mb-1">Communication Module</p>
                <h2 class="mb-1 page-title"><i class="bi bi-graph-up me-2"></i>Reports</h2>
                <p class="module-subtitle mb-0">Generate operational insights across residents, services, cases, and activity logs.</p>
            </div>
        </div>

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
                        <button class="btn report-filter-btn" onclick="applyFilter()"><i class="bi bi-funnel"></i> Apply</button>
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
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 report-card">
                    <div class="card-body">
                        <h5><i class="bi bi-clock-history"></i> Activity Logs</h5>
                        <p class="text-muted small">User activity logs with date filter</p>
                        <button class="btn btn-primary" onclick="loadReport('activity_logs', 'Activity Logs Report')">View Report</button>
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
