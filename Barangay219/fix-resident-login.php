<?php
/**
 * Diagnose and Fix Resident Login
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/config/database.php';

echo "<h2>Diagnosing Resident Login Issue...</h2>";

try {
    $db = Database::getInstance();
    
    // Check if resident user exists
    echo "<h3>Step 1: Check if 'resident' user exists</h3>";
    $user = $db->fetchOne("SELECT * FROM users WHERE username = 'resident'");
    
    if (!$user) {
        echo "<p style='color: orange;'>❌ User 'resident' not found. Creating new user...</p>";
        
        // Create resident record first (using only required columns)
        $residentSql = "INSERT INTO residents (
            first_name, last_name,
            birth_date, gender, 
            address,
            status, created_at
        ) VALUES (
            'Juan', 'Dela Cruz',
            '1995-06-14', 'male',
            'Blk 12 Lot 8, Barangay 219, Tondo, Manila',
            'active', NOW()
        )";
        
        $db->query($residentSql);
        $residentId = $db->getConnection()->lastInsertId();
        
        echo "<p style='color: green;'>✓ Resident record created: ID = {$residentId}</p>";
        
        // Create user
        $password = 'resident123';
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $userSql = "INSERT INTO users (username, password, email, role, status, resident_id, created_at) 
                    VALUES ('resident', ?, 'juandelacruz@email.com', 'resident', 'active', ?, NOW())";
        
        $db->query($userSql, [$passwordHash, $residentId]);
        
        echo "<p style='color: green;'>✓ User 'resident' created successfully!</p>";
        
        $user = $db->fetchOne("SELECT * FROM users WHERE username = 'resident'");
    } else {
        echo "<p style='color: green;'>✓ User 'resident' found!</p>";
        echo "<pre>";
        echo "User ID: " . $user['id'] . "\n";
        echo "Username: " . $user['username'] . "\n";
        echo "Email: " . $user['email'] . "\n";
        echo "Role: " . $user['role'] . "\n";
        echo "Status: " . $user['status'] . "\n";
        echo "Resident ID: " . ($user['resident_id'] ?? 'NULL') . "\n";
        echo "</pre>";
    }
    
    // Step 2: Update password to ensure it works
    echo "<h3>Step 2: Reset password to 'resident123'</h3>";
    $newPassword = 'resident123';
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $db->query("UPDATE users SET password = ?, status = 'active' WHERE username = 'resident'", [$newHash]);
    
    echo "<p style='color: green;'>✓ Password reset successfully!</p>";
    
    // Step 3: Verify password works
    echo "<h3>Step 3: Test password verification</h3>";
    $testUser = $db->fetchOne("SELECT password FROM users WHERE username = 'resident'");
    
    if (password_verify('resident123', $testUser['password'])) {
        echo "<p style='color: green;'>✓ Password verification works correctly!</p>";
    } else {
        echo "<p style='color: red;'>❌ Password verification failed!</p>";
    }
    
    // Final result
    echo "<div style='background: #d4edda; padding: 20px; border: 1px solid #28a745; border-radius: 5px; margin-top: 30px;'>";
    echo "<h3 style='color: #155724; margin-top: 0;'>✅ Resident Login Fixed!</h3>";
    echo "<p style='margin: 5px 0;'><strong>Username:</strong> resident</p>";
    echo "<p style='margin: 5px 0;'><strong>Password:</strong> resident123</p>";
    echo "<p style='margin: 15px 0 5px 0;'><a href='public/login.php' style='display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Try Login Now</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 20px; border: 1px solid #dc3545; border-radius: 5px;'>";
    echo "<h3 style='color: #721c24; margin-top: 0;'>❌ Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Fix Resident Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
        }
    </style>
</head>
<body>
</body>
</html>
