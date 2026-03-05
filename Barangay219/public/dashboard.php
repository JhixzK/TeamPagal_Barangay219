<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('dashboard');

$page_title = 'Dashboard';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content dashboard-page">
    <div class="container-fluid">
        <div class="dashboard-hero card border-0 shadow-sm mb-4">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="hero-copy">
                    <p class="hero-kicker text-uppercase small mb-1">Barangay Operations Center</p>
                    <h2 class="mb-1"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h2>
                    <p class="hero-subtitle mb-0">Real-time snapshot of residents, services, and community activities.</p>
                </div>
                <div class="text-md-end hero-meta">
                    <span class="hero-date-badge fs-6 px-3 py-2">
                        <i class="bi bi-calendar3 me-1"></i><?php echo date('F d, Y'); ?>
                    </span>
                    <div class="hero-chips mt-2">
                        <span class="hero-chip"><i class="bi bi-broadcast-pin me-1"></i>Live Data</span>
                        <span class="hero-chip"><i class="bi bi-shield-check me-1"></i>Admin View</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4" id="statsCards">
            <div class="col-sm-6 col-xl-2">
                <a href="<?php echo BASE_URL; ?>residents.php" class="text-decoration-none dashboard-kpi-link">
                    <div class="dashboard-kpi-card kpi-primary">
                        <div class="kpi-icon"><i class="bi bi-people"></i></div>
                        <div class="kpi-value" id="totalResidents">-</div>
                        <div class="kpi-label">Total Residents</div>
                        <div class="kpi-foot">View details <i class="bi bi-arrow-right-short"></i></div>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-xl-2">
                <a href="<?php echo BASE_URL; ?>households.php" class="text-decoration-none dashboard-kpi-link">
                    <div class="dashboard-kpi-card kpi-success">
                        <div class="kpi-icon"><i class="bi bi-house-door"></i></div>
                        <div class="kpi-value" id="totalHouseholds">-</div>
                        <div class="kpi-label">Total Households</div>
                        <div class="kpi-foot">View details <i class="bi bi-arrow-right-short"></i></div>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-xl-2">
                <a href="<?php echo BASE_URL; ?>applications.php" class="text-decoration-none dashboard-kpi-link">
                    <div class="dashboard-kpi-card kpi-info">
                        <div class="kpi-icon"><i class="bi bi-file-earmark-check"></i></div>
                        <div class="kpi-value" id="issuedCertificates">-</div>
                        <div class="kpi-label">Issued Certificates</div>
                        <div class="kpi-foot">View details <i class="bi bi-arrow-right-short"></i></div>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-xl-2">
                <a href="<?php echo BASE_URL; ?>applications.php" class="text-decoration-none dashboard-kpi-link">
                    <div class="dashboard-kpi-card kpi-warning">
                        <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
                        <div class="kpi-value" id="pendingApplications">-</div>
                        <div class="kpi-label">Pending Applications</div>
                        <div class="kpi-foot">View details <i class="bi bi-arrow-right-short"></i></div>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-xl-2">
                <a href="<?php echo BASE_URL; ?>complaints.php" class="text-decoration-none dashboard-kpi-link">
                    <div class="dashboard-kpi-card kpi-danger">
                        <div class="kpi-icon"><i class="bi bi-exclamation-triangle"></i></div>
                        <div class="kpi-value" id="pendingComplaints">-</div>
                        <div class="kpi-label">Pending Complaints</div>
                        <div class="kpi-foot">View details <i class="bi bi-arrow-right-short"></i></div>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-xl-2">
                <a href="<?php echo BASE_URL; ?>announcement.php" class="text-decoration-none dashboard-kpi-link">
                    <div class="dashboard-kpi-card kpi-secondary">
                        <div class="kpi-icon"><i class="bi bi-megaphone"></i></div>
                        <div class="kpi-value" id="activeAnnouncements">-</div>
                        <div class="kpi-label">Announcements</div>
                        <div class="kpi-foot">View details <i class="bi bi-arrow-right-short"></i></div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card dashboard-panel h-100 border-0 shadow-sm">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Overview</h5>
                        <span class="text-muted small">Updated automatically</span>
                    </div>
                    <div class="card-body pt-0">
                        <div class="dashboard-chart-wrap">
                            <canvas id="overviewChart" height="180"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card dashboard-panel h-100 border-0 shadow-sm">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Activities</h5>
                        <a href="<?php echo BASE_URL; ?>reports.php" class="btn btn-sm btn-outline-primary">Reports</a>
                    </div>
                    <div class="card-body p-0">
                        <div id="recentActivitiesList" class="list-group list-group-flush dashboard-activity-list">
                            <div class="list-group-item text-center text-muted py-4">Loading...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-page .dashboard-hero {
    border-radius: 16px;
    background:
        radial-gradient(circle at 0% 0%, rgba(147, 197, 253, 0.24), transparent 36%),
        linear-gradient(140deg, #f8fbff 0%, #eef4ff 58%, #f4f7fb 100%);
    border: 1px solid rgba(59, 130, 246, 0.2) !important;
    box-shadow: 0 16px 34px -24px rgba(37, 99, 235, 0.45);
}

.dashboard-page .dashboard-hero .card-body {
    padding: 1.2rem 1.3rem;
}

.dashboard-page .hero-kicker {
    color: #334155;
    letter-spacing: 0.08em;
    font-weight: 700;
}

.dashboard-page .hero-copy h2 {
    color: #0f172a;
    font-weight: 700;
}

.dashboard-page .hero-subtitle {
    color: #475569;
    max-width: 640px;
}

.dashboard-page .hero-date-badge {
    display: inline-block;
    border-radius: 999px;
    background: rgba(37, 99, 235, 0.12);
    color: #1e3a8a;
    border: 1px solid rgba(37, 99, 235, 0.22);
    font-weight: 600;
}

.dashboard-page .hero-chips {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

.dashboard-page .hero-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.2rem 0.6rem;
    font-size: 0.78rem;
    color: #334155;
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(148, 163, 184, 0.35);
}

.dashboard-page .dashboard-kpi-card {
    position: relative;
    border-radius: 15px;
    padding: 1rem 1rem 0.8rem;
    min-height: 128px;
    color: #ffffff;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.22);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12);
    transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
}

.dashboard-page .dashboard-kpi-card::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.16), transparent 55%);
    pointer-events: none;
}

.dashboard-page .dashboard-kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 14px 24px rgba(15, 23, 42, 0.2);
    filter: saturate(1.05);
}

.dashboard-page .dashboard-kpi-link:focus-visible .dashboard-kpi-card {
    outline: 3px solid rgba(59, 130, 246, 0.35);
    outline-offset: 2px;
}

.dashboard-page .kpi-icon {
    position: absolute;
    right: 13px;
    top: 11px;
    font-size: 1.22rem;
    opacity: 0.95;
}

.dashboard-page .kpi-value {
    font-size: 2.05rem;
    font-weight: 700;
    line-height: 1.15;
    margin-top: 0.35rem;
    font-variant-numeric: tabular-nums;
}

.dashboard-page .kpi-label {
    font-size: 0.9rem;
    opacity: 0.96;
}

.dashboard-page .kpi-foot {
    margin-top: 0.5rem;
    font-size: 0.78rem;
    opacity: 0.9;
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
}

.dashboard-page .kpi-primary { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); }
.dashboard-page .kpi-success { background: linear-gradient(135deg, #0f766e 0%, #115e59 100%); }
.dashboard-page .kpi-info { background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); }
.dashboard-page .kpi-warning { background: linear-gradient(135deg, #b45309 0%, #92400e 100%); }
.dashboard-page .kpi-danger { background: linear-gradient(135deg, #be123c 0%, #9f1239 100%); }
.dashboard-page .kpi-secondary { background: linear-gradient(135deg, #334155 0%, #1e293b 100%); }

.dashboard-page .dashboard-panel {
    border-radius: 15px;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 16px 28px -24px rgba(15, 23, 42, 0.35);
}

.dashboard-page .dashboard-panel .card-header {
    padding: 1rem 1rem 0.75rem;
    border-bottom: 1px solid #f1f5f9;
}

.dashboard-page .dashboard-chart-wrap {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
    padding: 0.9rem 0.75rem 0.35rem;
}

.dashboard-page .dashboard-activity-list .list-group-item {
    border-left: 0;
    border-right: 0;
    border-color: #f1f5f9;
    padding: 0.9rem 1rem;
}

.dashboard-page .dashboard-activity-list .list-group-item small {
    color: #64748b !important;
}

.dashboard-page .dashboard-activity-list .list-group-item div {
    color: #0f172a;
    font-weight: 500;
    margin: 0.1rem 0;
}

@media (max-width: 768px) {
    .dashboard-page .hero-chips {
        justify-content: flex-start;
    }

    .dashboard-page .hero-meta {
        text-align: left !important;
        width: 100%;
    }

    .dashboard-page .dashboard-kpi-card {
        min-height: 108px;
    }

    .dashboard-page .kpi-value {
        font-size: 1.75rem;
    }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    if (typeof window.API_URL === 'undefined') { window.API_URL = '<?php echo addslashes(API_URL); ?>'; }
</script>
<script src="<?php echo ASSETS_URL; ?>css/js/dashboard.js?v=<?php echo time(); ?>"></script>
