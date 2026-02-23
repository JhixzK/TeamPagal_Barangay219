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
        .reset-container {
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
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 5px rgba(102, 126, 234, 0.3);
        }
        .password-strength {
            margin-top: 8px;
            padding: 10px;
            border-radius: 5px;
            font-size: 12px;
            display: none;
        }
        .strength-weak {
            background: #fee;
            color: #c00;
        }
        .strength-fair {
            background: #fef3cd;
            color: #856404;
        }
        .strength-good {
            background: #d4edda;
            color: #155724;
        }
        .strength-strong {
            background: #d1ecf1;
            color: #0c5460;
        }
        .requirements {
            margin-top: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            font-size: 13px;
        }
        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            color: #666;
        }
        .requirement.met {
            color: #28a745;
        }
        .requirement.unmet {
            color: #dc3545;
        }
        .requirement-icon {
            margin-right: 8px;
            font-size: 12px;
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
            margin-top: 20px;
        }
        .submit-btn:hover {
            background: #5568d3;
        }
        .submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
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
        .alert-warning {
            background: #fef3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .loading {
            display: none;
            text-align: center;
            color: #667eea;
            margin-top: 15px;
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
    <div class="reset-container">
        <div class="header">
            <h1>🛡️ Set New Password</h1>
            <p>Create a strong password for your account</p>
        </div>

        <div id="alertContainer"></div>

        <form id="resetPasswordForm">
            <div class="form-group">
                <label for="newPassword">New Password <span style="color: #c00;">*</span></label>
                <input 
                    type="password" 
                    id="newPassword" 
                    name="new_password" 
                    placeholder="Enter your new password" 
                    required
                    autocomplete="off"
                >
                <div class="password-strength" id="passwordStrength"></div>
            </div>

            <div class="requirements" id="passwordRequirements" style="display: none;">
                <div class="requirement unmet" id="req-length">
                    <span class="requirement-icon">✗</span>
                    At least 8 characters
                </div>
                <div class="requirement unmet" id="req-uppercase">
                    <span class="requirement-icon">✗</span>
                    At least one uppercase letter (A-Z)
                </div>
                <div class="requirement unmet" id="req-lowercase">
                    <span class="requirement-icon">✗</span>
                    At least one lowercase letter (a-z)
                </div>
                <div class="requirement unmet" id="req-number">
                    <span class="requirement-icon">✗</span>
                    At least one number (0-9)
                </div>
                <div class="requirement unmet" id="req-special">
                    <span class="requirement-icon">✗</span>
                    At least one special character (!@#$%^&*)
                </div>
            </div>

            <div class="form-group">
                <label for="confirmPassword">Confirm Password <span style="color: #c00;">*</span></label>
                <input 
                    type="password" 
                    id="confirmPassword" 
                    name="confirm_password" 
                    placeholder="Confirm your new password" 
                    required
                    autocomplete="off"
                >
            </div>

            <div id="passwordMatchAlert" class="alert alert-danger" style="display: none;">
                Passwords do not match
            </div>

            <button type="submit" class="submit-btn" id="submitBtn">
                <span>Reset Password</span>
            </button>

            <div class="loading" id="loadingSpinner">
                <div class="spinner"></div>
                <span>Resetting password...</span>
            </div>
        </form>

        <div class="back-link">
            <a href="<?php echo BASE_URL; ?>login.php">← Back to Login</a>
        </div>
    </div>

    <script>
        const newPasswordInput = document.getElementById('newPassword');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const passwordStrength = document.getElementById('passwordStrength');
        const passwordRequirements = document.getElementById('passwordRequirements');

        // Real-time requirement checking
        newPasswordInput.addEventListener('input', () => {
            const password = newPasswordInput.value;
            passwordRequirements.style.display = password.length > 0 ? 'block' : 'none';

            // Check each requirement
            checkRequirement('length', password.length >= 8);
            checkRequirement('uppercase', /[A-Z]/.test(password));
            checkRequirement('lowercase', /[a-z]/.test(password));
            checkRequirement('number', /\d/.test(password));
            checkRequirement('special', /[!@#$%^&*()_+=\[\]{};:'"",.<>?\\|`~\-]/.test(password));

            // Update password strength indicator
            updatePasswordStrength(password);

            // Check if passwords match
            checkPasswordsMatch();
        });

        confirmPasswordInput.addEventListener('input', checkPasswordsMatch);

        function checkRequirement(req, met) {
            const reqElement = document.getElementById('req-' + req);
            if (met) {
                reqElement.classList.remove('unmet');
                reqElement.classList.add('met');
                reqElement.querySelector('.requirement-icon').textContent = '✓';
            } else {
                reqElement.classList.remove('met');
                reqElement.classList.add('unmet');
                reqElement.querySelector('.requirement-icon').textContent = '✗';
            }
        }

        function updatePasswordStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[!@#$%^&*()_+=\[\]{};:'"",.<>?\\|`~\-]/.test(password)) strength++;

            const strengthDiv = passwordStrength;
            strengthDiv.style.display = password.length > 0 ? 'block' : 'none';

            if (strength < 2) {
                strengthDiv.className = 'password-strength strength-weak';
                strengthDiv.textContent = '⚠️ Weak password';
            } else if (strength < 3) {
                strengthDiv.className = 'password-strength strength-fair';
                strengthDiv.textContent = '⚠️ Fair password';
            } else if (strength < 5) {
                strengthDiv.className = 'password-strength strength-good';
                strengthDiv.textContent = '✓ Good password';
            } else {
                strengthDiv.className = 'password-strength strength-strong';
                strengthDiv.textContent = '✓✓ Strong password';
            }
        }

        function checkPasswordsMatch() {
            const passwordMatchAlert = document.getElementById('passwordMatchAlert');
            if (newPasswordInput.value && confirmPasswordInput.value) {
                if (newPasswordInput.value === confirmPasswordInput.value) {
                    passwordMatchAlert.style.display = 'none';
                } else {
                    passwordMatchAlert.style.display = 'block';
                }
            } else {
                passwordMatchAlert.style.display = 'none';
            }
        }

        function allRequirementsMet() {
            const password = newPasswordInput.value;
            return password.length >= 8 &&
                   /[A-Z]/.test(password) &&
                   /[a-z]/.test(password) &&
                   /\d/.test(password) &&
                   /[!@#$%^&*()_+=\[\]{};:'"",.<>?\\|`~\-]/.test(password);
        }

        // Form submission
        document.getElementById('resetPasswordForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            // Validation
            if (!newPassword || !confirmPassword) {
                showAlert('Both password fields are required', 'danger');
                return;
            }

            if (newPassword !== confirmPassword) {
                showAlert('Passwords do not match', 'danger');
                return;
            }

            if (!allRequirementsMet()) {
                showAlert('Password does not meet all requirements', 'danger');
                return;
            }

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            document.getElementById('loadingSpinner').style.display = 'block';

            try {
                const response = await fetch('<?php echo API_URL; ?>password-reset.php?action=reset-password', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        new_password: newPassword,
                        confirm_password: confirmPassword
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Password reset successfully! Redirecting to login page...', 'success');
                    setTimeout(() => {
                        window.location.href = '<?php echo BASE_URL; ?>login.php?message=password_reset_success';
                    }, 2000);
                } else {
                    showAlert(result.message || 'An error occurred', 'danger');
                    submitBtn.disabled = false;
                    document.getElementById('loadingSpinner').style.display = 'none';
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('An error occurred. Please try again.', 'danger');
                submitBtn.disabled = false;
                document.getElementById('loadingSpinner').style.display = 'none';
            }
        });

        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            alertContainer.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            window.scrollTo(0, 0);
        }
    </script>
</body>
</html>
