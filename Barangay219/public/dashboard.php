<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();

$currentRole = getCurrentUserRole();
if (normalizeRole($currentRole) === normalizeRole(ROLE_RESIDENT)) {
    header('Location: ' . BASE_URL . 'resident_dashboard.php');
    exit();
}

requireModuleAccess('dashboard');

$page_title = 'Dashboard';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content dashboard-page">
    <div class="container-fluid">
        <!-- Hero Banner -->
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

        <!-- Row 1: KPI Summary Cards -->
        <div class="row g-3 mb-4" id="statsCards">
            <div class="col-6 col-lg-3 col-xl">
                <a href="<?php echo BASE_URL; ?>residents.php" class="text-decoration-none dashboard-kpi-link">
                    <div class="dashboard-kpi-card kpi-primary">
                        <div class="kpi-icon"><i class="bi bi-people"></i></div>
                        <div class="kpi-value" id="totalResidents">-</div>
                        <div class="kpi-label">Total Residents</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-lg-3 col-xl">
                <a href="<?php echo BASE_URL; ?>households.php" class="text-decoration-none dashboard-kpi-link">
                    <div class="dashboard-kpi-card kpi-teal">
                        <div class="kpi-icon"><i class="bi bi-house-door"></i></div>
                        <div class="kpi-value" id="totalHouseholds">-</div>
                        <div class="kpi-label">Total Households</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-lg-3 col-xl">
                <a href="<?php echo BASE_URL; ?>applications.php" class="text-decoration-none dashboard-kpi-link">
                    <div class="dashboard-kpi-card kpi-sky">
                        <div class="kpi-icon"><i class="bi bi-file-earmark-check"></i></div>
                        <div class="kpi-value" id="issuedCertificates">-</div>
                        <div class="kpi-label">Issued Certificates</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-lg-3 col-xl">
                <a href="<?php echo BASE_URL; ?>applications.php" class="text-decoration-none dashboard-kpi-link">
                    <div class="dashboard-kpi-card kpi-amber">
                        <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
                        <div class="kpi-value" id="pendingApplications">-</div>
                        <div class="kpi-label">Pending Requests</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-lg-3 col-xl">
                <a href="<?php echo BASE_URL; ?>complaints.php" class="text-decoration-none dashboard-kpi-link">
                    <div class="dashboard-kpi-card kpi-rose">
                        <div class="kpi-icon"><i class="bi bi-exclamation-triangle"></i></div>
                        <div class="kpi-value" id="pendingComplaints">-</div>
                        <div class="kpi-label">Pending Complaints</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-lg-3 col-xl">
                <div class="dashboard-kpi-card kpi-green">
                    <div class="kpi-icon"><i class="bi bi-check2-circle"></i></div>
                    <div class="kpi-value" id="approvedToday">-</div>
                    <div class="kpi-label">Approved Today</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 col-xl">
                <div class="dashboard-kpi-card kpi-purple">
                    <div class="kpi-icon"><i class="bi bi-person-plus"></i></div>
                    <div class="kpi-value" id="newRegistrationsToday">-</div>
                    <div class="kpi-label">New Today</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 col-xl">
                <a href="<?php echo BASE_URL; ?>announcement.php" class="text-decoration-none dashboard-kpi-link">
                    <div class="dashboard-kpi-card kpi-slate">
                        <div class="kpi-icon"><i class="bi bi-megaphone"></i></div>
                        <div class="kpi-value" id="activeAnnouncements">-</div>
                        <div class="kpi-label">Announcements</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Row 2: Service Requests -->
        <div class="row g-3 mb-4">
            <div class="col-xl-8">
                <div class="card dash-panel h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>Service Requests Over Time</h6>
                    </div>
                    <div class="card-body pt-0">
                        <div class="chart-wrap"><canvas id="chartRequestsTime"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card dash-panel h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="bi bi-pie-chart me-2 text-primary"></i>Request Status</h6>
                    </div>
                    <div class="card-body pt-0 d-flex align-items-center justify-content-center">
                        <div class="chart-wrap-doughnut"><canvas id="chartRequestStatus"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 3: Demographics -->
        <div class="row g-3 mb-4">
            <div class="col-xl-4">
                <div class="card dash-panel h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="bi bi-gender-ambiguous me-2 text-primary"></i>Gender Distribution</h6>
                    </div>
                    <div class="card-body pt-0 d-flex align-items-center justify-content-center">
                        <div class="chart-wrap-doughnut"><canvas id="chartGender"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card dash-panel h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="bi bi-bar-chart me-2 text-primary"></i>Population by Purok / Zone</h6>
                    </div>
                    <div class="card-body pt-0">
                        <div class="chart-wrap"><canvas id="chartPurok"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card dash-panel h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="bi bi-file-text me-2 text-primary"></i>Document Types Requested</h6>
                    </div>
                    <div class="card-body pt-0">
                        <div class="chart-wrap"><canvas id="chartDocTypes"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 4: Household Analytics -->
        <div class="row g-3 mb-4">
            <div class="col-xl-5">
                <div class="card dash-panel h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="bi bi-houses me-2 text-primary"></i>Household Types</h6>
                    </div>
                    <div class="card-body pt-0">
                        <div class="chart-wrap"><canvas id="chartHouseholdTypes"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-7">
                <div class="card dash-panel h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Household Registration Trends</h6>
                    </div>
                    <div class="card-body pt-0">
                        <div class="chart-wrap"><canvas id="chartHouseholdTrends"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 5: Age & System Activity + Recent Activities -->
        <div class="row g-3 mb-4">
            <div class="col-xl-4">
                <div class="card dash-panel h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Age Distribution</h6>
                    </div>
                    <div class="card-body pt-0">
                        <div class="chart-wrap"><canvas id="chartAge"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card dash-panel h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="bi bi-activity me-2 text-primary"></i>Daily Logins (30 days)</h6>
                    </div>
                    <div class="card-body pt-0">
                        <div class="chart-wrap"><canvas id="chartLogins"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card dash-panel h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Activities</h6>
                        <a href="<?php echo BASE_URL; ?>reports.php" class="btn btn-sm btn-outline-primary" style="font-size:.72rem;padding:2px 8px;">Reports</a>
                    </div>
                    <div class="card-body p-0">
                        <div id="recentActivitiesList" class="list-group list-group-flush dash-activity-list">
                            <div class="list-group-item text-center text-muted py-4"><div class="spinner-border spinner-border-sm me-2"></div>Loading...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 6: Special Categories -->
        <div class="row g-3 mb-4" id="specialCategoriesRow">
            <div class="col-12">
                <div class="card dash-panel">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="bi bi-star me-2 text-primary"></i>Special Categories</h6>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row g-3" id="specialCategoriesCards"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ---- Hero ---- */
.dashboard-page .dashboard-hero {
    border-radius: 16px;
    background: radial-gradient(circle at 0% 0%, rgba(147,197,253,0.24), transparent 36%), linear-gradient(140deg, #f8fbff 0%, #eef4ff 58%, #f4f7fb 100%);
    border: 1px solid rgba(59,130,246,0.2) !important;
    box-shadow: 0 16px 34px -24px rgba(37,99,235,0.45);
}
.dashboard-page .dashboard-hero .card-body { padding: 1.2rem 1.3rem; }
.dashboard-page .hero-kicker { color: #334155; letter-spacing: .08em; font-weight: 700; }
.dashboard-page .hero-copy h2 { color: #0f172a; font-weight: 700; }
.dashboard-page .hero-subtitle { color: #475569; max-width: 640px; }
.dashboard-page .hero-date-badge { display: inline-block; border-radius: 999px; background: rgba(37,99,235,0.12); color: #1e3a8a; border: 1px solid rgba(37,99,235,0.22); font-weight: 600; }
.dashboard-page .hero-chips { display: flex; justify-content: flex-end; gap: .5rem; }
.dashboard-page .hero-chip { display: inline-flex; align-items: center; border-radius: 999px; padding: .2rem .6rem; font-size: .78rem; color: #334155; background: rgba(255,255,255,.7); border: 1px solid rgba(148,163,184,.35); }

/* ---- KPI Cards ---- */
.dashboard-page .dashboard-kpi-card {
    position: relative; border-radius: 14px; padding: .85rem 1rem .7rem; min-height: 100px; color: #fff;
    overflow: hidden; border: 1px solid rgba(255,255,255,.22); box-shadow: inset 0 1px 0 rgba(255,255,255,.12);
    transition: transform .2s, box-shadow .2s;
}
.dashboard-page .dashboard-kpi-card::before { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(255,255,255,.16), transparent 55%); pointer-events: none; }
.dashboard-page .dashboard-kpi-card:hover { transform: translateY(-3px); box-shadow: 0 14px 24px rgba(15,23,42,.2); }
.dashboard-page .kpi-icon { position: absolute; right: 12px; top: 10px; font-size: 1.15rem; opacity: .9; }
.dashboard-page .kpi-value { font-size: 1.75rem; font-weight: 700; line-height: 1.15; margin-top: .15rem; font-variant-numeric: tabular-nums; }
.dashboard-page .kpi-label { font-size: .78rem; opacity: .92; margin-top: 2px; }

.kpi-primary  { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
.kpi-teal     { background: linear-gradient(135deg, #0f766e, #115e59); }
.kpi-sky      { background: linear-gradient(135deg, #0284c7, #0369a1); }
.kpi-amber    { background: linear-gradient(135deg, #d97706, #b45309); }
.kpi-rose     { background: linear-gradient(135deg, #be123c, #9f1239); }
.kpi-green    { background: linear-gradient(135deg, #16a34a, #15803d); }
.kpi-purple   { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
.kpi-slate    { background: linear-gradient(135deg, #475569, #334155); }

/* ---- Chart Panels ---- */
.dash-panel { border-radius: 14px; border: 1px solid #e2e8f0 !important; box-shadow: 0 8px 20px -12px rgba(15,23,42,.18); }
.dash-panel .card-header { padding: .85rem 1rem .6rem; border-bottom: 1px solid #f1f5f9; }
.dash-panel .card-header h6 { font-size: .85rem; font-weight: 700; color: #1e293b; }
.chart-wrap { border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc; padding: .75rem .6rem .4rem; position: relative; }
.chart-wrap canvas { max-height: 240px; }
.chart-wrap-doughnut { width: 100%; max-width: 260px; }
.chart-wrap-doughnut canvas { max-height: 240px; }

/* ---- Activity List ---- */
.dash-activity-list { max-height: 340px; overflow-y: auto; }
.dash-activity-list .list-group-item { border-left: 0; border-right: 0; border-color: #f1f5f9; padding: .65rem .85rem; font-size: .8rem; }
.dash-activity-list .act-user { font-weight: 600; color: #334155; font-size: .75rem; }
.dash-activity-list .act-action { color: #0f172a; font-weight: 500; margin: 1px 0; }
.dash-activity-list .act-time { color: #94a3b8; font-size: .7rem; }

/* ---- Special Category Cards ---- */
.special-cat-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: .75rem 1rem; text-align: center; }
.special-cat-card .sc-value { font-size: 1.5rem; font-weight: 700; color: #1e293b; }
.special-cat-card .sc-label { font-size: .75rem; color: #64748b; font-weight: 500; }
.special-cat-card .sc-icon { font-size: 1.1rem; color: #3b82f6; margin-bottom: 4px; }

/* ---- No Data ---- */
.chart-no-data { display: flex; align-items: center; justify-content: center; height: 180px; color: #94a3b8; font-size: .85rem; }

/* ---- Responsive ---- */
@media (max-width: 768px) {
    .dashboard-page .hero-chips { justify-content: flex-start; }
    .dashboard-page .hero-meta { text-align: left !important; width: 100%; }
    .dashboard-page .kpi-value { font-size: 1.4rem; }
    .dashboard-page .dashboard-kpi-card { min-height: 88px; padding: .7rem .8rem .6rem; }
    .chart-wrap canvas, .chart-wrap-doughnut canvas { max-height: 200px; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    if (typeof window.API_URL === 'undefined') { window.API_URL = '<?php echo addslashes(API_URL); ?>'; }
</script>
<script src="<?php echo ASSETS_URL; ?>css/js/dashboard.js?v=<?php echo time(); ?>"></script>
