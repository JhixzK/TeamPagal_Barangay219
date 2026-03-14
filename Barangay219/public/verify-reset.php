<?php
/**
 * E-Barangay Information Management System
 * Verify Reset - Verify OTP or Email Token
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

$method = sanitizeInput($_GET['method'] ?? 'email');
$identifier = sanitizeInput($_GET['identifier'] ?? '');
$token = sanitizeInput($_GET['token'] ?? ''); // For email verification via link

// Validate method
if (!in_array($method, ['email', 'sms'])) {
    $method = 'email';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Reset - <?php echo APP_NAME; ?></title>
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
        .otp-input-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 1.25rem 0;
        }
        .otp-input {
            width: 48px;
            height: 56px;
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            background: #f8f9fa;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
            outline: none;
            caret-color: #0d6efd;
        }
        .otp-input:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.2);
            background: #fff;
        }
        .otp-input.filled {
            border-color: #0d6efd;
            background: #eef3ff;
        }

        .otp-input.is-invalid {
            border-color: #dc3545;
            background: #fff5f5;
            box-shadow: 0 0 0 0.18rem rgba(220, 53, 69, 0.12);
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
                    <h3 class="card-title mt-3">Verify User</h3>
                    <p class="card-subtitle">Barangay 219 e-Portal</p>
                </div>

            <p class="text-muted small mb-3 text-center" id="headerText">
                <?php echo $method === 'sms'
                    ? 'Enter the 6-digit OTP sent to your phone number.'
                    : 'Enter the verification code sent to your email address.'; ?>
            </p>

            <div id="alertContainer"></div>

            <!-- Email Token Verification Form -->
            <form id="emailVerifyForm" <?php echo $method === 'sms' ? 'style="display:none;"' : ''; ?>>
                <div class="alert alert-info d-flex align-items-start gap-2 py-2">
                    <i class="bi bi-envelope-fill mt-1 flex-shrink-0"></i>
                    <div><strong>Verification Code</strong><br><small>Check your email for a verification link or code.</small></div>
                </div>

                <div class="mb-3">
                    <label for="emailCode" class="form-label">Verification Code</label>
                    <input
                        type="text"
                        class="form-control"
                        id="emailCode"
                        placeholder="Paste the code from your email"
                        autocomplete="off"
                    >
                    <div class="invalid-feedback" id="emailCodeErrorText"></div>
                </div>

                <button type="submit" class="btn btn-primary w-100" id="emailVerifyBtn">
                    <i class="bi bi-shield-check"></i> Verify Code
                </button>

                <div id="emailLoading" class="text-center text-primary mt-3" style="display:none;">
                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                    Verifying...
                </div>
            </form>

            <!-- SMS OTP Verification Form -->
            <form id="otpVerifyForm" <?php echo $method === 'email' ? 'style="display:none;"' : ''; ?>>
                <div class="alert alert-info d-flex align-items-start gap-2 py-2">
                    <i class="bi bi-phone-fill mt-1 flex-shrink-0"></i>
                    <div>
                        <strong>One-Time Password (OTP)</strong><br>
                        <small>A 6-digit code has been sent to your phone number.</small>
                        <span id="attemptsInfo" class="d-block mt-1" style="font-size:12px;"></span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Enter OTP</label>
                    <div class="otp-input-container" id="otpInputContainer">
                        <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="0">
                        <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="1">
                        <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="2">
                        <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="3">
                        <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="4">
                        <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="5">
                    </div>
                    <div class="invalid-feedback d-block" id="otpErrorText"></div>
                </div>

                <button type="button" class="btn btn-primary w-100" id="otpVerifyBtn">
                    <i class="bi bi-shield-check"></i> Verify OTP
                </button>

                <div id="otpLoading" class="text-center text-primary mt-3" style="display:none;">
                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                    Verifying...
                </div>

                <div class="text-center mt-3 pt-3 border-top">
                    <p class="text-muted small mb-2">Didn't receive the code?</p>
                    <button type="button" class="btn btn-link btn-sm p-0 link-no-container" id="resendBtn">
                        <i class="bi bi-arrow-clockwise"></i> Resend OTP
                    </button>
                    <div class="text-muted small mt-1" id="resendTimer"></div>
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

        const method = '<?php echo $method; ?>';
        const identifier = '<?php echo htmlspecialchars($identifier); ?>';
        const tokenParam = '<?php echo htmlspecialchars($token); ?>';

        function showAlert(message, type) {
            const container = document.getElementById('alertContainer');
            const icons = { success: 'check-circle-fill', danger: 'exclamation-triangle-fill', warning: 'exclamation-circle-fill', info: 'info-circle-fill' };
            const icon = icons[type] || 'info-circle-fill';
            container.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show d-flex align-items-start gap-2 py-2" role="alert">
                <i class="bi bi-${icon} flex-shrink-0 mt-1"></i>
                <div>${message}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
        }

        function setEmailCodeError(message) {
            const emailCodeField = document.getElementById('emailCode');
            const emailCodeErrorText = document.getElementById('emailCodeErrorText');
            if (!emailCodeField || !emailCodeErrorText) return;
            emailCodeField.classList.add('is-invalid');
            emailCodeErrorText.textContent = message;
        }

        function clearEmailCodeError() {
            const emailCodeField = document.getElementById('emailCode');
            const emailCodeErrorText = document.getElementById('emailCodeErrorText');
            if (!emailCodeField || !emailCodeErrorText) return;
            emailCodeField.classList.remove('is-invalid');
            emailCodeErrorText.textContent = '';
        }

        function setOtpError(message) {
            const otpErrorText = document.getElementById('otpErrorText');
            if (otpErrorText) {
                otpErrorText.textContent = message;
            }
            document.querySelectorAll('.otp-input').forEach(input => {
                input.classList.add('is-invalid');
            });
        }

        function clearOtpError() {
            const otpErrorText = document.getElementById('otpErrorText');
            if (otpErrorText) {
                otpErrorText.textContent = '';
            }
            document.querySelectorAll('.otp-input').forEach(input => {
                input.classList.remove('is-invalid');
            });
        }

        // Auto-verify if token is provided in URL (from email link)
        if (method === 'email' && tokenParam) {
            verifyEmailToken(tokenParam);
        }

        // OTP Input handling
        const otpInputs = document.querySelectorAll('.otp-input');
        otpInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                // Allow only single digit
                e.target.value = e.target.value.replace(/\D/g, '').slice(-1);
                e.target.classList.toggle('filled', e.target.value !== '');
                clearOtpError();
                if (e.target.value.length === 1 && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && e.target.value === '' && index > 0) {
                    otpInputs[index - 1].value = '';
                    otpInputs[index - 1].classList.remove('filled');
                    otpInputs[index - 1].focus();
                }
            });

            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pastedData = (e.clipboardData || window.clipboardData).getData('text');
                const otpDigits = pastedData.replace(/\D/g, '').split('');
                otpDigits.forEach((digit, i) => {
                    if (i < otpInputs.length) {
                        otpInputs[i].value = digit;
                        otpInputs[i].classList.toggle('filled', digit !== '');
                    }
                });
                // Focus the next empty slot or last one
                const next = otpDigits.length < otpInputs.length ? otpDigits.length : otpInputs.length - 1;
                otpInputs[next].focus();
            });
        });

        // Email verification
        document.getElementById('emailCode').addEventListener('input', () => {
            clearEmailCodeError();
        });

        document.getElementById('emailVerifyForm').addEventListener('submit', (e) => {
            e.preventDefault();
            const code = document.getElementById('emailCode').value.trim();
            clearEmailCodeError();

            if (!code) {
                setEmailCodeError('Please enter the verification code.');
                document.getElementById('emailCode').focus();
                return;
            }

            verifyEmailToken(code);
        });

        async function verifyEmailToken(token) {
            document.getElementById('emailVerifyBtn').disabled = true;
            document.getElementById('emailLoading').style.display = 'block';

            try {
                const response = await fetch('<?php echo API_URL; ?>password-reset.php?action=verify-token', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        token: token
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Email verified successfully! Redirecting to password reset...', 'success');
                    setTimeout(() => {
                        window.location.href = '<?php echo BASE_URL; ?>reset-password.php';
                    }, 2000);
                } else {
                    setEmailCodeError(result.message || 'Verification failed.');
                    document.getElementById('emailVerifyBtn').disabled = false;
                    document.getElementById('emailLoading').style.display = 'none';
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('An error occurred. Please try again.', 'danger');
                document.getElementById('emailVerifyBtn').disabled = false;
                    document.getElementById('emailLoading').style.display = 'none';
            }
        }

        // OTP verification
        document.getElementById('otpVerifyBtn').addEventListener('click', () => {
            const otp = Array.from(document.querySelectorAll('.otp-input'))
                .map(input => input.value)
                .join('');
            clearOtpError();

            // OTP must be exactly 6 digits
            if (otp.length !== 6) {
                setOtpError('Please enter all 6 digits.');
                return;
            }

            verifyOTP(otp);
        });

        async function verifyOTP(otp) {
            document.getElementById('otpVerifyBtn').disabled = true;
            document.getElementById('otpLoading').style.display = 'block';

            try {
                const response = await fetch('<?php echo API_URL; ?>password-reset.php?action=verify-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        otp: otp,
                        user_identifier: identifier
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('✅ OTP verified successfully! Redirecting to password reset...', 'success');
                    setTimeout(() => {
                        window.location.href = '<?php echo BASE_URL; ?>reset-password.php';
                    }, 2000);
                } else {
                    setOtpError(result.message || 'OTP verification failed.');
                    if (result.data && Number.isFinite(Number(result.data.attempts_remaining))) {
                        const attemptsRemaining = Number(result.data.attempts_remaining);
                        updateAttemptsDisplay(attemptsRemaining);
                    }
                    // Clear inputs
                    document.querySelectorAll('.otp-input').forEach(input => input.value = '');
                    document.querySelectorAll('.otp-input')[0].focus();
                    document.getElementById('otpVerifyBtn').disabled = false;
                    document.getElementById('otpLoading').style.display = 'none';
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('An error occurred. Please try again.', 'danger');
                document.getElementById('otpVerifyBtn').disabled = false;
                document.getElementById('otpLoading').style.display = 'none';
            }
        }

        function updateAttemptsDisplay(attempts) {
            const attemptsInfo = document.getElementById('attemptsInfo');
            if (attemptsInfo && attempts !== undefined && attempts !== null) {
                if (attempts <= 0) {
                    attemptsInfo.innerHTML = '❌ No attempts remaining. Please request a new code.';
                    attemptsInfo.style.color = '#dc3545';
                } else if (attempts === 1) {
                    attemptsInfo.innerHTML = '⚠️ 1 attempt remaining';
                    attemptsInfo.style.color = '#ff6b6b';
                } else {
                    attemptsInfo.innerHTML = `📱 ${attempts} attempts remaining`;
                    attemptsInfo.style.color = '#ff9800';
                }
            }
        }

        // Resend OTP
        let resendCount = 0;
        let resendTimer = 0;

        document.getElementById('resendBtn').addEventListener('click', async (e) => {
            e.preventDefault();

            if (resendTimer > 0) {
                showAlert('Please wait before requesting another code', 'danger');
                return;
            }

            if (resendCount >= 3) {
                showAlert('Maximum resend attempts reached. Please try again later.', 'danger');
                return;
            }

            try {
                const response = await fetch('<?php echo API_URL; ?>password-reset.php?action=resend-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        user_identifier: identifier
                    })
                });

                const result = await response.json();

                if (result.success) {
                    resendCount++;
                    resendTimer = 60;
                    updateResendTimer();
                    showAlert('New OTP sent! Check your phone.', 'success');
                } else {
                    showAlert(result.message || 'Failed to resend OTP', 'danger');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('An error occurred. Please try again.', 'danger');
            }
        });

        function updateResendTimer() {
            const timerDiv = document.getElementById('resendTimer');
            const resendBtn = document.getElementById('resendBtn');

            if (resendTimer > 0) {
                timerDiv.textContent = 'Resend available in ' + resendTimer + 's';
                resendBtn.disabled = true;
                resendTimer--;
                setTimeout(updateResendTimer, 1000);
            } else {
                timerDiv.textContent = '';
                resendBtn.disabled = false;
            }
        }
    </script>
</body>
</html>
