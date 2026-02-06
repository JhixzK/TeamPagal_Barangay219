<?php
/**
 * Run Resident Registration Workflow Migration
 * Execute from browser or CLI. Creates new tables and adds columns.
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/config/database.php';

$migrationDir = __DIR__ . '/database/migrations';
$file = $migrationDir . '/001_resident_registration_workflow.sql';

if (!file_exists($file)) {
    die("Migration file not found: $file");
}

$sql = file_get_contents($file);
$sql = preg_replace('/--[^\n]*/m', '', $sql);
$statements = array_filter(array_map('trim', explode(';', $sql)), function ($s) {
    return strlen($s) > 5;
});

$db = Database::getInstance();
$ok = 0;
$skip = 0;
$err = [];

foreach ($statements as $stmt) {
    if (empty($stmt)) continue;
    try {
        $db->query($stmt);
        $ok++;
        echo "OK: " . substr($stmt, 0, 60) . "...<br>\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            $skip++;
            echo "SKIP (already exists): " . substr($stmt, 0, 60) . "...<br>\n";
        } else {
            $err[] = $e->getMessage() . " | STMT: " . substr($stmt, 0, 100);
            echo "ERROR: " . $e->getMessage() . "<br>\n";
        }
    }
}

echo "<hr>Done. OK: $ok, Skipped: $skip, Errors: " . count($err) . "<br>";
if (!empty($err)) {
    echo "<pre>" . implode("\n", $err) . "</pre>";
}
