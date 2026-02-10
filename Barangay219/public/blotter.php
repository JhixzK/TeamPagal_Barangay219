<?php
define('ACCESS_ALLOWED', true);
$page_title = 'Blotter';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('blotters');

include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 gap-3 flex-wrap">
            <h2><i class="bi bi-journal-text"></i> Blotter Management</h2>
            <div class="flex-grow-1 min-width-search">
                <input type="text" class="form-control" id="searchInput" placeholder="Search by case title, complainant, or status...">
            </div>
            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#filterModal">
                <i class="bi bi-funnel"></i> Filter
            </button>
            <button class="btn btn-primary" id="btnOpenCreate" data-bs-toggle="modal" data-bs-target="#blotterModal" onclick="resetBlotterForm()">
                <i class="bi bi-plus-circle"></i> Add New Blotter
            </button>
        </div>

        <div class="data-table mt-2">
            <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Case Title</th><th>Complainant</th><th>Date</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody id="blotterTableBody"><tr><td colspan="6" class="text-center">Loading...</td></tr></tbody>
            </table>
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
                    <div class="mb-3">
                        <label class="form-label fw-bold">Incident Location:</label>
                        <p id="viewIncidentLocation"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Description:</label>
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Blotter Modal -->
    <div class="modal fade blotterModal" id="blotterModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
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
                            <div class="col-12 mb-3">
                                <label class="form-label">Incident Location <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="incident_location" name="incident_location" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        </div>

                        <hr>
                        <h6>Complainants</h6>
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
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Blotter Case</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog">
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
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="<?php echo ASSETS_URL; ?>css/js/blotter.js?v=<?php echo time(); ?>"></script>
