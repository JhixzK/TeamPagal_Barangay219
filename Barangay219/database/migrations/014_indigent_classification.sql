-- Indigent / non-indigent classification (threshold in system_settings; per-resident monthly_income)
-- Column alters for residents/applications are applied automatically on first API use (includes/indigent-classification.php).
USE `barangay219_db`;

CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` VARCHAR(64) NOT NULL,
  `setting_value` TEXT NOT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES ('indigent_threshold_monthly', '12000');
