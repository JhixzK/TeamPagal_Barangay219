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

// Sync role from DB before any UI that depends on role (some pages include this header before requireLogin()).
if (isLoggedIn()) {
    refreshSessionRole();
}

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
    <link href="<?php echo ASSETS_URL; ?>style.css?v=<?php echo time(); ?>" rel="stylesheet">
    <?php if (isResidentView()): ?>
    <link href="<?php echo ASSETS_URL; ?>css/resident-official-theme.css?v=<?php echo time(); ?>" rel="stylesheet">
    <?php endif; ?>
    
    <!-- Define API URL for JavaScript (available on all pages) -->
    <script>
        // Provide API URL and permissions to client JS (escaped)
        window.API_URL = '<?php echo addslashes(API_URL); ?>';
        <?php if (isLoggedIn()): ?>
        window.CURRENT_ROLE = <?php echo json_encode(getEffectiveUserRole()); ?>;
        window.IS_ADMIN = <?php echo isSystemAdmin() ? 'true' : 'false'; ?>;
        window.ROLE_PERMISSIONS = <?php echo json_encode(getRolePermissions(getEffectiveUserRole())); ?>;
        window.canModulePermission = function(module, perm) {
            if (window.IS_ADMIN) return true;
            var perms = window.ROLE_PERMISSIONS || {};
            var mod = perms[module] || {};
            if (!mod.can_access) return false;
            if (perm === 'can_access' || perm === 'access') return !!mod.can_access;
            // Access-only mode: any non-access permission follows module access.
            return !!mod.can_access;
        };
        <?php else: ?>
        window.CURRENT_ROLE = null;
        window.IS_ADMIN = false;
        window.ROLE_PERMISSIONS = {};
        window.canModulePermission = function() { return false; };
        <?php endif; ?>
    </script>
</head>
<body class="<?php echo isResidentView() ? 'resident-view' : ''; ?>">
    <?php if (isLoggedIn()): ?>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark app-topbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?><?php echo normalizeRole(getRealUserRole()) === normalizeRole(ROLE_RESIDENT) ? 'resident_dashboard.php' : 'dashboard.php'; ?>">
                <img src="<?php echo ASSETS_URL; ?>img/barangay_logo2.png" alt="Barangay Logo" class="app-navbar-logo">
                <span class="app-brand-text">
                    <span class="app-brand-title"><?php echo APP_NAME; ?></span>
                    <span class="app-brand-subtitle"><?php echo BARANGAY_NAME; ?></span>
                </span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <?php if (canSwitchToResidentView()): ?>
                <div class="navbar-nav ms-auto align-items-center">
                    <div class="view-mode-switch-wrap d-flex align-items-center gap-2">
                        <?php if (isResidentView()): ?>
                        <span class="view-mode-label text-white-50"><i class="bi bi-house-door me-1"></i>Resident View</span>
                        <a href="<?php echo API_URL; ?>auth.php?action=view_mode&mode=official"
                           class="btn btn-sm btn-view-switch" data-view-mode-switch="official">
                            <i class="bi bi-shield-check me-1"></i>Switch to Official
                        </a>
                        <?php else: ?>
                        <span class="view-mode-label text-white-50"><i class="bi bi-shield-check me-1"></i>Official View</span>
                        <a href="<?php echo API_URL; ?>auth.php?action=view_mode&mode=resident"
                           class="btn btn-sm btn-view-switch" data-view-mode-switch="resident">
                            <i class="bi bi-house-door me-1"></i>Switch to Resident
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <script src="<?php echo ASSETS_URL; ?>css/js/view-mode-switch.js?v=<?php echo time(); ?>"></script>
    <?php endif; ?>
