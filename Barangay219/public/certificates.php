<?php
define('ACCESS_ALLOWED', true);
$page_title = 'Certificates';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('certificates');

include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content module-page certificates-page">
    <div class="container-fluid">
        <div class="module-hero card border-0 shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <p class="module-kicker text-uppercase small mb-1">Records Module</p>
                    <h2 class="mb-1"><i class="bi bi-file-earmark-text me-2"></i>Certificate Management</h2>
                    <p class="module-subtitle mb-0">Manage certificate requests, approval flow, and issuance records.</p>
                </div>
                <a href="<?php echo BASE_URL; ?>applications.php" class="btn btn-primary" id="btnOpenApplications"><i class="bi bi-plus-lg"></i> New Application</a>
            </div>
        </div>
        <div class="row g-3 mb-4 module-stats" data-module="certificates">
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card bg-primary text-white" data-status="" role="button" tabindex="0">
                    <div class="stat-icon"><i class="bi bi-files"></i></div>
                    <div class="stat-value" data-stat="total">-</div>
                    <div class="stat-label">Total Requests</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card bg-warning text-dark" data-status="pending" role="button" tabindex="0">
                    <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                    <div class="stat-value" data-stat="pending">-</div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <div class="stat-card bg-success text-white" data-status="approved" role="button" tabindex="0">
                    <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                    <div class="stat-value" data-stat="approved">-</div>
                    <div class="stat-label">Approved</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <div class="stat-card bg-info text-white" data-status="issued" role="button" tabindex="0">
                    <div class="stat-icon"><i class="bi bi-award"></i></div>
                    <div class="stat-value" data-stat="issued">-</div>
                    <div class="stat-label">Issued</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <div class="stat-card bg-danger text-white" data-status="rejected" role="button" tabindex="0">
                    <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
                    <div class="stat-value" data-stat="rejected">-</div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
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
<script src="<?php echo ASSETS_URL; ?>css/js/module-stats.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo ASSETS_URL; ?>css/js/certificates.js?v=<?php echo time(); ?>"></script>
