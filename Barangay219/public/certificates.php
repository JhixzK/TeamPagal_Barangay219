<?php
define('ACCESS_ALLOWED', true);
$page_title = 'Certificates';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('certificates');

include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0"><i class="bi bi-file-earmark-text"></i> Certificate Management</h2>
            <a href="<?php echo BASE_URL; ?>applications.php" class="btn btn-primary" id="btnOpenApplications"><i class="bi bi-plus-lg"></i> New Application</a>
        </div>
        <div class="search-bar mb-3">
            <div class="row">
                <div class="col-md-6">
                    <input type="text" class="form-control" id="searchInput" placeholder="Search by resident, ref #, or control #...">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" onclick="searchCertificates()">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100" onclick="resetCertificates()">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </button>
                </div>
            </div>
        </div>
        <div class="data-table mt-4">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th><th>Resident</th><th>Type</th><th>Ref #</th><th>Status</th><th>Date</th><th>Control #</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody id="certTableBody"><tr><td colspan="8" class="text-center">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Filter Certificates</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="">All</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="issued">Issued</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Certificate Type</label>
                    <select class="form-select" id="filterType">
                        <option value="">All</option>
                        <option value="barangay_clearance">Barangay Clearance</option>
                        <option value="certificate_residency">Certificate of Residency</option>
                        <option value="certificate_indigency">Certificate of Indigency</option>
                        <option value="certificate_good_moral">Certificate of Good Moral</option>
                        <option value="transfer_request">Transfer Request</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" id="filterFrom">
                </div>
                <div class="mb-3">
                    <label class="form-label">Date To</label>
                    <input type="date" class="form-control" id="filterTo">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="applyCertificateFilters()">Apply Filters</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="<?php echo ASSETS_URL; ?>css/js/certificates.js?v=<?php echo time(); ?>"></script>
