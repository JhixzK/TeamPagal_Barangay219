<?php
/**
 * Indigent / non-indigent classification helpers (household income vs configurable threshold).
 */
if (!defined('ACCESS_ALLOWED')) {
    die('Direct access not allowed');
}

function _ind_tableExists($db, $table) {
    $row = $db->fetchOne(
        "SELECT COUNT(*) AS cnt FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?",
        [$table]
    );
    return !empty($row) && (int)$row['cnt'] > 0;
}

function _ind_columnExists($db, $table, $column) {
    $row = $db->fetchOne(
        "SELECT COUNT(*) AS cnt FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
        [$table, $column]
    );
    return !empty($row) && (int)$row['cnt'] > 0;
}

function _ind_addColumnIfMissing($db, $table, $column, $definition) {
    if (!_ind_columnExists($db, $table, $column)) {
        $db->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

/**
 * Create/alter schema pieces needed for indigent classification (idempotent).
 */
function ensureIndigentClassificationSchema($db) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        if (!_ind_tableExists($db, 'system_settings')) {
            $db->query(
                "CREATE TABLE IF NOT EXISTS `system_settings` (
                  `setting_key` VARCHAR(64) NOT NULL,
                  `setting_value` TEXT NOT NULL,
                  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`setting_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        $db->query(
            "INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES ('indigent_threshold_monthly', '12000')"
        );

        _ind_addColumnIfMissing($db, 'residents', 'monthly_income', 'DECIMAL(12,2) DEFAULT NULL');
        _ind_addColumnIfMissing($db, 'resident_applications', 'monthly_income', 'DECIMAL(12,2) DEFAULT NULL');
    } catch (Throwable $e) {
        error_log('ensureIndigentClassificationSchema: ' . $e->getMessage());
    }
}

function getIndigentThresholdMonthly($db) {
    $default = defined('DEFAULT_INDIGENT_THRESHOLD_MONTHLY')
        ? (float)DEFAULT_INDIGENT_THRESHOLD_MONTHLY
        : 12000.0;

    if (!_ind_tableExists($db, 'system_settings')) {
        return max(0.0, $default);
    }

    $row = $db->fetchOne(
        "SELECT setting_value FROM system_settings WHERE setting_key = 'indigent_threshold_monthly' LIMIT 1"
    );
    if (!$row || trim((string)($row['setting_value'] ?? '')) === '') {
        return max(0.0, $default);
    }
    $v = (float)$row['setting_value'];
    if ($v < 0 || !is_finite($v)) {
        return max(0.0, $default);
    }
    return $v;
}

function residentMonthlyIncomeContribution($memberRow) {
    if (!array_key_exists('monthly_income', $memberRow)) {
        return 0.0;
    }
    $v = $memberRow['monthly_income'];
    if ($v === null || $v === '') {
        return 0.0;
    }
    $f = (float)$v;
    return $f >= 0 ? $f : 0.0;
}

/**
 * @return array{
 *   total_monthly_income: float,
 *   threshold_monthly: float,
 *   computed_status: string
 * }
 */
function computeHouseholdIndigentSnapshot($db, array $members) {
    $threshold = getIndigentThresholdMonthly($db);
    $total = 0.0;
    foreach ($members as $m) {
        $total += residentMonthlyIncomeContribution($m);
    }

    $computed = ($total <= $threshold) ? 'indigent' : 'non_indigent';

    return [
        'total_monthly_income' => round($total, 2),
        'threshold_monthly' => round($threshold, 2),
        'computed_status' => $computed,
    ];
}

function attachIndigentFieldsToHouseholdArray($db, array &$household, array $members) {
    ensureIndigentClassificationSchema($db);

    if (!_ind_columnExists($db, 'residents', 'monthly_income')) {
        $household['indigent_classification_enabled'] = false;
        return;
    }

    $household['indigent_classification_enabled'] = true;
    $snap = computeHouseholdIndigentSnapshot($db, $members);

    $household['total_household_monthly_income'] = $snap['total_monthly_income'];
    $household['indigent_threshold_monthly'] = $snap['threshold_monthly'];
    $household['computed_indigent_status'] = $snap['computed_status'];
    $household['effective_indigent_status'] = $snap['computed_status'];
}
