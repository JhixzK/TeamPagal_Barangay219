<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/header.php';

requireLogin();
requireAnyRole([ROLE_BARANGAY_CAPTAIN, ROLE_SECRETARY, ROLE_KAGAWA, ROLE_SK_CHAIRMAN]);

$page_title = 'Announcements';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <h2><i class="bi bi-megaphone"></i> Announcements</h2>
        <div class="data-table mt-4">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Title</th><th>Posted By</th><th>Date</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody id="announcementsTableBody"><tr><td colspan="6" class="text-center">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="<?php echo ASSETS_URL; ?>css/js/announcements.js"></script>
