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

        <div class="search-bar mb-3">
            <div class="row">
                <div class="col-md-6">
                    <input type="text" class="form-control" id="searchInput" placeholder="Search by name, reference, or contact...">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100 search-action-btn" onclick="searchApplications()">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100 filter-action-btn" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100 reset-action-btn" onclick="resetApplications()">
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

        <div class="data-table resident-apps-table-wrap">
            <div class="table-responsive resident-apps-table-scroll">
                <table class="table table-hover resident-apps-table align-middle">
                    <thead>
                        <tr>
                            <th class="text-center">Ref #</th>
                            <th class="text-center">Applicant</th>
                            <th class="text-center">Sex</th>
                            <th class="text-center">Contact</th>
                            <th class="text-center">Submitted</th>
                            <th class="text-center" style="width: 140px;">Household Role</th>
                            <th class="text-center">Status</th>
                            <th class="text-center resident-apps-actions-col actions-col-compact">View</th>
                        </tr>
                    </thead>
                    <tbody id="applicationsTableBody">
                        <tr><td colspan="8" class="text-center py-4">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-center mt-2 mb-1 px-2" id="residentAppsPaginationOuter" style="display:none;" aria-label="Resident applications pages">
                <div id="pagination" role="group"></div>
            </div>
        </div>
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

.resident-apps-page .search-action-btn,
.resident-apps-page .filter-action-btn,
.resident-apps-page .reset-action-btn {
    min-height: 40px;
    border-radius: 10px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
}

.resident-apps-page .search-action-btn {
    background: #1f6fe8;
    border-color: #1f6fe8;
    color: #fff;
}

.resident-apps-page .search-action-btn:hover {
    background: #1a62cf;
    border-color: #1a62cf;
}

.resident-apps-page .filter-action-btn {
    color: #64748b;
    border-color: #cbd5e1;
    background: #fff;
}

.resident-apps-page .filter-action-btn:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #334155;
}

.resident-apps-page .reset-action-btn {
    background: #6b7280;
    border-color: #6b7280;
    color: #fff;
}

.resident-apps-page .reset-action-btn:hover {
    background: #5b6471;
    border-color: #5b6471;
}

.resident-apps-page .app-tabs {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    border-bottom: 0;
    gap: 0.45rem;
    width: 100%;
}

.resident-apps-page .app-tabs .nav-item {
    margin: 0;
}

.resident-apps-page .app-tabs .nav-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    border: 1px solid #dbe3ee;
    border-radius: 999px;
    color: #475569;
    font-weight: 600;
    padding: 0.55rem 0.85rem;
    background: #ffffff;
    white-space: nowrap;
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

.resident-apps-page .resident-apps-table-wrap {
    border: 1px solid #e7ecf3;
    border-radius: 14px;
    background: #fff;
    padding: 0.35rem;
}

.resident-apps-page .resident-apps-table {
    margin-bottom: 0;
}

.resident-apps-page .resident-apps-table-scroll {
    overflow-x: auto;
    overflow-y: visible;
}

.resident-apps-page .resident-apps-table > :not(caption) > * > * {
    border-bottom: 1px solid #edf1f6;
    padding: 0.9rem 0.85rem;
    vertical-align: middle;
}

.resident-apps-page .resident-apps-table thead th {
    border-bottom: 1px solid #dfe6ef;
    color: #4b5563;
    font-size: 0.82rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    background: #f9fbfd;
}

.resident-apps-page .resident-apps-table tbody td {
    color: #1f2937;
    font-size: 0.94rem;
}

.resident-apps-page .resident-apps-table tbody tr:hover {
    background: #f8fbff;
}

.resident-apps-page .resident-apps-secondary {
    color: #6b7280;
    font-size: 0.86rem;
}

.resident-apps-page .resident-apps-code-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.28rem 0.55rem;
    border-radius: 999px;
    background: #f2f5fa;
    border: 1px solid #e2e8f0;
    color: #465468;
    font-size: 0.76rem;
    font-weight: 600;
    line-height: 1;
}

.resident-apps-page .resident-apps-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 86px;
    padding: 0.35rem 0.65rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.01em;
}

.resident-apps-page .resident-apps-pill.status-pending {
    background: #fff4e8;
    color: #9a5b11;
}

.resident-apps-page .resident-apps-pill.status-approved {
    background: #e9f8ef;
    color: #1f7a3f;
}

.resident-apps-page .resident-apps-pill.status-rejected {
    background: #ffecee;
    color: #a53a44;
}

.resident-apps-page .resident-apps-pill.status-unknown {
    background: #eef2f7;
    color: #4b5563;
}

.resident-apps-page .resident-apps-pill.role-head {
    background: #e8f0ff;
    color: #1f4f8b;
}

.resident-apps-page .resident-apps-pill.role-member {
    background: #eef2f7;
    color: #4b5563;
}

.resident-apps-page .resident-apps-pill.role-unassigned {
    background: #fff4e8;
    color: #9a5b11;
}

.resident-apps-page #applicationsTableBody .status-pending,
.resident-apps-page #applicationsTableBody .badge.bg-warning {
    background: #fff4e8 !important;
    color: #9a5b11 !important;
    border-color: #ffe5c4 !important;
}

.resident-apps-page #applicationsTableBody .status-approved,
.resident-apps-page #applicationsTableBody .badge.bg-success {
    background: #e9f8ef !important;
    color: #1f7a3f !important;
    border-color: #cbeed9 !important;
}

.resident-apps-page #applicationsTableBody .status-rejected,
.resident-apps-page #applicationsTableBody .badge.bg-danger {
    background: #ffecee !important;
    color: #a53a44 !important;
    border-color: #ffd6dc !important;
}

.resident-apps-page #applicationsTableBody .role-head,
.resident-apps-page #applicationsTableBody .badge.bg-primary {
    background: #e8f0ff !important;
    color: #1f4f8b !important;
    border-color: #cfe0ff !important;
}

.resident-apps-page #applicationsTableBody .role-member,
.resident-apps-page #applicationsTableBody .badge.bg-secondary {
    background: #eef2f7 !important;
    color: #4b5563 !important;
    border-color: #e2e8f0 !important;
}

.resident-apps-page #applicationsTableBody .status-pending,
.resident-apps-page #applicationsTableBody .status-approved,
.resident-apps-page #applicationsTableBody .status-rejected,
.resident-apps-page #applicationsTableBody .role-head,
.resident-apps-page #applicationsTableBody .role-member,
.resident-apps-page #applicationsTableBody .badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 28px;
    padding: 0.33rem 0.62rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    border: 1px solid transparent;
}

.resident-apps-page .resident-apps-actions {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
}

/* Hard fallback: normalize any bootstrap-styled action controls in the table action cell */
.resident-apps-page #applicationsTableBody .resident-apps-actions .btn,
.resident-apps-page #applicationsTableBody .resident-apps-actions a,
.resident-apps-page #applicationsTableBody .resident-apps-actions button {
    width: 32px !important;
    height: 32px !important;
    min-width: 32px !important;
    min-height: 32px !important;
    padding: 0 !important;
    border-radius: 8px !important;
    border: 1px solid #e6ebf2 !important;
    background: #ffffff !important;
    color: #5b6678 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-shadow: none !important;
    text-decoration: none !important;
}

.resident-apps-page #applicationsTableBody td.resident-apps-actions-col .btn,
.resident-apps-page #applicationsTableBody td.resident-apps-actions-col a,
.resident-apps-page #applicationsTableBody td.resident-apps-actions-col button {
    width: 32px !important;
    height: 32px !important;
    min-width: 32px !important;
    min-height: 32px !important;
    padding: 0 !important;
    border-radius: 8px !important;
    border: 1px solid #e6ebf2 !important;
    background: #ffffff !important;
    color: #5b6678 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-shadow: none !important;
    text-decoration: none !important;
}

.resident-apps-page .resident-apps-table tbody td:last-child .btn.btn-primary,
.resident-apps-page .resident-apps-table tbody td:last-child .btn.btn-outline-info,
.resident-apps-page .resident-apps-table tbody td:last-child .btn.btn-info,
.resident-apps-page .resident-apps-table tbody td:last-child .btn.btn-outline-primary,
.resident-apps-page .resident-apps-table tbody td:last-child .btn.btn-outline-secondary {
    width: 32px !important;
    height: 32px !important;
    min-width: 32px !important;
    min-height: 32px !important;
    padding: 0 !important;
    border-radius: 8px !important;
    border: 1px solid #e6ebf2 !important;
    background: #ffffff !important;
    color: #5b6678 !important;
    box-shadow: none !important;
}

.resident-apps-page .resident-apps-table tbody td:last-child .btn.btn-primary:hover,
.resident-apps-page .resident-apps-table tbody td:last-child .btn.btn-outline-info:hover,
.resident-apps-page .resident-apps-table tbody td:last-child .btn.btn-info:hover,
.resident-apps-page .resident-apps-table tbody td:last-child .btn.btn-outline-primary:hover,
.resident-apps-page .resident-apps-table tbody td:last-child .btn.btn-outline-secondary:hover {
    background: #f5f9ff !important;
    border-color: #d6e4ff !important;
    color: #2f4f95 !important;
}

.resident-apps-page #applicationsTableBody .resident-apps-actions .btn:hover,
.resident-apps-page #applicationsTableBody .resident-apps-actions .btn:focus-visible,
.resident-apps-page #applicationsTableBody .resident-apps-actions a:hover,
.resident-apps-page #applicationsTableBody .resident-apps-actions a:focus-visible,
.resident-apps-page #applicationsTableBody .resident-apps-actions button:hover,
.resident-apps-page #applicationsTableBody .resident-apps-actions button:focus-visible {
    background: #f5f9ff !important;
    border-color: #d6e4ff !important;
    color: #2f4f95 !important;
    transform: translateY(-1px);
}

.resident-apps-page #applicationsTableBody td.resident-apps-actions-col .btn:hover,
.resident-apps-page #applicationsTableBody td.resident-apps-actions-col .btn:focus-visible,
.resident-apps-page #applicationsTableBody td.resident-apps-actions-col a:hover,
.resident-apps-page #applicationsTableBody td.resident-apps-actions-col a:focus-visible,
.resident-apps-page #applicationsTableBody td.resident-apps-actions-col button:hover,
.resident-apps-page #applicationsTableBody td.resident-apps-actions-col button:focus-visible {
    background: #f5f9ff !important;
    border-color: #d6e4ff !important;
    color: #2f4f95 !important;
    transform: translateY(-1px);
}

.resident-apps-page #applicationsTableBody .resident-apps-actions i {
    font-size: 0.92rem;
    line-height: 1;
}

.resident-apps-page .action-icon-btn {
    width: 32px;
    height: 32px;
    border: 1px solid #e6ebf2;
    background: #ffffff;
    color: #5b6678;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
}

.resident-apps-page .action-icon-btn:hover,
.resident-apps-page .action-icon-btn:focus-visible {
    background: #f5f9ff;
    border-color: #d6e4ff;
    color: #2f4f95;
    transform: translateY(-1px);
}

/* Safety override: if old cached markup still has action-view/action-link, render like standard buttons */
.resident-apps-page .action-icon-btn.action-view,
.resident-apps-page .action-icon-btn.action-link {
    background: #ffffff !important;
    border-color: #e6ebf2 !important;
    color: #5b6678 !important;
}

.resident-apps-page .action-icon-btn.action-view:hover,
.resident-apps-page .action-icon-btn.action-view:focus-visible,
.resident-apps-page .action-icon-btn.action-link:hover,
.resident-apps-page .action-icon-btn.action-link:focus-visible {
    background: #f5f9ff !important;
    border-color: #d6e4ff !important;
    color: #2f4f95 !important;
}

@media (max-width: 768px) {
    .resident-apps-page .app-tabs {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .resident-apps-page .resident-apps-table > :not(caption) > * > * {
        padding: 0.75rem 0.6rem;
    }
}
</style>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="approveId">
                <div class="mb-2">
                    <label class="form-label">Application ID</label>
                    <input type="text" class="form-control" id="approveIdDisplay" readonly>
                </div>
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
    <div class="modal-dialog modal-dialog-centered">
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

<!-- Assign Household Modal (Approved applications) -->
<div class="modal fade" id="assignHouseholdModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-house-check me-2"></i>Assign Household
                    <span class="badge bg-primary ms-2">Approved</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="assignApplicationId">
                <input type="hidden" id="assignResidentId">

                <div class="mb-3">
                    <label class="form-label"><span class="badge bg-light text-dark border me-1">1</span>Select Household</label>
                    <select class="form-select" id="assignHouseholdId">
                        <option value="">Loading households...</option>
                    </select>
                    <small class="text-muted">Choose the household this approved resident should belong to.</small>
                </div>
                <div class="small text-muted" id="assignHouseholdRoleHint">Role will be detected from the approved application.</div>
                <div class="mb-3" id="assignFamilyHeadRow" style="display:none;">
                    <label class="form-label"><span class="badge bg-light text-dark border me-1">2</span>Select Family Head (if household has multiple)</label>
                    <select class="form-select" id="assignFamilyHeadId">
                        <option value="">-- Select family head --</option>
                    </select>
                    <small class="text-muted">This determines which family head code the member will belong to.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="btnAssignHousehold">
                    <i class="bi bi-house-check"></i> Assign
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Assign Heads Modal (Table of approved heads) -->
<div class="modal fade" id="assignHeadsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-people me-2"></i>Assign Household - Approved Heads
                    <span class="badge bg-success ms-2">Heads</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    <span class="badge bg-info me-1">Info</span>
                    Assign approved head accounts to households. Heads not yet assigned are highlighted.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Ref #</th>
                                <th>Applicant</th>
                                <th>Status</th>
                                <th>Household</th>
                                <th class="text-end" style="width:100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="assignHeadsTableBody">
                            <tr><td colspan="5" class="text-center py-4">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
                <p id="assignHeadsEmpty" class="text-muted text-center py-3" style="display:none;">No approved heads found.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
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
