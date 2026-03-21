<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();

if (normalizeRole(getCurrentUserRole()) === normalizeRole(ROLE_RESIDENT)) {
    header('Location: ' . BASE_URL . 'complaints/my_complaints.php');
    exit();
}

requireModuleAccess('complaints');

$page_title = 'Complaints';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content module-page complaints-page">
    <div class="container-fluid">
        <div class="module-hero card border-0 shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <p class="module-kicker text-uppercase small mb-1">Cases Module</p>
                    <h2 class="mb-1"><i class="bi bi-exclamation-triangle me-2"></i>Complaints Management</h2>
                    <p class="module-subtitle mb-0">Handle complaint intake, review progress, and resolution outcomes.</p>
                </div>
                <button class="btn btn-primary" id="btnOpenCreate" data-bs-toggle="modal" data-bs-target="#createModal">
                    <i class="bi bi-plus-lg"></i> New Complaint
                </button>
            </div>
        </div>

        <div class="search-bar mb-3">
            <div class="row">
                <div class="col-md-6">
                    <input type="text" class="form-control" id="searchInput" placeholder="Search by title, complainant, or respondent...">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" onclick="searchComplaints()">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100" onclick="resetComplaints()">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs app-tabs mb-3" id="statusTabs">
            <li class="nav-item"><a class="nav-link active" href="#" data-status="">All</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="pending">Pending</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="under_review">Under Review</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="resolved">Resolved</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="dismissed">Dismissed</a></li>
        </ul>

        <div class="data-table complaints-table-wrap">
            <div class="table-responsive complaints-table-scroll">
                <table class="table table-hover complaints-table align-middle">
                    <thead>
                        <tr>
                            <th class="text-center">ID</th>
                            <th class="text-center">Title</th>
                            <th class="text-center">Complainant</th>
                            <th class="text-center">Resident</th>
                            <th class="text-center">Date</th>
                            <th class="text-center">Status</th>
                            <th class="text-center complaints-actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="complaintsTableBody"><tr><td colspan="7" class="text-center">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.complaints-page .app-tabs {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0.45rem;
    border-bottom: 0;
}

.complaints-page .app-tabs .nav-item {
    margin: 0;
}

.complaints-page .app-tabs .nav-link {
    width: 100%;
    text-align: center;
    border: 1px solid #dbe3ee;
    border-radius: 999px;
    color: #475569;
    font-weight: 600;
    padding: 0.5rem 0.8rem;
    background: #ffffff;
}

.complaints-page .app-tabs .nav-link.active {
    color: #1d4ed8;
    background: #e8f0ff;
    border-color: #bfdbfe;
}

.complaints-page .complaints-table-wrap {
    border: 1px solid #e7ecf3;
    border-radius: 14px;
    background: #fff;
    padding: 0.35rem;
}

.complaints-page .complaints-table {
    margin-bottom: 0;
}

.complaints-page .complaints-table-scroll {
    max-height: min(62vh, 640px);
    overflow-y: auto;
    overflow-x: auto;
    scrollbar-width: thin;
    scrollbar-color: #94a3b8 #f1f5f9;
}

.complaints-page .complaints-table-scroll::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

.complaints-page .complaints-table-scroll::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 999px;
}

.complaints-page .complaints-table-scroll::-webkit-scrollbar-thumb {
    background: #94a3b8;
    border-radius: 999px;
    border: 2px solid #f1f5f9;
}

.complaints-page .complaints-table-scroll::-webkit-scrollbar-thumb:hover {
    background: #64748b;
}

.complaints-page .complaints-table-scroll thead th {
    position: sticky;
    top: 0;
    z-index: 2;
}

.complaints-page .complaints-table > :not(caption) > * > * {
    border-bottom: 1px solid #edf1f6;
    padding: 0.9rem 0.85rem;
    vertical-align: middle;
}

.complaints-page .complaints-table thead th {
    border-bottom: 1px solid #dfe6ef;
    color: #4b5563;
    font-size: 0.82rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    background: #f9fbfd;
}

.complaints-page .complaints-table tbody td {
    color: #1f2937;
    font-size: 0.94rem;
}

.complaints-page .complaints-table tbody tr:hover {
    background: #f8fbff;
}

.complaints-page .complaints-secondary {
    color: #6b7280;
    font-size: 0.86rem;
}

.complaints-page .complaints-code-badge {
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

.complaints-page .complaints-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 110px;
    padding: 0.35rem 0.65rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.01em;
}

.complaints-page .complaints-pill.status-pending {
    background: #fff4e8;
    color: #9a5b11;
}

.complaints-page .complaints-pill.status-under-review,
.complaints-page .complaints-pill.status-under-investigation {
    background: #eaf6ff;
    color: #1f5f8b;
}

.complaints-page .complaints-pill.status-scheduled-for-mediation {
    background: #edf2ff;
    color: #4c46b7;
}

.complaints-page .complaints-pill.status-referred,
.complaints-page .complaints-pill.status-referred-to-other-barangay {
    background: #eef2f7;
    color: #4b5563;
}

.complaints-page .complaints-pill.status-resolved {
    background: #e9f8ef;
    color: #1f7a3f;
}

.complaints-page .complaints-pill.status-dismissed {
    background: #ffecee;
    color: #a53a44;
}

.complaints-page .complaints-pill.status-unknown {
    background: #f1f3f6;
    color: #374151;
}

.complaints-page .complaints-actions {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
}

.complaints-page .action-icon-btn {
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

.complaints-page .action-icon-btn:hover,
.complaints-page .action-icon-btn:focus-visible {
    background: #f5f9ff;
    border-color: #d6e4ff;
    color: #2f4f95;
    transform: translateY(-1px);
}

.complaints-page .action-icon-btn.action-delete:hover,
.complaints-page .action-icon-btn.action-delete:focus-visible {
    background: #fff1f3;
    border-color: #f6ccd3;
    color: #9f2f3e;
}

.complaints-page .complaints-actions-col {
    min-width: 140px;
}

@media (max-width: 768px) {
    .complaints-page .app-tabs {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .complaints-page .complaints-table > :not(caption) > * > * {
        padding: 0.75rem 0.6rem;
    }
}
</style>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Filter Complaints</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="">All</option>
                        <option value="pending">Pending Review</option>
                        <option value="under_review">Under Investigation</option>
                        <option value="Scheduled for Mediation">Scheduled for Mediation</option>
                        <option value="referred">Referred to Other Barangay</option>
                        <option value="resolved">Resolved</option>
                        <option value="dismissed">Dismissed</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Filing From</label>
                    <input type="date" class="form-control" id="filterFrom">
                </div>
                <div class="mb-3">
                    <label class="form-label">Filing To</label>
                    <input type="date" class="form-control" id="filterTo">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="applyComplaintFilters()">Apply Filters</button>
            </div>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Complaint Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewModalBody"></div>
            <div class="modal-footer" id="viewModalFooter"></div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Complaint</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editId">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" id="editTitle" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Complainant</label>
                        <input type="text" class="form-control" id="editComplainant" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Respondent</label>
                        <input type="text" class="form-control" id="editRespondent">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Type</label>
                        <input type="text" class="form-control" id="editType" placeholder="e.g., Noise, Property">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Filing Date</label>
                        <input type="date" class="form-control" id="editFilingDate">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="editStatus">
                            <option value="Pending Review">Pending Review</option>
                            <option value="Under Investigation">Under Investigation</option>
                            <option value="Scheduled for Mediation">Scheduled for Mediation</option>
                            <option value="Referred to Other Barangay">Referred to Other Barangay</option>
                            <option value="Resolved">Resolved</option>
                            <option value="Dismissed">Dismissed</option>
                            <option value="pending">Pending</option>
                            <option value="under_review">Under Review</option>
                            <option value="resolved">Resolved</option>
                            <option value="dismissed">Dismissed</option>
                        </select>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Narrative</label>
                        <textarea class="form-control" id="editNarrative" rows="4" required></textarea>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea class="form-control" id="editRemarks" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSaveComplaint" onclick="saveComplaint()">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Complaint</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" class="form-control" name="complaint_title" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Complainant Name *</label>
                            <input type="text" class="form-control" name="complainant_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Respondent</label>
                            <input type="text" class="form-control" name="respondent_name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Type</label>
                            <input type="text" class="form-control" name="complaint_type">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Filing Date</label>
                            <input type="date" class="form-control" name="filing_date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Narrative *</label>
                            <textarea class="form-control" name="narrative" rows="4" required></textarea>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnCreate">Create</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
document.getElementById('btnCreate').addEventListener('click', function() {
    const f = document.getElementById('createForm');
    if (window.applyTitleCaseToCreateForm) {
        window.applyTitleCaseToCreateForm(f);
    }
    const fd = new FormData();
    fd.append('action', 'create');
    fd.append('complaint_title', f.complaint_title.value);
    fd.append('complainant_name', f.complainant_name.value);
    fd.append('respondent_name', f.respondent_name.value);
    fd.append('complaint_type', f.complaint_type.value);
    fd.append('narrative', f.narrative.value);
    fd.append('filing_date', f.filing_date.value);
    fd.append('remarks', f.remarks.value);
    fetch(window.API_URL + 'complaints.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                bootstrap.Modal.getInstance(document.getElementById('createModal')).hide();
                f.reset();
                f.filing_date.value = new Date().toISOString().slice(0, 10);
                loadComplaints();
            } else alert(d.message || 'Error');
        });
});
</script>
<script src="<?php echo ASSETS_URL; ?>css/js/complaints.js?v=<?php echo time(); ?>"></script>
