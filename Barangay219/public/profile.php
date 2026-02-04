<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();

$page_title = 'Profile';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <h2><i class="bi bi-person"></i> My Profile</h2>
        <div class="form-card mt-4">
            <h5>User Information</h5>
            <p><strong>Username:</strong> <?php echo htmlspecialchars(getCurrentUsername()); ?></p>
            <p><strong>Role:</strong> <?php echo ucfirst(str_replace('_', ' ', getCurrentUserRole())); ?></p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
