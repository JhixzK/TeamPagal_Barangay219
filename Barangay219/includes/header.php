<?php
/**
 * E-Barangay Information Management System
 * Header Component
 */

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth-check.php';

// Get current page
$current_page = basename($_SERVER['PHP_SELF']);
$userInfo = getUserInfo();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS (Local) -->
    <link href="<?php echo ASSETS_URL; ?>css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?php echo ASSETS_URL; ?>style.css" rel="stylesheet">
    
    <!-- Define API URL for JavaScript (available on all pages) -->
    <script>
        // Provide API URL and permissions to client JS (escaped)
        window.API_URL = '<?php echo addslashes(API_URL); ?>';
        <?php if (isLoggedIn()): ?>
        window.CURRENT_ROLE = <?php echo json_encode(getCurrentUserRole()); ?>;
        window.IS_ADMIN = <?php echo isAdmin() ? 'true' : 'false'; ?>;
        window.ROLE_PERMISSIONS = <?php echo json_encode(getRolePermissions(getCurrentUserRole())); ?>;
        window.canModulePermission = function(module, perm) {
            if (window.IS_ADMIN) return true;
            var perms = window.ROLE_PERMISSIONS || {};
            var mod = perms[module] || {};
            if (!mod.can_access) return false;
            if (perm === 'can_access' || perm === 'access') return !!mod.can_access;
            return !!mod[perm];
        };
        <?php else: ?>
        window.CURRENT_ROLE = null;
        window.IS_ADMIN = false;
        window.ROLE_PERMISSIONS = {};
        window.canModulePermission = function() { return false; };
        <?php endif; ?>
    </script>
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>dashboard.php">
                <i class="bi bi-building"></i> <?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav"></div>
        </div>
    </nav>
    <?php endif; ?>
