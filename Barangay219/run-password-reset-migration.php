<?php
/**
 * E-Barangay Information Management System
 * Password Reset System Migration Runner
 * 
 * Run this script to apply the password reset database migration
 * Usage: php run-password-reset-migration.php
 */

define('ACCESS_ALLOWED', true);

// Require database configuration
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

// Get the migration SQL file
$migration_file = __DIR__ . '/database/migrations/003_password_reset_system.sql';

if (!file_exists($migration_file)) {
    echo "❌ Migration file not found: {$migration_file}\n";
    exit(1);
}

echo "===========================================\n";
echo "  E-Barangay Password Reset Migration\n";
echo "===========================================\n\n";

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Read migration file
    $sql = file_get_contents($migration_file);
    
    if ($sql === false) {
        echo "❌ Failed to read migration file\n";
        exit(1);
    }
    
    echo "📝 Running migration...\n\n";
    
    // Split SQL statements and execute them
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', trim($stmt));
        }
    );
    
    $success_count = 0;
    $skip_count = 0;
    
    foreach ($statements as $statement) {
        // Skip comments
        if (empty(trim($statement)) || preg_match('/^--/', trim($statement))) {
            continue;
        }
        
        try {
            // Try to execute the statement
            $conn->exec($statement . ';');
            $success_count++;
            
            // Extract the action for display
            if (preg_match('/CREATE TABLE.*`(\w+)`/i', $statement, $matches)) {
                echo "✓ Created table: {$matches[1]}\n";
            } elseif (preg_match('/ALTER TABLE.*`(\w+)`/i', $statement, $matches)) {
                echo "✓ Altered table: {$matches[1]}\n";
            } elseif (preg_match('/ADD COLUMN.*`(\w+)`/i', $statement, $matches)) {
                echo "✓ Added column: {$matches[1]}\n";
            } else {
                echo "✓ Executed statement\n";
            }
        } catch (PDOException $e) {
            // Check if it's a "table already exists" or "column already exists" error
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate column') !== false ||
                strpos($e->getMessage(), '1050') !== false ||
                strpos($e->getMessage(), '1060') !== false) {
                
                // Extract what already exists
                if (preg_match('/CREATE TABLE.*`(\w+)`/i', $statement, $matches)) {
                    echo "⊘ Table already exists: {$matches[1]}\n";
                } elseif (preg_match('/ADD COLUMN.*`(\w+)`/i', $statement, $matches)) {
                    echo "⊘ Column already exists: {$matches[1]}\n";
                } else {
                    echo "⊘ Already exists (skipped)\n";
                }
                $skip_count++;
            } else {
                // This is a real error
                echo "❌ Error: " . $e->getMessage() . "\n";
                echo "   Statement: " . substr($statement, 0, 100) . "...\n\n";
                throw $e;
            }
        }
    }
    
    echo "\n===========================================\n";
    echo "  Migration Summary\n";
    echo "===========================================\n";
    echo "✓ Executed: {$success_count} statements\n";
    if ($skip_count > 0) {
        echo "⊘ Skipped: {$skip_count} statements (already exist)\n";
    }
    echo "\n✅ Migration completed successfully!\n\n";
    
    // Verify tables were created
    echo "📋 Verifying tables...\n";
    
    $tables_to_check = [
        'password_reset_tokens',
        'password_reset_otp',
        'password_reset_rate_limit',
        'password_reset_logs'
    ];
    
    foreach ($tables_to_check as $table) {
        try {
            $quotedTable = $db->getConnection()->quote($table);
            $result = $db->fetchOne("SHOW TABLES LIKE {$quotedTable}");
            if ($result) {
                // Get row count
                $count_result = $db->fetchOne("SELECT COUNT(*) as count FROM {$table}");
                $count = $count_result['count'] ?? 0;
                echo "  ✓ {$table} ({$count} rows)\n";
            } else {
                echo "  ❌ {$table} (NOT FOUND)\n";
            }
        } catch (Exception $e) {
            echo "  ❌ {$table} (Error: " . $e->getMessage() . ")\n";
        }
    }
    
    // Check user table columns
    echo "\n📋 Verifying user table columns...\n";
    
    $user_columns = [
        'password_reset_request_id',
        'password_reset_request_method',
        'password_reset_request_expires'
    ];
    
    try {
        $result = $db->fetchAll("DESCRIBE users");
        $existing_columns = array_column($result, 'Field');
        
        foreach ($user_columns as $col) {
            if (in_array($col, $existing_columns)) {
                echo "  ✓ users.{$col}\n";
            } else {
                echo "  ❌ users.{$col} (NOT FOUND)\n";
            }
        }
    } catch (Exception $e) {
        echo "  ⚠️  Could not verify user columns: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("=", 43) . "\n";
    echo "🎉 Password Reset System is ready to use!\n";
    echo str_repeat("=", 43) . "\n\n";
    
    echo "Next steps:\n";
    echo "1. Configure email/SMS in includes/password-reset.php\n";
    echo "2. Test at: /Barangay219/public/forgot-password.php\n";
    echo "3. Check logs: SELECT * FROM password_reset_logs;\n";
    echo "4. Review: FORGOT_PASSWORD_SETUP.md for full documentation\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nPlease check the error above and try again.\n";
    exit(1);
}
?>
