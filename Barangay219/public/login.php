<?php
/**
 * E-Barangay Information Management System
 * Login Page
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = getCurrentUserRole();
    if ($role === 'resident') {
        header('Location: ' . BASE_URL . 'resident_dashboard.php');
    } else {
        header('Location: ' . BASE_URL . 'dashboard.php');
    }
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
            background: rgba(255, 255, 255, 0.80);
            border: 1px solid rgba(236, 240, 226, 0.9);
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.14);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
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

        .password-field {
            position: relative;
        }

        .username-field {
            position: relative;
        }

        .username-field .form-control,
        .password-field .form-control {
            padding-right: 2.85rem;
            border-radius: 10px !important;
        }

        .username-field-icon {
            position: absolute;
            top: 50%;
            right: 0.45rem;
            transform: translateY(-50%);
            z-index: 3;
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            background: rgba(37, 99, 235, 0.06);
            border-radius: 999px;
            pointer-events: none;
        }

        .password-toggle-btn {
            position: absolute;
            top: 50%;
            right: 0.45rem;
            transform: translateY(-50%);
            z-index: 3;
            width: 34px;
            height: 34px;
            padding: 0 !important;
            border: 0 !important;
            background: transparent !important;
            color: #64748b;
            box-shadow: none !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
        }

        .password-toggle-btn i {
            font-size: 1rem;
            line-height: 1;
            display: block;
        }

        .password-toggle-btn i::before {
            display: block;
        }

        .password-toggle-btn:hover,
        .password-toggle-btn:focus {
            color: #1e3a8a;
            background: rgba(37, 99, 235, 0.08) !important;
            border-radius: 999px;
            outline: none;
        }

        .link-no-container,
        .link-no-container:hover,
        .link-no-container:focus,
        .link-no-container:active {
            background: transparent !important;
            border-color: transparent !important;
            box-shadow: none !important;
            text-decoration: none;
        }

        .link-no-container:focus-visible {
            text-decoration: underline;
            outline: none;
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

        /* Register button uses shared .btn-outline-secondary states from style.css */

        .login-legal-links {
            margin-top: 0.65rem;
            text-align: center;
            font-size: 0.82rem;
            color: #64748b;
        }

        .login-legal-links a {
            color: #334155;
            text-decoration: none;
        }

        .login-legal-links a:hover,
        .login-legal-links a:focus {
            color: #1e3a8a;
            text-decoration: underline;
        }

        .field-error {
            display: none;
            margin-top: 0.5rem;
            color: #b91c1c;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .field-error.is-visible {
            display: block;
        }

        .password-field.password-invalid .form-control,
        .password-field.password-invalid .password-toggle-btn {
            border-color: #dc2626;
        }

        .password-field.password-invalid .form-control:focus,
        .password-field.password-invalid .password-toggle-btn:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 0.2rem rgba(220, 38, 38, 0.18);
        }

        @media (min-width: 992px) {
            .login-card {
                max-width: 520px;
                padding: 2.25rem;
            }
        }
    </style>
</head>
<body>
    <a href="<?php echo BASE_URL; ?>home.php" class="back-home-outside" aria-label="Back to Home" title="Back to Home">
        <i class="bi bi-arrow-left"></i>
    </a>

    <div class="login-container">
        <div class="login-stack">
        <div class="login-card">
            <div class="text-center mb-4">
                <img src="<?php echo ASSETS_URL; ?>img/barangay_logo2.png" alt="Barangay Logo" class="login-brand-logo">
                <h3 class="card-title mt-3">Barangay 219 e-Portal</h3>
                <p class="card-subtitle">Tondo, Manila</p>
            </div>
            
            <div id="alertContainer"></div>

            <div id="loginStepPassword">
            <form id="loginForm">
                <div class="mb-3">
                    <label for="username" class="form-label">Resident ID / Username</label>
                    <div class="username-field">
                        <input type="text" class="form-control" id="username" name="username" required autofocus placeholder="BR219-YYYY-NNNNN or username">
                        <span class="username-field-icon" aria-hidden="true">
                            <i class="bi bi-person"></i>
                        </span>
                    </div>
                </div>
                <div class="mb-2">
                    <label for="password" class="form-label">Password</label>
                    <div class="password-field">
                        <input type="password" class="form-control" id="password" name="password" required placeholder="Enter your password">
                        <button class="password-toggle-btn" type="button" id="togglePassword" aria-label="Show password" aria-pressed="false">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="rememberMe" name="remember_me" value="1">
                        <label class="form-check-label small" for="rememberMe">Remember Me</label>
                    </div>
                    <a href="forgot-password.php" class="btn btn-link btn-sm p-0 link-no-container">Forgot Password?</a>
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </button>
            </form>
            </div>

            <div id="loginStep2fa" class="d-none">
                <p class="text-muted small mb-3" id="login2faHint">Enter the 6-digit code sent to your email.</p>
                <div class="mb-3">
                    <label for="login2faOtp" class="form-label">Verification code</label>
                    <input type="text" class="form-control text-center" style="letter-spacing:0.35em;font-size:1.25rem;font-weight:600;" id="login2faOtp" name="login2fa_otp" maxlength="6" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" placeholder="000000" aria-describedby="login2faHint">
                </div>
                <button type="button" class="btn btn-primary w-100 mb-2" id="login2faVerifyBtn">
                    <i class="bi bi-shield-check"></i> Verify &amp; continue
                </button>
                <button type="button" class="btn btn-link w-100 py-1" id="login2faResendBtn">Resend code</button>
                <button type="button" class="btn btn-outline-secondary w-100 mt-2" id="login2faBackBtn">
                    <i class="bi bi-arrow-left"></i> Back to login
                </button>
            </div>
            <div class="mt-3 text-center">
                <a href="register.php" class="btn btn-outline-secondary w-100 register-resident-btn">Register as Resident</a>
                <div class="login-legal-links">
                    <a href="terms-of-use.php">Terms of Use</a>
                    <span class="mx-2">|</span>
                    <a href="privacy-policy.php">Privacy Policy</a>
                </div>
            </div>
        </div>
        <div class="login-footer-note">Barangay 219 e-Portal v1.0</div>
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

        (function () {
            var passwordInput = document.getElementById('password');
            var toggleButton = document.getElementById('togglePassword');

            if (!passwordInput || !toggleButton) {
                return;
            }

            toggleButton.addEventListener('click', function () {
                var icon = toggleButton.querySelector('i');
                var showing = passwordInput.type === 'text';

                passwordInput.type = showing ? 'password' : 'text';
                toggleButton.setAttribute('aria-pressed', String(!showing));
                toggleButton.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');

                if (icon) {
                    icon.className = showing ? 'bi bi-eye' : 'bi bi-eye-slash';
                }
            });
        })();

        (function () {
            var storageKey = 'barangay219_remembered_username';
            var usernameInput = document.getElementById('username');
            var rememberCheckbox = document.getElementById('rememberMe');
            var loginForm = document.getElementById('loginForm');

            if (!usernameInput || !rememberCheckbox || !loginForm) {
                return;
            }

            try {
                var savedUsername = localStorage.getItem(storageKey);
                if (savedUsername) {
                    usernameInput.value = savedUsername;
                    rememberCheckbox.checked = true;
                }

                loginForm.addEventListener('submit', function () {
                    if (rememberCheckbox.checked) {
                        localStorage.setItem(storageKey, usernameInput.value.trim());
                    } else {
                        localStorage.removeItem(storageKey);
                    }
                });
            } catch (e) {
                // Ignore storage errors in restricted browser modes.
            }
        })();
    </script>
    <script src="<?php echo ASSETS_URL; ?>css/js/auth.js?v=<?php echo time(); ?>"></script>
</body>
</html>
