<?php
/**
 * E-Barangay Information Management System
 * Forgot Password - Choose Verification Method
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
            max-width: 400px;
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
        .method-btn {
            flex: 1;
            padding: 12px 10px;
            border: 2px solid #dee2e6;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
            font-size: 0.875rem;
            font-weight: 600;
            color: #495057;
            line-height: 1.3;
        }
        .method-btn:hover {
            border-color: #0d6efd;
            background: #f0f5ff;
        }
        .method-btn.active {
            border-color: #0d6efd;
            background: #0d6efd;
            color: white;
            box-shadow: 0 4px 14px rgba(13, 110, 253, 0.35);
        }
        .method-btn small {
            display: block;
            font-weight: 400;
            font-size: 11px;
            margin-top: 3px;
            opacity: 0.85;
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

        @media (min-width: 992px) {
            .login-card {
                max-width: 520px;
                padding: 2.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="text-center mb-4">
                <img src="<?php echo ASSETS_URL; ?>img/barangay_logo2.png" alt="Barangay Logo" class="login-brand-logo">
                <h3 class="card-title mt-3">Barangay 219 e-Portal</h3>
                <p class="card-subtitle">Tondo, Manila</p>
            </div>

            <h5 class="fw-semibold mb-1 text-center">Forgot Password</h5>
            <p class="text-muted small mb-3 text-center">Reset your password using your registered email or phone number.</p>

            <div id="alertContainer"></div>

            <!-- Choose Verification Method -->
            <form id="forgotPasswordForm">
                <div class="mb-3">
                    <label class="form-label fw-semibold">How would you like to verify?</label>
                    <div class="d-flex gap-2">
                        <button type="button" class="method-btn active" id="emailMethodBtn" onclick="selectMethod('email'); return false;">
                            <i class="bi bi-envelope-fill"></i> Email
                            <small>Get a link via email</small>
                        </button>
                        <button type="button" class="method-btn" id="smsMethodBtn" onclick="selectMethod('sms'); return false;">
                            <i class="bi bi-phone-fill"></i> SMS
                            <small>Get code via text</small>
                        </button>
                    </div>
                    <input type="hidden" id="selectedMethod" name="method" value="email">
                </div>

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
                </div>

                <!-- Mobile Input Section -->
                <div id="mobileSection" class="mb-3" style="display:none;">
                    <label for="userMobile" class="form-label">Registered Mobile Number</label>
                    <input
                        type="text"
                        class="form-control"
                        id="userMobile"
                        name="mobile"
                        placeholder="09xxxxxxxxx"
                        autocomplete="off"
                        maxlength="11"
                        inputmode="numeric"
                    >
                    <div class="form-text">
                        Please type your registered mobile number.
                        <span id="mobileDigitCount" class="fw-semibold"></span>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                    <i class="bi bi-send"></i> Send Reset Code
                </button>

                <div id="loadingSpinner" class="text-center text-primary mt-3" style="display:none;">
                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                    Sending code...
                </div>
            </form>

            <div class="mt-3 text-center">
                <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-link btn-sm p-0 link-no-container">
                    <i class="bi bi-arrow-left"></i> Back to Login
                </a>
            </div>
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

        function selectMethod(method) {
            const emailBtn = document.getElementById('emailMethodBtn');
            const smsBtn = document.getElementById('smsMethodBtn');
            const emailSec = document.getElementById('emailSection');
            const mobileSec = document.getElementById('mobileSection');

            if (method === 'sms') {
                emailBtn.classList.remove('active');
                smsBtn.classList.add('active');
                emailSec.style.display = 'none';
                mobileSec.style.display = 'block';
                document.getElementById('selectedMethod').value = 'sms';
                setTimeout(() => document.getElementById('userMobile').focus(), 50);
            } else {
                emailBtn.classList.add('active');
                smsBtn.classList.remove('active');
                emailSec.style.display = 'block';
                mobileSec.style.display = 'none';
                document.getElementById('selectedMethod').value = 'email';
                setTimeout(() => document.getElementById('userEmail').focus(), 50);
            }
        }

        function isValidEmail(email) {
            // Must have local part, @, domain with dot and at least 2-char TLD
            return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
        }

        // Real-time email validation feedback
        document.getElementById('userEmail').addEventListener('input', function() {
            const val = this.value.trim();
            if (!val) {
                this.classList.remove('is-valid', 'is-invalid');
            } else if (isValidEmail(val)) {
                this.classList.add('is-valid');
                this.classList.remove('is-invalid');
            } else {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            }
        });

        // Mobile input - digits only, must start with 09
        document.getElementById('userMobile').addEventListener('input', function(e) {
            let cleaned = e.target.value.replace(/\D/g, '');
            if (cleaned.length > 11) cleaned = cleaned.slice(0, 11);
            e.target.value = cleaned;

            const counter = document.getElementById('mobileDigitCount');
            if (cleaned.length === 0) {
                counter.textContent = '';
            } else if (cleaned.length < 11) {
                counter.textContent = `${cleaned.length}/11 digits`;
                counter.className = 'fw-semibold text-danger';
            } else {
                if (cleaned.startsWith('09')) {
                    counter.textContent = 'Valid ✓';
                    counter.className = 'fw-semibold text-success';
                } else {
                    counter.textContent = 'Must start with 09';
                    counter.className = 'fw-semibold text-danger';
                }
            }
        });

        // Form submission
        document.getElementById('forgotPasswordForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const method = document.getElementById('selectedMethod').value;
            let identifier = '';

            if (method === 'email') {
                identifier = document.getElementById('userEmail').value.trim();
                if (!identifier) {
                    showAlert('danger', 'Please enter your email address.');
                    return;
                }
                if (!isValidEmail(identifier)) {
                    showAlert('danger', 'Please enter a valid email address (e.g. yourname@gmail.com).');
                    document.getElementById('userEmail').focus();
                    return;
                }
            } else {
                const raw = document.getElementById('userMobile').value.trim();
                const digits = raw.replace(/\D/g, '');
                if (!digits) {
                    showAlert('danger', 'Please enter your mobile number.');
                    return;
                }
                if (digits.length !== 11) {
                    showAlert('danger', 'Mobile number must be exactly 11 digits.');
                    return;
                }
                if (!digits.startsWith('09')) {
                    showAlert('danger', 'Mobile number must start with 09 (e.g., 09xxxxxxxxx).');
                    return;
                }
                identifier = '+63' + digits.substring(1);
            }

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            document.getElementById('loadingSpinner').style.display = 'block';

            try {
                const response = await fetch('<?php echo API_URL; ?>password-reset.php?action=initiate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ identifier, method })
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('success', '&#10003; Code sent! Redirecting&hellip;');
                    setTimeout(() => {
                        window.location.href = '<?php echo BASE_URL; ?>verify-reset.php?method=' + method + '&identifier=' + encodeURIComponent(identifier);
                    }, 1200);
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
