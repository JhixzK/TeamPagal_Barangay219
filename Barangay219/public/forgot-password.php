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
        .forgot-password-container {
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
        .identifier-input {
            margin-bottom: 20px;
            animation: slideIn 0.3s ease-in-out;
        }
        .identifier-input.hidden {
            display: none;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .identifier-input input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .identifier-input input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 8px rgba(102, 126, 234, 0.4);
        }
        .identifier-input input::placeholder {
            color: #999;
        }
        .identifier-input label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        .identifier-input small {
            display: block;
            margin-top: 6px;
            color: #666;
            font-size: 12px;
        }
        .method-selection {
            display: flex;
            gap: 15px;
            margin: 20px 0;
        }
        .method-btn {
            flex: 1;
            padding: 15px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        .method-btn:hover {
            border-color: #667eea;
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }
        .method-btn.active {
            border-color: #667eea;
            background: #667eea;
            color: white;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }
        .method-btn span {
            font-weight: 600;
            display: block;
            font-size: 14px;
        }
        .submit-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        .submit-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        .submit-btn:active:not(:disabled) {
            transform: translateY(0px);
        }
        .submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-block;
        }
        .back-link a:hover {
            color: #5568d3;
            transform: translateX(-3px);
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 14px;
            animation: slideIn 0.3s ease-in-out;
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
        .alert small {
            display: block;
            margin-top: 8px;
            font-size: 12px;
            opacity: 0.9;
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
            vertical-align: middle;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 600px) {
            .forgot-password-container {
                padding: 25px;
            }
            .header h1 {
                font-size: 24px;
            }
            .header p {
                font-size: 13px;
            }
            .method-selection {
                gap: 10px;
            }
            .method-btn {
                padding: 12px;
            }
            .method-btn span {
                font-size: 13px;
            }
            .identifier-input input {
                padding: 10px;
                font-size: 16px; /* Prevents zoom on iOS */
            }
            .submit-btn {
                padding: 11px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-password-container">
        <div class="header">
            <h1>Forgot Password</h1>
            <p>Reset your password using your registered email or phone number</p>
        </div>

        <div id="alertContainer"></div>

        <!-- Step 1: Choose Verification Method -->
        <form id="forgotPasswordForm" class="form-section">
            <div>
                <strong class="d-block mb-3">How would you like to verify?</strong>
                <div class="method-selection">
                    <button type="button" class="method-btn active" data-method="email" id="emailMethodBtn" onclick="selectMethod('email'); return false;">
                        <span>Email</span>
                        <small style="font-weight: normal; font-size: 12px; margin-top: 5px;">Get a link via email</small>
                    </button>
                    <button type="button" class="method-btn" data-method="sms" id="smsMethodBtn" onclick="selectMethod('sms'); return false;">
                        <span>SMS</span>
                        <small style="font-weight: normal; font-size: 12px; margin-top: 5px;">Get code via text</small>
                    </button>
                </div>
                <input type="hidden" id="selectedMethod" name="method" value="email">
            </div>

            <!-- Email Input Section -->
            <div id="emailSection" class="identifier-input">
                <label for="userEmail"><strong>Registered Email Address</strong></label>
                <input 
                    type="email" 
                    id="userEmail" 
                    name="email" 
                    placeholder="Enter your registered email address" 
                    autocomplete="off"
                >
                <small class="text-muted d-block mt-2">We'll send a password reset link to this email</small>
            </div>

            <!-- Mobile Input Section -->
            <div id="mobileSection" class="identifier-input" style="display: none;">
                <label for="userMobile"><strong>Registered Mobile Number</strong></label>
                <input 
                    type="text" 
                    id="userMobile" 
                    name="mobile" 
                    placeholder="Enter your mobile number" 
                    autocomplete="off"
                    required
                    maxlength="13"
                    inputmode="numeric"
                >
                <small class="text-muted d-block mt-2">
                    Enter 11 digits starting with 09 (e.g., 09123456789)
                    <br><span id="mobileDigitCount" style="color: #667eea; font-weight: 500;"></span>
                </small>
            </div>

            <button type="submit" class="submit-btn" id="submitBtn">
                <span>Send Reset Code</span>
            </button>

            <div class="loading" id="loadingSpinner">
                <div class="spinner"></div>
                <span>Sending code...</span>
            </div>
        </form>

        <div class="back-link">
            <a href="<?php echo BASE_URL; ?>login.php">← Back to Login</a>
        </div>
    </div>

    <script>
        // Simple method selection
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

        // Mobile input - digits only, must start with 09
        document.getElementById('userMobile').addEventListener('input', function(e) {
            let value = e.target.value;
            
            // Remove everything except digits
            let cleaned = value.replace(/\D/g, '');
            
            // Limit to 11 digits max (09XXXXXXXXX)
            if (cleaned.length > 11) {
                cleaned = cleaned.slice(0, 11);
            }
            
            e.target.value = cleaned;
            
            // Update counter
            const counter = document.getElementById('mobileDigitCount');
            const digitCount = cleaned.length;
            
            if (digitCount > 0) {
                if (digitCount < 11) {
                    counter.textContent = `${digitCount}/11 digits`;
                    counter.style.color = '#ff6b6b';
                } else if (digitCount === 11) {
                    // Check if it starts with 09
                    if (cleaned.startsWith('09')) {
                        counter.textContent = 'Valid ✓';
                        counter.style.color = '#51cf66';
                    } else {
                        counter.textContent = 'Must start with 09';
                        counter.style.color = '#ff6b6b';
                    }
                }
            } else {
                counter.textContent = '';
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
                    alert('Please enter your email');
                    return;
                }
            } else {
                identifier = document.getElementById('userMobile').value.trim();
                if (!identifier) {
                    alert('Please enter your mobile number');
                    return;
                }
                
                // Extract digits only
                const digitsOnly = identifier.replace(/\D/g, '');
                
                // Must be exactly 11 digits
                if (digitsOnly.length !== 11) {
                    alert('Mobile number must be exactly 11 digits');
                    return;
                }
                
                // Must start with 09
                if (!digitsOnly.startsWith('09')) {
                    alert('Mobile number must start with 09 (e.g., 09123456789)');
                    return;
                }
                
                // Auto-convert 09 format to +639 format
                identifier = '+63' + digitsOnly.substring(1);
            }

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            document.getElementById('loadingSpinner').style.display = 'block';

            try {
                const response = await fetch('<?php echo API_URL; ?>password-reset.php?action=initiate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        identifier: identifier,
                        method: method
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('✅ Code sent! Redirecting...');
                    setTimeout(() => {
                        window.location.href = '<?php echo BASE_URL; ?>verify-reset.php?method=' + method + '&identifier=' + encodeURIComponent(identifier);
                    }, 1000);
                } else {
                    alert('Error: ' + (result.message || 'Failed to send code'));
                    submitBtn.disabled = false;
                    document.getElementById('loadingSpinner').style.display = 'none';
                }
            } catch (error) {
                alert('Error: ' + error.message);
                submitBtn.disabled = false;
                document.getElementById('loadingSpinner').style.display = 'none';
            }
        });
    </script>
</body>
</html>
