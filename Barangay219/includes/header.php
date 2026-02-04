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
        window.API_URL = '<?php echo API_URL; ?>';
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
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> 
                            <?php echo htmlspecialchars($userInfo['username'] ?? 'User'); ?>
                            <span class="badge bg-light text-dark ms-1"><?php echo ucfirst(str_replace('_', ' ', getCurrentUserRole())); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>profile.php">
                                <i class="bi bi-person"></i> Profile
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="logout()">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>
