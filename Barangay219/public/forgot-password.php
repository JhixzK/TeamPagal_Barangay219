<?php
/**
 * E-Barangay Information Management System
 * Forgot Password - Email Verification
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth-check.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo APP_NAME; ?></title>
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
        
        /* Verification Method Selection */
        .method-btn {
            flex: 1;
            padding: 12px 10px;
            border: 2px solid #cbd5e1;
            background: #ffffff;
            border-radius: 10px;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
            text-align: center;
            font-size: 0.875rem;
            font-weight: 600;
            color: #334155;
            line-height: 1.3;
            font-family: 'Inter', sans-serif;
        }
        
        .method-btn:hover,
        .method-btn:focus,
        .method-btn:focus-visible {
            border-color: #bfdbfe;
            background: #f8fbff;
            color: #1e3a8a;
            transform: scale(1.03);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
        }

        .method-btn:active {
            background: #eaf2ff;
            border-color: #93c5fd;
            color: #1e3a8a;
            transform: scale(1.01);
            box-shadow: inset 0 2px 8px rgba(30, 64, 175, 0.12), 0 0 0 0.18rem rgba(191, 219, 254, 0.45);
        }
        
        .method-btn.active {
            border-color: #1d4ed8;
            background: #1d4ed8;
            color: white;
            box-shadow: 0 10px 24px rgba(29, 78, 216, 0.25);
            transform: scale(1.03);
        }

        .method-btn.active:hover,
        .method-btn.active:focus,
        .method-btn.active:focus-visible {
            border-color: #3b82f6;
            background: #3b82f6;
            color: #ffffff;
        }

        .method-btn.active:active {
            border-color: #60a5fa;
            background: #60a5fa;
            color: #ffffff;
            transform: scale(1.01);
            box-shadow: inset 0 2px 8px rgba(29, 78, 216, 0.18), 0 0 0 0.18rem rgba(59, 130, 246, 0.2);
        }
        
        .method-btn small {
            display: block;
            font-weight: 400;
            font-size: 11px;
            margin-top: 3px;
            opacity: 0.85;
        }

        /* Link Button Reset */
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

        @media (min-width: 992px) {
            .login-card {
                padding: 2.25rem;
            }
        }
    </style>
</head>
<body>
    <a href="<?php echo BASE_URL; ?>login.php" class="back-home-outside" aria-label="Back to Login" title="Back to Login">
        <i class="bi bi-arrow-left"></i>
    </a>

    <div class="login-container">
        <div class="login-stack">
            <div class="login-card">
                <div class="text-center mb-4">
                    <img src="<?php echo ASSETS_URL; ?>img/barangay_logo2.png" alt="Barangay Logo" class="login-brand-logo">
                    <h3 class="card-title mt-3">Forgot Password</h3>
                    <p class="card-subtitle">Barangay 219 e-Portal</p>
                </div>
                <p class="text-muted small mb-3 text-center">Reset your password using your registered email address.</p>

                <div id="alertContainer"></div>

                <form id="forgotPasswordForm">
                    <input type="hidden" id="selectedMethod" name="method" value="email">

                <!-- Email Input Section -->
                <div id="emailSection" class="mb-3">
                    <label for="userEmail" class="form-label">Registered Email Address</label>
                    <input
                        type="text"
                        class="form-control"
                        id="userEmail"
                        name="email"
                        placeholder="@gmail.com"
                        autocomplete="off"
                        inputmode="email"
                    >
                    <div class="form-text d-flex justify-content-between">
                        <span>Please type your registered email address.</span>
                    </div>
                    <div class="invalid-feedback" id="emailErrorText"></div>
                    <div class="valid-feedback">Valid Gmail address.</div>
                </div>

                    <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                        <i class="bi bi-send"></i> Send Reset Link
                    </button>

                    <div id="loadingSpinner" class="text-center text-primary mt-3" style="display:none;">
                        <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                        Sending reset link...
                    </div>
                </form>

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
            const container = document.getElementById('alertContainer');
            container.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
        }

        function setEmailError(message) {
            const emailField = document.getElementById('userEmail');
            const emailErrorText = document.getElementById('emailErrorText');
            if (!emailField || !emailErrorText) return;
            emailField.classList.add('is-invalid');
            emailField.classList.remove('is-valid');
            emailErrorText.textContent = message;
        }

        function clearEmailError() {
            const emailField = document.getElementById('userEmail');
            const emailErrorText = document.getElementById('emailErrorText');
            if (!emailField || !emailErrorText) return;
            emailField.classList.remove('is-invalid');
            emailErrorText.textContent = '';
        }

        function isValidEmail(email) {
            // Gmail-only format for this flow
            return /^[a-zA-Z0-9._%+\-]+@gmail\.com$/i.test(email);
        }

        // Real-time email validation feedback
        document.getElementById('userEmail').addEventListener('input', function() {
            const val = this.value.trim();
            clearEmailError();
            if (!val) {
                this.classList.remove('is-valid', 'is-invalid');
            } else if (isValidEmail(val)) {
                this.classList.add('is-valid');
                this.classList.remove('is-invalid');
            } else {
                this.classList.remove('is-valid');
            }
        });

        // Form submission
        document.getElementById('forgotPasswordForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const method = 'email';
            const identifier = document.getElementById('userEmail').value.trim();
            if (!identifier) {
                setEmailError('Please enter your email address.');
                document.getElementById('userEmail').focus();
                return;
            }
            if (!isValidEmail(identifier)) {
                setEmailError('Please enter a valid email address (e.g. yourname@gmail.com).');
                document.getElementById('userEmail').focus();
                return;
            }
            clearEmailError();

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            document.getElementById('loadingSpinner').style.display = 'block';

            try {
                const response = await fetch('<?php echo API_URL; ?>password-reset.php?action=initiate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ identifier, method })
                });

                const raw = await response.text();
                let result = null;
                try {
                    result = JSON.parse(raw);
                } catch (parseError) {
                    showAlert('Unable to process server response. Please try again or contact support if it continues.', 'danger');
                    submitBtn.disabled = false;
                    document.getElementById('loadingSpinner').style.display = 'none';
                    return;
                }

                if (result.success) {
                    showAlert('success', '&#10003; Reset link sent. Please check your email and click the link to continue.');
                    submitBtn.disabled = false;
                    document.getElementById('loadingSpinner').style.display = 'none';
                } else {
                    showAlert('danger', result.message || 'Failed to send code. Please try again.');
                    submitBtn.disabled = false;
                    document.getElementById('loadingSpinner').style.display = 'none';
                }
            } catch (error) {
                showAlert('danger', 'An error occurred: ' + error.message);
                submitBtn.disabled = false;
                document.getElementById('loadingSpinner').style.display = 'none';
            }
        });
    </script>
</body>
</html>
