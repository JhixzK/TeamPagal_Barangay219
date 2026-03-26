<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/complaint-categories.php';
require_once __DIR__ . '/../includes/complaint-statuses.php';

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

        <div class="card border-0 shadow-sm mb-3 complaints-filter-card">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small text-muted mb-1">Search</label>
                        <input type="text" class="form-control" id="searchInput" placeholder="Title, complainant, reference…">
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <label class="form-label small text-muted mb-1">Status</label>
                        <select class="form-select" id="filterStatus">
                            <option value="">All</option>
                            <?php foreach (complaintStatusCodes() as $code): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars(complaintStatusLabel($code)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <label class="form-label small text-muted mb-1">Type</label>
                        <select class="form-select" id="filterCategory">
                            <option value="">All</option>
                            <?php foreach (complaintCategoriesList() as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <label class="form-label small text-muted mb-1">List</label>
                        <select class="form-select" id="filterListScope">
                            <option value="active">Active</option>
                            <option value="archive">Archive</option>
                            <option value="all">All</option>
                        </select>
                    </div>
                    <div class="col-lg-1 col-md-4 col-6">
                        <label class="form-label small text-muted mb-1">From</label>
                        <input type="date" class="form-control" id="filterFrom">
                    </div>
                    <div class="col-lg-1 col-md-4 col-6">
                        <label class="form-label small text-muted mb-1">To</label>
                        <input type="date" class="form-control" id="filterTo">
                    </div>
                    <div class="col-lg-1 col-md-6 d-flex gap-2 align-items-end">
                        <button type="button" class="btn btn-primary flex-grow-1" id="btnApplyComplaintFilters" title="Apply filters"><i class="bi bi-funnel"></i></button>
                    </div>
                </div>
                <div class="mt-2">
                    <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" id="btnResetComplaintFilters"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
                </div>
            </div>
        </div>

        <div class="data-table complaints-table-wrap">
            <div class="table-responsive complaints-table-scroll">
                <table class="table table-hover complaints-table align-middle">
                    <thead>
                        <tr>
                            <th class="text-center">ID</th>
                            <th class="text-center">Type</th>
                            <th class="text-center">Submitted By</th>
                            <th class="text-center">Contact</th>
                            <th class="text-center">Incident Date</th>
                            <th class="text-center">Location</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Officer</th>
                            <th class="text-center complaints-actions-col actions-col-compact">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="complaintsTableBody"><tr><td colspan="9" class="text-center">Loading...</td></tr></tbody>
                </table>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-2 py-2 border-top bg-white rounded-bottom" id="complaintsPagerWrap" style="display:none;">
                <span class="text-muted small" id="complaintsPagerInfo"></span>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" id="complaintsPrevPage">Prev</button>
                    <button type="button" class="btn btn-outline-secondary" id="complaintsNextPage">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.complaints-page .complaints-filter-card .form-label { font-weight: 600; color: #334155 !important; }
.complaints-page .complaints-table-wrap {
    border: 1px solid #e7ecf3;
    border-radius: 14px;
    background: #fff;
    padding: 0.35rem;
}
.complaints-page .complaints-table { margin-bottom: 0; }
.complaints-page .complaints-table-scroll {
    max-height: min(58vh, 600px);
    overflow-y: auto;
    overflow-x: auto;
    scrollbar-width: thin;
    scrollbar-color: #94a3b8 #f1f5f9;
}
.complaints-page .complaints-table-scroll thead th {
    position: sticky;
    top: 0;
    z-index: 2;
}
.complaints-page .complaints-table > :not(caption) > * > * {
    border-bottom: 1px solid #edf1f6;
    padding: 0.85rem 0.65rem;
    vertical-align: middle;
    font-size: 0.9rem;
}
.complaints-page .complaints-table thead th {
    border-bottom: 1px solid #dfe6ef;
    color: #4b5563;
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    background: #f9fbfd;
}
.complaints-page .complaints-secondary { color: #6b7280; font-size: 0.86rem; }
.complaints-page .complaints-code-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.25rem 0.5rem;
    border-radius: 999px;
    background: #f2f5fa;
    border: 1px solid #e2e8f0;
    color: #465468;
    font-size: 0.78rem;
    font-weight: 600;
}
.complaints-page .complaints-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 88px;
    padding: 0.35rem 0.65rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.01em;
}
.complaints-page .complaints-pill.status-pending { background: #fff4e8; color: #9a5b11; }
.complaints-page .complaints-pill.status-approved { background: #e8f0ff; color: #1d4ed8; }
.complaints-page .complaints-pill.status-assigned { background: #eaf6ff; color: #1f5f8b; }
.complaints-page .complaints-pill.status-in-progress { background: #edf2ff; color: #4338ca; }
.complaints-page .complaints-pill.status-resolved { background: #e9f8ef; color: #1f7a3f; }
.complaints-page .complaints-pill.status-rejected { background: #ffecee; color: #a53a44; }
.complaints-page .complaints-pill.status-unknown { background: #f1f3f6; color: #374151; }
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
    color: #2563eb;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
}
.complaints-page .action-icon-btn:hover { background: #eff6ff; border-color: #bfdbfe; transform: translateY(-1px); }
.complaints-page .action-icon-btn.action-delete:hover { background: #fff1f3; border-color: #f6ccd3; color: #9f2f3e; }
.complaint-review-modal .complaint-review-header {
    background: linear-gradient(135deg, #1e3a5f 0%, #2d4a7c 100%);
    border-radius: 0.5rem 0.5rem 0 0;
}
.complaint-review-modal .complaint-review-card {
    border: 1px solid #e8edf4;
    border-radius: 12px;
    background: #fff;
    height: 100%;
}
.complaint-review-modal .complaint-review-card .card-section-title {
    font-size: 0.7rem;
    letter-spacing: 0.08em;
    color: #64748b;
    font-weight: 700;
    text-transform: uppercase;
    margin-bottom: 1rem;
}
.complaint-review-modal .complaint-kv-label {
    font-size: 0.65rem;
    letter-spacing: 0.06em;
    color: #94a3b8;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 0.2rem;
}
.complaint-review-modal .complaint-kv-value { color: #1f2937; font-weight: 500; font-size: 0.95rem; }
.complaint-review-modal .complaint-desc-box {
    background: #f0f4f8;
    border-radius: 10px;
    padding: 0.85rem 1rem;
    color: #334155;
    font-size: 0.9rem;
    line-height: 1.5;
    white-space: pre-wrap;
    word-break: break-word;
}
.complaint-review-modal .action-status-box {
    background: #f0f4f8;
    border-radius: 10px;
    padding: 1rem;
}
@media (max-width: 768px) {
    .complaints-page .complaints-table > :not(caption) > * > * { padding: 0.65rem 0.45rem; font-size: 0.82rem; }
}
</style>

<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content complaint-review-modal border-0 shadow">
            <div class="modal-header complaint-review-header text-white py-3">
                <div>
                    <h5 class="modal-title fw-bold mb-1"><i class="bi bi-chat-square-text me-2"></i>Review Complaint</h5>
                    <p class="mb-0 small opacity-75">View details and take the next action.</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light" id="viewModalBody"></div>
            <div class="modal-footer bg-white border-0" id="viewModalFooter"></div>
        </div>
    </div>
</div>

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
                        <label class="form-label">Category</label>
                        <select class="form-select" id="editCategory">
                            <option value="">—</option>
                            <?php foreach (complaintCategoriesList() as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Complainant First Name *</label>
                        <input type="text" class="form-control" id="editComplainantFirst" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Complainant Last Name *</label>
                        <input type="text" class="form-control" id="editComplainantLast" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Respondent First Name</label>
                        <input type="text" class="form-control" id="editRespondentFirst">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Respondent Last Name</label>
                        <input type="text" class="form-control" id="editRespondentLast">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Assigned Officer</label>
                        <input type="text" class="form-control" id="editAssignedOfficer" placeholder="e.g. Barangay Captain">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Filing Date</label>
                        <input type="date" class="form-control" id="editFilingDate">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="editStatus">
                            <?php foreach (complaintStatusCodes() as $code): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars(complaintStatusLabel($code)); ?></option>
                            <?php endforeach; ?>
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
                        <div class="col-12 mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" class="form-control" name="complaint_title" required>
                        </div>
                        <div class="col-12 mb-1">
                            <span class="small text-uppercase text-muted fw-semibold">Complainant</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First name *</label>
                            <input type="text" class="form-control" name="complainant_first_name" required autocomplete="given-name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last name *</label>
                            <input type="text" class="form-control" name="complainant_last_name" required autocomplete="family-name">
                        </div>
                        <div class="col-12 mb-1">
                            <span class="small text-uppercase text-muted fw-semibold">Respondent</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First name</label>
                            <input type="text" class="form-control" name="respondent_first_name" autocomplete="given-name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last name</label>
                            <input type="text" class="form-control" name="respondent_last_name" autocomplete="family-name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="complaint_type">
                                <option value="">—</option>
                                <?php foreach (complaintCategoriesList() as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
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
                <button type="submit" form="createForm" class="btn btn-primary" id="btnCreate">Create</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
document.getElementById('createForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const f = this;
    ['complaint_title', 'complainant_first_name', 'complainant_last_name', 'respondent_first_name', 'respondent_last_name', 'narrative'].forEach(function(name) {
        const el = f.elements.namedItem(name);
        if (el && typeof el.value === 'string') {
            el.value = el.value.trim();
        }
    });
    if (!f.checkValidity()) {
        f.reportValidity();
        return;
    }
    if (window.applyTitleCaseToCreateForm) {
        window.applyTitleCaseToCreateForm(f);
    }
    const fd = new FormData();
    fd.append('action', 'create');
    fd.append('complaint_title', f.complaint_title.value);
    fd.append('complainant_first_name', f.complainant_first_name.value);
    fd.append('complainant_last_name', f.complainant_last_name.value);
    fd.append('respondent_first_name', f.respondent_first_name.value.trim());
    fd.append('respondent_last_name', f.respondent_last_name.value.trim());
    fd.append('complaint_type', f.complaint_type ? f.complaint_type.value.trim() : '');
    fd.append('narrative', f.narrative.value);
    fd.append('filing_date', f.filing_date.value);
    fd.append('remarks', f.remarks.value.trim());
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
