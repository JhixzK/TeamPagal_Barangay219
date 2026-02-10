<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('announcements');

$page_title = 'Announcements';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><i class="bi bi-megaphone"></i> Announcements</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                <i class="bi bi-plus-lg"></i> New Announcement
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th><th>Title</th><th>Posted By</th><th>Date</th><th>Expires</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody id="announcementsTableBody"><tr><td colspan="7" class="text-center">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="viewContent" class="mb-3"></div>
                <small class="text-muted">Posted: <span id="viewDate"></span> | By: <span id="viewBy"></span></small>
            </div>
            <div class="modal-footer" id="viewFooter"></div>
        </div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalTitle">New Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editId">
                <div class="mb-3">
                    <label class="form-label">Title *</label>
                    <input type="text" class="form-control" id="editTitle" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Content *</label>
                    <textarea class="form-control" id="editContent" rows="5" required></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Date Posted</label>
                        <input type="date" class="form-control" id="editDatePosted">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Expiration Date</label>
                        <input type="date" class="form-control" id="editExpiration">
                    </div>
                    <div class="col-md-6 mb-3" id="editStatusRow">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="editStatus">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSave">Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Title *</label>
                    <input type="text" class="form-control" id="createTitle" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Content *</label>
                    <textarea class="form-control" id="createContent" rows="5" required></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Date Posted</label>
                        <input type="date" class="form-control" id="createDatePosted" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Expiration Date</label>
                        <input type="date" class="form-control" id="createExpiration">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnCreate">Create</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="<?php echo ASSETS_URL; ?>css/js/announcements.js?v=<?php echo time(); ?>"></script>
