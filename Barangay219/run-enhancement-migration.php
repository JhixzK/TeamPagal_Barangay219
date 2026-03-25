<?php
/**
 * Run Enhancement Migration
 * Safely adds new columns and tables - skips if already exist
 */
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance();
$conn = $db->getConnection();
$errors = [];
$success = [];

try {
    // Check and add application_ref to certificate_requests
    $cols = $conn->query("SHOW COLUMNS FROM certificate_requests LIKE 'application_ref'")->fetchAll();
    if (empty($cols)) {
        $conn->exec("ALTER TABLE certificate_requests ADD COLUMN application_ref VARCHAR(50) NULL UNIQUE AFTER id");
        $conn->exec("UPDATE certificate_requests SET application_ref = CONCAT('APP-', id, '-', YEAR(created_at)) WHERE application_ref IS NULL");
        $conn->exec("ALTER TABLE certificate_requests MODIFY application_ref VARCHAR(50) NOT NULL");
        $success[] = "Added application_ref to certificate_requests";
    }

    $cols = $conn->query("SHOW COLUMNS FROM certificate_requests LIKE 'control_number'")->fetchAll();
    if (empty($cols)) {
        $conn->exec("ALTER TABLE certificate_requests ADD COLUMN control_number VARCHAR(50) NULL AFTER issued_date");
        $success[] = "Added control_number to certificate_requests";
    }

    $cols = $conn->query("SHOW COLUMNS FROM certificate_requests LIKE 'remarks'")->fetchAll();
    if (empty($cols)) {
        $conn->exec("ALTER TABLE certificate_requests ADD COLUMN remarks TEXT NULL AFTER purpose");
        $success[] = "Added remarks to certificate_requests";
    }

    // Add certificate_good_moral to enum
    try {
        $conn->exec("ALTER TABLE certificate_requests MODIFY certificate_type ENUM('barangay_clearance','certificate_indigency','certificate_residency','certificate_good_moral','transfer_request') NOT NULL");
        $success[] = "Updated certificate_type enum";
    } catch (Exception $e) {
        // May already have good_moral
    }

    // Complaints - resident_id, remarks
    $cols = $conn->query("SHOW COLUMNS FROM complaints LIKE 'resident_id'")->fetchAll();
    if (empty($cols)) {
        $conn->exec("ALTER TABLE complaints ADD COLUMN resident_id INT(11) NULL AFTER complainant_name");
        $success[] = "Added resident_id to complaints";
    }
    $cols = $conn->query("SHOW COLUMNS FROM complaints LIKE 'remarks'")->fetchAll();
    if (empty($cols)) {
        $conn->exec("ALTER TABLE complaints ADD COLUMN remarks TEXT NULL AFTER resolution_date");
        $success[] = "Added remarks to complaints";
    }

    // Announcements - archived status
    try {
        $conn->exec("ALTER TABLE announcements MODIFY status ENUM('active','inactive','expired','archived') DEFAULT 'active'");
        $success[] = "Updated announcements status enum";
    } catch (Exception $e) {}

    // activity_logs table
    $tables = $conn->query("SHOW TABLES LIKE 'activity_logs'")->fetchAll();
    if (empty($tables)) {
        $conn->exec("CREATE TABLE activity_logs (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            action VARCHAR(100) NOT NULL,
            module VARCHAR(50) NOT NULL,
            entity_id INT(11) DEFAULT NULL,
            details JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_module (module),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $success[] = "Created activity_logs table";
    }

    // certificates_issued table
    $tables = $conn->query("SHOW TABLES LIKE 'certificates_issued'")->fetchAll();
    if (empty($tables)) {
        $conn->exec("CREATE TABLE certificates_issued (
            id INT(11) NOT NULL AUTO_INCREMENT,
            certificate_request_id INT(11) NOT NULL,
            control_number VARCHAR(50) NOT NULL UNIQUE,
            issued_to INT(11) NOT NULL,
            issued_by INT(11) NOT NULL,
            issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_cert_request (certificate_request_id),
            KEY idx_control_number (control_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $success[] = "Created certificates_issued table";
    }

} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

header('Content-Type: text/html; charset=utf-8');
echo "<h2>E-Barangay Enhancement Migration</h2>";
if (!empty($success)) {
    echo "<p style='color:green'><strong>Success:</strong></p><ul>";
    foreach ($success as $s) echo "<li>$s</li>";
    echo "</ul>";
}
if (!empty($errors)) {
    echo "<p style='color:red'><strong>Errors:</strong></p><ul>";
    foreach ($errors as $e) echo "<li>$e</li>";
    echo "</ul>";
}
if (empty($success) && empty($errors)) {
    echo "<p>Migration already applied (all columns/tables exist).</p>";
}
echo "<p><a href='public/dashboard.php'>Go to Dashboard</a></p>";
