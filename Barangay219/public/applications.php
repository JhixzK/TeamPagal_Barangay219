<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
if (!canAccessModule('applications') && !canAccessModule('certificates')) {
    redirectStaffPortalAccessDenied();
}

$page_title = 'Certificate Applications';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content module-page apps-page">
    <div class="container-fluid">
        <div class="module-hero card border-0 shadow-sm mb-4">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <p class="module-kicker text-uppercase small mb-1">Services Module</p>
                    <h2 class="mb-1"><i class="bi bi-file-earmark-person me-2"></i>Certificate Applications</h2>
                    <p class="module-subtitle mb-0">Manage requests, approvals, releases, and certificate records.</p>
                </div>
                <button class="btn btn-primary" id="btnOpenWalkIn" data-bs-toggle="modal" data-bs-target="#walkinModal">
                    <i class="bi bi-lightning-charge"></i> New Walk-in Request
                </button>
            </div>
        </div>

        <div class="search-bar mb-3">
            <div class="row">
                <div class="col-md-6">
                    <input type="text" class="form-control" id="searchInput" placeholder="Search by resident, ref #, or purpose...">
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
            <li class="nav-item"><a class="nav-link active" href="#" data-status="">All</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="pending">Pending</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="approved">Approved</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="ready_for_pickup">Ready for Pickup</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="released">Released</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="rejected">Rejected</a></li>
        </ul>

        <div class="table-responsive data-table apps-table-wrap apps-table-scroll">
            <table class="table table-hover apps-table align-middle">
                <thead>
                    <tr>
                        <th class="text-center">Ref #</th>
                        <th class="text-center">Control #</th>
                        <th class="text-center">Resident</th>
                        <th class="text-center">Type</th>
                        <th class="text-center">Purpose</th>
                        <th class="text-center">Date Requested</th>
                        <th class="text-center">Status</th>
                        <th class="text-center apps-actions-col actions-col-wide">Actions</th>
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
.apps-page .module-hero {
    border-radius: 16px;
    background: linear-gradient(135deg, #f8fbff 0%, #eff6ff 100%);
    border: 1px solid rgba(37, 99, 235, 0.18) !important;
}

.apps-page .module-kicker {
    letter-spacing: 0.08em;
    color: #475569;
    font-weight: 700;
}

.apps-page .module-subtitle {
    color: #64748b;
}

.apps-page .search-bar {
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 24px -20px rgba(15, 23, 42, 0.35);
}

.apps-page .app-tabs {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    border-bottom: 0;
    gap: 0.45rem;
}

.apps-page .app-tabs .nav-item {
    margin: 0;
}

.apps-page .app-tabs .nav-link {
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

.apps-page .app-tabs .nav-link.active {
    color: #1d4ed8;
    background: #e8f0ff;
    border-color: #bfdbfe;
}

.apps-page .data-table .table th,
.apps-page .data-table .table td {
    vertical-align: middle;
}

.apps-page .apps-table-wrap {
    border: 1px solid #e7ecf3;
    border-radius: 14px;
    background: #fff;
    padding: 0.35rem;
}

.apps-page .apps-table {
    margin-bottom: 0;
}

.apps-page .apps-table-scroll {
    max-height: min(62vh, 640px);
    overflow-y: auto;
    overflow-x: auto;
    scrollbar-width: thin;
    scrollbar-color: #94a3b8 #f1f5f9;
}

.apps-page .apps-table-scroll::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

.apps-page .apps-table-scroll::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 999px;
}

.apps-page .apps-table-scroll::-webkit-scrollbar-thumb {
    background: #94a3b8;
    border-radius: 999px;
    border: 2px solid #f1f5f9;
}

.apps-page .apps-table-scroll::-webkit-scrollbar-thumb:hover {
    background: #64748b;
}

.apps-page .apps-table-scroll thead th {
    position: sticky;
    top: 0;
    z-index: 2;
}

.apps-page .apps-table > :not(caption) > * > * {
    border-bottom: 1px solid #edf1f6;
    padding: 0.9rem 0.85rem;
    vertical-align: middle;
}

.apps-page .apps-table thead th {
    border-bottom: 1px solid #dfe6ef;
    color: #4b5563;
    font-size: 0.82rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    background: #f9fbfd;
}

.apps-page .apps-table tbody td {
    color: #1f2937;
    font-size: 0.94rem;
}

.apps-page .apps-table tbody tr:hover {
    background: #f8fbff;
}

.apps-page .apps-secondary {
    color: #6b7280;
    font-size: 0.86rem;
}

.apps-page .data-table code {
    background: #f1f5f9;
    color: #0f172a;
    padding: 0.1rem 0.35rem;
    border-radius: 6px;
}

.apps-page .apps-code-badge {
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

.apps-page .apps-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 106px;
    padding: 0.35rem 0.65rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.01em;
}

.apps-page .apps-pill.status-pending {
    background: #fff4e8;
    color: #9a5b11;
}

.apps-page .apps-pill.status-approved {
    background: #eaf6ff;
    color: #1f5f8b;
}

.apps-page .apps-pill.status-ready-for-pickup {
    background: #edf2ff;
    color: #4c46b7;
}

.apps-page .apps-pill.status-released,
.apps-page .apps-pill.status-issued {
    background: #e9f8ef;
    color: #1f7a3f;
}

.apps-page .apps-pill.status-rejected {
    background: #ffecee;
    color: #a53a44;
}

.apps-page .apps-pill.status-unknown {
    background: #eef2f7;
    color: #4b5563;
}

.apps-page .apps-actions {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
}

.apps-page .action-icon-btn {
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

.apps-page .action-icon-btn:hover,
.apps-page .action-icon-btn:focus-visible {
    background: #f5f9ff;
    border-color: #d6e4ff;
    color: #2f4f95;
    transform: translateY(-1px);
}

.apps-page .action-icon-btn.action-reject:hover,
.apps-page .action-icon-btn.action-reject:focus-visible {
    background: #fff1f3;
    border-color: #f6ccd3;
    color: #9f2f3e;
}

.app-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.9rem;
}

.app-detail-card {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.8rem;
    background: #fff;
}

.app-detail-card h6 {
    margin: 0 0 0.55rem;
    font-size: 0.92rem;
    font-weight: 700;
    color: #1e293b;
}

.detail-row {
    display: grid;
    grid-template-columns: 145px 1fr;
    gap: 0.5rem;
    font-size: 0.9rem;
    margin-bottom: 0.35rem;
}

.detail-row:last-child {
    margin-bottom: 0;
}

.detail-row span {
    color: #64748b;
    font-weight: 600;
}

.attachment-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 0.6rem;
}

.attachment-item {
    border: 1px solid #dbeafe;
    border-radius: 10px;
    padding: 0.4rem;
    background: #f8fbff;
}

.attachment-thumb {
    width: 100%;
    aspect-ratio: 1.4 / 1;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    margin-bottom: 0.4rem;
}

.status-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.82rem;
    font-weight: 700;
    border-radius: 999px;
    padding: 0.2rem 0.6rem;
}

.status-chip.pending { background: #fff7ed; color: #c2410c; }
.status-chip.approved { background: #e0f2fe; color: #0369a1; }
.status-chip.ready_for_pickup { background: #ede9fe; color: #6d28d9; }
.status-chip.released { background: #dcfce7; color: #166534; }
.status-chip.rejected { background: #fee2e2; color: #991b1b; }

.notes-box {
    width: 100%;
    min-height: 110px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    padding: 0.6rem;
    resize: vertical;
}

.notes-actions {
    display: flex;
    gap: 0.45rem;
    margin-top: 0.55rem;
}

@media (max-width: 992px) {
    .app-detail-grid {
        grid-template-columns: 1fr;
    }

    .detail-row {
        grid-template-columns: 120px 1fr;
    }
}

@media (max-width: 992px) {
    .apps-page .app-tabs {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .apps-page .apps-table > :not(caption) > * > * {
        padding: 0.75rem 0.6rem;
    }
}

@media (max-width: 576px) {
    .apps-page .app-tabs {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>

<!-- Direct Issue Walk-in Modal -->
<div class="modal fade" id="walkinModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Walk-in Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Resident <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="walkinResidentSearch" placeholder="Search resident..." autocomplete="off">
                        <button class="btn btn-outline-secondary" type="button" id="clearWalkinResidentBtn">Clear</button>
                    </div>
                    <input type="hidden" id="walkinResidentId" value="">
                    <div id="walkinResidentSearchResults" class="list-group mt-2" style="display:none; max-height:200px; overflow-y:auto;"></div>
                </div>
                <div class="mb-0">
                    <label class="form-label">Certificate Type <span class="text-danger">*</span></label>
                    <select class="form-select" id="walkinCertType" required>
                        <option value="">-- Select Type --</option>
                        <option value="barangay_certificate">Barangay Certificate</option>
                        <option value="transfer_request">Transfer Request</option>
                        <option value="barangay_indigency">Barangay Indigency</option>
                        <option value="barangay_clearance">Barangay Clearance</option>
                        <option value="certificate_residency">Certificate of Residency</option>
                    </select>
                    <small class="text-muted">This will issue directly to Ready for Pickup and open print preview.</small>
                </div>
                <div class="mt-3 d-none" id="walkinPurposeWrap">
                    <label class="form-label">Certificate Purpose <span class="text-danger">*</span></label>
                    <select class="form-select" id="walkinPurposeSelect">
                        <option value="">-- Select Purpose --</option>
                        <option value="Application for Employment">Application for Employment</option>
                        <option value="School Admission/Requirement">School Admission/Requirement</option>
                        <option value="Hospital Purpose">Hospital Purpose</option>
                        <option value="Processing of Calamity">Processing of Calamity</option>
                        <option value="Medical Purpose">Medical Purpose</option>
                        <option value="For Livelihood Loan">For Livelihood Loan</option>
                        <option value="Bank Transaction">Bank Transaction</option>
                        <option value="Indigent Family">Indigent Family</option>
                        <option value="Organized Vending Permit">Organized Vending Permit</option>
                        <option value="DSWD Requirement">DSWD Requirement</option>
                        <option value="For Travel Abroad">For Travel Abroad</option>
                        <option value="Transfer of Residence">Transfer of Residence</option>
                        <option value="Others">Others</option>
                    </select>
                    <input type="text" class="form-control mt-2 d-none" id="walkinPurposeOther" placeholder="Specify purpose for Others">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="btnDirectIssue"><i class="bi bi-printer"></i> Direct Issue & Print</button>
            </div>
        </div>
    </div>
</div>

<!-- View/Edit Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
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

<!-- Finalize Certificate Modal -->
<div class="modal fade" id="releaseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Finalize Certificate for Pickup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="releaseId">
                <div class="mb-2 small text-muted">Finalize the certificate details before marking it ready for pickup.</div>
                <div class="mb-3">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="releaseCertName" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Address <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="releaseCertAddress" rows="2" required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Purpose <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="releaseCertPurpose" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Certificate Body <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="releaseCertBody" rows="10" required readonly style="resize: none;"></textarea>
                    <small class="text-muted">Certificate body is auto-generated from the selected certificate details.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Date Issued <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="releaseDateIssued" required>
                </div>
                <div class="mb-0">
                    <label class="form-label">Control Number</label>
                    <input type="text" class="form-control" id="releaseControlNumber" readonly>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-primary" id="btnPreviewRelease">Preview PDF</button>
                <button type="button" class="btn btn-success" id="btnRelease"><i class="bi bi-bag-check"></i> Mark Ready for Pickup</button>
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
                    <label class="form-label">Certificate Type</label>
                    <select class="form-select" id="filterType">
                        <option value="">All</option>
                        <option value="barangay_certificate">Barangay Certificate</option>
                        <option value="transfer_request">Transfer Request</option>
                        <option value="barangay_indigency">Barangay Indigency</option>
                        <option value="barangay_clearance">Barangay Clearance</option>
                        <option value="certificate_residency">Certificate of Residency</option>
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

<script src="<?php echo ASSETS_URL; ?>css/js/module-stats.js?v=<?php echo time(); ?>"></script>
<script>
(function() {
    let currentStatus = '';
    let currentViewStatus = '';
    let currentPage = 1;
    let applicationFilters = { q: '', type: '', from: '', to: '' };
    let releaseAutoBodyEnabled = true;
    let releaseLastAutoBody = '';
    let currentReleaseCertificateType = '';
    let currentReleaseBirthDate = '';
    let currentReleaseCivilStatus = '';
    let currentReleaseNationality = '';
    let walkInResidentSearchTimer = null;
    const APP_PERMS = {
        canEdit: window.canModulePermission
            ? (window.canModulePermission('applications', 'can_edit') || window.canModulePermission('certificates', 'can_edit'))
            : true
    };

    function applyApplicationPermissions() {
        if (!APP_PERMS.canEdit) {
            const releaseBtn = document.getElementById('btnRelease');
            if (releaseBtn) releaseBtn.style.display = 'none';
            const walkInOpenBtn = document.getElementById('btnOpenWalkIn');
            if (walkInOpenBtn) walkInOpenBtn.style.display = 'none';
            const directIssueBtn = document.getElementById('btnDirectIssue');
            if (directIssueBtn) directIssueBtn.style.display = 'none';
        }
    }

    function loadApplications() {
        const tbody = document.getElementById('applicationsTableBody');
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4"><div class="spinner-border"></div></td></tr>';
        const params = new URLSearchParams({
            action: 'list',
            page: currentPage.toString(),
            _ts: Date.now().toString()
        });
        if (currentStatus) params.append('status', currentStatus);
        if (applicationFilters.q) params.append('q', applicationFilters.q);
        if (applicationFilters.type) params.append('type', applicationFilters.type);
        if (applicationFilters.from) params.append('from', applicationFilters.from);
        if (applicationFilters.to) params.append('to', applicationFilters.to);

        fetch(API_URL + 'certificates.php?' + params.toString(), { cache: 'no-store' })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-danger">' + (data.message || 'Error') + '</td></tr>';
                    return;
                }
                const apps = data.data.certificates || data.data || [];
                if (apps.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No applications found.</td></tr>';
                } else {
                      tbody.innerHTML = apps.map(a => `
                          <tr>
                              <td class="text-center"><span class="apps-code-badge">${esc(a.application_ref || 'APP-'+a.id)}</span></td>
                              <td class="text-center">${a.control_number ? `<span class="apps-code-badge">${esc(a.control_number)}</span>` : '<span class="apps-secondary">-</span>'}</td>
                              <td class="text-center fw-semibold">${esc(toTitleCase(a.resident_name || '-'))}</td>
                              <td class="text-center">${esc(toTitleCase((a.certificate_type || '').replace(/_/g, ' ')))}</td>
                              <td class="text-center"><span class="apps-secondary">${esc(toTitleCase((a.purpose || '').substring(0,20)))}${(a.purpose||'').length>20?'...':''}</span></td>
                              <td class="text-center"><span class="apps-secondary">${formatDate(a.created_at)}</span></td>
                              <td class="text-center"><span class="apps-pill ${getStatusColor(a.status)}">${getStatusLabel(a.status)}</span></td>
                              <td class="text-center">
                                <div class="apps-actions">
                                ${['ready_for_pickup', 'released'].includes(a.status) ? `<a href="<?php echo BASE_URL; ?>certificate-print.php?id=${a.id}" target="_blank" class="action-icon-btn" title="Print / PDF" aria-label="Print / PDF"><i class="bi bi-printer"></i></a>` : ''}
                                <button class="action-icon-btn" title="View" aria-label="View" onclick="viewApp(${a.id})"><i class="bi bi-eye"></i></button>
                                  ${APP_PERMS.canEdit && a.status === 'pending' ? `
                                      <button class="action-icon-btn" title="Approve" aria-label="Approve" onclick="updateStatus(${a.id}, 'approved')"><i class="bi bi-check-lg"></i></button>
                                      <button class="action-icon-btn action-reject" title="Reject" aria-label="Reject" onclick="rejectApp(${a.id})"><i class="bi bi-x-lg"></i></button>
                                  ` : ''}
                                  ${APP_PERMS.canEdit && a.status === 'approved' ? `
                                          <button class="action-icon-btn" title="Prepare for Pickup" aria-label="Prepare for Pickup" onclick="updateStatus(${a.id}, 'ready_for_pickup')"><i class="bi bi-bag-check"></i></button>
                                  ` : ''}
                                  ${APP_PERMS.canEdit && a.status === 'ready_for_pickup' ? `
                                      <button class="action-icon-btn" title="Mark Released" aria-label="Mark Released" onclick="updateStatus(${a.id}, 'released')"><i class="bi bi-box-arrow-up-right"></i></button>
                                  ` : ''}
                                  </div>
                              </td>
                          </tr>
                      `).join('');
                }
                const totalPages = data.data.total_pages || 1;
                renderPagination(totalPages, data.data.page || 1);
            })
                .catch(() => { tbody.innerHTML = '<tr><td colspan="8" class="text-danger">Failed to load.</td></tr>'; });
    }

    function refreshApplicationsPage() {
        const activeViewModal = document.getElementById('viewModal');
        const activeReleaseModal = document.getElementById('releaseModal');
        if (activeViewModal) {
            bootstrap.Modal.getInstance(activeViewModal)?.hide();
        }
        if (activeReleaseModal) {
            bootstrap.Modal.getInstance(activeReleaseModal)?.hide();
        }

        const nextUrl = new URL(window.location.href);
        nextUrl.searchParams.set('_refresh', Date.now().toString());
        window.location.replace(nextUrl.toString());
    }

    function renderPagination(totalPages, page) {
        const ul = document.getElementById('pagination');
        if (totalPages <= 1) { ul.innerHTML = ''; return; }
        let html = '';
        if (page > 1) html += '<li class="page-item"><a class="page-link" href="#" data-p="' + (page - 1) + '">Prev</a></li>';
        for (let i = 1; i <= Math.min(totalPages, 10); i++) {
            html += '<li class="page-item' + (i === page ? ' active' : '') + '"><a class="page-link" href="#" data-p="' + i + '">' + i + '</a></li>';
        }
        if (page < totalPages) html += '<li class="page-item"><a class="page-link" href="#" data-p="' + (page + 1) + '">Next</a></li>';
        ul.innerHTML = html;
        ul.querySelectorAll('a').forEach(a => {
            a.addEventListener('click', e => { e.preventDefault(); currentPage = parseInt(a.dataset.p); loadApplications(); });
        });
    }

    window.searchApplications = function() {
        const query = document.getElementById('searchInput')?.value.trim() || '';
        applicationFilters.q = query;
        currentPage = 1;
        loadApplications();
    };

    window.applyApplicationFilters = function() {
        applicationFilters.type = document.getElementById('filterType')?.value || '';
        applicationFilters.from = document.getElementById('filterFrom')?.value || '';
        applicationFilters.to = document.getElementById('filterTo')?.value || '';
        currentPage = 1;
        loadApplications();
        const modal = bootstrap.Modal.getInstance(document.getElementById('filterModal'));
        if (modal) modal.hide();
    };

    window.resetApplications = function() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) searchInput.value = '';
        applicationFilters = { q: '', type: '', from: '', to: '' };
        const typeSel = document.getElementById('filterType');
        const fromInput = document.getElementById('filterFrom');
        const toInput = document.getElementById('filterTo');
        if (typeSel) typeSel.value = '';
        if (fromInput) fromInput.value = '';
        if (toInput) toInput.value = '';
        currentStatus = '';
        document.querySelectorAll('#statusTabs .nav-link').forEach(l => l.classList.remove('active'));
        const firstTab = document.querySelector('#statusTabs .nav-link');
        if (firstTab) firstTab.classList.add('active');
        currentPage = 1;
        loadApplications();
    };

      function toTitleCase(text) {
          if (!text) return '';
          return String(text)
              .trim()
              .split(/\s+/)
              .map(word => {
                  if (!word) return '';
                  const clean = word.replace(/[^a-zA-Z]/g, '');
                  if (clean.length > 0 && clean === clean.toUpperCase() && clean.length <= 3) {
                      return word;
                  }
                  const first = word.charAt(0).toUpperCase();
                  const rest = word.slice(1).toLowerCase();
                  return first + rest;
              })
              .join(' ');
      }
      function esc(s) { return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }
    function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }
    function formatDateTime(d) {
        if (!d) return '-';
        const dt = new Date(d);
        if (Number.isNaN(dt.getTime())) return String(d);
        return dt.toLocaleString();
    }

    function getStatusColor(s) {
        const c = {
            'pending':'status-pending',
            'approved':'status-approved',
            'ready_for_pickup':'status-ready-for-pickup',
            'released':'status-released',
            'rejected':'status-rejected',
            'issued':'status-issued'
        };
        return c[s] || 'status-unknown';
    }

    function getStatusLabel(status) {
        const map = {
            pending: 'Pending',
            approved: 'Approved',
            ready_for_pickup: 'Ready for Pickup',
            released: 'Released',
            issued: 'Released',
            rejected: 'Rejected'
        };
        return map[status] || toTitleCase(String(status || 'Unknown').replace(/_/g, ' '));
    }

    function getStatusChip(status) {
        return `<span class="status-chip ${esc(status)}">${esc(getStatusLabel(status))}</span>`;
    }

    function calculateAgeFromDateString(dateValue) {
        if (!dateValue) return '-';
        const birthDate = new Date(dateValue + 'T00:00:00');
        if (Number.isNaN(birthDate.getTime())) return '-';
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        const dayDiff = today.getDate() - birthDate.getDate();
        if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) age -= 1;
        return age > 0 ? String(age) : '-';
    }

    function parseAttachmentPaths(raw) {
        const text = String(raw || '').trim();
        if (!text) return [];
        return text
            .split(/\r?\n|,|\|/)
            .map(v => v.trim())
            .filter(Boolean);
    }

    function isImagePath(path) {
        return /\.(png|jpe?g|gif|webp)$/i.test(path || '');
    }

    function normalizeCertificateType(certificateType = '') {
        return String(certificateType || '').trim().toLowerCase().replace(/_/g, ' ');
    }

    function normalizePurposeText(text = '') {
        return String(text || '').trim().toLowerCase().replace(/\s+/g, ' ');
    }

    function isPurposeOthers(value = '', certificateType = '') {
        const normalizedSelected = normalizePurposeText(value);
        if (!normalizedSelected) return false;
        if (normalizeCertificateType(certificateType) !== 'barangay certificate') return false;

        const knownPurposes = [
            'Application for Employment',
            'School Admission/Requirement',
            'Hospital Purpose',
            'Processing of Calamity',
            'Medical Purpose',
            'For Livelihood Loan',
            'Bank Transaction',
            'Indigent Family',
            'Organized Vending Permit',
            'DSWD Requirement',
            'For Travel Abroad',
            'Transfer of Residence'
        ];

        const isKnownPurpose = knownPurposes
            .some(item => normalizePurposeText(item) === normalizedSelected);
        return !isKnownPurpose;
    }

    function formatPurposeDisplay(value = '', certificateType = '', purposeOption = '', purposeDetails = '') {
        const rawValue = String(value || '').trim();
        if (!rawValue) return '-';
        const rawOption = String(purposeOption || '').trim();
        const rawDetails = String(purposeDetails || '').trim();
        if (normalizeCertificateType(certificateType) === 'barangay certificate' && normalizePurposeText(rawOption) === 'others') {
            return rawDetails ? `Others: ${rawDetails}` : 'Others';
        }
        if (normalizePurposeText(rawValue) === 'others') return 'Others';
        if (isPurposeOthers(rawValue, certificateType)) return `Others: ${toTitleCase(rawValue)}`;
        return toTitleCase(rawValue);
    }

    function buildBarangayPurposeChecklist(selectedPurpose = '') {
        const normalizedSelected = normalizePurposeText(selectedPurpose);
        const leftRightPairs = [
            ['Application for Employment', 'School Admission/Requirement'],
            ['Hospital Purpose', 'Processing of Calamity'],
            ['Medical Purpose', 'For Livelihood Loan'],
            ['Bank Transaction', 'Indigent Family'],
            ['Organized Vending Permit', 'DSWD Requirement'],
            ['For Travel Abroad', 'Transfer of Residence']
        ];

        const allKnownPurposes = leftRightPairs.flat();
        const isKnownPurpose = allKnownPurposes.some(item => normalizePurposeText(item) === normalizedSelected);
        const isOthersSelected = normalizedSelected !== '' && !isKnownPurpose;

        const marked = (label) => {
            const selected = normalizePurposeText(label) === normalizedSelected;
            return `(${selected ? '✓' : ' '}) ${label}`;
        };

        const lines = leftRightPairs.map(([left, right]) => `${marked(left)}\t${marked(right)}`);
        const otherValue = isOthersSelected ? selectedPurpose : '[PURPOSE]';
        lines.push(`(${isOthersSelected ? '✓' : ' '}) Others: ${otherValue}`);
        return lines.join('\n');
    }

    function buildDefaultCertificateTemplate(certificateType = '') {
        const normalizedType = normalizeCertificateType(certificateType);
        const isBarangayCertificate = normalizedType === 'barangay certificate';
        const isBarangayClearance = (
            normalizedType === 'barangay clearance'
            || normalizedType === 'barangay_clearance'
        );
        const isIndigencyCertificate = (
            normalizedType === 'barangay indigency'
            ||
            normalizedType === 'certificate of indigency'
            || normalizedType === 'certificate indigency'
            || normalizedType === 'certificate_indigency'
            || normalizedType === 'barangay_indigency'
        );

        if (isBarangayCertificate) {
            return [
                'TO WHOM IT MAY CONCERN:',
                '',
                'This is to certify that [NAME], [AGE] years old, [CIVIL_STATUS], is a bonafide resident of this Barangay 219, Zone 20, District II, Tondo, Manila with his/her postal address at [ADDRESS].',
                '',
                'This certification was issued upon the request of the above mentioned name for whatever legal purpose that may serve him/her best.',
                '',
                'AS PER REQUIREMENT IN SUPPORTING HIS/HER DOCUMENT',
                '[PURPOSE_CHECKLIST]',
                '',
                'IN WITNESS WHEREOF, I have hereunto set my hand and affixed the official seal of this office. Done in the Barangay Hall, Barangay 219, Zone 20, District II, City of Manila, this [DATE_ISSUED].'
            ].join('\n');
        }

        if (isBarangayClearance) {
            return [
                'THIS IS TO CERTIFY that <strong>[NAME_UPPER]</strong>, [AGE] years old, [CIVIL_STATUS], and a <span class="resident-field">[NATIONALITY_UPPER]</span>, is a bonafide resident of Barangay 219, Zone 20, District II, Tondo, Manila, with postal address at [ADDRESS].',
                '',
                'FURTHER TO THIS, the above-mentioned person is known to be of good moral character and has no derogatory record on file in this office as of this date of issuance.',
                '',
                'This clearance is being issued upon the request of the interested party for the purpose of <strong>[PURPOSE]</strong> and for whatever legal purpose it may serve.'
            ].join('\n');
        }

        if (isIndigencyCertificate) {
            return [
                'TO WHOM IT MAY CONCERN:',
                '',
                'This is to certify that [NAME], [AGE] years of age, [CIVIL_STATUS], is a bonafide resident of BARANGAY 219 Zone 20 with postal address at [ADDRESS].',
                '',
                'This is to further certify that the above mentioned name belongs to an indigent family of this barangay.',
                '',
                'Issued this [DATE_ISSUED] at Barangay 219 Zone 20 Manila.'
            ].join('\n');
        }

        return [
            'TO WHOM IT MAY CONCERN:',
            '',
            'This is to certify that [NAME], residing at [ADDRESS], is a bona fide resident of Barangay 219, Tondo, Manila.',
            '',
            'This certification is issued upon request for [PURPOSE].',
            '',
            'Issued this [DATE_ISSUED] at Barangay 219, Tondo, Manila.'
        ].join('\n');
    }

    function formatIssueDateForBody(dateValue) {
        if (!dateValue) return '';
        const parsed = new Date(dateValue + 'T00:00:00');
        if (Number.isNaN(parsed.getTime())) return dateValue;
        const day = parsed.getDate();
        const mod100 = day % 100;
        let suffix = 'th';
        if (mod100 < 11 || mod100 > 13) {
            const mod10 = day % 10;
            if (mod10 === 1) suffix = 'st';
            else if (mod10 === 2) suffix = 'nd';
            else if (mod10 === 3) suffix = 'rd';
        }
        const monthYear = parsed.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long'
        });
        return `${day}${suffix} day of ${monthYear}`;
    }

    function calculateAgeFromBirthDate(birthDateValue) {
        if (!birthDateValue) return '';
        const birthDate = new Date(birthDateValue + 'T00:00:00');
        if (Number.isNaN(birthDate.getTime())) return '';
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        const dayDiff = today.getDate() - birthDate.getDate();
        if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
            age -= 1;
        }
        return age > 0 ? String(age) : '';
    }

    function replaceBodyPlaceholders(template, values) {
        return String(template || '')
            .replace(/\[NAME\]/g, values.name)
            .replace(/\[NAME_UPPER\]/g, values.nameUpper || values.name)
            .replace(/\[NATIONALITY_UPPER\]/g, values.nationalityUpper || values.nationality || 'FILIPINO')
            .replace(/\[AGE\]/g, values.age)
            .replace(/\[CIVIL_STATUS\]/g, values.civilStatus)
            .replace(/\[ADDRESS\]/g, values.address)
            .replace(/\[PURPOSE\]/g, values.purpose)
                .replace(/\[PURPOSE_CHECKLIST\]/g, values.purposeChecklist)
            .replace(/\[DATE_ISSUED\]/g, values.dateIssued)
            .replace(/\[CONTROL_NUMBER\]/g, values.controlNumber);
    }

    function updateReleaseBodyAuto(force = false) {
        if (!force && !releaseAutoBodyEnabled) return;

        const name = (document.getElementById('releaseCertName')?.value || '').trim();
        const address = (document.getElementById('releaseCertAddress')?.value || '').trim();
        const purpose = (document.getElementById('releaseCertPurpose')?.value || '').trim();
        const dateIssuedRaw = document.getElementById('releaseDateIssued')?.value || '';
        const controlRaw = (document.getElementById('releaseControlNumber')?.value || '').trim();
        const age = calculateAgeFromBirthDate(currentReleaseBirthDate) || '[AGE]';
        const civilStatus = toTitleCase((currentReleaseCivilStatus || '').trim()) || '[CIVIL_STATUS]';
        const nationality = (currentReleaseNationality || '').trim() || 'Filipino';

        const values = {
            name: name || '[NAME]',
            nameUpper: (name || '[NAME]').toUpperCase(),
            nationality,
            nationalityUpper: nationality,
            age,
            civilStatus,
            address: address || '[ADDRESS]',
            purpose: purpose || '[PURPOSE]',
            purposeChecklist: buildBarangayPurposeChecklist(purpose),
            dateIssued: formatIssueDateForBody(dateIssuedRaw) || '[DATE_ISSUED]',
            controlNumber: (controlRaw && !/^Auto-generated/i.test(controlRaw)) ? controlRaw : '[CONTROL_NUMBER]'
        };

        const body = replaceBodyPlaceholders(buildDefaultCertificateTemplate(currentReleaseCertificateType), values);
        const bodyInput = document.getElementById('releaseCertBody');
        if (bodyInput) {
            bodyInput.value = body;
            releaseLastAutoBody = body;
            releaseAutoBodyEnabled = true;
        }
    }

    function initApplicationStatFilters() {
        const container = document.querySelector('.module-stats[data-module="applications"]');
        if (!container) return;
        container.querySelectorAll('[data-status]').forEach(card => {
            const handleClick = () => {
                const status = card.getAttribute('data-status') || '';
                currentStatus = status;
                currentPage = 1;
                document.querySelectorAll('#statusTabs .nav-link').forEach(l => l.classList.remove('active'));
                const activeTab = Array.from(document.querySelectorAll('#statusTabs .nav-link'))
                    .find(l => (l.getAttribute('data-status') || '') === status);
                if (activeTab) activeTab.classList.add('active');
                loadApplications();
            };
            card.addEventListener('click', handleClick);
            card.addEventListener('keypress', e => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    handleClick();
                }
            });
        });
    }

    function buildAttachmentSection(a) {
        const paths = parseAttachmentPaths(a.attachment);
        if (!paths.length) {
            return '<p class="mb-0 text-muted">No uploaded attachments.</p>';
        }
        return `<div class="attachment-grid">${paths.map((path, idx) => {
            const safePath = esc(path);
            const fileUrl = `<?php echo BASE_URL; ?>${safePath}`;
            const thumb = isImagePath(path)
                ? `<img class="attachment-thumb" src="${fileUrl}" alt="Attachment ${idx + 1}">`
                : `<div class="attachment-thumb d-flex align-items-center justify-content-center bg-light"><i class="bi bi-file-earmark-text fs-4"></i></div>`;
            return `<div class="attachment-item">${thumb}<a class="btn btn-sm btn-outline-primary w-100" href="${fileUrl}" target="_blank" download>Download</a></div>`;
        }).join('')}</div>`;
    }

    function getEditableStatus(status) {
        return status === 'approved';
    }

    function canEditViewNotes(status) {
        return APP_PERMS.canEdit && status !== 'pending';
    }

    function buildActionFooter(a) {
        let footer = '';
        const closeAndRefresh = "bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide();";

        if (!APP_PERMS.canEdit) {
            return '<button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';
        }

        if (a.status === 'pending') {
            footer += `<button class="btn btn-success" onclick="updateStatus(${a.id}, 'approved'); ${closeAndRefresh}">Approve</button>`;
            footer += `<button class="btn btn-outline-danger" onclick="rejectApp(${a.id}); ${closeAndRefresh}">Reject</button>`;
        } else if (a.status === 'approved') {
            footer += `<button class="btn btn-info" onclick="updateStatus(${a.id}, 'ready_for_pickup'); ${closeAndRefresh}">Prepare for Pickup</button>`;
        } else if (a.status === 'ready_for_pickup') {
            footer += `<a href="<?php echo BASE_URL; ?>certificate-print.php?id=${a.id}" target="_blank" class="btn btn-outline-primary">Print / PDF</a>`;
            footer += `<button class="btn btn-success" onclick="updateStatus(${a.id}, 'released'); ${closeAndRefresh}">Mark as Released</button>`;
        } else if (a.status === 'released') {
            footer += `<a href="<?php echo BASE_URL; ?>certificate-print.php?id=${a.id}" target="_blank" class="btn btn-primary">View Certificate Copy</a>`;
        }

        footer += '<button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';
        return footer;
    }

    window.viewApp = function(id) {
        fetch(API_URL + 'certificates.php?action=get&id=' + id + '&_ts=' + Date.now(), { cache: 'no-store' })
            .then(r => r.json())
            .then(data => {
                if (!data.success) return alert(data.message);
                const a = data.data;
                document.getElementById('viewAppRef').textContent = a.application_ref || 'APP-' + a.id;

                const age = calculateAgeFromDateString(a.birth_date);
                const relationshipStatus = toTitleCase(a.civil_status || '-');
                const residentAddress = a.address || a.resident_address || a.cert_address || '-';
                const certName = a.cert_name || a.resident_name || '';
                const certAddress = a.cert_address || residentAddress || '';
                const certPurpose = a.cert_purpose || a.purpose || '';
                const submittedPurposeRaw = a.purpose_option || a.purpose || '';
                const submittedPurpose = normalizePurposeText(submittedPurposeRaw) === 'others'
                    ? (a.purpose_details || a.purpose_other || a.purpose || 'Others')
                    : submittedPurposeRaw;
                const rejectionReason = (a.rejection_reason || '').trim();
                currentViewStatus = a.status || '';

                const html = `
                    <div class="app-detail-grid">
                        <section class="app-detail-card">
                            <h6><i class="bi bi-person-vcard me-1"></i> Resident Info</h6>
                            <div class="detail-row"><span>Name</span><strong>${esc(toTitleCase(a.resident_name || '-'))}</strong></div>
                            <div class="detail-row"><span>Age / Year</span><strong>${esc(age)}</strong></div>
                            <div class="detail-row"><span>Relationship Status</span><strong>${esc(relationshipStatus)}</strong></div>
                            <div class="detail-row"><span>Address</span><strong>${esc(residentAddress)}</strong></div>
                        </section>

                        <section class="app-detail-card">
                            <h6><i class="bi bi-file-earmark-text me-1"></i> Certificate Info</h6>
                            <div class="detail-row"><span>Type</span><strong>${esc(toTitleCase((a.certificate_type || '').replace(/_/g, ' ')))}</strong></div>
                            <div class="detail-row"><span>Submitted Purpose</span><strong>${esc(formatPurposeDisplay(submittedPurpose, a.certificate_type || '', a.purpose_option || '', a.purpose_details || a.purpose_other || ''))}</strong></div>
                            <div class="detail-row"><span>Certificate Name</span><strong>${esc(certName || '-')}</strong></div>
                            <div class="detail-row"><span>Certificate Address</span><strong>${esc(certAddress || '-')}</strong></div>
                            <div class="detail-row"><span>Certificate Purpose</span><strong>${esc(formatPurposeDisplay(certPurpose, a.certificate_type || '', a.purpose_option || '', a.purpose_details || a.purpose_other || ''))}</strong></div>
                        </section>

                        <section class="app-detail-card">
                            <h6><i class="bi bi-paperclip me-1"></i> Uploaded Files</h6>
                            ${buildAttachmentSection(a)}
                        </section>

                        <section class="app-detail-card">
                            <h6><i class="bi bi-clock-history me-1"></i> Status & Timestamps</h6>
                            <div class="detail-row"><span>Current Status</span><strong>${getStatusChip(a.status)}</strong></div>
                            <div class="detail-row"><span>Created</span><strong>${formatDateTime(a.created_at)}</strong></div>
                            <div class="detail-row"><span>Approved</span><strong>${formatDateTime(a.approved_at)}</strong></div>
                            <div class="detail-row"><span>Ready for Pickup</span><strong>${formatDateTime(a.ready_for_pickup_at)}</strong></div>
                            <div class="detail-row"><span>Released</span><strong>${formatDateTime(a.released_at || a.issued_date || a.date_issued)}</strong></div>
                            <div class="detail-row"><span>Rejected</span><strong>${formatDateTime(a.rejected_at)}</strong></div>
                            <div class="detail-row"><span>Rejection Reason</span><strong>${esc(rejectionReason || '-')}</strong></div>
                            <div class="detail-row"><span>Control Number</span><strong>${esc(a.control_number || '-')}</strong></div>
                        </section>
                    </div>

                `;

                document.getElementById('viewModalBody').innerHTML = html;
                document.getElementById('viewModalFooter').innerHTML = buildActionFooter(a);
                new bootstrap.Modal(document.getElementById('viewModal')).show();
            });
    };

    window.saveApplicationDraft = function(id) {
        alert('Manual certificate editing is disabled. Use Approve, Prepare for Pickup, Reject, and Mark as Released actions.');
    };

    window.updateStatus = function(id, status) {
        if (!APP_PERMS.canEdit) { alert('Access denied'); return; }

        let promptMessage = '';
        if (status === 'approved') {
            promptMessage = 'Approve this request?';
        } else if (status === 'ready_for_pickup') {
            promptMessage = 'Prepare this certificate for pickup now? Control number and issued date will be generated automatically.';
        } else if (status === 'released') {
            promptMessage = 'Mark this certificate as released? Make sure resident ID has been verified.';
        }

        if (promptMessage && !confirm(promptMessage)) {
            return;
        }

        const fd = new FormData();
        fd.append('action', 'update');
        fd.append('id', id);
        fd.append('status', status);
        fetch(API_URL + 'certificates.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    refreshApplicationsPage();
                } else {
                    alert(d.message || 'Error');
                }
            });
    };

    window.rejectApp = function(id) {
        if (!APP_PERMS.canEdit) { alert('Access denied'); return; }
        const reasonInput = prompt('Rejection reason (required):');
        if (reasonInput === null) return;
        const reason = (reasonInput || '').trim();
        if (!reason) {
            alert('Rejection reason is required.');
            return;
        }
        const fd = new FormData();
        fd.append('action', 'reject');
        fd.append('id', id);
        fd.append('reason', reason);
        fetch(API_URL + 'certificates.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    refreshApplicationsPage();
                } else {
                    alert(d.message || 'Error');
                }
            });
    };

    window.openRelease = function(id) {
        if (!APP_PERMS.canEdit) { alert('Access denied'); return; }
        fetch(API_URL + 'certificates.php?action=get&id=' + id + '&_ts=' + Date.now(), { cache: 'no-store' })
            .then(r => r.json())
            .then(async d => {
                if (!d.success || !d.data) { alert(d.message || 'Unable to load application'); return; }
                const a = d.data;
                currentReleaseCertificateType = (a.certificate_type || '').trim();
                currentReleaseBirthDate = (a.birth_date || '').trim();
                currentReleaseCivilStatus = (a.civil_status || '').trim();
                currentReleaseNationality = (a.nationality || a.citizenship || '').trim();
                document.getElementById('releaseId').value = id;
                document.getElementById('releaseCertName').value = a.cert_name || toTitleCase(a.resident_name || '');
                document.getElementById('releaseCertAddress').value = a.cert_address || a.address || '';
                const releasePurposeValue = a.cert_purpose || a.purpose || '';
                const releasePurposeInput = document.getElementById('releaseCertPurpose');
                releasePurposeInput.value = releasePurposeValue;
                releasePurposeInput.readOnly = !isPurposeOthers(releasePurposeValue, currentReleaseCertificateType);
                document.getElementById('releaseDateIssued').value = (a.date_issued || new Date().toISOString().slice(0,10));

                if (a.control_number) {
                    document.getElementById('releaseControlNumber').value = a.control_number;
                } else {
                    try {
                        const ctrlRes = await fetch(API_URL + 'certificates.php?action=generate_control');
                        const ctrl = await ctrlRes.json();
                        document.getElementById('releaseControlNumber').value = ctrl?.data?.control_number || 'Auto-generated on issue';
                    } catch (e) {
                        document.getElementById('releaseControlNumber').value = 'Auto-generated on issue';
                    }
                }

                const existingBody = (a.cert_body || '').trim();
                if (existingBody !== '') {
                    const dateIssuedPretty = formatIssueDateForBody(document.getElementById('releaseDateIssued').value || '');
                    const resolvedExisting = replaceBodyPlaceholders(existingBody, {
                        name: (document.getElementById('releaseCertName').value || '').trim(),
                        nameUpper: ((document.getElementById('releaseCertName').value || '').trim() || '[NAME]').toUpperCase(),
                        nationality: currentReleaseNationality || 'Filipino',
                        nationalityUpper: (currentReleaseNationality || 'Filipino').toUpperCase(),
                        age: calculateAgeFromBirthDate(currentReleaseBirthDate) || '[AGE]',
                        civilStatus: toTitleCase((currentReleaseCivilStatus || '').trim()) || '[CIVIL_STATUS]',
                        address: (document.getElementById('releaseCertAddress').value || '').trim(),
                        purpose: (document.getElementById('releaseCertPurpose').value || '').trim(),
                        purposeChecklist: buildBarangayPurposeChecklist((document.getElementById('releaseCertPurpose').value || '').trim()),
                        dateIssued: dateIssuedPretty,
                        controlNumber: (document.getElementById('releaseControlNumber').value || '').trim()
                    });
                    const normalizedExisting = (resolvedExisting || '')
                        .replace(/(postal\s+address\s+at\s+[^.]*\bManila)\s*,\s*Manila(\.)/gi, '$1$2');
                    document.getElementById('releaseCertBody').value = normalizedExisting;
                    releaseAutoBodyEnabled = false;
                    releaseLastAutoBody = '';
                } else {
                    updateReleaseBodyAuto(true);
                }

                new bootstrap.Modal(document.getElementById('releaseModal')).show();
            });
    };

    ['releaseCertName', 'releaseCertAddress', 'releaseCertPurpose'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', () => updateReleaseBodyAuto());
        }
    });

    const releaseDateInput = document.getElementById('releaseDateIssued');
    if (releaseDateInput) {
        releaseDateInput.addEventListener('change', () => updateReleaseBodyAuto());
    }

    const releaseBodyInput = document.getElementById('releaseCertBody');
    if (releaseBodyInput) {
        releaseBodyInput.addEventListener('input', () => {
            if (!releaseAutoBodyEnabled) return;
            if (releaseBodyInput.value !== releaseLastAutoBody) {
                releaseAutoBodyEnabled = false;
            }
        });
    }

    const previewReleaseBtn = document.getElementById('btnPreviewRelease');
    if (previewReleaseBtn) {
        previewReleaseBtn.addEventListener('click', function() {
            if (!APP_PERMS.canEdit) { alert('Access denied'); return; }

            const id = document.getElementById('releaseId').value;
            const certName = (document.getElementById('releaseCertName').value || '').trim();
            const certAddress = (document.getElementById('releaseCertAddress').value || '').trim();
            const certPurpose = (document.getElementById('releaseCertPurpose').value || '').trim();
            const certBody = (document.getElementById('releaseCertBody').value || '').trim();
            const dateIssued = (document.getElementById('releaseDateIssued').value || '').trim();
            const controlNumber = (document.getElementById('releaseControlNumber').value || '').trim();

            if (!id) {
                alert('Missing request ID for preview.');
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo BASE_URL; ?>certificate-print.php';
            form.target = '_blank';

            const fields = {
                preview: '1',
                id,
                cert_name: certName,
                cert_address: certAddress,
                cert_purpose: certPurpose,
                cert_body: certBody,
                date_issued: dateIssued,
                control_number: controlNumber
            };

            Object.entries(fields).forEach(([name, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
            form.remove();
        });
    }

    document.getElementById('btnRelease').addEventListener('click', function() {
        if (!APP_PERMS.canEdit) { alert('Access denied'); return; }
        const id = document.getElementById('releaseId').value;
        const certName = document.getElementById('releaseCertName').value.trim();
        const certAddress = document.getElementById('releaseCertAddress').value.trim();
        const certPurpose = document.getElementById('releaseCertPurpose').value.trim();
        const certBody = document.getElementById('releaseCertBody').value.trim();
        const dateIssued = document.getElementById('releaseDateIssued').value;

        const fd = new FormData();
        fd.append('action', 'update');
        fd.append('id', id);
        fd.append('status', 'ready_for_pickup');
        fd.append('cert_name', certName);
        fd.append('cert_address', certAddress);
        fd.append('cert_purpose', certPurpose);
        fd.append('cert_body', certBody);
        fd.append('date_issued', dateIssued);
        fetch(API_URL + 'certificates.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                bootstrap.Modal.getInstance(document.getElementById('releaseModal')).hide();
                if (d.success) {
                    alert('Marked ready for pickup. Control #: ' + (d.data?.control_number || ''));
                    refreshApplicationsPage();
                } else alert(d.message || 'Error');
            });
    });

    function buildResidentLabel(resident) {
        const lastName = String(resident.last_name || '').trim();
        const firstName = String(resident.first_name || '').trim();
        const middleName = String(resident.middle_name || '').trim();
        const full = `${lastName}, ${firstName}${middleName ? ' ' + middleName : ''}`.trim();
        return full.replace(/\s+/g, ' ');
    }

    function getWalkInSelectedResidentId() {
        return document.getElementById('walkinResidentId')?.value || '';
    }

    function resetWalkInResidentSelection() {
        const searchInput = document.getElementById('walkinResidentSearch');
        const hidden = document.getElementById('walkinResidentId');
        const results = document.getElementById('walkinResidentSearchResults');
        if (searchInput) searchInput.value = '';
        if (hidden) hidden.value = '';
        if (results) {
            results.innerHTML = '';
            results.style.display = 'none';
        }
    }

    function buildWalkInResidentMeta(resident) {
        const code = String(resident.resident_code || '').trim();
        const address = String(resident.address || resident.purok_sitio || '').trim();
        if (code && address) return `${code} • ${address}`;
        if (code) return code;
        if (address) return address;
        return 'No additional details';
    }

    function renderWalkInResidentResults(residents) {
        const results = document.getElementById('walkinResidentSearchResults');
        if (!results) return;

        if (!Array.isArray(residents) || residents.length === 0) {
            results.innerHTML = '<div class="list-group-item text-muted small">No matching residents</div>';
            results.style.display = 'block';
            return;
        }

        results.innerHTML = residents.map(r => {
            const fullName = buildResidentLabel(r);
            const meta = buildWalkInResidentMeta(r);
            return `<button type="button" class="list-group-item list-group-item-action" data-resident-id="${esc(r.id)}" data-resident-name="${esc(fullName)}">
                <div class="fw-semibold">${esc(fullName)}</div>
                <div class="small text-muted">${esc(meta)}</div>
            </button>`;
        }).join('');
        results.style.display = 'block';
    }

    function searchWalkInResidents(keyword) {
        const results = document.getElementById('walkinResidentSearchResults');
        if (!results) return;

        const q = String(keyword || '').trim();
        if (q.length < 3) {
            results.innerHTML = '';
            results.style.display = 'none';
            return;
        }

        fetch(API_URL + 'certificates.php?action=resident_options&limit=20&q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(d => {
                const residents = (d && d.success && d.data && Array.isArray(d.data.residents)) ? d.data.residents : [];
                renderWalkInResidentResults(residents);
            })
            .catch(() => {
                results.innerHTML = '<div class="list-group-item text-danger small">Unable to search residents</div>';
                results.style.display = 'block';
            });
    }

    function initWalkInResidentSearch() {
        const searchInput = document.getElementById('walkinResidentSearch');
        const hidden = document.getElementById('walkinResidentId');
        const clearBtn = document.getElementById('clearWalkinResidentBtn');
        const results = document.getElementById('walkinResidentSearchResults');
        if (!searchInput || !hidden || !results) return;

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                resetWalkInResidentSelection();
                searchInput.focus();
            });
        }

        searchInput.addEventListener('input', (e) => {
            hidden.value = '';
            if (walkInResidentSearchTimer) {
                clearTimeout(walkInResidentSearchTimer);
            }
            walkInResidentSearchTimer = setTimeout(() => {
                searchWalkInResidents(e.target.value || '');
            }, 250);
        });

        searchInput.addEventListener('focus', () => {
            if (searchInput.value.trim().length >= 3) {
                searchWalkInResidents(searchInput.value);
            }
        });

        searchInput.addEventListener('blur', () => {
            setTimeout(() => {
                if (!results.contains(document.activeElement)) {
                    results.style.display = 'none';
                }
            }, 100);
        });

        results.addEventListener('mousedown', (e) => {
            const item = e.target.closest('button[data-resident-id]');
            if (!item) return;
            e.preventDefault();
            hidden.value = String(item.getAttribute('data-resident-id') || '');
            searchInput.value = String(item.getAttribute('data-resident-name') || '');
            results.style.display = 'none';
        });

        results.addEventListener('click', (e) => {
            if (e.target.closest('button[data-resident-id]')) {
                e.preventDefault();
            }
        });
    }

    function updateWalkInPurposeVisibility() {
        const certType = document.getElementById('walkinCertType')?.value || '';
        const wrap = document.getElementById('walkinPurposeWrap');
        const purposeSelect = document.getElementById('walkinPurposeSelect');
        const purposeOther = document.getElementById('walkinPurposeOther');
        const purposeOptionsByType = {
            barangay_certificate: [
                'Application for Employment',
                'School Admission/Requirement',
                'Hospital Purpose',
                'Processing of Calamity',
                'Medical Purpose',
                'For Livelihood Loan',
                'Bank Transaction',
                'Indigent Family',
                'Organized Vending Permit',
                'DSWD Requirement',
                'For Travel Abroad',
                'Transfer of Residence',
                'Others'
            ],
            barangay_clearance: [
                'Job Application',
                'National ID Application',
                'Police Clearance Requirement',
                'Bank Account Opening',
                'School Enrollment',
                'Scholarship Application',
                'Business Permit Application',
                'Passport Application',
                'Utility Connection',
                'First Time Jobseeker (RA 11261)'
            ]
        };
        const options = purposeOptionsByType[certType] || [];
        const requiresPurpose = options.length > 0;

        if (wrap) {
            wrap.classList.toggle('d-none', !requiresPurpose);
        }

        if (!requiresPurpose) {
            if (purposeSelect) purposeSelect.value = '';
            if (purposeOther) {
                purposeOther.value = '';
                purposeOther.classList.add('d-none');
            }
            return;
        }

        if (purposeSelect) {
            const currentValue = purposeSelect.value || '';
            purposeSelect.innerHTML = '<option value="">-- Select Purpose --</option>'
                + options.map(option => `<option value="${option}">${option}</option>`).join('');
            if (options.includes(currentValue)) {
                purposeSelect.value = currentValue;
            }
        }

        if (purposeOther) {
            const isOthers = (purposeSelect?.value || '') === 'Others';
            purposeOther.classList.toggle('d-none', !isOthers);
            if (!isOthers) purposeOther.value = '';
        }
    }

    // Load residents for walk-in searchable field
    function loadResidents() {
        resetWalkInResidentSelection();
    }

    const btnDirectIssue = document.getElementById('btnDirectIssue');
    if (btnDirectIssue) {
        btnDirectIssue.addEventListener('click', function() {
            if (!APP_PERMS.canEdit) { alert('Access denied'); return; }

            const residentId = getWalkInSelectedResidentId();
            const certType = document.getElementById('walkinCertType')?.value || '';
            const purposeSelectVal = document.getElementById('walkinPurposeSelect')?.value || '';
            const purposeOtherVal = (document.getElementById('walkinPurposeOther')?.value || '').trim();
            const requiresPurpose = certType === 'barangay_certificate' || certType === 'barangay_clearance';

            if (!residentId || !certType) {
                alert('Resident and certificate type are required.');
                return;
            }

            if (requiresPurpose && !purposeSelectVal) {
                alert('Certificate purpose is required for this certificate type.');
                return;
            }

            if (requiresPurpose && purposeSelectVal === 'Others' && !purposeOtherVal) {
                alert('Please specify purpose for Others.');
                return;
            }

            const fd = new FormData();
            fd.append('action', 'direct_issue');
            fd.append('resident_id', residentId);
            fd.append('certificate_type', certType);
            if (requiresPurpose) {
                fd.append('purpose', purposeSelectVal);
                if (purposeSelectVal === 'Others') {
                    fd.append('purpose_other', purposeOtherVal);
                }
            }

            btnDirectIssue.disabled = true;
            fetch(API_URL + 'certificates.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    btnDirectIssue.disabled = false;
                    if (!d.success) {
                        alert(d.message || 'Failed to issue walk-in request.');
                        return;
                    }

                    const modal = bootstrap.Modal.getInstance(document.getElementById('walkinModal'));
                    if (modal) modal.hide();

                    const walkInType = document.getElementById('walkinCertType');
                    const walkInPurpose = document.getElementById('walkinPurposeSelect');
                    const walkInPurposeOther = document.getElementById('walkinPurposeOther');
                    resetWalkInResidentSelection();
                    if (walkInType) walkInType.value = '';
                    if (walkInPurpose) walkInPurpose.value = '';
                    if (walkInPurposeOther) {
                        walkInPurposeOther.value = '';
                        walkInPurposeOther.classList.add('d-none');
                    }
                    updateWalkInPurposeVisibility();

                    const issuedId = d?.data?.id;
                    if (issuedId) {
                        const printUrl = d?.data?.print_url || ('<?php echo BASE_URL; ?>certificate-print.php?id=' + encodeURIComponent(String(issuedId)));
                        window.open(printUrl, '_blank', 'noopener');
                    }

                    refreshApplicationsPage();
                })
                .catch(() => {
                    btnDirectIssue.disabled = false;
                    alert('Error issuing walk-in request.');
                });
        });
    }

    const walkInCertType = document.getElementById('walkinCertType');
    if (walkInCertType) {
        walkInCertType.addEventListener('change', updateWalkInPurposeVisibility);
    }

    const walkInPurposeSelect = document.getElementById('walkinPurposeSelect');
    if (walkInPurposeSelect) {
        walkInPurposeSelect.addEventListener('change', updateWalkInPurposeVisibility);
    }

    initWalkInResidentSearch();

    document.querySelectorAll('#statusTabs .nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('#statusTabs .nav-link').forEach(l => l.classList.remove('active'));
            this.classList.add('active');
            currentStatus = this.dataset.status || '';
            currentPage = 1;
            loadApplications();
        });
    });

    applyApplicationPermissions();
    initApplicationStatFilters();
    updateWalkInPurposeVisibility();
    loadResidents();
    loadApplications();
})();
</script>
