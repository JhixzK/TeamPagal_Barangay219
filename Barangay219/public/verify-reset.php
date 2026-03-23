<?php
/**
 * E-Barangay Information Management System
 * Reset Password page (token-based, single-step)
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth-check.php';

$token = trim((string)($_GET['token'] ?? ''));

// Allow token-link access even if logged in, similar to Activate Account behavior.
if (isLoggedIn() && $token === '') {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo APP_NAME; ?></title>
    <link href="<?php echo ASSETS_URL; ?>css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>style.css?v=<?php echo time(); ?>" rel="stylesheet">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            isolation: isolate;
            overflow-x: hidden;
            background: #ffffff;
        }

        .login-stack {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            padding: 56px 16px 24px;
        }

        .login-container::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            background: url('<?php echo ASSETS_URL; ?>img/crop219logo.png') no-repeat 95% center;
            background-size: calc(900px * var(--bg-zoom-inverse, 1));
            opacity: 0.90;
            filter: blur(6px);
            transform: scale(1.03);
            pointer-events: none;
        }

        .login-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 520px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.80);
            border: 1px solid rgba(236, 240, 226, 0.9);
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.14);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            padding: 2rem 1.15rem;
        }

        .login-brand-logo {
            width: 72px;
            height: 72px;
            object-fit: cover;
            border-radius: 50%;
            background: #ffffff;
            padding: 0;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.18);
        }

        .login-card .card-title {
            font-size: clamp(1.7rem, 3.2vw, 2.25rem);
            font-weight: 800;
            letter-spacing: 0.01em;
            line-height: 1.25;
            margin-bottom: 0.45rem;
            display: inline-block;
            padding-bottom: 0.08em;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #1d4ed8 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .login-card .card-subtitle {
            color: #475569;
            font-weight: 600;
            letter-spacing: 0.01em;
            margin-bottom: 0;
        }

        .back-home-outside {
            position: fixed;
            top: 18px;
            left: 18px;
            z-index: 2;
            width: 44px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 1.1rem;
            border: 1px solid rgba(255, 255, 255, 0.45);
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #1d4ed8 100%);
            color: #ffffff;
            text-decoration: none;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.28);
            transition: filter 0.2s ease, transform 0.2s ease;
        }

        .back-home-outside:hover {
            filter: brightness(1.08);
            color: #ffffff;
            transform: translateY(-1px);
        }

        .login-footer-note {
            margin-top: 0.9rem;
            font-size: 0.82rem;
            color: rgba(15, 23, 42, 0.82);
            text-align: center;
            font-weight: 500;
            letter-spacing: 0.01em;
        }

        @media (min-width: 992px) {
            .login-card {
                padding: 2.25rem;
            }
        }
    </style>
</head>
<body>
    <a href="<?php echo BASE_URL; ?>forgot-password.php" class="back-home-outside" aria-label="Back to Forgot Password" title="Back to Forgot Password">
        <i class="bi bi-arrow-left"></i>
    </a>

    <div class="login-container">
        <div class="login-stack">
            <div class="login-card">
                <div class="text-center mb-4">
                    <img src="<?php echo ASSETS_URL; ?>img/barangay_logo2.png" alt="Barangay Logo" class="login-brand-logo">
                    <h3 class="card-title mt-3">Reset Password</h3>
                    <p class="card-subtitle">Barangay 219 e-Portal</p>
                </div>

                <?php if ($token === ''): ?>
                    <div class="alert alert-warning d-flex align-items-start gap-2">
                        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                        <div>Invalid or missing reset link. Please request a new password reset email.</div>
                    </div>
                    <a href="<?php echo BASE_URL; ?>forgot-password.php" class="btn btn-outline-primary w-100">Back to Forgot Password</a>
                <?php else: ?>
                    <p class="text-muted small mb-3 text-center">Set your new password to complete account recovery.</p>

                    <div id="alertContainer"></div>

                    <form id="resetForm">
                        <input type="hidden" id="resetToken" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="mb-3">
                            <label for="newPassword" class="form-label">New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="newPassword" autocomplete="new-password" required>
                            <small class="text-muted">Must be at least 8 chars and include uppercase, lowercase, number, and special character.</small>
                        </div>

                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirmPassword" autocomplete="new-password" required>
                            <div class="invalid-feedback" id="passwordErrorText"></div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                            <i class="bi bi-key"></i> Reset Password
                        </button>

                        <div id="loadingSpinner" class="text-center text-primary mt-3" style="display:none;">
                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                            Updating password...
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            <div class="login-footer-note">Barangay 219 e-Portal v1.0</div>
        </div>
    </div>

    <script src="<?php echo ASSETS_URL; ?>js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            var baseOuterInnerRatio = window.outerWidth && window.innerWidth ? window.outerWidth / window.innerWidth : 1;
            if (!isFinite(baseOuterInnerRatio) || baseOuterInnerRatio <= 0) {
                baseOuterInnerRatio = 1;
            }

            function syncBackgroundZoom() {
                var viewportScale = window.visualViewport && window.visualViewport.scale ? window.visualViewport.scale : 1;
                if (!isFinite(viewportScale) || viewportScale <= 0) {
                    viewportScale = 1;
                }

                var desktopScale = 1;
                if (window.outerWidth && window.innerWidth) {
                    desktopScale = (window.outerWidth / window.innerWidth) / baseOuterInnerRatio;
                }
                if (!isFinite(desktopScale) || desktopScale <= 0) {
                    desktopScale = 1;
                }

                var zoomScale = Math.max(viewportScale, desktopScale);
                document.documentElement.style.setProperty('--bg-zoom-inverse', (1 / zoomScale).toFixed(4));
            }

            syncBackgroundZoom();
            window.addEventListener('resize', syncBackgroundZoom, { passive: true });
            window.addEventListener('orientationchange', syncBackgroundZoom, { passive: true });

            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', syncBackgroundZoom, { passive: true });
                window.visualViewport.addEventListener('scroll', syncBackgroundZoom, { passive: true });
            }
        })();

        function showAlert(type, message) {
            var container = document.getElementById('alertContainer');
            if (!container) return;
            container.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">'
                + message
                + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                + '</div>';
        }

        function setPasswordError(message) {
            var confirmField = document.getElementById('confirmPassword');
            var text = document.getElementById('passwordErrorText');
            if (!confirmField || !text) return;
            confirmField.classList.add('is-invalid');
            text.textContent = message;
        }

        function clearPasswordError() {
            var confirmField = document.getElementById('confirmPassword');
            var text = document.getElementById('passwordErrorText');
            if (!confirmField || !text) return;
            confirmField.classList.remove('is-invalid');
            text.textContent = '';
        }

        function isStrongPassword(password) {
            if (password.length < 8) return false;
            if (!/[A-Z]/.test(password)) return false;
            if (!/[a-z]/.test(password)) return false;
            if (!/\d/.test(password)) return false;
            if (!/[!@#$%^&*()_+=\[\]{};:'",.<>?\\|`~\-]/.test(password)) return false;
            return true;
        }

        document.getElementById('newPassword')?.addEventListener('input', clearPasswordError);
        document.getElementById('confirmPassword')?.addEventListener('input', clearPasswordError);

        document.getElementById('resetForm')?.addEventListener('submit', async function (e) {
            e.preventDefault();
            clearPasswordError();

            var token = document.getElementById('resetToken').value;
            var newPassword = document.getElementById('newPassword').value || '';
            var confirmPassword = document.getElementById('confirmPassword').value || '';

            if (!newPassword || !confirmPassword) {
                setPasswordError('Please complete both password fields.');
                return;
            }

            if (newPassword !== confirmPassword) {
                setPasswordError('Passwords do not match.');
                return;
            }

            if (!isStrongPassword(newPassword)) {
                setPasswordError('Password must be at least 8 characters and include uppercase, lowercase, number, and special character.');
                return;
            }

            var submitBtn = document.getElementById('submitBtn');
            var loading = document.getElementById('loadingSpinner');
            submitBtn.disabled = true;
            loading.style.display = 'block';

            try {
                var response = await fetch('<?php echo API_URL; ?>password-reset.php?action=reset-with-token', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        token: token,
                        new_password: newPassword,
                        confirm_password: confirmPassword
                    })
                });

                var result = await response.json();
                if (result.success) {
                    showAlert('success', 'Password reset successful. Redirecting to login...');
                    setTimeout(function () {
                        window.location.href = '<?php echo BASE_URL; ?>login.php';
                    }, 1500);
                } else {
                    setPasswordError(result.message || 'Unable to reset password. Please try again.');
                    submitBtn.disabled = false;
                    loading.style.display = 'none';
                }
            } catch (error) {
                showAlert('danger', 'An error occurred. Please try again.');
                submitBtn.disabled = false;
                loading.style.display = 'none';
            }
        });
    </script>
</body>
</html>
