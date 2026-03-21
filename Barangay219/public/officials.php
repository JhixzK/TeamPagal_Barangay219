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

<style>
.officials-page .officials-group {
    background: #ffffff;
    border: 1px solid #e7ecf3;
    box-shadow: 0 10px 24px -20px rgba(15, 23, 42, 0.35);
}

.officials-page .official-tile {
    border: 1px solid #edf1f6 !important;
}

.officials-page .official-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 82px;
    padding: 0.32rem 0.62rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.01em;
}

.officials-page .official-pill.status-active {
    background: #e9f8ef;
    color: #1f7a3f;
}

.officials-page .official-pill.status-inactive {
    background: #eef2f7;
    color: #4b5563;
}

.officials-page .official-pill.position-pill {
    background: #eaf6ff;
    color: #1f5f8b;
}

.officials-page .official-pill.vacant-pill {
    background: #f1f3f6;
    color: #4b5563;
}

.officials-page .official-actions {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
}

.officials-page .action-icon-btn {
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

.officials-page .action-icon-btn:hover,
.officials-page .action-icon-btn:focus-visible {
    background: #f5f9ff;
    border-color: #d6e4ff;
    color: #2f4f95;
    transform: translateY(-1px);
}

.officials-page .action-icon-btn.action-delete:hover,
.officials-page .action-icon-btn.action-delete:focus-visible {
    background: #fff1f3;
    border-color: #f6ccd3;
    color: #9f2f3e;
}

.officials-page .action-icon-btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
    transform: none;
}
</style>

<!-- Official Modal -->
<div class="modal fade" id="officialModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
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
                            <select class="form-select" id="position" name="position" required>
                                <option value="">-- Select position --</option>
                                <option value="barangay_captain">Punong Barangay (Captain)</option>
                                <option value="kagawad">Kagawad</option>
                                <option value="sk_chairperson">SK Chairperson</option>
                                <option value="secretary">Secretary</option>
                                <option value="treasurer">Treasurer</option>
                            </select>
                            <small class="text-muted">Choose the official's position in the barangay.</small>
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

