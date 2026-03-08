<?php
/**
 * Create Resident User Account
 * Run this file once in your browser to create a test resident account
 * Based on the registration system requirements
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/config/database.php';

echo "<h2>Creating Resident User Account...</h2>";

try {
    $db = Database::getInstance();
    
    // First, insert or update the resident record with actual database columns
    $residentSql = "INSERT INTO residents (
        first_name, middle_name, last_name, suffix,
        birth_date, place_of_birth, gender, civil_status, citizenship,
        house_number, street, purok_sitio, address,
        contact_number, email,
        emergency_contact_name, emergency_contact_number, emergency_contact_relationship,
        educational_attainment, employment_status, occupation,
        length_of_residency_years,
        is_senior_citizen, is_pwd, is_solo_parent, is_ip_member, is_4ps_beneficiary,
        record_status, status, created_at
    ) VALUES (
        'Juan', 'Santos', 'Dela Cruz', '',
        '1995-06-14', 'Tondo, Manila', 'male', 'single', 'Filipino',
        'Blk 12 Lot 8', 'Isla Puting Bato', 'Purok 3', 'Blk 12 Lot 8, Isla Puting Bato, Purok 3, Barangay 219, Tondo, Manila, Metro Manila',
        '09171234567', 'juandelacruz@email.com',
        'Maria Dela Cruz', '09187654321', 'mother',
        'College Graduate', 'employed', 'Administrative Assistant',
        12,
        0, 0, 0, 0, 0,
        'active', 'active', NOW()
    ) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)";
    
    $db->query($residentSql);
    $residentId = $db->getConnection()->lastInsertId();
    
    if (!$residentId) {
        // Try to get existing resident
        $existingResident = $db->fetchOne("SELECT id FROM residents WHERE contact_number = '09171234567'");
        $residentId = $existingResident['id'] ?? null;
    }
    
    if (!$residentId) {
        throw new Exception("Failed to create or find resident record");
    }
    
    echo "<p>✓ Resident record created/found: ID = {$residentId}</p>";
    
    // Generate password hash
    $password = 'resident123';
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Create or update user account
    $userSql = "INSERT INTO users (
        username, password, email, role, status, resident_id, created_at
    ) VALUES (
        'resident', ?, 'juandelacruz@email.com', 'resident', 'active', ?, NOW()
    ) ON DUPLICATE KEY UPDATE 
        password = VALUES(password),
        resident_id = VALUES(resident_id),
        status = 'active'";
    
    $db->query($userSql, [$passwordHash, $residentId]);
    
    echo "<p>✓ User account created/updated successfully!</p>";
    
    // Display success message with account details
    echo "<div style='background: #d4edda; padding: 20px; border: 1px solid #28a745; border-radius: 5px; margin-top: 20px;'>";
    echo "<h3 style='color: #155724; margin-top: 0;'>✅ Resident Account Created!</h3>";
    echo "<p style='margin: 5px 0;'><strong>Name:</strong> Juan Santos Dela Cruz</p>";
    echo "<p style='margin: 5px 0;'><strong>Username:</strong> resident</p>";
    echo "<p style='margin: 5px 0;'><strong>Password:</strong> resident123</p>";
    echo "<p style='margin: 5px 0;'><strong>Role:</strong> resident</p>";
    echo "<p style='margin: 5px 0;'><strong>Resident ID:</strong> {$residentId}</p>";
    echo "<p style='margin: 5px 0;'><strong>Mobile:</strong> 0917 123 4567</p>";
    echo "<p style='margin: 5px 0;'><strong>Email:</strong> juandelacruz@email.com</p>";
    echo "<p style='margin: 5px 0;'><strong>Address:</strong> Blk 12 Lot 8, Isla Puting Bato, Purok 3, Barangay 219, Tondo, Manila</p>";
    echo "</div>";
    
    echo "<div style='background: #cfe2ff; padding: 15px; border: 1px solid #0d6efd; border-radius: 5px; margin-top: 20px;'>";
    echo "<h4 style='color: #084298; margin-top: 0;'>📋 Account Details Match Registration System</h4>";
    echo "<ul style='color: #052c65; margin: 0;'>";
    echo "<li>All required registration fields included</li>";
    echo "<li>Emergency contact information: Maria Dela Cruz (mother)</li>";
    echo "<li>Employment: Administrative Assistant (employed)</li>";
    echo "<li>Household info: Son of Pedro Dela Cruz, 5 members</li>";
    echo "<li>Residency: 12 years in Barangay 219</li>";
    echo "<li>Verification status: Active (approved)</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='margin-top: 20px;'>";
    echo "<p><a href='public/login.php' style='display: inline-block; padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Go to Login Page</a>";
    echo "<a href='public/resident_dashboard.php' style='display: inline-block; padding: 10px 20px; background: #198754; color: white; text-decoration: none; border-radius: 5px;'>Go to Resident Dashboard</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 20px; border: 1px solid #dc3545; border-radius: 5px;'>";
    echo "<h3 style='color: #721c24; margin-top: 0;'>❌ Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p style='font-size: 0.9em; color: #721c24;'>Make sure the database tables exist and have the correct columns.</p>";
    echo "</div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Create Resident User</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
    </style>
</head>
<body>
</body>
</html>
