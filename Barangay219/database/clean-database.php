<?php
/**
 * Clean Database Script
 * Truncates all tables and optionally re-seeds the admin user.
 * Run: php database/clean-database.php
 * Or visit: /Barangay219/database/clean-database.php (via web)
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// Get all tables in the database
$tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

if (empty($tables)) {
    echo "No tables found in database.\n";
    exit(0);
}

echo "Database: " . DB_NAME . "\n";
echo "Tables to clean: " . count($tables) . "\n\n";

try {
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0");

    foreach ($tables as $table) {
        $conn->exec("TRUNCATE TABLE `" . $table . "`");
        echo "  Truncated: $table\n";
    }

    $conn->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Re-seed admin user so you can still log in
    $adminHash = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->exec("INSERT INTO users (username, password, email, role, status) VALUES 
        ('admin', " . $conn->quote($adminHash) . ", 'admin@barangay219.gov.ph', 'barangay_captain', 'active')
        ON DUPLICATE KEY UPDATE password = VALUES(password)");

    // Re-seed role_permissions (essential permissions for admin)
    if (in_array('role_permissions', $tables)) {
        $perms = [
            "('barangay_captain','dashboard',1,1,1,1)","('barangay_captain','applications',1,1,1,1)","('barangay_captain','resident_applications',1,1,1,1)",
            "('barangay_captain','residents',1,1,1,1)","('barangay_captain','households',1,1,1,1)","('barangay_captain','certificates',1,1,1,1)",
            "('barangay_captain','blotters',1,1,1,1)","('barangay_captain','complaints',1,1,1,1)","('barangay_captain','announcements',1,1,1,1)",
            "('barangay_captain','reports',1,1,1,1)","('barangay_captain','officials',1,1,1,1)","('barangay_captain','profile',1,1,1,1)",
            "('super_admin','dashboard',1,1,1,1)","('super_admin','residents',1,1,1,1)","('super_admin','households',1,1,1,1)",
            "('super_admin','certificates',1,1,1,1)","('super_admin','applications',1,1,1,1)","('super_admin','officials',1,1,1,1)",
        ];
        $conn->exec("INSERT INTO role_permissions (role, module, can_access, can_create, can_edit, can_delete) VALUES " . implode(',', $perms));
        echo "\n  Re-seeded role_permissions\n";
    }

    echo "\n  Re-seeded admin user (username: admin, password: admin123)\n";
    echo "\nDatabase cleaned successfully.\n";

} catch (Exception $e) {
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "\nError: " . $e->getMessage() . "\n";
    exit(1);
}
