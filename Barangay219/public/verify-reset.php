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
    <link href="<?php echo ASSETS_URL; ?>bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>style.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .verify-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            padding: 40px;
            max-width: 500px;
            width: 90%;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header p {
            color: #666;
            font-size: 14px;
        }
        .form-section {
            margin-bottom: 20px;
        }
        .form-section.hidden {
            display: none;
        }
        .otp-input-container {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin: 30px 0;
        }
        .otp-input {
            width: 60px;
            height: 60px;
            font-size: 28px;
            text-align: center;
            border: 2px solid #ddd;
            border-radius: 5px;
            transition: border-color 0.3s;
        }
        .otp-input:focus {
            outline: none;
            border-color: #667eea;
        }
        .code-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .code-input:focus {
            outline: none;
            border-color: #667eea;
        }
        .submit-btn {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .submit-btn:hover {
            background: #5568d3;
        }
        .submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .resend-section {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .resend-btn {
            background: none;
            border: none;
            color: #667eea;
            cursor: pointer;
            text-decoration: underline;
            font-size: 14px;
        }
        .resend-btn:hover {
            color: #5568d3;
        }
        .resend-btn:disabled {
            color: #ccc;
            cursor: not-allowed;
        }
        .timer {
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
        .alert {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 14px;
        }
        .alert-danger {
            background: #fee;
            color: #c00;
            border: 1px solid #fcc;
        }
        .alert-success {
            background: #efe;
            color: #0a0;
            border: 1px solid #cfc;
        }
        .alert-info {
            background: #eef;
            color: #009;
            border: 1px solid #ccf;
        }
        .loading {
            display: none;
            text-align: center;
            color: #667eea;
        }
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="header">
            <h1>✓ Verify Your Identity</h1>
            <p id="headerText">Enter the code sent to your email address</p>
        </div>

        <div id="alertContainer"></div>

        <!-- Email Token Verification Form -->
        <form id="emailVerifyForm" class="form-section <?php echo $method === 'sms' ? 'hidden' : ''; ?>">
            <div class="alert alert-info" id="emailInfo">
                <strong>Verification Code</strong><br>
                Check your email for a verification link or code.
            </div>

            <input 
                type="text" 
                class="code-input" 
                id="emailCode" 
                placeholder="Paste the code from your email" 
                autocomplete="off"
            >

            <button type="submit" class="submit-btn" id="emailVerifyBtn">
                <span>Verify Code</span>
            </button>

            <div class="loading" id="emailLoading" style="margin-top: 15px;">
                <div class="spinner"></div>
                <span>Verifying...</span>
            </div>
        </form>

        <!-- SMS OTP Verification Form -->
        <form id="otpVerifyForm" class="form-section <?php echo $method === 'email' ? 'hidden' : ''; ?>">
            <div class="alert alert-info" id="smsInfo">
                <strong>One-Time Password (OTP)</strong><br>
                A 6-digit code has been sent to your phone number.
                <br><span id="attemptsInfo" style="margin-top: 8px; display: block; font-size: 12px;"></span>
            </div>

            <div class="otp-input-container" id="otpInputContainer">
                <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="0">
                <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="1">
                <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="2">
                <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="3">
                <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="4">
                <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="5">
            </div>

            <button type="button" class="submit-btn" id="otpVerifyBtn">
                <span>Verify OTP</span>
            </button>

            <div class="loading" id="otpLoading" style="margin-top: 15px;">
                <div class="spinner"></div>
                <span>Verifying...</span>
            </div>

            <div class="resend-section">
                <p style="font-size: 13px; margin-bottom: 10px;">Didn't receive the code?</p>
                <button type="button" class="resend-btn" id="resendBtn">
                    <span>Resend OTP</span>
                </button>
                <div class="timer" id="resendTimer"></div>
            </div>
        </form>

        <div class="back-link">
            <a href="<?php echo BASE_URL; ?>forgot-password.php">← Change verification method</a>
        </div>
    </div>

    <script>
        const method = '<?php echo $method; ?>';
        const identifier = '<?php echo htmlspecialchars($identifier); ?>';
        const tokenParam = '<?php echo htmlspecialchars($token); ?>';

        // Auto-verify if token is provided in URL (from email link)
        if (method === 'email' && tokenParam) {
            verifyEmailToken(tokenParam);
        }

        // OTP Input handling
        const otpInputs = document.querySelectorAll('.otp-input');
        otpInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                if (e.target.value.length === 1 && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && e.target.value === '' && index > 0) {
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
                    }
                });
            });
        });

        // Email verification
        document.getElementById('emailVerifyForm').addEventListener('submit', (e) => {
            e.preventDefault();
            const code = document.getElementById('emailCode').value.trim();

            if (!code) {
                showAlert('Please enter the verification code', 'danger');
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
                    showAlert(result.message || 'Verification failed', 'danger');
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

            // OTP must be exactly 6 digits
            if (otp.length !== 6) {
                showAlert('Please enter all 6 digits', 'danger');
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
                    showAlert(result.message || 'OTP verification failed', 'danger');
                    if (result.data && result.data.attempts_remaining !== undefined) {
                        const attemptsRemaining = result.data.attempts_remaining;
                        showAlert(`⚠️ Attempts remaining: ${attemptsRemaining}`, 'warning');
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

        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            if (!alertContainer.innerHTML.includes(message)) {
                alertContainer.innerHTML += `<div class="alert alert-${type}">${message}</div>`;
            }

            // Auto-remove alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                if (alerts.length > 0) {
                    alerts[0].remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>
