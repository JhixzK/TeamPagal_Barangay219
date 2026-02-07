<?php
define('ACCESS_ALLOWED', true);
$page_title = 'Blotter';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireAnyRole([ROLE_BARANGAY_CAPTAIN, ROLE_KAGAWA]);

include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="bi bi-journal-text"></i> Blotter Management</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#blotterModal" onclick="resetBlotterForm()">
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

    <!-- Blotter Modal -->
    <div class="modal fade" id="blotterModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="blotterModalTitle">Add New Blotter Case</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="blotterForm">
                    <div class="modal-body">
                        <input type="hidden" id="blotterId" name="id">

                        <h6>Case Information</h6>
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Case Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="case_title" name="case_title" required>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Incident Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="incident_date" name="incident_date" required>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Incident Location</label>
                                <input type="text" class="form-control" id="incident_location" name="incident_location">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
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
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Case Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="pending">Pending</option>
                                    <option value="under_investigation">Under Investigation</option>
                                    <option value="resolved">Resolved</option>
                                    <option value="settled">Settled</option>
                                    <option value="referred">Referred</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Settlement Date</label>
                                <input type="date" class="form-control" id="settlement_date" name="settlement_date">
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="<?php echo ASSETS_URL; ?>css/js/blotter.js?v=<?php echo time(); ?>"></script>
