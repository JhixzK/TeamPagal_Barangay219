<?php
/**
 * Household Module Migration Runner
 * Run this file once to set up the household members table
 * 
 * Access via: http://localhost/TeamPagal_Barangay219/Barangay219/run-household-migration.php
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/config/database.php';

// Check if already run
$db = Database::getInstance();
$tableExists = false;

try {
    $result = $db->fetchOne("SHOW TABLES LIKE 'household_members'");
    $tableExists = !empty($result);
} catch (Exception $e) {
    // Table doesn't exist yet
}

if ($tableExists) {
    die("
    <!DOCTYPE html>
    <html>
    <head>
        <title>Migration Already Run</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .info { background: #d1ecf1; border: 1px solid #bee5eb; padding: 20px; border-radius: 8px; color: #0c5460; }
            h1 { color: #0c5460; }
        </style>
    </head>
    <body>
        <div class='info'>
            <h1>✓ Migration Already Applied</h1>
            <p>The household_members table already exists. Migration has been run previously.</p>
            <p>If you need to re-run the migration, please drop the household_members table first or restore from backup.</p>
            <p><a href='public/resident_household.php'>Go to Household Module →</a></p>
        </div>
    </body>
    </html>
    ");
}

// Read migration file
$migrationFile = __DIR__ . '/database/migrations/002_household_module_enhancement.sql';

if (!file_exists($migrationFile)) {
    die("
    <!DOCTYPE html>
    <html>
    <head>
        <title>Migration File Not Found</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .error { background: #f8d7da; border: 1px solid #f5c6cb; padding: 20px; border-radius: 8px; color: #721c24; }
            h1 { color: #721c24; }
        </style>
    </head>
    <body>
        <div class='error'>
            <h1>✗ Migration File Not Found</h1>
            <p>Could not find migration file at:<br><code>$migrationFile</code></p>
            <p>Please ensure the file exists in the correct location.</p>
        </div>
    </body>
    </html>
    ");
}

$sql = file_get_contents($migrationFile);

// Split into individual statements
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    function($stmt) {
        return !empty($stmt) && 
               strpos($stmt, '--') !== 0 && 
               strlen(trim($stmt)) > 0;
    }
);

$errors = [];
$success = [];

// Execute each statement
foreach ($statements as $statement) {
    if (empty(trim($statement))) continue;
    
    try {
        $db->query($statement);
        $success[] = "Executed: " . substr(trim($statement), 0, 100) . "...";
    } catch (Exception $e) {
        $errors[] = "Error: " . $e->getMessage() . "<br>Statement: " . substr($statement, 0, 100) . "...";
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Household Module Migration</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f7fa;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 5px;
            color: #155724;
            margin: 15px 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 5px;
            color: #721c24;
            margin: 15px 0;
        }
        .details {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 15px 0;
            font-size: 13px;
            max-height: 400px;
            overflow-y: auto;
        }
        .details summary {
            cursor: pointer;
            font-weight: bold;
            color: #1976d2;
            margin-bottom: 10px;
        }
        ul {
            list-style: none;
            padding: 0;
        }
        li {
            padding: 5px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .btn {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #2980b9;
        }
        .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .success-icon { color: #28a745; }
        .error-icon { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <?php if (empty($errors)): ?>
            <div class="icon success-icon">✓</div>
            <h1>Migration Completed Successfully!</h1>
            
            <div class="success">
                <strong>Database Updated:</strong><br>
                • household_members table created<br>
                • households table extended with new fields<br>
                • Indexes and foreign keys configured<br>
                • <?php echo count($success); ?> SQL statements executed successfully
            </div>
            
            <h2>What's New:</h2>
            <ul>
                <li>✓ Household members management system</li>
                <li>✓ Auto-calculated age from date of birth</li>
                <li>✓ Household statistics (adults, minors, seniors)</li>
                <li>✓ Emergency contact information</li>
                <li>✓ Government ID and voter information tracking</li>
                <li>✓ Extended address fields (house number, street, purok)</li>
            </ul>
            
            <details class="details">
                <summary>View Execution Details (<?php echo count($success); ?> statements)</summary>
                <ul>
                    <?php foreach ($success as $msg): ?>
                        <li><?php echo htmlspecialchars($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
            
            <h2>Next Steps:</h2>
            <ol>
                <li>Login as a resident user</li>
                <li>Navigate to "Household Information" in the sidebar</li>
                <li>Start managing your household members</li>
            </ol>
            
            <a href="public/resident_household.php" class="btn">Open Household Module →</a>
            <a href="public/resident_dashboard.php" class="btn">Go to Dashboard →</a>
            
        <?php else: ?>
            <div class="icon error-icon">✗</div>
            <h1>Migration Failed</h1>
            
            <div class="error">
                <strong>Errors Encountered:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <?php if (!empty($success)): ?>
                <details class="details">
                    <summary>Successful Statements (<?php echo count($success); ?>)</summary>
                    <ul>
                        <?php foreach ($success as $msg): ?>
                            <li><?php echo htmlspecialchars($msg); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
            
            <h2>Troubleshooting:</h2>
            <ul>
                <li>Check database connection settings</li>
                <li>Verify MySQL user has CREATE TABLE and ALTER TABLE permissions</li>
                <li>Check if tables already exist</li>
                <li>Review the error messages above</li>
                <li>Try running the migration manually via phpMyAdmin</li>
            </ul>
            
            <a href="test-db.php" class="btn">Test Database Connection →</a>
        <?php endif; ?>
    </div>
</body>
</html>
