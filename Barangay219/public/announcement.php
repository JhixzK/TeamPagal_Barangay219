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

        <ul class="nav nav-tabs app-tabs mb-3" id="statusTabs">
            <li class="nav-item"><a class="nav-link active" href="#" data-status="">All</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="published">Published</a></li>
            <li class="nav-item"><a class="nav-link" href="#" data-status="draft">Draft</a></li>
        </ul>

        <div class="data-table announcements-table-wrap">
            <div class="table-responsive">
                <table class="table table-hover announcements-table align-middle">
                    <thead>
                        <tr>
                            <th class="text-center">Title</th>
                            <th class="text-center">Category</th>
                            <th class="text-center">Priority</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Created</th>
                            <th class="text-center">Expires</th>
                            <th class="text-center">Views</th>
                            <th class="text-center announcements-actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="announcementsTableBody"><tr><td colspan="8" class="text-center">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.announcements-page .app-tabs {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.45rem;
    border-bottom: 0;
}

.announcements-page .app-tabs .nav-item {
    margin: 0;
}

.announcements-page .app-tabs .nav-link {
    width: 100%;
    text-align: center;
    border: 1px solid #dbe3ee;
    border-radius: 999px;
    color: #475569;
    font-weight: 600;
    padding: 0.5rem 0.8rem;
    background: #ffffff;
}

.announcements-page .app-tabs .nav-link.active {
    color: #1d4ed8;
    background: #e8f0ff;
    border-color: #bfdbfe;
}

.announcements-page .announcements-table-wrap {
    border: 1px solid #e7ecf3;
    border-radius: 14px;
    background: #fff;
    padding: 0.35rem;
}

.announcements-page .announcements-table {
    margin-bottom: 0;
}

.announcements-page .announcements-table > :not(caption) > * > * {
    border-bottom: 1px solid #edf1f6;
    padding: 0.9rem 0.85rem;
    vertical-align: middle;
}

.announcements-page .announcements-table thead th {
    border-bottom: 1px solid #dfe6ef;
    color: #4b5563;
    font-size: 0.82rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    background: #f9fbfd;
}

.announcements-page .announcements-table tbody td {
    color: #1f2937;
    font-size: 0.94rem;
}

.announcements-page .announcements-table tbody tr:hover {
    background: #f8fbff;
}

.announcements-page .announcements-secondary {
    color: #6b7280;
    font-size: 0.86rem;
}

.announcements-page .announcement-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 86px;
    padding: 0.35rem 0.65rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.01em;
}

.announcements-page .announcement-pill.category-general {
    background: #eef2f7;
    color: #4b5563;
}

.announcements-page .announcement-pill.category-event {
    background: #e9f8ef;
    color: #1f7a3f;
}

.announcements-page .announcement-pill.category-advisory {
    background: #e8f0ff;
    color: #2553b8;
}

.announcements-page .announcement-pill.category-emergency {
    background: #fdecec;
    color: #b42318;
}

.announcements-page .announcement-pill.priority-urgent {
    background: #ffecee;
    color: #a53a44;
}

.announcements-page .announcement-pill.priority-normal {
    background: #eef2f7;
    color: #4b5563;
}

.announcements-page .announcement-pill.status-published {
    background: #e9f8ef;
    color: #1f7a3f;
}

.announcements-page .announcement-pill.status-draft {
    background: #fff4e8;
    color: #9a5b11;
}

.announcements-page .announcement-pill.views-pill {
    min-width: 54px;
    background: #eaf6ff;
    color: #1f5f8b;
}

.announcements-page .announcements-actions {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
}

.announcements-page .action-icon-btn {
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

.announcements-page .action-icon-btn:hover,
.announcements-page .action-icon-btn:focus-visible {
    background: #f5f9ff;
    border-color: #d6e4ff;
    color: #2f4f95;
    transform: translateY(-1px);
}

.announcements-page .action-icon-btn.action-delete:hover,
.announcements-page .action-icon-btn.action-delete:focus-visible {
    background: #fff1f3;
    border-color: #f6ccd3;
    color: #9f2f3e;
}

.announcements-page .announcements-actions-col {
    min-width: 170px;
}

@media (max-width: 768px) {
    .announcements-page .announcements-table > :not(caption) > * > * {
        padding: 0.75rem 0.6rem;
    }
}
</style>

<!-- Create Announcement Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
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
    <div class="modal-dialog modal-lg modal-dialog-centered">
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
<script src="<?php echo ASSETS_URL; ?>css/js/announcements.js?v=<?php echo time(); ?>"></script>
