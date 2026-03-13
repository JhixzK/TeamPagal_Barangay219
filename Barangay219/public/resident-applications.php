<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('resident_applications');

$page_title = 'Resident Applications';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content module-page resident-apps-page">
    <div class="container-fluid">
        <div class="module-hero card border-0 shadow-sm mb-4">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <p class="module-kicker text-uppercase small mb-1">Services Module</p>
                    <h2 class="mb-1"><i class="bi bi-person-lines-fill me-2"></i>Resident Applications</h2>
                    <p class="module-subtitle mb-0">Review resident registration requests and process approvals with traceability.</p>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4 module-stats" data-module="resident_applications">
            <div class="col-sm-6 col-lg-4">
                <div class="stat-card bg-warning text-dark" data-status="pending" role="button" tabindex="0">
                    <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                    <div class="stat-value" data-stat="pending">-</div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="stat-card bg-success text-white" data-status="approved" role="button" tabindex="0">
                    <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                    <div class="stat-value" data-stat="approved">-</div>
                    <div class="stat-label">Approved</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="stat-card bg-danger text-white" data-status="rejected" role="button" tabindex="0">
                    <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
                    <div class="stat-value" data-stat="rejected">-</div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
        </div>

        <div class="search-bar mb-3">
            <div class="row">
                <div class="col-md-6">
                    <input type="text" class="form-control" id="searchInput" placeholder="Search by name, reference, or contact...">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" onclick="searchApplications()">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100" onclick="resetApplications()">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs app-tabs mb-3" id="statusTabs">
            <li class="nav-item"><a class="nav-link active" href="#" data-status="pending">Pending</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="approved">Approved</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="rejected">Rejected</a></li>
        </ul>

        <div class="table-responsive data-table">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Ref #</th>
                        <th>Applicant</th>
                        <th>Sex</th>
                        <th>Contact</th>
                        <th>Submitted</th>
                        <th>Household Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="applicationsTableBody">
                    <tr><td colspan="8" class="text-center py-4">Loading...</td></tr>
                </tbody>
            </table>
        </div>
        <nav class="mt-3"><ul class="pagination justify-content-center" id="pagination"></ul></nav>
    </div>
</div>

<style>
.resident-apps-page .module-hero {
    border-radius: 16px;
    background: linear-gradient(135deg, #f8fbff 0%, #eff6ff 100%);
    border: 1px solid rgba(37, 99, 235, 0.18) !important;
}

.resident-apps-page .module-kicker {
    letter-spacing: 0.08em;
    color: #475569;
    font-weight: 700;
}

.resident-apps-page .module-subtitle {
    color: #64748b;
}

.resident-apps-page .search-bar {
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 24px -20px rgba(15, 23, 42, 0.35);
}

.resident-apps-page .app-tabs {
    border-bottom: 0;
    gap: 0.35rem;
}

.resident-apps-page .app-tabs .nav-link {
    border: 1px solid #dbe3ee;
    border-radius: 999px;
    color: #475569;
    font-weight: 600;
    padding: 0.4rem 0.85rem;
    background: #ffffff;
}

.resident-apps-page .app-tabs .nav-link.active {
    color: #1d4ed8;
    background: #e8f0ff;
    border-color: #bfdbfe;
}

.resident-apps-page .data-table .table th,
.resident-apps-page .data-table .table td {
    vertical-align: middle;
}
</style>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Application Details - <span id="viewAppRef"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewModalBody"></div>
            <div class="modal-footer" id="viewModalFooter"></div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="approveId">
                <label class="form-label">Remarks (optional)</label>
                <textarea class="form-control" id="approveRemarks" rows="3" placeholder="Approval notes"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="btnApprove"><i class="bi bi-check2-circle"></i> Approve</button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rejectId">
                <label class="form-label">Reason</label>
                <textarea class="form-control" id="rejectReason" rows="3" placeholder="Rejection reason"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="btnReject"><i class="bi bi-x-circle"></i> Reject</button>
            </div>
        </div>
    </div>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Filter Applications</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Sex</label>
                    <select class="form-select" id="filterSex">
                        <option value="">All</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" id="filterFrom">
                </div>
                <div class="mb-3">
                    <label class="form-label">Date To</label>
                    <input type="date" class="form-control" id="filterTo">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="applyApplicationFilters()">Apply Filters</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
    window.RESIDENT_APPLICATIONS_BASE_URL = '<?php echo BASE_URL; ?>';
</script>
<script src="<?php echo ASSETS_URL; ?>css/js/module-stats.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo ASSETS_URL; ?>css/js/resident-applications.js?v=<?php echo time(); ?>"></script>
