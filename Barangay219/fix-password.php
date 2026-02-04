<?php
/**
 * Password Hash Generator
 * Generates a proper bcrypt hash for the admin password
 * 
 * Access this file via browser: http://localhost/e-barangay-system/e-barangay-system/fix-password.php
 */

// Generate password hash for 'admin123'
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>Password Hash Generator</h2>";
echo "<pre>";
echo "Password: $password\n";
echo "Hash: $hash\n\n";

echo "=== SQL to Update Password ===\n";
echo "Run this SQL in phpMyAdmin or MySQL:\n\n";
echo "UPDATE users SET password = '$hash' WHERE username = 'admin';\n\n";

echo "=== Or if user doesn't exist, use this INSERT ===\n";
echo "INSERT INTO users (username, password, email, role, status) VALUES\n";
echo "('admin', '$hash', 'admin@barangay195.gov.ph', 'barangay_captain', 'active');\n";
echo "</pre>";

// Test the hash
if (password_verify($password, $hash)) {
    echo "<p style='color: green;'>✓ Hash verification successful!</p>";
} else {
    echo "<p style='color: red;'>✗ Hash verification failed!</p>";
}
?>
