<?php
/**
 * Login Diagnostic Tool
 * This script helps identify login issues
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login Diagnostic Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #0d6efd; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #0d6efd; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #0d6efd; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Login Diagnostic Tool</h1>
        
        <?php
        // 1. Check Configuration
        echo '<div class="section">';
        echo '<h2>1. Configuration Check</h2>';
        echo '<table>';
        echo '<tr><th>Setting</th><th>Value</th><th>Status</th></tr>';
        
        $configs = [
            'BASE_URL' => BASE_URL,
            'API_URL' => API_URL,
            'ASSETS_URL' => ASSETS_URL,
            'DB_HOST' => DB_HOST,
            'DB_NAME' => DB_NAME,
            'DB_USER' => DB_USER,
            'DB_PASS' => DB_PASS ? '***' : '(empty)',
        ];
        
        foreach ($configs as $key => $value) {
            $status = $value ? '<span class="success">✓ Set</span>' : '<span class="error">✗ Not Set</span>';
            echo "<tr><td><strong>$key</strong></td><td>$value</td><td>$status</td></tr>";
        }
        echo '</table>';
        echo '</div>';
        
        // 2. Database Connection
        echo '<div class="section">';
        echo '<h2>2. Database Connection</h2>';
        try {
            $db = Database::getInstance();
            echo '<p class="success">✓ Database connection successful</p>';
            
            // Check if users table exists
            $tables = $db->fetchAll("SHOW TABLES LIKE 'users'");
            if (count($tables) > 0) {
                echo '<p class="success">✓ Users table exists</p>';
                
                // Check user count
                $userCount = $db->fetchOne("SELECT COUNT(*) as count FROM users");
                echo '<p class="info">ℹ Total users in database: ' . $userCount['count'] . '</p>';
                
                // Check for admin user
                $admin = $db->fetchOne("SELECT id, username, email, role, status FROM users WHERE username = 'admin'");
                if ($admin) {
                    echo '<p class="success">✓ Admin user exists</p>';
                    echo '<table>';
                    echo '<tr><th>Field</th><th>Value</th></tr>';
                    foreach ($admin as $key => $value) {
                        echo "<tr><td><strong>$key</strong></td><td>$value</td></tr>";
                    }
                    echo '</table>';
                    
                    // Check password hash
                    $passwordHash = $db->fetchOne("SELECT password FROM users WHERE username = 'admin'");
                    if ($passwordHash && strlen($passwordHash['password']) >= 60) {
                        echo '<p class="success">✓ Password hash appears valid (length: ' . strlen($passwordHash['password']) . ')</p>';
                        
                        // Test password verification
                        $testPassword = 'admin123';
                        $hash = $passwordHash['password'];
                        if (password_verify($testPassword, $hash)) {
                            echo '<p class="success">✓ Password "admin123" verifies correctly</p>';
                        } else {
                            echo '<p class="error">✗ Password "admin123" does NOT verify</p>';
                            echo '<p class="warning">⚠ You may need to reset the password hash</p>';
                        }
                    } else {
                        echo '<p class="error">✗ Password hash appears invalid</p>';
                    }
                } else {
                    echo '<p class="error">✗ Admin user NOT found</p>';
                }
            } else {
                echo '<p class="error">✗ Users table does NOT exist</p>';
            }
        } catch (Exception $e) {
            echo '<p class="error">✗ Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        echo '</div>';
        
        // 3. File Paths Check
        echo '<div class="section">';
        echo '<h2>3. File Paths Check</h2>';
        $files = [
            'API File' => __DIR__ . '/api/auth.php',
            'Auth JS' => __DIR__ . '/public/assets/css/js/auth.js',
            'Index Page' => __DIR__ . '/public/index.php',
            'Constants' => __DIR__ . '/config/constants.php',
            'Database' => __DIR__ . '/config/database.php',
        ];
        
        echo '<table>';
        echo '<tr><th>File</th><th>Path</th><th>Status</th></tr>';
        foreach ($files as $name => $path) {
            $exists = file_exists($path);
            $status = $exists ? '<span class="success">✓ Exists</span>' : '<span class="error">✗ Not Found</span>';
            echo "<tr><td><strong>$name</strong></td><td>$path</td><td>$status</td></tr>";
        }
        echo '</table>';
        echo '</div>';
        
        // 4. API URL Test
        echo '<div class="section">';
        echo '<h2>4. API Endpoint Test</h2>';
        $apiUrl = API_URL . 'auth.php?action=check';
        echo '<p class="info">Testing: <code>' . htmlspecialchars($apiUrl) . '</code></p>';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            echo '<p class="error">✗ cURL Error: ' . htmlspecialchars($curlError) . '</p>';
        } else {
            echo '<p class="info">HTTP Status Code: ' . $httpCode . '</p>';
            if ($response) {
                $json = json_decode($response, true);
                if ($json) {
                    echo '<p class="success">✓ API returns valid JSON</p>';
                    echo '<pre>' . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT)) . '</pre>';
                } else {
                    echo '<p class="warning">⚠ API response is not valid JSON</p>';
                    echo '<pre>' . htmlspecialchars(substr($response, 0, 500)) . '</pre>';
                }
            } else {
                echo '<p class="error">✗ No response from API</p>';
            }
        }
        echo '</div>';
        
        // 5. Session Check
        echo '<div class="section">';
        echo '<h2>5. Session Configuration</h2>';
        echo '<table>';
        echo '<tr><th>Setting</th><th>Value</th></tr>';
        echo '<tr><td>Session Status</td><td>' . (session_status() === PHP_SESSION_ACTIVE ? '<span class="success">Active</span>' : '<span class="warning">Not Active</span>') . '</td></tr>';
        echo '<tr><td>Session Name</td><td>' . SESSION_NAME . '</td></tr>';
        echo '<tr><td>Session Lifetime</td><td>' . SESSION_LIFETIME . ' seconds</td></tr>';
        echo '<tr><td>Session Save Path</td><td>' . session_save_path() . '</td></tr>';
        echo '</table>';
        echo '</div>';
        
        // 6. Recommendations
        echo '<div class="section">';
        echo '<h2>6. Recommendations</h2>';
        echo '<ul>';
        echo '<li>Check browser console (F12) for JavaScript errors when attempting to login</li>';
        echo '<li>Verify that Apache and MySQL are running in XAMPP</li>';
        echo '<li>Ensure the database <code>' . DB_NAME . '</code> exists and has been imported</li>';
        echo '<li>Check that the API URL is accessible: <a href="' . API_URL . 'auth.php?action=check" target="_blank">' . API_URL . 'auth.php?action=check</a></li>';
        echo '<li>Try accessing the login page: <a href="' . BASE_URL . 'index.php" target="_blank">' . BASE_URL . 'index.php</a></li>';
        echo '</ul>';
        echo '</div>';
        ?>
    </div>
</body>
</html>
