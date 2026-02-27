<?php
/**
 * E-Barangay Information Management System
 * Login Page
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit();
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap CSS (Local) -->
    <link href="<?php echo ASSETS_URL; ?>css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?php echo ASSETS_URL; ?>style.css?v=<?php echo time(); ?>" rel="stylesheet">
    <style>
        .login-container {
            background-image:
                linear-gradient(rgba(15, 23, 42, 0.35), rgba(15, 23, 42, 0.35)),
                url('<?php echo ASSETS_URL; ?>img/barangaylogo.png') !important;
            background-repeat: no-repeat !important;
            background-position: center center !important;
            background-size: cover, 42% auto !important;
        }

        .login-brand-logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
            border-radius: 50%;
            background: #ffffff;
            padding: 4px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.18);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="text-center mb-4">
                <img src="<?php echo ASSETS_URL; ?>img/barangaylogo.png" alt="Barangay Logo" class="login-brand-logo">
                <h3 class="card-title mt-3"><?php echo APP_NAME; ?></h3>
                <p class="text-muted"><?php echo BARANGAY_NAME; ?></p>
            </div>
            
            <div id="alertContainer"></div>
            
            <form id="loginForm">
                <div class="mb-3">
                    <label for="username" class="form-label">Resident ID / Username</label>
                    <input type="text" class="form-control" id="username" name="username" required autofocus placeholder="BR219-YYYY-NNNNN or username">
                </div>
                <div class="mb-2">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="text-end mb-3">
                    <a href="forgot-password.php" class="btn btn-link btn-sm p-0">Forgot Password?</a>
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </button>
            </form>
            <div class="mt-3 text-center">
                <a href="register.php" class="btn btn-outline-secondary w-100">Register as Resident</a>
                <a href="activate-account.php" class="btn btn-link w-100 mt-2">Activate Account (after approval)</a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS (Local) -->
    <script src="<?php echo ASSETS_URL; ?>js/bootstrap.bundle.min.js"></script>
    <!-- Define API URL for JavaScript -->
    <script>
        <?php
        // Ensure API_URL is defined
        if (!defined('API_URL')) {
            require_once __DIR__ . '/../config/constants.php';
        }
        ?>
        window.API_URL = '<?php echo addslashes(API_URL); ?>';
        console.log('API URL set to:', window.API_URL);
        
        // Fallback if API_URL is not set correctly (check for PHP code that wasn't executed)
        if (!window.API_URL || window.API_URL.indexOf('&lt;?php') !== -1 || window.API_URL.indexOf('%3C') !== -1 || window.API_URL.trim() === '') {
            window.API_URL = '<?php echo addslashes(API_URL); ?>';
            console.warn('Using fallback API URL:', window.API_URL);
        }
    </script>
    <script src="<?php echo ASSETS_URL; ?>css/js/auth.js?v=<?php echo time(); ?>"></script>
</body>
</html>
