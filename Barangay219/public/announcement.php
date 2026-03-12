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

<div class="main-content module-page announcements-page">
    <div class="container-fluid">
        <div class="module-hero card border-0 shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <p class="module-kicker text-uppercase small mb-1">Communication Module</p>
                    <h2 class="mb-1"><i class="bi bi-megaphone me-2"></i>Announcements</h2>
                    <p class="module-subtitle mb-0">Create and manage barangay announcements for residents.</p>
                </div>
                <button class="btn btn-primary" id="btnOpenCreate" data-bs-toggle="modal" data-bs-target="#createModal">
                    <i class="bi bi-plus-lg"></i> New Announcement
                </button>
            </div>
        </div>

        <div class="row g-3 mb-4 module-stats" data-module="announcements">
            <div class="col-sm-6 col-lg-4">
                <div class="stat-card bg-primary text-white" data-status="" role="button" tabindex="0">
                    <div class="stat-icon"><i class="bi bi-megaphone"></i></div>
                    <div class="stat-value" data-stat="total">-</div>
                    <div class="stat-label">Total</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="stat-card bg-success text-white" data-status="published" role="button" tabindex="0">
                    <div class="stat-icon"><i class="bi bi-broadcast"></i></div>
                    <div class="stat-value" data-stat="published">-</div>
                    <div class="stat-label">Published</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="stat-card bg-secondary text-white" data-status="draft" role="button" tabindex="0">
                    <div class="stat-icon"><i class="bi bi-pencil-square"></i></div>
                    <div class="stat-value" data-stat="draft">-</div>
                    <div class="stat-label">Draft</div>
                </div>
            </div>
        </div>

        <div class="search-bar mb-3">
            <div class="row g-2">
                <div class="col-md-8">
                    <input type="text" class="form-control" id="searchInput" placeholder="Search by title...">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" onclick="searchAnnouncements()">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100" onclick="resetAnnouncements()">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <div class="data-table">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Expires</th>
                            <th>Views</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="announcementsTableBody"><tr><td colspan="8" class="text-center">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create Announcement Modal -->
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
                    <textarea class="form-control" id="createContent" rows="6" required placeholder="Enter announcement content..."></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" id="createCategory">
                            <option value="General">General</option>
                            <option value="Event">Event</option>
                            <option value="Advisory">Advisory</option>
                            <option value="Emergency">Emergency</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Priority</label>
                        <select class="form-select" id="createPriority">
                            <option value="normal">Normal</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Expiration Date</label>
                        <input type="date" class="form-control" id="createExpires">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Pin Announcement</label>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="createPin">
                            <label class="form-check-label" for="createPin">
                                Pin this announcement (shows first for residents)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="createStatus">
                        <option value="draft">Draft (Not visible to residents)</option>
                        <option value="published">Publish (Visible to residents)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Upload Photo</label>
                    <input type="file" class="form-control" id="createPhoto" accept="image/jpeg,image/png,image/webp">
                    <small class="text-muted">Allowed: JPG, PNG, WEBP. Max 5MB. Image is auto-optimized on upload.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnCreate">Create</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Announcement Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Announcement</h5>
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
                    <textarea class="form-control" id="editContent" rows="6" required></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" id="editCategory">
                            <option value="General">General</option>
                            <option value="Event">Event</option>
                            <option value="Advisory">Advisory</option>
                            <option value="Emergency">Emergency</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Priority</label>
                        <select class="form-select" id="editPriority">
                            <option value="normal">Normal</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Expiration Date</label>
                        <input type="date" class="form-control" id="editExpires">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Pin Announcement</label>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="editPin">
                            <label class="form-check-label" for="editPin">
                                Pin this announcement (shows first for residents)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="editStatus">
                        <option value="draft">Draft (Not visible to residents)</option>
                        <option value="published">Publish (Visible to residents)</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Upload Photo</label>
                    <input type="file" class="form-control" id="editPhoto" accept="image/jpeg,image/png,image/webp">
                    <small class="text-muted">Leave empty to keep the current image.</small>
                </div>
                <div class="mb-3" id="editImagePreviewWrap" style="display:none;">
                    <img id="editImagePreview" src="" alt="Current announcement image" style="max-width:220px;max-height:140px;border-radius:8px;border:1px solid #dee2e6;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSave">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="<?php echo ASSETS_URL; ?>css/js/module-stats.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo ASSETS_URL; ?>css/js/announcements.js?v=<?php echo time(); ?>"></script>
