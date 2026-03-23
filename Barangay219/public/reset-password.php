<?php
/**
 * E-Barangay Information Management System
 * Reset Password - New Password Entry
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/password-reset.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit();
}

// Check if password reset is verified
if (!isset($_SESSION['password_reset_verified']) || $_SESSION['password_reset_verified'] !== true) {
    header('Location: ' . BASE_URL . 'forgot-password.php');
    exit();
}

// Check if reset session has expired
if (time() > $_SESSION['password_reset_expires']) {
    unset($_SESSION['password_reset_verified']);
    header('Location: ' . BASE_URL . 'forgot-password.php?error=session_expired');
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

        .login-footer-note {
            margin-top: 0.9rem;
            font-size: 0.82rem;
            color: rgba(15, 23, 42, 0.82);
            text-align: center;
            font-weight: 500;
            letter-spacing: 0.01em;
        }

        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .password-wrapper .form-control {
            padding-right: 40px;
        }
        .password-toggle {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 6px 8px;
            font-size: 0.95rem;
            transition: color 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .password-toggle:hover {
            color: #1f2937;
        }
        .strength-text {
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 0.5rem;
            transition: color 0.3s ease;
            display: inline-block;
        }
        .strength-weak { color: #ef4444; }
        .strength-fair { color: #f97316; }
        .strength-good { color: #eab308; }
        .strength-strong { color: #22c55e; }
        .password-field-help {

        @media (min-width: 992px) {
            .login-card {
                padding: 2.25rem;
            }
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    <div class="login-container">
        <div class="login-stack">
            <div class="login-card">
                <div class="text-center mb-4">
                    <img src="<?php echo ASSETS_URL; ?>img/barangay_logo2.png" alt="Barangay Logo" class="login-brand-logo">
                    <h3 class="card-title mt-3">Reset Your Password</h3>
                    <p class="card-subtitle">Barangay 219 e-Portal</p>
                    <small class="text-muted">At least 8 chars, one letter and one number</small>

                <p class="text-muted small mb-3 text-center">Set your new password to continue.</p>

                <div id="alertContainer"></div>
                <form id="resetPasswordForm">
                    <div class="mb-4">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" class="form-control" name="new_password" id="password"
                                   minlength="8" required autocomplete="new-password"
                                   placeholder="Enter password">
                            <button type="button" class="password-toggle" id="passwordToggle" aria-label="Toggle password visibility">
                                <i class="bi bi-eye-slash"></i>
                            </button>
                        </div>
                        <div class="d-flex align-items-center justify-content-between password-field-help" style="margin-top: 0.5rem;">
                            <small class="text-muted">At least 8 chars, one letter and one number</small>
                            <div class="strength-text" id="strengthText"></div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" class="form-control" name="confirm_password" id="password_confirm"
                                   minlength="8" required autocomplete="new-password"
                                   placeholder="Confirm password">
                            <button type="button" class="password-toggle" id="confirmPasswordToggle" aria-label="Toggle confirm password visibility">
                                <i class="bi bi-eye-slash"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                        <i class="bi bi-key"></i> Reset Password
                    </button>
                </form>

                <div class="text-center mt-3">
                    <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-outline-primary w-100">Back to Login</a>
                        <i class="bi bi-eye-slash"></i>
                    </button>
            <div class="login-footer-note">Barangay 219 e-Portal v1.0</div>
        <div class="back-link">
            <a href="<?php echo BASE_URL; ?>login.php">← Back to Login</a>
    <script src="<?php echo ASSETS_URL; ?>js/bootstrap.bundle.min.js"></script>
    </div>
        (function () {
            var baseOuterInnerRatio = window.outerWidth && window.innerWidth ? window.outerWidth / window.innerWidth : 1;
            if (!isFinite(baseOuterInnerRatio) || baseOuterInnerRatio <= 0) {
                baseOuterInnerRatio = 1;
                strengthText.textContent = '';
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
                button.querySelector('i').classList.remove('bi-eye');

            syncBackgroundZoom();
            window.addEventListener('resize', syncBackgroundZoom, { passive: true });
            window.addEventListener('orientationchange', syncBackgroundZoom, { passive: true });

            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', syncBackgroundZoom, { passive: true });
                window.visualViewport.addEventListener('scroll', syncBackgroundZoom, { passive: true });
            }
        })();

        window.API_URL = '<?php echo addslashes(API_URL); ?>';

        // Password strength meter and show/hide toggle
        (function() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('password_confirm');
            const passwordToggle = document.getElementById('passwordToggle');
            const confirmPasswordToggle = document.getElementById('confirmPasswordToggle');
            const strengthBars = document.querySelectorAll('.password-strength .strength-bar');
            const strengthText = document.getElementById('strengthText');

            function calculateStrength(password) {
                let strength = 0;
                if (password.length >= 8) strength++;
                if (password.length >= 12) strength++;
                if (/[a-z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                return strength;
            }

            function updateStrengthMeter() {
                const password = passwordInput.value;
                const strength = calculateStrength(password);
                const levels = ['', 'weak', 'fair', 'good', 'strong'];
                const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];

                strengthBars.forEach((bar, idx) => {
                    bar.classList.remove('active-weak', 'active-fair', 'active-good', 'active-strong');
                    if (idx < strength) {
                        bar.classList.add('active-' + levels[strength]);
                    }
                });

                if (password.length > 0) {
                    strengthText.textContent = labels[strength];
                    strengthText.className = 'strength-text strength-' + levels[strength];
                    submitBtn.disabled = false;
                    strengthText.textContent = '';
                    strengthText.className = 'strength-text';
                console.error('Error:', error);


            function togglePasswordVisibility(input, button) {
                if (input.type === 'password') {
                    input.type = 'text';
                    button.querySelector('i').classList.remove('bi-eye-slash');
                    button.querySelector('i').classList.add('bi-eye');
                } else {
                    input.type = 'password';
                    button.querySelector('i').classList.remove('bi-eye');
                    button.querySelector('i').classList.add('bi-eye-slash');
                }
            }

            if (passwordInput) {
                passwordInput.addEventListener('input', updateStrengthMeter);
            }

            if (passwordToggle) {
                passwordToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    togglePasswordVisibility(passwordInput, passwordToggle);
                });
            }

            if (confirmPasswordToggle) {
                confirmPasswordToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    togglePasswordVisibility(confirmPasswordInput, confirmPasswordToggle);
                });
            }
        })();

        document.getElementById('resetPasswordForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;
            const alc = document.getElementById('alertContainer');

            if (!password || !passwordConfirm) {
                alc.innerHTML = '<div class="alert alert-danger">Both password fields are required.</div>';
                return;
            }
            if (password !== passwordConfirm) {
                alc.innerHTML = '<div class="alert alert-danger">Passwords do not match.</div>';
                return;
            }
            if (!/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z0-9]{8,16}$/.test(password)) {
                alc.innerHTML = '<div class="alert alert-danger">Password must be 8-16 characters and contain both letters and numbers.</div>';
                return;
            }

            btn.disabled = true;
            try {
                const r = await fetch(API_URL + 'password-reset.php?action=reset-password', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        new_password: password,
                        confirm_password: passwordConfirm
                    })
                });
                const data = await r.json();
                alc.innerHTML = '<div class="alert alert-' + (data.success ? 'success' : 'danger') + '">' + data.message + '</div>';
                if (data.success) {
                    setTimeout(() => { window.location.href = '<?php echo BASE_URL; ?>login.php?message=password_reset_success'; }, 1500);
                } else {
                    btn.disabled = false;
                }
            } catch (err) {
                alc.innerHTML = '<div class="alert alert-danger">Network error. Please try again.</div>';
                btn.disabled = false;
            }
        });
</html>
