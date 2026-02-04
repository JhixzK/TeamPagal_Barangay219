<?php
/**
 * Test Login API Endpoint
 * This script tests if the login API is accessible and working
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/config/constants.php';

echo "<h2>Login API Test</h2>";
echo "<pre>";

echo "=== API Configuration ===\n";
echo "API URL: " . API_URL . "auth.php\n";
echo "Base URL: " . BASE_URL . "\n\n";

echo "=== Testing API Endpoint Accessibility ===\n";

// Test if API file exists
$apiFile = __DIR__ . '/api/auth.php';
if (file_exists($apiFile)) {
    echo "✓ API file exists: api/auth.php\n";
} else {
    echo "✗ API file NOT FOUND: api/auth.php\n";
    echo "</pre>";
    exit;
}

// Test API endpoint with a test request
echo "\n=== Testing API Response ===\n";
echo "Making test request to API...\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, API_URL . 'auth.php?action=check');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n";
if ($response) {
    echo "Response: " . substr($response, 0, 200) . "...\n";
    $json = json_decode($response, true);
    if ($json) {
        echo "✓ Valid JSON response\n";
        echo "Success: " . ($json['success'] ? 'true' : 'false') . "\n";
        echo "Message: " . ($json['message'] ?? 'N/A') . "\n";
    } else {
        echo "⚠ Response is not valid JSON\n";
    }
} else {
    echo "✗ No response from API\n";
}

echo "\n=== Manual Test Instructions ===\n";
echo "1. Open: " . BASE_URL . "index.php\n";
echo "2. Enter username: admin\n";
echo "3. Enter password: admin123\n";
echo "4. Click Login\n";
echo "5. Check browser console (F12) for any JavaScript errors\n";
echo "\n=== Expected Behavior ===\n";
echo "- Login form should submit\n";
echo "- You should be redirected to dashboard.php\n";
echo "- If error, check browser console for details\n";

echo "</pre>";
?>
