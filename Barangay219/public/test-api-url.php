<?php
/**
 * Test API URL Output
 * This verifies that API_URL is being defined correctly
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>API URL Test</title>
</head>
<body>
    <h2>API URL Configuration Test</h2>
    <pre>
<?php
echo "API_URL constant defined: " . (defined('API_URL') ? 'YES' : 'NO') . "\n";
if (defined('API_URL')) {
    echo "API_URL value: " . API_URL . "\n";
    echo "API_URL length: " . strlen(API_URL) . "\n";
}

echo "\n=== JavaScript Output Test ===\n";
?>
    </pre>
    
    <script>
        window.API_URL = '<?php echo addslashes(API_URL); ?>';
        document.write('<p>API_URL in JavaScript: ' + window.API_URL + '</p>');
        document.write('<p>API_URL type: ' + typeof window.API_URL + '</p>');
        document.write('<p>Contains PHP code: ' + (window.API_URL.includes('<?php') ? 'YES (ERROR!)' : 'NO (OK)') + '</p>');
        
        // Test fetch
        console.log('Testing API URL:', window.API_URL + 'auth.php?action=check');
    </script>
    
    <h3>Raw PHP Output:</h3>
    <pre><?php echo API_URL; ?></pre>
</body>
</html>
