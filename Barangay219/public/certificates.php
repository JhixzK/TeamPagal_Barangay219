<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireAnyRole([ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY, ROLE_TREASURER]);

$page_title = 'Certificates';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <h2><i class="bi bi-file-earmark-text"></i> Certificate Management</h2>
        <div class="data-table mt-4">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Resident</th><th>Type</th><th>Status</th><th>Date</th><th>Actions</th></tr>
                </thead>
                <tbody id="certTableBody"><tr><td colspan="6" class="text-center">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="<?php echo ASSETS_URL; ?>css/js/certificates.js"></script>
