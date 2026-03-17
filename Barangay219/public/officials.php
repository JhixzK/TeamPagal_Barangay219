<?php
/**
 * E-Barangay Information Management System
 * Officials Management Page
 */

define('ACCESS_ALLOWED', true);
$page_title = 'Officials';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireAnyRole([ROLE_SUPER_ADMIN, ROLE_BARANGAY_CAPTAIN]);
requireModuleAccess('officials');

include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content module-page officials-page">
    <div class="container-fluid">
        <div class="module-hero card border-0 shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <p class="module-kicker text-uppercase small mb-1">Administration Module</p>
                    <h2 class="mb-1"><i class="bi bi-people-fill me-2"></i>Officials</h2>
                    <p class="module-subtitle mb-0">
                        Manage the 10 core barangay officials (1 Captain, 7 Kagawad, 1 SK Chairperson, 1 Secretary, 1 Treasurer).
                    </p>
                </div>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#officialModal" onclick="resetOfficialForm()">
                        <i class="bi bi-plus-circle"></i> Add Official
                    </button>
                </div>
            </div>
        </div>

        <div id="officialsTiles" class="officials-tiles">
            <div class="officials-tiles-loading text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Official Modal -->
<div class="modal fade" id="officialModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="officialModalTitle">Add Official</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="officialForm">
                <div class="modal-body">
                    <input type="hidden" id="officialId" name="id">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Resident <span class="text-danger">*</span></label>
                            <input type="hidden" id="resident_id" name="resident_id" value="">
                            <div class="position-relative">
                                <input type="text" class="form-control" id="resident_search" placeholder="Search resident name or code..." autocomplete="off" required>
                                <div id="residentSearchResults" class="list-group resident-search-results" style="display:none;"></div>
                            </div>
                            <small class="text-muted">Select from the residents list to assign as an official.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Position <span class="text-danger">*</span></label>
                            <input type="hidden" id="position" name="position" value="">
                            <input type="text" class="form-control" id="position_display" value="" readonly>
                            <small class="text-muted">Position is fixed by the tile/slot you selected.</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" maxlength="255" readonly>
                            <small class="text-muted">Auto-filled from selected resident.</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Term Start</label>
                            <input type="date" class="form-control" id="term_start" name="term_start">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Term End</label>
                            <input type="date" class="form-control" id="term_end" name="term_end">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="<?php echo ASSETS_URL; ?>css/js/officials.js?v=<?php echo time(); ?>"></script>

