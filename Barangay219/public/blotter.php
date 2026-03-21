<?php
define('ACCESS_ALLOWED', true);
$page_title = 'Blotter';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('blotters');

include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content module-page blotter-page">
    <div class="container-fluid">
        <div class="module-hero card border-0 shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <p class="module-kicker text-uppercase small mb-1">Cases Module</p>
                    <h2 class="mb-1"><i class="bi bi-journal-text me-2"></i>Blotter Management</h2>
                    <p class="module-subtitle mb-0">Record incidents, monitor hearing schedules, and update case resolution status.</p>
                </div>
                <button class="btn btn-primary" id="btnOpenCreate" data-bs-toggle="modal" data-bs-target="#blotterModal" onclick="resetBlotterForm()">
                    <i class="bi bi-plus-circle"></i> Add New Blotter
                </button>
            </div>
        </div>

        <div class="search-bar mb-3">
            <div class="row g-2 align-items-center">
                <div class="col-md-8">
                    <input type="text" class="form-control" id="searchInput" placeholder="Search by case title, complainant, or status...">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100" onclick="window.location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs app-tabs mb-3" id="statusTabs">
            <li class="nav-item"><a class="nav-link active" href="#" data-status="">All</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="pending">Pending</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="resolved">Resolved</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="settled">Settled</a></li>
        </ul>

        <div class="data-table mt-2 blotter-table-wrap">
            <div class="table-responsive">
            <table class="table table-hover blotter-table align-middle">
                <thead>
                    <tr>
                        <th class="text-center">ID</th>
                        <th class="text-center">Case Title</th>
                        <th class="text-center">Complainant</th>
                        <th class="text-center">Incident Type</th>
                        <th class="text-center">Incident Location</th>
                        <th class="text-center">Date</th>
                        <th class="text-center">Status</th>
                        <th class="text-center blotter-actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody id="blotterTableBody"><tr><td colspan="8" class="text-center">Loading...</td></tr></tbody>
            </table>
            </div>
        </div>
    </div>
</div>

    <!-- Blotter View Modal -->
    <div class="modal fade blotterModal" id="viewBlotterModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Blotter Case Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Case Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Case Title:</label>
                            <p id="viewCaseTitle"></p>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Incident Date:</label>
                            <p id="viewIncidentDate"></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Incident Type:</label>
                            <p id="viewIncidentType"></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Proof of Incident:</label>
                            <p id="viewIncidentProof"></p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Incident Location:</label>
                        <p id="viewIncidentLocation"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Incident Detail:</label>
                        <p id="viewDescription"></p>
                    </div>
                    
                    <hr>
                    <h6>Complainants</h6>
                    <div id="viewComplainantsInfo"></div>
                    
                    <hr>
                    <h6>Respondents</h6>
                    <div id="viewRespondentsInfo"></div>
                    
                    <hr>
                    <h6>Case Status</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Status:</label>
                            <p id="viewStatus"></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Settlement Date:</label>
                            <p id="viewSettlementDate"></p>
                        </div>
                    </div>

                    <hr>
                    <h6>Hearings</h6>
                    <div id="viewHearingsInfo"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Blotter Modal -->
    <div class="modal fade blotterModal" id="blotterModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="blotterModalTitle">Add New Blotter Case</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="blotterForm">
                    <div class="modal-body">
                        <input type="hidden" id="blotterId" name="id">

                        <h6>Case Information</h6>
                        <div class="row g-2">
                            <div class="col-12 col-lg-8 mb-3">
                                <label class="form-label">Case Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="case_title" name="case_title" required>
                            </div>
                            <div class="col-12 col-lg-4 mb-3">
                                <label class="form-label">Incident Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="incident_date" name="incident_date" required>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-12 col-lg-6 mb-3">
                                <label class="form-label">Incident Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="incident_type" name="incident_type" required>
                                    <option value="">Select Incident Type</option>
                                    <option value="physical_assault">Physical Assault</option>
                                    <option value="verbal_threat">Verbal Threat</option>
                                    <option value="theft">Theft</option>
                                    <option value="property_damage">Property Damage</option>
                                    <option value="public_disturbance">Public Disturbance</option>
                                    <option value="domestic_dispute">Domestic Dispute</option>
                                    <option value="harassment">Harassment</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-12 col-lg-6 mb-3" id="incidentTypeCustomWrap" style="display:none;">
                                <label class="form-label">Custom Incident Type <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="incident_type_custom" name="incident_type_custom" placeholder="Specify incident type">
                            </div>
                            <div class="col-12 col-lg-6 mb-3">
                                <label class="form-label">Proof of Incident</label>
                                <input type="file" class="form-control" id="proof_of_incident" name="proof_of_incident" accept=".jpg,.jpeg,.png,.pdf">
                                <small class="text-muted">Accepted: JPG, PNG, PDF (max 5MB)</small>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-12 mb-3">
                                <label class="form-label">Incident Location <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="incident_location" name="incident_location" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Incident Detail <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        </div>

                        <hr>
                        <h6>Complainants</h6>
                        <div class="alert alert-info py-2 px-3 mb-3" id="primaryComplainantInfo">
                            Complainant Name & Contact: -
                        </div>
                        <div id="complainantsContainer" class="mb-2">
                            <!-- template row inserted by JS -->
                        </div>
                        <div class="mb-3">
                            <button type="button" class="btn btn-sm btn-secondary" id="addComplainantBtn">+ Add Another Complainant</button>
                        </div>

                        <hr>
                        <h6>Respondents</h6>
                        <div id="respondentsContainer" class="mb-2"></div>
                        <div class="mb-3">
                            <button type="button" class="btn btn-sm btn-secondary" id="addRespondentBtn">+ Add Another Respondent</button>
                        </div>

                        <hr>
                        <h6>Case Status</h6>
                        <div class="row g-2">
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label">Case Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="pending">Pending</option>
                                    <option value="under_investigation">Under Investigation</option>
                                    <option value="resolved">Resolved</option>
                                    <option value="settled">Settled</option>
                                    <option value="referred">Referred</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label">Settlement Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="settlement_date" name="settlement_date" required>
                            </div>
                        </div>

                        <hr>
                        <h6>Hearings</h6>
                        <div id="hearingsContainer" class="mb-2"></div>
                        <div class="mb-3">
                            <button type="button" class="btn btn-sm btn-secondary" id="addHearingBtn">+ Add Hearing</button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Blotter Case</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Filter Blotters</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="">All</option>
                        <option value="pending">Pending</option>
                        <option value="under_investigation">Under Investigation</option>
                        <option value="resolved">Resolved</option>
                        <option value="settled">Settled</option>
                        <option value="referred">Referred</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Incident From</label>
                    <input type="date" class="form-control" id="filterFrom">
                </div>
                <div class="mb-3">
                    <label class="form-label">Incident To</label>
                    <input type="date" class="form-control" id="filterTo">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="applyFilters()">Apply Filters</button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Search bar responsive styling */
    .min-width-search {
        min-width: 250px;
    }
    @media (max-width: 768px) {
        .min-width-search {
            min-width: 150px;
            flex-basis: 100%;
        }
    }

    /* Scrollbar styling for data table container */
    .data-table {
        max-height: 600px;
        overflow-y: auto;
    }
    .data-table::-webkit-scrollbar {
        width: 8px;
    }
    .data-table::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    .data-table::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 10px;
    }
    .data-table::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
    /* Firefox scrollbar styling */
    .data-table {
        scrollbar-width: thin;
        scrollbar-color: #888 #f1f1f1;
    }

    /* Scrollbar styling for modal */
    .blotterModal .modal-body {
        max-height: 70vh;
        overflow-y: auto !important;
    }
    .blotterModal .modal-body::-webkit-scrollbar {
        width: 8px;
    }
    .blotterModal .modal-body::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    .blotterModal .modal-body::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 10px;
    }
    .blotterModal .modal-body::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
    /* Firefox scrollbar styling */
    .blotterModal .modal-body {
        scrollbar-width: thin;
        scrollbar-color: #888 #f1f1f1;
    }

    /* Card styling for view modal */
    .blotterModal .card {
        border: 1px solid #e0e0e0;
        background-color: #f9f9f9;
    }
    .blotterModal .card-body p {
        font-size: 0.95rem;
        color: #333;
    }

    .blotter-page .app-tabs {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.45rem;
        border-bottom: 0;
    }

    .blotter-page .app-tabs .nav-item {
        margin: 0;
    }

    .blotter-page .app-tabs .nav-link {
        width: 100%;
        text-align: center;
        border: 1px solid #dbe3ee;
        border-radius: 999px;
        color: #475569;
        font-weight: 600;
        padding: 0.5rem 0.8rem;
        background: #ffffff;
    }

    .blotter-page .app-tabs .nav-link.active {
        color: #1d4ed8;
        background: #e8f0ff;
        border-color: #bfdbfe;
    }

    .blotter-page .blotter-table-wrap {
        border: 1px solid #e7ecf3;
        border-radius: 14px;
        background: #fff;
        padding: 0.35rem;
    }

    .blotter-page .blotter-table {
        margin-bottom: 0;
    }

    .blotter-page .blotter-table > :not(caption) > * > * {
        border-bottom: 1px solid #edf1f6;
        padding: 0.9rem 0.85rem;
        vertical-align: middle;
    }

    .blotter-page .blotter-table thead th {
        border-bottom: 1px solid #dfe6ef;
        color: #4b5563;
        font-size: 0.82rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        background: #f9fbfd;
    }

    .blotter-page .blotter-table tbody td {
        color: #1f2937;
        font-size: 0.94rem;
    }

    .blotter-page .blotter-table tbody tr:hover {
        background: #f8fbff;
    }

    .blotter-page .blotter-secondary {
        color: #6b7280;
        font-size: 0.86rem;
    }

    .blotter-page .blotter-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 96px;
        padding: 0.35rem 0.65rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.01em;
    }

    .blotter-page .blotter-pill.status-pending {
        background: #fff4e8;
        color: #9a5b11;
    }

    .blotter-page .blotter-pill.status-under-investigation {
        background: #eaf6ff;
        color: #1f5f8b;
    }

    .blotter-page .blotter-pill.status-resolved,
    .blotter-page .blotter-pill.status-settled {
        background: #e9f8ef;
        color: #1f7a3f;
    }

    .blotter-page .blotter-pill.status-referred {
        background: #eef2f7;
        color: #4b5563;
    }

    .blotter-page .blotter-pill.status-unknown {
        background: #f1f3f6;
        color: #374151;
    }

    .blotter-page .blotter-actions {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
    }

    .blotter-page .action-icon-btn {
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

    .blotter-page .action-icon-btn:hover,
    .blotter-page .action-icon-btn:focus-visible {
        background: #f5f9ff;
        border-color: #d6e4ff;
        color: #2f4f95;
        transform: translateY(-1px);
    }

    .blotter-page .action-icon-btn.action-delete:hover,
    .blotter-page .action-icon-btn.action-delete:focus-visible {
        background: #fff1f3;
        border-color: #f6ccd3;
        color: #9f2f3e;
    }

    .blotter-page .blotter-actions-col {
        min-width: 140px;
    }

    @media (max-width: 768px) {
        .blotter-page .app-tabs {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .blotter-page .blotter-table > :not(caption) > * > * {
            padding: 0.75rem 0.6rem;
        }
    }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="<?php echo ASSETS_URL; ?>css/js/module-stats.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo ASSETS_URL; ?>css/js/blotter.js?v=<?php echo time(); ?>"></script>
