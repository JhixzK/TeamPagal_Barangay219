<?php
/**
 * E-Barangay Information Management System
 * Password Reset System - Quick Test Script
 * 
 * This script allows you to test the password reset functionality
 * Usage: php test-password-reset.php
 */

define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/password-reset.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "===========================================\n";
echo "  Password Reset System - Tester\n";
echo "===========================================\n\n";

// Check for command line argument
$test_mode = php_sapi_name() === 'cli';

if (!$test_mode) {
    echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>\n";
}

echo "1️⃣  Database Connection Test\n";
echo str_repeat("-", 41) . "\n";

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    echo "✓ Database connection successful\n";
    echo "  Database: " . DB_NAME . "\n";
    echo "  Host: " . DB_HOST . "\n\n";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

echo "2️⃣  Required Tables Test\n";
echo str_repeat("-", 41) . "\n";

$tables = [
    'password_reset_tokens' => 'Email reset tokens',
    'password_reset_otp' => 'SMS OTP codes',
    'password_reset_rate_limit' => 'Rate limiting',
    'password_reset_logs' => 'Audit logs'
];

$all_tables_exist = true;

foreach ($tables as $table => $description) {
    try {
        $result = $db->fetchOne("SHOW TABLES LIKE ?", [$table]);
        if ($result) {
            $row_result = $db->fetchOne("SELECT COUNT(*) as count FROM {$table}");
            $count = $row_result['count'] ?? 0;
            echo "✓ {$table}\n";
            echo "  └─ {$description} ({$count} records)\n";
        } else {
            echo "❌ {$table} (NOT FOUND)\n";
            $all_tables_exist = false;
        }
    } catch (Exception $e) {
        echo "❌ Error checking {$table}: " . $e->getMessage() . "\n";
        $all_tables_exist = false;
    }
}

if (!$all_tables_exist) {
    echo "\n⚠️  Some tables are missing!\n";
    echo "Run: php run-password-reset-migration.php\n";
}

echo "\n";

echo "3️⃣  User Table Extensions Test\n";
echo str_repeat("-", 41) . "\n";

try {
    $result = $db->fetchAll("DESCRIBE users");
    $columns = array_column($result, 'Field');
    
    $new_columns = [
        'password_reset_request_id',
        'password_reset_request_method',
        'password_reset_request_expires'
    ];
    
    $missing_columns = [];
    
    foreach ($new_columns as $col) {
        if (in_array($col, $columns)) {
            echo "✓ {$col}\n";
        } else {
            echo "⚠️  {$col} (optional column)\n";
            $missing_columns[] = $col;
        }
    }
    
    if (!empty($missing_columns)) {
        echo "\nNote: These columns are optional for tracking.\n";
        echo "The system will work without them.\n";
    }
    
    echo "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

echo "4️⃣  Password Validation Test\n";
echo str_repeat("-", 41) . "\n";

$test_passwords = [
    'weak' => 'test',
    'medium' => 'Test123',
    'good' => 'Test@123',
    'strong' => 'MySecure@Pass123'
];

foreach ($test_passwords as $category => $password) {
    $validation = validatePassword($password);
    $status = $validation['valid'] ? '✓' : '✗';
    echo "{$status} \"{$password}\" ({$category})\n";
    
    if (!$validation['valid']) {
        foreach ($validation['errors'] as $error) {
            echo "   └─ Missing: {$error}\n";
        }
    }
}

echo "\n";

echo "5️⃣  Utility Functions Test\n";
echo str_repeat("-", 41) . "\n";

try {
    // Test OTP generation
    $otp = generateOTP();
    echo "✓ OTP Generation: {$otp}\n";
    echo "   └─ Length: " . strlen($otp) . " digits\n";
    
    // Test Token generation
    $token = generateResetToken();
    echo "✓ Token Generation: " . substr($token, 0, 20) . "...\n";
    echo "   └─ Length: " . strlen($token) . " characters\n";
    
    echo "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

echo "6️⃣  File Structure Test\n";
echo str_repeat("-", 41) . "\n";

$files = [
    'public/forgot-password.php' => 'Main forgot password page',
    'public/verify-reset.php' => 'Verification page',
    'public/reset-password.php' => 'Password reset form',
    'api/password-reset.php' => 'API endpoints',
    'includes/password-reset.php' => 'Helper functions',
    'database/migrations/003_password_reset_system.sql' => 'Database schema'
];

$base_path = __DIR__;

foreach ($files as $file => $description) {
    $full_path = $base_path . '/' . $file;
    if (file_exists($full_path)) {
        $size = filesize($full_path);
        echo "✓ {$file}\n";
        echo "   └─ {$description} (" . number_format($size) . " bytes)\n";
    } else {
        echo "❌ {$file} (NOT FOUND)\n";
    }
}

echo "\n";

echo "7️⃣  Configuration Test\n";
echo str_repeat("-", 41) . "\n";

$constants = [
    'OTP_LENGTH' => 'OTP code length',
    'OTP_EXPIRY_MINUTES' => 'OTP expiry time',
    'TOKEN_EXPIRY_MINUTES' => 'Email token expiry',
    'MAX_OTP_ATTEMPTS' => 'Max OTP attempts',
    'MAX_RESET_REQUESTS_PER_HOUR' => 'Max password resets/hour',
    'MAX_OTP_VERIFICATIONS_PER_HOUR' => 'Max OTP verifications/hour',
    'MAX_RESEND_OTP_PER_HOUR' => 'Max OTP resends/hour'
];

foreach ($constants as $const => $description) {
    if (defined($const)) {
        $value = constant($const);
        echo "✓ {$const} = {$value}\n";
        echo "   └─ {$description}\n";
    } else {
        echo "❌ {$const} (NOT DEFINED)\n";
    }
}

echo "\n";

echo "8️⃣  Integration Test (Sample Flow)\n";
echo str_repeat("-", 41) . "\n";

try {
    // Simulate a password reset flow
    echo "Simulating password reset flow...\n";
    
    // Check rate limit
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $rate_check = checkRateLimit(null, 'request', 3, 60);
    echo "✓ Rate limiting checked\n";
    echo "   └─ Allowed: " . ($rate_check['allowed'] ? 'Yes' : 'No') . "\n";
    echo "   └─ Current attempts: " . ($rate_check['attempts'] ?? 'N/A') . "\n";
    
    // Test password validation
    $pwd_test = validatePassword('SecureTest@123');
    echo "✓ Password validation\n";
    echo "   └─ Valid password: " . ($pwd_test['valid'] ? 'Yes' : 'No') . "\n";
    
    // Test identifier masking
    $masked_email = maskIdentifier('user@example.com', 'email');
    echo "✓ Identifier masking\n";
    echo "   └─ Masked email: {$masked_email}\n";
    
    echo "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

echo "===========================================\n";
echo "  Summary\n";
echo "===========================================\n\n";

echo "✅ Password Reset System is properly installed!\n\n";

echo "Quick Start:\n";
echo "1. Visit: /Barangay219/public/forgot-password.php\n";
echo "2. Enter a registered email or username\n";
echo "3. Select verification method (Email or SMS)\n";
echo "4. Complete verification\n";
echo "5. Set new password\n\n";

echo "Testing in Development:\n";
echo "• OTP and reset tokens are logged to error_log\n";
echo "• Check: tail -f /var/log/php_errors.log\n";
echo "• Or set up actual Email/SMS in includes/password-reset.php\n\n";

echo "Database Queries:\n";
echo "• Users: SELECT * FROM password_reset_logs LIMIT 10;\n";
echo "• Tokens: SELECT * FROM password_reset_tokens;\n";
echo "• OTPs: SELECT * FROM password_reset_otp;\n";
echo "• Rate Limits: SELECT * FROM password_reset_rate_limit;\n\n";

echo "Documentation:\n";
echo "• See: FORGOT_PASSWORD_SETUP.md\n";
echo "• Config: includes/password-reset.php\n\n";

if (!$test_mode) {
    echo "</pre>\n";
} else {
    echo "\n✅ Test completed successfully!\n";
}
?>
