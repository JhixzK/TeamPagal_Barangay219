<?php
/**
 * Run all SQL migrations in database/migrations.
 * Execute from browser or CLI.
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/config/database.php';

function out($message, $isCli)
{
    echo $message . ($isCli ? PHP_EOL : "<br>\n");
}

function removeSqlComments($sql)
{
    // Strip block comments and -- line comments to keep statement parsing predictable.
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    return preg_replace('/--[^\n]*/m', '', $sql);
}

function splitSqlStatements($sql)
{
    $statements = [];
    $current = '';
    $inSingleQuote = false;
    $inDoubleQuote = false;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $prev = $i > 0 ? $sql[$i - 1] : '';

        if ($char === "'" && !$inDoubleQuote && $prev !== '\\') {
            $inSingleQuote = !$inSingleQuote;
        } elseif ($char === '"' && !$inSingleQuote && $prev !== '\\') {
            $inDoubleQuote = !$inDoubleQuote;
        }

        if ($char === ';' && !$inSingleQuote && !$inDoubleQuote) {
            $stmt = trim($current);
            if (strlen($stmt) > 0) {
                $statements[] = $stmt;
            }
            $current = '';
            continue;
        }

        $current .= $char;
    }

    $tail = trim($current);
    if (strlen($tail) > 0) {
        $statements[] = $tail;
    }

    return $statements;
}

$isCli = PHP_SAPI === 'cli';
$migrationDir = __DIR__ . '/database/migrations';

if (!is_dir($migrationDir)) {
    die("Migration directory not found: $migrationDir");
}

$migrationFiles = glob($migrationDir . '/*.sql') ?: [];
natsort($migrationFiles);
$migrationFiles = array_values($migrationFiles);

if (count($migrationFiles) === 0) {
    die("No migration files found in: $migrationDir");
}

$db = Database::getInstance();
$ok = 0;
$skip = 0;
$err = [];

out('Running migrations from: ' . $migrationDir, $isCli);

foreach ($migrationFiles as $file) {
    out('', $isCli);
    out('=== ' . basename($file) . ' ===', $isCli);

    $sql = file_get_contents($file);
    if ($sql === false) {
        $err[] = 'Unable to read file: ' . $file;
        out('ERROR: Unable to read file', $isCli);
        continue;
    }

    $statements = splitSqlStatements(removeSqlComments($sql));

    foreach ($statements as $stmt) {
        if (empty($stmt)) {
            continue;
        }

        try {
            $db->query($stmt);
            $ok++;
            out('OK: ' . substr($stmt, 0, 80) . '...', $isCli);
        } catch (Exception $e) {
            $message = $e->getMessage();
            $alreadyExists = strpos($message, 'Duplicate column') !== false ||
                strpos($message, 'already exists') !== false ||
                strpos($message, 'Duplicate key name') !== false ||
                strpos($message, 'Duplicate entry') !== false;

            if ($alreadyExists) {
                $skip++;
                out('SKIP (already exists): ' . substr($stmt, 0, 80) . '...', $isCli);
            } else {
                $err[] = $message . ' | FILE: ' . basename($file) . ' | STMT: ' . substr($stmt, 0, 140);
                out('ERROR: ' . $message, $isCli);
            }
        }
    }
}

if (!$isCli) {
    echo '<hr>';
}

out('Done. OK: ' . $ok . ', Skipped: ' . $skip . ', Errors: ' . count($err), $isCli);

if (!empty($err)) {
    if ($isCli) {
        out('', $isCli);
        out(implode(PHP_EOL, $err), $isCli);
    } else {
        echo '<pre>' . htmlspecialchars(implode("\n", $err), ENT_QUOTES, 'UTF-8') . '</pre>';
    }
}
