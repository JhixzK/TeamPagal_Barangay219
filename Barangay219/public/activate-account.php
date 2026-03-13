<?php
/**
 * E-Barangay - Account Activation Page
 * Resident sets password after barangay approval.
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth-check.php';

$token = trim($_GET['token'] ?? '');

if (isLoggedIn() && !$token) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activate Account - <?php echo APP_NAME; ?></title>
    <link href="<?php echo ASSETS_URL; ?>css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>style.css" rel="stylesheet">
    <style>
        .login-container {
            position: relative;
            isolation: isolate;
            overflow: hidden;
            background: none !important;
            background-image: none !important;
        }

        .login-container::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            background: url('<?php echo ASSETS_URL; ?>img/crop219logo.png') no-repeat 95% center;
            background-size: 900px;
            opacity: 0.85;
            filter: blur(7px);
            transform: scale(1.03);
            pointer-events: none;
        }

        .login-card {
            position: relative;
            z-index: 1;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="text-center mb-4">
                <i class="bi bi-key" style="font-size: 3rem; color: #0d6efd;"></i>
                <h3 class="card-title mt-3">Activate Your Account</h3>
                <p class="text-muted"><?php echo BARANGAY_NAME; ?></p>
            </div>
            <?php if (!$token): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> Invalid or missing activation link. 
                    Please use the link provided by the barangay after your registration was approved.
                </div>
                <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-outline-primary w-100">Back to Login</a>
            <?php else: ?>
                <div id="alertContainer"></div>
                <form id="activateForm">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <div class="mb-3">
                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" id="password" 
                               minlength="8" maxlength="16" required autocomplete="new-password"
                               placeholder="8-16 chars, letters and numbers">
                        <small class="text-muted">Must be 8-16 characters with letters and numbers.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password_confirm" id="password_confirm" 
                               minlength="8" maxlength="16" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check-circle"></i> Activate Account
                    </button>
                </form>
                <div class="mt-3 text-center">
                    <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-link">Back to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="<?php echo ASSETS_URL; ?>js/bootstrap.bundle.min.js"></script>
    <script>
        window.API_URL = '<?php echo addslashes(API_URL); ?>';
        document.getElementById('activateForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type=submit]');
            btn.disabled = true;
            const formData = new FormData(this);
            try {
                const r = await fetch(API_URL + 'activate-account.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await r.json();
                const alc = document.getElementById('alertContainer');
                alc.innerHTML = '<div class="alert alert-' + (data.success ? 'success' : 'danger') + '">' + data.message + '</div>';
                if (data.success) {
                    setTimeout(() => { window.location.href = data.data?.redirect || '<?php echo BASE_URL; ?>login.php'; }, 1500);
                } else {
                    btn.disabled = false;
                }
            } catch (err) {
                alc.innerHTML = '<div class="alert alert-danger">Network error. Please try again.</div>';
                btn.disabled = false;
            }
        });
    </script>
</body>
</html>
