<?php
/**
 * Database Connection Test Script
 * This script tests the database connection and generates a proper password hash
 */

// Allow direct access for testing
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';

echo "<h2>Database Configuration Test</h2>";
echo "<pre>";

// Display configuration
echo "=== Database Configuration ===\n";
echo "Host: " . DB_HOST . "\n";
echo "Database: " . DB_NAME . "\n";
echo "User: " . DB_USER . "\n";
echo "Password: " . (DB_PASS ? "***" : "(empty)") . "\n";
echo "Charset: " . DB_CHARSET . "\n\n";

// Test database connection
echo "=== Testing Database Connection ===\n";
try {
    $db = Database::getInstance();
    echo "✓ Database connection successful!\n\n";
    
    // Check if database exists
    echo "=== Checking Database ===\n";
    $conn = $db->getConnection();
    $stmt = $conn->query("SELECT DATABASE() as db_name");
    $result = $stmt->fetch();
    echo "Current database: " . ($result['db_name'] ?: 'None') . "\n\n";
    
    // Check if tables exist
    echo "=== Checking Tables ===\n";
    $tables = ['users', 'residents', 'households', 'certificate_requests', 'blotters', 'complaints', 'announcements'];
    foreach ($tables as $table) {
        try {
            $stmt = $conn->query("SHOW TABLES LIKE '$table'");
            $exists = $stmt->rowCount() > 0;
            echo ($exists ? "✓" : "✗") . " Table '$table': " . ($exists ? "EXISTS" : "MISSING") . "\n";
        } catch (Exception $e) {
            echo "✗ Table '$table': ERROR - " . $e->getMessage() . "\n";
        }
    }
    echo "\n";
    
    // Check if admin user exists
    echo "=== Checking Admin User ===\n";
    try {
        $user = $db->fetchOne("SELECT id, username, email, role, status FROM users WHERE username = 'admin'");
        if ($user) {
            echo "✓ Admin user found:\n";
            echo "  ID: " . $user['id'] . "\n";
            echo "  Username: " . $user['username'] . "\n";
            echo "  Email: " . $user['email'] . "\n";
            echo "  Role: " . $user['role'] . "\n";
            echo "  Status: " . $user['status'] . "\n";
            
            // Test password hash
            $testPassword = 'admin123';
            $stmt = $conn->query("SELECT password FROM users WHERE username = 'admin'");
            $pwdResult = $stmt->fetch();
            $storedHash = $pwdResult['password'];
            
            echo "\n  Password Hash Test:\n";
            echo "  Stored hash: " . substr($storedHash, 0, 20) . "...\n";
            
            if (password_verify($testPassword, $storedHash)) {
                echo "  ✓ Password 'admin123' VERIFIES correctly!\n";
            } else {
                echo "  ✗ Password 'admin123' DOES NOT verify!\n";
                echo "  ⚠ You need to update the password hash!\n\n";
                
                // Generate new hash
                $newHash = password_hash($testPassword, PASSWORD_DEFAULT);
                echo "  === Generated New Password Hash ===\n";
                echo "  Use this SQL to update:\n";
                echo "  UPDATE users SET password = '$newHash' WHERE username = 'admin';\n\n";
            }
        } else {
            echo "✗ Admin user NOT FOUND!\n";
            echo "  You need to run the seeds.sql file to create the admin user.\n\n";
        }
    } catch (Exception $e) {
        echo "✗ Error checking admin user: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    echo "=== Password Hash Generator ===\n";
    $testPassword = 'admin123';
    $newHash = password_hash($testPassword, PASSWORD_DEFAULT);
    echo "Password: $testPassword\n";
    echo "Hash: $newHash\n";
    echo "\nSQL to update password:\n";
    echo "UPDATE users SET password = '$newHash' WHERE username = 'admin';\n";
    
} catch (PDOException $e) {
    echo "✗ Database connection FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    echo "=== Troubleshooting ===\n";
    echo "1. Make sure MySQL/MariaDB is running in XAMPP\n";
    echo "2. Check if the database '" . DB_NAME . "' exists\n";
    echo "3. Verify the database credentials in config/constants.php\n";
    echo "4. Create the database if it doesn't exist:\n";
    echo "   CREATE DATABASE " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
