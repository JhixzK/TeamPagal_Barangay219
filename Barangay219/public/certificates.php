<?php
define('ACCESS_ALLOWED', true);
$page_title = 'Certificates';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireAnyRole([ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY, ROLE_TREASURER]);

include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0"><i class="bi bi-file-earmark-text"></i> Certificate Management</h2>
            <a href="<?php echo BASE_URL; ?>applications.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Application</a>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="<?php echo ASSETS_URL; ?>css/js/certificates.js?v=<?php echo time(); ?>"></script>
